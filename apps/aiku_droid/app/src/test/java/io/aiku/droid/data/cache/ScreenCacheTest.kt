package io.aiku.droid.data.cache

import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Duration

@OptIn(ExperimentalCoroutinesApi::class)
class ScreenCacheTest {

    private val store = InMemoryScreenCacheStore()
    private val cipher = XorCipher()
    private val clock = TestClock()
    private val sessions = FakeSessions()
    private val cache = testScreenCache(sessions = sessions, store = store, cipher = cipher, clock = clock)
    private val myWorkKey = ScreenCacheKey(userId = SIGNED_IN_USER, warehouseId = 4, screen = CachedScreens.myWork.key)

    @Test
    fun `a saved answer is read back through the screen's own parser with its fetch time`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811", 9L to "AR24812"))
        clock.now = CACHE_NOW + Duration.ofMinutes(5)

        val cached = cache.read(4, CachedScreens.myWork)

        assertEquals(listOf("AR24811", "AR24812"), cached?.value?.rows?.map { it.reference })
        assertEquals(CACHE_NOW, cached?.fetchedAt)
    }

    @Test
    fun `the saved answer is encrypted on the phone`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))

        val stored = store.rows.getValue(myWorkKey).payload.toString(Charsets.UTF_8)

        assertFalse(stored.contains("AR24811"))
        assertFalse(stored.contains("delivery_note"))
    }

    @Test
    fun `a new answer replaces the saved one wholesale`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811", 9L to "AR24812"))

        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))

        assertEquals(listOf("AR24812"), cache.read(4, CachedScreens.myWork)?.value?.rows?.map { it.reference })
        assertEquals(1, store.rows.size)
    }

    @Test
    fun `each warehouse and screen has its own row`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(5, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))

        assertEquals(listOf("AR24811"), cache.read(4, CachedScreens.myWork)?.value?.rows?.map { it.reference })
        assertEquals(listOf("AR24812"), cache.read(5, CachedScreens.myWork)?.value?.rows?.map { it.reference })
        assertNull(cache.read(4, CachedScreens.warehouseDashboard))
    }

    @Test
    fun `a copy that cannot be decrypted is deleted and treated as missing`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cipher.failsToDecrypt = true

        assertNull(cache.read(4, CachedScreens.myWork))
        assertTrue(store.rows.isEmpty())
    }

    @Test
    fun `a copy the parser rejects is deleted and treated as missing`() = runTest {
        cache.save(4, CachedScreens.myWork, "not json")

        assertNull(cache.read(4, CachedScreens.myWork))
        assertTrue(store.rows.isEmpty())
    }

    @Test
    fun `opening a screen marks its row as opened`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        clock.now = CACHE_NOW + Duration.ofHours(3)

        cache.read(4, CachedScreens.myWork)

        assertEquals(clock.now.toEpochMilli(), store.rows.getValue(myWorkKey).lastOpenedAt)
        assertEquals(CACHE_NOW.toEpochMilli(), store.rows.getValue(myWorkKey).fetchedAt)
    }

    @Test
    fun `another user's rows are never read`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"), userId = 7)

        assertNull(cache.read(4, CachedScreens.myWork))
    }

    @Test
    fun `nothing is read while signed out`() = runTest {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        val signedOut = testScreenCache(sessions = FakeSessions(session = null), store = store, cipher = cipher, clock = clock)

        assertNull(signedOut.read(4, CachedScreens.myWork))
    }

    @Test
    fun `on app start rows not opened for seven days are deleted`() = runTest(UnconfinedTestDispatcher()) {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        clock.now = CACHE_NOW + Duration.ofDays(2)
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null))
        clock.now = CACHE_NOW + Duration.ofDays(7) + Duration.ofMinutes(1)

        cache.start(backgroundScope)

        assertEquals(setOf(CachedScreens.inbox("all").key), store.rows.keys.map { it.screen }.toSet())
    }

    @Test
    fun `the seven day clean-up runs at most once a day`() = runTest(UnconfinedTestDispatcher()) {
        cache.start(backgroundScope)
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(5, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))

        clock.now = CACHE_NOW + Duration.ofDays(8)
        cache.save(5, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))
        clock.now = CACHE_NOW + Duration.ofDays(8) + Duration.ofHours(1)
        cache.read(5, CachedScreens.myWork)

        assertEquals(setOf(5L), store.rows.keys.map { it.warehouseId }.toSet())

        cache.save(6, CachedScreens.myWork, CachedBodies.myWork(1L to "AR1"))
        clock.now = CACHE_NOW + Duration.ofDays(8) + Duration.ofHours(23)
        store.rows[ScreenCacheKey(SIGNED_IN_USER, 6, CachedScreens.myWork.key)] = store.rows.getValue(ScreenCacheKey(SIGNED_IN_USER, 6, CachedScreens.myWork.key)).let {
            StoredScreen(it.key, it.payload, it.fetchedAt, lastOpenedAt = 0)
        }
        cache.read(5, CachedScreens.myWork)

        assertEquals(setOf(5L, 6L), store.rows.keys.map { it.warehouseId }.toSet())

        clock.now = CACHE_NOW + Duration.ofDays(9) + Duration.ofHours(1)
        cache.read(5, CachedScreens.myWork)

        assertEquals(setOf(5L), store.rows.keys.map { it.warehouseId }.toSet())
    }

    @Test
    fun `the rows of any other user are deleted`() = runTest(UnconfinedTestDispatcher()) {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"), userId = 7)
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(9L to "AR24812"))

        cache.start(backgroundScope)

        assertEquals(setOf(SIGNED_IN_USER), store.rows.keys.map { it.userId }.toSet())

        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(1L to "AR1"), userId = 7)
        sessions.start(StaffSession(token = "8|def", userId = 7, username = "other", displayName = "Bo"), rememberOnDevice = false)

        assertEquals(setOf(7L), store.rows.keys.map { it.userId }.toSet())
    }

    @Test
    fun `signing out deletes everything`() = runTest(UnconfinedTestDispatcher()) {
        cache.start(backgroundScope)
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))

        sessions.end()

        assertTrue(store.rows.isEmpty())
    }

    @Test
    fun `starting without a session deletes everything`() = runTest(UnconfinedTestDispatcher()) {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        val signedOut = testScreenCache(sessions = FakeSessions(session = null), store = store, cipher = cipher, clock = clock)

        signedOut.start(backgroundScope)

        assertTrue(store.rows.isEmpty())
    }

    @Test
    fun `a warehouse no longer listed loses its rows while the user's own rows stay`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        cache.save(5, CachedScreens.workBadges, CachedBodies.badges(myWork = 1, inboxUnread = 0))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))

        cache.keepWarehouses(SIGNED_IN_USER, setOf(4L))

        assertEquals(setOf(4L, CachedScreens.USER_SCOPED), store.rows.keys.map { it.warehouseId }.toSet())
    }
}
