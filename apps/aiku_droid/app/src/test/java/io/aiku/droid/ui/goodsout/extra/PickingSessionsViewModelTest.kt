package io.aiku.droid.ui.goodsout.extra

import io.aiku.droid.data.api.DispatchPerson
import io.aiku.droid.data.api.DispatchRole
import io.aiku.droid.data.api.GoodsOutPagination
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PickingSession
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.PickingSessionPage
import io.aiku.droid.data.api.PickingSessionQuery
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.PickingSessionSort
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.api.PickingSessionWrite
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.SessionActionOutcome
import io.aiku.droid.data.api.SessionScanPackOutcome
import io.aiku.droid.data.api.SessionScanPickOutcome
import io.aiku.droid.data.api.StaffRef
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.SavedLists
import io.aiku.droid.data.cache.testScreenCache
import io.aiku.droid.ui.components.ListFilter
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
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
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PickingSessionsViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeSessionList()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = PickingSessionsViewModel(api, FakeSessions(), warehouseId = 1)

    @Test
    fun `opening asks for the server's default order and keeps the total`() {
        val sessions = viewModel()

        sessions.onStart()

        assertEquals(PickingSessionQuery(), api.queries.single())
        assertEquals(12, sessions.state.value.list.total)
        assertFalse(sessions.state.value.filter.isFiltered)
    }

    @Test
    fun `searching waits for typing to pause and reloads from page 1`() {
        val sessions = viewModel()
        sessions.onStart()
        sessions.loadMore()

        sessions.search("PS")
        sessions.search("PS-12 ")

        assertEquals(2, api.queries.size)

        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(PickingSessionQuery(search = "PS-12"), api.queries.last())
    }

    @Test
    fun `a sort reloads from page 1 with the state and search kept, and clearing drops it`() {
        val sessions = viewModel()
        sessions.onStart()
        sessions.selectState(PickingSessionState.Handling)
        sessions.search("PS")
        dispatcher.scheduler.advanceTimeBy(301)
        sessions.loadMore()

        sessions.applyFilter(ListFilter(sort = PickingSessionSort.Picker))

        assertEquals(PickingSessionQuery(state = PickingSessionState.Handling, search = "PS", sort = PickingSessionSort.Picker), api.queries.last())
        assertEquals(1, sessions.state.value.filter.activeCount)
        assertTrue(sessions.state.value.filter.isFiltered)

        sessions.clearFilters()

        assertEquals(PickingSessionQuery(state = PickingSessionState.Handling, search = "PS"), api.queries.last())
        assertFalse(sessions.state.value.filter.isFiltered)
    }

    @Test
    fun `offline the list shows the saved sessions, filtered and sorted on the phone, until the server answers`() = runTest {
        val cache = testScreenCache()
        val saved = listOf(
            PickingSessionRow(id = 1, reference = "PS-001", state = "handling", picker = StaffRef(5, "Zed")),
            PickingSessionRow(id = 2, reference = "PS-002", state = "handling", picker = StaffRef(6, "amy")),
            PickingSessionRow(id = 3, reference = "PS-003", state = "packing_finished"),
        )
        api.answer = { GoodsOutResult.Loaded(PickingSessionPage(sessions = saved, counts = emptyMap(), pagination = GoodsOutPagination(lastPage = 2, total = 60))) }
        val sessions = PickingSessionsViewModel(api, FakeSessions(), warehouseId = 1, cache = cache)
        sessions.onStart()
        assertEquals(3, cache.saved(1, SavedLists.pickingSessions)?.value?.size)

        api.answer = { GoodsOutResult.Unreachable }
        sessions.selectState(PickingSessionState.Handling)
        sessions.applyFilter(ListFilter(sort = PickingSessionSort.Picker))

        val offline = sessions.state.value
        assertEquals(listOf(2L, 1L), offline.list.sessions.map { it.id })
        assertEquals(CACHE_NOW, offline.lastUpdated)
        assertEquals(GoodsOutProblem.Offline, offline.list.firstPageProblem)
        assertFalse(offline.list.canLoadMore)
        assertEquals(2, offline.offlineTotalFor(ListFilter()))

        sessions.search("001")
        dispatcher.scheduler.advanceTimeBy(301)
        assertEquals(listOf(1L), sessions.state.value.list.sessions.map { it.id })

        api.answer = null
        sessions.refresh()

        assertNull(sessions.state.value.offline)
        assertNull(sessions.state.value.list.problem)
    }

    private class FakeSessionList : PickingSessionApi {
        val queries = mutableListOf<PickingSessionQuery>()
        var answer: ((PickingSessionQuery) -> GoodsOutResult<PickingSessionPage>)? = null

        override suspend fun pickingSessions(token: String, warehouseId: Long, query: PickingSessionQuery): GoodsOutResult<PickingSessionPage> {
            queries += query
            answer?.let { return it(query) }
            val rows = listOf(PickingSessionRow(id = query.page.toLong(), reference = "PS-${query.page}"))
            return GoodsOutResult.Loaded(
                PickingSessionPage(sessions = rows, counts = emptyMap(), pagination = GoodsOutPagination(currentPage = query.page, lastPage = 2, perPage = 1, total = 12)),
            )
        }

        override suspend fun pickingSession(token: String, sessionId: Long): GoodsOutResult<PickingSession> = GoodsOutResult.Unreachable

        override suspend fun createPickingSession(token: String, warehouseId: Long, deliveryNoteIds: List<Long>): GoodsOutResult<PickingSession> = GoodsOutResult.Unreachable

        override suspend fun writeSession(token: String, sessionId: Long, write: PickingSessionWrite): GoodsOutResult<SessionActionOutcome> = GoodsOutResult.Unreachable

        override suspend fun sessionScanPick(token: String, sessionId: Long, request: ScanPickRequest): GoodsOutResult<SessionScanPickOutcome> = GoodsOutResult.Unreachable

        override suspend fun sessionScanPack(token: String, sessionId: Long, request: ScanPackRequest): GoodsOutResult<SessionScanPackOutcome> = GoodsOutResult.Unreachable

        override suspend fun dispatchPersonnel(token: String, warehouseId: Long, role: DispatchRole): GoodsOutResult<List<DispatchPerson>> = GoodsOutResult.Unreachable
    }
}
