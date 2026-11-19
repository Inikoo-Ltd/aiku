package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.CheckResult
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.GoodsInChecklist
import io.aiku.droid.data.api.GoodsInErrorKey
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PlaceTarget
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedSko
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryOutcome
import io.aiku.droid.data.api.StockDeliveryWrite
import io.aiku.droid.ui.components.StockDeliveryStage
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeInventoryApi
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CompletableDeferred
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
class StockDeliveryViewModelTest {

    private val api = FakeGoodsInApi()
    private var requestIds = 0

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun viewModel(delivery: StockDelivery, handoff: StockDeliveryHandoff = StockDeliveryHandoff()): StockDeliveryViewModel {
        api.delivery = GoodsOutResult.Loaded(delivery)
        return StockDeliveryViewModel(api, FakeInventoryApi(), FakeSessions(), warehouseId = 1, stockDeliveryId = delivery.id, handoff = handoff) {
            requestIds += 1
            "req-$requestIds"
        }.also { it.onStart() }
    }

    private fun answer(delivery: StockDelivery, message: String? = null, status: String = GoodsInWriteStatus.DONE): (StockDeliveryWrite) -> GoodsOutResult<StockDeliveryOutcome> =
        { GoodsOutResult.Loaded(StockDeliveryOutcome(delivery = delivery, status = status, message = message)) }

    @Test
    fun `pulling to refresh reloads the delivery, but not while a write is being saved`() {
        val received = stockDelivery(state = "received", items = listOf(deliveryItem(id = 1)))
        val checked = stockDelivery(state = "checked", items = listOf(deliveryItem(id = 1, checked = 10.0)))
        val delivery = viewModel(received)
        api.delivery = GoodsOutResult.Loaded(checked)

        delivery.refresh()

        assertEquals(checked, delivery.state.value.delivery)
        assertFalse(delivery.state.value.isRefreshing)

        val gate = CompletableDeferred<Unit>()
        api.deliveryWriteGate = gate
        api.delivery = GoodsOutResult.Loaded(received)
        delivery.checkAll()
        delivery.refresh()

        assertEquals(checked, delivery.state.value.delivery)
        assertFalse(delivery.state.value.isRefreshing)
        gate.complete(Unit)
    }

    @Test
    fun `the detail opens on the step that needs work`() {
        val arriving = stockDelivery("dispatched", listOf(deliveryItem(1)), actions = listOf(GoodsInActionKey.RECEIVE))
        val checkingStarted = stockDelivery("checked", listOf(deliveryItem(1, state = "checked", checked = 10.0, toPlace = 10.0), deliveryItem(2)))
        val allChecked = stockDelivery("checked", listOf(deliveryItem(1, state = "checked", checked = 10.0, toPlace = 10.0), deliveryItem(2, state = "not_received", checked = 0.0)))
        val placed = stockDelivery("booking_in", listOf(deliveryItem(1, state = "placed", checked = 10.0, placed = 10.0)))
        val bookedIn = stockDelivery("booked_in", listOf(deliveryItem(1, state = "placed", checked = 10.0, placed = 10.0)))
        val notReceived = stockDelivery("not_received", listOf(deliveryItem(1, state = "not_received", checked = 0.0)))

        assertEquals(DeliveryView.Items, arriving.defaultView())
        assertEquals(DeliveryView.Check, checkingStarted.defaultView())
        assertEquals(DeliveryView.Place, allChecked.defaultView())
        assertEquals(DeliveryView.Done, placed.defaultView())
        assertEquals(DeliveryView.Done, bookedIn.defaultView())
        assertEquals(DeliveryView.Done, notReceived.defaultView())
        assertEquals(listOf(DeliveryView.Items), arriving.views())
        assertEquals(listOf(DeliveryView.Check, DeliveryView.Place, DeliveryView.Done), checkingStarted.views())
        assertEquals(listOf(DeliveryView.Check, DeliveryView.Done, DeliveryView.Checklist), bookedIn.copy(checklist = GoodsInChecklist()).views())
        assertEquals(StockDeliveryStage.Checked, checkingStarted.stage())
        assertNull(notReceived.stage())
    }

    @Test
    fun `the default step follows progress and a chosen tab stays while it exists`() {
        val delivery = viewModel(stockDelivery("received", listOf(deliveryItem(1)), actions = listOf(GoodsInActionKey.CHECK)))

        assertEquals(DeliveryView.Check, delivery.state.value.view)

        delivery.selectView(DeliveryView.Done)
        assertEquals(DeliveryView.Done, delivery.state.value.view)

        delivery.selectView(DeliveryView.Items)
        assertEquals(DeliveryView.Check, delivery.state.value.view)
    }

