package io.aiku.droid.ui.goodsout

import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryAddressFields
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.DeliveryNoteCounts
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.DeliveryNotePage
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteShipment
import io.aiku.droid.data.api.DeliveryNoteShop
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.LineBox
import io.aiku.droid.data.api.LineLocation
import io.aiku.droid.data.api.LineOrgStock
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.data.api.NoteActionOutcome
import io.aiku.droid.data.api.NoteActionStatus
import io.aiku.droid.data.api.ParcelWrite
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.data.api.Picker
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.data.api.PickingSettings
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanPackItem
import io.aiku.droid.data.api.ScanPackOutcome
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPackStatus
import io.aiku.droid.data.api.ScanPickItem
import io.aiku.droid.data.api.ScanPickOutcome
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.ScanPickStatus
import io.aiku.droid.data.api.Shipper
import io.aiku.droid.data.api.ShipperDirective
import io.aiku.droid.data.api.ShipperList
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.api.WaitingKind
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.navigation.AddShipmentRoute
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.runBlocking
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
import java.io.File
import java.time.Instant

@OptIn(ExperimentalCoroutinesApi::class)
class DeliveryNoteViewModelTest {

    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana"))
    private val api = FakePickingApi()
    private val now = Instant.parse("2026-11-12T09:41:20Z")
    private val handoff = DeliveryNoteHandoff()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `an action replaces the note, says what happened and opens the screen for the new state`() {
        api.note = note(state = "unassigned", actions = listOf(DeliveryNoteAction(NoteActionKey.START_PICKING, "Start picking")))
        val picking = note(state = "handling")
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = picking, status = "done", message = "You are picking AR24811"))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.StartPicking(trolleyId = 4))

        val state = viewModel.state.value
        assertEquals(listOf<DeliveryNoteWrite>(DeliveryNoteWrite.StartPicking(trolleyId = 4)), api.writes)
        assertEquals(picking, state.note)
        assertFalse(state.isWorking)
        assertEquals(
            DeliveryNoteEvent.Acted(message = "You are picking AR24811", isNothingChanged = false, route = DeliveryNotePickingRoute(id = NOTE_ID, reference = REFERENCE)),
            state.event,
        )
        assertEquals(picking, handoff.take(NOTE_ID))

        viewModel.eventHandled()
        assertNull(viewModel.state.value.event)
    }

    @Test
    fun `an action that keeps the state stays on the screen, and nothing changed is reported as such`() {
        api.note = note(state = "handling")
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "handling"), status = NoteActionStatus.NOTHING_CHANGED, message = "Nothing left to pick on this line."))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.PickAll(lineId = LINE_ID, locationOrgStockId = null))

        assertEquals(DeliveryNoteEvent.Acted(message = "Nothing left to pick on this line.", isNothingChanged = true, route = null), viewModel.state.value.event)
        assertNull(handoff.take(NOTE_ID))
    }

    @Test
    fun `set as picked moves to the picked screen`() {
        api.note = note(state = "handling")
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "picked"), status = "done", message = null))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.SetAsPicked(pickedBayId = 2))

        assertEquals(DeliveryNoteEvent.Acted(message = null, isNothingChanged = false, route = DeliveryNotePickedRoute(id = NOTE_ID, reference = REFERENCE)), viewModel.state.value.event)
    }

    @Test
    fun `set as packed on a dropshipping note moves to the packed screen`() {
        api.note = note(state = "handling", actions = listOf(DeliveryNoteAction(NoteActionKey.SET_AS_PACKED, "Set as packed")))
        val packed = note(state = "packed")
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = packed, status = "done", message = "AR24811 set as packed"))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.SetAsPacked)

        assertEquals(listOf<DeliveryNoteWrite>(DeliveryNoteWrite.SetAsPacked), api.writes)
        assertEquals(packed, viewModel.state.value.note)
        assertEquals(
            DeliveryNoteEvent.Acted(message = "AR24811 set as packed", isNothingChanged = false, route = DeliveryNotePackingRoute(id = NOTE_ID, reference = REFERENCE)),
            viewModel.state.value.event,
        )
        assertEquals(packed, handoff.take(NOTE_ID))
    }

    @Test
    fun `set as packed is the main footer action when the server offers it`() {
        val handled = note(state = "handling", actions = listOf(DeliveryNoteAction(NoteActionKey.CHANGE_TROLLEY, "Change trolley"), DeliveryNoteAction(NoteActionKey.SET_AS_PACKED, "Set as packed")))

        assertEquals(listOf(NoteActionKey.SET_AS_PACKED), handled.footerActions.map { it.key })
        assertEquals(listOf(NoteActionKey.CHANGE_TROLLEY), handled.menuActions.map { it.key })
        assertEquals(NoteSheet.Confirm(handled.footerActions.single()), handled.footerActions.single().sheet())
    }

    @Test
    fun `a refusal keeps the note and shows the server's message`() {
        val before = note(state = "handling")
        api.note = before
        api.writeResult = GoodsOutResult.Invalid("That trolley is in use.")
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.ChangeTrolley(trolleyId = 9))

        assertEquals(before, viewModel.state.value.note)
        assertFalse(viewModel.state.value.isWorking)
        assertEquals(DeliveryNoteEvent.Refused(message = "That trolley is in use.", problem = GoodsOutProblem.ServerError), viewModel.state.value.event)

        api.writeResult = GoodsOutResult.Unreachable
        viewModel.act(DeliveryNoteWrite.TakeOver)

        assertEquals(DeliveryNoteEvent.Refused(message = "", problem = GoodsOutProblem.Offline), viewModel.state.value.event)
    }

    @Test
    fun `an expired session during an action asks to sign in again`() {
        api.note = note(state = "handling")
        api.writeResult = GoodsOutResult.SessionExpired
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.TakeOver)

        assertTrue(viewModel.state.value.isSessionExpired)
    }

    @Test
    fun `waiting for the warehouse replaces the waiting quantity, waiting for CRM adds to it`() {
        val line = line(toPick = 2.0).copy(quantityWaitingWarehouse = 1.0)

        assertEquals(DeliveryNoteWrite.Waiting(lineId = LINE_ID, kind = WaitingKind.Warehouse, quantity = 3.0, note = "Check overstock"), waitingWrite(line, WaitingKind.Warehouse, 2.0, "Check overstock"))
        assertEquals(DeliveryNoteWrite.Waiting(lineId = LINE_ID, kind = WaitingKind.Crm, quantity = 2.0, note = null), waitingWrite(line, WaitingKind.Crm, 2.0, null))
    }

    @Test
    fun `a scan picks one SKO and the panel updates in place from the answer`() {
        api.note = note(state = "handling", line = line(picked = 1.0, toPick = 5.0))
        api.scanResult = scanOutcome(status = ScanPickStatus.PICKED, line = line(picked = 2.0, toPick = 4.0), message = "Picked 1 x AWO-12")
        val viewModel = loadedViewModel()

        viewModel.scanToPick(" AWO-12 ")

        val state = viewModel.state.value
        assertEquals(listOf(ScanPickRequest(barcode = "AWO-12", quantity = 1.0)), api.scans)
        assertEquals(ScanTarget(lineId = LINE_ID, barcode = "AWO-12", locationCode = "A-01-03", locationOrgStockId = LOCATION_ID), state.scanTarget)
        assertEquals(2.0, state.note?.line(LINE_ID)?.quantityPicked ?: 0.0, 0.0)
        assertEquals(listOf(ScanLogEntry(code = "AWO-12", at = now, status = ScanPickStatus.PICKED, quantity = 1.0, message = "Picked 1 x AWO-12")), state.scanLog)
        assertEquals(0, state.queuedScans)
        assertNull(state.event)

        api.scanResult = scanOutcome(status = ScanPickStatus.PICKED, line = line(picked = 4.0, toPick = 2.0), message = null)
        viewModel.pickScannedAmount(2)
        assertEquals(ScanPickRequest(barcode = "AWO-12", quantity = 2.0, deliveryNoteItemId = LINE_ID, locationOrgStockId = LOCATION_ID), api.scans.last())
        assertEquals(2.0, viewModel.state.value.scanLog.first().quantity, 0.0)

        api.scanResult = scanOutcome(status = ScanPickStatus.PICKED, line = line(picked = 6.0, toPick = 0.0), message = null)
        viewModel.pickAllScanned()

        assertEquals(ScanPickRequest(barcode = "AWO-12", quantity = null, deliveryNoteItemId = LINE_ID, locationOrgStockId = LOCATION_ID), api.scans.last())
        assertNull(viewModel.state.value.scanTarget)
        assertEquals(DeliveryNoteEvent.LineDone(code = "AWO-12", isEverythingPicked = true), viewModel.state.value.event)
    }

    @Test
    fun `a scan that is not on the note shows the message and keeps listening`() {
        api.note = note(state = "handling", line = line(picked = 1.0, toPick = 5.0))
        api.scanResult = scanOutcome(status = ScanPickStatus.PICKED, line = line(picked = 2.0, toPick = 4.0), message = null)
        val viewModel = loadedViewModel()
        viewModel.scanToPick("AWO-12")
        val target = viewModel.state.value.scanTarget

        api.scanResult = GoodsOutResult.Loaded(ScanPickOutcome(note = note(state = "handling", line = line(picked = 2.0, toPick = 4.0)), status = ScanPickStatus.NOT_FOUND, message = "999 does not belong to this delivery note", item = null))
        viewModel.scanToPick("999")

        assertEquals(target, viewModel.state.value.scanTarget)
        assertEquals(DeliveryNoteEvent.ScanFeedback(message = "999 does not belong to this delivery note"), viewModel.state.value.event)
        assertEquals(ScanPickStatus.NOT_FOUND, viewModel.state.value.scanLog.first().status)

        viewModel.eventHandled()
        api.scanResult = GoodsOutResult.Forbidden("Scan to pick is off for this organisation")
        viewModel.scanToPick("AWO-12")

        assertEquals(DeliveryNoteEvent.Refused(message = "Scan to pick is off for this organisation", problem = GoodsOutProblem.Forbidden), viewModel.state.value.event)
        assertEquals(ScanLogStatus.FAILED, viewModel.state.value.scanLog.first().status)
        assertEquals(3, viewModel.state.value.scanLog.size)
    }

    @Test
    fun `scanning is offered only while the user picks the note in handling`() {
        val scannable = note(state = "handling").copy(settings = PickingSettings(allowScanToPick = true))

        assertTrue(scannable.canScanToPick)
        assertFalse(scannable.copy(allowActions = false).canScanToPick)
        assertFalse(scannable.copy(state = "handling_blocked").canScanToPick)
        assertFalse(scannable.copy(settings = PickingSettings(allowScanToPick = false)).canScanToPick)
        assertFalse(scannable.copy(shop = DeliveryNoteShop(id = 1, type = "dropshipping")).canScanToPick)
        assertTrue(scannable.canWorkLines)
    }

    @Test
    fun `the equipment lists load for the note's warehouse`() {
        api.note = note(state = "handling")
        api.trolleys = listOf(Trolley(id = 3, name = "T-03"))
        val viewModel = loadedViewModel()

        viewModel.loadTrolleys()

        assertEquals(listOf(Trolley(id = 3, name = "T-03")), viewModel.state.value.trolleys.items)
        assertEquals(listOf(WAREHOUSE_ID to true), api.trolleyRequests)
    }

    @Test
    fun `start packing runs at once, replaces the note and opens the packing screen`() {
        val picked = note(state = "picked", actions = listOf(DeliveryNoteAction(NoteActionKey.START_PACKING, "Start packing")))
        api.note = picked
        val packing = note(state = "packing")
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = packing, status = "done", message = "You are packing AR24811"))
        val viewModel = loadedViewModel()

        val step = picked.stepFor(picked.footerActions.single())
        assertEquals(ActionStep.Run(DeliveryNoteWrite.StartPacking), step)
        viewModel.act((step as ActionStep.Run).write)

        assertEquals(packing, viewModel.state.value.note)
        assertEquals(
            DeliveryNoteEvent.Acted(message = "You are packing AR24811", isNothingChanged = false, route = DeliveryNotePackingRoute(id = NOTE_ID, reference = REFERENCE)),
            viewModel.state.value.event,
        )
        assertEquals(packing, handoff.take(NOTE_ID))
    }

    @Test
    fun `finalise and dispatch asks first, then opens the dispatched screen`() {
        val packed = note(state = "packed", actions = listOf(DeliveryNoteAction(NoteActionKey.UNPACK, "Unpack"), DeliveryNoteAction(NoteActionKey.FINALISE_AND_DISPATCH, "Finalise and Dispatch")))
        api.note = packed
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "dispatched"), status = "done", message = "Dispatched"))
        val viewModel = loadedViewModel()

        assertEquals(listOf(NoteActionKey.FINALISE_AND_DISPATCH), packed.footerActions.map { it.key })
        assertEquals(listOf(NoteActionKey.UNPACK), packed.menuActions.map { it.key })
        assertEquals(ActionStep.Sheet(NoteSheet.Confirm(packed.footerActions.single())), packed.stepFor(packed.footerActions.single()))
        assertEquals(ActionStep.Sheet(NoteSheet.Confirm(packed.menuActions.single())), packed.stepFor(packed.menuActions.single()))

        viewModel.act(DeliveryNoteWrite.FinaliseAndDispatch)

        assertEquals(DeliveryNoteDispatchedRoute(id = NOTE_ID, reference = REFERENCE), (viewModel.state.value.event as DeliveryNoteEvent.Acted).route)
    }

    @Test
    fun `add shipment opens its screen and moves to the shipments block once there is a shipment`() {
        val addShipment = DeliveryNoteAction(NoteActionKey.ADD_SHIPMENT, "Add shipment")
        val packed = note(state = "packed", actions = listOf(addShipment))

        assertEquals(listOf(addShipment), packed.footerActions)
        assertNull(packed.addAnotherShipment)
        assertEquals(ActionStep.Open(AddShipmentRoute(id = NOTE_ID, reference = REFERENCE)), packed.stepFor(addShipment))

        val shipped = packed.copy(shipments = listOf(DeliveryNoteShipment(id = 77, shipper = "APC")))

        assertEquals(emptyList<DeliveryNoteAction>(), shipped.footerActions)
        assertEquals(addShipment, shipped.addAnotherShipment)
    }

    @Test
    fun `a key with a disabled reason renders as a notice, never as a call`() {
        val blocked = DeliveryNoteAction(NoteActionKey.SET_AS_PACKED, "Set as packed", disabledReason = "Print every insert before continuing")
        val takeOver = DeliveryNoteAction(NoteActionKey.TAKE_OVER, "Unlock to pack")
        val packing = note(state = "packing", actions = listOf(blocked, takeOver))

        assertEquals(listOf(FooterItem.Notice(blocked), FooterItem.Button(takeOver, isPrimary = true)), packing.footerItems)
        assertEquals(ActionStep.Blocked("Print every insert before continuing"), packing.stepFor(blocked))
        assertFalse(DeliveryNoteAction(NoteActionKey.SET_AS_PACKED, "Set as packed", disabledReason = " ").isDisabled)
    }

    @Test
    fun `a warning that comes with a done write is surfaced with the message`() {
        api.note = note(state = "packing", line = packLine(picked = 2.0, packed = 0.0))
        val packed = note(state = "packing", line = packLine(picked = 2.0, packed = 2.0))
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = packed, status = "done", message = "Packed 2 × AWO-12", warning = "Print every insert before continuing"))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.Pack(lineId = LINE_ID, quantity = null))

        assertEquals(
            DeliveryNoteEvent.Acted(message = "Packed 2 × AWO-12", isNothingChanged = false, route = null, warning = "Print every insert before continuing"),
            viewModel.state.value.event,
        )
        assertEquals(packed, viewModel.state.value.note)
    }

    @Test
    fun `a scan to pack packs one SKO and the panel updates in place`() {
        api.note = note(state = "packing", line = packLine(picked = 5.0, packed = 1.0))
        api.scanPackResult = packOutcome(status = ScanPackStatus.PACKED, line = packLine(picked = 5.0, packed = 2.0))
        val viewModel = loadedViewModel()

        viewModel.scanToPack(" AWO-12 ")

        val state = viewModel.state.value
        assertEquals(listOf(ScanPackRequest(barcode = "AWO-12", quantity = 1.0)), api.packScans)
        assertEquals(ScanTarget(lineId = LINE_ID, barcode = "AWO-12", locationCode = null, locationOrgStockId = null), state.scanTarget)
        assertEquals(ScanLogEntry(code = "AWO-12", at = now, status = ScanPackStatus.PACKED, quantity = 1.0, message = "Packed 1 × AWO-12"), state.scanLog.first())
        assertNull(state.event)

        api.scanPackResult = packOutcome(status = ScanPackStatus.PACKED, line = packLine(picked = 5.0, packed = 4.0))
        viewModel.packScannedAmount(2)
        assertEquals(ScanPackRequest(barcode = "AWO-12", quantity = 2.0, deliveryNoteItemId = LINE_ID), api.packScans.last())

        api.scanPackResult = packOutcome(status = ScanPackStatus.PACKED, line = packLine(picked = 5.0, packed = 5.0))
        viewModel.packAllScanned()

        assertEquals(ScanPackRequest(barcode = "AWO-12", quantity = null, deliveryNoteItemId = LINE_ID), api.packScans.last())
        assertNull(viewModel.state.value.scanTarget)
        assertEquals(DeliveryNoteEvent.LinePacked(code = "AWO-12"), viewModel.state.value.event)
    }

    @Test
    fun `a scan to pack that is not on the note shows the message and keeps listening`() {
        api.note = note(state = "packing", line = packLine(picked = 5.0, packed = 1.0))
        api.scanPackResult = packOutcome(status = ScanPackStatus.PACKED, line = packLine(picked = 5.0, packed = 2.0))
        val viewModel = loadedViewModel()
        viewModel.scanToPack("AWO-12")
        val target = viewModel.state.value.scanTarget

        api.scanPackResult = GoodsOutResult.Loaded(
            ScanPackOutcome(note = note(state = "packing", line = packLine(picked = 5.0, packed = 2.0)), status = ScanPackStatus.NOT_FOUND, message = "999 is not on this delivery note", item = null),
        )
        viewModel.scanToPack("999")

        assertEquals(target, viewModel.state.value.scanTarget)
        assertEquals(DeliveryNoteEvent.ScanFeedback(message = "999 is not on this delivery note"), viewModel.state.value.event)
    }

    @Test
    fun `scan to pack listens only while the user packs a note that allows it`() {
        val packing = note(state = "packing").copy(settings = PickingSettings(allowScanToPack = true))

        assertTrue(packing.canScanToPack)
        assertTrue(packing.canPackLines)
        assertFalse(packing.copy(allowActions = false).canScanToPack)
        assertFalse(packing.copy(state = "packed").canScanToPack)
        assertTrue(packing.copy(state = "packed").canPackLines)
        assertFalse(packing.copy(settings = PickingSettings(allowScanToPack = false)).canScanToPack)
        assertFalse(packing.copy(shop = DeliveryNoteShop(id = 1, type = "dropshipping")).canScanToPack)
        assertFalse(packing.canScanToPick)
    }

    @Test
    fun `a rule lock asks to confirm, and confirming sends the same shipment with the override`() {
        api.note = note(state = "packed")
        api.writeResult = GoodsOutResult.Invalid(
            "This delivery note is locked to APC. Confirm to use another shipper.",
            mapOf("override_lock" to listOf("This delivery note is locked to APC. Confirm to use another shipper.")),
        )
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.AddShipment(shipperId = 9, tracking = "TRK-1"))

        assertEquals(
            ShipmentFollowUp.ConfirmOverride(message = "This delivery note is locked to APC. Confirm to use another shipper.", shipperId = 9, tracking = "TRK-1"),
            viewModel.state.value.shipmentFollowUp,
        )
        assertNull(viewModel.state.value.event)

        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "packed"), status = "done", message = "Shipment added."))
        viewModel.confirmOverrideLock()

        assertEquals(DeliveryNoteWrite.AddShipment(shipperId = 9, tracking = "TRK-1", overrideLock = true), api.writes.last())
        assertNull(viewModel.state.value.shipmentFollowUp)
        assertEquals(DeliveryNoteEvent.Acted(message = "Shipment added.", isNothingChanged = false, route = null), viewModel.state.value.event)
    }

    @Test
    fun `an address the carrier refuses opens the address fix, which retries with the same shipper`() {
        api.note = note(state = "packed")
        api.writeResult = GoodsOutResult.Invalid("Invalid postcode", mapOf("address" to listOf("Invalid postcode")))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.AddShipment(shipperId = 4, tracking = null))

        assertEquals(ShipmentFollowUp.FixAddress(message = "Invalid postcode", shipperId = 4), viewModel.state.value.shipmentFollowUp)

        val fixed = DeliveryAddressFields(contactName = "Jane", addressLine1 = "12 High St", postalCode = "S1 2AB", locality = "Sheffield", countryCode = "GB")
        api.writeResult = GoodsOutResult.Invalid("The carrier is down", mapOf("message" to listOf("The carrier is down")))
        viewModel.retryShipmentWithAddress(fixed)

        assertEquals(DeliveryNoteWrite.RetryShipmentWithAddress(shipperId = 4, address = fixed), api.writes.last())
        assertEquals(ShipmentFollowUp.FixAddress(message = "The carrier is down", shipperId = 4), viewModel.state.value.shipmentFollowUp)

        viewModel.dismissShipmentFollowUp()
        api.writeResult = GoodsOutResult.Invalid("Tracking is required", mapOf("tracking" to listOf("Tracking is required")))
        viewModel.act(DeliveryNoteWrite.AddShipment(shipperId = 5, tracking = null))

        assertNull(viewModel.state.value.shipmentFollowUp)
        assertEquals(mapOf("tracking" to listOf("Tracking is required")), viewModel.state.value.writeErrors)
        assertEquals(DeliveryNoteEvent.Refused(message = "Tracking is required", problem = GoodsOutProblem.ServerError), viewModel.state.value.event)
    }

    @Test
    fun `an override confirmed on add is resent on the address retry, and a lock on the retry asks again`() {
        val lock = "This delivery note is locked to APC. Confirm to use another shipper."
        api.note = note(state = "packed")
        api.writeResult = GoodsOutResult.Invalid(lock, mapOf("override_lock" to listOf(lock)))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.AddShipment(shipperId = 9, tracking = null))
        api.writeResult = GoodsOutResult.Invalid("Invalid postcode", mapOf("address" to listOf("Invalid postcode")))
        viewModel.confirmOverrideLock()

        assertEquals(ShipmentFollowUp.FixAddress(message = "Invalid postcode", shipperId = 9, overrideLock = true), viewModel.state.value.shipmentFollowUp)

        val fixed = DeliveryAddressFields(addressLine1 = "12 High St", postalCode = "S1 2AB", locality = "Sheffield", countryCode = "GB")
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "packed"), status = "done", message = "Shipment added."))
        viewModel.retryShipmentWithAddress(fixed)

        assertEquals(DeliveryNoteWrite.RetryShipmentWithAddress(shipperId = 9, address = fixed, overrideLock = true), api.writes.last())
        assertNull(viewModel.state.value.shipmentFollowUp)

        api.writeResult = GoodsOutResult.Invalid("Invalid postcode", mapOf("address" to listOf("Invalid postcode")))
        viewModel.act(DeliveryNoteWrite.AddShipment(shipperId = 4, tracking = null))
        api.writeResult = GoodsOutResult.Invalid(lock, mapOf("override_lock" to listOf(lock)))
        viewModel.retryShipmentWithAddress(fixed)

        assertEquals(DeliveryNoteWrite.RetryShipmentWithAddress(shipperId = 4, address = fixed, overrideLock = false), api.writes.last())
        assertEquals(ShipmentFollowUp.ConfirmOverride(message = lock, shipperId = 4, tracking = null, address = fixed), viewModel.state.value.shipmentFollowUp)

        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "packed"), status = "done", message = "Shipment added."))
        viewModel.confirmOverrideLock()

        assertEquals(DeliveryNoteWrite.RetryShipmentWithAddress(shipperId = 4, address = fixed, overrideLock = true), api.writes.last())
        assertNull(viewModel.state.value.shipmentFollowUp)
    }

    @Test
    fun `cancelling sends the return choice and routes to the cancelled screen`() {
        api.note = note(state = "handling", actions = listOf(DeliveryNoteAction(NoteActionKey.CANCEL, "Cancel delivery note")))
        val cancelled = note(state = "cancelled").copy(reference = "AR24811-CANCELLED")
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = cancelled, status = "done", message = "Delivery note cancelled"))
        val viewModel = loadedViewModel()

        viewModel.act(DeliveryNoteWrite.Cancel(createReturn = false))

        assertEquals(listOf<DeliveryNoteWrite>(DeliveryNoteWrite.Cancel(createReturn = false)), api.writes)
        assertEquals(
            DeliveryNoteEvent.Acted(message = "Delivery note cancelled", isNothingChanged = false, route = DeliveryNoteCancelledRoute(id = NOTE_ID, reference = "AR24811-CANCELLED")),
            viewModel.state.value.event,
        )
        assertEquals(cancelled, handoff.take(NOTE_ID))
    }

    @Test
    fun `the shippers load with the directive for the note`() {
        api.note = note(state = "packed")
        api.shippers = ShipperList(
            shippers = listOf(Shipper(id = 4, name = "APC", type = "api"), Shipper(id = 7, name = "Courier", type = "manual", isLockedOut = true), Shipper(id = 8, name = "Van")),
            directive = ShipperDirective(preferredShipperId = 4),
        )
        val viewModel = loadedViewModel()

        viewModel.loadShippers()

        assertEquals(api.shippers.shippers, viewModel.state.value.shippers.items)
        assertEquals(4L, viewModel.state.value.shipperDirective?.chosenShipperId)
        assertEquals(listOf(4L) to listOf(8L), shipperChoices(viewModel.state.value.shippers.items).let { (apiShippers, manualShippers) -> apiShippers.map { it.id } to manualShippers.map { it.id } })
    }

    @Test
    fun `parcels are checked as the server checks them before they are sent`() {
        val good = ParcelInput(weight = "3,4", length = "40", width = "30", height = "20", boxes = "2")

        assertEquals(ParcelCheck.Valid(listOf(ParcelWrite(weightKg = 3.4, dimensionsCm = listOf(40.0, 30.0, 20.0), boxes = 2))), checkParcels(listOf(good), withBoxes = true))
        assertEquals(ParcelCheck.Valid(listOf(ParcelWrite(weightKg = 3.4, dimensionsCm = listOf(40.0, 30.0, 20.0), boxes = null))), checkParcels(listOf(good), withBoxes = false))
        assertEquals(ParcelCheck.Invalid(ParcelListProblem.NoParcels, emptyMap()), checkParcels(emptyList(), withBoxes = false))
        assertEquals(ParcelListProblem.TooManyParcels, (checkParcels(List(MAX_PARCELS + 1) { good }, withBoxes = false) as ParcelCheck.Invalid).list)

        val bad = checkParcels(listOf(good, ParcelInput(weight = "0", length = "", width = "1001", height = "5", boxes = "0")), withBoxes = true) as ParcelCheck.Invalid

        assertNull(bad.list)
        assertEquals(
            mapOf(
                ParcelFieldKey(1, ParcelField.Weight) to ParcelProblem.WeightOutOfRange,
                ParcelFieldKey(1, ParcelField.Length) to ParcelProblem.DimensionMissing,
                ParcelFieldKey(1, ParcelField.Width) to ParcelProblem.DimensionOutOfRange,
                ParcelFieldKey(1, ParcelField.Boxes) to ParcelProblem.BoxesInvalid,
            ),
            bad.fields,
        )
        assertEquals(ParcelProblem.WeightMissing, (checkParcels(listOf(good.copy(weight = "")), withBoxes = false) as ParcelCheck.Invalid).fields[ParcelFieldKey(0, ParcelField.Weight)])
    }

    @Test
    fun `a new parcel starts at the estimated weight shared out, rounded up to a tenth`() {
        assertEquals(ParcelInput(weight = "3.1", length = "", width = "", height = "", boxes = ""), ParcelInput.added(estimatedWeightKg = 6.2, parcelCount = 2))
        assertEquals("0.4", ParcelInput.added(estimatedWeightKg = 1.0, parcelCount = 3).weight)
        assertEquals("", ParcelInput.added(estimatedWeightKg = null, parcelCount = 1).weight)
        assertEquals("12.53", numericInput("1a2.5.3", isWholeNumber = false))
        assertEquals("12", numericInput("1.2", isWholeNumber = true))
    }

    @Test
    fun `parcel errors from the server land on their parcel and field`() {
        val errors = parcelServerErrors(
            mapOf(
                "parcels" to listOf("Every parcel needs its dimensions."),
                "parcels.1.weight_kg" to listOf("Too heavy."),
                "parcels.0.dimensions_cm.2" to listOf("Height must be more than 0."),
                "tracking" to listOf("Not about parcels."),
            ),
        )

        assertEquals(listOf("Every parcel needs its dimensions."), errors.general)
        assertEquals(mapOf(1 to listOf("Too heavy."), 0 to listOf("Height must be more than 0.")), errors.rows)
        assertEquals(setOf(ParcelFieldKey(1, ParcelField.Weight), ParcelFieldKey(0, ParcelField.Height)), errors.fields)
    }

    @Test
    fun `boxes are checked against the picked quantity`() {
        assertEquals(BoxCheck.Valid(listOf(LineBox(box = 1, quantity = 2.0), LineBox(box = 2, quantity = 1.5))), checkBoxes(listOf(BoxInput("1", "2"), BoxInput("2", "1,5")), picked = 3.5))
        assertEquals(BoxCheck.Invalid(setOf(BoxProblem.OverPicked)), checkBoxes(listOf(BoxInput("1", "4")), picked = 3.0))
        assertEquals(BoxCheck.Invalid(setOf(BoxProblem.BoxNumber, BoxProblem.Quantity)), checkBoxes(listOf(BoxInput("1000", "")), picked = 3.0))
        assertEquals(0.5, smallestPackAmount(2.5), 0.0)
        assertEquals(1.0, smallestPackAmount(3.0), 0.0)
        assertEquals(0.25, smallestPackAmount(0.25), 0.0)
    }

    @Test
    fun `a document download needs the session and reports an expired one`() {
        api.note = note(state = "packed")
        val viewModel = loadedViewModel()
        api.download = AttachmentDownload.SessionExpired

        val result = runBlocking { viewModel.downloadInsertPdf(leafletId = 51, target = File("insert.pdf")) }

        assertEquals(AttachmentDownload.SessionExpired, result)
        assertEquals(listOf("insert:51"), api.downloads)
        assertTrue(viewModel.state.value.isSessionExpired)
    }

    private fun loadedViewModel(): DeliveryNoteViewModel =
        DeliveryNoteViewModel(api = api, sessions = sessions, deliveryNoteId = NOTE_ID, handoff = handoff, clock = { now }).also { it.onStart() }

    private fun line(picked: Double = 0.0, toPick: Double = 1.0): DeliveryNoteLine = DeliveryNoteLine(
        id = LINE_ID,
        orgStock = LineOrgStock(id = 812, code = "AWO-12", name = "Lavender oil"),
        quantityRequired = picked + toPick,
        quantityPicked = picked,
        quantityToPick = toPick,
        locations = listOf(LineLocation(locationOrgStockId = LOCATION_ID, code = "A-01-03", stock = 30.0)),
    )

    private fun packLine(picked: Double, packed: Double): DeliveryNoteLine = DeliveryNoteLine(
        id = LINE_ID,
        orgStock = LineOrgStock(id = 812, code = "AWO-12", name = "Lavender oil"),
        quantityRequired = picked,
        quantityPicked = picked,
        quantityPacked = packed,
        quantityToPack = picked - packed,
    )

    private fun packOutcome(status: String, line: DeliveryNoteLine): GoodsOutResult<ScanPackOutcome> = GoodsOutResult.Loaded(
        ScanPackOutcome(
            note = note(state = "packing", line = line),
            status = status,
            message = "Packed 1 × AWO-12",
            item = ScanPackItem(id = LINE_ID, code = "AWO-12", quantityPacked = line.quantityPacked, quantityToPack = line.quantityToPack),
        ),
    )

    private fun note(state: String, line: DeliveryNoteLine = line(), actions: List<DeliveryNoteAction> = emptyList()): DeliveryNote = DeliveryNote(
        id = NOTE_ID,
        reference = REFERENCE,
        state = state,
        warehouseId = WAREHOUSE_ID,
        isEditable = true,
        allowActions = true,
        canHandle = true,
        lines = listOf(line),
        availableActions = actions,
    )

    private fun scanOutcome(status: String, line: DeliveryNoteLine, message: String?): GoodsOutResult<ScanPickOutcome> = GoodsOutResult.Loaded(
        ScanPickOutcome(
            note = note(state = "handling", line = line),
            status = status,
            message = message,
            item = ScanPickItem(id = LINE_ID, code = "AWO-12", locationCode = "A-01-03", locationOrgStockId = LOCATION_ID),
        ),
    )

    private class FakePickingApi : GoodsOutApi {
        var note: DeliveryNote? = null
        var writeResult: GoodsOutResult<NoteActionOutcome> = GoodsOutResult.Unreachable
        var scanResult: GoodsOutResult<ScanPickOutcome> = GoodsOutResult.Unreachable
        var trolleys: List<Trolley> = emptyList()
        var scanPackResult: GoodsOutResult<ScanPackOutcome> = GoodsOutResult.Unreachable
        var shippers = ShipperList(shippers = emptyList(), directive = ShipperDirective())
        var download: AttachmentDownload = AttachmentDownload.Unreachable
        val writes = mutableListOf<DeliveryNoteWrite>()
        val scans = mutableListOf<ScanPickRequest>()
        val packScans = mutableListOf<ScanPackRequest>()
        val downloads = mutableListOf<String>()
        val trolleyRequests = mutableListOf<Pair<Long, Boolean>>()

        override suspend fun deliveryNotes(token: String, warehouseId: Long, query: DeliveryNoteQuery): GoodsOutResult<DeliveryNotePage> = GoodsOutResult.Unreachable

        override suspend fun deliveryNoteCounts(token: String, warehouseId: Long, picker: PickerFilter?): GoodsOutResult<DeliveryNoteCounts> = GoodsOutResult.Unreachable

        override suspend fun deliveryNote(token: String, deliveryNoteId: Long): GoodsOutResult<DeliveryNote> =
            note?.let { GoodsOutResult.Loaded(it) } ?: GoodsOutResult.Unreachable

        override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable

        override suspend fun write(token: String, deliveryNoteId: Long, write: DeliveryNoteWrite): GoodsOutResult<NoteActionOutcome> {
            writes += write
            return writeResult
        }

        override suspend fun scanPick(token: String, deliveryNoteId: Long, request: ScanPickRequest): GoodsOutResult<ScanPickOutcome> {
            scans += request
            return scanResult
        }

        override suspend fun trolleys(token: String, warehouseId: Long, availableOnly: Boolean): GoodsOutResult<List<Trolley>> {
            trolleyRequests += warehouseId to availableOnly
            return GoodsOutResult.Loaded(trolleys)
        }

        override suspend fun pickedBays(token: String, warehouseId: Long): GoodsOutResult<List<PickedBay>> = GoodsOutResult.Loaded(emptyList())

        override suspend fun pickers(token: String, warehouseId: Long): GoodsOutResult<List<Picker>> = GoodsOutResult.Loaded(emptyList())

        override suspend fun scanPack(token: String, deliveryNoteId: Long, request: ScanPackRequest): GoodsOutResult<ScanPackOutcome> {
            packScans += request
            return scanPackResult
        }

        override suspend fun shippers(token: String, deliveryNoteId: Long): GoodsOutResult<ShipperList> = GoodsOutResult.Loaded(shippers)

        override suspend fun downloadDocument(token: String, url: String, target: File): AttachmentDownload {
            downloads += url
            return download
        }

        override suspend fun downloadInsertPdf(token: String, deliveryNoteId: Long, leafletId: Long, target: File): AttachmentDownload {
            downloads += "insert:$leafletId"
            return download
        }
    }

    private class InMemorySessions(initial: StaffSession) : SessionRepository {
        private val current = MutableStateFlow<StaffSession?>(initial)
        override val session: StateFlow<StaffSession?> = current.asStateFlow()
        override fun start(session: StaffSession, rememberOnDevice: Boolean) {
            current.value = session
        }
        override fun end() {
            current.value = null
        }
    }

    private companion object {
        const val NOTE_ID = 9912L
        const val LINE_ID = 41001L
        const val LOCATION_ID = 5501L
        const val WAREHOUSE_ID = 1L
        const val REFERENCE = "AR24811"
    }
}
