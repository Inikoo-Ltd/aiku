package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.PalletReturnCounts
import io.aiku.droid.data.api.PalletReturnPage
import io.aiku.droid.data.api.PalletReturnQuery
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnSort
import io.aiku.droid.data.api.PalletReturnStateFilter
import io.aiku.droid.data.api.PalletReturnTypeFilter
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.ui.components.ListFilter
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
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PalletReturnsViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeFulfilmentOutboundApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `the list opens on active returns of both types, pages and keeps the counts`() {
        api.returnPages = { query ->
            val ids = if (query.page == 1) 1L..2L else 3L..3L
            GoodsOutResult.Loaded(
                PalletReturnPage(
                    rows = ids.map { PalletReturnRow(id = it, reference = "r-$it") },
                    meta = ListMeta(currentPage = query.page, lastPage = 2, perPage = 2, total = 3),
                    counts = PalletReturnCounts(states = StateCounts(mapOf("active" to 6, "picked" to 1)), types = StateCounts(mapOf("pallets" to 4, "items" to 2))),
                ),
            )
        }
        val returns = PalletReturnsViewModel(api, FakeSessions(), warehouseId = 1, perPage = 2)

        returns.onStart()
        returns.loadMore()

        assertEquals(listOf(1L, 2L, 3L), returns.state.value.list.rows.map { it.id })
        assertEquals(PalletReturnQuery(perPage = 2), api.returnQueries.first())
        assertEquals(6, returns.state.value.counts?.states?.count(PalletReturnStateFilter.Active.key))
        assertEquals(2, returns.state.value.counts?.types?.count(PalletReturnTypeFilter.Items.key))
        assertTrue(!returns.state.value.list.hasMore)
    }

    @Test
    fun `pulling to refresh reloads the first page`() {
        api.returnPages = { query -> GoodsOutResult.Loaded(PalletReturnPage(rows = listOf(PalletReturnRow(id = 1, reference = "r-1")), meta = ListMeta(currentPage = query.page))) }
        val returns = PalletReturnsViewModel(api, FakeSessions(), warehouseId = 1)
        returns.onStart()
        api.returnPages = { query -> GoodsOutResult.Loaded(PalletReturnPage(rows = listOf(PalletReturnRow(id = 2, reference = "r-2")), meta = ListMeta(currentPage = query.page))) }

        returns.refresh()

        assertEquals(listOf(2L), returns.state.value.list.rows.map { it.id })
        assertEquals(2, api.returnQueries.size)
        assertTrue(!returns.state.value.list.isRefreshing)
    }

    @Test
    fun `state, type and search filters reload from the first page`() {
        val returns = PalletReturnsViewModel(api, FakeSessions(), warehouseId = 1)
        returns.onStart()

        returns.selectState(PalletReturnStateFilter.Picked)
        returns.selectType(PalletReturnTypeFilter.Items)
        returns.search(" acme ")
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(PalletReturnQuery(state = PalletReturnStateFilter.Picked, type = PalletReturnTypeFilter.Items, search = "acme"), api.returnQueries.last())

        returns.selectType(null)

        assertEquals(null, api.returnQueries.last().type)
        assertEquals(1, api.returnQueries.last().page)
    }

    @Test
    fun `a failed page keeps what loaded and retries that page`() {
        api.returnPages = { query ->
            if (query.page == 1) {
                GoodsOutResult.Loaded(PalletReturnPage(rows = listOf(PalletReturnRow(id = 1)), meta = ListMeta(currentPage = 1, lastPage = 2)))
            } else {
                GoodsOutResult.Unreachable
            }
        }
        val returns = PalletReturnsViewModel(api, FakeSessions(), warehouseId = 1)
        returns.onStart()

        returns.loadMore()

        assertEquals(GoodsOutProblem.Offline, returns.state.value.list.nextPageProblem)
        assertEquals(1, returns.state.value.list.rows.size)

        returns.retry()

        assertEquals(2, api.returnQueries.last().page)
    }

    @Test
    fun `an expired session asks to sign in again`() {
        api.returnPages = { GoodsOutResult.SessionExpired }
        val returns = PalletReturnsViewModel(api, FakeSessions(), warehouseId = 1)

        returns.onStart()

        assertTrue(returns.state.value.isSessionExpired)
    }

    @Test
    fun `a chosen sort reloads from page 1 with the search kept, and clearing drops only the sort`() {
        val returns = PalletReturnsViewModel(api, FakeSessions(), warehouseId = 1)
        returns.onStart()
        returns.search("acme")
        dispatcher.scheduler.advanceTimeBy(301)

        returns.applyFilter(ListFilter(sort = PalletReturnSort.Dispatched))

        assertEquals(PalletReturnQuery(search = "acme", sort = PalletReturnSort.Dispatched), api.returnQueries.last())
        assertEquals(1, returns.state.value.filter.activeCount)
        assertTrue(returns.state.value.filter.isFiltered)

        returns.clearFilters()

        assertEquals(PalletReturnQuery(search = "acme"), api.returnQueries.last())
        assertFalse(returns.state.value.filter.isFiltered)
    }
}
