package io.aiku.droid.data.settings

import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LanguageUpdateResult
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.PasswordChange
import io.aiku.droid.data.api.PasswordReset
import io.aiku.droid.data.api.PasswordResult
import io.aiku.droid.data.api.ProfileLanguage
import io.aiku.droid.data.api.ProfileResult
import io.aiku.droid.data.api.StaffProfile
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.runTest
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class AccountLanguageSyncTest {

    private val scope = CoroutineScope(UnconfinedTestDispatcher())
    private val localePreferences = MemoryPreferences()
    private val pendingPreferences = MemoryPreferences()
    private val settings = AppLanguageSettings(PreferencesLocaleStore(localePreferences) { listOf("en-GB") })
    private val pending = PreferencesPendingLanguage(pendingPreferences)
    private val api = FakeAccountApi()

    @After
    fun stopScope() {
        scope.cancel()
    }

    private fun sync(sessions: FakeSessions = FakeSessions()) =
        AccountLanguageSync(settings = settings, api = api, sessions = sessions, pending = pending, scope = scope).also { it.start() }

    @Test
    fun `signing in applies the account language`() {
        val sessions = FakeSessions(session = null)
        sync(sessions)
        api.accountLanguage = "de"

        sessions.start(StaffSession(token = "8|new", userId = 42, username = "stock", displayName = "Ana"), rememberOnDevice = true)

        assertEquals(AppLanguage.German, settings.current.value)
        assertEquals("de", localePreferences.getString("chosen_language", null))
        assertEquals(listOf("8|new"), api.profileTokens)
    }

    @Test
    fun `a restored session follows the account language when the app comes to the foreground`() {
        api.accountLanguage = "sk"
        val sync = sync()

        assertEquals(0, api.profileTokens.size)

        sync.onForeground()

        assertEquals(AppLanguage.Slovak, settings.current.value)
        assertEquals(listOf("7|abc"), api.profileTokens)
    }

    @Test
    fun `loading the profile applies the account language`() = runTest {
        api.accountLanguage = "zh-Hans"

        val result = sync().profile("7|abc")

        assertTrue(result is ProfileResult.Loaded)
        assertEquals(AppLanguage.ChineseSimplified, settings.current.value)
    }

    @Test
    fun `an account language the app does not offer, or none, leaves the app language alone`() = runTest {
        val sync = sync()

        api.accountLanguage = "th"
        sync.profile("7|abc")
        assertEquals(AppLanguage.English, settings.current.value)

        api.accountLanguage = null
        sync.profile("7|abc")
        assertEquals(AppLanguage.English, settings.current.value)
        assertNull(localePreferences.getString("chosen_language", null))
    }

    @Test
    fun `choosing a language applies it at once and saves it to the account`() {
        val sync = sync()

        sync.choose(AppLanguage.French)

        assertEquals(AppLanguage.French, settings.current.value)
        assertEquals(listOf("fr"), api.updates)
        assertEquals("fr", api.accountLanguage)
        assertNull(pending.pendingTag)
    }

    @Test
    fun `without a connection the choice stays on the phone and is sent again on the next foreground`() {
        api.updateResult = LanguageUpdateResult.Unreachable
        val sync = sync()

        sync.choose(AppLanguage.French)

        assertEquals(AppLanguage.French, settings.current.value)
        assertEquals("fr", pending.pendingTag)
        assertEquals("fr", pendingPreferences.getString("pending_account_language", null))

        api.updateResult = LanguageUpdateResult.Saved
        sync.onForeground()

        assertEquals(listOf("fr", "fr"), api.updates)
        assertNull(pending.pendingTag)
        assertEquals(0, api.profileTokens.size)
    }

    @Test
    fun `a server error keeps the choice pending and a profile load sends it again`() = runTest {
        api.updateResult = LanguageUpdateResult.ServerError(500)
        val sync = sync()
        sync.choose(AppLanguage.Polish)

        assertEquals("pl", pending.pendingTag)

        api.updateResult = LanguageUpdateResult.Saved
        sync.profile("7|abc")

        assertEquals(listOf("pl", "pl"), api.updates)
        assertNull(pending.pendingTag)
        assertEquals(AppLanguage.Polish, settings.current.value)
    }

    @Test
    fun `while a change is pending the account language does not overwrite it`() = runTest {
        api.accountLanguage = "de"
        api.updateResult = LanguageUpdateResult.Unreachable
        val sync = sync()
        sync.choose(AppLanguage.French)

        sync.profile("7|abc")
        sync.onForeground()

        assertEquals(AppLanguage.French, settings.current.value)
        assertEquals("fr", pending.pendingTag)
        assertEquals(listOf("fr", "fr", "fr"), api.updates)
    }

    @Test
    fun `a pending change survives a restart of the app`() {
        api.accountLanguage = "de"
        api.updateResult = LanguageUpdateResult.Unreachable
        sync().choose(AppLanguage.French)

        api.updateResult = LanguageUpdateResult.Saved
        val restarted = AccountLanguageSync(
            settings = AppLanguageSettings(PreferencesLocaleStore(localePreferences) { listOf("en-GB") }),
            api = api,
            sessions = FakeSessions(),
            pending = PreferencesPendingLanguage(pendingPreferences),
            scope = scope,
        ).also { it.start() }
        restarted.onForeground()

        assertEquals(listOf("fr", "fr"), api.updates)
        assertEquals("fr", api.accountLanguage)
        assertNull(pendingPreferences.getString("pending_account_language", null))
    }

    @Test
    fun `a language the web refuses is dropped and the account language comes back`() {
        api.accountLanguage = "de"
        api.updateResult = LanguageUpdateResult.Rejected
        val sync = sync()

        sync.choose(AppLanguage.French)

        assertEquals(listOf("fr"), api.updates)
        assertNull(pending.pendingTag)
        assertEquals(AppLanguage.German, settings.current.value)
        assertEquals(listOf("7|abc"), api.profileTokens)
    }

    @Test
    fun `signing out clears the pending change`() {
        api.updateResult = LanguageUpdateResult.Unreachable
        val sessions = FakeSessions()
        val sync = sync(sessions)
        sync.choose(AppLanguage.French)

        sessions.end()

        assertNull(pending.pendingTag)
        assertNull(pendingPreferences.getString("pending_account_language", null))

        api.accountLanguage = "de"
        api.updateResult = LanguageUpdateResult.Saved
        sessions.start(StaffSession(token = "8|new", userId = 43, username = "other", displayName = "Ben"), rememberOnDevice = true)

        assertEquals(listOf("fr"), api.updates)
        assertEquals(AppLanguage.German, settings.current.value)
    }

    @Test
    fun `a profile that was loading when a language was chosen does not undo the choice`() {
        api.accountLanguage = "de"
        val gate = CompletableDeferred<Unit>()
        api.profileGate = gate
        val sync = sync()
        scope.launch { sync.profile("7|abc") }

        sync.choose(AppLanguage.French)
        gate.complete(Unit)

        assertEquals(AppLanguage.French, settings.current.value)
        assertEquals("fr", api.accountLanguage)
    }

    private class FakeAccountApi : AuthApi {
        var accountLanguage: String? = "en"
        var updateResult: LanguageUpdateResult = LanguageUpdateResult.Saved
        var profileGate: CompletableDeferred<Unit>? = null
        val profileTokens = mutableListOf<String>()
        val updates = mutableListOf<String>()

        override suspend fun login(credentials: LoginCredentials): LoginResult = LoginResult.Unreachable

        override suspend fun logout(token: String) = Unit

        override suspend fun requestPasswordResetCode(login: String): PasswordResult = PasswordResult.Done

        override suspend fun resetPassword(reset: PasswordReset): PasswordResult = PasswordResult.Done

        override suspend fun changePassword(token: String, change: PasswordChange): PasswordResult = PasswordResult.Done

        override suspend fun profile(token: String): ProfileResult {
            profileTokens += token
            val language = accountLanguage
            profileGate?.await()
            return ProfileResult.Loaded(StaffProfile(id = 42, username = "stock", language = language?.let { ProfileLanguage(code = it, name = it) }))
        }

        override suspend fun updateLanguage(token: String, code: String): LanguageUpdateResult {
            updates += code
            val result = updateResult
            if (result == LanguageUpdateResult.Saved) {
                accountLanguage = code
            }
            return result
        }
    }
}
