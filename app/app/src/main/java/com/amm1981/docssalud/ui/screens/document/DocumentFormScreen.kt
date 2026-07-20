package com.amm1981.docssalud.ui.screens.document

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.AttachFile
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.hilt.navigation.compose.hiltViewModel
import com.amm1981.docssalud.data.local.entity.CatalogEntity
import com.amm1981.docssalud.ui.theme.PrimaryGreen
import java.io.File

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DocumentFormScreen(
    viewModel: DocumentFormViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit
) {
    val context = LocalContext.current
    val state by viewModel.state.collectAsState()

    var selectedTypeId by remember { mutableStateOf<Int?>(null) }
    var selectedRelationId by remember { mutableStateOf<Int?>(null) }
    var dni by remember { mutableStateOf("") }
    var deliveryRelationDetail by remember { mutableStateOf("") }
    var delivererName by remember { mutableStateOf("") }
    var delivererDocument by remember { mutableStateOf("") }
    var contactNumber by remember { mutableStateOf("") }
    var observation by remember { mutableStateOf("") }
    var delivererPhotoUri by remember { mutableStateOf<Uri?>(null) }
    var medicalDocumentUri by remember { mutableStateOf<Uri?>(null) }
    var annexUris by remember { mutableStateOf<List<Uri>>(emptyList()) }
    var cameraTarget by remember { mutableStateOf<PhotoTarget?>(null) }
    var cameraOutputUri by remember { mutableStateOf<Uri?>(null) }
    var pendingCameraTarget by remember { mutableStateOf<PhotoTarget?>(null) }
    var localError by remember { mutableStateOf<String?>(null) }
    val selectedRelation = state.deliveryRelations.firstOrNull { it.id == selectedRelationId }
    val isWorkerRelation = selectedRelation?.code == "TRABAJADOR" || selectedRelation?.name.equals("Trabajador", ignoreCase = true)

    val delivererPicker = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) {
        if (it != null) {
            context.persistReadPermission(it)
            delivererPhotoUri = it
        }
    }
    val documentPicker = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) {
        if (it != null) {
            context.persistReadPermission(it)
            medicalDocumentUri = it
        }
    }
    val annexPicker = rememberLauncherForActivityResult(ActivityResultContracts.OpenMultipleDocuments()) {
        it.forEach(context::persistReadPermission)
        annexUris = (annexUris + it).distinct().take(4)
    }
    val cameraLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success ->
        val uri = cameraOutputUri
        if (success && uri != null) {
            when (cameraTarget) {
                PhotoTarget.Dni -> delivererPhotoUri = uri
                PhotoTarget.Document -> medicalDocumentUri = uri
                null -> Unit
            }
        } else {
            uri?.deleteCacheFile(context)
        }
        cameraTarget = null
        cameraOutputUri = null
    }
    val cameraPermissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        val target = pendingCameraTarget
        pendingCameraTarget = null
        if (granted && target != null) {
            val uri = context.createCameraImageUri()
            if (uri != null) {
                cameraTarget = target
                cameraOutputUri = uri
                cameraLauncher.launch(uri)
            } else {
                localError = "No se pudo preparar la camara."
            }
        } else {
            localError = "Permiso de cámara denegado."
        }
    }

    fun openCamera(target: PhotoTarget) {
        localError = null
        if (context.hasCameraPermission()) {
            val uri = context.createCameraImageUri()
            if (uri != null) {
                cameraTarget = target
                cameraOutputUri = uri
                cameraLauncher.launch(uri)
            } else {
                localError = "No se pudo preparar la camara."
            }
        } else {
            pendingCameraTarget = target
            cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    LaunchedEffect(Unit) {
        viewModel.loadInitialData()
    }

    LaunchedEffect(state.selectedWorker) {
        state.selectedWorker?.let {
            dni = it.dni
            contactNumber = it.phone.orEmpty()
            if (isWorkerRelation) {
                delivererName = "${it.firstName} ${it.lastName}"
                delivererDocument = it.dni
            }
        }
    }

    LaunchedEffect(state.isSaved) {
        if (state.isSaved) onNavigateBack()
    }

    if (state.isSaving) {
        AlertDialog(
            onDismissRequest = { },
            title = { Text("Registrando documento") },
            text = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CircularProgressIndicator(modifier = Modifier.size(24.dp), color = PrimaryGreen)
                    Spacer(modifier = Modifier.width(12.dp))
                    Text("Preparando archivos y enviando el registro...")
                }
            },
            confirmButton = {}
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Nuevo Registro", color = Color.White) },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Volver", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = PrimaryGreen)
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Text("Tipo de Documento", fontWeight = FontWeight.Medium)
            CatalogSelector(
                items = state.documentTypes,
                selectedId = selectedTypeId,
                placeholder = "Seleccione tipo",
                leadingIcon = { Icon(Icons.Default.Description, contentDescription = null, tint = PrimaryGreen) },
                onSelected = { selectedTypeId = it.id }
            )

            Text("Trabajador *", fontWeight = FontWeight.Medium)
            Row(modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = dni,
                    onValueChange = { dni = it.take(80) },
                    modifier = Modifier.weight(1f),
                    placeholder = { Text("DNI, nombre o apellidos") },
                    shape = RoundedCornerShape(8.dp),
                    singleLine = true
                )
                Spacer(modifier = Modifier.width(8.dp))
                Button(
                    onClick = { viewModel.searchWorker(dni) },
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = PrimaryGreen),
                    modifier = Modifier.height(56.dp)
                ) {
                    Icon(Icons.Default.Search, contentDescription = "Buscar", tint = Color.White)
                }
            }

            if (state.workerResults.size > 1) {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    state.workerResults.take(5).forEach { result ->
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable {
                                    viewModel.selectWorker(result)
                                    localError = null
                                },
                            colors = CardDefaults.cardColors(containerColor = Color.White),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                Text("${result.firstName} ${result.lastName}", fontWeight = FontWeight.SemiBold)
                                Text("DNI: ${result.dni}", fontSize = 12.sp, color = Color.Gray)
                            }
                        }
                    }
                }
            }

            state.selectedWorker?.let { worker ->
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFE8F5E9)),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Person, contentDescription = null, tint = PrimaryGreen)
                        Spacer(modifier = Modifier.width(12.dp))
                        Column {
                            Text("${worker.firstName} ${worker.lastName}", fontWeight = FontWeight.Bold)
                            Text("DNI: ${worker.dni}", fontSize = 12.sp)
                            worker.position?.let { Text(it, fontSize = 12.sp, color = Color.Gray) }
                        }
                    }
                }
            }

            Text("Relación de quien entrega *", fontWeight = FontWeight.Medium)
            CatalogSelector(
                items = state.deliveryRelations,
                selectedId = selectedRelationId,
                placeholder = "Seleccione relación",
                onSelected = {
                    selectedRelationId = it.id
                    if (it.code == "TRABAJADOR" || it.name.equals("Trabajador", ignoreCase = true)) {
                        state.selectedWorker?.let { worker ->
                            delivererName = "${worker.firstName} ${worker.lastName}"
                            delivererDocument = worker.dni
                            contactNumber = worker.phone.orEmpty()
                        }
                    } else {
                        deliveryRelationDetail = ""
                        delivererName = ""
                        delivererDocument = ""
                        contactNumber = state.selectedWorker?.phone.orEmpty()
                    }
                }
            )
            if (selectedRelation?.requiresDetail == true) {
                OutlinedTextField(
                    value = deliveryRelationDetail,
                    onValueChange = { deliveryRelationDetail = it },
                    label = { Text("Detalle de relación") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp)
                )
            }

            OutlinedTextField(
                value = delivererName,
                onValueChange = { delivererName = it },
                label = { Text("Nombre de quien entrega *") },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(8.dp),
                singleLine = true
            )
            OutlinedTextField(
                value = delivererDocument,
                onValueChange = { delivererDocument = it },
                label = { Text("Documento de quien entrega") },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(8.dp),
                singleLine = true
            )

            PhotoUploadSection(
                title = "Foto de quien entrega",
                selectedText = delivererPhotoUri?.lastPathSegment,
                onCamera = { openCamera(PhotoTarget.Dni) },
                onAttach = { delivererPicker.launch(arrayOf("image/*")) }
            )
            PhotoUploadSection(
                title = "Documento *",
                selectedText = medicalDocumentUri?.lastPathSegment,
                onCamera = { openCamera(PhotoTarget.Document) },
                onAttach = { documentPicker.launch(documentMimeTypes()) }
            )

            Text(
                "Formatos permitidos: DOCX, PDF, JPEG, JPG, PNG. Tamano maximo por archivo: 10MB. Las imagenes se comprimen antes de subir.",
                fontSize = 12.sp,
                color = Color.Gray
            )

            OutlinedTextField(
                value = contactNumber,
                onValueChange = { contactNumber = it.filter(Char::isDigit).take(15) },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Número de Contacto *") },
                placeholder = { Text("987654321") },
                shape = RoundedCornerShape(8.dp),
                leadingIcon = { Icon(Icons.Default.Phone, contentDescription = null) },
                singleLine = true
            )

            OutlinedTextField(
                value = observation,
                onValueChange = { observation = it },
                label = { Text("Observación") },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(8.dp),
                minLines = 2
            )

            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Anexos Adicionales (Máx. 4)", fontWeight = FontWeight.Medium)
                Text("${annexUris.size}/4 archivos", fontSize = 12.sp, color = Color.Gray)
            }
            OutlinedButton(
                onClick = { annexPicker.launch(documentMimeTypes()) },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(8.dp)
            ) {
                Icon(Icons.Default.Add, contentDescription = null, tint = PrimaryGreen)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Adjuntar Archivo", color = PrimaryGreen)
            }
            annexUris.forEachIndexed { index, uri ->
                Text("Anexo ${index + 1}: ${uri.lastPathSegment}", fontSize = 12.sp, color = Color.Gray)
            }

            (localError ?: state.error)?.let {
                Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
            }

            Button(
                onClick = {
                    viewModel.saveDocument(
                        documentTypeId = selectedTypeId,
                        deliveryRelationId = selectedRelationId,
                        deliveryRelationDetail = deliveryRelationDetail,
                        delivererName = delivererName,
                        delivererDocument = delivererDocument,
                        contactNumber = contactNumber,
                        observation = observation,
                        delivererPhotoUri = delivererPhotoUri,
                        medicalDocumentUri = medicalDocumentUri,
                        annexUris = annexUris
                    )
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp),
                colors = ButtonDefaults.buttonColors(containerColor = PrimaryGreen),
                shape = RoundedCornerShape(8.dp),
                enabled = !state.isLoading && !state.isSaving
            ) {
                if (state.isSaving) {
                    CircularProgressIndicator(color = Color.White, modifier = Modifier.size(24.dp))
                } else {
                    Text("Guardar Registro", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CatalogSelector(
    items: List<CatalogEntity>,
    selectedId: Int?,
    placeholder: String,
    leadingIcon: @Composable (() -> Unit)? = null,
    onSelected: (CatalogEntity) -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    val selected = items.firstOrNull { it.id == selectedId }

    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = !expanded }) {
        OutlinedTextField(
            value = selected?.name ?: placeholder,
            onValueChange = {},
            readOnly = true,
            leadingIcon = leadingIcon,
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
            modifier = Modifier
                .menuAnchor()
                .fillMaxWidth(),
            shape = RoundedCornerShape(8.dp),
            colors = OutlinedTextFieldDefaults.colors(
                focusedContainerColor = Color(0xFFE8F5E9),
                unfocusedContainerColor = Color(0xFFE8F5E9)
            )
        )
        ExposedDropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false },
            modifier = Modifier.exposedDropdownSize(matchTextFieldWidth = true)
        ) {
            if (items.isEmpty()) {
                DropdownMenuItem(
                    text = {
                        Text(
                            "Sin datos. Sincronice Data Maestra.",
                            color = MaterialTheme.colorScheme.onSurface
                        )
                    },
                    onClick = { expanded = false }
                )
            }
            items.forEach { item ->
                DropdownMenuItem(
                    text = { Text(item.name, color = MaterialTheme.colorScheme.onSurface) },
                    onClick = {
                        onSelected(item)
                        expanded = false
                    }
                )
            }
        }
    }
}

