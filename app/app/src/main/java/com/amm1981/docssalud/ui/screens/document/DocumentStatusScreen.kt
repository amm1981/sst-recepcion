package com.amm1981.docssalud.ui.screens.document

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.amm1981.docssalud.data.repository.DocumentHistoryUi
import com.amm1981.docssalud.data.repository.DocumentUi
import com.amm1981.docssalud.ui.theme.PrimaryGreen

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DocumentStatusScreen(
    documentId: String,
    viewModel: DocumentDetailViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit
) {
    val state by viewModel.state.collectAsState()

    LaunchedEffect(documentId) {
        viewModel.loadDocument(documentId)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Estado del Documento", color = Color.White) },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Volver", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = PrimaryGreen)
            )
        }
    ) { innerPadding ->
        when {
            state.isLoading -> Box(Modifier.fillMaxSize().padding(innerPadding), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = PrimaryGreen)
            }
            state.error != null -> Box(Modifier.fillMaxSize().padding(innerPadding).padding(24.dp), contentAlignment = Alignment.Center) {
                Text(state.error ?: "", color = MaterialTheme.colorScheme.error)
            }
            state.document != null -> DocumentStatusContent(
                doc = state.document!!,
                modifier = Modifier.padding(innerPadding)
            )
        }
    }
}

@Composable
private fun DocumentStatusContent(doc: DocumentUi, modifier: Modifier) {
    val visual = statusVisual(doc.status)

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Nro. ${doc.displayId()}", fontSize = 20.sp, fontWeight = FontWeight.Bold)
            Box(
                modifier = Modifier
                    .background(visual.background, shape = RoundedCornerShape(12.dp))
                    .padding(horizontal = 12.dp, vertical = 6.dp)
            ) {
                Text(visual.label, color = visual.color, fontWeight = FontWeight.Bold, fontSize = 14.sp)
            }
        }

        HorizontalDivider(modifier = Modifier.padding(vertical = 16.dp))

        Text("Estado Actual", fontWeight = FontWeight.Bold, fontSize = 16.sp, modifier = Modifier.padding(bottom = 8.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(modifier = Modifier.size(16.dp).background(visual.color, shape = CircleShape))
            Spacer(modifier = Modifier.width(8.dp))
            Text(visual.label, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        }
        Text(
            statusMessage(doc.status),
            fontSize = 14.sp,
            color = Color.Gray,
            modifier = Modifier.padding(start = 24.dp, top = 4.dp)
        )

        HorizontalDivider(modifier = Modifier.padding(vertical = 16.dp))

        Text("Historial del Proceso", fontWeight = FontWeight.Bold, fontSize = 16.sp, modifier = Modifier.padding(bottom = 16.dp))
        val history = normalizedHistory(doc)
        history.forEachIndexed { index, item ->
            TimelineItem(
                title = item.statusLabel,
                subtitle = item.subtitle,
                date = item.date,
                isCompleted = item.isCompleted,
                isLast = index == history.lastIndex,
                color = item.color
            )
        }
    }
}

private data class TimelineUi(
    val statusLabel: String,
    val subtitle: String,
    val date: String,
    val isCompleted: Boolean,
    val color: Color
)

private fun normalizedHistory(doc: DocumentUi): List<TimelineUi> {
    val completed = doc.history.map {
        TimelineUi(
            statusLabel = when (it.status) {
                "PENDIENTE" -> "Registro creado"
                "RECEPCIONADO" -> "Recepcionado"
                "REGISTRADO" -> "Registrado en Genesys"
                "RECHAZADO" -> "Rechazado"
                else -> it.status
            },
            subtitle = it.observation ?: it.userName?.let { user -> "Por: $user" }.orEmpty(),
            date = it.date,
            isCompleted = true,
            color = statusVisual(it.status).color
        )
    }
    val currentStatuses = completed.map { it.statusLabel }.toSet()
    val pendingSteps = listOf(
        TimelineUi("Recepcionado", "Pendiente", "", false, Color.LightGray),
        TimelineUi("Registrado en Genesys", "Pendiente", "", false, Color.LightGray)
    ).filter { it.statusLabel !in currentStatuses && doc.status != "RECHAZADO" }

    return if (completed.isEmpty()) {
        listOf(TimelineUi("Registro creado", "Pendiente", "", false, Color.LightGray)) + pendingSteps
    } else {
        completed + pendingSteps
    }
}

private fun statusMessage(status: String): String = when (status) {
    "RECEPCIONADO" -> "Su documento fue recepcionado por SST."
    "REGISTRADO" -> "Su documento fue registrado en Genesys."
    "RECHAZADO" -> "Su documento fue rechazado. Revise la observación."
    else -> "Su documento está en revisión."
}

@Composable
fun TimelineItem(
    title: String,
    subtitle: String,
    date: String,
    isCompleted: Boolean,
    isLast: Boolean,
    color: Color
) {
    Row(modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(24.dp)) {
            Box(
                modifier = Modifier
                    .size(16.dp)
                    .background(color = if (isCompleted) color else Color.Transparent, shape = CircleShape)
                    .padding(2.dp)
            ) {
                if (!isCompleted) {
                    Canvas(modifier = Modifier.fillMaxSize()) {
                        drawCircle(color = color, style = Stroke(width = 4f))
                    }
                }
            }
            if (!isLast) {
                Box(
                    modifier = Modifier
                        .width(2.dp)
                        .weight(1f)
                        .background(Color.LightGray)
                )
            }
        }

        Spacer(modifier = Modifier.width(16.dp))

        Column(modifier = Modifier.padding(bottom = 24.dp)) {
            if (date.isNotEmpty()) Text(date, fontSize = 12.sp, color = Color.Gray)
            Text(title, fontSize = 16.sp, fontWeight = FontWeight.Medium, color = if (isCompleted) Color.Black else Color.Gray)
            if (subtitle.isNotEmpty()) Text(subtitle, fontSize = 14.sp, color = Color.Gray)
        }
    }
}
