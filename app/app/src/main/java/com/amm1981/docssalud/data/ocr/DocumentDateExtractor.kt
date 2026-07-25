package com.amm1981.docssalud.data.ocr

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.text.TextRecognition
import com.google.mlkit.vision.text.latin.TextRecognizerOptions
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.suspendCancellableCoroutine
import java.text.Normalizer
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale
import javax.inject.Inject
import javax.inject.Singleton
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

data class DocumentDateExtractionResult(
    val candidates: List<String>,
    val message: String
)

@Singleton
class DocumentDateExtractor @Inject constructor(
    @ApplicationContext private val context: Context
) {
    private val outputFormatter = DateTimeFormatter.ISO_LOCAL_DATE
    private val numericDate = Regex("""\b(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})\b""")
    private val writtenDate = Regex(
        """\b(\d{1,2})\s+DE\s+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SETIEMBRE|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+(?:DE|DEL)?\s*(\d{2,4})\b"""
    )
    private val months = mapOf(
        "ENERO" to 1,
        "FEBRERO" to 2,
        "MARZO" to 3,
        "ABRIL" to 4,
        "MAYO" to 5,
        "JUNIO" to 6,
        "JULIO" to 7,
        "AGOSTO" to 8,
        "SETIEMBRE" to 9,
        "SEPTIEMBRE" to 9,
        "OCTUBRE" to 10,
        "NOVIEMBRE" to 11,
        "DICIEMBRE" to 12
    )

    suspend fun extract(uri: Uri): DocumentDateExtractionResult {
        if (!isImage(uri)) {
            return DocumentDateExtractionResult(
                candidates = emptyList(),
                message = "El OCR local esta disponible para imagenes. Ingrese la fecha manualmente para PDF o Word."
            )
        }

        val text = recognizeImageText(uri)
        val candidates = extractDates(text)
        return DocumentDateExtractionResult(
            candidates = candidates,
            message = when (candidates.size) {
                0 -> "No se reconocio una fecha en la imagen. Ingrese la fecha manualmente."
                1 -> "Se detecto una fecha. Verifiquela antes de guardar."
                else -> "Se detectaron varias fechas. Seleccione la que corresponde al documento."
            }
        )
    }

    private suspend fun recognizeImageText(uri: Uri): String {
        val image = InputImage.fromFilePath(context, uri)
        val recognizer = TextRecognition.getClient(TextRecognizerOptions.DEFAULT_OPTIONS)
        return suspendCancellableCoroutine { continuation ->
            recognizer.process(image)
                .addOnSuccessListener { continuation.resume(it.text) }
                .addOnFailureListener { continuation.resumeWithException(it) }
        }
    }

    private fun extractDates(text: String): List<String> {
        val normalized = normalize(text)
        val dates = linkedSetOf<String>()

        numericDate.findAll(normalized).forEach { match ->
            parseDate(
                day = match.groupValues[1].toIntOrNull(),
                month = match.groupValues[2].toIntOrNull(),
                year = normalizeYear(match.groupValues[3])
            )?.let(dates::add)
        }

        writtenDate.findAll(normalized).forEach { match ->
            parseDate(
                day = match.groupValues[1].toIntOrNull(),
                month = months[match.groupValues[2]],
                year = normalizeYear(match.groupValues[3])
            )?.let(dates::add)
        }

        return dates.toList()
    }

    private fun parseDate(day: Int?, month: Int?, year: Int?): String? {
        if (day == null || month == null || year == null) return null
        return runCatching { LocalDate.of(year, month, day).format(outputFormatter) }.getOrNull()
    }

    private fun normalizeYear(value: String): Int? {
        val year = value.toIntOrNull() ?: return null
        return if (value.length == 2) 2000 + year else year
    }

    private fun isImage(uri: Uri): Boolean {
        val mimeType = context.contentResolver.getType(uri).orEmpty()
        if (mimeType.startsWith("image/")) return true
        val name = displayName(uri).lowercase(Locale.US)
        return name.endsWith(".jpg") || name.endsWith(".jpeg") || name.endsWith(".png")
    }

    private fun displayName(uri: Uri): String {
        var cursor = context.contentResolver.query(uri, null, null, null, null)
        return try {
            val nameIndex = cursor?.getColumnIndex(OpenableColumns.DISPLAY_NAME) ?: -1
            if (cursor != null && cursor.moveToFirst() && nameIndex >= 0) cursor.getString(nameIndex) else uri.lastPathSegment.orEmpty()
        } finally {
            cursor?.close()
        }
    }

    private fun normalize(value: String): String {
        return Normalizer.normalize(value, Normalizer.Form.NFD)
            .replace(Regex("\\p{Mn}+"), "")
            .uppercase(Locale("es", "PE"))
    }
}
