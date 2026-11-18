package io.aiku.droid.ui.mywork

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.WorkBadges
import io.aiku.droid.data.session.StaffSession
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
        val badges = WorkBadgesViewModel(api, sessions)

        badges.refresh()

        assertTrue(api.badgeRequests.isEmpty())
        assertEquals(WorkBadges(), badges.badges.value)
    }

    @Test
    fun `choosing a warehouse loads its badges and each refresh asks again`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4, goodsOutUnassigned = 12)) }
        val badges = WorkBadgesViewModel(api, sessions)

        badges.showWarehouse(1)
        badges.refresh()

        assertEquals(listOf("7|abc" to 1L, "7|abc" to 1L), api.badgeRequests)
        assertEquals(WorkBadges(myWork = 4, goodsOutUnassigned = 12), badges.badges.value)
    }

    @Test
    fun `switching warehouse clears and reloads for the new one`() {
        api.badgeResults = { id -> if (id == 1L) GoodsOutResult.Loaded(WorkBadges(myWork = 4)) else GoodsOutResult.Unreachable }
        val badges = WorkBadgesViewModel(api, sessions)
        badges.showWarehouse(1)

        badges.showWarehouse(2)

        assertEquals(2L, api.badgeRequests.last().second)
        assertEquals(WorkBadges(), badges.badges.value)
    }

    @Test
    fun `showing the same warehouse again does not ask twice`() {
        val badges = WorkBadgesViewModel(api, sessions)

        badges.showWarehouse(1)
        badges.showWarehouse(1)

        assertEquals(1, api.badgeRequests.size)
    }

    @Test
    fun `losing the connection keeps the last counts`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4, goodsOutUnassigned = 12)) }
        val badges = WorkBadgesViewModel(api, sessions)
        badges.showWarehouse(1)

        api.badgeResults = { GoodsOutResult.Unreachable }
        badges.refresh()

        assertEquals(WorkBadges(myWork = 4, goodsOutUnassigned = 12), badges.badges.value)
    }

    @Test
    fun `a warehouse the user may no longer open clears the badges`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4, goodsOutUnassigned = 12)) }
        val badges = WorkBadgesViewModel(api, sessions)
        badges.showWarehouse(1)

        api.badgeResults = { GoodsOutResult.NotFound("") }
        badges.refresh()

        assertEquals(WorkBadges(), badges.badges.value)
    }

    @Test
    fun `a new sign in clears the badges and reloads them with the new token`() {
        api.badgeResults = { GoodsOutResult.Loaded(WorkBadges(myWork = 4)) }
        val badges = WorkBadgesViewModel(api, sessions)
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
    }
}
