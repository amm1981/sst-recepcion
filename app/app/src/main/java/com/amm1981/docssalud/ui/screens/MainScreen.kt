package com.amm1981.docssalud.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CloudSync
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalDrawerSheet
import androidx.compose.material3.ModalNavigationDrawer
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationDrawerItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDrawerState
import androidx.compose.material3.DrawerValue
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.amm1981.docssalud.ui.navigation.Route
import com.amm1981.docssalud.ui.screens.document.DocumentListScreen
import com.amm1981.docssalud.ui.screens.home.HomeScreen
import com.amm1981.docssalud.ui.screens.profile.ProfileScreen
import com.amm1981.docssalud.ui.theme.PrimaryGreen
import kotlinx.coroutines.launch

sealed class BottomNavItem(val route: String, val title: String, val icon: ImageVector) {
    object Home : BottomNavItem(Route.Home.route, "Inicio", Icons.Default.Home)
    object Documents : BottomNavItem(Route.DocumentList.route, "Documentos", Icons.AutoMirrored.Filled.List)
    object Profile : BottomNavItem(Route.Profile.route, "Perfil", Icons.Default.Person)
}

@Composable
fun MainScreen(
    rootNavController: NavHostController,
    viewModel: MainViewModel = hiltViewModel()
) {
    val bottomNavController = rememberNavController()
    val drawerState = rememberDrawerState(initialValue = DrawerValue.Closed)
    val scope = rememberCoroutineScope()
    val state by viewModel.state.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    val items = listOf(
        BottomNavItem.Home,
        BottomNavItem.Documents,
        BottomNavItem.Profile
    )

    fun openDrawer() {
        scope.launch { drawerState.open() }
    }

    fun closeDrawer() {
        scope.launch { drawerState.close() }
    }

    fun navigateBottom(route: String) {
        bottomNavController.navigate(route) {
            popUpTo(bottomNavController.graph.startDestinationId) { saveState = true }
            launchSingleTop = true
            restoreState = true
        }
        closeDrawer()
    }

    LaunchedEffect(Unit) {
        viewModel.syncOnStart()
    }

    LaunchedEffect(state.syncMessage) {
        state.syncMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeSyncMessage()
        }
    }

    if (state.showSyncDialog) {
        AlertDialog(
            onDismissRequest = { },
            title = { Text("Actualizando Data Maestra") },
            text = {
                Column {
                    Text(state.syncProgressMessage.ifBlank { "Descargando informacion..." })
                    Spacer(modifier = Modifier.height(12.dp))
                    LinearProgressIndicator(
                        progress = { state.syncPercent.coerceIn(0, 100) / 100f },
                        modifier = Modifier.fillMaxWidth(),
                        color = PrimaryGreen
                    )
                    Text(
                        text = "${state.syncPercent.coerceIn(0, 100)}%",
                        modifier = Modifier
                            .align(Alignment.End)
                            .padding(top = 8.dp),
                        fontWeight = FontWeight.Bold
                    )
                }
            },
            confirmButton = {
                TextButton(enabled = false, onClick = { }) {
                    Text("Espere")
                }
            }
        )
    }

    ModalNavigationDrawer(
        drawerState = drawerState,
        drawerContent = {
            AppDrawer(
                userName = state.userName,
                userEmail = state.userEmail,
                isSyncing = state.isSyncing,
                onHome = { navigateBottom(BottomNavItem.Home.route) },
                onDocuments = { navigateBottom(BottomNavItem.Documents.route) },
                onNewDocument = {
                    closeDrawer()
                    rootNavController.navigate(Route.DocumentForm.route)
                },
                onProfile = { navigateBottom(BottomNavItem.Profile.route) },
                onSync = { viewModel.syncMasterData(forceWorkers = true, showMessage = true) },
                onLogout = {
                    viewModel.logout()
                    rootNavController.navigate(Route.Login.route) {
                        popUpTo(Route.Home.route) { inclusive = true }
                    }
                }
            )
        }
    ) {
        Scaffold(
            snackbarHost = { SnackbarHost(snackbarHostState) },
            bottomBar = {
                NavigationBar {
                    val navBackStackEntry by bottomNavController.currentBackStackEntryAsState()
                    val currentRoute = navBackStackEntry?.destination?.route

                    items.forEach { item ->
                        NavigationBarItem(
                            icon = { Icon(item.icon, contentDescription = item.title) },
                            label = { Text(item.title) },
                            selected = currentRoute == item.route,
                            onClick = { navigateBottom(item.route) }
                        )
                    }
                }
            }
        ) { innerPadding ->
            NavHost(
                navController = bottomNavController,
                startDestination = BottomNavItem.Home.route,
                modifier = Modifier.padding(bottom = innerPadding.calculateBottomPadding())
            ) {
                composable(BottomNavItem.Home.route) {
                    HomeScreen(
                        onOpenMenu = ::openDrawer,
                        onNavigateToNewRecord = { rootNavController.navigate(Route.DocumentForm.route) },
                        onNavigateToDocuments = { navigateBottom(BottomNavItem.Documents.route) }
                    )
                }
                composable(BottomNavItem.Documents.route) {
                    DocumentListScreen(
                        onOpenMenu = ::openDrawer,
                        onNavigateToDetail = { id -> rootNavController.navigate(Route.DocumentDetail.createRoute(id)) }
                    )
                }
                composable(BottomNavItem.Profile.route) {
                    ProfileScreen(
                        userName = state.userName,
                        userEmail = state.userEmail,
                        onOpenMenu = ::openDrawer,
                        onLogout = {
                            viewModel.logout()
                            rootNavController.navigate(Route.Login.route) {
                                popUpTo(Route.Home.route) { inclusive = true }
                            }
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun AppDrawer(
    userName: String,
    userEmail: String,
    isSyncing: Boolean,
    onHome: () -> Unit,
    onDocuments: () -> Unit,
    onNewDocument: () -> Unit,
    onProfile: () -> Unit,
    onSync: () -> Unit,
    onLogout: () -> Unit
) {
    ModalDrawerSheet {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(56.dp)
                    .background(PrimaryGreen, RoundedCornerShape(12.dp)),
                contentAlignment = Alignment.Center
            ) {
                Text("DS", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 20.sp)
            }
            Text(userName, fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.padding(top = 12.dp))
            Text(userEmail.ifBlank { "DocsSalud" }, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 13.sp)
            Spacer(modifier = Modifier.height(20.dp))

            NavigationDrawerItem(
                label = { Text("Inicio") },
                selected = false,
                icon = { Icon(Icons.Default.Home, contentDescription = null) },
                onClick = onHome
            )
            NavigationDrawerItem(
                label = { Text("Documentos") },
                selected = false,
                icon = { Icon(Icons.AutoMirrored.Filled.List, contentDescription = null) },
                onClick = onDocuments
            )
            NavigationDrawerItem(
                label = { Text("Nuevo Registro") },
                selected = false,
                icon = { Icon(Icons.Default.Add, contentDescription = null) },
                onClick = onNewDocument
            )
            NavigationDrawerItem(
                label = { Text(if (isSyncing) "Actualizando..." else "Data Maestra") },
                selected = false,
                icon = {
                    if (isSyncing) {
                        CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                    } else {
                        Icon(Icons.Default.CloudSync, contentDescription = null)
                    }
                },
                onClick = onSync
            )
            NavigationDrawerItem(
                label = { Text("Perfil") },
                selected = false,
                icon = { Icon(Icons.Default.Person, contentDescription = null) },
                onClick = onProfile
            )

            Spacer(modifier = Modifier.weight(1f))
            NavigationDrawerItem(
                label = { Text("Cerrar sesión") },
                selected = false,
                icon = { Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = null) },
                onClick = onLogout,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}
