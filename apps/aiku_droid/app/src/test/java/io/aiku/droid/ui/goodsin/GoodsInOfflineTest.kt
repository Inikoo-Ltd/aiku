package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.CustomerReturn
import io.aiku.droid.data.api.CustomerReturnItem
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.GoodsInSko
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ItemLocation
import io.aiku.droid.data.api.PlaceRef
import io.aiku.droid.data.api.PlaceTarget
import io.aiku.droid.data.api.ReturnOutcome
import io.aiku.droid.data.api.ReturnState
import io.aiku.droid.data.api.ReturnWrite
import io.aiku.droid.data.api.StockDeliveryOutcome
import io.aiku.droid.data.api.StockDeliveryState
import io.aiku.droid.data.api.StockDeliveryWrite
import io.aiku.droid.data.offline.DownloadedRecord
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.ui.inventory.FakeInventoryApi
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.sync.FakeOfflineWork
import io.aiku.droid.ui.sync.QueueEvent
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.time.Instant

@OptIn(ExperimentalCoroutinesApi::class)
class GoodsInOfflineTest {

    private val api = FakeGoodsInApi()
    private val offline = FakeOfflineWork()
    private val returnKey = OfflineRecordKey(OfflineRecordType.RETURN, 5)
    private val deliveryKey = OfflineRecordKey(OfflineRecordType.STOCK_DELIVERY, 1)
    private val seenAt = Instant.parse("2026-11-20T09:30:00Z")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private val item = CustomerReturnItem(
        id = 51,
        sko = GoodsInSko(id = 77, code = "AWO-12"),
        expected = 5.0,
        remaining = 5.0,
        locations = listOf(ItemLocation(locationOrgStockId = 5501, location = PlaceRef(220, "E-04-2"), quantity = 4.0, version = 7)),
    )

    private val returning = CustomerReturn(
        id = 5,
        reference = "R-5",
        state = ReturnState.RETURNING,
        items = listOf(item),
        availableActions = listOf(GoodsInActionKey.PUT_BACK, GoodsInActionKey.SET_AS_RETURNED).map { DeliveryNoteAction(key = it, label = it) },
    )

