package io.aiku.droid.ui.mywork

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MyWorkCounts
import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.MyWorkPage
import io.aiku.droid.data.api.MyWorkRow
import io.aiku.droid.data.cache.testScreenCache
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
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
class MyWorkViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeMyWorkApi()
    private val note = MyWorkRow(kind = MyWorkKind.DeliveryNote, id = 123, reference = "AR24811", state = "handling", step = "picking")
    private val session = MyWorkRow(kind = MyWorkKind.PickingSession, id = 9, reference = "PS-9", state = "in_process", step = "to_start")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `opening loads this warehouse's work in the order received`() {
        api.work = GoodsOutResult.Loaded(MyWorkPage(rows = listOf(session, note), counts = MyWorkCounts(total = 2, deliveryNotes = 1, pickingSessions = 1)))
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = 4, cache = testScreenCache())

        assertFalse(work.state.value.hasLoaded)

        work.onStart()

        assertEquals(listOf(4L), api.workRequests)
        assertEquals(listOf(session, note), work.state.value.rows)
        assertEquals(2, work.state.value.counts?.total)
        assertTrue(work.state.value.hasLoaded)
        assertFalse(work.state.value.isEmpty)
    }

    @Test
    fun `pull to refresh reloads and replaces the list`() {
        api.work = GoodsOutResult.Loaded(MyWorkPage(rows = listOf(note, session)))
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = 4, cache = testScreenCache())
        work.onStart()

        api.work = GoodsOutResult.Loaded(MyWorkPage(rows = listOf(session)))
        work.refresh()

        assertEquals(2, api.workRequests.size)
        assertEquals(listOf(session), work.state.value.rows)
        assertFalse(work.state.value.isRefreshing)
    }

    @Test
    fun `nothing assigned shows the empty state`() {
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = 4, cache = testScreenCache())

        work.onStart()

        assertTrue(work.state.value.isEmpty)
    }

    @Test
    fun `without a warehouse the list is empty and nothing is asked`() {
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = null, cache = testScreenCache())

        work.onStart()

        assertTrue(api.workRequests.isEmpty())
        assertTrue(work.state.value.isEmpty)
    }

    @Test
    fun `a failed load shows the problem and retry clears it`() {
        api.work = GoodsOutResult.Unreachable
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = 4, cache = testScreenCache())

        work.onStart()

        assertEquals(GoodsOutProblem.Offline, work.state.value.problem)
        assertFalse(work.state.value.isEmpty)

        api.work = GoodsOutResult.Loaded(MyWorkPage(rows = listOf(note)))
        work.retry()

        assertNull(work.state.value.problem)
        assertEquals(listOf(note), work.state.value.rows)
    }

    @Test
    fun `a failed refresh keeps the rows already shown`() {
        api.work = GoodsOutResult.Loaded(MyWorkPage(rows = listOf(note)))
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = 4, cache = testScreenCache())
        work.onStart()

        api.work = GoodsOutResult.ServerError(500)
        work.refresh()

        assertEquals(listOf(note), work.state.value.rows)
        assertEquals(GoodsOutProblem.ServerError, work.state.value.problem)
        assertFalse(work.state.value.isRefreshing)
    }

    @Test
    fun `another warehouse answers not found`() {
        api.work = GoodsOutResult.NotFound("")
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = 99, cache = testScreenCache())

        work.onStart()

        assertEquals(GoodsOutProblem.NotFound, work.state.value.problem)
    }

    @Test
    fun `an expired session asks to sign in again`() {
        api.work = GoodsOutResult.SessionExpired
        val work = MyWorkViewModel(api, FakeSessions(), warehouseId = 4, cache = testScreenCache())

        work.onStart()

        assertTrue(work.state.value.isSessionExpired)
    }
}
