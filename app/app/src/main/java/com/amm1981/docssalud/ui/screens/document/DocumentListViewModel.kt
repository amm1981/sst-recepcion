package com.amm1981.docssalud.ui.screens.document

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.amm1981.docssalud.data.repository.DocumentRepository
import com.amm1981.docssalud.data.repository.DocumentUi
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class DocumentListState(
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val documents: List<DocumentUi> = emptyList(),
    val error: String? = null
)

@HiltViewModel
class DocumentListViewModel @Inject constructor(
    private val documentRepository: DocumentRepository
) : ViewModel() {

    private val _state = MutableStateFlow(DocumentListState())
    val state: StateFlow<DocumentListState> = _state.asStateFlow()

    fun loadDocuments(status: String) {
        viewModelScope.launch {
            val localDocuments = documentRepository.getLocalDocuments(status)
            _state.value = _state.value.copy(
                isLoading = localDocuments.isEmpty(),
                isRefreshing = true,
                documents = localDocuments,
                error = null
            )

            val result = documentRepository.getDocuments(status)
            _state.value = result.fold(
                onSuccess = { DocumentListState(documents = it, isRefreshing = false) },
                onFailure = {
                    DocumentListState(
                        documents = localDocuments,
                        isRefreshing = false,
                        error = it.message ?: "No se pudieron cargar los documentos"
                    )
                }
            )
        }
    }
}
