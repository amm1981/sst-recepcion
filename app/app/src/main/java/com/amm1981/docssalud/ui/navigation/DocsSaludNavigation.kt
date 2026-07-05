package com.amm1981.docssalud.ui.navigation

import android.content.SharedPreferences
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.amm1981.docssalud.ui.screens.MainScreen
import com.amm1981.docssalud.ui.screens.auth.AuthViewModel
import com.amm1981.docssalud.ui.screens.auth.LoginScreen
import com.amm1981.docssalud.ui.screens.document.DocumentDetailScreen
import com.amm1981.docssalud.ui.screens.document.DocumentFormScreen
import com.amm1981.docssalud.ui.screens.document.DocumentListScreen
import com.amm1981.docssalud.ui.screens.document.DocumentStatusScreen

@Composable
fun DocsSaludNavigation(
    isLoggedIn: Boolean,
    navController: NavHostController = rememberNavController()
) {
    val startDestination = if (isLoggedIn) Route.Home.route else Route.Login.route

    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        composable(Route.Login.route) {
            LoginScreen(
                onLoginSuccess = {
                    navController.navigate(Route.Home.route) {
                        popUpTo(Route.Login.route) { inclusive = true }
                    }
                }
            )
        }
        composable(Route.Home.route) {
            MainScreen(rootNavController = navController)
        }
        composable(Route.DocumentForm.route) {
            DocumentFormScreen(
                onNavigateBack = { navController.popBackStack() }
            )
        }
        composable(Route.DocumentList.route) {
            DocumentListScreen(
                onOpenMenu = { },
                onNavigateToDetail = { id -> navController.navigate(Route.DocumentDetail.createRoute(id)) }
            )
        }
        composable(Route.DocumentDetail.route) { backStackEntry ->
            val docId = backStackEntry.arguments?.getString("documentId") ?: ""
            DocumentDetailScreen(
                documentId = docId,
                onNavigateBack = { navController.popBackStack() },
                onNavigateToStatus = { id -> navController.navigate(Route.DocumentStatus.createRoute(id)) }
            )
        }
        composable(Route.DocumentStatus.route) { backStackEntry ->
            val docId = backStackEntry.arguments?.getString("documentId") ?: ""
            DocumentStatusScreen(
                documentId = docId,
                onNavigateBack = { navController.popBackStack() }
            )
        }
        composable(Route.Profile.route) {
            // ProfileScreen(navController)
        }
    }
}
