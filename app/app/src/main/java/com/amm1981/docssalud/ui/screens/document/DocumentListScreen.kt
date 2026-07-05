package com.amm1981.docssalud.ui.screens.document

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.amm1981.docssalud.data.repository.DocumentUi
import com.amm1981.docssalud.ui.theme.PendingBackground
import com.amm1981.docssalud.ui.theme.PendingOrange
import com.amm1981.docssalud.ui.theme.PrimaryGreen
import com.amm1981.docssalud.ui.theme.ReceivedBackground
import com.amm1981.docssalud.ui.theme.ReceivedBlue
import com.amm1981.docssalud.ui.theme.RegisteredBackground
import com.amm1981.docssalud.ui.theme.RegisteredGreen

private data class DocumentTab(val title: String, val status: String)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DocumentListScreen(
    viewModel: DocumentListViewModel = hiltViewModel(),
    onOpenMenu: () -> Unit,
    onNavigateToDetail: (String) -> Unit
) {
    var selectedTabIndex by remember { mutableStateOf(0) }
    val tabs = listOf(
        DocumentTab("Pendientes", "PENDIENTE"),
        DocumentTab("Recepcionados", "RECEPCIONADO"),
        DocumentTab("Registrados", "REGISTRADO"),
        DocumentTab("Rechazados", "RECHAZADO")
    )
    val state by viewModel.state.collectAsState()

    LaunchedEffect(selectedTabIndex) {
        viewModel.loadDocuments(tabs[selectedTabIndex].status)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Mis Documentos", color = Color.White) },
                navigationIcon = {
                    IconButton(onClick = onOpenMenu) {
                        Icon(Icons.Default.Menu, contentDescription = "Menu", tint = Color.White)
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.loadDocuments(tabs[selectedTabIndex].status) }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Actualizar", tint = Color.White)
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
        ) {
            ScrollableTabRow(
                selectedTabIndex = selectedTabIndex,
                containerColor = Color.White,
                contentColor = PrimaryGreen,
                edgePadding = 0.dp
            ) {
                tabs.forEachIndexed { index, tab ->
                    Tab(
                        selected = selectedTabIndex == index,
                        onClick = { selectedTabIndex = index },
                        modifier = Modifier.widthIn(min = 132.dp),
                        text = {
                            Text(
                                text = tab.title,
                                fontSize = 13.sp,
                                maxLines = 1,
                                color = if (selectedTabIndex == index) PrimaryGreen else Color.Gray,
                                fontWeight = if (selectedTabIndex == index) FontWeight.Bold else FontWeight.Normal
                            )
                        }
                    )
                }
            }
            if (state.isRefreshing) {
                LinearProgressIndicator(
                    modifier = Modifier.fillMaxWidth(),
                    color = PrimaryGreen
                )
            }

            when {
                state.isLoading && state.documents.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = PrimaryGreen)
                }
                state.error != null && state.documents.isEmpty() -> Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) {
                    Text(state.error ?: "", color = MaterialTheme.colorScheme.error)
                }
                state.documents.isEmpty() -> Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) {
                    Text("No hay documentos en este estado.", color = Color.Gray)
                }
                else -> LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(state.documents, key = { it.id }) { doc ->
                        DocumentCard(doc = doc, onClick = { onNavigateToDetail(doc.id) })
                    }
                }
            }
        }
    }
}

@Composable
private fun DocumentCard(
    doc: DocumentUi,
    onClick: () -> Unit
) {
    val visual = statusVisual(doc.status)
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Nro. ${doc.displayId()}", fontWeight = FontWeight.Bold, fontSize = 16.sp)
                Box(
                    modifier = Modifier
                        .background(visual.background, shape = RoundedCornerShape(12.dp))
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(visual.label, color = visual.color, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                }
            }
            Spacer(modifier = Modifier.height(8.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(doc.typeName, fontSize = 14.sp)
                    Text(doc.createdAt, fontSize = 12.sp, color = Color.Gray)
                    Text(doc.workerName, fontSize = 12.sp, color = Color.Gray)
                }
                Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color.Gray)
            }
        }
    }
}

data class StatusVisual(val label: String, val color: Color, val background: Color)

fun statusVisual(status: String): StatusVisual = when (status) {
    "RECEPCIONADO" -> StatusVisual("Recepcionado", ReceivedBlue, ReceivedBackground)
    "REGISTRADO" -> StatusVisual("Registrado", RegisteredGreen, RegisteredBackground)
    "RECHAZADO" -> StatusVisual("Rechazado", Color(0xFFB42318), Color(0xFFFEE4E2))
    else -> StatusVisual("Pendiente", PendingOrange, PendingBackground)
}

fun DocumentUi.displayId(): String {
    return id.toIntOrNull()?.let { "2026-${it.toString().padStart(5, '0')}" } ?: id.take(8)
}
