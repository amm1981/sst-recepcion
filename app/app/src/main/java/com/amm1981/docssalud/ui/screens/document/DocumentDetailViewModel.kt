package com.amm1981.docssalud.ui.screens.document

import android.content.SharedPreferences
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

data class DocumentDetailState(
    val isLoading: Boolean = false,
    val document: DocumentUi? = null,
    val error: String? = null
)

@HiltViewModel
class DocumentDetailViewModel @Inject constructor(
    private val documentRepository: DocumentRepository,
    private val prefs: SharedPreferences
) : ViewModel() {

    private val _state = MutableStateFlow(DocumentDetailState())
    val state: StateFlow<DocumentDetailState> = _state.asStateFlow()

    fun loadDocument(id: String) {
        viewModelScope.launch {
            _state.value = DocumentDetailState(isLoading = true)
            val result = documentRepository.getDocument(id)
            _state.value = result.fold(
                onSuccess = { DocumentDetailState(document = it) },
                onFailure = { DocumentDetailState(error = it.message ?: "No se pudo cargar el documento") }
            )
        }
    }

    fun getAuthToken(): String = prefs.getString("auth_token", null) ?: ""
}
