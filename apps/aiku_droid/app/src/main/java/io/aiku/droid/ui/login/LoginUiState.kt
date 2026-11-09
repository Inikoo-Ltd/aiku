package io.aiku.droid.ui.login

import io.aiku.droid.ui.UiMessage

enum class LoginStep {
    Credentials,
    OneTimePassword,
    PasswordReset,
    PasswordResetSent,
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
    val resetEmail: String = "",
    val isSubmitting: Boolean = false,
    val usernameError: UiMessage? = null,
    val passwordError: UiMessage? = null,
    val oneTimePasswordError: UiMessage? = null,
    val resetEmailError: UiMessage? = null,
    val alert: LoginAlert? = null,
    val passwordFocusRequest: Int = 0,
)

interface LoginActions {
    fun onUsernameChange(value: String)
    fun onPasswordChange(value: String)
    fun onPasswordVisibilityToggle()
    fun onRememberMeChange(value: Boolean)
    fun onOneTimePasswordChange(value: String)
    fun onResetEmailChange(value: String)
    fun signIn()
    fun verifyOneTimePassword()
    fun openPasswordReset()
    fun sendPasswordResetLink()
    fun backToCredentials()
}