    @Test
    fun `items group into check, place and done`() {
        val delivery = stockDelivery(
            "booking_in",
            listOf(
                deliveryItem(1, state = "placed", checked = 5.0, placed = 5.0),
                deliveryItem(2, state = "checked", checked = 4.0, placed = 1.0, toPlace = 3.0),
                deliveryItem(3, state = "not_received", checked = 0.0),
                deliveryItem(4),
                deliveryItem(5, state = "cancelled"),
            ),
        )

        assertEquals(listOf(2L, 3L, 4L), delivery.itemsFor(DeliveryView.Check).map { it.id })
        assertEquals(listOf(1L, 2L), delivery.itemsFor(DeliveryView.Place).map { it.id })
        assertEquals(listOf(1L, 5L), delivery.itemsFor(DeliveryView.Done).map { it.id })
        assertEquals(2L, delivery.nextToPlace()?.id)
    }

    @Test
    fun `receiving uses the action and keeps the re-read detail`() {
        val delivery = viewModel(stockDelivery("dispatched", listOf(deliveryItem(1)), actions = listOf(GoodsInActionKey.RECEIVE)))
        val received = stockDelivery("received", listOf(deliveryItem(1)), actions = listOf(GoodsInActionKey.CHECK))
        api.deliveryWriteResults = answer(received)

        delivery.receive()

        assertEquals(listOf<StockDeliveryWrite>(StockDeliveryWrite.Receive), api.deliveryWrites)
        assertEquals("received", delivery.state.value.delivery?.state)
        assertEquals(DeliveryView.Check, delivery.state.value.view)
        assertTrue(delivery.state.value.event is GoodsInEvent.Done)
    }

    @Test
    fun `checking sends the decimal SKO quantity and not received sends 0`() {
        val delivery = viewModel(stockDelivery("received", listOf(deliveryItem(1, expected = 2.5)), actions = listOf(GoodsInActionKey.CHECK)))
        api.deliveryWriteResults = { write ->
            val sent = (write as StockDeliveryWrite.Check).skoQuantity
            GoodsOutResult.Loaded(StockDeliveryOutcome(stockDelivery("checked", listOf(deliveryItem(1, state = "checked", expected = 2.5, checked = sent))), GoodsInWriteStatus.DONE, null))
        }

        delivery.check(itemId = 1, skoQuantity = 2.25)
        val checked = delivery.state.value.event as GoodsInEvent.Done
        delivery.eventHandled()
        delivery.notReceived(itemId = 1)

        assertEquals(listOf(StockDeliveryWrite.Check(1, 2.25), StockDeliveryWrite.Check(1, 0.0)), api.deliveryWrites)
        assertFalse(checked.wasRaised)
        assertEquals(0.0, delivery.state.value.delivery?.item(1)?.skoChecked!!, 0.0)
    }

    @Test
    fun `a check the server raised to what is already placed shows its message`() {
        val delivery = viewModel(stockDelivery("booking_in", listOf(deliveryItem(1, state = "checked", checked = 8.0, placed = 6.0, toPlace = 2.0)), actions = listOf(GoodsInActionKey.CHECK)))
        val raised = stockDelivery("booking_in", listOf(deliveryItem(1, state = "checked", checked = 6.0, placed = 6.0)))
        api.deliveryWriteResults = answer(raised, message = "Raised to 6, already placed.")

        delivery.notReceived(itemId = 1)

        val event = delivery.state.value.event as GoodsInEvent.Done
        assertTrue(event.wasRaised)
        assertEquals("Raised to 6, already placed.", event.message)
    }

    @Test
    fun `check all is one write`() {
        val delivery = viewModel(stockDelivery("received", listOf(deliveryItem(1), deliveryItem(2)), actions = listOf(GoodsInActionKey.CHECK)))
        api.deliveryWriteResults = answer(stockDelivery("checked", listOf(deliveryItem(1, checked = 10.0), deliveryItem(2, checked = 10.0))))

        delivery.checkAll()

        assertEquals(listOf<StockDeliveryWrite>(StockDeliveryWrite.CheckAll), api.deliveryWrites)
    }

    @Test
    fun `placing sends a fresh request id per tap and a retry reuses it`() {
        val item = deliveryItem(1, state = "checked", checked = 10.0, toPlace = 10.0, locations = listOf(itemLocation(5501, 220, "A-01")), hasPickingLocation = true)
        val delivery = viewModel(stockDelivery("checked", listOf(item), actions = listOf(GoodsInActionKey.PLACE)))
        val target = PlaceTarget.Link(5501, "A-01")
        api.deliveryWriteResults = { GoodsOutResult.Unreachable }

        delivery.place(itemId = 1, quantity = 4.0, target = target, setAsPickingLocation = false)

        val first = api.deliveryWrites.single() as StockDeliveryWrite.Place
        assertEquals("req-1", first.clientRequestId)
        assertEquals(first, delivery.state.value.unsurePlacement)
        assertEquals(GoodsOutProblem.Offline, (delivery.state.value.event as GoodsInEvent.Refused).problem)

        api.deliveryWriteResults = answer(stockDelivery("booking_in", listOf(item.copy(skoPlaced = 4.0, skoToPlace = 6.0))), message = "Already placed.", status = GoodsInWriteStatus.NOTHING_CHANGED)
        delivery.retryPlacement()

        val retried = api.deliveryWrites.last() as StockDeliveryWrite.Place
        assertEquals("req-1", retried.clientRequestId)
        assertNull(delivery.state.value.unsurePlacement)
        assertTrue((delivery.state.value.event as GoodsInEvent.Done).isNothingChanged)

        delivery.place(itemId = 1, quantity = 4.0, target = target, setAsPickingLocation = false)
        assertEquals("req-2", (api.deliveryWrites.last() as StockDeliveryWrite.Place).clientRequestId)
    }

