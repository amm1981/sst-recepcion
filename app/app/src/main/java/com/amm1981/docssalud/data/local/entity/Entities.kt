package com.amm1981.docssalud.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "workers")
data class WorkerEntity(
    @PrimaryKey val id: Int,
    val dni: String,
    val firstName: String,
    val lastName: String,
    val email: String?,
    val phone: String?,
    val position: String?,
    val managementId: Int?,
    val managementName: String?,
    val sectorId: Int?,
    val sectorName: String?
)

@Entity(tableName = "catalogs", primaryKeys = ["id", "type"])
data class CatalogEntity(
    val id: Int,
    val type: String,
    val name: String,
    val requiresDetail: Boolean = false
)

@Entity(tableName = "sync_queue")
data class SyncQueueEntity(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val offlineUuid: String,
    val remoteDocumentId: Int? = null,
    val medicalDocumentTypeId: Int,
    val medicalDocumentTypeName: String,
    val workerDni: String,
    val workerName: String,
    val deliveryRelationId: Int,
    val deliveryRelationDetail: String?,
    val delivererName: String,
    val delivererDocument: String?,
    val contactNumber: String,
    val observation: String?,
    val delivererPhotoUri: String?,
    val medicalDocumentUri: String,
    val annexUris: String,
    val createdAt: Long = System.currentTimeMillis(),
    val status: String
)
