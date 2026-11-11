package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ClockingResponsesTest {

    @Test
    fun `the clocking status carries today, the sessions, the methods and the personal code`() {
        val body = """
            {"data":{"employee":{"id":1,"name":"Louis Perez","organisation":{"id":2,"code":"SK","name":"Ancient Wisdom s.r.o"}},
            "timezone":"Europe/Bratislava","status":"clocked_in","since":"2026-11-11T08:02:00+01:00",
            "today":{"date":"2026-11-11","first_clock_in":"2026-11-11T08:02:00+01:00","last_clock_out":null,"working_seconds":22380,"breaks_seconds":900},
            "sessions":[{"id":9,"sequence":1,"status":"closed","is_open":false,"starts_at":"2026-11-11T08:02:00+01:00","ends_at":"2026-11-11T12:00:00+01:00","duration":14280,
            "clock_in":{"id":55,"clocked_at":"2026-11-11T08:02:00+01:00","type":"clocking-machine","is_late":false,"notes":null},
            "clock_out":{"id":56,"clocked_at":"2026-11-11T12:00:00+01:00","type":"clocking-machine","is_late":null,"notes":"Lunch"}},
            {"id":10,"sequence":2,"status":"open","is_open":true,"starts_at":"2026-11-11T12:15:00+01:00","ends_at":null,"duration":null,
            "clock_in":{"id":57,"clocked_at":"2026-11-11T12:15:00+01:00","type":"clocking-machine","is_late":false,"notes":null},"clock_out":null}],
            "methods":["qr_code","pin","barcode","camera_qr"],"code":"YEY173","has_shifts":true}}
        """.trimIndent()

        val status = (ClockingResponses.parseClocking(200, body) as ClockingResult.Loaded).value

        assertTrue(status.isClockedIn)
        assertEquals("Ancient Wisdom s.r.o", status.employee?.organisation?.name)
        assertEquals("Europe/Bratislava", status.timezone)
        assertEquals("2026-11-11T08:02:00+01:00", status.since)
        assertEquals(22380L, status.today?.workingSeconds)
        assertEquals(900L, status.today?.breaksSeconds)
        assertNull(status.today?.lastClockOut)
        assertEquals(2, status.sessions.size)
        assertEquals(14280L, status.sessions.first().duration)
        assertEquals("Lunch", status.sessions.first().clockOut?.notes)
        assertFalse(status.sessions.first().clockOut?.isLate ?: true)
        assertTrue(status.sessions.last().isOpen)
        assertNull(status.sessions.last().clockOut)
        assertEquals(1, status.sessions.last().clockingCount)
        assertEquals(listOf("qr_code", "pin", "barcode", "camera_qr"), status.methods)
        assertEquals("YEY173", status.code)
        assertTrue(status.hasShifts)
    }

    @Test
    fun `a user without an employee record is clocked out with no method`() {
        val body = """{"data":{"employee":null,"status":"clocked_out","since":null,"today":null,"sessions":[],"methods":[],"code":null,"has_shifts":false}}"""

        val status = (ClockingResponses.parseClocking(200, body) as ClockingResult.Loaded).value

        assertNull(status.employee)
        assertNull(status.timezone)
        assertFalse(status.isClockedIn)
        assertTrue(status.methods.isEmpty())
        assertNull(status.code)
    }

    @Test
    fun `an expired session, a missing app key and server errors are told apart on every read`() {
        assertEquals(ClockingResult.SessionExpired, ClockingResponses.parseClocking(401, ""))
        assertEquals(ClockingResult.SessionExpired, ClockingResponses.parseTimesheets(403, ""))
        assertEquals(ClockingResult.AppKeyRejected, ClockingResponses.parseShifts(404, ""))
        assertEquals(ClockingResult.ServerError(500), ClockingResponses.parseClocking(500, "<html></html>"))
        assertEquals(ClockingResult.ServerError(200), ClockingResponses.parseClocking(200, "not json"))
    }

    @Test
    fun `timesheets come per day with their sessions`() {
        val body = """
            {"data":{"from":"2026-11-09","to":"2026-11-15","timezone":"Europe/Bratislava","timesheets":[
            {"date":"2026-11-09","first_clock_in":"2026-11-09T07:58:00+01:00","last_clock_out":"2026-11-09T16:31:00+01:00","working_seconds":28860,"breaks_seconds":1920,
            "sessions":[{"id":3,"sequence":1,"status":"closed","is_open":false,"starts_at":"2026-11-09T07:58:00+01:00","ends_at":"2026-11-09T16:31:00+01:00","duration":28860,"clock_in":null,"clock_out":null}]},
            {"date":"2026-11-10","first_clock_in":"2026-11-10T08:05:00+01:00","last_clock_out":null,"working_seconds":0,"breaks_seconds":0,"sessions":[]}]}}
        """.trimIndent()

        val period = (ClockingResponses.parseTimesheets(200, body) as ClockingResult.Loaded).value

        assertEquals("2026-11-09", period.from)
        assertEquals("2026-11-15", period.to)
        assertEquals(listOf("2026-11-09", "2026-11-10"), period.timesheets.map { it.date })
        assertEquals(28860L, period.timesheets.first().workingSeconds)
        assertEquals(1, period.timesheets.first().sessions.size)
        assertNull(period.timesheets.last().lastClockOut)
    }

    @Test
    fun `only active shifts are offered`() {
        val body = """
            {"data":[{"id":3,"name":"Morning","type":"shift","is_active":true},{"id":4,"name":"Night","type":"shift","is_active":false},
            {"id":5,"name":"Default","type":"default","is_active":true}]}
        """.trimIndent()

        val shifts = (ClockingResponses.parseShifts(200, body) as ClockingResult.Loaded).value

        assertEquals(listOf(ShiftSchedule(id = 3, name = "Morning", type = "shift", isActive = true)), shifts)
    }

    @Test
    fun `an accepted qr code returns the clocking, the visit and the working hours`() {
        val body = """
            {"success":true,"message":"QR Code valid","machine":{"id":4,"name":"Goods-in door","workplace_id":null},"is_visiting":true,
            "working_hours":{"start":"08:00:00","end":"17:00:00","name":"Default"},
            "clocking":{"clocked_at":"2026-11-11T07:02:14.000000Z","id":56,"type":"clock_out","is_late":null}}
        """.trimIndent()

        val result = (ClockingResponses.parseQrClocking(200, body) as QrClockingResult.Recorded).result

        assertEquals(56L, result.clocking.id)
        assertEquals("2026-11-11T07:02:14.000000Z", result.clocking.clockedAt)
        assertTrue(result.clocking.isClockOut)
        assertFalse(result.clocking.isLate)
        assertTrue(result.isVisiting)
        assertEquals("08:00:00", result.workingHours?.start)
        assertEquals("Goods-in door", result.machine?.name)
    }

    @Test
    fun `a qr code without working hours or a known action still counts as recorded`() {
        val body = """{"success":true,"machine":{"id":4,"name":"Door"},"is_visiting":false,"working_hours":null,"clocking":{"clocked_at":"2026-11-11T07:02:14.000000Z","id":57,"type":null}}"""

        val result = (ClockingResponses.parseQrClocking(200, body) as QrClockingResult.Recorded).result

        assertNull(result.workingHours)
        assertFalse(result.clocking.isClockIn)
        assertFalse(result.clocking.isClockOut)
    }

    @Test
    fun `a refused qr code carries the message to show`() {
        assertEquals(
            QrClockingResult.Refused("This QR Code is no longer active. Please scan a new one."),
            ClockingResponses.parseQrClocking(400, """{"success":false,"message":"This QR Code is no longer active. Please scan a new one."}"""),
        )
        assertEquals(
            QrClockingResult.Refused("The qr code field is required."),
            ClockingResponses.parseQrClocking(422, """{"message":"Invalid","errors":{"qr_code":["The qr code field is required."]}}"""),
        )
    }

    @Test
    fun `a qr scan with an expired session or a broken server is not mistaken for a refusal`() {
        assertEquals(QrClockingResult.SessionExpired, ClockingResponses.parseQrClocking(401, ""))
        assertEquals(QrClockingResult.SessionExpired, ClockingResponses.parseQrClocking(403, ""))
        assertEquals(QrClockingResult.ServerError(500), ClockingResponses.parseQrClocking(500, ""))
    }

    @Test
    fun `the request sends the scanned code with the schedule and leaves out a missing location`() {
        assertEquals(
            """{"qr_code":"https://app.aiku.io/c/ABC","work_schedule_id":3}""",
            ClockingResponses.qrClockingBody(QrClockingRequest(qrCode = "https://app.aiku.io/c/ABC", workScheduleId = 3)),
        )
        assertEquals(
            """{"qr_code":"ABC","latitude":48.1,"longitude":17.1}""",
            ClockingResponses.qrClockingBody(QrClockingRequest(qrCode = "ABC", latitude = 48.1, longitude = 17.1)),
        )
    }

    @Test
    fun `a note is saved, cleared or refused when the clocking is someone else's`() {
        assertEquals(ClockingNoteResult.Saved("Forgot my badge"), ClockingResponses.parseNote(200, """{"data":{"id":56,"notes":"Forgot my badge"}}"""))
        assertEquals(ClockingNoteResult.Saved(null), ClockingResponses.parseNote(200, """{"data":{"id":56,"notes":null}}"""))
        assertEquals(ClockingNoteResult.Refused, ClockingResponses.parseNote(403, ""))
        assertEquals(ClockingNoteResult.SessionExpired, ClockingResponses.parseNote(401, ""))
        assertEquals(ClockingNoteResult.ServerError(500), ClockingResponses.parseNote(500, ""))
    }

    @Test
    fun `clearing a note sends an explicit null`() {
        assertEquals("""{"notes":null}""", ClockingResponses.noteBody(null))
        assertEquals("""{"notes":"Late bus"}""", ClockingResponses.noteBody("Late bus"))
    }

    @Test
    fun `the clocking status says whether the user may edit clocking notes`() {
        val allowed = """{"data":{"status":"clocked_out","sessions":[],"methods":["qr_code"],"can_edit_notes":true}}"""
        val refused = """{"data":{"status":"clocked_out","sessions":[],"methods":["qr_code"],"can_edit_notes":false}}"""
        val older = """{"data":{"status":"clocked_out","sessions":[],"methods":["qr_code"]}}"""

        assertTrue((ClockingResponses.parseClocking(200, allowed) as ClockingResult.Loaded).value.canEditNotes)
        assertFalse((ClockingResponses.parseClocking(200, refused) as ClockingResult.Loaded).value.canEditNotes)
        assertFalse((ClockingResponses.parseClocking(200, older) as ClockingResult.Loaded).value.canEditNotes)
    }

    @Test
    fun `timesheets carry the statistics, the target, the correction fields and where each clocking was made`() {
        val body = """
            {"data":{"from":"2026-11-09","to":"2026-11-15","timezone":"Europe/London",
            "statistics":{"on_time":3,"late_clock_in":1,"early_clock_out":0,"no_clock_out":1,"invalid":0,"absent":0,"total":4},
            "target_seconds":135000,
            "timesheets":[{"id":8812,"date":"2026-11-10","first_clock_in":"2026-11-10T08:58:12+00:00","last_clock_out":"2026-11-10T17:31:40+00:00",
            "working_seconds":30808,"breaks_seconds":1800,"start_time_local":"08:58","end_time_local":"17:31",
            "number_time_trackers":2,"number_open_time_trackers":1,"clock_in_count":2,"clock_out_count":1,"has_pending_adjustment":true,
            "sessions":[{"id":551,"sequence":1,"status":"closed","is_open":false,"starts_at":"2026-11-10T08:58:12+00:00","ends_at":"2026-11-10T12:30:00+00:00","duration":12708,
            "clock_in":{"id":9001,"clocked_at":"2026-11-10T08:58:12+00:00","type":"clocking-machine","is_late":false,"notes":null,
            "workplace":{"id":3,"name":"Sheffield warehouse"},"clocking_machine":{"id":7,"name":"Front door QR"}},
            "clock_out":{"id":9002,"clocked_at":"2026-11-10T12:30:00+00:00","type":"manual","is_late":false,"notes":null,"workplace":null,"clocking_machine":null}}]}]}}
        """.trimIndent()

        val period = (ClockingResponses.parseTimesheets(200, body) as ClockingResult.Loaded).value
        val timesheet = period.timesheets.single()
        val session = timesheet.sessions.single()

        assertEquals(3, period.statistics?.onTime)
        assertEquals(1, period.statistics?.lateClockIn)
        assertEquals(1, period.statistics?.noClockOut)
        assertEquals(4, period.statistics?.total)
        assertEquals(135000L, period.targetSeconds)
        assertEquals(8812L, timesheet.id)
        assertEquals("08:58", timesheet.startTimeLocal)
        assertEquals("17:31", timesheet.endTimeLocal)
        assertEquals(2, timesheet.numberTimeTrackers)
        assertEquals(1, timesheet.numberOpenTimeTrackers)
        assertEquals(2, timesheet.clockInCount)
        assertEquals(1, timesheet.clockOutCount)
        assertTrue(timesheet.hasPendingAdjustment)
        assertEquals("Sheffield warehouse", session.clockIn?.workplace?.name)
        assertEquals("Front door QR", session.clockIn?.clockingMachine?.name)
        assertNull(session.clockOut?.workplace)
        assertNull(session.clockOut?.clockingMachine)
    }

    @Test
    fun `a range without a work schedule has no target`() {
        val body = """{"data":{"from":"2026-11-09","to":"2026-11-15","statistics":null,"target_seconds":null,"timesheets":[]}}"""

        val period = (ClockingResponses.parseTimesheets(200, body) as ClockingResult.Loaded).value

        assertNull(period.targetSeconds)
        assertNull(period.statistics)
    }
}
