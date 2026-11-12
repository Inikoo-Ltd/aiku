package io.aiku.droid.ui.login

import io.aiku.droid.data.api.TwoFactorSetup
import io.aiku.droid.ui.UiMessage

const val ONE_TIME_CODE_LENGTH = 6
const val MAX_RESET_CODE_TRIES = 5

enum class LoginStep {
    Credentials,
    OneTimePassword,
    TwoFactorSetup,
    PasswordReset,
    PasswordResetCode,
}

data class LoginAlert(
    val title: UiMessage,
    val body: UiMessage?,
    val isOffline: Boolean = false,
)

data class LoginUiState(
    val step: LoginStep = LoginStep.Credentials,
    val username: String = "",
    val password: String = "",
    val isPasswordVisible: Boolean = false,
    val rememberMe: Boolean = true,
    val oneTimePassword: String = "",
    val twoFactorSetup: TwoFactorSetup? = null,
    val resetLogin: String = "",
    val resetCode: String = "",
    val newPassword: String = "",
    val newPasswordConfirmation: String = "",
    val isNewPasswordVisible: Boolean = false,
    val isNewPasswordConfirmationVisible: Boolean = false,
    val wrongResetCodes: Int = 0,
    val resendCooldownSeconds: Int = 0,
    val isSubmitting: Boolean = false,
    val isResending: Boolean = false,
    val usernameError: UiMessage? = null,
    val passwordError: UiMessage? = null,
    val oneTimePasswordError: UiMessage? = null,
    val resetLoginError: UiMessage? = null,
    val resetCodeError: UiMessage? = null,
    val newPasswordError: UiMessage? = null,
    val newPasswordConfirmationError: UiMessage? = null,
    val alert: LoginAlert? = null,
    val notice: UiMessage? = null,
    val passwordFocusRequest: Int = 0,
) {
    val isResetCodeUsedUp: Boolean get() = wrongResetCodes >= MAX_RESET_CODE_TRIES
}

interface LoginActions {
    fun onUsernameChange(value: String)
    fun onPasswordChange(value: String)
    fun onPasswordVisibilityToggle()
    fun onRememberMeChange(value: Boolean)
    fun onOneTimePasswordChange(value: String)
    fun onResetLoginChange(value: String)
    fun onResetCodeChange(value: String)
    fun onNewPasswordChange(value: String)
    fun onNewPasswordConfirmationChange(value: String)
    fun onNewPasswordVisibilityToggle()
    fun onNewPasswordConfirmationVisibilityToggle()
    fun signIn()
    fun verifyOneTimePassword()
    fun openPasswordReset()
    fun sendPasswordResetCode()
    fun resendPasswordResetCode()
    fun resetPassword()
    fun backToCredentials()
}
