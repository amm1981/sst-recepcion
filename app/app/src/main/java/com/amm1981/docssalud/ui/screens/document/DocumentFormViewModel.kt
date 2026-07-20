package com.amm1981.docssalud.ui.screens.document

import android.content.Context
import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import com.amm1981.docssalud.data.connectivity.ConnectivityMonitor
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
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch
import javax.inject.Inject

data class DocumentFormState(
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val isSaved: Boolean = false,
    val error: String? = null,
    val documentTypes: List<CatalogEntity> = emptyList(),
    val deliveryRelations: List<CatalogEntity> = emptyList(),
    val selectedWorker: WorkerEntity? = null,
    val workerResults: List<WorkerEntity> = emptyList()
)

@HiltViewModel
class DocumentFormViewModel @Inject constructor(
    @ApplicationContext private val context: Context,
    private val workerDao: WorkerDao,
    private val catalogDao: CatalogDao,
    private val documentRepository: DocumentRepository,
    private val syncRepository: SyncRepository,
    private val connectivityMonitor: ConnectivityMonitor
) : ViewModel() {

    private val _state = MutableStateFlow(DocumentFormState())
    val state: StateFlow<DocumentFormState> = _state.asStateFlow()
    private var isOnline = true

    init {
        viewModelScope.launch {
            connectivityMonitor.isOnline.collectLatest { online ->
                isOnline = online
            }
        }
    }

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

    fun searchWorker(query: String) {
        viewModelScope.launch {
            val term = query.trim()
            if (term.length < 2) {
                _state.value = _state.value.copy(selectedWorker = null, workerResults = emptyList(), error = "Ingrese DNI, nombre o apellidos.")
                return@launch
            }
            val worker = workerDao.findByDni(term)
            val results = if (worker != null) listOf(worker) else workerDao.search(term)
            _state.value = if (worker != null) {
                _state.value.copy(selectedWorker = worker, workerResults = results, error = null)
            } else if (results.isNotEmpty()) {
                _state.value.copy(selectedWorker = results.first(), workerResults = results, error = null)
            } else {
                _state.value.copy(selectedWorker = null, workerResults = emptyList(), error = "Trabajador no encontrado.")
            }
        }
    }

    fun selectWorker(worker: WorkerEntity) {
        _state.value = _state.value.copy(selectedWorker = worker, error = null)
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
        if (_state.value.isSaving) return

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

        _state.value = _state.value.copy(isSaving = true, error = null)
        viewModelScope.launch {
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
                val offlineUuid = result.getOrNull()
                if (isOnline) {
                    val syncResult = offlineUuid
                        ?.let { documentRepository.syncQueuedDocument(it) }
                        ?: Result.failure(IllegalStateException("No se pudo identificar el documento local."))
                    if (syncResult.getOrDefault(false).not()) {
                        enqueueSyncWork()
                    }
                } else {
                    enqueueSyncWork()
                }
                _state.value = _state.value.copy(isSaving = false, isSaved = true)
            } else {
                _state.value = _state.value.copy(
                    isSaving = false,
                    error = result.exceptionOrNull()?.message ?: "Error al guardar el documento"
                )
            }
        }
    }

    private fun enqueueSyncWork() {
        val workRequest = OneTimeWorkRequestBuilder<SyncWorker>().build()
        WorkManager.getInstance(context).enqueueUniqueWork(
            "document-sync",
            ExistingWorkPolicy.KEEP,
            workRequest
        )
    }
}
