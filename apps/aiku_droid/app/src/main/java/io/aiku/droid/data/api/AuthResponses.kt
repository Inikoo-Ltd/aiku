package io.aiku.droid.data.api

import io.aiku.droid.data.session.StaffSession
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json

private const val ONE_TIME_PASSWORD_REQUIRED_CODE = "otp_required"
private const val HTTP_OK = 200
private const val HTTP_NOT_FOUND = 404
private const val HTTP_UNPROCESSABLE = 422
private const val HTTP_TOO_MANY_REQUESTS = 429

@Serializable
data class LoginRequest(
    val username: String,
    val password: String,
    @SerialName("device_name") val deviceName: String,
    @SerialName("one_time_password") val oneTimePassword: String? = null,
)

@Serializable
data class PasswordResetRequest(val email: String)

@Serializable
private data class LoginResponse(
    val token: String,
    val user: StaffUser,
)

@Serializable
private data class StaffUser(
    val id: Long,
    val username: String,
    @SerialName("contact_name") val contactName: String? = null,
)

@Serializable
private data class ErrorResponse(
    val message: String = "",
    val code: String? = null,
    val errors: Map<String, List<String>> = emptyMap(),
)

object AuthResponses {
    val json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
    }

    fun parseLogin(statusCode: Int, body: String): LoginResult = when (statusCode) {
        HTTP_OK -> decode<LoginResponse>(body)
            ?.let { response ->
                LoginResult.SignedIn(
                    StaffSession(
                        token = response.token,
                        userId = response.user.id,
                        username = response.user.username,
                        displayName = response.user.contactName?.takeIf { it.isNotBlank() } ?: response.user.username,
                    ),
                )
            }
            ?: LoginResult.ServerError(statusCode)

        HTTP_UNPROCESSABLE -> decode<ErrorResponse>(body)
            ?.let { error ->
                if (error.code == ONE_TIME_PASSWORD_REQUIRED_CODE) {
                    LoginResult.OneTimePasswordRequired
                } else {
                    LoginResult.Rejected(error.toRejection())
                }
            }
            ?: LoginResult.ServerError(statusCode)

        HTTP_TOO_MANY_REQUESTS -> LoginResult.Rejected(decode<ErrorResponse>(body)?.toRejection() ?: Rejection())
        HTTP_NOT_FOUND -> LoginResult.AppKeyRejected
        else -> LoginResult.ServerError(statusCode)
    }

    fun parsePasswordReset(statusCode: Int, body: String): PasswordResetResult = when (statusCode) {
        HTTP_OK -> PasswordResetResult.LinkSent
        HTTP_UNPROCESSABLE, HTTP_TOO_MANY_REQUESTS -> PasswordResetResult.Rejected(decode<ErrorResponse>(body)?.toRejection() ?: Rejection())
        HTTP_NOT_FOUND -> PasswordResetResult.AppKeyRejected
        else -> PasswordResetResult.ServerError(statusCode)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun ErrorResponse.toRejection() = Rejection(
        fieldErrors = errors
            .mapValues { (_, messages) -> messages.firstOrNull { it.isNotBlank() }.orEmpty() }
            .filterValues { it.isNotEmpty() },
        message = message,
    )
}
