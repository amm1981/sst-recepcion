package com.amm1981.docssalud.data.local.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.amm1981.docssalud.data.local.entity.CatalogEntity
import com.amm1981.docssalud.data.local.entity.SyncQueueEntity
import com.amm1981.docssalud.data.local.entity.WorkerEntity

@Dao
interface WorkerDao {
    @Query("SELECT * FROM workers WHERE dni = :dni LIMIT 1")
    suspend fun findByDni(dni: String): WorkerEntity?

    @Query("SELECT * FROM workers WHERE dni LIKE '%' || :query || '%' OR firstName LIKE '%' || :query || '%' OR lastName LIKE '%' || :query || '%' LIMIT 20")
    suspend fun search(query: String): List<WorkerEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(workers: List<WorkerEntity>)

    @Query("DELETE FROM workers")
    suspend fun clearAll()
}

@Dao
interface CatalogDao {
    @Query("SELECT * FROM catalogs WHERE type = :type ORDER BY name")
    suspend fun getByType(type: String): List<CatalogEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(catalogs: List<CatalogEntity>)

    @Query("DELETE FROM catalogs")
    suspend fun clearAll()
}

@Dao
interface SyncQueueDao {
    @Query("SELECT * FROM sync_queue WHERE status IN (:statuses) ORDER BY createdAt DESC")
    suspend fun getByStatuses(statuses: List<String>): List<SyncQueueEntity>

    @Query("SELECT * FROM sync_queue WHERE status IN ('PENDING', 'FAILED') ORDER BY createdAt ASC")
    suspend fun getPending(): List<SyncQueueEntity>

    @Query("SELECT * FROM sync_queue WHERE offlineUuid = :offlineUuid LIMIT 1")
    suspend fun findByOfflineUuid(offlineUuid: String): SyncQueueEntity?

    @Query("SELECT COUNT(*) FROM sync_queue WHERE status IN (:statuses)")
    suspend fun countByStatuses(statuses: List<String>): Int

    @Query("SELECT COUNT(*) FROM sync_queue WHERE status IN ('PENDING', 'FAILED')")
    suspend fun countPendingUpload(): Int

    @Insert
    suspend fun insert(item: SyncQueueEntity)

    @Query("UPDATE sync_queue SET status = :status WHERE id = :id")
    suspend fun updateStatus(id: Int, status: String)

    @Query("UPDATE sync_queue SET status = 'SYNCED', remoteDocumentId = :remoteDocumentId WHERE id = :id")
    suspend fun markSynced(id: Int, remoteDocumentId: Int)

    @Query("UPDATE sync_queue SET status = 'SYNCED', remoteDocumentId = :remoteDocumentId WHERE offlineUuid = :offlineUuid")
    suspend fun markSyncedByOfflineUuid(offlineUuid: String, remoteDocumentId: Int)
}
