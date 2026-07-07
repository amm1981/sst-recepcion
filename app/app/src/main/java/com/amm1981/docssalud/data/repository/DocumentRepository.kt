package com.amm1981.docssalud.data.repository

import android.content.Context
import android.database.Cursor
import android.net.Uri
import android.provider.OpenableColumns
import com.amm1981.docssalud.data.api.DocsSaludApi
import com.amm1981.docssalud.data.api.MedicalDocumentDto
import com.amm1981.docssalud.data.local.dao.SyncQueueDao
import com.amm1981.docssalud.data.local.entity.SyncQueueEntity
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.UUID
import java.io.File
import java.io.FileOutputStream
import javax.inject.Inject
import javax.inject.Singleton

data class DocumentFileUi(
    val id: Int? = null,
    val type: String,
    val name: String,
    val uri: String? = null
)

data class DocumentHistoryUi(
    val status: String,
    val previousStatus: String?,
    val observation: String?,
    val date: String,
    val userName: String?
)

data class DocumentUi(
    val id: String,
    val isLocal: Boolean,
    val status: String,
    val typeName: String,
    val createdAt: String,
    val workerDni: String,
    val workerName: String,
    val contactNumber: String,
    val delivererName: String,
    val delivererDocument: String?,
    val observation: String?,
    val files: List<DocumentFileUi>,
    val history: List<DocumentHistoryUi>
)

data class DocumentCounts(
    val pending: Int = 0,
    val received: Int = 0,
    val registered: Int = 0,
    val rejected: Int = 0
)

