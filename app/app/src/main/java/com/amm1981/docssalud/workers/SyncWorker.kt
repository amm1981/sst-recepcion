package com.amm1981.docssalud.workers

import android.content.Context
import androidx.hilt.work.HiltWorker
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.amm1981.docssalud.data.repository.DocumentRepository
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

@HiltWorker
class SyncWorker @AssistedInject constructor(
    @Assisted appContext: Context,
    @Assisted workerParams: WorkerParameters,
    private val documentRepository: DocumentRepository
) : CoroutineWorker(appContext, workerParams) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        try {
            val pending = documentRepository.pendingUploadCount()
            if (pending == 0) {
                return@withContext Result.success()
            }

            setForeground(SyncNotificationHelper.foregroundInfo(applicationContext, 0, pending))
            val syncResult = documentRepository.processSyncQueue { uploaded, total ->
                setForeground(SyncNotificationHelper.foregroundInfo(applicationContext, uploaded, total))
            }

            if (syncResult.remaining == 0) {
                SyncNotificationHelper.showSuccess(applicationContext, syncResult.uploaded)
                Result.success()
            } else {
                SyncNotificationHelper.showError(applicationContext, syncResult.remaining)
                Result.retry()
            }
        } catch (e: Exception) {
            val remaining = documentRepository.pendingUploadCount()
            if (remaining > 0) {
                SyncNotificationHelper.showError(applicationContext, remaining)
            }
            Result.retry()
        }
    }
}
