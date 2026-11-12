package io.aiku.droid.ui.profile

import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.PasswordChange
import io.aiku.droid.data.api.PasswordReset
import io.aiku.droid.data.api.PasswordResult
import io.aiku.droid.data.api.Rejection
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.UiMessage
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class ChangePasswordViewModelTest {

    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana"))

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `the form asks for every field and a matching confirmation before calling the server`() {
        val api = FakeAuthApi()
        val viewModel = ChangePasswordViewModel(api, sessions)

        viewModel.changePassword()

        assertEquals(UiMessage.FromResource(R.string.profile_password_current_required), viewModel.state.value.currentPasswordError)
        assertEquals(UiMessage.FromResource(R.string.password_new_required), viewModel.state.value.newPasswordError)
        assertEquals(UiMessage.FromResource(R.string.password_confirm_required), viewModel.state.value.confirmationError)

        viewModel.onCurrentPasswordChange("old")
        viewModel.onNewPasswordChange("new-secret")
        viewModel.onConfirmationChange("new-secrte")
        viewModel.changePassword()

        assertNull(viewModel.state.value.currentPasswordError)
        assertEquals(UiMessage.FromResource(R.string.password_mismatch), viewModel.state.value.confirmationError)
        assertTrue(api.changes.isEmpty())
    }

    @Test
    fun `a change sends the passwords with the session token and is then done`() {
        val api = FakeAuthApi(PasswordResult.Done)
        val viewModel = filledFor(api)

        viewModel.changePassword()

        assertEquals("7|abc" to PasswordChange("old", "new-secret", "new-secret"), api.changes.single())
        assertTrue(viewModel.state.value.isChanged)
        assertEquals("", viewModel.state.value.currentPassword)
        assertEquals("", viewModel.state.value.newPassword)
    }

    @Test
    fun `a wrong current password and weak new password are shown under their fields`() {
        val api = FakeAuthApi(
            PasswordResult.Rejected(
                Rejection(
                    mapOf(
                        "current_password" to "The current password is incorrect.",
                        "password" to "The password field must be at least 8 characters.",
                    ),
                ),
            ),
        )
        val viewModel = filledFor(api)

        viewModel.changePassword()

        val state = viewModel.state.value
        assertEquals(UiMessage.FromServer("The current password is incorrect."), state.currentPasswordError)
        assertEquals(UiMessage.FromServer("The password field must be at least 8 characters."), state.newPasswordError)
        assertFalse(state.isChanged)
        assertFalse(state.isSubmitting)
        assertEquals("old", state.currentPassword)
    }

    @Test
    fun `an expired token shows the session expiry and signing in again ends the session`() {
        val viewModel = filledFor(FakeAuthApi(PasswordResult.SessionExpired))

        viewModel.changePassword()

        assertTrue(viewModel.state.value.isSessionExpired)

        viewModel.signInAgain()

        assertNull(sessions.session.value)
    }

    @Test
    fun `no connection and a throttle are shown in the alert`() {
        val viewModel = filledFor(FakeAuthApi(PasswordResult.Unreachable, PasswordResult.Rejected(Rejection(message = "Too Many Attempts."))))

        viewModel.changePassword()
        assertEquals(
            ChangePasswordAlert(
                title = UiMessage.FromResource(R.string.alert_offline_title),
                body = UiMessage.FromResource(R.string.profile_password_offline_body),
            ),
            viewModel.state.value.alert,
        )

        viewModel.changePassword()
        assertEquals(
            ChangePasswordAlert(
                title = UiMessage.FromResource(R.string.profile_password_failed_title),
                body = UiMessage.FromServer("Too Many Attempts."),
            ),
            viewModel.state.value.alert,
        )
    }

    private fun filledFor(api: FakeAuthApi) = ChangePasswordViewModel(api, sessions).apply {
        onCurrentPasswordChange("old")
        onNewPasswordChange("new-secret")
        onConfirmationChange("new-secret")
    }

    private class FakeAuthApi(vararg results: PasswordResult) : AuthApi {
        private val queued = ArrayDeque(results.toList())
        val changes = mutableListOf<Pair<String, PasswordChange>>()

        override suspend fun login(credentials: LoginCredentials): LoginResult = LoginResult.Unreachable
        override suspend fun logout(token: String) = Unit
        override suspend fun requestPasswordResetCode(login: String): PasswordResult = PasswordResult.Unreachable
        override suspend fun resetPassword(reset: PasswordReset): PasswordResult = PasswordResult.Unreachable

        override suspend fun changePassword(token: String, change: PasswordChange): PasswordResult {
            changes += token to change
            return queued.removeFirst()
        }
    }

    private class InMemorySessions(initial: StaffSession) : SessionRepository {
        private val current = MutableStateFlow<StaffSession?>(initial)
        override val session: StateFlow<StaffSession?> = current.asStateFlow()
        override fun start(session: StaffSession, rememberOnDevice: Boolean) {
            current.value = session
        }
        override fun end() {
            current.value = null
        }
    }
}
