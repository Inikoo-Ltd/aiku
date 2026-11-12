package io.aiku.droid.data.api

import kotlinx.serialization.Serializable

import io.aiku.droid.data.session.StaffSession

data class LoginCredentials(
    val username: String,
    val password: String,
    val deviceName: String,
    val oneTimePassword: String? = null,
    val twoFactorSetupToken: String? = null,
)

data class TwoFactorSetup(
    val setupToken: String,
    val secret: String,
    val otpauthUrl: String,
    val issuer: String,
    val account: String,
    val expiresInSeconds: Int,
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
    data class TwoFactorSetupRequired(val setup: TwoFactorSetup) : LoginResult
    data class Rejected(val rejection: Rejection) : LoginResult
    data object AppKeyRejected : LoginResult
    data object Unreachable : LoginResult
    data class ServerError(val statusCode: Int) : LoginResult
}

data class PasswordReset(
    val login: String,
    val code: String,
    val password: String,
    val passwordConfirmation: String,
)

data class PasswordChange(
    val currentPassword: String,
    val password: String,
    val passwordConfirmation: String,
)

sealed interface PasswordResult {
    data object Done : PasswordResult
    data class Rejected(val rejection: Rejection) : PasswordResult
    data object SessionExpired : PasswordResult
    data object AppKeyRejected : PasswordResult
    data object Unreachable : PasswordResult
    data class ServerError(val statusCode: Int) : PasswordResult
}

@Serializable
data class ProfileOrganisation(val id: Long, val code: String, val name: String)

@Serializable
data class StaffProfile(
    val id: Long,
    val username: String,
    val name: String? = null,
    val position: String? = null,
    val organisation: ProfileOrganisation? = null,
)

sealed interface ProfileResult {
    data class Loaded(val profile: StaffProfile) : ProfileResult
    data object Unavailable : ProfileResult
}

interface AuthApi {
    suspend fun login(credentials: LoginCredentials): LoginResult

    suspend fun logout(token: String)

    suspend fun requestPasswordResetCode(login: String): PasswordResult

    suspend fun resetPassword(reset: PasswordReset): PasswordResult

    suspend fun changePassword(token: String, change: PasswordChange): PasswordResult

    suspend fun profile(token: String): ProfileResult = ProfileResult.Unavailable
}
