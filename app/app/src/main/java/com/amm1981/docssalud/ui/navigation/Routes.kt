package com.amm1981.docssalud.ui.navigation

sealed class Route(val route: String) {
    object Login : Route("login")
    object Home : Route("home")
    object DocumentForm : Route("document_form")
    object DocumentList : Route("document_list")
    object DocumentDetail : Route("document_detail/{documentId}") {
        fun createRoute(documentId: String) = "document_detail/$documentId"
    }
    object DocumentStatus : Route("document_status/{documentId}") {
        fun createRoute(documentId: String) = "document_status/$documentId"
    }
    object Profile : Route("profile")
}
