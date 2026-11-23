package io.aiku.droid.ui.clocking

import io.aiku.droid.data.api.ClockingApi
import io.aiku.droid.data.api.ClockingNoteResult
import io.aiku.droid.data.api.ClockingResponses
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
import java.time.Instant
import java.time.LocalDate

private val LOADED_AT: Instant = Instant.parse("2026-11-23T08:15:00Z")

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
    fun `a later refresh never brings the saved status back and says when the shown status was loaded`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingStatus, CachedBodies.clocking("clocked_in"))
        api.status = ClockingResult.Loaded(fresh)
        val clocking = ClockingViewModel(api, sessions, cache, now = { LOADED_AT })
        assertNull(clocking.state.value.lastUpdated)

        api.status = ClockingResult.Unreachable
        clocking.refresh(isPull = true)

        assertEquals(fresh, clocking.state.value.actionableStatus)
        assertEquals(LOADED_AT, clocking.state.value.lastUpdated)
    }

    @Test
    fun `a week loaded earlier stays on screen when it can not be loaded again and says when it was loaded`() {
        api.week = ClockingResponses.parseTimesheets(200, CachedBodies.thisWeek(workingSeconds = 1800))
        val clocking = ClockingViewModel(api, sessions, cache, now = { LOADED_AT })
        clocking.loadWeek(from = null, isPull = false)
        assertNull(clocking.state.value.week.lastUpdated)

        api.week = ClockingResult.Unreachable
        clocking.loadWeek(from = null, isPull = true)

        assertEquals(1800L, clocking.state.value.week.period?.timesheets?.single()?.workingSeconds)
        assertEquals(LOADED_AT, clocking.state.value.week.lastUpdated)
    }

    @Test
    fun `timesheets of this week fall back to the saved week and say when it was last updated`() {
        cache.save(CachedScreens.USER_SCOPED, CachedScreens.clockingThisWeek, CachedBodies.thisWeek(workingSeconds = 7200))
        api.status = ClockingResult.Unreachable
        val clocking = ClockingViewModel(api, sessions, cache)

        clocking.loadWeek(from = null, isPull = false)

        assertEquals(7200L, clocking.state.value.week.period?.timesheets?.single()?.workingSeconds)
        assertEquals(ClockingProblem.Offline, clocking.state.value.week.problem)
        assertEquals(CACHE_NOW, clocking.state.value.week.lastUpdated)

        clocking.loadWeek(from = LocalDate.of(2026, 11, 9), isPull = false)

        assertNull(clocking.state.value.week.period)
        assertNull(clocking.state.value.week.lastUpdated)
    }

    @Test
    fun `timesheets of a person with no employee record load with no statistics`() {
        val body = """{"data":{"from":"2026-11-16","to":"2026-11-22","statistics":null,"target_seconds":null,"timesheets":[]}}"""

        val result = ClockingResponses.parseTimesheets(200, body)

        assertTrue(result is ClockingResult.Loaded)
        assertNull((result as ClockingResult.Loaded).value.statistics)
    }

    private class GatedClockingApi : ClockingApi {
        var gate: CompletableDeferred<Unit>? = null
        var status: ClockingResult<ClockingStatus> = ClockingResult.Unreachable
        var statusRequests = 0
        var week: ClockingResult<TimesheetPeriod> = ClockingResult.Unreachable

        override suspend fun clocking(token: String): ClockingResult<ClockingStatus> {
            statusRequests++
            gate?.await()
            return status
        }

        override suspend fun timesheets(token: String, from: LocalDate?, to: LocalDate?): ClockingResult<TimesheetPeriod> {
            gate?.await()
            return week
        }

        override suspend fun shifts(token: String): ClockingResult<List<ShiftSchedule>> = ClockingResult.Loaded(emptyList())

        override suspend fun clockWithQr(token: String, request: QrClockingRequest): QrClockingResult = QrClockingResult.Unreachable

        override suspend fun saveNote(token: String, clockingId: Long, notes: String?): ClockingNoteResult = ClockingNoteResult.Unreachable
    }
}
