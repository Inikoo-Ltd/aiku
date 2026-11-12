package io.aiku.droid.ui.login

import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.PasswordChange
import io.aiku.droid.data.api.PasswordReset
import io.aiku.droid.data.api.PasswordResult
import io.aiku.droid.data.api.Rejection
import io.aiku.droid.data.api.TwoFactorSetup
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
class LoginViewModelTest {

    private val session = StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana Picker")
    private val sessions = InMemorySessions()
    private val dispatcher = UnconfinedTestDispatcher()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
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
    fun `an account with two factor asks for the code and the sixth digit signs in with the same credentials`() {
        val api = FakeAuthApi(LoginResult.OneTimePasswordRequired, LoginResult.SignedIn(session))
        val viewModel = filledFormFor(api, rememberMe = false)

        viewModel.signIn()

        assertEquals(LoginStep.OneTimePassword, viewModel.state.value.step)
        assertNull(viewModel.state.value.alert)
        assertNull(sessions.session.value)

        viewModel.onOneTimePasswordChange("123 45")
        assertEquals(1, api.loginRequests.size)

        viewModel.onOneTimePasswordChange("123 456")

        assertEquals(
            LoginCredentials("picker", "secret", DEVICE_NAME, oneTimePassword = "123456"),
            api.loginRequests.last(),
        )
        assertEquals(session, sessions.session.value)
        assertEquals(false, sessions.rememberedOnDevice)
    }

    @Test
    fun `the code field keeps digits only and at most six of them`() {
        val viewModel = filledFormFor(FakeAuthApi(LoginResult.OneTimePasswordRequired, LoginResult.SignedIn(session)))

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("12a-3")

        assertEquals("123", viewModel.state.value.oneTimePassword)
    }

    @Test
    fun `verifying without the full code asks for it without calling the server`() {
        val api = FakeAuthApi(LoginResult.OneTimePasswordRequired)
        val viewModel = filledFormFor(api)

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("123")
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

        val state = viewModel.state.value
        assertEquals(LoginStep.OneTimePassword, state.step)
        assertEquals(UiMessage.FromServer("Invalid OTP is given. Please check your Authenticator App"), state.oneTimePasswordError)
        assertEquals("", state.oneTimePassword)
        assertNull(state.alert)
        assertEquals(false, state.isSubmitting)
    }

    @Test
    fun `too many wrong codes is shown under the code field`() {
        val message = "Too many wrong codes. Please try again in 5 minutes."
        val api = FakeAuthApi(
            LoginResult.OneTimePasswordRequired,
            LoginResult.Rejected(Rejection(mapOf("one_time_password" to message), message)),
        )
        val viewModel = filledFormFor(api)

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("111111")

        assertEquals(UiMessage.FromServer(message), viewModel.state.value.oneTimePasswordError)
        assertNull(viewModel.state.value.alert)
    }

    @Test
    fun `a throttled code check shows the server message in the alert`() {
        val api = FakeAuthApi(LoginResult.OneTimePasswordRequired, LoginResult.Rejected(Rejection(message = "Too Many Attempts.")))
        val viewModel = filledFormFor(api)

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("222222")

        assertEquals(LoginStep.OneTimePassword, viewModel.state.value.step)
        assertEquals(UiMessage.FromServer("Too Many Attempts."), viewModel.state.value.alert?.body)
    }

    @Test
    fun `going back from the code step clears the password and the code`() {
        val viewModel = filledFormFor(FakeAuthApi(LoginResult.OneTimePasswordRequired))

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("123")
        viewModel.backToCredentials()

        val state = viewModel.state.value
        assertEquals(LoginStep.Credentials, state.step)
        assertEquals(" picker ", state.username)
        assertEquals("", state.password)
        assertEquals("", state.oneTimePassword)
    }

    @Test
    fun `an account that must set up two factor gets the setup step and finishes it with the setup token`() {
        val api = FakeAuthApi(LoginResult.TwoFactorSetupRequired(setup), LoginResult.SignedIn(session))
        val viewModel = filledFormFor(api)

        viewModel.signIn()

        assertEquals(LoginStep.TwoFactorSetup, viewModel.state.value.step)
        assertEquals(setup, viewModel.state.value.twoFactorSetup)
        assertNull(viewModel.state.value.oneTimePasswordError)
        assertNull(viewModel.state.value.alert)

        viewModel.onOneTimePasswordChange("654321")

        assertEquals(
            LoginCredentials("picker", "secret", DEVICE_NAME, oneTimePassword = "654321", twoFactorSetupToken = "token-1"),
            api.loginRequests.last(),
        )
        assertEquals(session, sessions.session.value)
    }

