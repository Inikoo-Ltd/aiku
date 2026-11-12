package io.aiku.droid.data.api

import io.aiku.droid.data.session.StaffSession
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject

private const val ONE_TIME_PASSWORD_REQUIRED_CODE = "otp_required"
private const val TWO_FACTOR_SETUP_REQUIRED_CODE = "two_factor_setup_required"
private const val HTTP_OK = 200
private const val HTTP_UNAUTHORISED = 401
private const val HTTP_FORBIDDEN = 403
private const val HTTP_NOT_FOUND = 404
private const val HTTP_UNPROCESSABLE = 422
private const val HTTP_TOO_MANY_REQUESTS = 429

@Serializable
data class LoginRequest(
    val username: String,
    val password: String,
    @SerialName("device_name") val deviceName: String,
    @SerialName("one_time_password") val oneTimePassword: String? = null,
    @SerialName("two_factor_setup_token") val twoFactorSetupToken: String? = null,
)

@Serializable
data class PasswordResetCodeRequest(val login: String)

@Serializable
data class PasswordResetRequest(
    val login: String,
    val code: String,
    val password: String,
    @SerialName("password_confirmation") val passwordConfirmation: String,
)

@Serializable
data class PasswordChangeRequest(
    @SerialName("current_password") val currentPassword: String,
    val password: String,
    @SerialName("password_confirmation") val passwordConfirmation: String,
)

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
private data class TwoFactorSetupBlock(
    @SerialName("setup_token") val setupToken: String = "",
    val secret: String = "",
    @SerialName("otpauth_url") val otpauthUrl: String = "",
    val issuer: String = "",
    val account: String = "",
    @SerialName("expires_in") val expiresIn: Int = 0,
)

@Serializable
private data class ErrorResponse(
    val message: String = "",
    val code: String? = null,
    val errors: Map<String, List<String>> = emptyMap(),
    @SerialName("two_factor_setup") val twoFactorSetup: TwoFactorSetupBlock? = null,
)

@Serializable
private data class PasswordResponse(val data: JsonObject)

@Serializable
private data class ProfileResponse(val data: StaffProfile)

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
                when (error.code) {
                    ONE_TIME_PASSWORD_REQUIRED_CODE -> LoginResult.OneTimePasswordRequired
                    TWO_FACTOR_SETUP_REQUIRED_CODE -> error.twoFactorSetup?.toSetup()?.let { LoginResult.TwoFactorSetupRequired(it) }
                        ?: LoginResult.Rejected(error.toRejection())
                    else -> LoginResult.Rejected(error.toRejection())
                }
            }
            ?: LoginResult.ServerError(statusCode)

        HTTP_TOO_MANY_REQUESTS -> LoginResult.Rejected(decode<ErrorResponse>(body)?.toRejection() ?: Rejection())
        HTTP_NOT_FOUND -> LoginResult.AppKeyRejected
        else -> LoginResult.ServerError(statusCode)
    }

    fun parseProfile(statusCode: Int, body: String): ProfileResult =
        decode<ProfileResponse>(body)?.takeIf { statusCode == HTTP_OK }?.let { ProfileResult.Loaded(it.data) } ?: ProfileResult.Unavailable

    fun parsePassword(statusCode: Int, body: String): PasswordResult = when (statusCode) {
        HTTP_OK -> if (decode<PasswordResponse>(body) != null) PasswordResult.Done else PasswordResult.ServerError(statusCode)
        HTTP_UNPROCESSABLE, HTTP_TOO_MANY_REQUESTS -> PasswordResult.Rejected(decode<ErrorResponse>(body)?.toRejection() ?: Rejection())
        HTTP_UNAUTHORISED, HTTP_FORBIDDEN -> PasswordResult.SessionExpired
        HTTP_NOT_FOUND -> PasswordResult.AppKeyRejected
        else -> PasswordResult.ServerError(statusCode)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun TwoFactorSetupBlock.toSetup(): TwoFactorSetup? {
        if (setupToken.isBlank() || secret.isBlank() || otpauthUrl.isBlank()) {
            return null
        }
        return TwoFactorSetup(
            setupToken = setupToken,
            secret = secret,
            otpauthUrl = otpauthUrl,
            issuer = issuer,
            account = account,
            expiresInSeconds = expiresIn,
        )
    }

    private fun ErrorResponse.toRejection() = Rejection(
        fieldErrors = errors
            .mapValues { (_, messages) -> messages.firstOrNull { it.isNotBlank() }.orEmpty() }
            .filterValues { it.isNotEmpty() },
        message = message,
    )
}
