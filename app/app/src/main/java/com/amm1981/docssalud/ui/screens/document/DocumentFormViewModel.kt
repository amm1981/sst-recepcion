package com.amm1981.docssalud.ui.screens.document

import android.content.Context
import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import com.amm1981.docssalud.data.local.dao.CatalogDao
import com.amm1981.docssalud.data.local.dao.WorkerDao
import com.amm1981.docssalud.data.local.entity.CatalogEntity
import com.amm1981.docssalud.data.local.entity.WorkerEntity
import com.amm1981.docssalud.data.repository.DocumentRepository
import com.amm1981.docssalud.data.repository.SyncRepository
import com.amm1981.docssalud.workers.SyncWorker
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class DocumentFormState(
    val isLoading: Boolean = false,
    val isSaved: Boolean = false,
    val error: String? = null,
    val documentTypes: List<CatalogEntity> = emptyList(),
    val deliveryRelations: List<CatalogEntity> = emptyList(),
    val selectedWorker: WorkerEntity? = null
)

@HiltViewModel
class DocumentFormViewModel @Inject constructor(
    @ApplicationContext private val context: Context,
    private val workerDao: WorkerDao,
    private val catalogDao: CatalogDao,
    private val documentRepository: DocumentRepository,
    private val syncRepository: SyncRepository
) : ViewModel() {

    private val _state = MutableStateFlow(DocumentFormState())
    val state: StateFlow<DocumentFormState> = _state.asStateFlow()

    fun loadInitialData() {
        viewModelScope.launch {
            var docTypes = catalogDao.getByType("DOCUMENT_TYPE")
            var relations = catalogDao.getByType("RELATION")
            var loadError: String? = null

            _state.value = _state.value.copy(
                isLoading = docTypes.isEmpty() || relations.isEmpty(),
                error = null,
                documentTypes = docTypes,
                deliveryRelations = relations
            )
            
            if (docTypes.isEmpty() || relations.isEmpty()) {
                val syncResult = syncRepository.syncAll()
                if (syncResult.isSuccess) {
                    docTypes = catalogDao.getByType("DOCUMENT_TYPE")
                    relations = catalogDao.getByType("RELATION")
                } else {
                    loadError = "No se pudo cargar la Data Maestra. Use el menu lateral para sincronizar."
                }
            }
            
            _state.value = _state.value.copy(
                isLoading = false,
                error = loadError,
                documentTypes = docTypes,
                deliveryRelations = relations
            )
        }
    }

    fun searchWorker(dni: String) {
        viewModelScope.launch {
            val worker = workerDao.findByDni(dni.trim())
            _state.value = if (worker != null) {
                _state.value.copy(selectedWorker = worker, error = null)
            } else {
                _state.value.copy(selectedWorker = null, error = "Trabajador no encontrado.")
            }
        }
    }

    fun saveDocument(
        documentTypeId: Int?,
        deliveryRelationId: Int?,
        deliveryRelationDetail: String?,
        delivererName: String,
        delivererDocument: String?,
        contactNumber: String,
        observation: String?,
        delivererPhotoUri: Uri?,
        medicalDocumentUri: Uri?,
        annexUris: List<Uri>
    ) {
        val worker = _state.value.selectedWorker
        val documentType = _state.value.documentTypes.firstOrNull { it.id == documentTypeId }
        val relation = _state.value.deliveryRelations.firstOrNull { it.id == deliveryRelationId }

        when {
            documentType == null -> {
                _state.value = _state.value.copy(error = "Seleccione el tipo de documento.")
                return
            }
            worker == null -> {
                _state.value = _state.value.copy(error = "Debe buscar un trabajador primero.")
                return
            }
            relation == null -> {
                _state.value = _state.value.copy(error = "Seleccione la relación de entrega.")
                return
            }
            relation.requiresDetail && deliveryRelationDetail.isNullOrBlank() -> {
                _state.value = _state.value.copy(error = "Debe detallar la relación de entrega.")
                return
            }
            delivererName.isBlank() -> {
                _state.value = _state.value.copy(error = "Ingrese el nombre de quien entrega.")
                return
            }
            contactNumber.isBlank() -> {
                _state.value = _state.value.copy(error = "Ingrese el número de contacto.")
                return
            }
            medicalDocumentUri == null -> {
                _state.value = _state.value.copy(error = "Adjunte la foto o archivo del documento.")
                return
            }
            annexUris.size > 4 -> {
                _state.value = _state.value.copy(error = "Solo puede adjuntar hasta 4 anexos.")
                return
            }
        }

        viewModelScope.launch {
            _state.value = _state.value.copy(isLoading = true, error = null)
            val result = documentRepository.enqueueDocument(
                medicalDocumentTypeId = documentType.id,
                medicalDocumentTypeName = documentType.name,
                workerDni = worker.dni,
                workerName = "${worker.firstName} ${worker.lastName}",
                deliveryRelationId = relation.id,
                deliveryRelationDetail = deliveryRelationDetail?.takeIf { it.isNotBlank() },
                delivererName = delivererName,
                delivererDocument = delivererDocument?.takeIf { it.isNotBlank() },
                contactNumber = contactNumber,
                observation = observation?.takeIf { it.isNotBlank() },
                delivererPhotoUri = delivererPhotoUri,
                medicalDocumentUri = medicalDocumentUri,
                annexUris = annexUris
            )

            if (result.isSuccess) {
                val workRequest = OneTimeWorkRequestBuilder<SyncWorker>().build()
                WorkManager.getInstance(context).enqueue(workRequest)
                _state.value = _state.value.copy(isLoading = false, isSaved = true)
            } else {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = result.exceptionOrNull()?.message ?: "Error al guardar el documento"
                )
            }
        }
    }
}
