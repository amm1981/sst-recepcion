package com.amm1981.docssalud.ui.screens.document

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.InsertDriveFile
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.amm1981.docssalud.BuildConfig
import com.amm1981.docssalud.data.repository.DocumentFileUi
import com.amm1981.docssalud.data.repository.DocumentUi
import com.amm1981.docssalud.ui.theme.PrimaryGreen

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DocumentDetailScreen(
    documentId: String,
    viewModel: DocumentDetailViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit,
    onNavigateToStatus: (String) -> Unit
) {
    val state by viewModel.state.collectAsState()

    LaunchedEffect(documentId) {
        viewModel.loadDocument(documentId)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Detalle del Documento", color = Color.White) },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Volver", tint = Color.White)
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
            state.document != null -> DocumentDetailContent(
                doc = state.document!!,
                token = viewModel.getAuthToken(),
                modifier = Modifier.padding(innerPadding),
                onNavigateToStatus = onNavigateToStatus
            )
        }
    }
}

@Composable
private fun DocumentDetailContent(
    doc: DocumentUi,
    token: String,
    modifier: Modifier,
    onNavigateToStatus: (String) -> Unit
) {
    val visual = statusVisual(doc.status)
    val delivererPhoto = doc.files.firstOrNull { it.type == "DELIVERER_PHOTO" }
    val medicalFile = doc.files.firstOrNull { it.type == "MEDICAL_DOCUMENT" }
    val annexes = doc.files.filter { it.type == "ANNEX" }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { onNavigateToStatus(doc.id) },
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
        Text(
            "Ver estado del documento",
            fontSize = 12.sp,
            color = PrimaryGreen,
            modifier = Modifier
                .padding(top = 4.dp)
                .clickable { onNavigateToStatus(doc.id) }
        )

        HorizontalDivider(modifier = Modifier.padding(vertical = 16.dp))

        Text("Información", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.padding(bottom = 12.dp))
        DetailRow("Tipo de Documento", doc.typeName)
        DetailRow("Fecha de Registro", doc.createdAt)
        DetailRow("DNI", doc.workerDni)
        DetailRow("Nombre", doc.workerName)
        DetailRow("Número de Contacto", doc.contactNumber)

        HorizontalDivider(modifier = Modifier.padding(vertical = 16.dp))

        Text("Documentos Adjuntos", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.padding(bottom = 12.dp))
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            ImagePreviewCard(
                file = delivererPhoto,
                title = "Foto de quien entrega",
                token = token,
                isLocal = doc.isLocal,
                modifier = Modifier.weight(1f)
            )
            ImagePreviewCard(
                file = medicalFile,
                title = doc.typeName,
                token = token,
                isLocal = doc.isLocal,
                modifier = Modifier.weight(1f)
            )
        }

        HorizontalDivider(modifier = Modifier.padding(vertical = 16.dp))

        Text("Anexos (${annexes.size})", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.padding(bottom = 12.dp))
        if (annexes.isEmpty()) {
            Text("Sin anexos.", color = Color.Gray, fontSize = 14.sp)
        } else {
            annexes.forEach { AttachmentRow(it.name) }
        }
    }
}

@Composable
fun DetailRow(label: String, value: String) {
    Column(modifier = Modifier.padding(bottom = 12.dp)) {
        Text(label, fontSize = 12.sp, color = Color.Gray, fontWeight = FontWeight.Medium)
        Text(value.ifBlank { "-" }, fontSize = 16.sp, color = Color.Black)
    }
}

@Composable
fun ImagePreviewCard(
    file: DocumentFileUi?,
    title: String,
    token: String,
    isLocal: Boolean,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current

    Card(
        modifier = modifier.height(170.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        shape = RoundedCornerShape(8.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(8.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f)
                    .clip(RoundedCornerShape(4.dp))
                    .background(Color(0xFFF3F4F6)),
                contentAlignment = Alignment.Center
            ) {
                if (file != null) {
                    if (isLocal && file.uri != null) {
                        // Local file: load from content URI
                        AsyncImage(
                            model = ImageRequest.Builder(context)
                                .data(Uri.parse(file.uri))
                                .crossfade(true)
                                .build(),
                            contentDescription = title,
                            contentScale = ContentScale.Crop,
                            modifier = Modifier.fillMaxSize()
                        )
                    } else if (file.id != null) {
                        // Remote file: load via preview API
                        val baseUrl = BuildConfig.API_BASE_URL.trimEnd('/')
                        val previewUrl = "$baseUrl/medical-documents/files/${file.id}/preview?token=$token"
                        AsyncImage(
                            model = ImageRequest.Builder(context)
                                .data(previewUrl)
                                .crossfade(true)
                                .build(),
                            contentDescription = title,
                            contentScale = ContentScale.Crop,
                            modifier = Modifier.fillMaxSize()
                        )
                    } else {
                        Icon(Icons.Default.Image, contentDescription = null, tint = Color.Gray, modifier = Modifier.size(32.dp))
                    }
                } else {
                    Icon(Icons.Default.Image, contentDescription = null, tint = Color.Gray, modifier = Modifier.size(32.dp))
                }
            }
            Spacer(modifier = Modifier.height(6.dp))
            Text(title, fontSize = 12.sp, fontWeight = FontWeight.Medium, maxLines = 1)
            Text(file?.name ?: "No adjunto", fontSize = 11.sp, color = PrimaryGreen, maxLines = 1)
        }
    }
}

@Composable
fun AttachmentRow(filename: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
            Icon(Icons.Default.InsertDriveFile, contentDescription = null, tint = Color.Gray)
            Spacer(modifier = Modifier.width(8.dp))
            Text(filename, fontSize = 14.sp, maxLines = 1)
        }
        Icon(Icons.Default.Download, contentDescription = "Descargar", tint = Color.Gray)
    }
}
