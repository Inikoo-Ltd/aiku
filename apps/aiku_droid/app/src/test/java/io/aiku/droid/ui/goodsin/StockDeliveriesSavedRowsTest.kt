package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.DispatchedBucket
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.StockDeliveryPage
import io.aiku.droid.data.api.StockDeliveryQuery
import io.aiku.droid.data.api.StockDeliveryRow
import io.aiku.droid.data.api.StockDeliverySort
import io.aiku.droid.data.api.StockDeliverySource
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.data.api.SupplierRef
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.SavedLists
import io.aiku.droid.data.cache.testScreenCache
import io.aiku.droid.ui.components.DateRange
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test
import java.time.LocalDate
import java.time.ZoneId

@OptIn(ExperimentalCoroutinesApi::class)
class StockDeliveriesSavedRowsTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeGoodsInApi()
    private val cache = testScreenCache()
    private val utc = ZoneId.of("UTC")
    private val today = LocalDate.of(2026, 11, 23)
    private val rows = listOf(
        StockDeliveryRow(
            id = 1, reference = "SD-1", state = "dispatched", source = "suppliers", supplier = SupplierRef(name = "Acme"),
            dispatchedAt = "2026-11-23T08:00:00+00:00", estimatedReceivingDate = "2026-11-25", numberItems = 5,
        ),
        StockDeliveryRow(
            id = 2, reference = "SD-2", agentReference = "AG-77", state = "received", source = "agents", supplier = SupplierRef(name = "beta"),
            dispatchedAt = "2026-11-19T10:00:00+00:00", estimatedReceivingDate = "2026-11-24", numberItems = 12,
        ),
        StockDeliveryRow(id = 3, reference = "SD-3", state = "placed", source = "partners", supplier = SupplierRef(name = "Core"), dispatchedAt = "2026-11-01T10:00:00+00:00", numberItems = 1),
        StockDeliveryRow(id = 4, reference = "SD-4", state = "checked", source = "suppliers"),
    )

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `the active chip keeps the goods-in states, newest dispatched first`() {
        assertEquals(listOf(1L, 2L, 4L), ids(StockDeliveryQuery()))
        assertEquals(listOf(3L), ids(StockDeliveryQuery(state = StockDeliveryStateFilter.Placed)))
    }

    @Test
    fun `source, dispatched, search and the date range filter the saved rows`() {
        assertEquals(listOf(2L), ids(StockDeliveryQuery(source = StockDeliverySource.Agents)))
        assertEquals(listOf(1L), ids(StockDeliveryQuery(dispatched = DispatchedBucket.Today)))
        assertEquals(listOf(1L, 2L), ids(StockDeliveryQuery(dispatched = DispatchedBucket.Week)))
        assertEquals(listOf(3L), ids(StockDeliveryQuery(state = StockDeliveryStateFilter.Placed, dispatched = DispatchedBucket.Older)))
        assertEquals(listOf(2L), ids(StockDeliveryQuery(search = "ag-77")))
        assertEquals(listOf(1L), ids(StockDeliveryQuery(search = "ACME")))
        assertEquals(listOf(2L), ids(StockDeliveryQuery(from = LocalDate.of(2026, 11, 19), to = LocalDate.of(2026, 11, 19))))
    }

    @Test
    fun `each sort mirrors the server`() {
        assertEquals(listOf(2L, 1L, 4L), ids(StockDeliveryQuery(sort = StockDeliverySort.Oldest)))
        assertEquals(listOf(1L, 2L, 4L), ids(StockDeliveryQuery(sort = StockDeliverySort.Reference)))
        assertEquals(listOf(1L, 2L, 4L), ids(StockDeliveryQuery(sort = StockDeliverySort.Supplier)))
        assertEquals(listOf(2L, 1L, 4L), ids(StockDeliveryQuery(sort = StockDeliverySort.ExpectedDate)))
        assertEquals(listOf(1L, 2L, 4L), ids(StockDeliveryQuery(sort = StockDeliverySort.DispatchedAt)))
        assertEquals(listOf(2L, 1L, 4L), ids(StockDeliveryQuery(sort = StockDeliverySort.Items)))
    }

    @Test
    fun `offline the list and the filter panel count use the saved rows, and the server is used again once it answers`() = runTest {
        api.deliveryPages = { GoodsOutResult.Loaded(StockDeliveryPage(rows, ListMeta(currentPage = 1, lastPage = 2, total = 80))) }
        val deliveries = StockDeliveriesViewModel(api, FakeSessions(), warehouseId = 1, cache = cache, zone = { utc })
        deliveries.onStart()
        assertEquals(4, cache.saved(1, SavedLists.stockDeliveries)?.value?.size)

        api.deliveryPages = { GoodsOutResult.Unreachable }
        deliveries.applyFilter(deliveries.state.value.filter.copy(source = StockDeliverySource.Suppliers, sort = StockDeliverySort.Items))

        val offline = deliveries.state.value
        assertEquals(listOf(1L, 4L), offline.list.rows.map { it.id })
        assertEquals(CACHE_NOW, offline.lastUpdated)
        assertEquals(GoodsOutProblem.Offline, offline.list.firstPageProblem)
        assertFalse(offline.list.canLoadMore)

        val draft = offline.filter.copy(source = null, dates = DateRange(from = LocalDate.of(2026, 11, 19)))
        val asked = api.deliveryQueries.size
        deliveries.previewTotal(draft)
        assertEquals(2, deliveries.state.value.totalFor(draft))
        assertEquals(asked, api.deliveryQueries.size)

        deliveries.clearFilters()
        assertEquals(listOf(1L, 2L, 4L), deliveries.state.value.list.rows.map { it.id })

        api.deliveryPages = { GoodsOutResult.Loaded(StockDeliveryPage(listOf(rows[1]), ListMeta(total = 1))) }
        deliveries.refresh()

        assertNull(deliveries.state.value.offline)
        assertEquals(listOf(2L), deliveries.state.value.list.rows.map { it.id })
    }

    private fun ids(query: StockDeliveryQuery): List<Long> = offlineStockDeliveries(rows, query, utc, today).map { it.id }
}
