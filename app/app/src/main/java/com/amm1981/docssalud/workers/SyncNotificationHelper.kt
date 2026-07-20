package com.amm1981.docssalud.workers

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.ForegroundInfo
import com.amm1981.docssalud.MainActivity
import com.amm1981.docssalud.R

object SyncNotificationHelper {
    private const val CHANNEL_ID = "document_sync"
    private const val FOREGROUND_NOTIFICATION_ID = 3101
    private const val RESULT_NOTIFICATION_ID = 3102

    fun foregroundInfo(
        context: Context,
        uploaded: Int,
        total: Int
    ): ForegroundInfo {
        ensureChannel(context)
        val title = "Subiendo documentos"
        val text = if (total > 0) {
            "Enviando $uploaded de $total documentos pendientes"
        } else {
            "Preparando documentos pendientes"
        }
        val notification = baseBuilder(context, title, text)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setProgress(total.coerceAtLeast(1), uploaded.coerceAtMost(total.coerceAtLeast(1)), total <= 0)
            .build()

        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            ForegroundInfo(
                FOREGROUND_NOTIFICATION_ID,
                notification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC
            )
        } else {
            ForegroundInfo(FOREGROUND_NOTIFICATION_ID, notification)
        }
    }

    fun showSuccess(context: Context, uploaded: Int) {
        val text = if (uploaded == 1) {
            "Se subio 1 documento correctamente."
        } else {
            "Se subieron $uploaded documentos correctamente."
        }
        showResult(context, "Subida completada", text)
    }

    fun showError(context: Context, remaining: Int) {
        val text = if (remaining == 1) {
            "Queda 1 documento pendiente. Se reintentara automaticamente."
        } else {
            "Quedan $remaining documentos pendientes. Se reintentara automaticamente."
        }
        showResult(context, "Error al subir documentos", text)
    }

    private fun showResult(context: Context, title: String, text: String) {
        ensureChannel(context)
        if (!canPostNotifications(context)) return

        NotificationManagerCompat.from(context).notify(
            RESULT_NOTIFICATION_ID,
            baseBuilder(context, title, text)
                .setAutoCancel(true)
                .build()
        )
    }

    private fun baseBuilder(context: Context, title: String, text: String): NotificationCompat.Builder {
        return NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_launcher_foreground)
            .setContentTitle(title)
            .setContentText(text)
            .setStyle(NotificationCompat.BigTextStyle().bigText(text))
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setContentIntent(openAppIntent(context))
    }

    private fun openAppIntent(context: Context): PendingIntent {
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        return PendingIntent.getActivity(
            context,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    private fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val manager = context.getSystemService(NotificationManager::class.java)
        val channel = NotificationChannel(
            CHANNEL_ID,
            "Sincronizacion de documentos",
            NotificationManager.IMPORTANCE_DEFAULT
        ).apply {
            description = "Estado de subida de documentos pendientes"
        }
        manager.createNotificationChannel(channel)
    }

    private fun canPostNotifications(context: Context): Boolean {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
    }
}
