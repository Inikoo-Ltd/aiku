package io.aiku.droid.ui.locations

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.LocationOccupancy
import io.aiku.droid.data.api.LocationPage
import io.aiku.droid.data.api.LocationQuery
import io.aiku.droid.data.api.LocationScope
import io.aiku.droid.data.api.LocationSort
import io.aiku.droid.data.api.LocationStatusFilter
import io.aiku.droid.data.api.LocationTab
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedLocation
import io.aiku.droid.data.api.ScannedSko
import io.aiku.droid.ui.inventory.FakeInventoryApi
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.inventory.locationSummaries
import io.aiku.droid.ui.inventory.meta
import io.aiku.droid.ui.navigation.LocationRoute
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
class LocationsViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeInventoryApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
        api.locationPages = { query -> GoodsOutResult.Loaded(pageFor(query)) }
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun pageFor(query: LocationQuery): LocationPage = when {
        query.perPage == 1 -> LocationPage(emptyList(), meta(1, 1, tabTotals.getValue(query.tab)))
        query.page == 1 -> LocationPage(locationSummaries(1L..2L), meta(1, 2, 4))
        else -> LocationPage(locationSummaries(3L..4L), meta(2, 2, 4))
    }

    private val tabTotals = mapOf(LocationTab.All to 1284, LocationTab.Empty to 180, LocationTab.PartiallyEmpty to 96)

    private fun viewModel(tab: LocationTab = LocationTab.All, areaId: Long? = null) =
        LocationsViewModel(api, FakeSessions(), warehouseId = 1, initialTab = tab, areaId = areaId, perPage = 50)

    @Test
    fun `pulling to refresh reloads the first page and the tab counts`() {
        val locations = viewModel()
        locations.onStart()
        locations.loadMore()
        val before = api.locationQueries.size

        locations.refresh()

        val asked = api.locationQueries.drop(before)
        assertTrue(asked.any { it.page == 1 && it.perPage == 50 })
        assertTrue(asked.any { it.perPage == 1 })
        assertEquals(listOf(1L, 2L), locations.state.value.list.rows.map { it.id })
        assertFalse(locations.state.value.list.isRefreshing)
    }

    @Test
    fun `opening loads the first page from meta and a count for every tab`() {
        val locations = viewModel()

        locations.onStart()

        val state = locations.state.value
        assertEquals(listOf(1L, 2L), state.list.rows.map { it.id })
        assertTrue(state.list.hasMore)
        assertEquals(4, state.countOf(LocationTab.All))
        assertEquals(180, state.countOf(LocationTab.Empty))
        assertEquals(96, state.countOf(LocationTab.PartiallyEmpty))
        assertEquals(LocationQuery(page = 1, perPage = 50), api.locationQueries.first())
    }

    @Test
    fun `more pages are added to the rows`() {
        val locations = viewModel()
        locations.onStart()

        locations.loadMore()

        assertEquals(listOf(1L, 2L, 3L, 4L), locations.state.value.list.rows.map { it.id })
        assertFalse(locations.state.value.list.hasMore)
        assertEquals(2, api.locationQueries.last().page)
    }

    @Test
    fun `the Empty tab from the overview starts on the empty locations`() {
        viewModel(tab = LocationTab.Empty).onStart()

        assertEquals(LocationTab.Empty, api.locationQueries.first().tab)
    }

    @Test
    fun `tabs and filters go to the server as the endpoint takes them`() {
        val locations = viewModel()
        locations.onStart()

        locations.selectTab(LocationTab.PartiallyEmpty)
        locations.applyFilter(
            locations.state.value.filter.copy(
                areaIds = setOf(3),
                statuses = setOf(LocationStatusFilter.Operational),
                scopes = setOf(LocationScope.Fulfilment),
                occupancies = setOf(LocationOccupancy.GoodsOut),
                sort = LocationSort.Weight,
            ),
        )

        val listQuery = api.locationQueries.last { it.perPage == 50 }
        assertEquals(LocationTab.PartiallyEmpty, listQuery.tab)
        assertEquals(setOf(3L), listQuery.areaIds)
        assertEquals(setOf(LocationStatusFilter.Operational), listQuery.statuses)
        assertEquals(setOf(LocationScope.Fulfilment), listQuery.scopes)
        assertEquals(setOf(LocationOccupancy.GoodsOut), listQuery.occupancies)
        assertEquals(LocationSort.Weight, listQuery.sort)
        assertEquals(4, locations.state.value.filter.activeFilterCount)
    }

    @Test
    fun `clearing filters keeps the tab and the search`() {
        val locations = viewModel()
        locations.onStart()
        locations.applyFilter(LocationFilter(tab = LocationTab.Empty, search = "A", areaIds = setOf(3), sort = LocationSort.Code))

        locations.clearFilters()

        assertEquals(LocationFilter(tab = LocationTab.Empty, search = "A"), locations.state.value.filter)
    }

    @Test
    fun `the search waits for typing to stop`() {
        val locations = viewModel()
        locations.onStart()
        val before = api.locationQueries.size

        locations.search("a-0")
        assertEquals(before, api.locationQueries.size)
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals("a-0", api.locationQueries.last { it.perPage == 50 }.search)
    }

    @Test
    fun `Show N comes from the total of a one row request`() {
        val locations = viewModel()
        locations.onStart()
        val draft = LocationFilter(tab = LocationTab.Empty, scopes = setOf(LocationScope.Stocks))

        locations.previewTotal(draft)
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(180, locations.state.value.totalFor(draft))
        assertEquals(4, locations.state.value.totalFor(locations.state.value.filter))
    }

    @Test
    fun `a scanned location opens by id and anything else is not a location`() {
        val locations = viewModel()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "a-01-03", type = ScanMatch.LOCATION, location = ScannedLocation(id = 220, code = "A-01-03")))

        locations.lookUpScan(" a-01-03 ")

        assertEquals(StockScanOutcome.Open(LocationRoute(id = 220, code = "A-01-03")), locations.state.value.scanOutcome)
        assertEquals("a-01-03", api.scans.single())

        locations.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "5060", type = ScanMatch.SKO, sko = ScannedSko(id = 77, code = "AWO-12")))
        locations.lookUpScan("5060")

        assertEquals(StockScanOutcome.NoMatch("5060"), locations.state.value.scanOutcome)
        assertNull(locations.state.value.lookingUpCode)
    }

    @Test
    fun `an area pages its own locations without tab counts`() {
        val locations = viewModel(areaId = 3)

        locations.onStart()
        locations.loadMore()

        assertTrue(api.locationQueries.all { it.areaId == 3L && it.perPage == 50 })
        assertTrue(locations.state.value.tabCounts.isEmpty())
    }

    @Test
    fun `a failed first page can be retried`() {
        api.locationPages = { GoodsOutResult.Unreachable }
        val locations = viewModel()
        locations.onStart()
        assertTrue(locations.state.value.list.firstPageProblem != null)

        api.locationPages = { query -> GoodsOutResult.Loaded(pageFor(query)) }
        locations.retry()

        assertEquals(2, locations.state.value.list.rows.size)
    }
}
