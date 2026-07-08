package com.amm1981.docssalud.data.repository

import android.content.SharedPreferences
import com.amm1981.docssalud.data.api.DocsSaludApi
import com.amm1981.docssalud.data.api.LoginRequest
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val api: DocsSaludApi,
    private val prefs: SharedPreferences
) {
    suspend fun login(user: String, password: String): Result<Unit> {
        return try {
            val response = api.login(LoginRequest(user, password))
            if (response.isSuccessful && response.body() != null) {
                val body = response.body()!!
                prefs.edit()
                    .putString("auth_token", body.token)
                    .putString("user_name", body.user.name)
                    .putString("user_email", body.user.email)
                    .apply()
                Result.success(Unit)
            } else {
                Result.failure(Exception("Credenciales incorrectas o error en el servidor."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun isLoggedIn(): Boolean {
        return prefs.getString("auth_token", null) != null
    }

    suspend fun refreshUser(): Result<Unit> {
        return try {
            val response = api.me()
            val user = response.body()?.user
            if (response.isSuccessful && user != null) {
                prefs.edit()
                    .putString("user_name", user.name)
                    .putString("user_email", user.email)
                    .apply()
                Result.success(Unit)
            } else {
                Result.failure(Exception("No se pudo actualizar el perfil."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun userName(): String {
        return prefs.getString("user_name", null) ?: "Usuario"
    }

    fun userEmail(): String {
        return prefs.getString("user_email", null) ?: ""
    }

    fun logout() {
        prefs.edit().remove("auth_token").remove("user_name").remove("user_email").apply()
    }
}
