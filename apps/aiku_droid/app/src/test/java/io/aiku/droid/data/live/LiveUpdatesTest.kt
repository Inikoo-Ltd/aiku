package io.aiku.droid.data.live

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.RealtimeApi
import io.aiku.droid.data.api.RealtimeResponses
import io.aiku.droid.data.api.RealtimeServer
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.runTest
import okhttp3.OkHttpClient
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class LiveUpdatesTest {

    private class FakeRealtimeApi : RealtimeApi {
        val authorized = mutableListOf<Pair<String, String>>()

        override suspend fun realtimeServer(token: String): GoodsOutResult<RealtimeServer?> = GoodsOutResult.Loaded(null)

        override suspend fun authorizeChannel(token: String, socketId: String, channel: String): String? {
            authorized += socketId to channel
            return null
        }
    }

    @Test
    fun `the websocket server comes from the mobile API and is opened with protocol 7`() {
        val server = (
            RealtimeResponses.parseServer(200, """{"data":{"key":"app-key","cluster":"mt1","host":"ws.aiku.io","port":443,"scheme":"https"}}""")
                as GoodsOutResult.Loaded
            ).value

        assertEquals("wss://ws.aiku.io:443/app/app-key?protocol=7&client=aiku-droid&version=1.4&flash=false", server?.socketUrl("aiku-droid", "1.4"))
        assertEquals("ws://192.168.0.140:6001/app/k?protocol=7&client=a&version=1&flash=false", RealtimeServer(key = "k", host = "192.168.0.140", port = 6001, scheme = "http").socketUrl("a", "1"))
        assertNull((RealtimeResponses.parseServer(200, """{"data":null}""") as GoodsOutResult.Loaded).value)
        assertEquals("app-key:signature", RealtimeResponses.parseAuth(200, """{"auth":"app-key:signature"}"""))
        assertNull(RealtimeResponses.parseAuth(403, """{"message":"Forbidden"}"""))
        assertEquals("""{"socket_id":"1.2","channel_name":"private-grp.personal.5"}""", RealtimeResponses.authBody("1.2", "private-grp.personal.5"))
    }

    @Test
    fun `a channel event reaches only the screens listening to that channel, protocol frames never do`() = runTest {
        val api = FakeRealtimeApi()
        val live = LiveUpdates(OkHttpClient(), api, FakeSessions(), backgroundScope, "aiku-droid", "1.4")
        val received = mutableListOf<LiveEvent>()
        backgroundScope.launch(UnconfinedTestDispatcher(testScheduler)) { live.events(LiveChannelNames.personal(5)).collect { received += it } }

        assertFalse(live.isConnected.value)
        live.receive("""{"event":"pusher:connection_established","data":"{\"socket_id\":\"123.456\",\"activity_timeout\":30}"}""")
        live.receive("""{"event":"pusher_internal:subscription_succeeded","channel":"private-grp.personal.5","data":"{}"}""")
        live.receive("""{"event":"permissions-changed","channel":"private-grp.personal.5","data":"{\"user_id\":5}"}""")
        live.receive("""{"event":"permissions-changed","channel":"private-grp.personal.6","data":"{}"}""")
        live.receive("not json")
        testScheduler.runCurrent()

        assertTrue(live.isConnected.value)
        assertEquals(listOf("123.456" to "private-grp.personal.5"), api.authorized)
        assertEquals(listOf(LiveEvent(channel = "private-grp.personal.5", event = "permissions-changed", data = """{"user_id":5}""")), received)
    }

    @Test
    fun `channel names follow the server's channels`() {
        assertEquals("private-grp.mobile.stock_delivery.12", LiveChannelNames.record("stock_delivery", 12))
        assertEquals("private-grp.org.3.dispatching-backlog", LiveChannelNames.dispatchingBacklog(3))
    }
}
