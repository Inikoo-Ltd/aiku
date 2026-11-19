package io.aiku.droid.ui.mywork

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.MyWorkPage
import io.aiku.droid.data.api.MyWorkRow
import io.aiku.droid.data.api.WorkBadges
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.CachedBodies
import io.aiku.droid.data.cache.CachedScreens
import io.aiku.droid.data.cache.save
import io.aiku.droid.data.cache.testScreenCache
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inbox.FakeInboxApi
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
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
class MyWorkCacheTest {

    private val api = FakeMyWorkApi()
    private val sessions = FakeSessions()
    private val cache = testScreenCache(sessions)
    private val stillAssigned = MyWorkRow(kind = MyWorkKind.DeliveryNote, id = 9, reference = "AR24812", state = "handling", step = "picking")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `the saved copy shows at once while the fresh answer loads, then the fresh answer replaces it`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811", 9L to "AR24812"))
        api.gate = CompletableDeferred()
        api.work = GoodsOutResult.Loaded(MyWorkPage(rows = listOf(stillAssigned)))
        val work = MyWorkViewModel(api, sessions, warehouseId = 4, cache = cache)

        work.onStart()

        assertEquals(listOf("AR24811", "AR24812"), work.state.value.rows.map { it.reference })
        assertTrue(work.state.value.isRefreshing)
        assertTrue(work.state.value.hasLoaded)
        assertEquals(CACHE_NOW, work.state.value.cachedAt)
        assertNull(work.state.value.lastUpdated)

        api.gate?.complete(Unit)

        assertEquals(listOf(stillAssigned), work.state.value.rows)
        assertFalse(work.state.value.isRefreshing)
        assertNull(work.state.value.cachedAt)
    }

    @Test
    fun `without a saved copy the screen waits for the answer as before`() {
        api.gate = CompletableDeferred()
        val work = MyWorkViewModel(api, sessions, warehouseId = 4, cache = cache)

        work.onStart()

        assertFalse(work.state.value.hasLoaded)
        assertTrue(work.state.value.rows.isEmpty())
    }

    @Test
    fun `a failed refresh keeps the saved copy and says when it was last updated`() {
        cache.save(4, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        api.work = GoodsOutResult.Unreachable
        val work = MyWorkViewModel(api, sessions, warehouseId = 4, cache = cache)

        work.onStart()

        assertEquals(listOf("AR24811"), work.state.value.rows.map { it.reference })
        assertEquals(GoodsOutProblem.Offline, work.state.value.problem)
        assertEquals(CACHE_NOW, work.state.value.lastUpdated)
        assertFalse(work.state.value.isRefreshing)
    }

    @Test
    fun `the saved copy of another warehouse is not shown`() {
        cache.save(5, CachedScreens.myWork, CachedBodies.myWork(123L to "AR24811"))
        api.gate = CompletableDeferred()
        val work = MyWorkViewModel(api, sessions, warehouseId = 4, cache = cache)

        work.onStart()

        assertTrue(work.state.value.rows.isEmpty())
    }

    @Test
    fun `the saved badges show at once and the fresh badges replace them`() {
        cache.save(4, CachedScreens.workBadges, CachedBodies.badges(myWork = 3, inboxUnread = 2))
        api.gate = CompletableDeferred()
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 0, inboxUnread = 1)) }
        val badges = WorkBadgesViewModel(api, sessions, FakeInboxApi(), cache)

        badges.showWarehouse(4)

        assertEquals(3, badges.badges.value.myWork)
        assertEquals(2, badges.badges.value.inboxUnread)

        api.gate?.complete(Unit)

        assertEquals(WorkBadges(myWork = 0, inboxUnread = 1), badges.badges.value)
    }

    @Test
    fun `switching warehouse shows that warehouse's saved badges, not the previous ones`() {
        cache.save(5, CachedScreens.workBadges, CachedBodies.badges(myWork = 7, inboxUnread = 0))
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 1)) }
        val badges = WorkBadgesViewModel(api, sessions, FakeInboxApi(), cache)
        badges.showWarehouse(4)
        api.gate = CompletableDeferred()

        badges.showWarehouse(5)

        assertEquals(7, badges.badges.value.myWork)
    }

    @Test
    fun `badges that fail to refresh keep the saved copy`() {
        cache.save(4, CachedScreens.workBadges, CachedBodies.badges(myWork = 3, inboxUnread = 2))
        api.badgeResults = { GoodsOutResult.Unreachable }
        val badges = WorkBadgesViewModel(api, sessions, FakeInboxApi(), cache)

        badges.showWarehouse(4)

        assertEquals(3, badges.badges.value.myWork)
    }
}
