package io.aiku.droid.data.api

import io.aiku.droid.data.session.StaffSession

data class LoginCredentials(
    val username: String,
    val password: String,
    val deviceName: String,
    val oneTimePassword: String? = null,
)

data class Rejection(
    val fieldErrors: Map<String, String> = emptyMap(),
    val message: String = "",
) {
    val firstMessage: String? get() = fieldErrors.values.firstOrNull() ?: message.takeIf { it.isNotBlank() }
}

sealed interface LoginResult {
    data class SignedIn(val session: StaffSession) : LoginResult
    data object OneTimePasswordRequired : LoginResult
    data class Rejected(val rejection: Rejection) : LoginResult
    data object AppKeyRejected : LoginResult
    data object Unreachable : LoginResult
    data class ServerError(val statusCode: Int) : LoginResult
}

sealed interface PasswordResetResult {
    data object LinkSent : PasswordResetResult
    data class Rejected(val rejection: Rejection) : PasswordResetResult
    data object AppKeyRejected : PasswordResetResult
    data object Unreachable : PasswordResetResult
    data class ServerError(val statusCode: Int) : PasswordResetResult
}

interface AuthApi {
    suspend fun login(credentials: LoginCredentials): LoginResult

    suspend fun logout(token: String)

    suspend fun sendPasswordResetLink(email: String): PasswordResetResult
}