    @Test
    fun `a wrong setup code keeps the key and the token for the next try`() {
        val api = FakeAuthApi(
            LoginResult.TwoFactorSetupRequired(setup),
            LoginResult.Rejected(Rejection(mapOf("one_time_password" to "Invalid OTP is given. Please check your Authenticator App"))),
            LoginResult.SignedIn(session),
        )
        val viewModel = filledFormFor(api)

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("000000")

        val state = viewModel.state.value
        assertEquals(LoginStep.TwoFactorSetup, state.step)
        assertEquals(setup, state.twoFactorSetup)
        assertEquals(UiMessage.FromServer("Invalid OTP is given. Please check your Authenticator App"), state.oneTimePasswordError)
        assertEquals("", state.oneTimePassword)

        viewModel.onOneTimePasswordChange("123456")

        assertEquals("token-1", api.loginRequests.last().twoFactorSetupToken)
        assertEquals(session, sessions.session.value)
    }

    @Test
    fun `a fresh setup replaces the shown key and says why`() {
        val renewed = setup.copy(setupToken = "token-2", secret = "KRSXG5CTMVRXEZLU", otpauthUrl = "otpauth://totp/Aiku:picker?secret=KRSXG5CTMVRXEZLU&issuer=Aiku")
        val api = FakeAuthApi(LoginResult.TwoFactorSetupRequired(setup), LoginResult.TwoFactorSetupRequired(renewed), LoginResult.SignedIn(session))
        val viewModel = filledFormFor(api)

        viewModel.signIn()
        viewModel.onOneTimePasswordChange("111111")

        assertEquals(renewed, viewModel.state.value.twoFactorSetup)
        assertEquals(UiMessage.FromResource(R.string.two_factor_renewed), viewModel.state.value.oneTimePasswordError)
        assertEquals("", viewModel.state.value.oneTimePassword)

        viewModel.onOneTimePasswordChange("222222")

        assertEquals("token-2", api.loginRequests.last().twoFactorSetupToken)
    }

