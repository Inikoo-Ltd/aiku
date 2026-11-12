package io.aiku.droid.ui.login

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.PasswordReset
import io.aiku.droid.data.api.PasswordResult
import io.aiku.droid.data.api.Rejection
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.UiMessage
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val ONE_TIME_PASSWORD_FIELD = "one_time_password"
private const val CODE_FIELD = "code"
private const val PASSWORD_FIELD = "password"
private const val PASSWORD_CONFIRMATION_FIELD = "password_confirmation"
private const val LOGIN_FIELD = "login"
private const val EMAIL_SIGN = '@'
const val RESEND_COOLDOWN_SECONDS = 30
private const val ONE_SECOND_MILLIS = 1_000L

class LoginViewModel(
    private val authApi: AuthApi,
    private val sessions: SessionRepository,
    private val deviceName: String,
) : ViewModel(), LoginActions {

    private val mutableState = MutableStateFlow(LoginUiState())
    val state: StateFlow<LoginUiState> = mutableState.asStateFlow()

    private var submission: Job? = null
    private var resendCooldown: Job? = null

    override fun onUsernameChange(value: String) = mutableState.update { it.copy(username = value, usernameError = null) }

    override fun onPasswordChange(value: String) = mutableState.update { it.copy(password = value, passwordError = null) }

    override fun onPasswordVisibilityToggle() = mutableState.update { it.copy(isPasswordVisible = !it.isPasswordVisible) }

    override fun onRememberMeChange(value: Boolean) = mutableState.update { it.copy(rememberMe = value) }

    override fun onOneTimePasswordChange(value: String) {
        val code = value.filter(Char::isDigit).take(ONE_TIME_CODE_LENGTH)
        val previous = mutableState.value.oneTimePassword
        mutableState.update { it.copy(oneTimePassword = code, oneTimePasswordError = null) }
        if (code.length == ONE_TIME_CODE_LENGTH && code != previous) {
            verifyOneTimePassword()
        }
    }

    override fun onResetLoginChange(value: String) = mutableState.update { it.copy(resetLogin = value, resetLoginError = null) }

    override fun onResetCodeChange(value: String) =
        mutableState.update { it.copy(resetCode = value.filter(Char::isDigit).take(ONE_TIME_CODE_LENGTH), resetCodeError = null) }

    override fun onNewPasswordChange(value: String) = mutableState.update { it.copy(newPassword = value, newPasswordError = null) }

    override fun onNewPasswordConfirmationChange(value: String) =
        mutableState.update { it.copy(newPasswordConfirmation = value, newPasswordConfirmationError = null) }

    override fun onNewPasswordVisibilityToggle() = mutableState.update { it.copy(isNewPasswordVisible = !it.isNewPasswordVisible) }

    override fun onNewPasswordConfirmationVisibilityToggle() =
        mutableState.update { it.copy(isNewPasswordConfirmationVisible = !it.isNewPasswordConfirmationVisible) }

    override fun signIn() {
        val current = mutableState.value
        if (current.isSubmitting) {
            return
        }
        val usernameError = UiMessage.FromResource(R.string.login_username_required).takeIf { current.username.isBlank() }
        val passwordError = UiMessage.FromResource(R.string.login_password_required).takeIf { current.password.isEmpty() }
        if (usernameError != null || passwordError != null) {
            mutableState.update { it.copy(usernameError = usernameError, passwordError = passwordError, alert = null, notice = null) }
            return
        }
        submitLogin(oneTimePassword = null)
    }

    override fun verifyOneTimePassword() {
        val current = mutableState.value
        if (current.isSubmitting) {
            return
        }
        if (current.oneTimePassword.length != ONE_TIME_CODE_LENGTH) {
            mutableState.update { it.copy(oneTimePasswordError = UiMessage.FromResource(R.string.otp_required), alert = null) }
            return
        }
        submitLogin(oneTimePassword = current.oneTimePassword)
    }

    override fun openPasswordReset() {
        submission?.cancel()
        mutableState.update {
            it.copy(
                step = LoginStep.PasswordReset,
                resetLogin = it.resetLogin.ifBlank { it.username.trim() },
                isSubmitting = false,
                resetLoginError = null,
                alert = null,
                notice = null,
            )
        }
    }

    override fun backToCredentials() {
        submission?.cancel()
        resendCooldown?.cancel()
        mutableState.update {
            it.copy(
                step = LoginStep.Credentials,
                password = if (it.step == LoginStep.OneTimePassword || it.step == LoginStep.TwoFactorSetup) "" else it.password,
                oneTimePassword = "",
                twoFactorSetup = null,
                resetCode = "",
                newPassword = "",
                newPasswordConfirmation = "",
                isNewPasswordVisible = false,
                isNewPasswordConfirmationVisible = false,
                wrongResetCodes = 0,
                resendCooldownSeconds = 0,
                isSubmitting = false,
                isResending = false,
                usernameError = null,
                passwordError = null,
                oneTimePasswordError = null,
                resetLoginError = null,
                resetCodeError = null,
                newPasswordError = null,
                newPasswordConfirmationError = null,
                alert = null,
                notice = null,
            )
        }
    }

    override fun sendPasswordResetCode() {
        val current = mutableState.value
        if (current.isSubmitting) {
            return
        }
        if (current.resetLogin.isBlank()) {
            mutableState.update { it.copy(resetLoginError = UiMessage.FromResource(R.string.reset_login_required), alert = null) }
            return
        }
        val login = current.resetLogin.trim()
        mutableState.update { it.copy(isSubmitting = true, resetLoginError = null, alert = null) }

        submission = viewModelScope.launch {
            when (val result = authApi.requestPasswordResetCode(login)) {
                PasswordResult.Done -> {
                    mutableState.update {
                        it.copy(
                            step = LoginStep.PasswordResetCode,
                            resetLogin = login,
                            resetCode = "",
                            newPassword = "",
                            newPasswordConfirmation = "",
                            wrongResetCodes = 0,
                            isSubmitting = false,
                            resetCodeError = null,
                            newPasswordError = null,
                            newPasswordConfirmationError = null,
                            notice = UiMessage.FromResource(R.string.reset_code_sent),
                        )
                    }
                    startResendCooldown()
                }
                is PasswordResult.Rejected -> showResetCodeRequestRejection(result.rejection)
                else -> showAlert(passwordProblemAlert(result))
            }
        }
    }

    override fun resendPasswordResetCode() {
        val current = mutableState.value
        if (current.isSubmitting || current.isResending || current.resendCooldownSeconds > 0) {
            return
        }
        mutableState.update { it.copy(isResending = true, alert = null) }

        submission = viewModelScope.launch {
            val result = authApi.requestPasswordResetCode(current.resetLogin.trim())
            mutableState.update { it.copy(isResending = false) }
            when (result) {
                PasswordResult.Done -> {
                    mutableState.update {
                        it.copy(
                            resetCode = "",
                            wrongResetCodes = 0,
                            resetCodeError = null,
                            notice = UiMessage.FromResource(R.string.reset_code_resent),
                        )
                    }
                    startResendCooldown()
                }
                is PasswordResult.Rejected -> showAlert(
                    LoginAlert(
                        title = UiMessage.FromResource(R.string.reset_send_failed_title),
                        body = result.rejection.firstMessage?.let { UiMessage.FromServer(it) },
                    ),
                )
                else -> showAlert(passwordProblemAlert(result))
            }
        }
    }

    override fun resetPassword() {
        val current = mutableState.value
        if (current.isSubmitting || current.isResending) {
            return
        }
        val codeError = UiMessage.FromResource(R.string.reset_code_required).takeIf { current.resetCode.length != ONE_TIME_CODE_LENGTH }
        val newPasswordError = UiMessage.FromResource(R.string.password_new_required).takeIf { current.newPassword.isEmpty() }
        val confirmationError = when {
            current.newPasswordConfirmation.isEmpty() -> UiMessage.FromResource(R.string.password_confirm_required)
            current.newPassword.isNotEmpty() && current.newPasswordConfirmation != current.newPassword -> UiMessage.FromResource(R.string.password_mismatch)
            else -> null
        }
        if (codeError != null || newPasswordError != null || confirmationError != null) {
            mutableState.update {
                it.copy(
                    resetCodeError = codeError,
                    newPasswordError = newPasswordError,
                    newPasswordConfirmationError = confirmationError,
                    alert = null,
                )
            }
            return
        }
        val login = current.resetLogin.trim()
        mutableState.update {
            it.copy(isSubmitting = true, resetCodeError = null, newPasswordError = null, newPasswordConfirmationError = null, alert = null)
        }
        val reset = PasswordReset(
            login = login,
            code = current.resetCode,
            password = current.newPassword,
            passwordConfirmation = current.newPasswordConfirmation,
        )

        submission = viewModelScope.launch {
            when (val result = authApi.resetPassword(reset)) {
                PasswordResult.Done -> finishPasswordReset(login)
                is PasswordResult.Rejected -> showResetRejection(result.rejection)
                else -> showAlert(passwordProblemAlert(result))
            }
        }
    }

    private fun finishPasswordReset(login: String) {
        resendCooldown?.cancel()
        val current = mutableState.value
        val isUsername = EMAIL_SIGN !in login
        mutableState.value = LoginUiState(
            username = if (isUsername) login else current.username,
            rememberMe = current.rememberMe,
            notice = UiMessage.FromResource(R.string.reset_done),
            passwordFocusRequest = if (isUsername) current.passwordFocusRequest + 1 else current.passwordFocusRequest,
        )
    }

    private fun startResendCooldown() {
        resendCooldown?.cancel()
        resendCooldown = viewModelScope.launch {
            for (remaining in RESEND_COOLDOWN_SECONDS downTo 1) {
                mutableState.update { it.copy(resendCooldownSeconds = remaining) }
                delay(ONE_SECOND_MILLIS)
            }
            mutableState.update { it.copy(resendCooldownSeconds = 0) }
        }
    }

    private fun submitLogin(oneTimePassword: String?) {
        val current = mutableState.value
        mutableState.update {
            it.copy(isSubmitting = true, usernameError = null, passwordError = null, oneTimePasswordError = null, alert = null, notice = null)
        }
        val credentials = LoginCredentials(
            username = current.username.trim(),
            password = current.password,
            deviceName = deviceName,
            oneTimePassword = oneTimePassword,
            twoFactorSetupToken = current.twoFactorSetup?.setupToken?.takeIf { current.step == LoginStep.TwoFactorSetup },
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
                        it.copy(step = LoginStep.OneTimePassword, isSubmitting = false, oneTimePassword = "", twoFactorSetup = null)
                    }
                }
                is LoginResult.TwoFactorSetupRequired -> mutableState.update {
                    it.copy(
                        step = LoginStep.TwoFactorSetup,
                        isSubmitting = false,
                        twoFactorSetup = result.setup,
                        oneTimePassword = "",
                        oneTimePasswordError = UiMessage.FromResource(R.string.two_factor_renewed).takeIf { current.step == LoginStep.TwoFactorSetup },
                    )
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
        val isCodeStep = step == LoginStep.OneTimePassword || step == LoginStep.TwoFactorSetup
        if (isCodeStep && codeError != null) {
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

    private fun showResetCodeRequestRejection(rejection: Rejection) {
        val loginError = rejection.fieldErrors[LOGIN_FIELD]
        if (loginError != null) {
            mutableState.update { it.copy(isSubmitting = false, resetLoginError = UiMessage.FromServer(loginError)) }
            return
        }
        showAlert(
            LoginAlert(
                title = UiMessage.FromResource(R.string.reset_send_failed_title),
                body = rejection.firstMessage?.let { UiMessage.FromServer(it) },
            ),
        )
    }

    private fun showResetRejection(rejection: Rejection) {
        val codeError = rejection.fieldErrors[CODE_FIELD]
        val passwordError = rejection.fieldErrors[PASSWORD_FIELD]
        val confirmationError = rejection.fieldErrors[PASSWORD_CONFIRMATION_FIELD]
        if (codeError == null && passwordError == null && confirmationError == null) {
            showAlert(
                LoginAlert(
                    title = UiMessage.FromResource(R.string.reset_failed_title),
                    body = rejection.firstMessage?.let { UiMessage.FromServer(it) },
                ),
            )
            return
        }
        mutableState.update {
            it.copy(
                isSubmitting = false,
                resetCodeError = codeError?.let { message -> UiMessage.FromServer(message) },
                newPasswordError = passwordError?.let { message -> UiMessage.FromServer(message) },
                newPasswordConfirmationError = confirmationError?.let { message -> UiMessage.FromServer(message) },
                wrongResetCodes = if (codeError != null) it.wrongResetCodes + 1 else it.wrongResetCodes,
            )
        }
    }

    private fun showAlert(alert: LoginAlert) = mutableState.update { it.copy(isSubmitting = false, alert = alert) }

    private fun passwordProblemAlert(result: PasswordResult): LoginAlert = when (result) {
        PasswordResult.AppKeyRejected -> appKeyAlert()
        PasswordResult.Unreachable -> offlineAlert(R.string.alert_offline_reset_body)
        is PasswordResult.ServerError -> serverAlert(result.statusCode)
        else -> LoginAlert(title = UiMessage.FromResource(R.string.alert_server_title), body = null)
    }

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
