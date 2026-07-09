package com.amm1981.docssalud.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.amm1981.docssalud.data.repository.AuthRepository
import com.amm1981.docssalud.data.repository.SyncRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class MainState(
    val userName: String = "Usuario",
    val userEmail: String = "",
    val isSyncing: Boolean = false,
    val syncPercent: Int = 0,
    val syncProgressMessage: String = "",
    val showSyncDialog: Boolean = false,
    val syncMessage: String? = null,
    val syncRevision: Int = 0
)

@HiltViewModel
class MainViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val syncRepository: SyncRepository
) : ViewModel() {

    private val _state = MutableStateFlow(
        MainState(
            userName = authRepository.userName(),
            userEmail = authRepository.userEmail()
        )
    )
    val state: StateFlow<MainState> = _state.asStateFlow()

    init {
        refreshUser()
    }

    private fun refreshUser() {
        viewModelScope.launch {
            authRepository.refreshUser()
            _state.value = _state.value.copy(
                userName = authRepository.userName(),
                userEmail = authRepository.userEmail()
            )
        }
    }

    fun syncMasterData(forceWorkers: Boolean = true, showMessage: Boolean = true) {
        if (_state.value.isSyncing) return

        viewModelScope.launch {
            _state.value = _state.value.copy(
                isSyncing = true,
                syncPercent = 0,
                syncProgressMessage = "Preparando actualizacion...",
                showSyncDialog = showMessage,
                syncMessage = null
            )
            val result = syncRepository.syncAll(forceWorkers = forceWorkers) { progress ->
                _state.value = _state.value.copy(
                    syncPercent = progress.percent,
                    syncProgressMessage = progress.message
                )
            }
            _state.value = _state.value.copy(
                isSyncing = false,
                syncPercent = if (result.isSuccess) 100 else _state.value.syncPercent,
                showSyncDialog = false,
                syncRevision = if (result.isSuccess) _state.value.syncRevision + 1 else _state.value.syncRevision,
                syncMessage = if (showMessage) {
                    result.fold(
                        onSuccess = { "Data Maestra actualizada." },
                        onFailure = { it.message ?: "No se pudo actualizar la Data Maestra." }
                    )
                } else {
                    null
                }
            )
        }
    }

    fun syncOnStart() {
        syncMasterData(forceWorkers = false, showMessage = false)
    }

    fun consumeSyncMessage() {
        _state.value = _state.value.copy(syncMessage = null)
    }

    fun logout() {
        authRepository.logout()
    }
}
