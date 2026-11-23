package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.DispatchedBucket
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedSko
import io.aiku.droid.data.api.ScannedStockDelivery
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.data.api.StockDeliveryPage
import io.aiku.droid.data.api.StockDeliveryQuery
import io.aiku.droid.data.api.StockDeliveryRow
import io.aiku.droid.data.api.StockDeliverySort
import io.aiku.droid.data.api.StockDeliverySource
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.ui.components.DateRange
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.navigation.StockDeliveryRoute
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
import java.time.LocalDate

@OptIn(ExperimentalCoroutinesApi::class)
class StockDeliveriesViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeGoodsInApi()
    private val counts = StateCounts(mapOf("active" to 7, "received" to 2, "placed" to 40))

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
        api.deliveryPages = { query -> GoodsOutResult.Loaded(pageFor(query)) }
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun rows(ids: LongRange) = ids.map { StockDeliveryRow(id = it, reference = "SD-$it") }

    private fun pageFor(query: StockDeliveryQuery): StockDeliveryPage = when {
        query.perPage == 1 -> StockDeliveryPage(emptyList(), ListMeta(currentPage = 1, lastPage = 33, perPage = 1, total = 33), counts)
        query.page == 1 -> StockDeliveryPage(rows(1L..2L), ListMeta(currentPage = 1, lastPage = 2, perPage = 50, total = 4), counts)
        else -> StockDeliveryPage(rows(3L..4L), ListMeta(currentPage = 2, lastPage = 2, perPage = 50, total = 4), counts)
    }

    private fun viewModel(filter: StockDeliveryFilter = StockDeliveryFilter()) = StockDeliveriesViewModel(api, FakeSessions(), warehouseId = 1, initialFilter = filter, perPage = 50)

    @Test
    fun `pulling to refresh reloads the first page and keeps the rows until it arrives`() {
        val deliveries = viewModel()
        deliveries.onStart()
        deliveries.loadMore()
        api.deliveryPages = { GoodsOutResult.Loaded(StockDeliveryPage(rows(9L..9L), ListMeta(currentPage = 1, lastPage = 1, perPage = 50, total = 1), counts)) }

        deliveries.refresh()

        val state = deliveries.state.value
        assertEquals(listOf(9L), state.list.rows.map { it.id })
        assertFalse(state.list.isRefreshing)
        assertEquals(1, api.deliveryQueries.last().page)
        assertEquals(3, api.deliveryQueries.size)
    }

    @Test
    fun `opening loads the active deliveries with the counts per state`() {
        val deliveries = viewModel()

        deliveries.onStart()

        val state = deliveries.state.value
        assertEquals(listOf(1L, 2L), state.list.rows.map { it.id })
        assertEquals(4, state.list.total)
        assertEquals(7, state.counts?.count(StockDeliveryStateFilter.Active.key))
        assertEquals(StockDeliveryQuery(page = 1, perPage = 50), api.deliveryQueries.single())
    }

    @Test
    fun `real paging appends the next page until the last one`() {
        val deliveries = viewModel()
        deliveries.onStart()

        deliveries.loadMore()
        deliveries.loadMore()

        assertEquals(listOf(1L, 2L, 3L, 4L), deliveries.state.value.list.rows.map { it.id })
        assertFalse(deliveries.state.value.list.hasMore)
        assertEquals(listOf(1, 2), api.deliveryQueries.map { it.page })
    }

    @Test
    fun `a hub card opens the list on its source and state`() {
        val deliveries = viewModel(StockDeliveryFilter(state = StockDeliveryStateFilter.Received, source = StockDeliverySource.Agents))

        deliveries.onStart()

        val query = api.deliveryQueries.single()
        assertEquals(StockDeliveryStateFilter.Received, query.state)
        assertEquals(StockDeliverySource.Agents, query.source)
    }

    @Test
    fun `a state tab, the filter sheet and clearing reload from the first page`() {
        val deliveries = viewModel()
        deliveries.onStart()
        deliveries.loadMore()

        deliveries.selectState(StockDeliveryStateFilter.Placed)
        deliveries.applyFilter(
            deliveries.state.value.filter.copy(source = StockDeliverySource.Partners, dispatched = DispatchedBucket.Older, sort = StockDeliverySort.Items),
        )

        val filtered = api.deliveryQueries.last()
        assertEquals(StockDeliveryStateFilter.Placed, filtered.state)
        assertEquals(StockDeliverySource.Partners, filtered.source)
        assertEquals(DispatchedBucket.Older, filtered.dispatched)
        assertEquals(StockDeliverySort.Items, filtered.sort)
        assertEquals(1, filtered.page)
        assertEquals(2, deliveries.state.value.filter.activeFilterCount)

        deliveries.clearFilters()

        val cleared = api.deliveryQueries.last()
        assertEquals(StockDeliveryStateFilter.Placed, cleared.state)
        assertNull(cleared.source)
        assertNull(cleared.dispatched)
        assertNull(cleared.sort)
    }

    @Test
    fun `searching waits for typing to pause`() {
        val deliveries = viewModel()
        deliveries.onStart()

        deliveries.search("SD")
        deliveries.search("SD-9")
        assertEquals(1, api.deliveryQueries.size)
        dispatcher.scheduler.advanceTimeBy(SEARCH_WAIT_MILLIS)

        assertEquals(listOf(null, "SD-9"), api.deliveryQueries.map { it.search })
    }

    @Test
    fun `the filter sheet shows the total from meta for the draft`() {
        val deliveries = viewModel()
        deliveries.onStart()
        val draft = deliveries.state.value.filter.copy(source = StockDeliverySource.Agents)

        assertEquals(4, deliveries.state.value.totalFor(deliveries.state.value.filter))

        deliveries.previewTotal(draft)
        dispatcher.scheduler.advanceTimeBy(SEARCH_WAIT_MILLIS)

        assertEquals(33, deliveries.state.value.totalFor(draft))
        assertEquals(1, api.deliveryQueries.last().perPage)
    }

    @Test
    fun `scanning a stock delivery code opens it and anything else says so`() {
        val deliveries = viewModel()
        api.scanResult = GoodsOutResult.Loaded(
            ScanLookup(code = "SD-9", type = ScanMatch.STOCK_DELIVERY, stockDelivery = ScannedStockDelivery(id = 9, reference = "SD-9")),
        )

        deliveries.lookUpScan("SD-9")

        assertEquals(StockScanOutcome.Open(StockDeliveryRoute(id = 9, reference = "SD-9")), deliveries.state.value.scanOutcome)

        deliveries.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "5060", type = ScanMatch.SKO, sko = ScannedSko(id = 4, code = "AWO-1")))
        deliveries.lookUpScan("5060")

        assertEquals(StockScanOutcome.NoMatch("5060"), deliveries.state.value.scanOutcome)
    }

    private companion object {
        const val SEARCH_WAIT_MILLIS = 301L
    }

    @Test
    fun `a date range and a new sort reach the query, count on the filter button and clear together`() {
        val deliveries = viewModel()
        deliveries.onStart()
        deliveries.loadMore()
        val range = DateRange(from = LocalDate.of(2026, 11, 18), to = LocalDate.of(2026, 11, 23))

        deliveries.applyFilter(deliveries.state.value.filter.copy(dates = range, sort = StockDeliverySort.ExpectedDate))

        val query = api.deliveryQueries.last()
        assertEquals(LocalDate.of(2026, 11, 18), query.from)
        assertEquals(LocalDate.of(2026, 11, 23), query.to)
        assertEquals(StockDeliverySort.ExpectedDate, query.sort)
        assertEquals(1, query.page)
        assertEquals(1, deliveries.state.value.filter.activeFilterCount)
        assertEquals(2, deliveries.state.value.filter.panelCount)
        assertTrue(deliveries.state.value.filter.isFiltered)

        deliveries.clearFilters()

        val cleared = api.deliveryQueries.last()
        assertNull(cleared.from)
        assertNull(cleared.to)
        assertNull(cleared.sort)
        assertEquals(0, deliveries.state.value.filter.panelCount)
    }
}
