package io.aiku.droid.ui.goodsout

import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteCounts
import io.aiku.droid.data.api.DeliveryNotePage
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteWrite
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
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.api.PickingSessionWrite
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanPackOutcome
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPickOutcome
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.SessionActionOutcome
import io.aiku.droid.data.api.SessionScanPackOutcome
import io.aiku.droid.data.api.SessionScanPickOutcome
import io.aiku.droid.data.api.ShipperList
import io.aiku.droid.data.api.StaffRef
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.goodsout.extra.EquipmentListViewModel
import io.aiku.droid.ui.goodsout.extra.PickingSessionsViewModel
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
import org.junit.Before
import org.junit.Test
import java.io.File

@OptIn(ExperimentalCoroutinesApi::class)
class GoodsOutRefreshTest {

    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana"))
    private val goodsOut = FakeCounts()
    private val pickingSessions = FakeSessions()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `pulling the backlog reloads the counts and every personnel list already shown`() {
        goodsOut.counts = DeliveryNoteCounts(buckets = mapOf("unassigned" to 3))
        val backlog = GoodsOutBacklogViewModel(goodsOut, pickingSessions, sessions, WAREHOUSE_ID)
        backlog.onStart()
        backlog.loadPersonnel(DispatchRole.Packers)
        goodsOut.counts = DeliveryNoteCounts(buckets = mapOf("unassigned" to 5))

        backlog.refresh()

        val state = backlog.state.value
        assertFalse(state.isRefreshing)
        assertEquals(5, state.counts?.buckets?.get("unassigned"))
        assertEquals(2, goodsOut.countRequests)
        assertEquals(listOf(DispatchRole.Pickers, DispatchRole.Packers, DispatchRole.Pickers, DispatchRole.Packers), pickingSessions.personnelRequests)
    }

    @Test
    fun `pulling an equipment list fetches it again`() {
        var fetches = 0
        var trolleys = listOf(Trolley(id = 1, name = "T1"))
        val list = EquipmentListViewModel(sessions) {
            fetches++
            GoodsOutResult.Loaded(trolleys)
        }
        list.onStart()
        trolleys = listOf(Trolley(id = 1, name = "T1"), Trolley(id = 2, name = "T2"))

        list.refresh()

        assertEquals(2, fetches)
        assertEquals(trolleys, list.state.value.items)
        assertFalse(list.state.value.isRefreshing)
    }

    @Test
    fun `pulling the picking sessions reloads the first page`() {
        pickingSessions.rows = listOf(PickingSessionRow(id = 1, reference = "PS-1"))
        val list = PickingSessionsViewModel(pickingSessions, sessions, WAREHOUSE_ID)
        list.onStart()
        pickingSessions.rows = listOf(PickingSessionRow(id = 2, reference = "PS-2"))

        list.refresh()

        assertEquals(listOf(1, 1), pickingSessions.pageRequests)
        assertEquals(listOf(2L), list.state.value.list.sessions.map { it.id })
        assertFalse(list.state.value.isRefreshing)
    }

    private class FakeCounts : GoodsOutApi {
        var counts = DeliveryNoteCounts()
        var countRequests = 0

        override suspend fun deliveryNotes(token: String, warehouseId: Long, query: DeliveryNoteQuery): GoodsOutResult<DeliveryNotePage> = GoodsOutResult.Unreachable

        override suspend fun deliveryNoteCounts(token: String, warehouseId: Long, picker: PickerFilter?): GoodsOutResult<DeliveryNoteCounts> {
            countRequests++
            return GoodsOutResult.Loaded(counts)
        }

        override suspend fun deliveryNote(token: String, deliveryNoteId: Long): GoodsOutResult<DeliveryNote> = GoodsOutResult.Unreachable

        override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable

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

    private class FakeSessions : PickingSessionApi {
        var rows = emptyList<PickingSessionRow>()
        val pageRequests = mutableListOf<Int>()
        val personnelRequests = mutableListOf<DispatchRole>()

        override suspend fun pickingSessions(token: String, warehouseId: Long, state: PickingSessionState?, page: Int): GoodsOutResult<PickingSessionPage> {
            pageRequests += page
            return GoodsOutResult.Loaded(PickingSessionPage(sessions = rows, counts = emptyMap(), pagination = GoodsOutPagination(currentPage = page)))
        }

        override suspend fun pickingSession(token: String, sessionId: Long): GoodsOutResult<PickingSession> = GoodsOutResult.Unreachable

        override suspend fun createPickingSession(token: String, warehouseId: Long, deliveryNoteIds: List<Long>): GoodsOutResult<PickingSession> = GoodsOutResult.Unreachable

        override suspend fun writeSession(token: String, sessionId: Long, write: PickingSessionWrite): GoodsOutResult<SessionActionOutcome> = GoodsOutResult.Unreachable

        override suspend fun sessionScanPick(token: String, sessionId: Long, request: ScanPickRequest): GoodsOutResult<SessionScanPickOutcome> = GoodsOutResult.Unreachable

        override suspend fun sessionScanPack(token: String, sessionId: Long, request: ScanPackRequest): GoodsOutResult<SessionScanPackOutcome> = GoodsOutResult.Unreachable

        override suspend fun dispatchPersonnel(token: String, warehouseId: Long, role: DispatchRole): GoodsOutResult<List<DispatchPerson>> {
            personnelRequests += role
            return GoodsOutResult.Loaded(listOf(DispatchPerson(user = StaffRef(id = 7, name = "Ana"))))
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
        const val WAREHOUSE_ID = 3L
    }
}
