package com.amm1981.docssalud.data.local

import androidx.room.Database
import androidx.room.RoomDatabase
import com.amm1981.docssalud.data.local.dao.CatalogDao
import com.amm1981.docssalud.data.local.dao.SyncQueueDao
import com.amm1981.docssalud.data.local.dao.WorkerDao
import com.amm1981.docssalud.data.local.entity.CatalogEntity
import com.amm1981.docssalud.data.local.entity.SyncQueueEntity
import com.amm1981.docssalud.data.local.entity.WorkerEntity

@Database(entities = [WorkerEntity::class, CatalogEntity::class, SyncQueueEntity::class], version = 4, exportSchema = false)
abstract class AppDatabase : RoomDatabase() {
    abstract fun workerDao(): WorkerDao
    abstract fun catalogDao(): CatalogDao
    abstract fun syncQueueDao(): SyncQueueDao
}
