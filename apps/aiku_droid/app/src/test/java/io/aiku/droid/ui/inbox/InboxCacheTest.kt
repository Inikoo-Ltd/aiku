package io.aiku.droid.ui.inbox

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxNotification
import io.aiku.droid.data.api.InboxPage
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.CachedBodies
import io.aiku.droid.data.cache.CachedScreens
import io.aiku.droid.data.cache.save
import io.aiku.droid.data.cache.testScreenCache
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableSharedFlow
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
class InboxCacheTest {

    private val api = FakeInboxApi()
    private val sessions = FakeSessions()
    private val cache = testScreenCache(sessions)
    private val kept = InboxNotification(id = "a", kind = "delivery_note", event = "assigned", title = "a assigned")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun inbox() = InboxViewModel(api, sessions, MutableSharedFlow(), cache)

    @Test
    fun `the saved first page shows at once and the fresh page replaces it, dropping what the server no longer sends`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null, "outside-allow-list" to null))
        api.pageGate = CompletableDeferred()
        api.pages = { GoodsOutResult.Loaded(InboxPage(listOf(kept), ListMeta(currentPage = 1, lastPage = 1), unread = 1)) }
        val inbox = inbox()

        inbox.onStart()

        assertEquals(listOf("a", "outside-allow-list"), inbox.state.value.notifications.map { it.id })
        assertEquals(2, inbox.state.value.unread)
        assertTrue(inbox.state.value.isRefreshing)
        assertFalse(inbox.state.value.canLoadMore)

        api.pageGate?.complete(Unit)

        assertEquals(listOf(kept), inbox.state.value.notifications)
        assertEquals(1, inbox.state.value.unread)
        assertFalse(inbox.state.value.isRefreshing)
        assertNull(inbox.state.value.cachedAt)
    }

    @Test
    fun `a failed first page keeps the saved copy and says when it was last updated`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("a" to null))
        api.pages = { GoodsOutResult.Unreachable }
        val inbox = inbox()

        inbox.onStart()

        assertEquals(listOf("a"), inbox.state.value.notifications.map { it.id })
        assertEquals(GoodsOutProblem.Offline, inbox.state.value.firstPageProblem)
        assertEquals(CACHE_NOW, inbox.state.value.lastUpdated)
    }

    @Test
    fun `pull to refresh does not read the saved copy again`() {
        api.pages = { GoodsOutResult.Loaded(InboxPage(listOf(kept), ListMeta(currentPage = 1, lastPage = 1), unread = 1)) }
        val inbox = inbox()
        inbox.onStart()
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.inbox("all"), CachedBodies.inbox("z" to null))
        api.pageGate = CompletableDeferred()

        inbox.refresh()

        assertEquals(listOf(kept), inbox.state.value.notifications)
    }
}
