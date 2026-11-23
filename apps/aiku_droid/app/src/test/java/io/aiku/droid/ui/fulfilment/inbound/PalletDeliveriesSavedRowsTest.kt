package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.FulfilmentPerson
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.PalletDeliveryPage
import io.aiku.droid.data.api.PalletDeliveryQuery
import io.aiku.droid.data.api.PalletDeliveryRow
import io.aiku.droid.data.api.PalletDeliverySort
import io.aiku.droid.data.api.PalletDeliveryStateFilter
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.SavedLists
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
import java.time.LocalDate
import java.time.ZoneId

@OptIn(ExperimentalCoroutinesApi::class)
class PalletDeliveriesSavedRowsTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeFulfilmentInboundApi()
    private val cache = testScreenCache()
    private val kathmandu = ZoneId.of("Asia/Kathmandu")
    private val rows = listOf(
        PalletDeliveryRow(id = 1, reference = "PD-1", customerReference = "CR-9", state = "confirmed", customer = FulfilmentPerson(name = "Zeta"), estimatedDeliveryDate = "2026-11-25"),
        PalletDeliveryRow(id = 2, reference = "PD-2", state = "received", customer = FulfilmentPerson(name = "alpha"), estimatedDeliveryDate = "2026-11-24"),
        PalletDeliveryRow(id = 3, reference = "PD-3", state = "booked_in", customer = FulfilmentPerson(name = "Mid"), bookedInAt = "2026-11-21T20:00:00+00:00"),
        PalletDeliveryRow(id = 4, reference = "PD-4", state = "booked_in", bookedInAt = "2026-11-20T10:00:00+00:00"),
        PalletDeliveryRow(id = 5, reference = "PD-5", state = "booking_in"),
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
    fun `the active chip keeps confirmed, received and booking in, by expected date with undated rows last`() {
        assertEquals(listOf(2L, 1L, 5L), ids(PalletDeliveryQuery()))
        assertEquals(listOf(4L, 3L), ids(PalletDeliveryQuery(state = PalletDeliveryStateFilter.BookedIn)))
    }

    @Test
    fun `search, the booked in range in the organisation zone and the sorts`() {
        assertEquals(listOf(1L), ids(PalletDeliveryQuery(search = "cr-9")))
        assertEquals(listOf(2L), ids(PalletDeliveryQuery(search = "ALP")))
        val day = LocalDate.of(2026, 11, 22)
        assertEquals(listOf(3L), ids(PalletDeliveryQuery(state = PalletDeliveryStateFilter.BookedIn, from = day, to = day)))
        assertEquals(listOf(3L, 4L), ids(PalletDeliveryQuery(state = PalletDeliveryStateFilter.BookedIn, sort = PalletDeliverySort.BookedIn)))
        assertEquals(listOf(1L, 2L, 5L), ids(PalletDeliveryQuery(sort = PalletDeliverySort.Reference)))
    }

    @Test
    fun `offline the list uses the saved rows and pull to refresh goes back to the server`() = runTest {
        api.deliveryPages = { GoodsOutResult.Loaded(PalletDeliveryPage(rows, ListMeta(currentPage = 1, lastPage = 3, total = 140))) }
        val deliveries = PalletDeliveriesViewModel(api, FakeSessions(), warehouseId = 1, cache = cache, zone = { kathmandu })
        deliveries.onStart()
        assertEquals(5, cache.saved(1, SavedLists.palletDeliveries)?.value?.size)

        api.deliveryPages = { GoodsOutResult.Unreachable }
        deliveries.selectState(PalletDeliveryStateFilter.BookedIn)
        deliveries.applyFilter(ListFilter(dates = DateRange(from = LocalDate.of(2026, 11, 22))))

        val offline = deliveries.state.value
        assertEquals(listOf(3L), offline.list.rows.map { it.id })
        assertEquals(CACHE_NOW, offline.lastUpdated)
        assertEquals(GoodsOutProblem.Offline, offline.list.firstPageProblem)
        assertFalse(offline.list.canLoadMore)
        assertEquals(2, offline.offlineTotalFor(ListFilter()))

        deliveries.search("pd-4")
        dispatcher.scheduler.advanceTimeBy(SEARCH_DEBOUNCE_MILLIS + 1)
        assertEquals(emptyList<Long>(), deliveries.state.value.list.rows.map { it.id })
        assertEquals(true, deliveries.state.value.list.isEmpty)

        api.deliveryPages = { GoodsOutResult.Loaded(PalletDeliveryPage(listOf(rows[3]), ListMeta(total = 1))) }
        deliveries.refresh()

        assertNull(deliveries.state.value.offline)
        assertEquals(listOf(4L), deliveries.state.value.list.rows.map { it.id })
    }

    private fun ids(query: PalletDeliveryQuery): List<Long> = offlinePalletDeliveries(rows, query, kathmandu).map { it.id }
}
