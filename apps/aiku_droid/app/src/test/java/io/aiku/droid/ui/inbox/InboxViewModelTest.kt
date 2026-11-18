package io.aiku.droid.ui.inbox

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxNotification
import io.aiku.droid.data.api.InboxPage
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
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
import java.time.Instant

@OptIn(ExperimentalCoroutinesApi::class)
class InboxViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeInboxApi()
    private val arrivals = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    private val readTime = Instant.parse("2026-11-18T10:00:00Z")
    private val newest = InboxNotification(id = "a", kind = "delivery_note", event = "assigned", title = "AR1 assigned to you", createdAt = "2026-11-18T09:00:00Z")
    private val older = InboxNotification(id = "b", kind = "return", event = "cancelled", title = "RET-31 cancelled", createdAt = "2026-11-17T09:00:00Z", readAt = "2026-11-17T10:00:00Z")
    private val oldest = InboxNotification(id = "c", kind = "picking_session", event = "taken_over", title = "PS-9 taken over", createdAt = "2026-11-10T09:00:00Z")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun inbox() = InboxViewModel(api, FakeSessions(), arrivals, now = { readTime })

    private fun page(rows: List<InboxNotification>, current: Int = 1, last: Int = 1, unread: Int? = rows.count { it.isUnread }) =
        GoodsOutResult.Loaded(InboxPage(rows, ListMeta(currentPage = current, lastPage = last, perPage = 30, total = rows.size), unread))

    @Test
    fun `opening loads the first page in the order received with the unread count`() {
        api.pages = { page(listOf(newest, older)) }
        val inbox = inbox()

        assertFalse(inbox.state.value.hasLoaded)

        inbox.onStart()

        assertEquals(listOf(1), api.pageRequests)
        assertEquals(listOf(newest, older), inbox.state.value.notifications)
        assertEquals(1, inbox.state.value.unread)
        assertTrue(inbox.state.value.hasLoaded)
        assertFalse(inbox.state.value.isEmpty)
    }

    @Test
    fun `no notifications shows the empty state`() {
        api.pages = { page(emptyList(), unread = 0) }
        val inbox = inbox()

        inbox.onStart()

        assertTrue(inbox.state.value.isEmpty)
        assertEquals(0, inbox.state.value.unreadCount)
    }

    @Test
    fun `a failed first page shows the problem and retry loads it again`() {
        api.pages = { GoodsOutResult.Unreachable }
        val inbox = inbox()

        inbox.onStart()

        assertEquals(GoodsOutProblem.Offline, inbox.state.value.firstPageProblem)
        assertFalse(inbox.state.value.isEmpty)

        api.pages = { page(listOf(newest)) }
        inbox.retry()

        assertNull(inbox.state.value.problem)
        assertEquals(listOf(newest), inbox.state.value.notifications)
    }

    @Test
    fun `pull to refresh reloads the first page and replaces the list`() {
        api.pages = { page(listOf(older)) }
        val inbox = inbox()
        inbox.onStart()

        api.pages = { page(listOf(newest, older)) }
        inbox.refresh()

        assertEquals(listOf(1, 1), api.pageRequests)
        assertEquals(listOf(newest, older), inbox.state.value.notifications)
        assertFalse(inbox.state.value.isRefreshing)
    }

    @Test
    fun `loading more appends the next page until the last one`() {
        api.pages = { pageNumber -> if (pageNumber == 1) page(listOf(newest, older), current = 1, last = 2, unread = 2) else page(listOf(oldest), current = 2, last = 2, unread = 2) }
        val inbox = inbox()
        inbox.onStart()

        assertTrue(inbox.state.value.canLoadMore)

        inbox.loadMore()
        inbox.loadMore()

        assertEquals(listOf(1, 2), api.pageRequests)
        assertEquals(listOf(newest, older, oldest), inbox.state.value.notifications)
        assertFalse(inbox.state.value.canLoadMore)
    }

    @Test
    fun `a failed next page keeps the rows and retries that page`() {
        api.pages = { pageNumber -> if (pageNumber == 1) page(listOf(newest), current = 1, last = 2) else GoodsOutResult.ServerError(500) }
        val inbox = inbox()
        inbox.onStart()

        inbox.loadMore()

        assertEquals(listOf(newest), inbox.state.value.notifications)
        assertEquals(GoodsOutProblem.ServerError, inbox.state.value.nextPageProblem)
        assertNull(inbox.state.value.firstPageProblem)

        api.pages = { page(listOf(oldest), current = 2, last = 2) }
        inbox.retry()

        assertEquals(listOf(1, 2, 2), api.pageRequests)
        assertEquals(listOf(newest, oldest), inbox.state.value.notifications)
    }

    @Test
    fun `an expired session asks to sign in again`() {
        api.pages = { GoodsOutResult.SessionExpired }
        val inbox = inbox()

        inbox.onStart()

        assertTrue(inbox.state.value.isSessionExpired)
    }

    @Test
    fun `opening an unread notification marks it read at once and tells the server`() {
        api.pages = { page(listOf(newest, older), unread = 1) }
        api.readResult = { GoodsOutResult.Unreachable }
        val inbox = inbox()
        inbox.onStart()

        inbox.markRead(newest)

        assertEquals(listOf("a"), api.readRequests)
        assertFalse(inbox.state.value.notifications.first().isUnread)
        assertEquals(readTime.toString(), inbox.state.value.notifications.first().readAt)
        assertEquals(0, inbox.state.value.unread)
    }

    @Test
    fun `the server's copy of a read row replaces the local one`() {
        val serverRow = newest.copy(readAt = "2026-11-18T09:05:00Z")
        api.pages = { page(listOf(newest), unread = 1) }
        api.readResult = { GoodsOutResult.Loaded(serverRow) }
        val inbox = inbox()
        inbox.onStart()

        inbox.markRead(newest)

        assertEquals(listOf(serverRow), inbox.state.value.notifications)
    }

    @Test
    fun `opening a notification that is already read does not call the server`() {
        api.pages = { page(listOf(older), unread = 0) }
        val inbox = inbox()
        inbox.onStart()

        inbox.markRead(older)

        assertTrue(api.readRequests.isEmpty())
        assertEquals(0, inbox.state.value.unread)
    }

    @Test
    fun `mark all as read clears every unread row and takes the server's count`() {
        api.pages = { page(listOf(newest, older, oldest), unread = 2) }
        api.readAllResult = GoodsOutResult.Loaded(0)
        val inbox = inbox()
        inbox.onStart()

        inbox.markAllRead()

        assertEquals(1, api.readAllRequests)
        assertTrue(inbox.state.value.notifications.none { it.isUnread })
        assertEquals("2026-11-17T10:00:00Z", inbox.state.value.notifications[1].readAt)
        assertEquals(0, inbox.state.value.unread)
    }

    @Test
    fun `mark all as read that fails puts the unread rows back and reports it once`() {
        api.pages = { page(listOf(newest, older, oldest), unread = 2) }
        api.readAllResult = GoodsOutResult.Unreachable
        val inbox = inbox()
        inbox.onStart()

        inbox.markAllRead()

        assertEquals(listOf(newest, older, oldest), inbox.state.value.notifications)
        assertEquals(2, inbox.state.value.unread)
        assertEquals(GoodsOutProblem.Offline, inbox.state.value.markAllProblem)

        inbox.markAllProblemShown()

        assertNull(inbox.state.value.markAllProblem)
    }

    @Test
    fun `a push that arrives reloads the first page once the inbox has loaded`() {
        api.pages = { page(listOf(older)) }
        val inbox = inbox()

        arrivals.tryEmit(Unit)

        assertTrue(api.pageRequests.isEmpty())

        inbox.onStart()
        api.pages = { page(listOf(newest, older)) }
        arrivals.tryEmit(Unit)

        assertEquals(listOf(1, 1), api.pageRequests)
        assertEquals(listOf(newest, older), inbox.state.value.notifications)
    }
}
