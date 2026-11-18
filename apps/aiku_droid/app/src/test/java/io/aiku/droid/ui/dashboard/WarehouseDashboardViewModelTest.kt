package io.aiku.droid.ui.dashboard

import io.aiku.droid.data.api.DashboardGoodsOut
import io.aiku.droid.data.api.DashboardInventory
import io.aiku.droid.data.api.DispatchedToday
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.WarehouseDashboard
import io.aiku.droid.data.api.WarehouseDashboardApi
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
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
class WarehouseDashboardViewModelTest {

    private val api = FakeDashboardApi()
    private val goodsOut = DashboardGoodsOut(unassigned = 47, handling = 17, handlingBlocked = 2, packed = 26, inWarehouse = 92, dispatchedToday = DispatchedToday(count = 142, yesterdayByNow = 130))
    private val inventory = DashboardInventory(numberCurrentOrgStocks = 4812, belowMinimumStock = 17, negativeStocks = 5, numberAbnormalities = 3)

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `opening loads this warehouse's figures and keeps the blocks the user has no right to as null`() {
        api.result = GoodsOutResult.Loaded(WarehouseDashboard(goodsOut = goodsOut, inventory = inventory))
        val dashboard = WarehouseDashboardViewModel(api, FakeSessions(), warehouseId = 4)

        assertTrue(dashboard.state.value.isLoading)

        dashboard.onStart()

        assertEquals(listOf(4L), api.requests)
        assertEquals(goodsOut, dashboard.state.value.dashboard?.goodsOut)
        assertNull(dashboard.state.value.dashboard?.goodsIn)
        assertEquals(inventory, dashboard.state.value.dashboard?.inventory)
        assertFalse(dashboard.state.value.isLoading)
    }

    @Test
    fun `pull to refresh reloads and replaces the figures`() {
        api.result = GoodsOutResult.Loaded(WarehouseDashboard(goodsOut = goodsOut))
        val dashboard = WarehouseDashboardViewModel(api, FakeSessions(), warehouseId = 4)
        dashboard.onStart()

        api.result = GoodsOutResult.Loaded(WarehouseDashboard(goodsOut = goodsOut.copy(unassigned = 40)))
        dashboard.refresh()

        assertEquals(2, api.requests.size)
        assertEquals(40, dashboard.state.value.dashboard?.goodsOut?.unassigned)
        assertFalse(dashboard.state.value.isRefreshing)
    }

    @Test
    fun `no block at all leaves an empty dashboard`() {
        val dashboard = WarehouseDashboardViewModel(api, FakeSessions(), warehouseId = 4)

        dashboard.onStart()

        assertEquals(WarehouseDashboard(), dashboard.state.value.dashboard)
        assertFalse(dashboard.state.value.isLoading)
    }

    @Test
    fun `a failed load shows the problem and retry clears it`() {
        api.result = GoodsOutResult.Unreachable
        val dashboard = WarehouseDashboardViewModel(api, FakeSessions(), warehouseId = 4)

        dashboard.onStart()

        assertEquals(GoodsOutProblem.Offline, dashboard.state.value.problem)
        assertFalse(dashboard.state.value.isLoading)

        api.result = GoodsOutResult.Loaded(WarehouseDashboard(inventory = inventory))
        dashboard.retry()

        assertNull(dashboard.state.value.problem)
        assertEquals(inventory, dashboard.state.value.dashboard?.inventory)
    }

    @Test
    fun `a failed refresh keeps the figures already shown`() {
        api.result = GoodsOutResult.Loaded(WarehouseDashboard(goodsOut = goodsOut))
        val dashboard = WarehouseDashboardViewModel(api, FakeSessions(), warehouseId = 4)
        dashboard.onStart()

        api.result = GoodsOutResult.ServerError(500)
        dashboard.refresh()

        assertEquals(goodsOut, dashboard.state.value.dashboard?.goodsOut)
        assertEquals(GoodsOutProblem.ServerError, dashboard.state.value.problem)
        assertFalse(dashboard.state.value.isRefreshing)
    }

    @Test
    fun `without a warehouse nothing is asked and no block shows`() {
        val dashboard = WarehouseDashboardViewModel(api, FakeSessions(), warehouseId = null)

        dashboard.onStart()

        assertTrue(api.requests.isEmpty())
        assertEquals(WarehouseDashboard(), dashboard.state.value.dashboard)
    }

    @Test
    fun `an expired session asks to sign in again`() {
        api.result = GoodsOutResult.SessionExpired
        val dashboard = WarehouseDashboardViewModel(api, FakeSessions(), warehouseId = 4)

        dashboard.onStart()

        assertTrue(dashboard.state.value.isSessionExpired)
    }

    private class FakeDashboardApi : WarehouseDashboardApi {
        val requests = mutableListOf<Long>()
        var result: GoodsOutResult<WarehouseDashboard> = GoodsOutResult.Loaded(WarehouseDashboard())

        override suspend fun dashboard(token: String, warehouseId: Long): GoodsOutResult<WarehouseDashboard> {
            requests += warehouseId
            return result
        }
    }
}
