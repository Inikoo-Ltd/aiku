package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.OutboundPalletRef
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.data.api.StoredItemDetail
import io.aiku.droid.data.api.StoredItemPage
import io.aiku.droid.data.api.StoredItemPalletRow
import io.aiku.droid.data.api.StoredItemQuery
import io.aiku.droid.data.api.StoredItemRow
import io.aiku.droid.data.api.StoredItemStateFilter
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
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class StoredItemsViewModelTest {

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
    fun `stored items open on active, page and filter by state and search`() {
        api.storedItemPages = { query ->
            val ids = if (query.page == 1) 1L..2L else 3L..3L
            GoodsOutResult.Loaded(
                StoredItemPage(
                    rows = ids.map { StoredItemRow(id = it, reference = "SI-$it") },
                    meta = ListMeta(currentPage = query.page, lastPage = 2, perPage = 2, total = 3),
                    counts = StateCounts(mapOf("active" to 3, "all" to 5)),
                ),
            )
        }
        val items = StoredItemsViewModel(api, FakeSessions(), warehouseId = 1, perPage = 2)

        items.onStart()
        items.loadMore()

        assertEquals(listOf(1L, 2L, 3L), items.state.value.list.rows.map { it.id })
        assertEquals(StoredItemQuery(perPage = 2), api.storedItemQueries.first())
        assertEquals(5, items.state.value.counts?.count(StoredItemStateFilter.All.key))

        items.selectState(StoredItemStateFilter.Discontinuing)
        items.search("mug")
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(StoredItemQuery(state = StoredItemStateFilter.Discontinuing, search = "mug", perPage = 2), api.storedItemQueries.last())
    }

    @Test
    fun `pulling to refresh reloads the first page of stored items`() {
        api.storedItemPages = { query -> GoodsOutResult.Loaded(StoredItemPage(rows = listOf(StoredItemRow(id = 1, reference = "SI-1")), meta = ListMeta(currentPage = query.page))) }
        val items = StoredItemsViewModel(api, FakeSessions(), warehouseId = 1)
        items.onStart()
        api.storedItemPages = { query -> GoodsOutResult.Loaded(StoredItemPage(rows = listOf(StoredItemRow(id = 4, reference = "SI-4")), meta = ListMeta(currentPage = query.page))) }

        items.refresh()

        assertEquals(listOf(4L), items.state.value.list.rows.map { it.id })
        assertEquals(2, api.storedItemQueries.size)
        assertFalse(items.state.value.list.isRefreshing)
    }

    @Test
    fun `tapping an item loads its pallets in this warehouse`() {
        val row = StoredItemRow(id = 4, reference = "MUG-BLUE")
        api.storedItem = GoodsOutResult.Loaded(
            StoredItemDetail(id = 4, reference = "MUG-BLUE", pallets = listOf(StoredItemPalletRow(pallet = OutboundPalletRef(id = 10, reference = "acme-p0140"), quantity = 40))),
        )
        val items = StoredItemsViewModel(api, FakeSessions(), warehouseId = 1)

        items.open(row)

        assertEquals(listOf(4L), api.openedStoredItems)
        assertEquals("acme-p0140", items.state.value.opened?.detail?.pallets?.single()?.pallet?.reference)

        items.close()

        assertNull(items.state.value.opened)
    }

    @Test
    fun `a failed item can be retried`() {
        api.storedItem = GoodsOutResult.Unreachable
        val items = StoredItemsViewModel(api, FakeSessions(), warehouseId = 1)

        items.open(StoredItemRow(id = 4))

        assertEquals(GoodsOutProblem.Offline, items.state.value.opened?.problem)

        api.storedItem = GoodsOutResult.Loaded(StoredItemDetail(id = 4))
        items.retryDetail()

        assertNull(items.state.value.opened?.problem)
        assertEquals(4L, items.state.value.opened?.detail?.id)
    }
}
