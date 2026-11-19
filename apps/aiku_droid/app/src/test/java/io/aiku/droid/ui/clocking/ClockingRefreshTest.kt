package io.aiku.droid.ui.clocking

import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.CancelledRequest
import io.aiku.droid.data.api.ClockingAdjustment
import io.aiku.droid.data.api.ClockingAdjustmentInput
import io.aiku.droid.data.api.ClockingAdjustmentPage
import io.aiku.droid.data.api.ClockingApi
import io.aiku.droid.data.api.ClockingNoteResult
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.data.api.HolidayCalendar
import io.aiku.droid.data.api.HrApi
import io.aiku.droid.data.api.HrWriteResult
import io.aiku.droid.data.api.Leave
import io.aiku.droid.data.api.LeavePage
import io.aiku.droid.data.api.LeaveRequestInput
import io.aiku.droid.data.api.OvertimePage
import io.aiku.droid.data.api.OvertimeRequest
import io.aiku.droid.data.api.OvertimeRequestInput
import io.aiku.droid.data.api.QrClockingRequest
import io.aiku.droid.data.api.QrClockingResult
import io.aiku.droid.data.api.ShiftSchedule
import io.aiku.droid.data.api.Timesheet
import io.aiku.droid.data.api.TimesheetPeriod
import io.aiku.droid.data.api.UploadFile
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
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.io.File
import java.time.LocalDate
import java.time.YearMonth

@OptIn(ExperimentalCoroutinesApi::class)
class ClockingRefreshTest {

    private val hr = FakeHrApi()
    private val clockingApi = FakeClockingApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `pulling leave reloads the first page, but not while a request is being sent`() {
        val leave = LeaveViewModel(hr, FakeSessions())
        leave.load()
        hr.leaves = listOf(Leave(id = 2))

        leave.refresh()

        assertEquals(listOf(1, 1), hr.leavePages)
        assertEquals(listOf(2L), leave.state.value.list.items.map { it.id })
        assertFalse(leave.state.value.list.isRefreshing)

        val gate = CompletableDeferred<Unit>()
        hr.writeGate = gate
        leave.requestLeave(
            LeaveRequestInput(type = "annual", startDate = LocalDate.of(2026, 12, 1), endDate = LocalDate.of(2026, 12, 2), isHalfDay = false, session = "", reason = ""),
        )
        leave.refresh()

        assertEquals(listOf(1, 1), hr.leavePages)
        gate.complete(Unit)
    }

    @Test
    fun `pulling clocking corrections reloads the first page`() {
        val adjustments = ClockingAdjustmentsViewModel(hr, FakeSessions())
        adjustments.load()
        hr.adjustments = listOf(ClockingAdjustment(id = 8))

        adjustments.refresh()

        assertEquals(listOf(1, 1), hr.adjustmentPages)
        assertEquals(listOf(8L), adjustments.state.value.list.items.map { it.id })
        assertFalse(adjustments.state.value.list.isRefreshing)
    }

    @Test
    fun `pulling overtime reloads the first page`() {
        val overtime = OvertimeViewModel(hr, FakeSessions())
        overtime.load()
        hr.overtime = listOf(OvertimeRequest(id = 3))

        overtime.refresh()

        assertEquals(listOf(1, 1), hr.overtimePages)
        assertEquals(listOf(3L), overtime.state.value.list.items.map { it.id })
        assertFalse(overtime.state.value.list.isRefreshing)
    }

    @Test
    fun `pulling the calendar reloads the shown month and keeps it while loading`() {
        val month = YearMonth.of(2026, 11)
        val calendar = ClockingCalendarViewModel(hr, FakeSessions())
        calendar.loadMonth(month)
        val gate = CompletableDeferred<Unit>()
        hr.calendarGate = gate

        calendar.loadMonth(month, isPull = true)

        assertTrue(calendar.state.value.isRefreshing)
        assertEquals(HolidayCalendar(month = "2026-11"), calendar.state.value.months[month])

        gate.complete(Unit)

        assertFalse(calendar.state.value.isRefreshing)
        assertEquals(listOf(month, month), hr.calendarMonths)
    }

