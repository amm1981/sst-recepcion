package com.amm1981.docssalud.ui.screens.document

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.amm1981.docssalud.data.local.dao.CatalogDao
import com.amm1981.docssalud.data.local.dao.WorkerDao
import com.amm1981.docssalud.data.local.entity.CatalogEntity
import com.amm1981.docssalud.data.local.entity.WorkerEntity
import com.amm1981.docssalud.data.ocr.DocumentDateExtractor
import com.amm1981.docssalud.data.repository.DocumentRepository
import com.amm1981.docssalud.data.repository.SyncRepository
import com.amm1981.docssalud.workers.DocumentSyncScheduler
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.text.Normalizer
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.ResolverStyle
import java.util.Locale
import javax.inject.Inject

data class DocumentFormState(
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val isSaved: Boolean = false,
    val error: String? = null,
    val documentTypes: List<CatalogEntity> = emptyList(),
    val deliveryRelations: List<CatalogEntity> = emptyList(),
    val selectedWorker: WorkerEntity? = null,
    val workerResults: List<WorkerEntity> = emptyList(),
    val isExtractingDate: Boolean = false,
    val extractedDateCandidates: List<String> = emptyList(),
    val dateExtractionMessage: String? = null
)

@HiltViewModel
class DocumentFormViewModel @Inject constructor(
    private val workerDao: WorkerDao,
    private val catalogDao: CatalogDao,
    private val documentRepository: DocumentRepository,
    private val syncRepository: SyncRepository,
    private val documentSyncScheduler: DocumentSyncScheduler,
    private val documentDateExtractor: DocumentDateExtractor
) : ViewModel() {
    private val displayDateFormatter = DateTimeFormatter.ofPattern("dd-MM-uuuu").withResolverStyle(ResolverStyle.STRICT)
    private val apiDateFormatter = DateTimeFormatter.ISO_LOCAL_DATE

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

    fun searchWorker(query: String) {
        viewModelScope.launch {
            val term = query.trim()
            if (term.length < 2) {
                _state.value = _state.value.copy(selectedWorker = null, workerResults = emptyList(), error = "Ingrese DNI, nombre o apellidos.")
                return@launch
            }
            _state.value = _state.value.copy(isLoading = true, error = null)

            if (workerDao.count() == 0) {
                val syncResult = syncRepository.syncAll(forceWorkers = true)
                if (syncResult.isFailure && workerDao.count() == 0) {
                    _state.value = _state.value.copy(
                        isLoading = false,
                        selectedWorker = null,
                        workerResults = emptyList(),
                        error = "No hay trabajadores sincronizados. Sincronice Data Maestra e intente nuevamente."
                    )
                    return@launch
                }
            }

            val worker = workerDao.findByDni(term)
            val directResults = if (worker != null) listOf(worker) else workerDao.search(term)
            val results = if (directResults.isNotEmpty()) directResults else searchWorkersNormalized(term)
            _state.value = if (worker != null) {
                _state.value.copy(isLoading = false, selectedWorker = worker, workerResults = results, error = null)
            } else if (results.size == 1) {
                _state.value.copy(isLoading = false, selectedWorker = results.first(), workerResults = results, error = null)
            } else if (results.isNotEmpty()) {
                _state.value.copy(isLoading = false, selectedWorker = null, workerResults = results, error = "Se encontraron varios trabajadores. Seleccione uno para continuar.")
            } else {
                _state.value.copy(isLoading = false, selectedWorker = null, workerResults = emptyList(), error = "Trabajador no encontrado.")
            }
        }
    }

    fun previewWorkers(query: String) {
        viewModelScope.launch {
            val term = query.trim()
            if (term.length < 2) {
                _state.value = _state.value.copy(workerResults = emptyList(), error = null)
                return@launch
            }

            if (workerDao.count() == 0) {
                syncRepository.syncAll(forceWorkers = true)
            }

            val exact = workerDao.findByDni(term)
            val directResults = if (exact != null) listOf(exact) else workerDao.search(term)
            val results = if (directResults.isNotEmpty()) directResults else searchWorkersNormalized(term)
            _state.value = _state.value.copy(workerResults = results.take(8), error = null)
        }
    }

    fun selectWorker(worker: WorkerEntity) {
        _state.value = _state.value.copy(selectedWorker = worker, workerResults = emptyList(), error = null)
    }

    fun clearWorkerSelection() {
        _state.value = _state.value.copy(selectedWorker = null)
    }

    fun extractDateFromDocument(uri: Uri) {
        _state.value = _state.value.copy(
            isExtractingDate = true,
            extractedDateCandidates = emptyList(),
            dateExtractionMessage = "Procesando OCR local..."
        )
        viewModelScope.launch {
            runCatching { documentDateExtractor.extract(uri) }
                .onSuccess { result ->
                    _state.value = _state.value.copy(
                        isExtractingDate = false,
                        extractedDateCandidates = result.candidates,
                        dateExtractionMessage = result.message,
                        error = null
                    )
                }
                .onFailure {
                    _state.value = _state.value.copy(
                        isExtractingDate = false,
                        extractedDateCandidates = emptyList(),
                        dateExtractionMessage = "No se pudo reconocer la fecha. Ingrese la fecha manualmente."
                    )
                }
        }
    }

    fun saveDocument(
        documentTypeId: Int?,
        documentDate: String,
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
        val parsedDocumentDate = parseDisplayDate(documentDate)
        val documentDateError = validateDocumentDate(documentDate, documentType)

        when {
            documentType == null -> {
                _state.value = _state.value.copy(error = "Seleccione el tipo de documento.")
                return
            }
            documentDateError != null -> {
                _state.value = _state.value.copy(error = documentDateError)
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
                workerPositionSnapshot = worker.position,
                workerManagementIdSnapshot = worker.managementId,
                workerManagementNameSnapshot = worker.managementName,
                workerSectorIdSnapshot = worker.sectorId,
                workerSectorNameSnapshot = worker.sectorName,
                documentDate = parsedDocumentDate?.format(apiDateFormatter) ?: documentDate,
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
                documentSyncScheduler.enqueue()
                _state.value = _state.value.copy(isSaving = false, isSaved = true)
            } else {
                _state.value = _state.value.copy(
                    isSaving = false,
                    error = result.exceptionOrNull()?.message ?: "Error al guardar el documento"
                )
            }
        }
    }

    fun validateDocumentDate(documentDate: String, documentType: CatalogEntity?): String? {
        if (documentDate.isBlank()) return "Ingrese la fecha del documento."
        val parsed = parseDisplayDate(documentDate) ?: return "Ingrese una fecha valida en formato dd-mm-aaaa."
        val today = LocalDate.now()
        val minDate = today.minusDays(allowedPastDays(documentType).toLong())
        return if (parsed.isBefore(minDate) || parsed.isAfter(today)) {
            val label = if (allowedPastDays(documentType) == 1) "1 dia anterior" else "${allowedPastDays(documentType)} dias anteriores"
            "La fecha debe estar entre ${minDate.format(displayDateFormatter)} y ${today.format(displayDateFormatter)} para este tipo de documento ($label)."
        } else {
            null
        }
    }

    private fun parseDisplayDate(value: String): LocalDate? {
        val clean = value.trim().replace('/', '-')
        return runCatching { LocalDate.parse(clean, displayDateFormatter) }.getOrNull()
    }

    private fun allowedPastDays(documentType: CatalogEntity?): Int {
        val normalized = Normalizer.normalize("${documentType?.code.orEmpty()} ${documentType?.name.orEmpty()}", Normalizer.Form.NFD)
            .replace(Regex("\\p{Mn}+"), "")
            .uppercase(Locale("es", "PE"))
        return if (normalized.contains("ATENCION")) 1 else 2
    }

    private suspend fun searchWorkersNormalized(term: String): List<WorkerEntity> {
        val normalizedTerm = normalizeForSearch(term)
        return workerDao.getAll()
            .asSequence()
            .filter { worker ->
                normalizeForSearch(worker.dni).contains(normalizedTerm) ||
                    normalizeForSearch(worker.firstName).contains(normalizedTerm) ||
                    normalizeForSearch(worker.lastName).contains(normalizedTerm) ||
                    normalizeForSearch("${worker.firstName} ${worker.lastName}").contains(normalizedTerm)
            }
            .take(20)
            .toList()
    }

    private fun normalizeForSearch(value: String): String {
        return Normalizer.normalize(value, Normalizer.Form.NFD)
            .replace(Regex("\\p{Mn}+"), "")
            .uppercase(Locale("es", "PE"))
            .trim()
    }
}
