package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.FamilyPage
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OrgStockFamily
import io.aiku.droid.data.api.OrgStockPage
import io.aiku.droid.data.api.OrgStockRow
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedLocation
import io.aiku.droid.data.api.ScannedSko
import io.aiku.droid.data.api.SkoBucket
import io.aiku.droid.data.api.SkoCounts
import io.aiku.droid.data.api.SkoQuery
import io.aiku.droid.data.api.SkoSort
import io.aiku.droid.ui.navigation.SkuRoute
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
class SkosViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeInventoryApi()
    private val counts = SkoCounts(current = 2345, discontinued = 120, abnormality = 3, all = 2468)

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
        api.skoPages = { query -> GoodsOutResult.Loaded(pageFor(query)) }
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun rows(ids: LongRange) = ids.map { OrgStockRow(id = it, code = "SKO-$it") }

    private fun pageFor(query: SkoQuery): OrgStockPage = when {
        query.perPage == 1 -> OrgStockPage(emptyList(), meta(1, 1, 42), counts)
        query.page == 1 -> OrgStockPage(rows(1L..2L), meta(1, 2, 4), counts, currencyCode = "GBP")
        else -> OrgStockPage(rows(3L..4L), meta(2, 2, 4), counts)
    }

    private fun viewModel(bucket: SkoBucket = SkoBucket.Current) = SkosViewModel(api, FakeSessions(), warehouseId = 1, initialBucket = bucket, perPage = 50)

    @Test
    fun `opening loads the current SKOs with the bucket counts and the currency`() {
        val skos = viewModel()

        skos.onStart()

        val state = skos.state.value
        assertEquals(listOf(1L, 2L), state.list.rows.map { it.id })
        assertEquals(3, state.counts?.count(SkoBucket.Abnormality))
        assertEquals("GBP", state.currencyCode)
        assertEquals(SkoQuery(bucket = SkoBucket.Current, page = 1, perPage = 50), api.skoQueries.single())
    }

    @Test
    fun `paging adds the next page and keeps the counts`() {
        val skos = viewModel()
        skos.onStart()

        skos.loadMore()

        assertEquals(listOf(1L, 2L, 3L, 4L), skos.state.value.list.rows.map { it.id })
        assertFalse(skos.state.value.list.hasMore)
        assertEquals(2468, skos.state.value.counts?.count(SkoBucket.All))
    }

    @Test
    fun `the abnormalities tile opens the list on that bucket`() {
        viewModel(bucket = SkoBucket.Abnormality).onStart()

        assertEquals(SkoBucket.Abnormality, api.skoQueries.single().bucket)
    }

    @Test
    fun `bucket, family, sort and search go to the server`() {
        val skos = viewModel()
        skos.onStart()

        skos.selectBucket(SkoBucket.All)
        skos.applyFilter(skos.state.value.filter.copy(familyId = 5, sort = SkoSort.StockValue))
        skos.search("awo")
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(SkoQuery(bucket = SkoBucket.All, familyId = 5, search = "awo", sort = SkoSort.StockValue, page = 1, perPage = 50), api.skoQueries.last())
        assertEquals(1, skos.state.value.filter.activeFilterCount)

        skos.clearFilters()
        assertEquals(SkoFilter(bucket = SkoBucket.All, search = "awo"), skos.state.value.filter)
    }

    @Test
    fun `Show N comes from a one row request`() {
        val skos = viewModel()
        skos.onStart()
        val draft = SkoFilter(familyId = 5)

        skos.previewTotal(draft)
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(42, skos.state.value.totalFor(draft))
    }

    @Test
    fun `a scanned SKO opens by id and a location is not an SKO`() {
        val skos = viewModel()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "5060", type = ScanMatch.SKO, sko = ScannedSko(id = 812, code = "AWO-12")))

        skos.lookUpScan("5060")
        assertEquals(StockScanOutcome.Open(SkuRoute(id = 812, code = "AWO-12")), skos.state.value.scanOutcome)

        skos.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "a-01", type = ScanMatch.LOCATION, location = ScannedLocation(id = 1, code = "A-01")))
        skos.lookUpScan("a-01")
        assertEquals(StockScanOutcome.NoMatch("a-01"), skos.state.value.scanOutcome)
    }

    @Test
    fun `the family filter reads every page of active families`() {
        api.familyPages = { query ->
            val family = OrgStockFamily(id = query.page.toLong(), code = "F${query.page}")
            GoodsOutResult.Loaded(FamilyPage(listOf(family), meta(query.page, 2, 2)))
        }
        val skos = viewModel()

        skos.loadFamilies()

        assertEquals(listOf("F1", "F2"), skos.state.value.families?.map { it.code })
        assertTrue(api.familyQueries.all { it.perPage == 150 })
    }
}
