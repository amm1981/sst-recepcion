package com.amm1981.docssalud.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.amm1981.docssalud.data.repository.AuthRepository
import com.amm1981.docssalud.data.repository.DocumentRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class HomeState(
    val userName: String = "Usuario",
    val pendingCount: Int = 0,
    val receivedCount: Int = 0,
    val registeredCount: Int = 0,
    val rejectedCount: Int = 0,
    val isLoading: Boolean = false
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val documentRepository: DocumentRepository
) : ViewModel() {

    // Load user name IMMEDIATELY from prefs (no network needed)
    private val _state = MutableStateFlow(HomeState(userName = authRepository.userName()))
    val state: StateFlow<HomeState> = _state.asStateFlow()

    fun loadCounts() {
        viewModelScope.launch {
            val localCounts = documentRepository.getLocalCounts()
            _state.value = _state.value.copy(
                isLoading = true,
                pendingCount = localCounts.pending,
                receivedCount = localCounts.received,
                registeredCount = localCounts.registered,
                rejectedCount = localCounts.rejected
            )
            val counts = documentRepository.getCounts().getOrNull()
            _state.value = _state.value.copy(
                isLoading = false,
                pendingCount = counts?.pending ?: localCounts.pending,
                receivedCount = counts?.received ?: localCounts.received,
                registeredCount = counts?.registered ?: localCounts.registered,
                rejectedCount = counts?.rejected ?: localCounts.rejected
            )
        }
    }
}
