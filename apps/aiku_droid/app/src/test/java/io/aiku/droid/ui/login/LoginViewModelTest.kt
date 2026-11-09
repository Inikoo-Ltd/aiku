package io.aiku.droid.ui.login

import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.PasswordResetResult
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
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class LoginViewModelTest {

    private val session = StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana Picker")
    private val sessions = InMemorySessions()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `remember me starts ticked`() {
        assertTrue(LoginViewModel(FakeAuthApi(), sessions, DEVICE_NAME).state.value.rememberMe)
    }

    @Test
    fun `signing in with empty fields asks for them without calling the server`() {
        val api = FakeAuthApi()
        val viewModel = LoginViewModel(api, sessions, DEVICE_NAME)

        viewModel.onUsernameChange("   ")
        viewModel.signIn()

        assertEquals(UiMessage.FromResource(R.string.login_username_required), viewModel.state.value.usernameError)
        assertEquals(UiMessage.FromResource(R.string.login_password_required), viewModel.state.value.passwordError)
        assertTrue(api.loginRequests.isEmpty())

        viewModel.onUsernameChange("picker")
        assertNull(viewModel.state.value.usernameError)
    }

    @Test
    fun `signing in starts a session that is remembered when remember me is ticked`() {
        val api = FakeAuthApi(LoginResult.SignedIn(session))
        val viewModel = filledFormFor(api, rememberMe = true)

        viewModel.signIn()

        assertEquals(session, sessions.session.value)
        assertEquals(true, sessions.rememberedOnDevice)
        assertEquals(LoginCredentials("picker", "secret", DEVICE_NAME, oneTimePassword = null), api.loginRequests.single())
        assertEquals("", viewModel.state.value.password)
    }

    @Test
    fun `an account with two factor asks for the code and then signs in with the same credentials`() {
        val api = FakeAuthApi(LoginResult.OneTimePasswordRequired, LoginResult.SignedIn(session))
        val viewModel = filledFormFor(api, rememberMe = false)

        viewModel.signIn()

        assertEquals(LoginStep.OneTimePassword, viewModel.state.value.step)
        assertNull(viewModel.state.value.alert)
        assertNull(sessions.session.value)

        viewModel.onOneTimePasswordChange("123 456")
        viewModel.verifyOneTimePassword()

        assertEquals(
            LoginCredentials("picker", "secret", DEVICE_NAME, oneTimePassword = "123456"),
            api.loginRequests.last(),
        )
        assertEquals(session, sessions.session.value)
        assertEquals(false, sessions.rememberedOnDevice)
    }

    @Test
    fun `verifying without a code asks for it without calling the server`() {
        val api = FakeAuthApi(LoginResult.OneTimePasswordRequired)
        val viewModel = filledFormFor(api)

        viewModel.signIn()
        viewModel.verifyOneTimePassword()

        assertEquals(LoginStep.OneTimePassword, viewModel.state.value.step)
        assertEquals(UiMessage.FromResource(R.string.otp_required), viewModel.state.value.oneTimePasswordError)
        assertEquals(1, api.loginRequests.size)
    }

    @Test
    fun `a wrong code is shown under the code field and the code is cleared`() {
        val api = FakeAuthApi(
            LoginResult.OneTimePasswordRequired,
            LoginResult.Rejected(Rejection(mapOf("one_time_password" to "Invalid OTP is given. Please check your Authenticator App"))),
        )
        val viewModel = filledFormFor(api)

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("000000")
        viewModel.verifyOneTimePassword()

        val state = viewModel.state.value
        assertEquals(LoginStep.OneTimePassword, state.step)
        assertEquals(UiMessage.FromServer("Invalid OTP is given. Please check your Authenticator App"), state.oneTimePasswordError)
        assertEquals("", state.oneTimePassword)
        assertNull(state.alert)
        assertEquals(false, state.isSubmitting)
    }

    @Test
    fun `going back from the code step clears the password and the code`() {
        val viewModel = filledFormFor(FakeAuthApi(LoginResult.OneTimePasswordRequired))

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("123456")
        viewModel.backToCredentials()

        val state = viewModel.state.value
        assertEquals(LoginStep.Credentials, state.step)
        assertEquals(" picker ", state.username)
        assertEquals("", state.password)
        assertEquals("", state.oneTimePassword)
    }

    @Test
    fun `refused credentials show the alert, clear the password and move focus to it`() {
        val message = "These credentials do not match our records."
        val viewModel = filledFormFor(FakeAuthApi(LoginResult.Rejected(Rejection(mapOf("username" to message), message))))

        viewModel.signIn()

        val state = viewModel.state.value
        assertEquals(LoginStep.Credentials, state.step)
        assertEquals(
            LoginAlert(title = UiMessage.FromResource(R.string.login_failed_title), body = UiMessage.FromServer(message)),
            state.alert,
        )
        assertEquals("", state.password)
        assertEquals(1, state.passwordFocusRequest)
        assertNull(sessions.session.value)
    }

    @Test
    fun `no connection, a refused app key and a server error each get their own alert`() {
        val viewModel = filledFormFor(FakeAuthApi(LoginResult.Unreachable, LoginResult.AppKeyRejected, LoginResult.ServerError(500)))

        viewModel.signIn()
        assertEquals(
            LoginAlert(
                title = UiMessage.FromResource(R.string.alert_offline_title),
                body = UiMessage.FromResource(R.string.alert_offline_sign_in_body),
                isOffline = true,
            ),
            viewModel.state.value.alert,
        )
        assertEquals("secret", viewModel.state.value.password)

        viewModel.signIn()
        assertEquals(UiMessage.FromResource(R.string.alert_app_key_title), viewModel.state.value.alert?.title)

        viewModel.signIn()
        assertEquals(UiMessage.FromResource(R.string.alert_server_body, listOf(500)), viewModel.state.value.alert?.body)
    }

    @Test
    fun `asking for a reset link needs an email, then sends it trimmed and shows the sent step`() {
        val api = FakeAuthApi()
        val viewModel = LoginViewModel(api, sessions, DEVICE_NAME)

        viewModel.openPasswordReset()
        viewModel.sendPasswordResetLink()
        assertEquals(UiMessage.FromResource(R.string.reset_email_required), viewModel.state.value.resetEmailError)
        assertTrue(api.resetEmails.isEmpty())

        viewModel.onResetEmailChange("  ana@example.com ")
        viewModel.sendPasswordResetLink()

        assertEquals(listOf("ana@example.com"), api.resetEmails)
        assertEquals(LoginStep.PasswordResetSent, viewModel.state.value.step)
    }

    @Test
    fun `an email the server refuses is shown under the email field`() {
        val api = FakeAuthApi(passwordResetResult = PasswordResetResult.Rejected(Rejection(mapOf("email" to "The email field must be a valid email address."))))
        val viewModel = LoginViewModel(api, sessions, DEVICE_NAME)

        viewModel.openPasswordReset()
        viewModel.onResetEmailChange("ana@")
        viewModel.sendPasswordResetLink()

        assertEquals(LoginStep.PasswordReset, viewModel.state.value.step)
        assertEquals(UiMessage.FromServer("The email field must be a valid email address."), viewModel.state.value.resetEmailError)
    }

    private fun filledFormFor(api: FakeAuthApi, rememberMe: Boolean = true) =
        LoginViewModel(api, sessions, DEVICE_NAME).apply {
            onUsernameChange(" picker ")
            onPasswordChange("secret")
            onRememberMeChange(rememberMe)
        }

    private class FakeAuthApi(
        vararg loginResults: LoginResult,
        private val passwordResetResult: PasswordResetResult = PasswordResetResult.LinkSent,
    ) : AuthApi {
        private val queuedLoginResults = ArrayDeque(loginResults.toList())
        val loginRequests = mutableListOf<LoginCredentials>()
        val resetEmails = mutableListOf<String>()

        override suspend fun login(credentials: LoginCredentials): LoginResult {
            loginRequests += credentials
            return queuedLoginResults.removeFirst()
        }

        override suspend fun logout(token: String) = Unit

        override suspend fun sendPasswordResetLink(email: String): PasswordResetResult {
            resetEmails += email
            return passwordResetResult
        }
    }

    private class InMemorySessions : SessionRepository {
        private val current = MutableStateFlow<StaffSession?>(null)
        override val session: StateFlow<StaffSession?> = current.asStateFlow()
        var rememberedOnDevice: Boolean? = null

        override fun start(session: StaffSession, rememberOnDevice: Boolean) {
            current.value = session
            rememberedOnDevice = rememberOnDevice
        }

        override fun end() {
            current.value = null
        }
    }

    private companion object {
        const val DEVICE_NAME = "Google Pixel 9"
    }
}
