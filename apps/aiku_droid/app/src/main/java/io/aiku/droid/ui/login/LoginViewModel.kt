package io.aiku.droid.ui.login

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.PasswordResetResult
import io.aiku.droid.data.api.Rejection
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.UiMessage
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val ONE_TIME_PASSWORD_FIELD = "one_time_password"
private const val EMAIL_FIELD = "email"

class LoginViewModel(
    private val authApi: AuthApi,
    private val sessions: SessionRepository,
    private val deviceName: String,
) : ViewModel(), LoginActions {

    private val mutableState = MutableStateFlow(LoginUiState())
    val state: StateFlow<LoginUiState> = mutableState.asStateFlow()

    private var submission: Job? = null

    override fun onUsernameChange(value: String) = mutableState.update { it.copy(username = value, usernameError = null) }

    override fun onPasswordChange(value: String) = mutableState.update { it.copy(password = value, passwordError = null) }

    override fun onPasswordVisibilityToggle() = mutableState.update { it.copy(isPasswordVisible = !it.isPasswordVisible) }

    override fun onRememberMeChange(value: Boolean) = mutableState.update { it.copy(rememberMe = value) }

    override fun onOneTimePasswordChange(value: String) =
        mutableState.update { it.copy(oneTimePassword = value.filter(Char::isDigit), oneTimePasswordError = null) }

    override fun onResetEmailChange(value: String) = mutableState.update { it.copy(resetEmail = value, resetEmailError = null) }

    override fun signIn() {
        val current = mutableState.value
        if (current.isSubmitting) {
            return
        }
        val usernameError = UiMessage.FromResource(R.string.login_username_required).takeIf { current.username.isBlank() }
        val passwordError = UiMessage.FromResource(R.string.login_password_required).takeIf { current.password.isEmpty() }
        if (usernameError != null || passwordError != null) {
            mutableState.update { it.copy(usernameError = usernameError, passwordError = passwordError, alert = null) }
            return
        }
        submitLogin(oneTimePassword = null)
    }

    override fun verifyOneTimePassword() {
        val current = mutableState.value
        if (current.isSubmitting) {
            return
        }
        if (current.oneTimePassword.isEmpty()) {
            mutableState.update { it.copy(oneTimePasswordError = UiMessage.FromResource(R.string.otp_required), alert = null) }
            return
        }
        submitLogin(oneTimePassword = current.oneTimePassword)
    }

    override fun openPasswordReset() {
        submission?.cancel()
        mutableState.update {
            it.copy(step = LoginStep.PasswordReset, isSubmitting = false, resetEmailError = null, alert = null)
        }
    }

    override fun backToCredentials() {
        submission?.cancel()
        mutableState.update {
            it.copy(
                step = LoginStep.Credentials,
                password = if (it.step == LoginStep.OneTimePassword) "" else it.password,
                oneTimePassword = "",
                isSubmitting = false,
                usernameError = null,
                passwordError = null,
                oneTimePasswordError = null,
                resetEmailError = null,
                alert = null,
            )
        }
    }

    override fun sendPasswordResetLink() {
        val current = mutableState.value
        if (current.isSubmitting) {
            return
        }
        if (current.resetEmail.isBlank()) {
            mutableState.update { it.copy(resetEmailError = UiMessage.FromResource(R.string.reset_email_required), alert = null) }
            return
        }
        mutableState.update { it.copy(isSubmitting = true, resetEmailError = null, alert = null) }

        submission = viewModelScope.launch {
            when (val result = authApi.sendPasswordResetLink(current.resetEmail.trim())) {
                PasswordResetResult.LinkSent -> mutableState.update { it.copy(step = LoginStep.PasswordResetSent, isSubmitting = false) }
                is PasswordResetResult.Rejected -> showPasswordResetRejection(result.rejection)
                PasswordResetResult.AppKeyRejected -> showAlert(appKeyAlert())
                PasswordResetResult.Unreachable -> showAlert(offlineAlert(R.string.alert_offline_reset_body))
                is PasswordResetResult.ServerError -> showAlert(serverAlert(result.statusCode))
            }
        }
    }

    private fun submitLogin(oneTimePassword: String?) {
        val current = mutableState.value
        mutableState.update {
            it.copy(isSubmitting = true, usernameError = null, passwordError = null, oneTimePasswordError = null, alert = null)
        }
        val credentials = LoginCredentials(
            username = current.username.trim(),
            password = current.password,
            deviceName = deviceName,
            oneTimePassword = oneTimePassword,
        )

        submission = viewModelScope.launch {
            when (val result = authApi.login(credentials)) {
                is LoginResult.SignedIn -> {
                    sessions.start(result.session, rememberOnDevice = current.rememberMe)
                    mutableState.value = LoginUiState(username = current.username, rememberMe = current.rememberMe)
                }
                LoginResult.OneTimePasswordRequired -> mutableState.update {
                    if (current.step == LoginStep.OneTimePassword) {
                        it.copy(isSubmitting = false, oneTimePasswordError = UiMessage.FromResource(R.string.otp_required))
                    } else {
                        it.copy(step = LoginStep.OneTimePassword, isSubmitting = false)
                    }
                }
                is LoginResult.Rejected -> showLoginRejection(current.step, result.rejection)
                LoginResult.AppKeyRejected -> showAlert(appKeyAlert())
                LoginResult.Unreachable -> showAlert(offlineAlert(R.string.alert_offline_sign_in_body))
                is LoginResult.ServerError -> showAlert(serverAlert(result.statusCode))
            }
        }
    }

    private fun showLoginRejection(step: LoginStep, rejection: Rejection) {
        val codeError = rejection.fieldErrors[ONE_TIME_PASSWORD_FIELD]
        if (step == LoginStep.OneTimePassword && codeError != null) {
            mutableState.update {
                it.copy(isSubmitting = false, oneTimePassword = "", oneTimePasswordError = UiMessage.FromServer(codeError))
            }
            return
        }

        val alert = LoginAlert(
            title = UiMessage.FromResource(R.string.login_failed_title),
            body = rejection.firstMessage?.let { UiMessage.FromServer(it) } ?: UiMessage.FromResource(R.string.login_failed_body),
        )
        mutableState.update {
            if (step == LoginStep.Credentials) {
                it.copy(isSubmitting = false, alert = alert, password = "", passwordFocusRequest = it.passwordFocusRequest + 1)
            } else {
                it.copy(isSubmitting = false, alert = alert)
            }
        }
    }

    private fun showPasswordResetRejection(rejection: Rejection) {
        val emailError = rejection.fieldErrors[EMAIL_FIELD]
        if (emailError != null) {
            mutableState.update { it.copy(isSubmitting = false, resetEmailError = UiMessage.FromServer(emailError)) }
            return
        }
        showAlert(
            LoginAlert(
                title = UiMessage.FromResource(R.string.reset_failed_title),
                body = rejection.firstMessage?.let { UiMessage.FromServer(it) },
            ),
        )
    }

    private fun showAlert(alert: LoginAlert) = mutableState.update { it.copy(isSubmitting = false, alert = alert) }

    private fun offlineAlert(bodyResource: Int) = LoginAlert(
        title = UiMessage.FromResource(R.string.alert_offline_title),
        body = UiMessage.FromResource(bodyResource),
        isOffline = true,
    )

    private fun appKeyAlert() = LoginAlert(
        title = UiMessage.FromResource(R.string.alert_app_key_title),
        body = UiMessage.FromResource(R.string.alert_app_key_body),
    )

    private fun serverAlert(statusCode: Int) = LoginAlert(
        title = UiMessage.FromResource(R.string.alert_server_title),
        body = UiMessage.FromResource(R.string.alert_server_body, listOf(statusCode)),
    )
}
