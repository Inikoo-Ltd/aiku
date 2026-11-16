package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MovementFlow
import io.aiku.droid.data.api.MovementPage
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
class StockMovementsViewModelTest {

    private val api = FakeInventoryApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        api.movementPages = { page ->
            GoodsOutResult.Loaded(if (page == 1) MovementPage(movements(1L..2L), meta(1, 2, 4)) else MovementPage(movements(3L..4L), meta(2, 2, 4)))
        }
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `an SKO history pages and filters by flow`() {
        val history = StockMovementsViewModel(api, FakeSessions(), MovementSource.Sko(warehouseId = 1, orgStockId = 77))
        history.onStart()
        history.loadMore()

        assertEquals(listOf(1L, 2L, 3L, 4L), history.state.value.list.rows.map { it.id })
        assertFalse(history.state.value.list.hasMore)

        history.selectFlow(MovementFlow.Audit)

        assertEquals(MovementFlow.Audit, history.state.value.flow)
        assertEquals(MovementRequest(orgStockId = 77, locationId = null, flow = MovementFlow.Audit, page = 1), api.movementRequests.last())
        assertEquals(listOf(1L, 2L), history.state.value.list.rows.map { it.id })
        assertTrue(history.canFilterByFlow)
    }

    @Test
    fun `a location history has no flow filter`() {
        val history = StockMovementsViewModel(api, FakeSessions(), MovementSource.Location(locationId = 220))
        history.onStart()

        history.selectFlow(MovementFlow.In)

        assertNull(history.state.value.flow)
        assertEquals(listOf(MovementRequest(orgStockId = null, locationId = 220, flow = null, page = 1)), api.movementRequests)
        assertFalse(history.canFilterByFlow)
    }

    @Test
    fun `a failed next page keeps the rows and can be retried`() {
        val history = StockMovementsViewModel(api, FakeSessions(), MovementSource.Location(locationId = 220))
        history.onStart()
        val pages = api.movementPages
        api.movementPages = { GoodsOutResult.Unreachable }

        history.loadMore()
        assertEquals(2, history.state.value.list.rows.size)
        assertTrue(history.state.value.list.nextPageProblem != null)

        api.movementPages = pages
        history.retry()
        assertEquals(4, history.state.value.list.rows.size)
    }
}
