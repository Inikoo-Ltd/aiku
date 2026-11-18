package io.aiku.droid.push

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.ui.inbox.FakeInboxApi
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PushInboxTest {

    private val scope = CoroutineScope(UnconfinedTestDispatcher())
    private val api = FakeInboxApi()
    private val pushOpen = PushOpen(notificationId = "9b1c", record = null)

    @After
    fun stopScope() {
        scope.cancel()
    }

    @Test
    fun `a tapped push waits until the signed-in app takes it, once`() {
        val pushInbox = PushInbox(api, FakeSessions(), scope)

        pushInbox.receive(pushOpen)

        assertEquals(pushOpen, pushInbox.pending.value)
        assertEquals(pushOpen, pushInbox.take())
        assertNull(pushInbox.pending.value)
        assertNull(pushInbox.take())
    }

    @Test
    fun `a push tapped while signed out is dropped`() {
        val pushInbox = PushInbox(api, FakeSessions(session = null), scope)

        pushInbox.receive(pushOpen)

        assertNull(pushInbox.take())
    }

    @Test
    fun `marking a tapped push read tells the server and then refreshes`() {
        val pushInbox = PushInbox(api, FakeSessions(), scope)
        var refreshes = 0

        pushInbox.markRead("9b1c", onRead = { refreshes++ })

        assertEquals(listOf("9b1c"), api.readRequests)
        assertEquals(1, refreshes)
    }

    @Test
    fun `a read that fails does not refresh`() {
        api.readResult = { GoodsOutResult.Unreachable }
        val pushInbox = PushInbox(api, FakeSessions(), scope)
        var refreshes = 0

        pushInbox.markRead("9b1c", onRead = { refreshes++ })

        assertEquals(0, refreshes)
    }

    @Test
    fun `an arriving message is announced to whoever listens`() {
        val pushInbox = PushInbox(api, FakeSessions(), scope)
        val heard = mutableListOf<Unit>()
        scope.launch { pushInbox.arrivals.collect { heard += it } }

        pushInbox.messageArrived()

        assertTrue(heard.isNotEmpty())
    }
}
