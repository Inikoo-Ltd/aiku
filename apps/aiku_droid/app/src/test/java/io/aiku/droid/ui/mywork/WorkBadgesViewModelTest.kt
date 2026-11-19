package io.aiku.droid.ui.mywork

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.WorkBadges
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.inbox.FakeInboxApi
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class WorkBadgesViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeMyWorkApi()
    private val sessions = FakeSessions()
    private val inbox = FakeInboxApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `nothing is asked before a warehouse is chosen`() {
        val badges = WorkBadgesViewModel(api, sessions, inbox)

        badges.refresh()

        assertTrue(api.badgeRequests.isEmpty())
        assertTrue(inbox.unreadRequests.isEmpty())
        assertEquals(WorkBadges(), badges.badges.value)
    }

    @Test
    fun `an account without a warehouse gets the inbox count on its own`() {
        inbox.unread = GoodsOutResult.Loaded(5)
        val badges = WorkBadgesViewModel(api, sessions, inbox)

        badges.showWarehouse(null)
        badges.refresh()

        assertTrue(api.badgeRequests.isEmpty())
        assertEquals(listOf("7|abc", "7|abc"), inbox.unreadRequests)
        assertEquals(WorkBadges(inboxUnread = 5), badges.badges.value)
    }

    @Test
    fun `the inbox count without a warehouse survives a lost connection`() {
        inbox.unread = GoodsOutResult.Loaded(5)
        val badges = WorkBadgesViewModel(api, sessions, inbox)
        badges.showWarehouse(null)

        inbox.unread = GoodsOutResult.Unreachable
        badges.refresh()

        assertEquals(WorkBadges(inboxUnread = 5), badges.badges.value)
    }

    @Test
    fun `a warehouse's badges carry the inbox count and the inbox can correct it`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4, inboxUnread = 7)) }
        val badges = WorkBadgesViewModel(api, sessions, inbox)
        badges.showWarehouse(1)

        assertEquals(7, badges.badges.value.inboxBadge)
        assertTrue(inbox.unreadRequests.isEmpty())

        badges.showInboxUnread(0)

        assertEquals(WorkBadges(myWork = 4, inboxUnread = 0), badges.badges.value)
        assertEquals(null, badges.badges.value.inboxBadge)
    }

    @Test
    fun `choosing a warehouse loads its badges and each refresh asks again`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4, goodsOutUnassigned = 12)) }
        val badges = WorkBadgesViewModel(api, sessions, inbox)

        badges.showWarehouse(1)
        badges.refresh()

        assertEquals(listOf("7|abc" to 1L, "7|abc" to 1L), api.badgeRequests)
        assertEquals(WorkBadges(myWork = 4, goodsOutUnassigned = 12), badges.badges.value)
    }

    @Test
    fun `switching warehouse clears and reloads for the new one`() {
        api.badgeResults = { id -> if (id == 1L) GoodsOutResult.Loaded(WorkBadges(myWork = 4)) else GoodsOutResult.Unreachable }
        val badges = WorkBadgesViewModel(api, sessions, inbox)
        badges.showWarehouse(1)

        badges.showWarehouse(2)

        assertEquals(2L, api.badgeRequests.last().second)
        assertEquals(WorkBadges(), badges.badges.value)
    }

    @Test
    fun `showing the same warehouse again does not ask twice`() {
        val badges = WorkBadgesViewModel(api, sessions, inbox)

        badges.showWarehouse(1)
        badges.showWarehouse(1)

        assertEquals(1, api.badgeRequests.size)
    }

    @Test
    fun `losing the connection keeps the last counts`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4, goodsOutUnassigned = 12)) }
        val badges = WorkBadgesViewModel(api, sessions, inbox)
        badges.showWarehouse(1)

        api.badgeResults = { GoodsOutResult.Unreachable }
        badges.refresh()

        assertEquals(WorkBadges(myWork = 4, goodsOutUnassigned = 12), badges.badges.value)
    }

    @Test
    fun `a warehouse the user may no longer open clears the badges`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4, goodsOutUnassigned = 12)) }
        val badges = WorkBadgesViewModel(api, sessions, inbox)
        badges.showWarehouse(1)

        api.badgeResults = { GoodsOutResult.NotFound("") }
        badges.refresh()

        assertEquals(WorkBadges(), badges.badges.value)
    }

    @Test
    fun `a new sign in clears the badges and reloads them with the new token`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4)) }
        val badges = WorkBadgesViewModel(api, sessions, inbox)
        badges.showWarehouse(1)

        sessions.end()

        assertEquals(WorkBadges(), badges.badges.value)

        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 1)) }
        sessions.start(StaffSession(token = "8|new", userId = 43, username = "picker", displayName = "Bo"), rememberOnDevice = false)

        assertEquals("8|new" to 1L, api.badgeRequests.last())
        assertEquals(WorkBadges(myWork = 1), badges.badges.value)
    }

    @Test
    fun `zero and null counts are hidden`() {
        assertEquals(null, WorkBadges(myWork = 0, goodsOutUnassigned = null).myWorkBadge)
        assertEquals(null, WorkBadges(myWork = 0, goodsOutUnassigned = null).goodsOutUnassignedBadge)
        assertEquals(null, WorkBadges(goodsOutUnassigned = 0).goodsOutUnassignedBadge)
        assertEquals(null, WorkBadges().myWorkBadge)
        assertEquals(3, WorkBadges(myWork = 3).myWorkBadge)
        assertEquals(12, WorkBadges(goodsOutUnassigned = 12).goodsOutUnassignedBadge)
        assertEquals(null, WorkBadges(inboxUnread = 0).inboxBadge)
        assertEquals(null, WorkBadges().inboxBadge)
        assertEquals(1, WorkBadges(inboxUnread = 1).inboxBadge)
    }

    @Test
    fun `received stock deliveries show only when there are some`() {
        assertEquals(2, WorkBadges(goodsInReceived = 2).goodsInReceivedBadge)
        assertEquals(null, WorkBadges(goodsInReceived = 0).goodsInReceivedBadge)
        assertEquals(null, WorkBadges(goodsInReceived = null).goodsInReceivedBadge)
        assertEquals(null, WorkBadges(myWork = 4, goodsOutUnassigned = 1).goodsInReceivedBadge)
    }

    @Test
    fun `badge text tops out at 99+`() {
        assertEquals("1", badgeText(1))
        assertEquals("99", badgeText(99))
        assertEquals("99+", badgeText(100))
        assertEquals("99+", badgeText(2878))
    }
}