    @Test
    fun `going back from the setup step forgets the key and the password`() {
        val viewModel = filledFormFor(FakeAuthApi(LoginResult.TwoFactorSetupRequired(setup)))

        viewModel.signIn()
        viewModel.backToCredentials()

        val state = viewModel.state.value
        assertEquals(LoginStep.Credentials, state.step)
        assertNull(state.twoFactorSetup)
        assertEquals("", state.password)
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
    fun `asking for a reset code needs a login, then sends it trimmed and shows the code step with the generic answer`() {
        val api = FakeAuthApi()
        val viewModel = LoginViewModel(api, sessions, DEVICE_NAME)

        viewModel.openPasswordReset()
        viewModel.sendPasswordResetCode()
        assertEquals(UiMessage.FromResource(R.string.reset_login_required), viewModel.state.value.resetLoginError)
        assertTrue(api.resetCodeLogins.isEmpty())

        viewModel.onResetLoginChange("  ana@example.com ")
        viewModel.sendPasswordResetCode()

        val state = viewModel.state.value
        assertEquals(listOf("ana@example.com"), api.resetCodeLogins)
        assertEquals(LoginStep.PasswordResetCode, state.step)
        assertEquals(UiMessage.FromResource(R.string.reset_code_sent), state.notice)
        assertEquals(RESEND_COOLDOWN_SECONDS, state.resendCooldownSeconds)
    }

    @Test
    fun `the reset form starts with the username already typed on the sign in form`() {
        val viewModel = LoginViewModel(FakeAuthApi(), sessions, DEVICE_NAME)

        viewModel.onUsernameChange(" picker ")
        viewModel.openPasswordReset()

        assertEquals(LoginStep.PasswordReset, viewModel.state.value.step)
        assertEquals("picker", viewModel.state.value.resetLogin)
    }

    @Test
    fun `a login the server refuses is shown under the field and a throttle shows the server message`() {
        val api = FakeAuthApi(
            resetCodeResults = listOf(
                PasswordResult.Rejected(Rejection(mapOf("login" to "The login field is required."))),
                PasswordResult.Rejected(Rejection(message = "Too Many Attempts.")),
            ),
        )
        val viewModel = LoginViewModel(api, sessions, DEVICE_NAME)

        viewModel.openPasswordReset()
        viewModel.onResetLoginChange("ana")
        viewModel.sendPasswordResetCode()

        assertEquals(LoginStep.PasswordReset, viewModel.state.value.step)
        assertEquals(UiMessage.FromServer("The login field is required."), viewModel.state.value.resetLoginError)

        viewModel.sendPasswordResetCode()

        assertEquals(LoginStep.PasswordReset, viewModel.state.value.step)
        assertEquals(UiMessage.FromResource(R.string.reset_send_failed_title), viewModel.state.value.alert?.title)
        assertEquals(UiMessage.FromServer("Too Many Attempts."), viewModel.state.value.alert?.body)
    }

    @Test
    fun `resending waits for the cooldown, then sends the code again and restarts it`() {
        val api = FakeAuthApi()
        val viewModel = codeStepFor(api)

        viewModel.resendPasswordResetCode()
        assertEquals(1, api.resetCodeLogins.size)

        dispatcher.scheduler.advanceTimeBy(RESEND_COOLDOWN_SECONDS * 1_000L)
        dispatcher.scheduler.runCurrent()
        assertEquals(0, viewModel.state.value.resendCooldownSeconds)

        viewModel.onResetCodeChange("12")
        viewModel.resendPasswordResetCode()

        val state = viewModel.state.value
        assertEquals(listOf("picker", "picker"), api.resetCodeLogins)
        assertEquals(UiMessage.FromResource(R.string.reset_code_resent), state.notice)
        assertEquals("", state.resetCode)
        assertEquals(RESEND_COOLDOWN_SECONDS, state.resendCooldownSeconds)
    }

    @Test
    fun `resetting checks the code and both passwords before calling the server`() {
        val api = FakeAuthApi()
        val viewModel = codeStepFor(api)

        viewModel.onResetCodeChange("12 3")
        viewModel.resetPassword()

        assertEquals(UiMessage.FromResource(R.string.reset_code_required), viewModel.state.value.resetCodeError)
        assertEquals(UiMessage.FromResource(R.string.password_new_required), viewModel.state.value.newPasswordError)
        assertEquals(UiMessage.FromResource(R.string.password_confirm_required), viewModel.state.value.newPasswordConfirmationError)

        viewModel.onResetCodeChange("482913")
        viewModel.onNewPasswordChange("new-secret")
        viewModel.onNewPasswordConfirmationChange("new-secrets")
        viewModel.resetPassword()

        assertNull(viewModel.state.value.resetCodeError)
        assertEquals(UiMessage.FromResource(R.string.password_mismatch), viewModel.state.value.newPasswordConfirmationError)
        assertTrue(api.resets.isEmpty())
    }

    @Test
    fun `a wrong code is shown under the code field and after five the app offers a new code`() {
        val wrongCode = PasswordResult.Rejected(Rejection(mapOf("code" to "The code is wrong or has expired.")))
        val usedUp = PasswordResult.Rejected(Rejection(mapOf("code" to "Too many wrong codes. Ask for a new code.")))
        val api = FakeAuthApi(resetResults = List(MAX_RESET_CODE_TRIES - 1) { wrongCode } + usedUp)
        val viewModel = filledResetFor(api)

        repeat(MAX_RESET_CODE_TRIES - 1) { viewModel.resetPassword() }

        assertEquals(UiMessage.FromServer("The code is wrong or has expired."), viewModel.state.value.resetCodeError)
        assertFalse(viewModel.state.value.isResetCodeUsedUp)
        assertEquals("482913", viewModel.state.value.resetCode)
        assertEquals("new-secret", viewModel.state.value.newPassword)

        viewModel.resetPassword()

        assertEquals(UiMessage.FromServer("Too many wrong codes. Ask for a new code."), viewModel.state.value.resetCodeError)
        assertTrue(viewModel.state.value.isResetCodeUsedUp)

        dispatcher.scheduler.advanceTimeBy(RESEND_COOLDOWN_SECONDS * 1_000L)
        dispatcher.scheduler.runCurrent()
        viewModel.resendPasswordResetCode()

        assertFalse(viewModel.state.value.isResetCodeUsedUp)
        assertNull(viewModel.state.value.resetCodeError)
    }

    @Test
    fun `password rules from the server are shown under the password fields`() {
        val api = FakeAuthApi(
            resetResults = listOf(
                PasswordResult.Rejected(
                    Rejection(
                        mapOf(
                            "password" to "The password field must be at least 8 characters.",
                            "password_confirmation" to "The password confirmation field is required.",
                        ),
                    ),
                ),
            ),
        )
        val viewModel = filledResetFor(api)

        viewModel.resetPassword()

        val state = viewModel.state.value
        assertEquals(UiMessage.FromServer("The password field must be at least 8 characters."), state.newPasswordError)
        assertEquals(UiMessage.FromServer("The password confirmation field is required."), state.newPasswordConfirmationError)
        assertNull(state.resetCodeError)
        assertFalse(state.isResetCodeUsedUp)
    }

    @Test
    fun `a reset sends the code and passwords, then returns to sign in with the username and a confirmation`() {
        val api = FakeAuthApi()
        val viewModel = filledResetFor(api)

        viewModel.resetPassword()

        assertEquals(PasswordReset(login = "picker", code = "482913", password = "new-secret", passwordConfirmation = "new-secret"), api.resets.single())
        val state = viewModel.state.value
        assertEquals(LoginStep.Credentials, state.step)
        assertEquals("picker", state.username)
        assertEquals("", state.password)
        assertEquals(UiMessage.FromResource(R.string.reset_done), state.notice)
        assertEquals(1, state.passwordFocusRequest)
        assertEquals("", state.newPassword)
    }

    @Test
    fun `a reset by email address does not put the email in the username field`() {
        val api = FakeAuthApi()
        val viewModel = LoginViewModel(api, sessions, DEVICE_NAME)

        viewModel.openPasswordReset()
        viewModel.onResetLoginChange("ana@example.com")
        viewModel.sendPasswordResetCode()
        viewModel.onResetCodeChange("482913")
        viewModel.onNewPasswordChange("new-secret")
        viewModel.onNewPasswordConfirmationChange("new-secret")
        viewModel.resetPassword()

        assertEquals(LoginStep.Credentials, viewModel.state.value.step)
        assertEquals("", viewModel.state.value.username)
        assertEquals(UiMessage.FromResource(R.string.reset_done), viewModel.state.value.notice)
    }

    @Test
    fun `a reset without a connection keeps the form and shows the offline alert`() {
        val viewModel = filledResetFor(FakeAuthApi(resetResults = listOf(PasswordResult.Unreachable)))

        viewModel.resetPassword()

        val state = viewModel.state.value
        assertEquals(LoginStep.PasswordResetCode, state.step)
        assertEquals(UiMessage.FromResource(R.string.alert_offline_reset_body), state.alert?.body)
        assertEquals("482913", state.resetCode)
        assertFalse(state.isSubmitting)
    }

    @Test
    fun `signing in after a reset clears the confirmation`() {
        val api = FakeAuthApi(LoginResult.SignedIn(session))
        val viewModel = filledResetFor(api)

        viewModel.resetPassword()
        viewModel.onPasswordChange("new-secret")
        viewModel.signIn()

        assertEquals(LoginCredentials("picker", "new-secret", DEVICE_NAME), api.loginRequests.single())
        assertNull(viewModel.state.value.notice)
    }

    private fun filledFormFor(api: FakeAuthApi, rememberMe: Boolean = true) =
        LoginViewModel(api, sessions, DEVICE_NAME).apply {
            onUsernameChange(" picker ")
            onPasswordChange("secret")
            onRememberMeChange(rememberMe)
        }

    private fun codeStepFor(api: FakeAuthApi) =
        LoginViewModel(api, sessions, DEVICE_NAME).apply {
            openPasswordReset()
            onResetLoginChange("picker")
            sendPasswordResetCode()
        }

    private fun filledResetFor(api: FakeAuthApi) =
        codeStepFor(api).apply {
            onResetCodeChange("482913")
            onNewPasswordChange("new-secret")
            onNewPasswordConfirmationChange("new-secret")
        }

    private class FakeAuthApi(
        vararg loginResults: LoginResult,
        resetCodeResults: List<PasswordResult> = emptyList(),
        resetResults: List<PasswordResult> = emptyList(),
    ) : AuthApi {
        private val queuedLoginResults = ArrayDeque(loginResults.toList())
        private val queuedResetCodeResults = ArrayDeque(resetCodeResults)
        private val queuedResetResults = ArrayDeque(resetResults)
        val loginRequests = mutableListOf<LoginCredentials>()
        val resetCodeLogins = mutableListOf<String>()
        val resets = mutableListOf<PasswordReset>()

        override suspend fun login(credentials: LoginCredentials): LoginResult {
            loginRequests += credentials
            return queuedLoginResults.removeFirst()
        }

        override suspend fun logout(token: String) = Unit

        override suspend fun requestPasswordResetCode(login: String): PasswordResult {
            resetCodeLogins += login
            return queuedResetCodeResults.removeFirstOrNull() ?: PasswordResult.Done
        }

        override suspend fun resetPassword(reset: PasswordReset): PasswordResult {
            resets += reset
            return queuedResetResults.removeFirstOrNull() ?: PasswordResult.Done
        }

        override suspend fun changePassword(token: String, change: PasswordChange): PasswordResult = PasswordResult.Done
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

        val setup = TwoFactorSetup(
            setupToken = "token-1",
            secret = "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP",
            otpauthUrl = "otpauth://totp/Aiku:picker?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=Aiku",
            issuer = "Aiku",
            account = "picker",
            expiresInSeconds = 600,
        )
    }
}
