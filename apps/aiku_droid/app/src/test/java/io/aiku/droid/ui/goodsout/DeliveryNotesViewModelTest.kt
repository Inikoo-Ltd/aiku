package io.aiku.droid.ui.goodsout

import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DeliveryNoteCounts
import io.aiku.droid.data.api.DeliveryNotePage
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.DeliveryNoteSort
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.data.api.DispatchPerson
import io.aiku.droid.data.api.DispatchRole
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutPagination
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.NoteActionOutcome
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.data.api.Picker
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.data.api.PickingSession
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.PickingSessionPage
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.api.PickingSessionWrite
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScanPackOutcome
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPickOutcome
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.ScannedSko
import io.aiku.droid.data.api.SessionActionOutcome
import io.aiku.droid.data.api.SessionScanPackOutcome
import io.aiku.droid.data.api.SessionScanPickOutcome
import io.aiku.droid.data.api.ShipperList
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.goodsout.extra.PickingSessionHandoff
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.scan.ScanProblem
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
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.io.File
import java.time.LocalDate

@OptIn(ExperimentalCoroutinesApi::class)
class DeliveryNotesViewModelTest {

    private val today = LocalDate.of(2026, 11, 12)
    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana"))
    private val api = FakeGoodsOutApi()
    private val pickingSessions = FakePickingSessions()
    private val handoff = PickingSessionHandoff()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `opening the list loads the counts and the first To do page`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(rows(1L..2L))
        api.counts = DeliveryNoteCounts(buckets = mapOf("unassigned" to 2, "dispatched" to 5821))
        val viewModel = viewModel()

        viewModel.onStart()

