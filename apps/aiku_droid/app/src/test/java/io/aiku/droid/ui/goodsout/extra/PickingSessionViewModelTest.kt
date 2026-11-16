package io.aiku.droid.ui.goodsout.extra

import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.DeliveryNoteCounts
import io.aiku.droid.data.api.DeliveryNotePage
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteRef
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.DispatchPerson
import io.aiku.droid.data.api.DispatchRole
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.LineOrgStock
import io.aiku.droid.data.api.NoteActionOutcome
import io.aiku.droid.data.api.PickListEntry
import io.aiku.droid.data.api.PickListLine
import io.aiku.droid.data.api.PickListLocation
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.data.api.Picker
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.data.api.PickingSession
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.PickingSessionPage
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.api.PickingSessionWrite
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanPackOutcome
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPickItem
import io.aiku.droid.data.api.ScanPickOutcome
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.ScanPickStatus
import io.aiku.droid.data.api.SessionActionKey
import io.aiku.droid.data.api.SessionActionOutcome
import io.aiku.droid.data.api.SessionScanPackOutcome
import io.aiku.droid.data.api.SessionScanPickOutcome
import io.aiku.droid.data.api.ShipperList
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test
import java.io.File
import java.time.Instant

@OptIn(ExperimentalCoroutinesApi::class)
class PickingSessionViewModelTest {

    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 5, username = "picker", displayName = "Ana"))
    private val api = FakeSessionApi()
    private val goodsOut = FakeTrolleys()
    private val handoff = PickingSessionHandoff()
    private val now = Instant.parse("2026-11-13T09:41:20Z")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `a session action replaces the session with the answer and says what happened`() {
        api.session = session(state = PickingSessionState.InProcess, actions = listOf(DeliveryNoteAction(SessionActionKey.START_PICKING, "Start picking")))
        val started = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 1.0), locationB()))
        api.writeResult = GoodsOutResult.Loaded(SessionActionOutcome(session = started, status = "done", message = "Picking started"))
        val viewModel = loadedViewModel()

        viewModel.act(PickingSessionWrite.StartPicking)

        val state = viewModel.state.value
        assertEquals(listOf<PickingSessionWrite>(PickingSessionWrite.StartPicking), api.writes)
        assertEquals(started, state.session)
        assertNull(state.working)
        assertEquals(SessionEvent.Acted(message = "Picking started", isNothingChanged = false), state.event)
        assertEquals(KEY_A, state.currentPickKey)
    }

    @Test
    fun `adding a trolley loads only free trolleys and a refusal keeps the session`() {
        val before = session(state = PickingSessionState.Handling)
        api.session = before
        api.writeResult = GoodsOutResult.Invalid("This trolley is in another session.", mapOf("trolley" to listOf("This trolley is in another session.")))
        val viewModel = loadedViewModel()

        viewModel.loadTrolleys()
        viewModel.act(PickingSessionWrite.AttachTrolley(3))

        assertEquals(listOf(WAREHOUSE_ID to true), goodsOut.trolleyRequests)
        assertEquals(before, viewModel.state.value.session)
        assertEquals(SessionEvent.Refused(message = "This trolley is in another session.", problem = GoodsOutProblem.ServerError), viewModel.state.value.event)
    }

    @Test
    fun `a scan that finishes the location moves on to the next location`() {
        api.session = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 1.0), locationB()), actions = scanPick)
        val viewModel = loadedViewModel()
        assertEquals(KEY_A, viewModel.state.value.currentPickKey)
        api.scanResult = GoodsOutResult.Loaded(
            SessionScanPickOutcome(
                session = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 0.0), locationB()), actions = scanPick),
                status = ScanPickStatus.PICKED,
                message = "Picked 1 for DN-9",
                item = ScanPickItem(id = LINE_A, code = "AWO-12", quantityPicked = 2.0, quantityToPick = 0.0, locationCode = "A-01-03", locationOrgStockId = 4401),
                deliveryNote = DeliveryNoteRef(id = 9, reference = "DN-9", state = "handling"),
            ),
        )

        viewModel.scanToPick(" 501 ")

        val state = viewModel.state.value
        assertEquals(listOf(ScanPickRequest(barcode = "501", quantity = 1.0)), api.scans)
        assertEquals(KEY_B, state.currentPickKey)
        assertEquals(SessionEvent.LocationDone(location = "A-01-03"), state.event)
        assertNull(state.scanTarget)
        assertEquals(1.0, state.scanLog.single().quantity, 0.0)
    }

    @Test
    fun `a scan with more to pick opens the panel, and pick all sends the line with no quantity`() {
        api.session = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 3.0), locationB()), actions = scanPick)
        val viewModel = loadedViewModel()
        api.scanResult = GoodsOutResult.Loaded(
            SessionScanPickOutcome(
                session = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 2.0), locationB()), actions = scanPick),
                status = ScanPickStatus.PICKED,
                message = null,
                item = ScanPickItem(id = LINE_A, code = "AWO-12", name = "Lavender oil", quantityPicked = 1.0, quantityToPick = 2.0, locationCode = "A-01-03", locationOrgStockId = 4401),
                deliveryNote = DeliveryNoteRef(id = 9, reference = "DN-9"),
            ),
        )

        viewModel.scanToPick("501")
        viewModel.pickAllScanned()

        assertEquals(KEY_A, viewModel.state.value.currentPickKey)
        assertEquals("DN-9", viewModel.state.value.scanTarget?.deliveryNoteReference)
        assertEquals(ScanPickRequest(barcode = "501", quantity = null, deliveryNoteItemId = LINE_A, locationOrgStockId = 4401), api.scans.last())
    }

    @Test
    fun `next location skips to the following open location and wraps round`() {
        api.session = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 1.0), locationB()))
        val viewModel = loadedViewModel()

        viewModel.nextLocation()
        assertEquals(KEY_B, viewModel.state.value.currentPickKey)

        viewModel.nextLocation()
        assertEquals(KEY_A, viewModel.state.value.currentPickKey)
    }

    @Test
    fun `when nothing is left to pick the screen is told picking is over`() {
        api.session = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 1.0)), actions = scanPick)
        val viewModel = loadedViewModel()
        api.scanResult = GoodsOutResult.Loaded(
            SessionScanPickOutcome(
                session = session(state = PickingSessionState.PickingFinished, pickList = emptyList()),
                status = ScanPickStatus.PICKED,
                message = null,
                item = ScanPickItem(id = LINE_A, code = "AWO-12", quantityPicked = 2.0, quantityToPick = 0.0),
                deliveryNote = null,
            ),
        )

        viewModel.scanToPick("501")

        assertEquals(SessionEvent.AllPicked, viewModel.state.value.event)
        assertNull(viewModel.state.value.currentPickKey)
    }

    @Test
    fun `a handed over session shows at once`() {
        val handed = session(state = PickingSessionState.Handling, pickList = listOf(locationA(toPick = 1.0)))
        handoff.hand(handed)

        val viewModel = PickingSessionViewModel(api, goodsOut, sessions, SESSION_ID, WAREHOUSE_ID, handoff, clock = { now })

        assertEquals(handed, viewModel.state.value.session)
        assertEquals(KEY_A, viewModel.state.value.currentPickKey)
    }

    private fun loadedViewModel(): PickingSessionViewModel =
        PickingSessionViewModel(api, goodsOut, sessions, SESSION_ID, WAREHOUSE_ID, handoff, clock = { now }).also { it.onStart() }

    private val scanPick = listOf(DeliveryNoteAction(SessionActionKey.SCAN_PICK, "Scan to pick"))

    private fun locationA(toPick: Double): PickListEntry = PickListEntry(
        key = KEY_A,
        location = PickListLocation(id = 5501, code = "A-01-03"),
        orgStock = LineOrgStock(id = 77, code = "AWO-12", name = "Lavender oil", barcode = "501"),
        quantityRequired = 2.0,
        quantityPicked = 2.0 - toPick,
        quantityToPick = toPick,
        lines = listOf(PickListLine(deliveryNoteItemId = LINE_A, deliveryNoteId = 9, deliveryNoteReference = "DN-9", quantityRequired = 2.0, quantityPicked = 2.0 - toPick, quantityToPick = toPick)),
    )

    private fun locationB(): PickListEntry = PickListEntry(
        key = KEY_B,
        location = PickListLocation(id = 5502, code = "B-02-01"),
        orgStock = LineOrgStock(id = 78, code = "AWO-13", name = "Rose oil", barcode = "502"),
        quantityRequired = 2.0,
        quantityToPick = 2.0,
        lines = listOf(PickListLine(deliveryNoteItemId = 803, deliveryNoteId = 10, deliveryNoteReference = "DN-10", quantityRequired = 2.0, quantityToPick = 2.0)),
    )

    private fun session(
        state: PickingSessionState,
        pickList: List<PickListEntry> = emptyList(),
        actions: List<DeliveryNoteAction> = emptyList(),
    ): PickingSession = PickingSession(
        id = SESSION_ID,
        reference = "PS-0012",
        state = state.key,
        canWork = true,
        availableActions = actions,
        pickList = pickList,
    )

    private class FakeSessionApi : PickingSessionApi {
        var session: PickingSession? = null
        var writeResult: GoodsOutResult<SessionActionOutcome> = GoodsOutResult.Unreachable
        var scanResult: GoodsOutResult<SessionScanPickOutcome> = GoodsOutResult.Unreachable
        val writes = mutableListOf<PickingSessionWrite>()
        val scans = mutableListOf<ScanPickRequest>()

        override suspend fun pickingSessions(token: String, warehouseId: Long, state: PickingSessionState?, page: Int): GoodsOutResult<PickingSessionPage> =
            GoodsOutResult.Unreachable

        override suspend fun pickingSession(token: String, sessionId: Long): GoodsOutResult<PickingSession> =
            session?.let { GoodsOutResult.Loaded(it) } ?: GoodsOutResult.Unreachable

        override suspend fun createPickingSession(token: String, warehouseId: Long, deliveryNoteIds: List<Long>): GoodsOutResult<PickingSession> =
            GoodsOutResult.Unreachable

        override suspend fun writeSession(token: String, sessionId: Long, write: PickingSessionWrite): GoodsOutResult<SessionActionOutcome> {
            writes += write
            return writeResult
        }

        override suspend fun sessionScanPick(token: String, sessionId: Long, request: ScanPickRequest): GoodsOutResult<SessionScanPickOutcome> {
            scans += request
            return scanResult
        }

        override suspend fun sessionScanPack(token: String, sessionId: Long, request: ScanPackRequest): GoodsOutResult<SessionScanPackOutcome> =
            GoodsOutResult.Unreachable

        override suspend fun dispatchPersonnel(token: String, warehouseId: Long, role: DispatchRole): GoodsOutResult<List<DispatchPerson>> =
            GoodsOutResult.Unreachable
    }

    private class FakeTrolleys : GoodsOutApi {
        val trolleyRequests = mutableListOf<Pair<Long, Boolean>>()

        override suspend fun deliveryNotes(token: String, warehouseId: Long, query: DeliveryNoteQuery): GoodsOutResult<DeliveryNotePage> = GoodsOutResult.Unreachable

        override suspend fun deliveryNoteCounts(token: String, warehouseId: Long, picker: PickerFilter?): GoodsOutResult<DeliveryNoteCounts> = GoodsOutResult.Unreachable

        override suspend fun deliveryNote(token: String, deliveryNoteId: Long): GoodsOutResult<DeliveryNote> = GoodsOutResult.Unreachable

        override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable

        override suspend fun write(token: String, deliveryNoteId: Long, write: DeliveryNoteWrite): GoodsOutResult<NoteActionOutcome> = GoodsOutResult.Unreachable

        override suspend fun scanPick(token: String, deliveryNoteId: Long, request: ScanPickRequest): GoodsOutResult<ScanPickOutcome> = GoodsOutResult.Unreachable

        override suspend fun trolleys(token: String, warehouseId: Long, availableOnly: Boolean): GoodsOutResult<List<Trolley>> {
            trolleyRequests += warehouseId to availableOnly
            return GoodsOutResult.Loaded(listOf(Trolley(id = 3, name = "T03")))
        }

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
        const val SESSION_ID = 12L
        const val WAREHOUSE_ID = 1L
        const val LINE_A = 801L
        const val KEY_A = "5501-77"
        const val KEY_B = "5502-78"
    }
}
