package com.amm1981.docssalud.ui.screens.auth

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

sealed class AuthState {
    object Idle : AuthState()
    object Loading : AuthState()
    object Success : AuthState()
    data class Error(val message: String) : AuthState()
    data class Warning(val message: String) : AuthState()
}

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val syncRepository: SyncRepository
) : ViewModel() {

    private val _authState = MutableStateFlow<AuthState>(AuthState.Idle)
    val authState: StateFlow<AuthState> = _authState.asStateFlow()

    fun checkLoginStatus() {
        if (authRepository.isLoggedIn()) {
            _authState.value = AuthState.Success
        }
    }

    fun login(user: String, pass: String) {
        viewModelScope.launch {
            _authState.value = AuthState.Loading
            val result = authRepository.login(user, pass)
            if (result.isSuccess) {
                // Sincronizar catálogos y trabajadores después de loguearse exitosamente
                val syncResult = syncRepository.syncAll(forceWorkers = true)
                _authState.value = if (syncResult.isSuccess) {
                    AuthState.Success
                } else {
                    AuthState.Warning(
                        "Sesion iniciada. No se pudo sincronizar la Data Maestra inicial: ${syncResult.exceptionOrNull()?.message ?: "reintente desde el menu lateral."}"
                    )
                }
            } else {
                _authState.value = AuthState.Error(result.exceptionOrNull()?.message ?: "Error desconocido")
            }
        }
    }

    fun logout() {
        authRepository.logout()
        _authState.value = AuthState.Idle
    }
}
