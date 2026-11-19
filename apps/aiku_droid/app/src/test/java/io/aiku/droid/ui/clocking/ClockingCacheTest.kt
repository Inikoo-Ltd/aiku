package io.aiku.droid.ui.clocking

import io.aiku.droid.data.api.ClockingApi
import io.aiku.droid.data.api.ClockingNoteResult
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.data.api.QrClockingRequest
import io.aiku.droid.data.api.QrClockingResult
import io.aiku.droid.data.api.ShiftSchedule
import io.aiku.droid.data.api.TimesheetPeriod
import io.aiku.droid.data.cache.CACHE_NOW
import io.aiku.droid.data.cache.CachedBodies
import io.aiku.droid.data.cache.CachedScreens
import io.aiku.droid.data.cache.save
import io.aiku.droid.data.cache.testScreenCache
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
import java.time.LocalDate

@OptIn(ExperimentalCoroutinesApi::class)
class ClockingCacheTest {

    private val api = GatedClockingApi()
    private val sessions = FakeSessions()
    private val cache = testScreenCache(sessions)
    private val fresh = ClockingStatus(status = "clocked_out", methods = listOf("pin"), code = "4711", hasShifts = false)

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `the saved status and week show at once, but nothing can act on them until the fresh status arrives`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingThisWeek, CachedBodies.thisWeek(workingSeconds = 3600))
        api.gate = CompletableDeferred()
        api.status = ClockingResult.Loaded(fresh)

        val clocking = ClockingViewModel(api, sessions, cache)

        assertTrue(clocking.state.value.status?.isClockedIn == true)
        assertEquals(3600L, clocking.state.value.thisWeek?.timesheets?.single()?.workingSeconds)
        assertTrue(clocking.state.value.isStatusCached)
        assertTrue(clocking.state.value.isRefreshing)
        assertNull(clocking.state.value.actionableStatus)

        api.gate?.complete(Unit)

        assertEquals(fresh, clocking.state.value.status)
        assertEquals(fresh, clocking.state.value.actionableStatus)
        assertFalse(clocking.state.value.isStatusCached)
        assertFalse(clocking.state.value.isRefreshing)
    }

    @Test
    fun `a failed refresh keeps the saved status for display only and says when it was last updated`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))
        api.status = ClockingResult.Unreachable

        val clocking = ClockingViewModel(api, sessions, cache)

        assertTrue(clocking.state.value.status?.isClockedIn == true)
        assertEquals(ClockingProblem.Offline, clocking.state.value.statusProblem)
        assertEquals(CACHE_NOW, clocking.state.value.lastUpdated)
        assertNull(clocking.state.value.actionableStatus)

        clocking.refresh()

        assertNull(clocking.state.value.actionableStatus)
        assertEquals(2, api.statusRequests)
    }

    @Test
    fun `a later refresh never brings the saved status back`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))
        api.status = ClockingResult.Loaded(fresh)
        val clocking = ClockingViewModel(api, sessions, cache)

        api.status = ClockingResult.Unreachable
        clocking.refresh(isPull = true)

        assertEquals(fresh, clocking.state.value.actionableStatus)
        assertNull(clocking.state.value.lastUpdated)
    }

    private class GatedClockingApi : ClockingApi {
        var gate: CompletableDeferred<Unit>? = null
        var status: ClockingResult<ClockingStatus> = ClockingResult.Unreachable
        var statusRequests = 0

        override suspend fun clocking(token: String): ClockingResult<ClockingStatus> {
            statusRequests++
            gate?.await()
            return status
        }

        override suspend fun timesheets(token: String, from: LocalDate?, to: LocalDate?): ClockingResult<TimesheetPeriod> {
            gate?.await()
            return ClockingResult.Unreachable
        }

        override suspend fun shifts(token: String): ClockingResult<List<ShiftSchedule>> = ClockingResult.Loaded(emptyList())

        override suspend fun clockWithQr(token: String, request: QrClockingRequest): QrClockingResult = QrClockingResult.Unreachable

        override suspend fun saveNote(token: String, clockingId: Long, notes: String?): ClockingNoteResult = ClockingNoteResult.Unreachable
    }
}
