package io.aiku.droid.ui.dashboard

import io.aiku.droid.data.api.DashboardGoodsOut
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.WarehouseDashboard
import io.aiku.droid.data.api.WarehouseDashboardApi
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
class WarehouseDashboardCacheTest {

    private val api = GatedDashboardApi()
    private val sessions = FakeSessions()
    private val cache = testScreenCache(sessions)

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `the saved figures show at once without a skeleton and the fresh figures replace them`() {
        cache.save(4, CachedScreens.warehouseDashboard, CachedBodies.dashboard(unassigned = 47))
        api.gate = CompletableDeferred()
        api.result = GoodsOutResult.Loaded(WarehouseDashboard(goodsOut = DashboardGoodsOut(unassigned = 12)))
        val dashboard = WarehouseDashboardViewModel(api, sessions, warehouseId = 4, cache = cache)

        dashboard.onStart()

        assertEquals(47, dashboard.state.value.dashboard?.goodsOut?.unassigned)
        assertFalse(dashboard.state.value.isLoading)
        assertTrue(dashboard.state.value.isRefreshing)

        api.gate?.complete(Unit)

        assertEquals(12, dashboard.state.value.dashboard?.goodsOut?.unassigned)
        assertFalse(dashboard.state.value.isRefreshing)
        assertNull(dashboard.state.value.cachedAt)
    }

    @Test
    fun `a failed refresh keeps the saved figures and says when they were last updated`() {
        cache.save(4, CachedScreens.warehouseDashboard, CachedBodies.dashboard(unassigned = 47))
        api.result = GoodsOutResult.ServerError(500)
        val dashboard = WarehouseDashboardViewModel(api, sessions, warehouseId = 4, cache = cache)

        dashboard.onStart()

        assertEquals(47, dashboard.state.value.dashboard?.goodsOut?.unassigned)
        assertEquals(GoodsOutProblem.ServerError, dashboard.state.value.problem)
        assertEquals(CACHE_NOW, dashboard.state.value.lastUpdated)
    }

    private class GatedDashboardApi : WarehouseDashboardApi {
        var gate: CompletableDeferred<Unit>? = null
        var result: GoodsOutResult<WarehouseDashboard> = GoodsOutResult.Loaded(WarehouseDashboard())

        override suspend fun dashboard(token: String, warehouseId: Long): GoodsOutResult<WarehouseDashboard> {
            gate?.await()
            return result
        }
    }
}
