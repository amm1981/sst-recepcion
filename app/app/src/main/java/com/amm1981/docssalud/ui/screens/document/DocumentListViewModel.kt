package com.amm1981.docssalud.ui.screens.document

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.amm1981.docssalud.data.connectivity.ConnectivityMonitor
import com.amm1981.docssalud.data.repository.AuthRepository
import com.amm1981.docssalud.data.repository.DocumentRepository
import com.amm1981.docssalud.data.repository.DocumentUi
import com.amm1981.docssalud.data.repository.RegistrarUi
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch
import javax.inject.Inject

data class DocumentListState(
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val isOnline: Boolean = true,
    val isUploading: Boolean = false,
    val pendingUploadCount: Int = 0,
    val documents: List<DocumentUi> = emptyList(),
    val registrars: List<RegistrarUi> = emptyList(),
    val canFilterByRegistrar: Boolean = false,
    val dateFrom: String = "",
    val dateTo: String = "",
    val createdBy: Int? = null,
    val error: String? = null,
    val message: String? = null
)

@HiltViewModel
class DocumentListViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val documentRepository: DocumentRepository,
    private val connectivityMonitor: ConnectivityMonitor
) : ViewModel() {

    private val _state = MutableStateFlow(DocumentListState())
    val state: StateFlow<DocumentListState> = _state.asStateFlow()

    init {
        _state.value = _state.value.copy(canFilterByRegistrar = authRepository.canFilterByRegistrar())
        observeConnectivity()
        refreshPendingUploadCount()
        loadRegistrars()
    }

    private fun observeConnectivity() {
        viewModelScope.launch {
            connectivityMonitor.isOnline.collectLatest { isOnline ->
                _state.value = _state.value.copy(isOnline = isOnline)
            }
        }
    }

    fun refreshPendingUploadCount() {
        viewModelScope.launch {
            _state.value = _state.value.copy(
                pendingUploadCount = documentRepository.pendingUploadCount()
            )
        }
    }

    fun loadDocuments(status: String) {
        viewModelScope.launch {
            val localDocuments = documentRepository.getLocalDocuments(status)
            _state.value = _state.value.copy(
                isLoading = localDocuments.isEmpty(),
                isRefreshing = true,
                documents = localDocuments,
                error = null
            )

            val filters = _state.value
            val result = documentRepository.getDocuments(
                status = status,
                dateFrom = filters.dateFrom,
                dateTo = filters.dateTo,
                createdBy = filters.createdBy
            )
            _state.value = result.fold(
                onSuccess = {
                    _state.value.copy(
                        isLoading = false,
                        isRefreshing = false,
                        documents = it,
                        error = null
                    )
                },
                onFailure = {
                    _state.value.copy(
                        isLoading = false,
                        documents = localDocuments,
                        isRefreshing = false,
                        error = it.message ?: "No se pudieron cargar los documentos"
                    )
                }
            )
            refreshPendingUploadCount()
        }
    }

    fun sendPendingDocuments(currentStatus: String) {
        viewModelScope.launch {
            val pending = documentRepository.pendingUploadCount()
            if (pending == 0) {
                _state.value = _state.value.copy(message = "No hay documentos pendientes por enviar.")
                return@launch
            }
            if (!_state.value.isOnline) {
                _state.value = _state.value.copy(message = "Sin conexion. Los documentos siguen guardados en el equipo.")
                return@launch
            }

            _state.value = _state.value.copy(isUploading = true, message = null)
            val result = documentRepository.syncPendingDocuments()
            val remaining = documentRepository.pendingUploadCount()
            val message = result.fold(
                onSuccess = { uploaded ->
                    when {
                        remaining == 0 -> "Documentos pendientes enviados."
                        uploaded > 0 -> "Se enviaron $uploaded documentos. Quedan $remaining pendientes."
                        else -> "No se pudieron enviar los documentos pendientes."
                    }
                },
                onFailure = { it.message ?: "No se pudieron enviar los documentos pendientes." }
            )

            _state.value = _state.value.copy(
                isUploading = false,
                pendingUploadCount = remaining,
                message = message
            )
            loadDocuments(currentStatus)
        }
    }

    fun updateDateFrom(value: String) {
        _state.value = _state.value.copy(dateFrom = value)
        loadRegistrars()
    }

    fun updateDateTo(value: String) {
        _state.value = _state.value.copy(dateTo = value)
        loadRegistrars()
    }

    fun updateCreatedBy(value: Int?) {
        _state.value = _state.value.copy(createdBy = value)
    }

    fun clearFilters() {
        _state.value = _state.value.copy(dateFrom = "", dateTo = "", createdBy = null)
        loadRegistrars()
    }

    private fun loadRegistrars() {
        if (!_state.value.canFilterByRegistrar) return

        viewModelScope.launch {
            val filters = _state.value
            documentRepository.getRegistrars(filters.dateFrom, filters.dateTo).onSuccess { registrars ->
                _state.value = _state.value.copy(registrars = registrars)
            }
        }
    }

    fun consumeMessage() {
        _state.value = _state.value.copy(message = null)
    }
}
