package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.GoodsInHubKey
import io.aiku.droid.data.api.GoodsInHubRow
import io.aiku.droid.data.api.GoodsOutResult
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
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class GoodsInBacklogViewModelTest {

    private val api = FakeGoodsInApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `pulling to refresh reloads the hub and keeps the rows when it fails`() {
        val first = listOf(GoodsInHubRow(key = GoodsInHubKey.STOCK_DELIVERIES, arriving = 2))
        val second = listOf(GoodsInHubRow(key = GoodsInHubKey.STOCK_DELIVERIES, arriving = 5))
        api.hub = GoodsOutResult.Loaded(first)
        val backlog = GoodsInBacklogViewModel(api, FakeSessions(), warehouseId = 1)
        backlog.onStart()
        api.hub = GoodsOutResult.Loaded(second)

        backlog.refresh()

        assertEquals(second, backlog.state.value.rows)
        assertFalse(backlog.state.value.isRefreshing)

        api.hub = GoodsOutResult.Unreachable

        backlog.refresh()

        assertEquals(second, backlog.state.value.rows)
        assertFalse(backlog.state.value.isRefreshing)
        assertEquals(GoodsOutProblem.Offline, backlog.state.value.problem)
    }
}
