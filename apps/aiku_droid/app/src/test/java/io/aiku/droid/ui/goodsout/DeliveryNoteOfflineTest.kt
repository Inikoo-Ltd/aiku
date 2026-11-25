package io.aiku.droid.ui.goodsout

import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteCounts
import io.aiku.droid.data.api.DeliveryNoteCountsFilter
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.DeliveryNoteLineCounts
import io.aiku.droid.data.api.DeliveryNotePage
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.LineLocation
import io.aiku.droid.data.api.LineOrgStock
import io.aiku.droid.data.api.NotPickedReason
import io.aiku.droid.data.api.NoteActionOutcome
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.data.api.Picker
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.data.api.PickingSettings
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanPackOutcome
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPickOutcome
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.ScanPickStatus
import io.aiku.droid.data.api.ShipperList
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.api.WaitingKind
import io.aiku.droid.data.offline.DownloadedRecord
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.offline.SyncReason
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import java.io.File
import java.time.Instant
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class DeliveryNoteOfflineTest {

    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana"))
    private val api = FakeGoodsOutApi()
    private val offline = FakeOfflineWork()
    private val now = Instant.parse("2026-11-20T10:00:00Z")
    private val seenAt = Instant.parse("2026-11-20T09:30:00Z")
    private val record = OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, NOTE_ID)

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `offline, the note opens from the downloaded copy through the same parser and shows its age`() {
        offline.online.value = false
        offline.download = DownloadedRecord(body = """{"data":${NOTE_JSON}}""", seenAt = seenAt, downloadedAt = seenAt)

        val viewModel = viewModel()

        val state = viewModel.state.value
        assertEquals(0, api.noteLoads)
        assertEquals("AR24811", state.note?.reference)
        assertEquals(listOf("5060001"), state.note?.lines?.single()?.barcodes)
        assertEquals(OfflineCopy(seenAt = seenAt, downloadedAt = seenAt), state.offlineCopy)
        assertNull(state.problem)
        assertFalse(state.isOnline)
    }

    @Test
    fun `offline, reloading a note already on screen keeps it without an error or a network call`() {
        api.note = note(line = line(toPick = 1.0))
        val viewModel = viewModel()
        val loadsBefore = api.noteLoads
        offline.online.value = false

        viewModel.retry()

        assertEquals(loadsBefore, api.noteLoads)
        assertNull(viewModel.state.value.problem)
        assertEquals(REFERENCE, viewModel.state.value.note?.reference)
    }

    @Test
    fun `online, an unreachable server falls back to the downloaded copy`() {
        api.note = null
        offline.download = DownloadedRecord(body = """{"data":${NOTE_JSON}}""", seenAt = seenAt, downloadedAt = seenAt)

        val viewModel = viewModel()

        assertEquals(1, api.noteLoads)
        assertEquals("AR24811", viewModel.state.value.note?.reference)
        assertNull(viewModel.state.value.problem)
    }

    @Test
    fun `offline, a pick at a location queues the explicit amount picked with the copy's seen time`() {
        offline.download = DownloadedRecord(body = """{"data":${NOTE_JSON}}""", seenAt = seenAt, downloadedAt = seenAt)
        val viewModel = offlineViewModel(note(line = line(picked = 1.0, toPick = 4.0, pickedAtLocation = 1.0)))

        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 3.0))

        val queued = offline.actions.value.single()
        assertEquals(OfflineOperation.PICK, queued.operation)
        assertEquals(record, queued.record)
        assertEquals(WAREHOUSE_ID, queued.warehouseId)
        assertEquals(seenAt, queued.seenAt)
        assertEquals(pickPayload(quantity = 2, id = "req-1"), queued.payload)
        assertEquals("req-1", queued.clientRequestId)
        assertEquals(emptyList<DeliveryNoteWrite>(), api.writes)
        assertEquals(DeliveryNoteEvent.Queued, viewModel.state.value.event)
    }

    @Test
    fun `offline, pick all goes out as an explicit pick of what is left at the chosen location`() {
        val viewModel = offlineViewModel(note(line = line(picked = 1.0, toPick = 4.0)))

        viewModel.act(DeliveryNoteWrite.PickAll(lineId = LINE_ID, locationOrgStockId = null))

        assertEquals(pickPayload(quantity = 4, id = "req-1"), offline.actions.value.single().payload)
    }

    @Test
    fun `offline, not picked, waiting and pass to customer services queue their explicit quantities`() {
        val settings = PickingSettings(allowWaiting = true, allowPickerSetNotPicked = true)
        val viewModel = offlineViewModel(note(line = line(toPick = 5.0), settings = settings))

        viewModel.act(DeliveryNoteWrite.NotPicked(lineId = LINE_ID, quantity = null, reason = NotPickedReason.OutOfStock, note = " gone "))
        viewModel.act(DeliveryNoteWrite.Waiting(lineId = LINE_ID, kind = WaitingKind.Warehouse, quantity = 2.0, note = null))
        viewModel.act(DeliveryNoteWrite.Waiting(lineId = LINE_ID, kind = WaitingKind.Crm, quantity = 1.0, note = "Substitute?"))

        val actions = offline.actions.value
        assertEquals(listOf(OfflineOperation.NOT_PICKED, OfflineOperation.SET_WAITING_WAREHOUSE, OfflineOperation.SET_WAITING_CRM), actions.map { it.operation })
        assertEquals(
            buildJsonObject {
                put("delivery_note_id", NOTE_ID)
                put("delivery_note_item_id", LINE_ID)
                put("quantity", 5)
                put("reason", "out-of-stock")
                put("note", "gone")
                put("client_request_id", "req-1")
            },
            actions[0].payload,
        )
        assertEquals(
            buildJsonObject {
                put("delivery_note_id", NOTE_ID)
                put("delivery_note_item_id", LINE_ID)
                put("quantity", 1)
                put("note", "Substitute?")
                put("client_request_id", "req-3")
            },
            actions[2].payload,
        )
        assertEquals(2.0, (actions[1].payload["quantity"] as JsonPrimitive).content.toDouble(), 0.0)
    }

    @Test
    fun `settings that are off hide not picked and waiting, and offline nothing is queued for them`() {
        val off = note(line = line(toPick = 2.0), settings = PickingSettings())
        val on = note(line = line(toPick = 2.0), settings = PickingSettings(allowWaiting = true, allowPickerSetNotPicked = true))

        assertEquals(LineOffers(pickAll = true, notPicked = false, waiting = false), lineOffers(off, off.lines.single()))
        assertEquals(LineOffers(pickAll = true, notPicked = true, waiting = true), lineOffers(on, on.lines.single()))
        assertEquals(LineOffers(pickAll = false, notPicked = false, waiting = false), lineOffers(on, line(picked = 2.0, toPick = 0.0)))

        val viewModel = offlineViewModel(off)
        viewModel.act(DeliveryNoteWrite.NotPicked(lineId = LINE_ID, quantity = null, reason = NotPickedReason.OutOfStock, note = null))
        viewModel.act(DeliveryNoteWrite.Waiting(lineId = LINE_ID, kind = WaitingKind.Warehouse, quantity = 1.0, note = null))

        assertEquals(emptyList<QueuedAction>(), offline.actions.value)
    }

    @Test
    fun `offline, pack is queued only on a note already in packing`() {
        val packing = offlineViewModel(note(state = "packing", line = line(picked = 3.0, toPick = 0.0).copy(quantityToPack = 3.0)))

        packing.act(DeliveryNoteWrite.Pack(lineId = LINE_ID, quantity = null))

        assertEquals(OfflineOperation.PACK, offline.actions.value.single().operation)
        assertEquals(
            buildJsonObject {
                put("delivery_note_id", NOTE_ID)
                put("delivery_note_item_id", LINE_ID)
                put("quantity", 3)
                put("client_request_id", "req-1")
            },
            offline.actions.value.single().payload,
        )

        offline.actions.value = emptyList()
        val packed = offlineViewModel(note(state = "packed", line = line(picked = 3.0, toPick = 0.0).copy(quantityToPack = 3.0)))
        packed.act(DeliveryNoteWrite.Pack(lineId = LINE_ID, quantity = null))

        assertEquals(emptyList<QueuedAction>(), offline.actions.value)
        assertEquals(DeliveryNoteEvent.NeedsConnection(REFERENCE), packed.state.value.event)
    }

    @Test
    fun `online, every line write carries a client request id, and a network failure queues the same action with that id`() {
        api.note = note(line = line(picked = 1.0, toPick = 4.0, pickedAtLocation = 1.0))
        api.writeResult = GoodsOutResult.Unreachable
        val viewModel = viewModel()

        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 3.0))

        val sent = api.writes.single() as DeliveryNoteWrite.Pick
        assertEquals("req-1", sent.clientRequestId)
        val queued = offline.actions.value.single()
        assertEquals("req-1", queued.clientRequestId)
        assertEquals(pickPayload(quantity = 2, id = "req-1"), queued.payload)
        assertFalse(viewModel.state.value.isWorking)
        assertEquals(DeliveryNoteEvent.Queued, viewModel.state.value.event)
    }

    @Test
    fun `online, a refusal from the server is shown and nothing is queued`() {
        api.note = note(line = line(toPick = 4.0))
        api.writeResult = GoodsOutResult.Invalid("Nothing left to pick.")
        val viewModel = viewModel()

        viewModel.act(DeliveryNoteWrite.PickAll(lineId = LINE_ID, locationOrgStockId = LOCATION_ID))

        assertEquals("req-1", (api.writes.single() as DeliveryNoteWrite.PickAll).clientRequestId)
        assertEquals(emptyList<QueuedAction>(), offline.actions.value)
        assertEquals(DeliveryNoteEvent.Refused(message = "Nothing left to pick.", problem = GoodsOutProblem.ServerError), viewModel.state.value.event)
    }

    @Test
    fun `queued actions show on the lines with their marks, and undo removes them`() {
        val settings = PickingSettings(allowWaiting = true, allowPickerSetNotPicked = true)
        val viewModel = offlineViewModel(note(line = line(picked = 0.0, toPick = 6.0), settings = settings, counts = DeliveryNoteLineCounts(all = 1, todo = 1, done = 0)))

        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 3.0))
        viewModel.act(DeliveryNoteWrite.Waiting(lineId = LINE_ID, kind = WaitingKind.Warehouse, quantity = 1.0, note = null))

        val state = viewModel.state.value
        val line = state.note!!.lines.single()
        assertEquals(3.0, line.quantityPicked, 0.0)
        assertEquals(1.0, line.quantityWaitingWarehouse, 0.0)
        assertEquals(2.0, line.quantityToPick, 0.0)
        assertNull(line.quantityToPickLabel)
        assertEquals(3.0, line.locations.single().quantityPicked)
        assertEquals(27.0, line.locations.single().stock)
        assertEquals(listOf(OfflineOperation.PICK, OfflineOperation.SET_WAITING_WAREHOUSE), state.queuedFor(LINE_ID).map { it.operation })
        assertTrue(state.queued.all { it.canUndo })

        viewModel.act(DeliveryNoteWrite.NotPicked(lineId = LINE_ID, quantity = null, reason = NotPickedReason.OutOfStock, note = null))
        val handled = viewModel.state.value.note!!
        assertEquals(0.0, handled.lines.single().quantityToPick, 0.0)
        assertEquals(DeliveryNoteLineCounts(all = 1, todo = 0, done = 1), handled.lineCounts)

        viewModel.undoQueued(state.queued.first().localId)

        val undone = viewModel.state.value
        assertEquals(0.0, undone.note!!.lines.single().quantityPicked, 0.0)
        assertEquals(listOf(OfflineOperation.SET_WAITING_WAREHOUSE, OfflineOperation.NOT_PICKED), undone.queued.map { it.operation })
    }

    @Test
    fun `a queued action that is being sent or needs the person cannot be undone`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 6.0)))
        offline.add(queuedPick(localId = 7, quantity = 2.0, state = QueuedActionState.SENDING, sequence = 12))
        offline.add(queuedPick(localId = 8, quantity = 1.0, state = QueuedActionState.NEEDS_YOU, sequence = 13))

        viewModel.undoQueued(7)
        viewModel.undoQueued(8)

        val state = viewModel.state.value
        assertEquals(emptyList<Long>(), offline.undone)
        assertEquals(2.0, state.note!!.lines.single().quantityPicked, 0.0)
        assertFalse(state.queued.any { it.canUndo })
        assertTrue(state.queued.single { it.localId == 8L }.needsYou)
    }

    @Test
    fun `changing an unsent queued pick edits it, and changing a sent one queues the difference`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 6.0)))

        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 2.0))
        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 3.0))

        assertEquals(listOf(1L), offline.undone)
        assertEquals(pickPayload(quantity = 3, id = "req-2"), offline.actions.value.single().payload)
        assertEquals(3.0, viewModel.state.value.note!!.lines.single().quantityPicked, 0.0)

        offline.actions.value = offline.actions.value.map { it.copy(sequence = 40) }
        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 4.0))

        assertEquals(listOf(1L), offline.undone)
        assertEquals(listOf(3.0, 1.0), offline.actions.value.map { (it.payload["quantity"] as JsonPrimitive).content.toDouble() })
        assertEquals(4.0, viewModel.state.value.note!!.lines.single().quantityPicked, 0.0)
    }

    @Test
    fun `lowering an unsent queued pick to nothing simply undoes it`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 6.0)))

        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 2.0))
        viewModel.act(DeliveryNoteWrite.Pick(lineId = LINE_ID, locationOrgStockId = LOCATION_ID, quantity = 0.0))

        assertEquals(emptyList<QueuedAction>(), offline.actions.value)
        assertEquals(0.0, viewModel.state.value.note!!.lines.single().quantityPicked, 0.0)
    }

    @Test
    fun `a line in a picking session stays online only`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 2.0).copy(inPickingSession = true)))

        viewModel.act(DeliveryNoteWrite.PickAll(lineId = LINE_ID, locationOrgStockId = LOCATION_ID))

        assertEquals(emptyList<QueuedAction>(), offline.actions.value)
        assertEquals(DeliveryNoteEvent.NeedsConnection(REFERENCE), viewModel.state.value.event)
    }

    @Test
    fun `offline, a status change opens the online only screen and sends nothing`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 0.0, picked = 1.0)))

        viewModel.act(DeliveryNoteWrite.SetAsPicked(pickedBayId = 2))

        assertEquals(emptyList<DeliveryNoteWrite>(), api.writes)
        assertEquals(DeliveryNoteEvent.NeedsConnection(REFERENCE), viewModel.state.value.event)
        assertFalse(viewModel.state.value.isWorking)
    }

    @Test
    fun `online with queued work, a status change sends the queue first`() {
        api.note = note(line = line(toPick = 1.0))
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "picked", line = line(picked = 1.0, toPick = 0.0)), status = "done", message = null))
        val viewModel = viewModel()
        offline.add(queuedPick(localId = 3, quantity = 1.0))

        viewModel.act(DeliveryNoteWrite.SetAsPicked(pickedBayId = 2))

        assertEquals(listOf(record), offline.flushed)
        assertEquals(listOf<DeliveryNoteWrite>(DeliveryNoteWrite.SetAsPicked(pickedBayId = 2)), api.writes)
        assertEquals("picked", viewModel.state.value.note?.state)
    }

    @Test
    fun `online, the note reloads when queued work leaves the queue after a sync`() {
        api.note = note(line = line(toPick = 1.0))
        val viewModel = viewModel()
        offline.add(queuedPick(localId = 3, quantity = 1.0))
        val loadsBefore = api.noteLoads

        api.note = note(line = line(picked = 1.0, toPick = 0.0))
        offline.actions.value = emptyList()

        assertEquals(loadsBefore + 1, api.noteLoads)
        assertEquals(1.0, viewModel.state.value.note?.lines?.single()?.quantityPicked ?: 0.0, 0.0)
    }

    @Test
    fun `offline, settled work does not reload until the phone is back online`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 1.0)))
        offline.add(queuedPick(localId = 3, quantity = 1.0))
        val loadsBefore = api.noteLoads

        offline.actions.value = emptyList()

        assertEquals(loadsBefore, api.noteLoads)
        assertFalse(viewModel.state.value.isOnline)
    }

    @Test
    fun `when the queue does not clear, the status change waits and says what needs the person`() {
        api.note = note(line = line(toPick = 1.0))
        val viewModel = viewModel()
        offline.add(queuedPick(localId = 3, quantity = 1.0, state = QueuedActionState.NEEDS_YOU, sequence = 9))
        offline.flushResult = false

        viewModel.act(DeliveryNoteWrite.SetAsPicked(pickedBayId = 2))

        assertEquals(listOf(record), offline.flushed)
        assertEquals(emptyList<DeliveryNoteWrite>(), api.writes)
        assertEquals(DeliveryNoteEvent.QueuedWorkLeft(needsYou = 1, waiting = 0), viewModel.state.value.event)
        assertFalse(viewModel.state.value.isWorking)
    }

    @Test
    fun `a status change with nothing queued does not flush`() {
        api.note = note(line = line(toPick = 1.0))
        api.writeResult = GoodsOutResult.Loaded(NoteActionOutcome(note = note(state = "picked"), status = "done", message = null))
        val viewModel = viewModel()

        viewModel.act(DeliveryNoteWrite.TakeOver)

        assertEquals(emptyList<OfflineRecordKey>(), offline.flushed)
        assertEquals(listOf<DeliveryNoteWrite>(DeliveryNoteWrite.TakeOver), api.writes)
    }

    @Test
    fun `offline, scan to pick matches the lines' barcodes and queues a pick of one`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 3.0), settings = PickingSettings(allowScanToPick = true)))

        viewModel.scanToPick(" 5060001 ")

        val state = viewModel.state.value
        assertEquals(emptyList<ScanPickRequest>(), api.scans)
        assertEquals(pickPayload(quantity = 1, id = "req-1"), offline.actions.value.single().payload)
        assertEquals(ScanLogEntry(code = "5060001", at = now, status = ScanPickStatus.PICKED, quantity = 1.0, isQueued = true), state.scanLog.first())
        assertEquals(ScanTarget(lineId = LINE_ID, barcode = "5060001", locationCode = "A-01-03", locationOrgStockId = LOCATION_ID), state.scanTarget)

        viewModel.pickAllScanned()

        assertEquals(pickPayload(quantity = 2, id = "req-2"), offline.actions.value.last().payload)
        assertNull(viewModel.state.value.scanTarget)
        assertEquals(DeliveryNoteEvent.LineDone(code = "AWO-12", isEverythingPicked = true), viewModel.state.value.event)
    }

    @Test
    fun `offline, an unknown code or a line in a picking session is refused without queueing`() {
        val viewModel = offlineViewModel(note(line = line(toPick = 3.0).copy(inPickingSession = true), settings = PickingSettings(allowScanToPick = true)))

        viewModel.scanToPick("999")
        assertEquals(ScanPickStatus.NOT_FOUND, viewModel.state.value.scanLog.first().status)
        assertEquals(DeliveryNoteEvent.ScanFeedback(message = null), viewModel.state.value.event)

        viewModel.scanToPick("5060001")
        assertEquals(ScanLogStatus.ONLINE_ONLY, viewModel.state.value.scanLog.first().status)
        assertEquals(emptyList<QueuedAction>(), offline.actions.value)
    }

    @Test
    fun `the overlay of an applied action is kept only on a copy downloaded before it was done`() {
        val applied = queuedPick(localId = 1, quantity = 2.0, state = QueuedActionState.APPLIED, sequence = 5)
        val base = note(line = line(toPick = 4.0))

        assertEquals(2.0, base.withQueued(listOf(applied), copySeenAt = seenAt).lines.single().quantityPicked, 0.0)
        assertEquals(0.0, base.withQueued(listOf(applied), copySeenAt = now.plusSeconds(60)).lines.single().quantityPicked, 0.0)
        assertEquals(0.0, base.withQueued(listOf(applied), copySeenAt = null).lines.single().quantityPicked, 0.0)
    }

    @Test
    fun `sko labels follow the server rule of whole packs and loose units, otherwise none`() {
        assertEquals("28 10/16", skoLabel(28.625, 16.0))
        assertEquals("66 4/6", skoLabel(66.666667, 6.0))
        assertEquals("3", skoLabel(3.0, 6.0))
        assertEquals("1/6", skoLabel(1.0 / 6.0, 6.0))
        assertNull(skoLabel(32.673667, 6.0))
        assertEquals("32 4/6", skoLabel(32.673667, 6.0, roundToUnits = true))
        assertNull(skoLabel(2.5, 1.0))
        assertNull(skoLabel(2.5, null))
    }

    @Test
    fun `a queued pick keeps the location stock in packs and loose units`() {
        val base = note(line = line(toPick = 2.0).let { it.copy(orgStock = it.orgStock?.copy(packedIn = 16.0), locations = it.locations.map { location -> location.copy(stock = 30.625, stockLabel = "30 10/16") }) })

        val line = base.withQueued(listOf(queuedPick(localId = 1, quantity = 2.0)), copySeenAt = null).lines.single()

        assertEquals("28 10/16", line.locations.single().stockLabel)
        assertEquals("2", line.quantityPickedLabel)
    }

    @Test
    fun `offline scan matching ignores case and surrounding spaces`() {
        val scanned = line().copy(barcodeValues = listOf("505579657449N"))

        assertTrue(scanned.matchesScan(" 505579657449n "))
    }

    @Test
    fun `passing to customer services moves waiting from the warehouse, capped at what is outstanding`() {
        val base = note(line = line(toPick = 3.0, picked = 1.0).copy(quantityRequired = 6.0, quantityWaitingWarehouse = 2.0))
        val crm = queuedAction(localId = 1, operation = OfflineOperation.SET_WAITING_CRM, quantity = 9.0)

        val line = base.withQueued(listOf(crm), copySeenAt = null).lines.single()

        assertEquals(5.0, line.quantityWaitingCrm, 0.0)
        assertEquals(0.0, line.quantityWaitingWarehouse, 0.0)
        assertEquals(0.0, line.quantityToPick, 0.0)
    }

    private fun viewModel(): DeliveryNoteViewModel =
        DeliveryNoteViewModel(api = api, sessions = sessions, deliveryNoteId = NOTE_ID, clock = { now }, offline = offline).also { it.onStart() }

    private fun offlineViewModel(note: DeliveryNote): DeliveryNoteViewModel {
        offline.online.value = false
        val handoff = DeliveryNoteHandoff().apply { hand(note) }
        return DeliveryNoteViewModel(api = api, sessions = sessions, deliveryNoteId = NOTE_ID, handoff = handoff, clock = { now }, offline = offline)
    }

    private fun pickPayload(quantity: Int, id: String): JsonObject = buildJsonObject {
        put("delivery_note_id", NOTE_ID)
        put("delivery_note_item_id", LINE_ID)
        put("location_org_stock_id", LOCATION_ID)
        put("quantity", quantity)
        put("client_request_id", id)
    }

    private fun queuedPick(localId: Long, quantity: Double, state: QueuedActionState = QueuedActionState.WAITING, sequence: Long? = null): QueuedAction =
        queuedAction(localId, OfflineOperation.PICK, quantity, state, sequence)

    private fun queuedAction(
        localId: Long,
        operation: OfflineOperation,
        quantity: Double,
        state: QueuedActionState = QueuedActionState.WAITING,
        sequence: Long? = null,
    ): QueuedAction = QueuedAction(
        localId = localId,
        userId = 42,
        warehouseId = WAREHOUSE_ID,
        operation = operation,
        record = record,
        payload = buildJsonObject {
            put("delivery_note_id", NOTE_ID)
            put("delivery_note_item_id", LINE_ID)
            put("location_org_stock_id", LOCATION_ID)
            put("quantity", quantity)
            put("client_request_id", "queued-$localId")
        },
        clientRequestId = "queued-$localId",
        performedAt = now,
        timeSourceIsServer = true,
        seenAt = seenAt,
        sequence = sequence,
        state = state,
        result = null,
    )

    private fun line(picked: Double = 0.0, toPick: Double = 1.0, pickedAtLocation: Double? = null): DeliveryNoteLine = DeliveryNoteLine(
        id = LINE_ID,
        orgStock = LineOrgStock(id = 812, code = "AWO-12", name = "Lavender oil"),
        quantityRequired = picked + toPick,
        quantityPicked = picked,
        quantityToPick = toPick,
        quantityToPickLabel = "label",
        locations = listOf(LineLocation(locationOrgStockId = LOCATION_ID, code = "A-01-03", stock = 30.0, quantityPicked = pickedAtLocation)),
        barcodeValues = listOf("5060001"),
    )

    private fun note(
        state: String = "handling",
        line: DeliveryNoteLine = line(),
        settings: PickingSettings = PickingSettings(),
        counts: DeliveryNoteLineCounts = DeliveryNoteLineCounts(),
    ): DeliveryNote = DeliveryNote(
        id = NOTE_ID,
        reference = REFERENCE,
        state = state,
        warehouseId = WAREHOUSE_ID,
        isEditable = true,
        allowActions = true,
        canHandle = true,
        lines = listOf(line),
        settings = settings,
        lineCounts = counts,
    )

    private class FakeOfflineWork : OfflineWork {
        val online = MutableStateFlow(true)
        val actions = MutableStateFlow<List<QueuedAction>>(emptyList())
        val undone = mutableListOf<Long>()
        val flushed = mutableListOf<OfflineRecordKey>()
        var flushResult = true
        var download: DownloadedRecord? = null
        private var nextLocalId = 1L
        private var nextRequestId = 0

        override val isOnline: StateFlow<Boolean> = online
        override val needsYouCount: StateFlow<Int> = MutableStateFlow(0)
        override val waitingCount: StateFlow<Int> = MutableStateFlow(0)
        override val queue: StateFlow<List<QueuedAction>> = actions

        fun add(action: QueuedAction) {
            actions.value += action
        }

        override fun shouldQueue(record: OfflineRecordKey): Boolean =
            !online.value || actions.value.any { it.record == record && (it.state == QueuedActionState.WAITING || it.state == QueuedActionState.SENDING) }

        override fun enqueue(
            warehouseId: Long,
            operation: OfflineOperation,
            record: OfflineRecordKey,
            payload: JsonObject,
            clientRequestId: String,
            seenAt: Instant?,
        ): QueuedAction = QueuedAction(
            localId = nextLocalId++,
            userId = 42,
            warehouseId = warehouseId,
            operation = operation,
            record = record,
            payload = payload,
            clientRequestId = clientRequestId,
            performedAt = Instant.parse("2026-11-20T10:00:00Z"),
            timeSourceIsServer = true,
            seenAt = seenAt,
            sequence = null,
            state = QueuedActionState.WAITING,
            result = null,
        ).also { add(it) }

        override fun pendingFor(record: OfflineRecordKey): StateFlow<List<QueuedAction>> = actions

        override fun undo(localId: Long) {
            undone += localId
            actions.value = actions.value.filterNot { it.localId == localId }
        }

        override suspend fun downloaded(record: OfflineRecordKey): DownloadedRecord? = download

        override fun syncNow(reason: SyncReason) = Unit

        override suspend fun flush(record: OfflineRecordKey): Boolean {
            flushed += record
            if (flushResult) {
                actions.value = emptyList()
            }
            return flushResult
        }

        override fun newClientRequestId(): String = "req-${++nextRequestId}"
    }

    private class FakeGoodsOutApi : GoodsOutApi {
        var note: DeliveryNote? = null
        var writeResult: GoodsOutResult<NoteActionOutcome> = GoodsOutResult.Unreachable
        val writes = mutableListOf<DeliveryNoteWrite>()
        val scans = mutableListOf<ScanPickRequest>()
        var noteLoads = 0

        override suspend fun deliveryNotes(token: String, warehouseId: Long, query: DeliveryNoteQuery): GoodsOutResult<DeliveryNotePage> = GoodsOutResult.Unreachable

        override suspend fun deliveryNoteCounts(token: String, warehouseId: Long, filter: DeliveryNoteCountsFilter): GoodsOutResult<DeliveryNoteCounts> = GoodsOutResult.Unreachable

        override suspend fun deliveryNote(token: String, deliveryNoteId: Long): GoodsOutResult<DeliveryNote> {
            noteLoads++
            return note?.let { GoodsOutResult.Loaded(it) } ?: GoodsOutResult.Unreachable
        }

        override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable

        override suspend fun write(token: String, deliveryNoteId: Long, write: DeliveryNoteWrite): GoodsOutResult<NoteActionOutcome> {
            writes += write
            return writeResult
        }

        override suspend fun scanPick(token: String, deliveryNoteId: Long, request: ScanPickRequest): GoodsOutResult<ScanPickOutcome> {
            scans += request
            return GoodsOutResult.Unreachable
        }

        override suspend fun trolleys(token: String, warehouseId: Long, availableOnly: Boolean): GoodsOutResult<List<Trolley>> = GoodsOutResult.Unreachable

        override suspend fun pickedBays(token: String, warehouseId: Long): GoodsOutResult<List<PickedBay>> = GoodsOutResult.Unreachable

        override suspend fun pickers(token: String, warehouseId: Long): GoodsOutResult<List<Picker>> = GoodsOutResult.Unreachable

        override suspend fun scanPack(token: String, deliveryNoteId: Long, request: ScanPackRequest): GoodsOutResult<ScanPackOutcome> = GoodsOutResult.Unreachable

        override suspend fun shippers(token: String, deliveryNoteId: Long): GoodsOutResult<ShipperList> = GoodsOutResult.Unreachable

        override suspend fun downloadDocument(token: String, url: String, target: File): AttachmentDownload = AttachmentDownload.Unreachable

        override suspend fun downloadInsertPdf(token: String, deliveryNoteId: Long, leafletId: Long, target: File): AttachmentDownload = AttachmentDownload.Unreachable
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
        const val NOTE_JSON = """{"id":9912,"reference":"AR24811","state":"handling","warehouse_id":1,"is_editable":true,"allow_actions":true,
            "lines":[{"id":41001,"quantity_required":3,"quantity_to_pick":3,"barcodes":["5060001"],
            "locations":[{"location_org_stock_id":5501,"code":"A-01-03","stock":30,"version":4}]}]}"""
    }
}
