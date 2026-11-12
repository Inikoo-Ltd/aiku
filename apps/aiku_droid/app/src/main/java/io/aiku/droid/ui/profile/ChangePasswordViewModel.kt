package io.aiku.droid.ui.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.PasswordChange
import io.aiku.droid.data.api.PasswordResult
import io.aiku.droid.data.api.Rejection
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.UiMessage
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val CURRENT_PASSWORD_FIELD = "current_password"
private const val PASSWORD_FIELD = "password"
private const val PASSWORD_CONFIRMATION_FIELD = "password_confirmation"

data class ChangePasswordAlert(val title: UiMessage, val body: UiMessage?)

data class ChangePasswordUiState(
    val currentPassword: String = "",
    val newPassword: String = "",
    val confirmation: String = "",
    val isCurrentPasswordVisible: Boolean = false,
    val isNewPasswordVisible: Boolean = false,
    val isConfirmationVisible: Boolean = false,
    val currentPasswordError: UiMessage? = null,
    val newPasswordError: UiMessage? = null,
    val confirmationError: UiMessage? = null,
    val alert: ChangePasswordAlert? = null,
    val isSubmitting: Boolean = false,
    val isChanged: Boolean = false,
    val isSessionExpired: Boolean = false,
)

interface ChangePasswordActions {
    fun onCurrentPasswordChange(value: String)
    fun onNewPasswordChange(value: String)
    fun onConfirmationChange(value: String)
    fun onCurrentPasswordVisibilityToggle()
    fun onNewPasswordVisibilityToggle()
    fun onConfirmationVisibilityToggle()
    fun changePassword()
    fun signInAgain()
}

class ChangePasswordViewModel(
    private val authApi: AuthApi,
    private val sessions: SessionRepository,
) : ViewModel(), ChangePasswordActions {

    private val mutableState = MutableStateFlow(ChangePasswordUiState())
    val state: StateFlow<ChangePasswordUiState> = mutableState.asStateFlow()

    override fun onCurrentPasswordChange(value: String) =
        mutableState.update { it.copy(currentPassword = value, currentPasswordError = null) }

    override fun onNewPasswordChange(value: String) = mutableState.update { it.copy(newPassword = value, newPasswordError = null) }

    override fun onConfirmationChange(value: String) = mutableState.update { it.copy(confirmation = value, confirmationError = null) }

    override fun onCurrentPasswordVisibilityToggle() =
        mutableState.update { it.copy(isCurrentPasswordVisible = !it.isCurrentPasswordVisible) }

    override fun onNewPasswordVisibilityToggle() = mutableState.update { it.copy(isNewPasswordVisible = !it.isNewPasswordVisible) }

    override fun onConfirmationVisibilityToggle() = mutableState.update { it.copy(isConfirmationVisible = !it.isConfirmationVisible) }

    override fun changePassword() {
        val current = mutableState.value
        if (current.isSubmitting || current.isChanged) {
            return
        }
        val token = sessions.session.value?.token ?: return
        val currentPasswordError = UiMessage.FromResource(R.string.profile_password_current_required).takeIf { current.currentPassword.isEmpty() }
        val newPasswordError = UiMessage.FromResource(R.string.password_new_required).takeIf { current.newPassword.isEmpty() }
        val confirmationError = when {
            current.confirmation.isEmpty() -> UiMessage.FromResource(R.string.password_confirm_required)
            current.newPassword.isNotEmpty() && current.confirmation != current.newPassword -> UiMessage.FromResource(R.string.password_mismatch)
            else -> null
        }
        if (currentPasswordError != null || newPasswordError != null || confirmationError != null) {
            mutableState.update {
                it.copy(
                    currentPasswordError = currentPasswordError,
                    newPasswordError = newPasswordError,
                    confirmationError = confirmationError,
                    alert = null,
                )
            }
            return
        }
        mutableState.update {
            it.copy(isSubmitting = true, currentPasswordError = null, newPasswordError = null, confirmationError = null, alert = null)
        }
        val change = PasswordChange(
            currentPassword = current.currentPassword,
            password = current.newPassword,
            passwordConfirmation = current.confirmation,
        )

        viewModelScope.launch {
            when (val result = authApi.changePassword(token, change)) {
                PasswordResult.Done -> mutableState.value = ChangePasswordUiState(isChanged = true)
                is PasswordResult.Rejected -> showRejection(result.rejection)
                PasswordResult.SessionExpired -> mutableState.update { it.copy(isSubmitting = false, isSessionExpired = true) }
                PasswordResult.AppKeyRejected -> showAlert(
                    ChangePasswordAlert(
                        title = UiMessage.FromResource(R.string.alert_app_key_title),
                        body = UiMessage.FromResource(R.string.alert_app_key_body),
                    ),
                )
                PasswordResult.Unreachable -> showAlert(
                    ChangePasswordAlert(
                        title = UiMessage.FromResource(R.string.alert_offline_title),
                        body = UiMessage.FromResource(R.string.profile_password_offline_body),
                    ),
                )
                is PasswordResult.ServerError -> showAlert(
                    ChangePasswordAlert(
                        title = UiMessage.FromResource(R.string.alert_server_title),
                        body = UiMessage.FromResource(R.string.alert_server_body, listOf(result.statusCode)),
                    ),
                )
            }
        }
    }

    override fun signInAgain() {
        sessions.end()
    }

    private fun showRejection(rejection: Rejection) {
        val currentPasswordError = rejection.fieldErrors[CURRENT_PASSWORD_FIELD]
        val newPasswordError = rejection.fieldErrors[PASSWORD_FIELD]
        val confirmationError = rejection.fieldErrors[PASSWORD_CONFIRMATION_FIELD]
        if (currentPasswordError == null && newPasswordError == null && confirmationError == null) {
            showAlert(
                ChangePasswordAlert(
                    title = UiMessage.FromResource(R.string.profile_password_failed_title),
                    body = rejection.firstMessage?.let { UiMessage.FromServer(it) },
                ),
            )
            return
        }
        mutableState.update {
            it.copy(
                isSubmitting = false,
                currentPasswordError = currentPasswordError?.let { message -> UiMessage.FromServer(message) },
                newPasswordError = newPasswordError?.let { message -> UiMessage.FromServer(message) },
                confirmationError = confirmationError?.let { message -> UiMessage.FromServer(message) },
            )
        }
    }

    private fun showAlert(alert: ChangePasswordAlert) = mutableState.update { it.copy(isSubmitting = false, alert = alert) }
}
