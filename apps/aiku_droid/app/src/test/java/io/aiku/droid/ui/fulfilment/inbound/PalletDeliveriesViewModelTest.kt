package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.PalletDeliveryPage
import io.aiku.droid.data.api.PalletDeliveryQuery
import io.aiku.droid.data.api.PalletDeliveryRow
import io.aiku.droid.data.api.PalletDeliverySort
import io.aiku.droid.data.api.PalletDeliveryStateFilter
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedPallet
import io.aiku.droid.data.api.ScannedPalletDelivery
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.ui.components.DateRange
import io.aiku.droid.ui.components.ListFilter
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveryRoute
import io.aiku.droid.ui.scan.ScanProblem
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
import java.time.LocalDate

@OptIn(ExperimentalCoroutinesApi::class)
class PalletDeliveriesViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeFulfilmentInboundApi()
    private val counts = StateCounts(mapOf("active" to 4, "confirmed" to 2, "booking_in" to 1))

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
        api.deliveryPages = { query -> GoodsOutResult.Loaded(pageFor(query)) }
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun rows(ids: LongRange, state: String = "confirmed") = ids.map { PalletDeliveryRow(id = it, reference = "acme-0$it", state = state) }

    private fun pageFor(query: PalletDeliveryQuery): PalletDeliveryPage = when (query.page) {
        1 -> PalletDeliveryPage(rows(1L..2L), ListMeta(currentPage = 1, lastPage = 2, perPage = 50, total = 4), counts)
        else -> PalletDeliveryPage(rows(3L..4L), ListMeta(currentPage = 2, lastPage = 2, perPage = 50, total = 4), null)
    }

    private fun viewModel() = PalletDeliveriesViewModel(api, FakeSessions(), warehouseId = 1, perPage = 50)

    @Test
    fun `pulling to refresh reloads the first page`() {
        val deliveries = viewModel()
        deliveries.onStart()
        deliveries.loadMore()

        deliveries.refresh()

        assertEquals(listOf(1, 2, 1), api.deliveryQueries.map { it.page })
        assertEquals(listOf(1L, 2L), deliveries.state.value.list.rows.map { it.id })
        assertFalse(deliveries.state.value.list.isRefreshing)
    }

    @Test
    fun `opening loads the active deliveries with the counts per state`() {
        val deliveries = viewModel()

        deliveries.onStart()

        val state = deliveries.state.value
        assertEquals(listOf(1L, 2L), state.list.rows.map { it.id })
        assertEquals(4, state.list.total)
        assertEquals(4, state.counts?.count(PalletDeliveryStateFilter.Active.key))
        assertEquals(PalletDeliveryQuery(page = 1, perPage = 50), api.deliveryQueries.single())
    }

    @Test
    fun `real paging appends the next page and keeps the counts`() {
        val deliveries = viewModel()
        deliveries.onStart()

        deliveries.loadMore()
        deliveries.loadMore()

        assertEquals(listOf(1L, 2L, 3L, 4L), deliveries.state.value.list.rows.map { it.id })
        assertFalse(deliveries.state.value.list.hasMore)
        assertEquals(listOf(1, 2), api.deliveryQueries.map { it.page })
        assertEquals(2, deliveries.state.value.counts?.count("confirmed"))
    }

    @Test
    fun `a state chip reloads from the first page`() {
        val deliveries = viewModel()
        deliveries.onStart()
        deliveries.loadMore()

        deliveries.selectState(PalletDeliveryStateFilter.BookingIn)
        deliveries.selectState(PalletDeliveryStateFilter.BookingIn)

        val query = api.deliveryQueries.last()
        assertEquals(PalletDeliveryStateFilter.BookingIn, query.state)
        assertEquals(1, query.page)
        assertEquals(3, api.deliveryQueries.size)
    }

    @Test
    fun `searching waits for typing to pause`() {
        val deliveries = viewModel()
        deliveries.onStart()

        deliveries.search("ac")
        deliveries.search("acme ")
        assertEquals(1, api.deliveryQueries.size)
        dispatcher.scheduler.advanceTimeBy(SEARCH_WAIT_MILLIS)

        assertEquals(listOf(null, "acme"), api.deliveryQueries.map { it.search })
    }

    @Test
    fun `a failed first page can be retried`() {
        api.deliveryPages = { GoodsOutResult.Unreachable }
        val deliveries = viewModel()
        deliveries.onStart()

        assertEquals(GoodsOutProblem.Offline, deliveries.state.value.list.firstPageProblem)

        api.deliveryPages = { query -> GoodsOutResult.Loaded(pageFor(query)) }
        deliveries.retry()

        assertEquals(2, deliveries.state.value.list.rows.size)
    }

    @Test
    fun `a row and a scanned delivery open the screen for its state`() {
        assertEquals(FulfilmentDeliveryRoute(id = 1, reference = "acme-01"), palletDeliveryRoute(1, "acme-01", "confirmed"))
        assertEquals(FulfilmentDeliveryRoute(id = 1, reference = "acme-01"), palletDeliveryRoute(1, "acme-01", "received"))
        assertEquals(FulfilmentBookInRoute(id = 1, reference = "acme-01"), palletDeliveryRoute(1, "acme-01", "booking_in"))
        assertEquals(FulfilmentBookedInRoute(id = 1, reference = "acme-01"), palletDeliveryRoute(1, "acme-01", "booked_in"))
        assertEquals(FulfilmentDeliveryRoute(id = 1, reference = "acme-01"), palletDeliveryRoute(1, "acme-01", "not_received"))

        val deliveries = viewModel()
        api.scanResult = GoodsOutResult.Loaded(
            ScanLookup(code = "pad-acme-012", type = ScanMatch.PALLET_DELIVERY, palletDelivery = ScannedPalletDelivery(id = 12, reference = "acme-012", state = "booking_in")),
        )

        deliveries.lookUpScan("pad-acme-012")

        assertEquals(StockScanOutcome.Open(FulfilmentBookInRoute(id = 12, reference = "acme-012")), deliveries.state.value.scanOutcome)
    }

    @Test
    fun `a listed reference opens without a lookup and anything else says so`() {
        val deliveries = viewModel()
        deliveries.onStart()

        deliveries.lookUpScan("ACME-01")
        assertEquals(StockScanOutcome.Open(FulfilmentDeliveryRoute(id = 1, reference = "acme-01")), deliveries.state.value.scanOutcome)
        assertTrue(api.scans.isEmpty())

        deliveries.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "acme-p1", type = ScanMatch.PALLET, pallet = ScannedPallet(id = 1, reference = "acme-p1")))
        deliveries.lookUpScan("acme-p1")
        assertEquals(StockScanOutcome.NoMatch("acme-p1"), deliveries.state.value.scanOutcome)

        deliveries.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Unreachable
        deliveries.lookUpScan("acme-099")
        assertEquals(StockScanOutcome.Failed("acme-099", ScanProblem.Offline), deliveries.state.value.scanOutcome)
    }

    @Test
    fun `an expired session asks to sign in again`() {
        api.deliveryPages = { GoodsOutResult.SessionExpired }
        val deliveries = viewModel()

        deliveries.onStart()

        assertTrue(deliveries.state.value.isSessionExpired)
    }

    private companion object {
        const val SEARCH_WAIT_MILLIS = 301L
    }

    @Test
    fun `the booked-in range and the sort reach the query and reload from page 1`() {
        val deliveries = viewModel()
        deliveries.onStart()
        deliveries.loadMore()
        val range = DateRange(from = LocalDate.of(2026, 11, 18))

        deliveries.applyFilter(ListFilter(dates = range, sort = PalletDeliverySort.BookedIn))

        assertEquals(
            PalletDeliveryQuery(from = LocalDate.of(2026, 11, 18), sort = PalletDeliverySort.BookedIn, perPage = 50),
            api.deliveryQueries.last(),
        )
        assertEquals(listOf(1L, 2L), deliveries.state.value.list.rows.map { it.id })
        assertEquals(2, deliveries.state.value.filter.activeCount)

        deliveries.clearFilters()

        assertEquals(PalletDeliveryQuery(perPage = 50), api.deliveryQueries.last())
    }
}
