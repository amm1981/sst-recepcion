package com.amm1981.docssalud.data.repository

import android.content.SharedPreferences
import com.amm1981.docssalud.data.api.DocsSaludApi
import com.amm1981.docssalud.data.local.dao.CatalogDao
import com.amm1981.docssalud.data.local.dao.WorkerDao
import com.amm1981.docssalud.data.local.entity.CatalogEntity
import com.amm1981.docssalud.data.local.entity.WorkerEntity
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.time.Duration
import java.time.Instant
import javax.inject.Inject
import javax.inject.Singleton

data class SyncProgress(
    val percent: Int,
    val message: String
)

@Singleton
class SyncRepository @Inject constructor(
    private val api: DocsSaludApi,
    private val workerDao: WorkerDao,
    private val catalogDao: CatalogDao,
    private val prefs: SharedPreferences
) {
    companion object {
        private const val PREF_LAST_WORKER_SYNC = "last_worker_sync_timestamp"
        private const val PREF_CATALOGS_SYNCED = "catalogs_synced"
        private const val WORKER_AUTO_SYNC_INTERVAL_HOURS = 6L
    }

    /**
     * Sync all data. Catalogs are small and always fully replaced.
     * Workers use incremental sync after the first full load.
     */
    suspend fun syncAll(
        forceWorkers: Boolean = false,
        onProgress: ((SyncProgress) -> Unit)? = null
    ): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            onProgress?.invoke(SyncProgress(5, "Preparando actualizacion..."))
            val catalogsResult = syncCatalogs()
            onProgress?.invoke(SyncProgress(35, "Catalogos actualizados."))
            val workersResult = if (forceWorkers || shouldAutoSyncWorkers()) {
                onProgress?.invoke(SyncProgress(45, "Actualizando trabajadores..."))
                syncWorkers().also {
                    if (it.isSuccess) onProgress?.invoke(SyncProgress(90, "Trabajadores actualizados."))
                }
            } else {
                onProgress?.invoke(SyncProgress(85, "Trabajadores ya estan actualizados."))
                null
            }

            if (catalogsResult.isFailure) return@withContext catalogsResult
            if (workersResult?.isFailure == true) return@withContext workersResult

            onProgress?.invoke(SyncProgress(100, "Data Maestra actualizada."))
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Sync only catalogs (lightweight - always fast)
     */
    suspend fun syncCatalogs(): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val catalogsResponse = api.syncCatalogs()
            if (catalogsResponse.isSuccessful) {
                val dto = catalogsResponse.body()
                val catalogEntities = mutableListOf<CatalogEntity>()

                dto?.medicalDocumentTypes.orEmpty().forEach {
                    catalogEntities.add(CatalogEntity(it.id, "DOCUMENT_TYPE", it.name, it.requiresDetail ?: false))
                }
                dto?.deliveryRelations.orEmpty().forEach {
                    catalogEntities.add(CatalogEntity(it.id, "RELATION", it.name, it.requiresDetail ?: false))
                }
                dto?.managements.orEmpty().forEach {
                    catalogEntities.add(CatalogEntity(it.id, "MANAGEMENT", it.name, false))
                }
                dto?.sectors.orEmpty().forEach {
                    catalogEntities.add(CatalogEntity(it.id, "SECTOR", it.name, false))
                }

                catalogDao.clearAll()
                catalogDao.insertAll(catalogEntities)
                prefs.edit().putBoolean(PREF_CATALOGS_SYNCED, true).apply()

                Result.success(Unit)
            } else {
                Result.failure(Exception("Error sincronizando catálogos: ${catalogsResponse.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Sync workers. Uses incremental sync after first load:
     * - First time: fetch all active workers
     * - Subsequent: fetch only workers updated since last sync timestamp
     */
    private suspend fun syncWorkers(): Result<Unit> {
        return try {
            val lastSync = prefs.getString(PREF_LAST_WORKER_SYNC, null)

            val workersResponse = if (lastSync != null) {
                api.syncWorkersIncremental(updatedSince = lastSync)
            } else {
                api.syncWorkers()
            }

            if (workersResponse.isSuccessful) {
                val workerDtos = workersResponse.body().orEmpty()
                val workers = workerDtos.map {
                    WorkerEntity(
                        id = it.id,
                        dni = it.dni,
                        firstName = it.firstName,
                        lastName = it.lastName,
                        email = it.email,
                        phone = it.phone,
                        position = it.position,
                        managementId = it.managementId,
                        managementName = it.management?.name,
                        sectorId = it.sectorId,
                        sectorName = it.sector?.name
                    )
                }

                if (lastSync == null) {
                    // First sync: clear and insert all
                    workerDao.clearAll()
                    workerDao.insertAll(workers)
                } else {
                    // Incremental: upsert only changed workers
                    if (workers.isNotEmpty()) {
                        workerDao.insertAll(workers) // REPLACE strategy handles upserts
                    }
                }

                // Save current timestamp for next incremental sync
                prefs.edit().putString(PREF_LAST_WORKER_SYNC, Instant.now().toString()).apply()

                Result.success(Unit)
            } else {
                Result.failure(Exception("Error sincronizando trabajadores: ${workersResponse.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun hasCatalogsSynced(): Boolean = prefs.getBoolean(PREF_CATALOGS_SYNCED, false)

    private fun shouldAutoSyncWorkers(): Boolean {
        val lastSync = prefs.getString(PREF_LAST_WORKER_SYNC, null) ?: return true
        return runCatching {
            Duration.between(Instant.parse(lastSync), Instant.now()).toHours() >= WORKER_AUTO_SYNC_INTERVAL_HOURS
        }.getOrDefault(true)
    }
}