    @Test
    fun `pulling the clocking screens reloads the status, the week and the day`() {
        val clocking = ClockingViewModel(clockingApi, FakeSessions())
        val day = LocalDate.of(2026, 11, 17)
        clocking.loadWeek(null)
        clocking.loadDay(day)
        val statusBefore = clockingApi.statusRequests
        val timesheetsBefore = clockingApi.timesheetRequests.size
        val gate = CompletableDeferred<Unit>()
        clockingApi.statusGate = gate

        clocking.refresh(isPull = true)

        assertTrue(clocking.state.value.isRefreshing)
        assertEquals(statusBefore + 1, clockingApi.statusRequests)

        gate.complete(Unit)

        assertFalse(clocking.state.value.isRefreshing)

        clocking.loadWeek(null, isPull = true)
        clocking.loadDay(day, isPull = true)

        assertFalse(clocking.state.value.week.isRefreshing)
        assertFalse(clocking.state.value.day?.isRefreshing == true)
        assertEquals(day, clockingApi.timesheetRequests.last())
        assertEquals(timesheetsBefore + 3, clockingApi.timesheetRequests.size)
    }

    private class FakeHrApi : HrApi {
        var leaves = listOf(Leave(id = 1))
        val leavePages = mutableListOf<Int>()
        var adjustments = listOf(ClockingAdjustment(id = 1))
        val adjustmentPages = mutableListOf<Int>()
        var overtime = listOf(OvertimeRequest(id = 1))
        val overtimePages = mutableListOf<Int>()
        val calendarMonths = mutableListOf<YearMonth>()
        var calendarGate: CompletableDeferred<Unit>? = null
        var writeGate: CompletableDeferred<Unit>? = null

        override suspend fun leaves(token: String, page: Int, perPage: Int): ClockingResult<LeavePage> {
            leavePages += page
            return ClockingResult.Loaded(LeavePage(leaves = leaves))
        }

        override suspend fun requestLeave(token: String, input: LeaveRequestInput): HrWriteResult<Leave> {
            writeGate?.await()
            return HrWriteResult.Unreachable
        }

        override suspend fun replaceLeaveAttachments(token: String, leaveId: Long, attachments: List<UploadFile>): HrWriteResult<Leave> = HrWriteResult.Unreachable

        override suspend fun clockingAdjustments(token: String, page: Int, perPage: Int): ClockingResult<ClockingAdjustmentPage> {
            adjustmentPages += page
            return ClockingResult.Loaded(ClockingAdjustmentPage(adjustments = adjustments))
        }

        override suspend fun requestClockingAdjustment(token: String, input: ClockingAdjustmentInput): HrWriteResult<ClockingAdjustment> = HrWriteResult.Unreachable

        override suspend fun cancelClockingAdjustment(token: String, adjustmentId: Long): HrWriteResult<CancelledRequest> = HrWriteResult.Unreachable

        override suspend fun overtimeRequests(token: String, page: Int, perPage: Int): ClockingResult<OvertimePage> {
            overtimePages += page
            return ClockingResult.Loaded(OvertimePage(overtimeRequests = overtime))
        }

        override suspend fun requestOvertime(token: String, input: OvertimeRequestInput): HrWriteResult<OvertimeRequest> = HrWriteResult.Unreachable

        override suspend fun updateOvertime(token: String, overtimeId: Long, input: OvertimeRequestInput): HrWriteResult<OvertimeRequest> = HrWriteResult.Unreachable

        override suspend fun holidayCalendar(token: String, month: YearMonth): ClockingResult<HolidayCalendar> {
            calendarMonths += month
            calendarGate?.await()
            return ClockingResult.Loaded(HolidayCalendar(month = month.toString()))
        }

        override suspend fun downloadAttachment(token: String, url: String, target: File): AttachmentDownload = AttachmentDownload.Unreachable
    }

    private class FakeClockingApi : ClockingApi {
        var statusRequests = 0
        var statusGate: CompletableDeferred<Unit>? = null
        val timesheetRequests = mutableListOf<LocalDate?>()

        override suspend fun clocking(token: String): ClockingResult<ClockingStatus> {
            statusRequests++
            statusGate?.await()
            return ClockingResult.Loaded(ClockingStatus())
        }

        override suspend fun timesheets(token: String, from: LocalDate?, to: LocalDate?): ClockingResult<TimesheetPeriod> {
            timesheetRequests += from
            return ClockingResult.Loaded(TimesheetPeriod(timesheets = from?.let { listOf(Timesheet(date = it.toString())) }.orEmpty()))
        }

        override suspend fun shifts(token: String): ClockingResult<List<ShiftSchedule>> = ClockingResult.Loaded(emptyList())

        override suspend fun clockWithQr(token: String, request: QrClockingRequest): QrClockingResult = QrClockingResult.Unreachable

        override suspend fun saveNote(token: String, clockingId: Long, notes: String?): ClockingNoteResult = ClockingNoteResult.Unreachable
    }
}
