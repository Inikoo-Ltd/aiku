package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OutboundWriteStatus
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.PalletReturnOutcome
import io.aiku.droid.data.api.PalletReturnScanOutcome
import io.aiku.droid.data.api.PalletReturnScanStatus
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.data.api.ParcelWrite
import io.aiku.droid.data.api.ReturnOtherPallet
import io.aiku.droid.data.api.Shipper
import io.aiku.droid.ui.goodsout.GoodsOutProblem
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
import org.junit.Assert.assertSame
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PalletReturnViewModelTest {

    private val api = FakeFulfilmentOutboundApi()
    private var requestIds = 0

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun viewModel(palletReturn: PalletReturn, handoff: PalletReturnHandoff = PalletReturnHandoff()): PalletReturnViewModel {
        api.palletReturn = GoodsOutResult.Loaded(palletReturn)
        return PalletReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = palletReturn.id, handoff = handoff) {
            requestIds += 1
            "req-$requestIds"
        }.also { it.onStart() }
    }

    private val palletsPicking = palletReturn(
        pallets = listOf(
            palletLine(55, 10, "acme-p0118", "picked", PalletReturnActionKey.UNDO),
            palletLine(56, 11, "acme-p0121", "picking", PalletReturnActionKey.PICK, PalletReturnActionKey.NOT_PICKED, customerReference = "Spring 2"),
        ),
    )

    private val itemsPicking = palletReturn(
        typeGroup = PalletReturnTypeGroup.ITEMS,
        storedItems = listOf(
            storedItem(
                id = 1,
                reference = "GIFT-L",
                toPick = 2,
                barcode = "5060",
                lines = listOf(
                    itemLine(61, 11, "bravo-p0140", ordered = 4, picked = 4, toPick = 0, onPallet = 0, PalletReturnActionKey.UNDO),
                    itemLine(62, 12, "bravo-p0152", ordered = 2, picked = 0, toPick = 2, onPallet = 1, PalletReturnActionKey.PICK, PalletReturnActionKey.NOT_PICKED),
                ),
                others = listOf(ReturnOtherPallet(palletStoredItemId = 88, quantityAvailable = 3)),
            ),
        ),
    )

    @Test
    fun `pulling to refresh loads the return again, but not while a write is being sent`() {
        val detail = viewModel(palletReturn(state = "picking", keys = listOf(PalletReturnActionKey.FINISH_PICKING)))
        api.palletReturn = GoodsOutResult.Loaded(palletReturn(state = "picked", keys = listOf(PalletReturnActionKey.DISPATCH)))

        detail.refresh()

        assertEquals("picked", detail.state.value.palletReturn?.state)
        assertFalse(detail.state.value.isRefreshing)

        val gate = CompletableDeferred<Unit>()
        api.writeGate = gate
        api.writeResults = answer(palletReturn(state = "dispatched"))
        detail.dispatch()
        api.palletReturn = GoodsOutResult.Loaded(palletReturn(state = "picking"))

        assertFalse(detail.state.value.canRefresh)
        detail.refresh()

        assertEquals("picked", detail.state.value.palletReturn?.state)
        gate.complete(Unit)
        assertEquals("dispatched", detail.state.value.palletReturn?.state)
    }

    @Test
    fun `the return loads and the transitions send their writes and report done`() {
        val picked = palletReturn(state = "picked", keys = listOf(PalletReturnActionKey.DISPATCH))
        api.writeResults = answer(picked, message = "Return set as picked.")
        val detail = viewModel(palletReturn(state = "picking", keys = listOf(PalletReturnActionKey.FINISH_PICKING)))

        detail.finishPicking()

        assertEquals(listOf<PalletReturnWrite>(PalletReturnWrite.FinishPicking), api.writes)
        assertEquals("picked", detail.state.value.palletReturn?.state)
        val event = detail.state.value.event as OutboundEvent.Done
        assertEquals(PalletReturnWrite.FinishPicking, event.action)
        assertEquals("Return set as picked.", event.message)
        assertFalse(detail.state.value.isWorking)
        assertEquals(ReturnScreen.Picked, detail.state.value.palletReturn?.screen)

        detail.eventHandled()
        detail.dispatch()
        detail.startPicking()
        detail.printInserts()

        assertEquals(
            listOf(PalletReturnWrite.FinishPicking, PalletReturnWrite.Dispatch, PalletReturnWrite.StartPicking, PalletReturnWrite.PrintInserts),
            api.writes,
        )
    }

    @Test
    fun `line actions send pick, not picked and undo`() {
        api.writeResults = answer(palletsPicking)
        val detail = viewModel(palletsPicking)

        detail.pickPallet(56)
        detail.notPicked(56)
        detail.undo(55)
        detail.notPicked(62, quantity = 2)

        assertEquals(
            listOf(
                PalletReturnWrite.PickPallet(56),
                PalletReturnWrite.NotPicked(lineId = 56, quantity = null),
                PalletReturnWrite.Undo(55),
                PalletReturnWrite.NotPicked(lineId = 62, quantity = 2),
            ),
            api.writes,
        )
    }

    @Test
    fun `a refused write keeps the return and shows the server message and field errors`() {
        api.writeResults = { GoodsOutResult.Invalid("Only 1 can be picked from this pallet.", mapOf("quantity" to listOf("Only 1 can be picked from this pallet."))) }
        val detail = viewModel(itemsPicking)

        detail.pickItem(62, 2)

        val event = detail.state.value.event as OutboundEvent.Refused
        assertEquals("Only 1 can be picked from this pallet.", event.message)
        assertEquals(listOf("Only 1 can be picked from this pallet."), detail.state.value.writeErrors["quantity"])
        assertNull(detail.state.value.unsure)
        assertSame(itemsPicking, detail.state.value.palletReturn)
        assertFalse(detail.state.value.isWorking)
    }

    @Test
    fun `a forbidden write shows the server message`() {
        api.writeResults = { GoodsOutResult.Forbidden("You can't dispatch returns.") }
        val detail = viewModel(palletReturn(state = "picked", keys = listOf(PalletReturnActionKey.DISPATCH)))

        detail.dispatch()

        val event = detail.state.value.event as OutboundEvent.Refused
        assertEquals("You can't dispatch returns.", event.message)
        assertEquals(GoodsOutProblem.Forbidden, event.problem)
    }

    @Test
    fun `picking a quantity sends a fresh request id per tap and a retry reuses it`() {
        api.writeResults = { GoodsOutResult.Unreachable }
        val detail = viewModel(itemsPicking)

        detail.pickItem(62, 1)

        val first = api.writes.single() as PalletReturnWrite.PickItem
        assertEquals("req-1", first.clientRequestId)
        assertEquals(1, first.quantity)
        assertTrue(detail.state.value.unsure is ReturnTask.Write)
        assertEquals(GoodsOutProblem.Offline, (detail.state.value.event as OutboundEvent.Refused).problem)

        api.writeResults = answer(itemsPicking)
        detail.retryUnsure()

        val retried = api.writes.last() as PalletReturnWrite.PickItem
        assertEquals("req-1", retried.clientRequestId)
        assertNull(detail.state.value.unsure)

        detail.pickAllOfItem(62)

        val all = api.writes.last() as PalletReturnWrite.PickItem
        assertEquals("req-2", all.clientRequestId)
        assertNull(all.quantity)
    }

    @Test
    fun `a write without a request id is not offered for retry`() {
        api.writeResults = { GoodsOutResult.ServerError(500) }
        val detail = viewModel(palletsPicking)

        detail.pickPallet(56)

        assertNull(detail.state.value.unsure)
        assertEquals(GoodsOutProblem.ServerError, (detail.state.value.event as OutboundEvent.Refused).problem)

        api.writeResults = answer(palletsPicking)
        detail.pickPallet(56)

        assertEquals(2, api.writes.size)
    }

    @Test
    fun `picking from another pallet names the short line and retries with the same request id`() {
        api.writeResults = { GoodsOutResult.Unreachable }
        val detail = viewModel(itemsPicking)

        detail.pickFromOtherPallet(palletStoredItemId = 88, quantity = 1, fromLineId = 62)
        api.writeResults = answer(itemsPicking, message = "Picked 1 from bravo-p0160.")
        detail.retryUnsure()

        assertEquals(
            listOf(
                PalletReturnWrite.PickFromOtherPallet(palletStoredItemId = 88, quantity = 1, fromLineId = 62, clientRequestId = "req-1"),
                PalletReturnWrite.PickFromOtherPallet(palletStoredItemId = 88, quantity = 1, fromLineId = 62, clientRequestId = "req-1"),
            ),
            api.writes,
        )
        assertEquals("Picked 1 from bravo-p0160.", (detail.state.value.event as OutboundEvent.Done).message)
    }

    @Test
    fun `with scan to pick every scan goes to the return scan with a fresh request id`() {
        val scanning = palletsPicking.copy(canScan = true)
        api.scanResults = { request ->
            GoodsOutResult.Loaded(PalletReturnScanOutcome(palletReturn = scanning, status = PalletReturnScanStatus.PICKED, message = "${request.barcode} picked.", lineId = 56))
        }
        val detail = viewModel(scanning)

        detail.scan(" acme-p0121 ")
        detail.scan("MUG-BLUE")

        assertEquals(listOf("acme-p0121", "MUG-BLUE"), api.scans.map { it.barcode })
        assertEquals(listOf(1, 1), api.scans.map { it.quantity })
        assertEquals(listOf("req-1", "req-2"), api.scans.map { it.clientRequestId })
        val event = detail.state.value.event as OutboundEvent.Scanned
        assertEquals("MUG-BLUE picked.", event.message)
        assertEquals(56L, event.lineId)
        assertEquals(0, detail.state.value.queuedScans)
        assertTrue(api.writes.isEmpty())
    }

    @Test
    fun `an unanswered scan is retried with the same request id`() {
        val scanning = palletsPicking.copy(canScan = true)
        api.scanResults = { GoodsOutResult.Unreachable }
        val detail = viewModel(scanning)

        detail.scan("acme-p0121")

        assertTrue(detail.state.value.unsure is ReturnTask.Scan)

        api.scanResults = { GoodsOutResult.Loaded(PalletReturnScanOutcome(palletReturn = scanning, status = PalletReturnScanStatus.NOT_FOUND, message = null, lineId = null)) }
        detail.retryUnsure()

        assertEquals(listOf("req-1", "req-1"), api.scans.map { it.clientRequestId })
        assertEquals(PalletReturnScanStatus.NOT_FOUND, (detail.state.value.event as OutboundEvent.Scanned).status)
    }

    @Test
    fun `a refused scan shows the message and the next scan still goes`() {
        val scanning = palletsPicking.copy(canScan = true)
        api.scanResults = { GoodsOutResult.Forbidden("Scan to pick is not enabled for this organisation.") }
        val detail = viewModel(scanning)

        detail.scan("acme-p0121")

        assertEquals("Scan to pick is not enabled for this organisation.", (detail.state.value.event as OutboundEvent.Refused).message)
        assertNull(detail.state.value.unsure)

        detail.scan("acme-p0118")

        assertEquals(2, api.scans.size)
    }

    @Test
    fun `without scan to pick a scanned pallet reference selects its line`() {
        val detail = viewModel(palletsPicking)

        detail.scan("ACME-P0121")

        assertEquals(OutboundEvent.LineFound(56), detail.state.value.event)
        assertEquals(11L, detail.state.value.selectedPalletId)

        detail.scan("spring 2")

        assertEquals(OutboundEvent.LineFound(56), detail.state.value.event)

        detail.scan("nope")

        assertEquals(OutboundEvent.NotOnReturn("nope"), detail.state.value.event)
        assertTrue(api.scans.isEmpty())
    }

    @Test
    fun `without scan to pick a stored item return selects the pallet, then the item's line on it`() {
        val detail = viewModel(itemsPicking)

        detail.scan("bravo-p0140")

        assertEquals(OutboundEvent.PalletFound(palletId = 11, reference = "bravo-p0140"), detail.state.value.event)

        detail.scan("5060")

        assertEquals(OutboundEvent.LineFound(61), detail.state.value.event)

        detail.selectPallet(null)
        detail.scan("gift-l")

        assertEquals(OutboundEvent.LineFound(62), detail.state.value.event)
    }

    @Test
    fun `creating an api shipment saves the parcels first and stops when they are refused`() {
        val parcels = listOf(ParcelWrite(weightKg = 12.5, dimensionsCm = listOf(120.0, 80.0, 100.0), boxes = null))
        api.writeResults = { write ->
            when (write) {
                is PalletReturnWrite.SetParcels -> GoodsOutResult.Invalid("Enter the weight.", mapOf("parcels.0.weight_kg" to listOf("Enter the weight.")))
                else -> GoodsOutResult.Loaded(PalletReturnOutcome(palletReturn = palletsPicking, status = OutboundWriteStatus.DONE, message = null))
            }
        }
        val detail = viewModel(palletReturn(state = "picked", keys = listOf(PalletReturnActionKey.ADD_SHIPMENT)))

        detail.createShipment(shipperId = 4, tracking = null, parcels = parcels)

        assertEquals(listOf<PalletReturnWrite>(PalletReturnWrite.SetParcels(parcels)), api.writes)
        assertEquals(listOf("Enter the weight."), detail.state.value.writeErrors["parcels.0.weight_kg"])
        assertEquals(PalletReturnWrite.AddShipment(shipperId = 4, tracking = null), (detail.state.value.event as OutboundEvent.Refused).action)

        api.writeResults = answer(palletsPicking, message = "Shipment added.")
        detail.createShipment(shipperId = 5, tracking = " TRK-1 ", parcels = null)

        assertEquals(PalletReturnWrite.AddShipment(shipperId = 5, tracking = "TRK-1"), api.writes.last())
        assertEquals(PalletReturnWrite.AddShipment(shipperId = 5, tracking = "TRK-1"), (detail.state.value.event as OutboundEvent.Done).action)
    }

    @Test
    fun `shippers load for the return and the shipment actions send their writes`() {
        api.shippers = GoodsOutResult.Loaded(listOf(Shipper(id = 4, name = "DPD", type = "api")))
        api.writeResults = answer(palletsPicking)
        val detail = viewModel(palletsPicking)

        detail.loadShippers()
        detail.deleteShipment(91)
        detail.printLabel(91)

        assertEquals(listOf("DPD"), detail.state.value.shippers.items.map { it.name })
        assertEquals(listOf(PalletReturnWrite.DeleteShipment(91), PalletReturnWrite.PrintLabel(91)), api.writes)
    }

    @Test
    fun `a handed over return shows at once and is refreshed`() {
        val handoff = PalletReturnHandoff()
        handoff.hand(palletsPicking)
        api.palletReturn = GoodsOutResult.Unreachable
        val detail = PalletReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = 7, handoff = handoff)

        assertSame(palletsPicking, detail.state.value.palletReturn)

        detail.onStart()

        assertEquals(GoodsOutProblem.Offline, detail.state.value.problem)
        assertSame(palletsPicking, detail.state.value.palletReturn)
        assertNull(handoff.take(7))
    }

    @Test
    fun `an expired session ends the screen`() {
        api.writeResults = { GoodsOutResult.SessionExpired }
        val detail = viewModel(palletsPicking)

        detail.pickPallet(56)

        assertTrue(detail.state.value.isSessionExpired)
    }
}
