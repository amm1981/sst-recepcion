package com.amm1981.docssalud

import android.content.SharedPreferences
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Surface
import androidx.compose.ui.Modifier
import com.amm1981.docssalud.ui.navigation.DocsSaludNavigation
import com.amm1981.docssalud.ui.theme.DocsSaludTheme
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    @Inject
    lateinit var prefs: SharedPreferences

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // Read login state SYNCHRONOUSLY before setContent, so we never flash login screen
        val isLoggedIn = prefs.getString("auth_token", null) != null

        setContent {
            DocsSaludTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    DocsSaludNavigation(isLoggedIn = isLoggedIn)
                }
            }
        }
    }
}