        val state = viewModel.state.value
        assertEquals(listOf(1L, 2L), state.list.notes.map { it.id })
        assertEquals(2, state.list.total)
        assertFalse(state.list.hasMore)
        assertTrue(state.canWorkInWarehouse)
        assertEquals(5821, state.counts?.count(DeliveryNoteBucket.Dispatched))
        assertEquals(DeliveryNoteQuery(bucket = DeliveryNoteBucket.Unassigned, page = 1, perPage = PER_PAGE), api.queries.single())
        assertEquals(listOf<PickerFilter?>(null), api.countRequests)
    }

    @Test
    fun `switching bucket replaces the rows with the first page of the new bucket`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(rows(1L..2L))
        api.pages[DeliveryNoteBucket.Handling] = listOf(rows(10L..10L))
        val viewModel = viewModel()
        viewModel.onStart()

        viewModel.selectBucket(DeliveryNoteBucket.Handling)

        assertEquals(DeliveryNoteBucket.Handling, viewModel.state.value.filter.bucket)
        assertEquals(listOf(10L), viewModel.state.value.list.notes.map { it.id })
        assertEquals(DeliveryNoteQuery(bucket = DeliveryNoteBucket.Handling, page = 1, perPage = PER_PAGE), api.queries.last())
        assertEquals(1, api.countRequests.size)
    }

    @Test
    fun `filters go to the server and a new picker reloads the counts`() {
        val viewModel = viewModel()
        viewModel.onStart()

        viewModel.applyFilter(
            DeliveryNoteFilter(
                bucket = DeliveryNoteBucket.Unassigned,
                channel = DispatchChannel.Dropshipping,
                picker = PickerFilter.Me,
                date = DateFilter.LastSevenDays,
                search = " AR248 ",
            ).withSort(DeliveryNoteSort.Customer),
        )

        assertEquals(
            DeliveryNoteQuery(
                bucket = DeliveryNoteBucket.Unassigned,
                channel = DispatchChannel.Dropshipping,
                search = "AR248",
                from = LocalDate.of(2026, 11, 6),
                to = today,
                picker = PickerFilter.Me,
                sort = DeliveryNoteSort.Customer,
                page = 1,
                perPage = PER_PAGE,
            ),
            api.queries.last(),
        )
        assertEquals(listOf(null, PickerFilter.Me), api.countRequests)
        assertEquals(4, viewModel.state.value.filter.activeFilterCount)

        viewModel.clearFilters()

        assertEquals(DeliveryNoteFilter(bucket = DeliveryNoteBucket.Unassigned), viewModel.state.value.filter)
        assertEquals(DeliveryNoteQuery(bucket = DeliveryNoteBucket.Unassigned, page = 1, perPage = PER_PAGE), api.queries.last())
    }

    @Test
    fun `the default sort of a bucket is never sent`() {
        val filter = DeliveryNoteFilter(bucket = DeliveryNoteBucket.Dispatched)

        assertEquals(DeliveryNoteSort.DateNewest, filter.shownSort)
        assertNull(filter.withSort(DeliveryNoteSort.DateNewest).sort)
        assertEquals(DeliveryNoteSort.DateOldest, filter.withSort(DeliveryNoteSort.DateOldest).sort)
        assertEquals(DeliveryNoteSort.DateOldest, DeliveryNoteFilter().shownSort)
        assertEquals(today.minusDays(1)..today.minusDays(1), DateFilter.Yesterday.range(today))
    }

    @Test
    fun `scrolling loads the next page until the last one`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(rows(1L..2L), rows(3L..4L), rows(5L..5L))
        val viewModel = viewModel()
        viewModel.onStart()

        assertTrue(viewModel.state.value.list.canLoadMoreOnScroll)

        viewModel.loadMore()
        viewModel.loadMore()
        viewModel.loadMore()

        assertEquals(listOf(1L, 2L, 3L, 4L, 5L), viewModel.state.value.list.notes.map { it.id })
        assertEquals(listOf(1, 2, 3), api.queries.map { it.page })
        assertFalse(viewModel.state.value.list.hasMore)
    }

    @Test
    fun `a failed page shows the problem and retry asks for that page again`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(rows(1L..2L), rows(3L..4L))
        val viewModel = viewModel()
        viewModel.onStart()
        api.failure = GoodsOutResult.Unreachable

        viewModel.loadMore()

        assertEquals(GoodsOutProblem.Offline, viewModel.state.value.list.nextPageProblem)
        assertFalse(viewModel.state.value.list.canLoadMoreOnScroll)

        api.failure = null
        viewModel.retry()

        assertEquals(listOf(1L, 2L, 3L, 4L), viewModel.state.value.list.notes.map { it.id })
        assertNull(viewModel.state.value.list.problem)
    }

    @Test
    fun `no right on the warehouse shows on the first page`() {
        api.failure = GoodsOutResult.Forbidden("This action is unauthorized.")
        val viewModel = viewModel()

        viewModel.onStart()

        assertEquals(GoodsOutProblem.Forbidden, viewModel.state.value.list.firstPageProblem)
    }

    @Test
    fun `an expired session asks to sign in again`() {
        api.failure = GoodsOutResult.SessionExpired
        val viewModel = viewModel()

        viewModel.onStart()

        assertTrue(viewModel.state.value.isSessionExpired)
    }

    @Test
    fun `a hardware scan opens the matching delivery note or says there is none`() {
        val note = row(77L)
        val viewModel = viewModel()
        viewModel.onStart()

        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "AR24811", type = ScanMatch.DELIVERY_NOTE, deliveryNote = note))
        viewModel.lookUpScan(" AR24811 ")
        assertEquals(ListScanOutcome.Open(note), viewModel.state.value.scanOutcome)
        assertEquals("AR24811", api.scannedCodes.last())

        viewModel.scanOutcomeHandled()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "5060", type = ScanMatch.SKO, sko = ScannedSko(id = 1, code = "AWO-12")))
        viewModel.lookUpScan("5060")
        assertEquals(ListScanOutcome.NoDeliveryNote("5060"), viewModel.state.value.scanOutcome)

        api.scanResult = GoodsOutResult.NotFound("")
        viewModel.lookUpScan("999")
        assertEquals(ListScanOutcome.NoDeliveryNote("999"), viewModel.state.value.scanOutcome)

        api.scanResult = GoodsOutResult.Unreachable
        viewModel.lookUpScan("998")
        assertEquals(ListScanOutcome.Failed("998", ScanProblem.Offline), viewModel.state.value.scanOutcome)
        assertNull(viewModel.state.value.lookingUpCode)
    }

    @Test
    fun `the sheet total comes from the list for the applied filter`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(rows(1L..3L))
        val viewModel = viewModel()
        viewModel.onStart()

        assertEquals(3, viewModel.state.value.totalFor(viewModel.state.value.filter))
        assertNull(viewModel.state.value.totalFor(viewModel.state.value.filter.copy(channel = DispatchChannel.B2c)))
    }

    @Test
    fun `selecting delivery notes and creating a session opens the new session`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(listOf(row(9).copy(canAddToPickingSession = true), row(10).copy(canAddToPickingSession = true)), listOf(row(11)))
        val created = PickingSession(id = 12, reference = "PS-0012", state = "in_process")
        pickingSessions.created = GoodsOutResult.Loaded(created)
        val viewModel = viewModel()
        viewModel.onStart()
        val (first, second) = viewModel.state.value.list.notes

        viewModel.toggleSelection(first)
        assertFalse(viewModel.state.value.isSelecting)

        viewModel.startSelection(first)
        viewModel.toggleSelection(second)
        viewModel.toggleSelection(row(11))
        assertEquals(setOf(9L, 10L), viewModel.state.value.selection)

        viewModel.submitSelection()

        val state = viewModel.state.value
        assertEquals(listOf(WAREHOUSE_ID to listOf(9L, 10L)), pickingSessions.creates)
        assertEquals(SelectionOutcome.OpenSession(PickingSessionRoute(id = 12, reference = "PS-0012")), state.selectionOutcome)
        assertNull(state.selection)
        assertEquals(created, handoff.take(12))
    }

    @Test
    fun `unselecting the last note leaves selection, and a refusal keeps the selection`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(listOf(row(9).copy(canAddToPickingSession = true)))
        pickingSessions.created = GoodsOutResult.Invalid("DN-9 is already in a picking session.")
        val viewModel = viewModel()
        viewModel.onStart()
        val note = viewModel.state.value.list.notes.single()

        viewModel.startSelection(note)
        viewModel.toggleSelection(note)
        assertNull(viewModel.state.value.selection)

        viewModel.startSelection(note)
        viewModel.submitSelection()

        assertEquals(SelectionOutcome.Refused(message = "DN-9 is already in a picking session.", problem = GoodsOutProblem.ServerError), viewModel.state.value.selectionOutcome)
        assertEquals(setOf(9L), viewModel.state.value.selection)
    }

    @Test
    fun `adding to a session starts in selection and posts the notes to that session`() {
        api.pages[DeliveryNoteBucket.Unassigned] = listOf(listOf(row(9).copy(canAddToPickingSession = true)))
        pickingSessions.written = GoodsOutResult.Loaded(SessionActionOutcome(session = PickingSession(id = 12), status = "done", message = "1 delivery note added"))
        val viewModel = DeliveryNotesViewModel(
            api = api,
            sessions = sessions,
            warehouseId = WAREHOUSE_ID,
            today = { today },
            pickingSessions = pickingSessions,
            addToSession = SessionTarget(id = 12, reference = "PS-0012"),
            perPage = PER_PAGE,
            sessionHandoff = handoff,
        )
        viewModel.onStart()
        assertEquals(emptySet<Long>(), viewModel.state.value.selection)

        viewModel.toggleSelection(viewModel.state.value.list.notes.single())
        viewModel.submitSelection()

        assertEquals(listOf(12L to PickingSessionWrite.AddDeliveryNotes(listOf(9L))), pickingSessions.writes)
        assertEquals(SelectionOutcome.NotesAdded("1 delivery note added"), viewModel.state.value.selectionOutcome)
        assertEquals(emptySet<Long>(), viewModel.state.value.selection)
    }

    private fun viewModel(): DeliveryNotesViewModel =
        DeliveryNotesViewModel(api = api, sessions = sessions, warehouseId = WAREHOUSE_ID, today = { today }, pickingSessions = pickingSessions, perPage = PER_PAGE, sessionHandoff = handoff)

    private class FakePickingSessions : PickingSessionApi {
        var created: GoodsOutResult<PickingSession> = GoodsOutResult.Unreachable
        var written: GoodsOutResult<SessionActionOutcome> = GoodsOutResult.Unreachable
        val creates = mutableListOf<Pair<Long, List<Long>>>()
        val writes = mutableListOf<Pair<Long, PickingSessionWrite>>()

        override suspend fun pickingSessions(token: String, warehouseId: Long, state: PickingSessionState?, page: Int): GoodsOutResult<PickingSessionPage> =
            GoodsOutResult.Unreachable

        override suspend fun pickingSession(token: String, sessionId: Long): GoodsOutResult<PickingSession> = GoodsOutResult.Unreachable

        override suspend fun createPickingSession(token: String, warehouseId: Long, deliveryNoteIds: List<Long>): GoodsOutResult<PickingSession> {
            creates += warehouseId to deliveryNoteIds
            return created
        }

        override suspend fun writeSession(token: String, sessionId: Long, write: PickingSessionWrite): GoodsOutResult<SessionActionOutcome> {
            writes += sessionId to write
            return written
        }

        override suspend fun sessionScanPick(token: String, sessionId: Long, request: ScanPickRequest): GoodsOutResult<SessionScanPickOutcome> = GoodsOutResult.Unreachable

        override suspend fun sessionScanPack(token: String, sessionId: Long, request: ScanPackRequest): GoodsOutResult<SessionScanPackOutcome> = GoodsOutResult.Unreachable

        override suspend fun dispatchPersonnel(token: String, warehouseId: Long, role: DispatchRole): GoodsOutResult<List<DispatchPerson>> = GoodsOutResult.Unreachable
    }

    private fun rows(ids: LongRange): List<DeliveryNoteRow> = ids.map(::row)

    private fun row(id: Long): DeliveryNoteRow = DeliveryNoteRow(id = id, reference = "AR$id", state = "unassigned", stateLabel = "To do")

    private class FakeGoodsOutApi : GoodsOutApi {
        val pages = mutableMapOf<DeliveryNoteBucket, List<List<DeliveryNoteRow>>>()
        var counts = DeliveryNoteCounts()
        var failure: GoodsOutResult<Nothing>? = null
        var scanResult: GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable
        val queries = mutableListOf<DeliveryNoteQuery>()
        val countRequests = mutableListOf<PickerFilter?>()
        val scannedCodes = mutableListOf<String>()

        override suspend fun deliveryNotes(token: String, warehouseId: Long, query: DeliveryNoteQuery): GoodsOutResult<DeliveryNotePage> {
            queries += query
            failure?.let { return it }
            val bucketPages = pages[query.bucket].orEmpty()
            val notes = bucketPages.getOrNull(query.page - 1).orEmpty()
            val pagination = GoodsOutPagination(
                currentPage = query.page,
                lastPage = bucketPages.size.coerceAtLeast(1),
                perPage = query.perPage,
                total = bucketPages.sumOf { it.size },
            )
            return GoodsOutResult.Loaded(DeliveryNotePage(notes = notes, canWorkInWarehouse = true, pagination = pagination))
        }

        override suspend fun deliveryNoteCounts(token: String, warehouseId: Long, picker: PickerFilter?): GoodsOutResult<DeliveryNoteCounts> {
            countRequests += picker
            return failure ?: GoodsOutResult.Loaded(counts)
        }

        override suspend fun deliveryNote(token: String, deliveryNoteId: Long): GoodsOutResult<DeliveryNote> = GoodsOutResult.Unreachable

        override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> {
            scannedCodes += code
            return scanResult
        }

        override suspend fun write(token: String, deliveryNoteId: Long, write: DeliveryNoteWrite): GoodsOutResult<NoteActionOutcome> = GoodsOutResult.Unreachable

        override suspend fun scanPick(token: String, deliveryNoteId: Long, request: ScanPickRequest): GoodsOutResult<ScanPickOutcome> = GoodsOutResult.Unreachable

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
        const val WAREHOUSE_ID = 1L
        const val PER_PAGE = 2
    }
}
