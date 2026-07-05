package com.amm1981.docssalud.data.api

import com.google.gson.annotations.SerializedName
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Path
import retrofit2.http.Query

interface DocsSaludApi {
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("auth/me")
    suspend fun me(): Response<MeResponse>

    @GET("sync/workers")
    suspend fun syncWorkers(): Response<List<WorkerDto>>

    @GET("sync/workers")
    suspend fun syncWorkersIncremental(
        @Query("updated_since") updatedSince: String
    ): Response<List<WorkerDto>>

    @GET("sync/catalogs")
    suspend fun syncCatalogs(): Response<CatalogsDto>

    @GET("medical-documents")
    suspend fun getDocuments(
        @Query("status") status: String? = null,
        @Query("per_page") perPage: Int = 100
    ): Response<PaginatedResponse<MedicalDocumentDto>>

    @GET("medical-documents/counts")
    suspend fun getCounts(): Response<DocumentCountsDto>

    @GET("medical-documents/{id}")
    suspend fun getDocument(@Path("id") id: Int): Response<MedicalDocumentDto>

    @Multipart
    @POST("sync/documents")
    suspend fun uploadDocument(
        @Part("offline_uuid") offlineUuid: RequestBody,
        @Part("medical_document_type_id") medicalDocumentTypeId: RequestBody,
        @Part("worker_dni") workerDni: RequestBody,
        @Part("delivery_relation_id") deliveryRelationId: RequestBody,
        @Part("delivery_relation_detail") deliveryRelationDetail: RequestBody?,
        @Part("deliverer_name") delivererName: RequestBody,
        @Part("deliverer_document") delivererDocument: RequestBody?,
        @Part("contact_number") contactNumber: RequestBody,
        @Part("observation") observation: RequestBody?,
        @Part delivererPhoto: MultipartBody.Part?,
        @Part medicalDocumentFile: MultipartBody.Part,
        @Part annexes: List<MultipartBody.Part>
    ): Response<MedicalDocumentDto>
}

data class LoginRequest(val email: String, val password: String)
data class LoginResponse(val token: String, val user: UserDto)
data class MeResponse(val user: UserDto)
data class UserDto(val id: Int, val name: String, val email: String)

data class WorkerDto(
    val id: Int,
    val dni: String,
    @SerializedName("first_name") val firstName: String,
    @SerializedName("last_name") val lastName: String,
    val email: String? = null,
    val phone: String? = null,
    val position: String? = null,
    @SerializedName("management_id") val managementId: Int? = null,
    @SerializedName("sector_id") val sectorId: Int? = null,
    val management: CatalogItemDto? = null,
    val sector: CatalogItemDto? = null
)

data class CatalogsDto(
    @SerializedName("medical_document_types") val medicalDocumentTypes: List<CatalogItemDto>? = emptyList(),
    @SerializedName("delivery_relations") val deliveryRelations: List<CatalogItemDto>? = emptyList(),
    val managements: List<CatalogItemDto>? = emptyList(),
    val sectors: List<CatalogItemDto>? = emptyList()
)

data class CatalogItemDto(
    val id: Int,
    val name: String,
    val code: String? = null,
    @SerializedName("requires_detail") val requiresDetail: Boolean? = null
)

data class PaginatedResponse<T>(
    val data: List<T>? = emptyList(),
    @SerializedName("current_page") val currentPage: Int,
    @SerializedName("last_page") val lastPage: Int,
    val total: Int
)

data class DocumentCountsDto(
    val pending: Int,
    val received: Int,
    val registered: Int,
    val rejected: Int
)

data class MedicalDocumentDto(
    val id: Int,
    val status: String,
    @SerializedName("offline_uuid") val offlineUuid: String? = null,
    @SerializedName("contact_number") val contactNumber: String,
    val observation: String? = null,
    @SerializedName("delivery_relation_detail") val deliveryRelationDetail: String? = null,
    @SerializedName("deliverer_name") val delivererName: String,
    @SerializedName("deliverer_document") val delivererDocument: String? = null,
    @SerializedName("created_at") val createdAt: String,
    val type: CatalogItemDto? = null,
    val worker: WorkerDto? = null,
    @SerializedName("delivery_relation") val deliveryRelation: CatalogItemDto? = null,
    val files: List<MedicalDocumentFileDto>? = emptyList(),
    val history: List<MedicalDocumentHistoryDto>? = emptyList()
)

data class MedicalDocumentFileDto(
    val id: Int,
    @SerializedName("file_type") val fileType: String,
    @SerializedName("original_name") val originalName: String,
    @SerializedName("mime_type") val mimeType: String? = null,
    val size: Long = 0
)

data class MedicalDocumentHistoryDto(
    val id: Int,
    @SerializedName("from_status") val fromStatus: String? = null,
    @SerializedName("to_status") val toStatus: String,
    val observation: String? = null,
    @SerializedName("created_at") val createdAt: String,
    val user: UserDto? = null
)
