package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.OutboundNamedRef
import io.aiku.droid.data.api.PalletReturnPage
import io.aiku.droid.data.api.PalletReturnQuery
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnSort
import io.aiku.droid.data.api.PalletReturnStateFilter
import io.aiku.droid.data.api.PalletReturnTypeFilter
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.SavedLists
import io.aiku.droid.data.cache.testScreenCache
import io.aiku.droid.ui.components.ListFilter
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
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PalletReturnsSavedRowsTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeFulfilmentOutboundApi()
    private val cache = testScreenCache()
    private val rows = listOf(
        PalletReturnRow(id = 1, reference = "PR-10", customerReference = "PO-55", typeGroup = "pallets", state = "confirmed", customer = OutboundNamedRef(name = "Zara"), date = "2026-11-20T10:00:00+00:00"),
        PalletReturnRow(id = 2, reference = "PR-20", typeGroup = "items", state = "picking", customer = OutboundNamedRef(name = "anna"), date = "2026-11-22T10:00:00+00:00"),
        PalletReturnRow(id = 3, reference = "PR-30", typeGroup = "pallets", state = "dispatched", customer = OutboundNamedRef(name = "Bob"), date = "2026-11-21T10:00:00+00:00"),
        PalletReturnRow(id = 4, reference = "PR-40", typeGroup = "items", state = "picked"),
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
    fun `state, type and search filter the saved rows, newest first`() {
        assertEquals(listOf(2L, 1L, 4L), ids(PalletReturnQuery()))
        assertEquals(listOf(3L), ids(PalletReturnQuery(state = PalletReturnStateFilter.Dispatched)))
        assertEquals(listOf(1L), ids(PalletReturnQuery(type = PalletReturnTypeFilter.Pallets)))
        assertEquals(listOf(1L), ids(PalletReturnQuery(search = "po-55")))
        assertEquals(listOf(2L), ids(PalletReturnQuery(search = "ANN")))
    }

    @Test
    fun `each sort mirrors the server and the dispatched sort, which the rows cannot show, keeps the default order`() {
        assertEquals(listOf(1L, 2L, 4L), ids(PalletReturnQuery(sort = PalletReturnSort.Oldest)))
        assertEquals(listOf(1L, 2L, 4L), ids(PalletReturnQuery(sort = PalletReturnSort.Reference)))
        assertEquals(listOf(2L, 1L, 4L), ids(PalletReturnQuery(sort = PalletReturnSort.Customer)))
        assertEquals(listOf(2L, 1L, 4L), ids(PalletReturnQuery(sort = PalletReturnSort.Dispatched)))
        assertTrue(PalletReturnSort.Dispatched in onlineOnlyPalletReturnSorts)
    }

    @Test
    fun `offline the list uses the saved rows, filtered and sorted on the phone`() = runTest {
        api.returnPages = { GoodsOutResult.Loaded(PalletReturnPage(rows, ListMeta(currentPage = 1, lastPage = 4, total = 200))) }
        val returns = PalletReturnsViewModel(api, FakeSessions(), warehouseId = 1, cache = cache)
        returns.onStart()
        assertEquals(4, cache.saved(1, SavedLists.palletReturns)?.value?.size)

        api.returnPages = { GoodsOutResult.Unreachable }
        returns.selectType(PalletReturnTypeFilter.Items)
        returns.applyFilter(ListFilter(sort = PalletReturnSort.Reference))

        val offline = returns.state.value
        assertEquals(listOf(2L, 4L), offline.list.rows.map { it.id })
        assertEquals(CACHE_NOW, offline.lastUpdated)
        assertEquals(GoodsOutProblem.Offline, offline.list.firstPageProblem)
        assertFalse(offline.list.canLoadMore)
        assertEquals(2, offline.offlineTotalFor(ListFilter(sort = PalletReturnSort.Customer)))

        api.returnPages = { GoodsOutResult.Loaded(PalletReturnPage(listOf(rows[1]), ListMeta(total = 1))) }
        returns.retry()

        assertNull(returns.state.value.offline)
        assertNull(returns.state.value.offlineTotalFor(ListFilter()))
        assertEquals(listOf(2L), returns.state.value.list.rows.map { it.id })
    }

    private fun ids(query: PalletReturnQuery): List<Long> = offlinePalletReturns(rows, query).map { it.id }
}