    @Test
    fun `a refused placement keeps the field errors and is not retried`() {
        val item = deliveryItem(1, state = "checked", checked = 10.0, toPlace = 10.0)
        val delivery = viewModel(stockDelivery("checked", listOf(item), actions = listOf(GoodsInActionKey.PLACE)))
        api.deliveryWriteResults = { GoodsOutResult.Invalid("Too many", mapOf(GoodsInErrorKey.QUANTITY to listOf("Too many"))) }

        delivery.placeAll(itemId = 1, target = PlaceTarget.Location(330, "B-02"), setAsPickingLocation = true)

        val sent = api.deliveryWrites.single() as StockDeliveryWrite.PlaceAll
        assertTrue(sent.setAsPickingLocation)
        assertEquals(listOf("Too many"), delivery.state.value.writeErrors[GoodsInErrorKey.QUANTITY])
        assertNull(delivery.state.value.unsurePlacement)
    }

    @Test
    fun `a failed check needs its note and a passed check sends no incident`() {
        val delivery = viewModel(stockDelivery("received", listOf(deliveryItem(1)), actions = listOf(GoodsInActionKey.CHECKLIST)))
        api.deliveryWriteResults = answer(stockDelivery("received", listOf(deliveryItem(1))))

        delivery.recordCheck(key = "labels", result = CheckResult.Failed, body = " Torn ", incidentType = "damaged", incidentQuantity = 2.0)
        delivery.recordCheck(key = "wrap", result = CheckResult.Passed, body = "", incidentType = "damaged", incidentQuantity = 1.0)

        assertEquals(
            listOf(
                StockDeliveryWrite.RecordCheck(key = "labels", result = CheckResult.Failed, body = "Torn", incidentType = "damaged", incidentQuantity = 2.0),
                StockDeliveryWrite.RecordCheck(key = "wrap", result = CheckResult.Passed, body = null, incidentType = null, incidentQuantity = null),
            ),
            api.deliveryWrites,
        )
    }

    @Test
    fun `scanning an SKO barcode jumps to its line or asks which line`() {
        val delivery = viewModel(
            stockDelivery(
                "received",
                listOf(deliveryItem(1, skoId = 77, barcode = "5060"), deliveryItem(2, skoId = 88, barcode = "7070"), deliveryItem(3, skoId = 88, barcode = "7070"), deliveryItem(4, skoId = 99)),
                actions = listOf(GoodsInActionKey.CHECK),
            ),
        )

        delivery.scanLine("5060")
        assertEquals(LineScanOutcome.Line(1), delivery.state.value.scanOutcome)

        delivery.scanOutcomeHandled()
        delivery.scanLine("7070")
        assertEquals(LineScanOutcome.Choose(code = "7070", itemIds = listOf(2, 3)), delivery.state.value.scanOutcome)

        delivery.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "u-99", type = ScanMatch.SKO, sko = ScannedSko(id = 99, code = "SKO-4")))
        delivery.scanLine("u-99")
        assertEquals(LineScanOutcome.Line(4), delivery.state.value.scanOutcome)

        delivery.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "u-12", type = ScanMatch.SKO, sko = ScannedSko(id = 12, code = "OTHER")))
        delivery.scanLine("u-12")
        assertEquals(LineScanOutcome.NotOnDelivery("u-12"), delivery.state.value.scanOutcome)
        assertEquals(listOf("u-99", "u-12"), api.scans)
    }

    @Test
    fun `a handed over delivery shows at once`() {
        val handoff = StockDeliveryHandoff()
        val delivery = stockDelivery("checked", listOf(deliveryItem(1)))
        handoff.hand(delivery)
        api.delivery = GoodsOutResult.Unreachable

        val opened = StockDeliveryViewModel(api, FakeInventoryApi(), FakeSessions(), warehouseId = 1, stockDeliveryId = 1, handoff = handoff)

        assertEquals("SD-1", opened.state.value.delivery?.reference)
        assertNull(handoff.take(1))
    }
}
