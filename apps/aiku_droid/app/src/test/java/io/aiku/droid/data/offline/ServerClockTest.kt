package io.aiku.droid.data.offline

import io.aiku.droid.data.settings.MemoryPreferences
import okhttp3.OkHttpClient
import okhttp3.Protocol
import okhttp3.Request
import okhttp3.Response
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Instant

class ServerClockTest {

    private val preferences = MemoryPreferences()
    private var elapsed = 50_000L
    private var boot = 3
    private val phoneTime = Instant.parse("2026-11-20T09:58:30Z")

    private fun clock() = ServerClock(preferences, elapsedRealtime = { elapsed }, bootCount = { boot }, deviceClock = { phoneTime })

    @Test
    fun `performed at is the server time of the last answer plus the uptime since`() {
        val clock = clock()
        clock.record(OFFLINE_NOW)
        elapsed += 90_000

        val now = clock.now()

        assertEquals(OFFLINE_NOW.plusSeconds(90), now.instant)
        assertTrue(now.isServerTime)
    }

    @Test
    fun `without any answer the phone clock is used`() {
        val now = clock().now()

        assertEquals(phoneTime, now.instant)
        assertFalse(now.isServerTime)
    }

    @Test
    fun `the anchor survives the app being restarted on the same boot`() {
        clock().record(OFFLINE_NOW)
        elapsed += 30_000

        val now = clock().now()

        assertEquals(OFFLINE_NOW.plusSeconds(30), now.instant)
        assertTrue(now.isServerTime)
    }

    @Test
    fun `after the phone restarts the phone clock is used until aiku answers again`() {
        clock().record(OFFLINE_NOW)
        boot = 4
        elapsed = 2_000

        val restarted = clock()

        assertEquals(phoneTime, restarted.now().instant)
        assertFalse(restarted.now().isServerTime)

        restarted.record(OFFLINE_NOW.plusSeconds(600))
        elapsed += 1_000

        assertEquals(OFFLINE_NOW.plusSeconds(601), restarted.now().instant)
        assertTrue(restarted.now().isServerTime)
    }

    @Test
    fun `an uptime lower than the anchor means a restart even without a boot count`() {
        val noBoot = ServerClock(preferences, elapsedRealtime = { elapsed }, bootCount = { null }, deviceClock = { phoneTime })
        noBoot.record(OFFLINE_NOW)
        elapsed = 1_000

        assertFalse(ServerClock(preferences, elapsedRealtime = { elapsed }, bootCount = { null }, deviceClock = { phoneTime }).now().isServerTime)
    }

    @Test
    fun `every answer's date header moves the anchor and every request carries the device headers`() {
        val clock = clock()
        val device = OfflineDevice(preferences)
        var sent: Request? = null
        val client = OkHttpClient.Builder()
            .addInterceptor(OfflineHeadersInterceptor(deviceId = { device.deviceId }, appVersion = OfflineHeadersInterceptor.appVersionOf("1.4.2", 52), clock = clock))
            .addInterceptor { chain ->
                sent = chain.request()
                Response.Builder()
                    .request(chain.request())
                    .protocol(Protocol.HTTP_1_1)
                    .code(200)
                    .message("")
                    .header("Date", "Fri, 20 Nov 2026 10:00:00 GMT")
                    .body("{}".toResponseBody())
                    .build()
            }
            .build()

        client.newCall(Request.Builder().url("https://app.aiku.test/app/api/mobile/profile").build()).execute().close()
        elapsed += 4_000

        val headers = sent!!
        assertEquals(device.deviceId, headers.header("X-Aiku-Device"))
        assertEquals(4, java.util.UUID.fromString(headers.header("X-Aiku-Device")).version())
        assertEquals("android", headers.header("X-Aiku-Platform"))
        assertEquals("1.4.2 (52)", headers.header("X-Aiku-App-Version"))
        assertEquals(OFFLINE_NOW.plusSeconds(4), clock.now().instant)
    }

    @Test
    fun `the device id is created once and kept`() {
        val first = OfflineDevice(preferences).deviceId

        assertEquals(first, OfflineDevice(preferences).deviceId)
    }

    @Test
    fun `sequence numbers never go back for the same user and are kept per user`() {
        val device = OfflineDevice(preferences)

        assertEquals(listOf(1L, 2L), device.reserveSequences(ANA.userId, 2))
        assertEquals(listOf(1L), device.reserveSequences(BEN.userId, 1))
        assertEquals(listOf(3L), OfflineDevice(preferences).reserveSequences(ANA.userId, 1))
    }
}
