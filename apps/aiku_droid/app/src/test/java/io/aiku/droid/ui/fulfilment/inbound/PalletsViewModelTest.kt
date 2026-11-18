package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.PalletFilter
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.data.api.PalletPage
import io.aiku.droid.data.api.PalletQuery
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedPallet
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.navigation.PalletRoute
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PalletsViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeFulfilmentInboundApi()
    private val counts = StateCounts(mapOf("storing" to 642, "incidents" to 3))
    private val incidentCounts = StateCounts(mapOf("damaged" to 1, "lost" to 1, "other_incident" to 1))

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
        api.palletPages = { query -> GoodsOutResult.Loaded(pageFor(query)) }
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun pageFor(query: PalletQuery): PalletPage {
        val ids = if (query.page == 1) 1L..2L else 3L..4L
        return PalletPage(
            rows = ids.map { testPallet(it, state = "storing", status = "storing") },
            meta = ListMeta(currentPage = query.page, lastPage = 2, perPage = 50, total = 4),
            counts = counts,
            incidentCounts = incidentCounts,
        )
    }

    private fun viewModel(filter: PalletFilter = PalletFilter.Storing) = PalletsViewModel(api, FakeSessions(), warehouseId = 1, initialFilter = filter, perPage = 50)

    @Test
    fun `stored pallets open on storing with both count groups`() {
        val pallets = viewModel()

        pallets.onStart()

        assertEquals(PalletQuery(page = 1, perPage = 50), api.palletQueries.single())
        assertEquals(642, pallets.state.value.counts?.count(PalletFilter.Storing.key))
        assertEquals(1, pallets.state.value.incidentCounts?.count(PalletIncidentState.Lost.key))
    }

    @Test
    fun `paging appends and a filter chip reloads from the first page without an incident state`() {
        val pallets = viewModel()
        pallets.onStart()
        pallets.loadMore()

        assertEquals(listOf(1L, 2L, 3L, 4L), pallets.state.value.list.rows.map { it.id })
        assertFalse(pallets.state.value.list.hasMore)

        pallets.selectFilter(PalletFilter.Returning)

        val query = api.palletQueries.last()
        assertEquals(PalletFilter.Returning, query.filter)
        assertNull(query.incidentState)
        assertEquals(1, query.page)
    }

    @Test
    fun `the incidents list filters by incident state and back to all`() {
        val incidents = viewModel(PalletFilter.Incidents)
        incidents.onStart()

        incidents.selectIncidentState(PalletIncidentState.OtherIncident)
        assertEquals(PalletIncidentState.OtherIncident, api.palletQueries.last().incidentState)
        assertEquals(PalletFilter.Incidents, api.palletQueries.last().filter)

        incidents.selectIncidentState(null)
        assertNull(api.palletQueries.last().incidentState)
        assertEquals(3, api.palletQueries.size)
    }

    @Test
    fun `searching waits for typing to pause`() {
        val pallets = viewModel()
        pallets.onStart()

        pallets.search("D-0")
        pallets.search("D-02")
        dispatcher.scheduler.advanceTimeBy(SEARCH_WAIT_MILLIS)

        assertEquals(listOf(null, "D-02"), api.palletQueries.map { it.search })
    }

    @Test
    fun `a scanned pallet label opens the pallet page`() {
        val pallets = viewModel()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "acme-p0231", type = ScanMatch.PALLET, pallet = ScannedPallet(id = 231, reference = "acme-p0231")))

        pallets.lookUpScan("acme-p0231")
        assertEquals(StockScanOutcome.Open(PalletRoute(id = 231, reference = "acme-p0231")), pallets.state.value.scanOutcome)

        pallets.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "D-02-01", type = ScanMatch.LOCATION))
        pallets.lookUpScan("D-02-01")
        assertEquals(StockScanOutcome.NoMatch("D-02-01"), pallets.state.value.scanOutcome)
    }

    private companion object {
        const val SEARCH_WAIT_MILLIS = 301L
    }
}