@Singleton
class DocumentRepository @Inject constructor(
    @ApplicationContext private val context: Context,
    private val syncQueueDao: SyncQueueDao,
    private val api: DocsSaludApi
) {
    suspend fun enqueueDocument(
        medicalDocumentTypeId: Int,
        medicalDocumentTypeName: String,
        workerDni: String,
        workerName: String,
        deliveryRelationId: Int,
        deliveryRelationDetail: String?,
        delivererName: String,
        delivererDocument: String?,
        contactNumber: String,
        observation: String?,
        delivererPhotoUri: Uri?,
        medicalDocumentUri: Uri,
        annexUris: List<Uri>
    ): Result<Unit> {
        return try {
            val offlineUuid = UUID.randomUUID().toString()
            val documentDir = File(context.filesDir, "offline_documents/$offlineUuid").apply { mkdirs() }
            val persistedDelivererPhotoUri = delivererPhotoUri?.let {
                persistOfflineFile(it, documentDir, "deliverer_photo")
            }
            val persistedMedicalDocumentUri = persistOfflineFile(medicalDocumentUri, documentDir, "medical_document")
            val persistedAnnexUris = annexUris.mapIndexed { index, uri ->
                persistOfflineFile(uri, documentDir, "annex_${index + 1}")
            }
            val entity = SyncQueueEntity(
                offlineUuid = offlineUuid,
                medicalDocumentTypeId = medicalDocumentTypeId,
                medicalDocumentTypeName = medicalDocumentTypeName,
                workerDni = workerDni,
                workerName = workerName,
                deliveryRelationId = deliveryRelationId,
                deliveryRelationDetail = deliveryRelationDetail,
                delivererName = delivererName,
                delivererDocument = delivererDocument,
                contactNumber = contactNumber,
                observation = observation,
                delivererPhotoUri = persistedDelivererPhotoUri?.toString(),
                medicalDocumentUri = persistedMedicalDocumentUri.toString(),
                annexUris = persistedAnnexUris.joinToString("|") { it.toString() },
                status = "PENDING"
            )
            syncQueueDao.insert(entity)
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun processSyncQueue() {
        val pendingItems = syncQueueDao.getPending()
        for (item in pendingItems) {
            try {
                val response = api.uploadDocument(
                    offlineUuid = textPart(item.offlineUuid),
                    medicalDocumentTypeId = textPart(item.medicalDocumentTypeId.toString()),
                    workerDni = textPart(item.workerDni),
                    deliveryRelationId = textPart(item.deliveryRelationId.toString()),
                    deliveryRelationDetail = nullableTextPart(item.deliveryRelationDetail),
                    delivererName = textPart(item.delivererName),
                    delivererDocument = nullableTextPart(item.delivererDocument),
                    contactNumber = textPart(item.contactNumber),
                    observation = nullableTextPart(item.observation),
                    delivererPhoto = item.delivererPhotoUri?.let {
                        uriPart("deliverer_photo", Uri.parse(it), "dni.jpg")
                    },
                    medicalDocumentFile = uriPart("medical_document_file", Uri.parse(item.medicalDocumentUri), "documento.jpg"),
                    annexes = item.annexUris
                        .split("|")
                        .filter { it.isNotBlank() }
                        .map { uriPart("annexes[]", Uri.parse(it), "anexo") }
                )

                val body = response.body()
                if (response.isSuccessful && body != null) {
                    syncQueueDao.markSynced(item.id, body.id)
                } else {
                    if (!reconcileKnownRemoteDocument(item.offlineUuid)) {
                        syncQueueDao.updateStatus(item.id, "FAILED")
                    }
                }
            } catch (e: Exception) {
                if (!reconcileKnownRemoteDocument(item.offlineUuid)) {
                    syncQueueDao.updateStatus(item.id, "FAILED")
                }
            }
        }
    }

    suspend fun pendingUploadCount(): Int = withContext(Dispatchers.IO) {
        syncQueueDao.countPendingUpload()
    }

    suspend fun syncPendingDocuments(): Result<Int> = withContext(Dispatchers.IO) {
        try {
            val before = syncQueueDao.countPendingUpload()
            processSyncQueue()
            val after = syncQueueDao.countPendingUpload()
            Result.success((before - after).coerceAtLeast(0))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getLocalDocuments(status: String): List<DocumentUi> = withContext(Dispatchers.IO) {
        if (status == "PENDIENTE") {
            syncQueueDao.getByStatuses(listOf("PENDING", "FAILED", "SYNCED")).map { it.toUi() }
        } else {
            emptyList()
        }
    }

    suspend fun getLocalCounts(): DocumentCounts = withContext(Dispatchers.IO) {
        DocumentCounts(
            pending = syncQueueDao.countByStatuses(listOf("PENDING", "FAILED", "SYNCED"))
        )
    }

    suspend fun getDocuments(status: String): Result<List<DocumentUi>> = withContext(Dispatchers.IO) {
        try {
            val remoteResponse = api.getDocuments(status = status, perPage = 100)
            val remoteDtos = if (remoteResponse.isSuccessful) {
                remoteResponse.body()?.data.orEmpty()
            } else {
                emptyList()
            }
            val remoteSyncedUuids = reconcileRemoteDocuments(remoteDtos)
            val remote = remoteDtos.map { it.toUi() }

            val local = if (status == "PENDIENTE") {
                syncQueueDao.getByStatuses(listOf("PENDING", "FAILED"))
                    .filterNot { it.offlineUuid in remoteSyncedUuids }
                    .map { it.toUi() }
            } else {
                emptyList()
            }

            Result.success(local + remote)
        } catch (e: Exception) {
            // OFFLINE MODE: When no internet, show local pending documents
            if (status == "PENDIENTE") {
                val local = syncQueueDao.getByStatuses(listOf("PENDING", "FAILED", "SYNCED")).map { it.toUi() }
                Result.success(local)
            } else {
                Result.success(emptyList()) // No remote data available offline for other statuses
            }
        }
    }

    suspend fun getDocument(id: String): Result<DocumentUi> = withContext(Dispatchers.IO) {
        try {
            val remoteId = id.toIntOrNull()
            if (remoteId != null) {
                val response = api.getDocument(remoteId)
                if (response.isSuccessful && response.body() != null) {
                    Result.success(response.body()!!.toUi())
                } else {
                    Result.failure(Exception("Documento no encontrado"))
                }
            } else {
                val item = syncQueueDao.findByOfflineUuid(id)
                if (item != null) {
                    Result.success(item.toUi())
                } else {
                    Result.failure(Exception("Documento local no encontrado"))
                }
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getCounts(): Result<DocumentCounts> = withContext(Dispatchers.IO) {
        try {
            val response = api.getCounts()
            val remote = response.body()
            reconcilePendingDocuments()
            val localPending = syncQueueDao.countByStatuses(listOf("PENDING", "FAILED"))
            if (response.isSuccessful && remote != null) {
                Result.success(
                    DocumentCounts(
                        pending = localPending + remote.pending,
                        received = remote.received,
                        registered = remote.registered,
                        rejected = remote.rejected
                    )
                )
            } else {
                Result.success(DocumentCounts(pending = localPending))
            }
        } catch (e: Exception) {
            val localPending = syncQueueDao.countByStatuses(listOf("PENDING", "FAILED"))
            Result.success(DocumentCounts(pending = localPending))
        }
    }

    private suspend fun reconcilePendingDocuments() {
        val response = api.getDocuments(status = "PENDIENTE", perPage = 100)
        if (response.isSuccessful) {
            reconcileRemoteDocuments(response.body()?.data.orEmpty())
        }
    }

    private suspend fun reconcileRemoteDocuments(remoteDocuments: List<MedicalDocumentDto>): Set<String> {
        val remoteByOfflineUuid = remoteDocuments
            .mapNotNull { document -> document.offlineUuid?.let { it to document.id } }
            .toMap()

        remoteByOfflineUuid.forEach { (offlineUuid, remoteDocumentId) ->
            syncQueueDao.markSyncedByOfflineUuid(offlineUuid, remoteDocumentId)
        }

        return remoteByOfflineUuid.keys
    }

    private suspend fun reconcileKnownRemoteDocument(offlineUuid: String): Boolean {
        return runCatching {
            val response = api.getDocuments(perPage = 100)
            if (!response.isSuccessful) return@runCatching false
            val remote = response.body()?.data.orEmpty().firstOrNull { it.offlineUuid == offlineUuid }
                ?: return@runCatching false
            syncQueueDao.markSyncedByOfflineUuid(offlineUuid, remote.id)
            true
        }.getOrDefault(false)
    }

    private fun MedicalDocumentDto.toUi(): DocumentUi {
        val workerName = listOfNotNull(worker?.firstName, worker?.lastName).joinToString(" ").ifBlank { "Sin trabajador" }
        return DocumentUi(
            id = id.toString(),
            isLocal = false,
            status = status,
            typeName = type?.name ?: "Documento medico",
            createdAt = formatRemoteDate(createdAt),
            workerDni = worker?.dni.orEmpty(),
            workerName = workerName,
            contactNumber = contactNumber,
            delivererName = delivererName,
            delivererDocument = delivererDocument,
            observation = observation,
            files = files.orEmpty().map { DocumentFileUi(id = it.id, type = it.fileType, name = it.originalName) },
            history = history.orEmpty().map {
                DocumentHistoryUi(
                    status = it.toStatus,
                    previousStatus = it.fromStatus,
                    observation = it.observation,
                    date = it.createdAt,
                    userName = it.user?.name
                )
            }
        )
    }

    private fun SyncQueueEntity.toUi(): DocumentUi {
        val files = buildList {
            delivererPhotoUri?.let { add(DocumentFileUi(type = "DELIVERER_PHOTO", name = displayName(Uri.parse(it), "DNI"), uri = it)) }
            add(DocumentFileUi(type = "MEDICAL_DOCUMENT", name = displayName(Uri.parse(medicalDocumentUri), medicalDocumentTypeName), uri = medicalDocumentUri))
            annexUris.split("|").filter { it.isNotBlank() }.forEachIndexed { index, uri ->
                add(DocumentFileUi(type = "ANNEX", name = displayName(Uri.parse(uri), "Anexo_${index + 1}"), uri = uri))
            }
        }
        return DocumentUi(
            id = offlineUuid,
            isLocal = true,
            status = if (status == "FAILED") "PENDIENTE" else "PENDIENTE",
            typeName = medicalDocumentTypeName,
            createdAt = formatLocalDate(createdAt),
            workerDni = workerDni,
            workerName = workerName,
            contactNumber = contactNumber,
            delivererName = delivererName,
            delivererDocument = delivererDocument,
            observation = observation,
            files = files,
            history = listOf(
                DocumentHistoryUi(
                    status = "PENDIENTE",
                    previousStatus = null,
                    observation = when (status) {
                        "FAILED" -> "Pendiente de reintento de sincronizacion."
                        "SYNCED" -> "Documento sincronizado y conservado para consulta offline."
                        else -> "Documento guardado en el dispositivo."
                    },
                    date = formatLocalDate(createdAt),
                    userName = delivererName
                )
            )
        )
    }

    private fun textPart(value: String): RequestBody =
        value.toRequestBody("text/plain".toMediaTypeOrNull())

    private fun nullableTextPart(value: String?): RequestBody? =
        value?.takeIf { it.isNotBlank() }?.let { textPart(it) }

    private fun uriPart(fieldName: String, uri: Uri, fallbackName: String): MultipartBody.Part {
        val resolver = context.contentResolver
        val mimeType = resolver.getType(uri) ?: "application/octet-stream"
        val bytes = resolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw IllegalArgumentException("No se pudo leer el archivo seleccionado")
        val fileName = displayName(uri, fallbackName)
        val body = bytes.toRequestBody(mimeType.toMediaTypeOrNull())
        return MultipartBody.Part.createFormData(fieldName, fileName, body)
    }

    private fun displayName(uri: Uri, fallbackName: String): String {
        var cursor: Cursor? = null
        return try {
            cursor = context.contentResolver.query(uri, null, null, null, null)
            val nameIndex = cursor?.getColumnIndex(OpenableColumns.DISPLAY_NAME) ?: -1
            if (cursor != null && cursor.moveToFirst() && nameIndex >= 0) {
                cursor.getString(nameIndex)
            } else {
                fallbackName
            }
        } catch (_: Exception) {
            fallbackName
        } finally {
            cursor?.close()
        }
    }

    private fun persistOfflineFile(uri: Uri, targetDir: File, fallbackName: String): Uri {
        val originalName = displayName(uri, fallbackName)
        val targetFile = uniqueFile(targetDir, originalName.ifBlank { fallbackName })
        val inputStream = if (uri.scheme == "file") {
            uri.path?.let { File(it).inputStream() }
        } else {
            context.contentResolver.openInputStream(uri)
        }
        inputStream?.use { input ->
            FileOutputStream(targetFile).use { output -> input.copyTo(output) }
        } ?: throw IllegalArgumentException("No se pudo leer el archivo seleccionado")
        return Uri.fromFile(targetFile)
    }

    private fun uniqueFile(targetDir: File, rawName: String): File {
        val sanitized = rawName.replace(Regex("[^A-Za-z0-9._-]"), "_").ifBlank { "archivo" }
        var candidate = File(targetDir, sanitized)
        if (!candidate.exists()) return candidate

        val nameWithoutExtension = candidate.nameWithoutExtension
        val extension = candidate.extension.takeIf { it.isNotBlank() }?.let { ".$it" }.orEmpty()
        var index = 1
        while (candidate.exists()) {
            candidate = File(targetDir, "${nameWithoutExtension}_$index$extension")
            index++
        }
        return candidate
    }

    private fun formatLocalDate(timestamp: Long): String {
        return SimpleDateFormat("dd/MM/yyyy HH:mm", Locale("es", "PE")).format(Date(timestamp))
    }

    private fun formatRemoteDate(value: String): String {
        val normalized = value.replace(Regex("\\.\\d+Z$"), "Z")
        val parser = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US).apply {
            timeZone = TimeZone.getTimeZone("UTC")
        }
        val output = SimpleDateFormat("dd/MM/yyyy HH:mm", Locale("es", "PE"))
        return runCatching { parser.parse(normalized)?.let(output::format) }.getOrNull() ?: value
    }
}
