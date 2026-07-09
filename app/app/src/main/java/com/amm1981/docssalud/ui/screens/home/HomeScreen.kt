package com.amm1981.docssalud.ui.screens.home

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.amm1981.docssalud.ui.theme.PrimaryGreen

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    viewModel: HomeViewModel = hiltViewModel(),
    refreshTrigger: Int = 0,
    onOpenMenu: () -> Unit,
    onNavigateToNewRecord: () -> Unit,
    onNavigateToDocuments: () -> Unit
) {
    val state by viewModel.state.collectAsState()

    LaunchedEffect(refreshTrigger) {
        viewModel.loadCounts()
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Inicio", color = Color.White) },
                navigationIcon = {
                    IconButton(onClick = onOpenMenu) {
                        Icon(Icons.Default.Menu, contentDescription = "Menu", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = PrimaryGreen
                )
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .padding(16.dp)
        ) {
            Text(
                text = "Hola, ${state.userName}",
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onBackground
            )
            Text(
                text = "Bienvenido al sistema de recepcion de documentos medicos.",
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = 4.dp, bottom = 24.dp)
            )

            // Nuevo Registro Card
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { onNavigateToNewRecord() },
                colors = CardDefaults.cardColors(containerColor = Color.White),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                shape = RoundedCornerShape(12.dp)
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(56.dp)
                            .background(PrimaryGreen, shape = RoundedCornerShape(8.dp)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.Add, contentDescription = "Nuevo", tint = Color.White)
                    }
                    Column(
                        modifier = Modifier
                            .weight(1f)
                            .padding(horizontal = 16.dp)
                    ) {
                        Text("Nuevo Registro", fontWeight = FontWeight.Bold, fontSize = 16.sp)
                        Text("Registra un nuevo documento medico.", fontSize = 12.sp, color = Color.Gray)
                    }
                    Icon(Icons.Default.ChevronRight, contentDescription = "Ir", tint = PrimaryGreen)
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            // Mis Documentos Summary Card
            Text(
                text = "Mis Documentos",
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { onNavigateToDocuments() },
                colors = CardDefaults.cardColors(containerColor = Color.White),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    SummaryItem("Pendientes", state.pendingCount.toString(), Color(0xFFF57C00), Color(0xFFFFF3E0))
                    HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))
                    SummaryItem("Recepcionados", state.receivedCount.toString(), Color(0xFF1976D2), Color(0xFFE3F2FD))
                    HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))
                    SummaryItem("Registrados", state.registeredCount.toString(), Color(0xFF388E3C), Color(0xFFE8F5E9))
                    HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))
                    SummaryItem("Rechazados", state.rejectedCount.toString(), Color(0xFFD32F2F), Color(0xFFFFEBEE))
                }
            }
        }
    }
}

@Composable
fun SummaryItem(title: String, count: String, iconColor: Color, bgColor: Color) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(32.dp)
                    .background(bgColor, shape = RoundedCornerShape(8.dp)),
                contentAlignment = Alignment.Center
            ) {
                // We use generic shape or icon for now
                Icon(Icons.AutoMirrored.Filled.List, contentDescription = null, tint = iconColor, modifier = Modifier.size(16.dp))
            }
            Spacer(modifier = Modifier.width(16.dp))
            Text(text = title, fontWeight = FontWeight.Medium)
        }
        Box(
            modifier = Modifier
                .background(bgColor, shape = RoundedCornerShape(16.dp))
                .padding(horizontal = 12.dp, vertical = 4.dp)
        ) {
            Text(text = count, color = iconColor, fontWeight = FontWeight.Bold)
        }
    }
}
