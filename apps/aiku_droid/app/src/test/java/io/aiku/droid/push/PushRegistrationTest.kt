package io.aiku.droid.push

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PushTokenApi
import io.aiku.droid.data.api.PushTokenRegistration
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.cancel
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.runTest
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class FakePushTokenApi : PushTokenApi {
    val calls = mutableListOf<String>()
    val registrations = mutableListOf<Pair<String, PushTokenRegistration>>()
    val unregistrations = mutableListOf<String>()

    override suspend fun register(token: String, registration: PushTokenRegistration): GoodsOutResult<Boolean> {
        calls += "register"
        registrations += token to registration
        return GoodsOutResult.Loaded(true)
    }

    override suspend fun unregister(token: String): GoodsOutResult<Boolean> {
        calls += "unregister"
        unregistrations += token
        return GoodsOutResult.Loaded(false)
    }
}

class FakePushTokenSource(override var isAvailable: Boolean = true, var token: String? = "fcm-1") : PushTokenSource {
    override suspend fun currentToken(): String? = token
}

@OptIn(ExperimentalCoroutinesApi::class)
class PushRegistrationTest {

    private val scope = CoroutineScope(UnconfinedTestDispatcher())
    private val api = FakePushTokenApi()
    private val tokens = FakePushTokenSource()
    private var clearedNotifications = 0
    private val shown = ShownNotifications { clearedNotifications++ }

    @After
    fun stopScope() {
        scope.cancel()
    }

    private fun registration(sessions: FakeSessions = FakeSessions()) =
        PushRegistration(api = api, tokens = tokens, sessions = sessions, shown = shown, appVersion = "0.1.0", scope = scope)

    @Test
    fun `starting while signed in registers this device's token with the app version`() {
        registration().start()

        assertEquals(listOf("7|abc" to PushTokenRegistration(fcmToken = "fcm-1", appVersion = "0.1.0")), api.registrations)
    }

    @Test
    fun `signing in registers the token with the new session and signing out registers nothing`() {
        val sessions = FakeSessions(session = null)
        registration(sessions).start()

        assertTrue(api.registrations.isEmpty())

        sessions.start(StaffSession(token = "8|new", userId = 43, username = "picker", displayName = "Bo"), rememberOnDevice = false)
        sessions.end()
        sessions.start(StaffSession(token = "9|next", userId = 44, username = "packer", displayName = "Cy"), rememberOnDevice = false)

        assertEquals(listOf("8|new", "9|next"), api.registrations.map { it.first })
    }

    @Test
    fun `without firebase nothing is registered or unregistered`() = runTest {
        tokens.isAvailable = false
        val registration = registration()

        registration.start()
        registration.unregister("7|abc")

        assertTrue(api.calls.isEmpty())
        assertEquals(0, clearedNotifications)
    }

    @Test
    fun `no token from firebase registers nothing`() {
        tokens.token = null

        registration().start()

        assertTrue(api.calls.isEmpty())
    }

    @Test
    fun `a refreshed token is registered only while signed in`() {
        val sessions = FakeSessions()
        val registration = registration(sessions)

        registration.onNewToken("fcm-2")
        sessions.end()
        registration.onNewToken("fcm-3")

        assertEquals(listOf("7|abc" to PushTokenRegistration(fcmToken = "fcm-2", appVersion = "0.1.0")), api.registrations)
    }

    @Test
    fun `unregistering clears this device's notifications and deletes the token of the session`() = runTest {
        registration().unregister("7|abc")

        assertEquals(listOf("7|abc"), api.unregistrations)
        assertEquals(1, clearedNotifications)
    }
}