    private fun openReturn(): ReturnViewModel {
        api.customerReturn = GoodsOutResult.Loaded(returning)
        return ReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = 5, offlineWork = offline).also { it.onStart() }
    }

    @Test
    fun `offline, the three return puts queue their contract payloads with explicit quantities`() {
        val detail = openReturn()
        offline.online.value = false
        offline.downloads[returnKey] = DownloadedRecord(body = "{}", seenAt = seenAt, downloadedAt = seenAt)

        detail.backToStock(itemId = 51, quantity = 2.0, locationOrgStockId = 5501)
        detail.damaged(itemId = 51, quantity = 1.0)
        detail.notReturned(itemId = 51, quantity = null)

        assertTrue(api.returnWrites.isEmpty())
        val queued = offline.queued
        assertEquals(listOf(OfflineOperation.RETURN_PUT_BACK, OfflineOperation.RETURN_DAMAGED, OfflineOperation.RETURN_NOT_RETURNED), queued.map { it.operation })
        assertEquals(
            buildJsonObject {
                put("return_delivery_note_id", 5)
                put("return_delivery_note_item_id", 51)
                put("quantity", 2)
                put("location_org_stock_id", 5501)
                put("client_request_id", "req-1")
            },
            queued[0].payload,
        )
        assertEquals(JsonPrimitive(2), queued[2].payload["quantity"])
        assertTrue(queued.all { it.record == returnKey && it.seenAt == seenAt && it.warehouseId == 1L })
        assertEquals(listOf("req-1", "req-2", "req-3"), queued.map { it.clientRequestId })
        assertEquals(QueueEvent.Queued(OfflineOperation.RETURN_NOT_RETURNED), detail.state.value.queueEvent)
    }

    @Test
    fun `queued puts show on the item until they are sent, and undo takes them off`() {
        val detail = openReturn()
        offline.online.value = false

        detail.backToStock(itemId = 51, quantity = 2.0, locationOrgStockId = 5501)
        detail.damaged(itemId = 51, quantity = 1.0)

        val shown = detail.state.value.customerReturn?.item(51)
        assertEquals(2.0, shown?.returned ?: 0.0, 0.0)
        assertEquals(1.0, shown?.damaged ?: 0.0, 0.0)
        assertEquals(2.0, shown?.remaining ?: 0.0, 0.0)
        assertEquals(6.0, shown?.locations?.single()?.quantity ?: 0.0, 0.0)
        assertEquals(2, detail.state.value.pending.size)

        detail.undoQueued(detail.state.value.pending.first().localId)

        assertEquals(0.0, detail.state.value.customerReturn?.item(51)?.returned ?: -1.0, 0.0)
        assertEquals(4.0, detail.state.value.customerReturn?.item(51)?.remaining ?: 0.0, 0.0)
        assertEquals(listOf(1L), offline.undone)
    }

    @Test
    fun `a network failure online queues the same action with the same request id`() {
        val detail = openReturn()
        api.returnWriteResults = { GoodsOutResult.Unreachable }

        detail.backToStock(itemId = 51, quantity = 3.0, locationOrgStockId = 5501)

        val sent = api.returnWrites.single() as ReturnWrite.Returned
        assertEquals("req-1", sent.clientRequestId)
        assertEquals("req-1", offline.queued.single().clientRequestId)
        assertEquals(JsonPrimitive("req-1"), offline.queued.single().payload["client_request_id"])
        assertNull(detail.state.value.event)
    }

    @Test
    fun `an HTTP refusal online is shown as before and nothing is queued`() {
        val detail = openReturn()
        api.returnWriteResults = { GoodsOutResult.Invalid("Only 5 left") }

        detail.damaged(itemId = 51, quantity = 6.0)

        assertTrue(offline.queued.isEmpty())
        assertEquals("Only 5 left", (detail.state.value.event as GoodsInEvent.Refused).message)
    }

    @Test
    fun `while puts wait for this return, a new put queues behind them even online`() {
        val detail = openReturn()
        offline.online.value = false
        detail.damaged(itemId = 51, quantity = 1.0)
        offline.online.value = true

        detail.damaged(itemId = 51, quantity = 1.0)

        assertTrue(api.returnWrites.isEmpty())
        assertEquals(2, offline.queued.size)
    }

    @Test
    fun `starting, set as returned and undo of a put are online only, and online they flush the queue first`() {
        val detail = openReturn()
        offline.online.value = false

        detail.setAsReturned()
        assertEquals(QueueEvent.OnlineOnly, detail.state.value.queueEvent)
        detail.queueEventHandled()
        detail.undo(putId = 700)
        assertEquals(QueueEvent.OnlineOnly, detail.state.value.queueEvent)
        assertTrue(api.returnWrites.isEmpty())

        offline.online.value = true
        offline.flushResult = false
        detail.setAsReturned()
        assertEquals(QueueEvent.NeedsYou, detail.state.value.queueEvent)
        assertTrue(api.returnWrites.isEmpty())

        offline.flushResult = true
        api.returnWriteResults = { GoodsOutResult.Loaded(ReturnOutcome(returning.copy(state = ReturnState.RETURNED), GoodsInWriteStatus.DONE, null)) }
        detail.setAsReturned()
        assertEquals(listOf(returnKey, returnKey), offline.flushed)
        assertEquals(listOf<ReturnWrite>(ReturnWrite.SetAsReturned), api.returnWrites)
    }

    @Test
    fun `offline, the return opens from the downloaded copy with its age`() {
        offline.online.value = false
        val downloadedAt = Instant.parse("2026-11-20T09:31:00Z")
        offline.downloads[returnKey] = DownloadedRecord(
            body = """{"data":{"id":5,"reference":"R-5","state":"returning","items":[{"id":51,"expected":2,"remaining":2}]}}""",
            seenAt = seenAt,
            downloadedAt = downloadedAt,
        )

        val detail = ReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = 5, offlineWork = offline).also { it.onStart() }

        assertEquals("R-5", detail.state.value.customerReturn?.reference)
        assertEquals(downloadedAt, detail.state.value.copyAt)
        assertNull(detail.state.value.problem)
    }

    @Test
    fun `online but unreachable falls back to the downloaded copy, with no copy it shows the problem`() {
        api.customerReturn = GoodsOutResult.Unreachable
        val noCopy = ReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = 5, offlineWork = offline).also { it.onStart() }
        assertNull(noCopy.state.value.customerReturn)
        assertTrue(noCopy.state.value.problem != null)

        offline.downloads[returnKey] = DownloadedRecord(body = """{"data":{"id":5,"reference":"R-5"}}""", seenAt = seenAt, downloadedAt = seenAt)
        noCopy.retry()
        assertEquals("R-5", noCopy.state.value.customerReturn?.reference)
    }

    @Test
    fun `a sent action reloads the return when online`() {
        val detail = openReturn()
        offline.online.value = false
        detail.damaged(itemId = 51, quantity = 1.0)
        offline.online.value = true
        api.customerReturn = GoodsOutResult.Loaded(returning.copy(items = listOf(item.copy(damaged = 1.0, remaining = 4.0))))

        offline.setState(offline.queued.single().localId, QueuedActionState.APPLIED)

        assertEquals(1.0, detail.state.value.customerReturn?.item(51)?.damaged ?: 0.0, 0.0)
        assertEquals(4.0, detail.state.value.customerReturn?.item(51)?.remaining ?: 0.0, 0.0)
    }

    private val deliveryItemToPlace = deliveryItem(
        id = 10,
        state = StockDeliveryState.CHECKED,
        checked = 6.0,
        toPlace = 6.0,
        locations = listOf(itemLocation(linkId = 5501, locationId = 220, code = "A-01")),
    )

    private fun openDelivery(): StockDeliveryViewModel {
        api.delivery = GoodsOutResult.Loaded(stockDelivery(StockDeliveryState.BOOKING_IN, listOf(deliveryItemToPlace), listOf(GoodsInActionKey.PLACE, GoodsInActionKey.RECEIVE)))
        return StockDeliveryViewModel(api, FakeInventoryApi(), FakeSessions(), warehouseId = 1, stockDeliveryId = 1, offlineWork = offline).also { it.onStart() }
    }

    @Test
    fun `offline, place and place all queue explicit placements`() {
        val delivery = openDelivery()
        offline.online.value = false

        delivery.place(itemId = 10, quantity = 2.0, target = PlaceTarget.Link(5501, "A-01"), setAsPickingLocation = false)
        delivery.placeAll(itemId = 10, target = PlaceTarget.Location(330, "B-02"), setAsPickingLocation = true)

        assertTrue(api.deliveryWrites.isEmpty())
        val (place, placeAll) = offline.queued
        assertEquals(
            buildJsonObject {
                put("stock_delivery_id", 1)
                put("stock_delivery_item_id", 10)
                put("quantity", 2)
                put("location_org_stock_id", 5501)
                put("set_as_picking_location", false)
                put("client_request_id", "req-1")
            },
            place.payload,
        )
        assertEquals(
            buildJsonObject {
                put("stock_delivery_id", 1)
                put("stock_delivery_item_id", 10)
                put("quantity", 4)
                put("location_id", 330)
                put("set_as_picking_location", true)
                put("client_request_id", "req-2")
            },
            placeAll.payload,
        )
        assertTrue(offline.queued.all { it.operation == OfflineOperation.PLACE_STOCK_DELIVERY_ITEM && it.record == deliveryKey })
        val shown = delivery.state.value.delivery?.item(10)
        assertEquals(6.0, shown?.skoPlaced ?: 0.0, 0.0)
        assertEquals(0.0, shown?.skoToPlace ?: -1.0, 0.0)
        assertEquals(2.0, shown?.locations?.single()?.quantity ?: 0.0, 0.0)
    }

    @Test
    fun `a placement that cannot reach Aiku is queued with its request id, a server error stays unsure`() {
        val delivery = openDelivery()
        api.deliveryWriteResults = { GoodsOutResult.Unreachable }

        delivery.placeAll(itemId = 10, target = PlaceTarget.Link(5501, "A-01"), setAsPickingLocation = false)

        val sent = api.deliveryWrites.single() as StockDeliveryWrite.PlaceAll
        assertEquals(sent.clientRequestId, offline.queued.single().clientRequestId)
        assertEquals(JsonPrimitive(6), offline.queued.single().payload["quantity"])
        assertNull(delivery.state.value.unsurePlacement)

        api.deliveryWriteResults = { GoodsOutResult.ServerError(500) }
        offline.replace(emptyList())
        delivery.place(itemId = 10, quantity = 1.0, target = PlaceTarget.Link(5501, "A-01"), setAsPickingLocation = false)
        assertTrue(delivery.state.value.unsurePlacement is StockDeliveryWrite.Place)
        assertTrue(offline.queued.isEmpty())
    }

    @Test
    fun `booking-in status changes are online only and flush the queue first online`() {
        val delivery = openDelivery()
        offline.online.value = false

        delivery.receive()
        delivery.check(itemId = 10, skoQuantity = 6.0)

        assertEquals(QueueEvent.OnlineOnly, delivery.state.value.queueEvent)
        assertTrue(api.deliveryWrites.isEmpty())

        offline.online.value = true
        api.deliveryWriteResults = { GoodsOutResult.Loaded(StockDeliveryOutcome(stockDelivery(StockDeliveryState.RECEIVED, listOf(deliveryItemToPlace)), "done", null)) }
        delivery.receive()
        assertEquals(listOf(deliveryKey), offline.flushed)
        assertEquals(listOf<StockDeliveryWrite>(StockDeliveryWrite.Receive), api.deliveryWrites)
    }
}