@Composable
private fun PhotoUploadSection(
    title: String,
    selectedText: String?,
    onCamera: () -> Unit,
    onAttach: () -> Unit
) {
    Column {
        Text(title, fontWeight = FontWeight.Medium, modifier = Modifier.padding(bottom = 8.dp))
        if (!selectedText.isNullOrBlank()) {
            Text(selectedText, color = PrimaryGreen, fontSize = 12.sp, modifier = Modifier.padding(bottom = 8.dp))
        }
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(onClick = onCamera, modifier = Modifier.weight(1f), shape = RoundedCornerShape(8.dp)) {
                Icon(Icons.Default.CameraAlt, contentDescription = null, tint = Color.Black)
                Spacer(modifier = Modifier.width(4.dp))
                Text("Tomar Foto", color = Color.Black, fontSize = 13.sp)
            }
            OutlinedButton(onClick = onAttach, modifier = Modifier.weight(1f), shape = RoundedCornerShape(8.dp)) {
                Icon(Icons.Default.AttachFile, contentDescription = null, tint = Color.Black)
                Spacer(modifier = Modifier.width(4.dp))
                Text("Adjuntar", color = Color.Black, fontSize = 13.sp)
            }
        }
    }
}

private enum class PhotoTarget {
    Dni,
    Document
}

private fun Context.hasCameraPermission(): Boolean {
    return ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED
}

private fun Context.createCameraImageUri(): Uri? {
    return try {
        val directory = File(cacheDir, "camera").apply { mkdirs() }
        val file = File(directory, "captura_${System.currentTimeMillis()}.jpg")
        FileProvider.getUriForFile(this, "$packageName.fileprovider", file)
    } catch (_: IllegalArgumentException) {
        null
    }
}

private fun Uri.deleteCacheFile(context: Context) {
    if (scheme == "content" && authority == "${context.packageName}.fileprovider") {
        path?.substringAfterLast('/')?.takeIf { it.isNotBlank() }?.let { fileName ->
            File(context.cacheDir, "camera/$fileName").delete()
        }
    }
}

private fun documentMimeTypes(): Array<String> = arrayOf(
    "image/jpeg",
    "image/png",
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
)

private fun Context.persistReadPermission(uri: Uri) {
    try {
        contentResolver.takePersistableUriPermission(uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
    } catch (_: SecurityException) {
        // Some providers grant only temporary access; those URIs are still readable during the session.
    } catch (_: IllegalArgumentException) {
        // File/content providers that do not support persistable permissions are accepted.
    }
}
