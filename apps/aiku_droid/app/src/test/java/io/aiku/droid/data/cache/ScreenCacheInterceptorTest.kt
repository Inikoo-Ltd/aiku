package io.aiku.droid.data.cache

import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Protocol
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ScreenCacheInterceptorTest {

    private val store = InMemoryScreenCacheStore()
    private val clock = TestClock()
    private val cache = testScreenCache(sessions = FakeSessions(), store = store, clock = clock)
    private var reply: (Request) -> Pair<Int, String> = { HTTP_OK to "{}" }
    private val client = OkHttpClient.Builder()
        .addInterceptor(ScreenCacheInterceptor(cache = { cache }))
        .addInterceptor { chain ->
            val (code, body) = reply(chain.request())
            Response.Builder()
                .request(chain.request())
                .protocol(Protocol.HTTP_1_1)
                .code(code)
                .message("")
                .body(body.toResponseBody(JSON))
                .build()
        }
        .build()

    private fun call(method: String, path: String, token: String = TOKEN, answer: Pair<Int, String>): String {
        reply = { answer }
        val request = Request.Builder()
            .url("$API$path")
            .method(method, if (method == "GET") null else "{}".toRequestBody(JSON))
            .header("Authorization", "Bearer $token")
            .build()
        return client.newCall(request).execute().use { it.body.string() }
    }

    private fun saved(warehouseId: Long, screen: CachedScreen<*>): Boolean = store.rows.containsKey(ScreenCacheKey(SIGNED_IN_USER, warehouseId, screen.key))

    @Test
    fun `a successful my work answer replaces the saved copy wholesale and still reaches the screen`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811", 9L to "AR24812"))
        val fresh = CachedBodies.myWork(9L to "AR24812")

        val received = call("GET", "/warehouses/4/my-work", answer = HTTP_OK to fresh)

        assertEquals(fresh, received)
        assertEquals(listOf("AR24812"), cache.read(4, CachedScreens.myWork)?.value?.rows?.map { it.reference })
    }

    @Test
    fun `the badges and the warehouse dashboard are saved per warehouse`() = runTest {
        call("GET", "/warehouses/4/badges", answer = HTTP_OK to CachedBodies.badges(myWork = 3, inboxUnread = 2))
        call("GET", "/warehouses/5/dashboard", answer = HTTP_OK to CachedBodies.dashboard(unassigned = 47))

        assertEquals(3, cache.read(4, CachedScreens.workBadges)?.value?.myWork)
        assertEquals(47, cache.read(5, CachedScreens.warehouseDashboard)?.value?.goodsOut?.unassigned)
        assertNull(cache.read(5, CachedScreens.workBadges))
    }

    @Test
    fun `the first inbox page is saved per filter and later pages are not`() = runTest {
        call("GET", "/inbox?filter=all&page=1&per_page=30", answer = HTTP_OK to CachedBodies.inbox("a" to null, "b" to null))
        call("GET", "/inbox?filter=all&page=2&per_page=30", answer = HTTP_OK to CachedBodies.inbox("c" to null))
        call("GET", "/inbox?filter=unread&page=1&per_page=30", answer = HTTP_OK to CachedBodies.inbox("b" to null))

        assertEquals(listOf("a", "b"), cache.read(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"))?.value?.notifications?.map { it.id })
        assertEquals(listOf("b"), cache.read(CachedScreens.USER_SCOPED, CachedScreens.inbox("unread"))?.value?.notifications?.map { it.id })
    }

    @Test
    fun `a notification the server no longer returns disappears from the saved inbox`() = runTest {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null, "outside-allow-list" to null))

        call("GET", "/inbox?filter=all&page=1&per_page=30", answer = HTTP_OK to CachedBodies.inbox("a" to null))

        assertEquals(listOf("a"), cache.read(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"))?.value?.notifications?.map { it.id })
    }

    @Test
    fun `the clocking status and only this week's timesheets are saved`() = runTest {
        call("GET", "/clocking", answer = HTTP_OK to CachedBodies.clocking("clocked_in"))
        call("GET", "/clocking/timesheets?from=2026-11-09&to=2026-11-15", answer = HTTP_OK to CachedBodies.thisWeek(workingSeconds = 100))

        assertTrue(cache.read(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus)?.value?.isClockedIn == true)
        assertFalse(saved(CachedScreens.USER_SCOPED, CachedScreens.clockingThisWeek))

        call("GET", "/clocking/timesheets", answer = HTTP_OK to CachedBodies.thisWeek(workingSeconds = 3600))

        assertEquals(3600L, cache.read(CachedScreens.USER_SCOPED, CachedScreens.clockingThisWeek)?.value?.timesheets?.single()?.workingSeconds)
    }

    @Test
    fun `other screens are not saved`() {
        call("GET", "/warehouses/4/delivery-notes?page=1", answer = HTTP_OK to """{"data":[]}""")
        call("GET", "/warehouses/4/goods-in-hub", answer = HTTP_OK to """{"data":{}}""")

        assertTrue(store.rows.isEmpty())
    }

    @Test
    fun `an answer for another session is not saved`() {
        call("GET", "/warehouses/4/my-work", token = "8|old", answer = HTTP_OK to CachedBodies.myWork(123L to "AR24811"))

        assertTrue(store.rows.isEmpty())
    }

    @Test
    fun `an answer the parser rejects removes the saved copy`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))

        call("GET", "/warehouses/4/my-work", answer = HTTP_OK to "<html>maintenance</html>")

        assertFalse(saved(4, CachedScreens.myWork))
    }

    @Test
    fun `a screen the user may no longer see loses its saved copy, a server error keeps it`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(5, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))

        call("GET", "/warehouses/4/my-work", answer = HTTP_SERVER_ERROR to "")
        call("GET", "/warehouses/5/my-work", answer = HTTP_NOT_FOUND to """{"message":"Not found"}""")

        assertTrue(saved(4, CachedScreens.myWork))
        assertFalse(saved(5, CachedScreens.myWork))
    }

    @Test
    fun `an expired session deletes everything`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))

        call("GET", "/warehouses/4/locations", answer = HTTP_UNAUTHORISED to """{"message":"Unauthenticated."}""")

        assertTrue(store.rows.isEmpty())
    }

    @Test
    fun `marking a notification read updates the saved inbox and drops the saved badges`() = runTest {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null, "b" to null, "c" to "2026-11-18T08:00:00Z"))
        cache.save(4, CachedScreens.workBadges, CachedBodies.badges(myWork = 1, inboxUnread = 2))

        call("POST", "/inbox/a/read", answer = HTTP_OK to """{"data":{"id":"a","kind":"delivery_note","event":"assigned","title":"a assigned","read_at":"2026-11-19T09:59:00Z"}}""")

        val inbox = cache.read(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"))?.value
        assertEquals("2026-11-19T09:59:00Z", inbox?.notifications?.first { it.id == "a" }?.readAt)
        assertNull(inbox?.notifications?.first { it.id == "b" }?.readAt)
        assertEquals(1, inbox?.unread)
        assertFalse(saved(4, CachedScreens.workBadges))
    }

    @Test
    fun `marking a notification outside the saved page read drops the saved inbox`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null))

        call("POST", "/inbox/zz/read", answer = HTTP_OK to """{"data":{"id":"zz","read_at":"2026-11-19T09:59:00Z"}}""")

        assertFalse(saved(CachedScreens.USER_SCOPED, CachedScreens.inbox("all")))
    }

    @Test
    fun `marking all read updates every saved notification and the unread count`() = runTest {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null, "b" to null, "c" to "2026-11-18T08:00:00Z"))

        call("POST", "/inbox/read-all", answer = HTTP_OK to """{"data":{"unread":0}}""")

        val inbox = cache.read(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"))?.value
        assertEquals(listOf(CACHE_NOW.toString(), CACHE_NOW.toString(), "2026-11-18T08:00:00Z"), inbox?.notifications?.map { it.readAt })
        assertEquals(0, inbox?.unread)
    }

    @Test
    fun `a refused inbox write keeps the saved inbox`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null))

        call("POST", "/inbox/read-all", answer = HTTP_SERVER_ERROR to "")

        assertTrue(saved(CachedScreens.USER_SCOPED, CachedScreens.inbox("all")))
    }

    @Test
    fun `a confirmed warehouse action drops the saved work screens of every warehouse`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(4, CachedScreens.workBadges, CachedBodies.badges(myWork = 1, inboxUnread = 0))
        cache.save(5, CachedScreens.warehouseDashboard, CachedBodies.dashboard(unassigned = 3))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))

        call("PATCH", "/delivery-notes/123", answer = HTTP_OK to """{"data":{}}""")

        assertEquals(setOf(CachedScreens.inbox("all").key, CachedScreens.clockingStatus.key), store.rows.keys.map { it.screen }.toSet())
    }

    @Test
    fun `a refused warehouse action keeps the saved work screens`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))

        call("POST", "/delivery-notes/123/scan-pick", answer = HTTP_UNPROCESSABLE to """{"message":"Wrong item"}""")

        assertTrue(saved(4, CachedScreens.myWork))
    }

    @Test
    fun `a confirmed clocking drops the saved clocking only`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_out"))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingThisWeek, CachedBodies.thisWeek(workingSeconds = 60))

        call("POST", "/clocking/qr", answer = HTTP_OK to """{"success":true}""")

        assertEquals(setOf(CachedScreens.myWork.key), store.rows.keys.map { it.screen }.toSet())
    }

    @Test
    fun `account requests leave the saved copies alone`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))

        call("PATCH", "/profile/language", answer = HTTP_OK to "{}")
        call("POST", "/push-token", answer = HTTP_OK to "{}")

        assertTrue(saved(4, CachedScreens.myWork))
    }

    @Test
    fun `the warehouse list drops the rows of warehouses no longer listed`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(5, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null))

        call("GET", "/warehouses", answer = HTTP_OK to CachedBodies.warehouses(4))

        assertEquals(setOf(4L, CachedScreens.USER_SCOPED), store.rows.keys.map { it.warehouseId }.toSet())
    }

    @Test
    fun `an unreadable warehouse list keeps every row`() {
        cache.save(5, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))

        call("GET", "/warehouses", answer = HTTP_OK to "oops")

        assertTrue(saved(5, CachedScreens.myWork))
    }

    @Test
    fun `requests outside the mobile api are ignored`() {
        reply = { HTTP_OK to CachedBodies.myWork(1L to "AR1") }
        val request = Request.Builder().url("https://app.aiku.test/images/warehouses/4/my-work").header("Authorization", "Bearer $TOKEN").build()

        client.newCall(request).execute().close()

        assertTrue(store.rows.isEmpty())
    }

    private companion object {
        const val API = "https://app.aiku.test/app/api/mobile"
        const val TOKEN = "7|abc"
        const val HTTP_OK = 200
        const val HTTP_UNAUTHORISED = 401
        const val HTTP_NOT_FOUND = 404
        const val HTTP_UNPROCESSABLE = 422
        const val HTTP_SERVER_ERROR = 500
        val JSON = "application/json".toMediaType()
    }
}
