package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.CustomerRef
import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.ReturnPage
import io.aiku.droid.data.api.ReturnQuery
import io.aiku.droid.data.api.ReturnSort
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.SavedLists
import io.aiku.droid.data.cache.TestClock
import io.aiku.droid.data.cache.testScreenCache
import io.aiku.droid.ui.components.DateRange
import io.aiku.droid.ui.components.ListFilter
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
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
import java.time.Duration
import java.time.LocalDate
import java.time.ZoneId

@OptIn(ExperimentalCoroutinesApi::class)
class ReturnsSavedRowsTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeGoodsInApi()
    private val clock = TestClock()
    private val cache = testScreenCache(clock = clock)
    private val madrid = ZoneId.of("Europe/Madrid")
    private val rows = listOf(
        CustomerReturnRow(id = 1, reference = "RET-100", state = "received", customer = CustomerRef("Bakery Ltd"), createdAt = "2026-11-20T23:30:00+00:00"),
        CustomerReturnRow(id = 2, reference = "RET-200", state = "returning", customer = CustomerRef("alpha shop"), createdAt = "2026-11-18T10:00:00+00:00"),
        CustomerReturnRow(id = 3, reference = "RET-300", state = "done", customer = CustomerRef("Zed"), createdAt = "2026-11-21T09:00:00+00:00"),
        CustomerReturnRow(id = 4, reference = "RET-400", state = "received"),
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
    fun `the to process chip keeps received and returning, newest received first with undated rows last`() {
        assertEquals(listOf(1L, 2L, 4L), offlineReturns(rows, ReturnQuery(state = ReturnStateFilter.ToProcess), madrid).map { it.id })
        assertEquals(listOf(3L), offlineReturns(rows, ReturnQuery(state = ReturnStateFilter.Done), madrid).map { it.id })
    }

    @Test
    fun `search looks inside the reference only, ignoring case, as Aiku does`() {
        assertEquals(emptyList<Long>(), offlineReturns(rows, ReturnQuery(search = "bakery"), madrid).map { it.id })
        assertEquals(listOf(1L), offlineReturns(rows, ReturnQuery(search = "ret-1"), madrid).map { it.id })
        assertEquals(listOf(3L), offlineReturns(rows, ReturnQuery(search = "t-3"), madrid).map { it.id })
    }

    @Test
    fun `the date received range is read in the organisation zone and drops undated rows`() {
        val day = LocalDate.of(2026, 11, 21)

        assertEquals(listOf(3L, 1L), offlineReturns(rows, ReturnQuery(from = day, to = day), madrid).map { it.id })
        assertEquals(listOf(3L), offlineReturns(rows, ReturnQuery(from = day, to = day), ZoneId.of("UTC")).map { it.id })
        assertEquals(listOf(1L, 2L), offlineReturns(rows, ReturnQuery(to = LocalDate.of(2026, 11, 20)), ZoneId.of("UTC")).map { it.id })
    }

    @Test
    fun `each sort mirrors the server`() {
        assertEquals(listOf(2L, 1L, 3L, 4L), offlineReturns(rows, ReturnQuery(sort = ReturnSort.OldestReceived), madrid).map { it.id })
        assertEquals(listOf(1L, 2L, 3L, 4L), offlineReturns(rows, ReturnQuery(sort = ReturnSort.Reference), madrid).map { it.id })
        assertEquals(listOf(2L, 1L, 3L, 4L), offlineReturns(rows, ReturnQuery(sort = ReturnSort.Customer), madrid).map { it.id })
    }

    @Test
    fun `offline the list shows the saved rows, filtered and sorted on the phone, until the server answers again`() = runTest {
        api.returnPages = { GoodsOutResult.Loaded(ReturnPage(rows, ListMeta(currentPage = 1, lastPage = 3, total = 120))) }
        val returns = viewModel()
        returns.onStart()
        assertEquals(4, cache.saved(1, SavedLists.returns)?.value?.size)

        clock.now = CACHE_NOW + Duration.ofHours(2)
        api.returnPages = { GoodsOutResult.Unreachable }
        returns.applyFilter(ListFilter(sort = ReturnSort.Customer))

        val offline = returns.state.value
        assertEquals(listOf(2L, 1L, 3L, 4L), offline.list.rows.map { it.id })
        assertEquals(CACHE_NOW, offline.lastUpdated)
        assertEquals(GoodsOutProblem.Offline, offline.list.firstPageProblem)
        assertEquals(4, offline.list.total)
        assertFalse(offline.list.canLoadMore)
        assertEquals(2, offline.offlineTotalFor(ListFilter(dates = DateRange(LocalDate.of(2026, 11, 21), LocalDate.of(2026, 11, 21)))))

        returns.selectState(ReturnStateFilter.ToProcess)
        returns.search("t-1")
        dispatcher.scheduler.advanceTimeBy(SEARCH_DEBOUNCE_MILLIS + 1)

        assertEquals(listOf(1L), returns.state.value.list.rows.map { it.id })

        api.returnPages = { GoodsOutResult.Loaded(ReturnPage(listOf(rows[0]), ListMeta(total = 1))) }
        returns.refresh()

        assertNull(returns.state.value.offline)
        assertNull(returns.state.value.lastUpdated)
        assertNull(returns.state.value.list.problem)
        assertNull(returns.state.value.offlineTotalFor(ListFilter()))
    }

    @Test
    fun `without saved rows, or when the server answers with an error, the usual problem shows`() {
        api.returnPages = { GoodsOutResult.Unreachable }
        val returns = viewModel()
        returns.onStart()

        assertEquals(GoodsOutProblem.Offline, returns.state.value.list.firstPageProblem)
        assertEquals(emptyList<CustomerReturnRow>(), returns.state.value.list.rows)
        assertNull(returns.state.value.offline)

        api.returnPages = { GoodsOutResult.Loaded(ReturnPage(rows, ListMeta(total = 4))) }
        returns.refresh()
        api.returnPages = { GoodsOutResult.ServerError(500) }
        returns.refresh()

        assertEquals(GoodsOutProblem.ServerError, returns.state.value.list.firstPageProblem)
        assertNull(returns.state.value.offline)
    }

    private fun viewModel() = ReturnsViewModel(api, FakeSessions(), warehouseId = 1, cache = cache, zone = { madrid })
}
