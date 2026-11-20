package io.aiku.droid.ui.inventory

import io.aiku.droid.ui.sync.FakeOfflineWork
import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationOrgStockUpdate
import io.aiku.droid.data.api.LocationPage
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.data.api.MovementPage
import io.aiku.droid.data.api.MoveTarget
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockLocation
import io.aiku.droid.data.api.OrgStockLocationPlace
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.data.api.OrgStockWriteOutcome
import io.aiku.droid.data.api.PickingChannel
import io.aiku.droid.data.api.StockRights
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.ui.goodsout.GoodsOutProblem
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
class SkoViewModelTest {

    private val api = FakeInventoryApi()

    private fun link(id: Long, locationId: Long, quantity: Double, isDefaultWholesale: Boolean = false) = OrgStockLocation(
        locationOrgStockId = id,
        type = if (isDefaultWholesale) LocationStockType.Picking.key else LocationStockType.Storing.key,
        quantity = quantity,
        isDefaultWholesale = isDefaultWholesale,
        location = OrgStockLocationPlace(id = locationId, code = "L-$locationId"),
    )

    private val detail = OrgStockDetail(
        id = 77,
        code = "AWO-12",
        quantityInLocations = 36.0,
        locations = listOf(link(5501, 220, 30.0, isDefaultWholesale = true), link(5502, 221, 6.0)),
        can = StockRights(editStocks = true),
    )

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun loaded(): SkoViewModel {
        api.orgStock = GoodsOutResult.Loaded(detail)
        api.movementPages = { GoodsOutResult.Loaded(MovementPage(movements(1L..12L), meta(1, 3, 120))) }
        return SkoViewModel(api, FakeSessions(), warehouseId = 1, orgStockId = 77, offlineWork = FakeOfflineWork()).also { it.onStart() }
    }

    @Test
    fun `pulling to refresh loads the SKO again`() {
        val sko = loaded()
        api.orgStock = GoodsOutResult.Loaded(detail.copy(quantityInLocations = 40.0))

        sko.refresh()

        assertEquals(listOf(77L, 77L), api.orgStockRequests)
        assertEquals(40.0, sko.state.value.orgStock?.quantityInLocations ?: 0.0, 0.0)
        assertFalse(sko.state.value.isRefreshing)
    }

    @Test
    fun `opening loads the SKO and its latest movements`() {
        val sko = loaded()

        val state = sko.state.value
        assertEquals(77L, state.orgStock?.id)
        assertEquals(RECENT_MOVEMENTS, state.movements?.size)
        assertEquals(MovementRequest(orgStockId = 77, locationId = null, flow = null, page = 1), api.movementRequests.single())
    }

    @Test
    fun `every stock write replaces the SKO and carries the server message`() {
        val writes = listOf(
            OrgStockWrite.Audit(listOf(AuditCount(locationOrgStockId = 5501, quantity = 28.0, reason = "damaged", note = null))),
            OrgStockWrite.Move(fromLocationOrgStockId = 5501, targets = listOf(MoveTarget(locationOrgStockId = 5502, quantity = 2.0))),
            OrgStockWrite.Consume(locationOrgStockId = 5501, quantity = 1.0, note = "Showroom"),
            OrgStockWrite.AddLocation(locationId = 300, type = LocationStockType.Storing),
            OrgStockWrite.RemoveLocations(listOf(5502)),
        )
        val sko = loaded()

        writes.forEachIndexed { index, write ->
            val answer = detail.copy(quantityInLocations = index.toDouble())
            api.orgStockWriteResult = GoodsOutResult.Loaded(OrgStockWriteOutcome(orgStock = answer, status = "done", message = "Done $index"))

            sko.write(write)

            val state = sko.state.value
            assertEquals(index.toDouble(), state.orgStock?.quantityInLocations ?: -1.0, 0.0)
            assertEquals(StockEvent.Done(action = api.orgStockWrites.last(), message = "Done $index"), state.event)
            assertFalse(state.isWorking)
            sko.eventHandled()
        }
        assertEquals(
            listOf(
                (writes[0] as OrgStockWrite.Audit).copy(clientRequestId = "req-1"),
                (writes[1] as OrgStockWrite.Move).copy(clientRequestId = "req-2"),
            ) + writes.drop(2),
            api.orgStockWrites,
        )
        assertEquals(1 + writes.size, api.movementRequests.size)
    }

    @Test
    fun `a refused write keeps the SKO and the error keys`() {
        val sko = loaded()
        api.orgStockWriteResult = GoodsOutResult.Invalid("This SKO is already in that location", mapOf("location_id" to listOf("This SKO is already in that location")))

        sko.write(OrgStockWrite.AddLocation(locationId = 220, type = LocationStockType.Picking))

        val state = sko.state.value
        assertEquals(36.0, state.orgStock?.quantityInLocations ?: 0.0, 0.0)
        assertEquals(StockEvent.Refused("This SKO is already in that location", GoodsOutProblem.ServerError), state.event)
        assertEquals(listOf("This SKO is already in that location"), state.writeErrors["location_id"])
    }

    @Test
    fun `stock actions need stock edit, and moving needs two locations`() {
        val none = WarehouseAbilities()
        val single = detail.copy(locations = detail.locations.take(1))
        val empty = detail.copy(locations = emptyList())

        assertEquals(SkoAction.entries.toList(), detail.allowedActions(none))
        assertEquals(listOf(SkoAction.Audit, SkoAction.Consume, SkoAction.AddLocation, SkoAction.RemoveLocations), single.allowedActions(none))
        assertEquals(listOf(SkoAction.AddLocation), empty.allowedActions(none))
        assertTrue(detail.copy(can = StockRights(editStocks = false)).allowedActions(WarehouseAbilities(moveStock = true)).isEmpty())
        assertEquals(SkoAction.entries.toList(), detail.copy(can = null).allowedActions(WarehouseAbilities(moveStock = true)))
    }

    @Test
    fun `picking defaults are moved with true flags and the SKO is read again`() {
        val sko = loaded()
        api.linkUpdateResult = GoodsOutResult.Loaded(LocationDetail(summary = LocationSummary(id = 221)))
        api.orgStock = GoodsOutResult.Loaded(detail.copy(locations = listOf(link(5501, 220, 30.0), link(5502, 221, 6.0, isDefaultWholesale = true))))

        sko.setPickingDefaults(PickingDefaults(wholesale = 5502, dropshipping = 5502))

        assertEquals(listOf(5502L to LocationOrgStockUpdate(makeDefaultFor = setOf(PickingChannel.Wholesale, PickingChannel.Dropshipping))), api.linkUpdates)
        assertTrue(sko.state.value.orgStock?.location(5502)?.isDefaultWholesale == true)
        assertEquals(StockEvent.Done(action = PickingDefaults(wholesale = 5502, dropshipping = 5502)), sko.state.value.event)
        assertEquals(listOf(77L, 77L), api.orgStockRequests)
    }

    @Test
    fun `an unchanged default sends nothing`() {
        val sko = loaded()

        sko.setPickingDefaults(PickingDefaults(wholesale = 5501, dropshipping = null))

        assertTrue(api.linkUpdates.isEmpty())
    }

    @Test
    fun `new locations are searched without the ones the SKO already has`() {
        val dispatcher = UnconfinedTestDispatcher()
        Dispatchers.setMain(dispatcher)
        val sko = loaded()
        api.locationPages = { GoodsOutResult.Loaded(LocationPage(locationSummaries(219L..222L), meta(1, 1, 4))) }

        sko.finder.search("L-")
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(listOf(219L, 222L), sko.finder.state.value.results.map { it.id })
    }
}
