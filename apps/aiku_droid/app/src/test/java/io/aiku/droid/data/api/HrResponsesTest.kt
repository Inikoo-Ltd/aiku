package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class HrResponsesTest {

    private val leaveJson = """
        {"id":412,"employee_id":77,"employee_name":"Jane Doe","employee_avatar":null,"type":"SICK","type_label":"Sick leave","type_color":"yellow",
        "type_color_hex":"#facc15","start_date":"2026-11-24","end_date":"2026-11-24","duration_days":1,"is_half_day":false,"leave_type_value":1.0,
        "session":"Full","reason":"Dentist appointment","status":"pending","status_label":"Pending","status_color":"yellow",
        "status_icon":{"tooltip":"Pending","icon":"fal fa-clock","class":"text-yellow-500"},"approval_total_steps":2,"approval_completed_steps":0,
        "approval_current_step":1,"can_approve_current_user":false,"approved_by":null,"approved_at":null,"rejection_reason":null,
        "cover_employee_id":null,"cover_employee_name":null,"cover_employee_avatar":null,"approver_name":null,"approver_avatar":null,"approvals":[],
        "created_at":"2026-11-11T10:02:44.000000Z",
        "attachments":[{"id":9931,"name":"certificate.jpg","url":"https://app.aiku.test/app/api/mobile/clocking/attachments/01JC"}]}
    """.trimIndent()

    private val adjustmentJson = """
        {"id":120,"employee_id":77,"employee_name":"Jane Doe","employee_avatar":null,"timesheet_id":null,"date":"2026-11-10",
        "original_start_at":null,"original_end_at":null,"requested_start_at":"2026-11-10T09:00:00.000000Z","requested_end_at":"2026-11-10T17:30:00.000000Z",
        "original_start_time_local":null,"original_end_time_local":null,"requested_start_time_local":"09:00","requested_end_time_local":"17:30",
        "timezone":"Europe/London","reason":"Phone battery was flat","status":"pending","status_label":"Pending","status_color":"yellow",
        "status_icon":{"tooltip":"Pending","icon":"fal fa-clock","class":"text-yellow-500"},"approved_by":null,"approver_name":null,
        "approver_avatar":null,"approved_at":null,"approval_comment":null,"created_at":"2026-11-11T09:12:03.000000Z","attachments":[]}
    """.trimIndent()

    private val overtimeJson = """
        {"id":66,"overtime_type_id":4,"overtime_type_name":"Weekday overtime","requested_date":"2026-11-10",
        "requested_start_at":"2026-11-10T18:00:00+00:00","requested_end_at":"2026-11-10T19:30:00+00:00","requested_duration_minutes":90,
        "recorded_start_at":null,"recorded_end_at":null,"recorded_duration_minutes":null,"lieu_requested_minutes":0,"reason":"Stock take",
        "status":"pending","status_label":"Pending","approved_at":null,"rejected_at":null}
    """.trimIndent()

    @Test
    fun `the leave list carries the requests, the balance cards and what the request form needs`() {
        val body = """
            {"data":{"leaves":[$leaveJson],"pagination":{"current_page":1,"last_page":3,"per_page":10,"total":27},
            "balance":{"id":301,"annual_days":20,"annual_used":2,"annual_remaining":18,"medical_used":0,"unpaid_used":0,
            "annual_adjustment":0.0,"medical_adjustment":0.0,"unpaid_adjustment":0.0},
            "annual_submitted_days":2.0,"annual_remaining_after_submission":18.0,"medical_request_count":1.0,"unpaid_request_count":0.0,
            "type_options":[{"code":"AL","label":"Annual leave","category":"annual","max_days_per_year":null,"value":1.0},
            {"code":"SICK","label":"Sick leave","category":"medical","max_days_per_year":10,"value":0.5}],
            "holidays":[{"date":"2026-12-25","label":"Christmas Day"}]}}
        """.trimIndent()

        val page = (HrResponses.parseLeaves(200, body) as ClockingResult.Loaded).value
        val leave = page.leaves.single()

        assertEquals(412L, leave.id)
        assertEquals("Sick leave", leave.typeLabel)
        assertEquals("#facc15", leave.typeColorHex)
        assertEquals("2026-11-24", leave.startDate)
        assertFalse(leave.isHalfDayDuration)
        assertTrue(leave.isPending)
        assertEquals("Pending", leave.statusLabel)
        assertEquals(2, leave.approvalTotalSteps)
        assertEquals(0, leave.approvalCompletedSteps)
        assertEquals("certificate.jpg", leave.attachments.single().name)
        assertEquals(3, page.pagination?.lastPage)
        assertEquals(27, page.pagination?.total)
        assertEquals(20.0, page.balance?.annualDays ?: 0.0, 0.0)
        assertEquals(2.0, page.annualSubmittedDays, 0.0)
        assertEquals(18.0, page.annualRemainingAfterSubmission, 0.0)
        assertEquals(1.0, page.medicalRequestCount, 0.0)
        assertEquals(0.0, page.unpaidRequestCount, 0.0)
        assertTrue(page.typeOf(leave)?.isMedical == true)
        assertEquals(10.0, page.typeOptions.last().maxDaysPerYear ?: 0.0, 0.0)
        assertNull(page.typeOptions.first().maxDaysPerYear)
        assertEquals("Christmas Day", page.holidays.single().label)
    }

    @Test
    fun `a user with no employee record gets an empty leave list without cards`() {
        val body = """{"data":{"leaves":[],"pagination":null,"balance":null,"annual_submitted_days":0,"annual_remaining_after_submission":0,
            "medical_request_count":0,"unpaid_request_count":0,"type_options":[],"holidays":[]}}"""

        val page = (HrResponses.parseLeaves(200, body) as ClockingResult.Loaded).value

        assertTrue(page.leaves.isEmpty())
        assertNull(page.pagination)
        assertNull(page.balance)
    }

    @Test
    fun `a half day leave is told apart by its type value`() {
        val body = """{"data":${leaveJson.replace("\"leave_type_value\":1.0", "\"leave_type_value\":0.5")}}"""

        val leave = (HrResponses.parseLeave(201, body) as HrWriteResult.Saved).value

        assertTrue(leave.isHalfDayDuration)
    }

    @Test
    fun `a created leave comes back as saved`() {
        val leave = (HrResponses.parseLeave(201, """{"data":$leaveJson}""") as HrWriteResult.Saved).value

        assertEquals(412L, leave.id)
        assertEquals("pending", leave.status)
    }

    @Test
    fun `a refused leave keeps the first message of each field`() {
        val body = """{"message":"Insufficient leave balance. Remaining balance: 2 days. (and 1 more error)",
            "errors":{"duration_days":["Insufficient leave balance. Remaining balance: 2 days."],
            "attachments.0":["The attachments.0 field must be a file of type: pdf, jpg, jpeg, png."],"reason":[]}}"""

        val result = HrResponses.parseLeave(422, body) as HrWriteResult.Invalid

        assertEquals("Insufficient leave balance. Remaining balance: 2 days.", result.fieldErrors["duration_days"])
        assertEquals("The attachments.0 field must be a file of type: pdf, jpg, jpeg, png.", result.fieldErrors["attachments.0"])
        assertFalse(result.fieldErrors.containsKey("reason"))
        assertTrue(result.message.startsWith("Insufficient leave balance"))
    }

    @Test
    fun `a forbidden or unknown write shows the server's message`() {
        assertEquals(
            HrWriteResult.Refused("Only pending leaves can be edited."),
            HrResponses.parseLeave(403, """{"message":"Only pending leaves can be edited."}"""),
        )
        assertEquals(
            HrWriteResult.Refused("Employee record not found for current user."),
            HrResponses.parseClockingAdjustment(404, """{"message":"Employee record not found for current user."}"""),
        )
        assertEquals(HrWriteResult.Refused(""), HrResponses.parseOvertimeRequest(404, "<html></html>"))
    }

    @Test
    fun `writes tell an expired session, a broken server and an unreadable answer apart`() {
        assertEquals(HrWriteResult.SessionExpired, HrResponses.parseLeave(401, """{"message":"Unauthenticated."}"""))
        assertEquals(HrWriteResult.ServerError(500), HrResponses.parseOvertimeRequest(500, ""))
        assertEquals(HrWriteResult.ServerError(201), HrResponses.parseClockingAdjustment(201, "not json"))
        assertEquals(HrWriteResult.Invalid(emptyMap(), ""), HrResponses.parseLeave(422, "not json"))
    }

    @Test
    fun `reads treat 401 and 403 as an expired session`() {
        assertEquals(ClockingResult.SessionExpired, HrResponses.parseLeaves(401, ""))
        assertEquals(ClockingResult.SessionExpired, HrResponses.parseClockingAdjustments(403, ""))
        assertEquals(ClockingResult.AppKeyRejected, HrResponses.parseOvertimeRequests(404, ""))
        assertEquals(ClockingResult.ServerError(422), HrResponses.parseHolidayCalendar(422, """{"errors":{"month":["Bad"]}}"""))
    }

    @Test
    fun `the adjustment list carries recorded and requested times`() {
        val body = """{"data":{"adjustments":[$adjustmentJson],"pagination":{"current_page":1,"last_page":1,"per_page":10,"total":1}}}"""

        val page = (HrResponses.parseClockingAdjustments(200, body) as ClockingResult.Loaded).value
        val adjustment = page.adjustments.single()

        assertEquals(120L, adjustment.id)
        assertNull(adjustment.timesheetId)
        assertNull(adjustment.originalStartTimeLocal)
        assertNull(adjustment.originalEndTimeLocal)
        assertEquals("09:00", adjustment.requestedStartTimeLocal)
        assertEquals("17:30", adjustment.requestedEndTimeLocal)
        assertTrue(adjustment.isPending)
        assertTrue(adjustment.attachments.isEmpty())
        assertEquals(1, page.pagination?.total)
    }

    @Test
    fun `a rejected adjustment keeps the approval comment`() {
        val body = """{"data":${adjustmentJson.replace("\"status\":\"pending\"", "\"status\":\"rejected\"").replace("\"approval_comment\":null", "\"approval_comment\":\"No proof\"")}}"""

        val adjustment = (HrResponses.parseClockingAdjustment(201, body) as HrWriteResult.Saved).value

        assertTrue(adjustment.isRejected)
        assertEquals("No proof", adjustment.approvalComment)
    }

    @Test
    fun `an adjustment refused for its date shows the message under the date`() {
        val body = """{"message":"Requests can only go back one month.","errors":{"date":["Requests can only go back one month."]}}"""

        val result = HrResponses.parseClockingAdjustment(422, body) as HrWriteResult.Invalid

        assertEquals(mapOf("date" to "Requests can only go back one month."), result.fieldErrors)
    }

    @Test
    fun `a cancelled adjustment says so, and a request that is no longer pending is refused`() {
        val cancelled = (HrResponses.parseCancellation(200, """{"data":{"id":120,"cancelled":true}}""") as HrWriteResult.Saved).value
        val notPending = HrResponses.parseCancellation(422, """{"message":"Only a pending request can be cancelled.","errors":{"status":["Only a pending request can be cancelled."]}}""")

        assertEquals(120L, cancelled.id)
        assertTrue(cancelled.cancelled)
        assertEquals(HrWriteResult.Invalid(mapOf("status" to "Only a pending request can be cancelled."), "Only a pending request can be cancelled."), notPending)
        assertEquals(HrWriteResult.Refused("This action is unauthorized."), HrResponses.parseCancellation(403, """{"message":"This action is unauthorized."}"""))
    }

    @Test
    fun `the overtime list carries the requests and the types`() {
        val body = """{"data":{"overtime_requests":[$overtimeJson],"pagination":{"current_page":2,"last_page":2,"per_page":10,"total":11},
            "type_options":[{"value":4,"label":"Weekday overtime"},{"value":5,"label":"Weekend overtime"}]}}"""

        val page = (HrResponses.parseOvertimeRequests(200, body) as ClockingResult.Loaded).value
        val request = page.overtimeRequests.single()

        assertEquals(66L, request.id)
        assertEquals(4L, request.overtimeTypeId)
        assertEquals("Weekday overtime", request.overtimeTypeName)
        assertEquals(90, request.requestedDurationMinutes)
        assertNull(request.recordedDurationMinutes)
        assertEquals(0, request.lieuRequestedMinutes)
        assertTrue(request.isPending)
        assertEquals(listOf(4L, 5L), page.typeOptions.map { it.value })
        assertEquals(2, page.pagination?.currentPage)
    }

    @Test
    fun `an overtime edit that is no longer pending is refused with its message`() {
        assertEquals(
            HrWriteResult.Refused("Only pending overtime requests can be edited."),
            HrResponses.parseOvertimeRequest(403, """{"message":"Only pending overtime requests can be edited."}"""),
        )
        val invalid = HrResponses.parseOvertimeRequest(422, """{"message":"x","errors":{"duration_hours":["Duration must be greater than zero."]}}""") as HrWriteResult.Invalid
        assertEquals("Duration must be greater than zero.", invalid.fieldErrors["duration_hours"])
    }

    @Test
    fun `the calendar carries the holidays, the ranges, the holiday year and the employee's leaves`() {
        val body = """
            {"data":{"month":"2026-12","holidays":[{"date":"2026-12-25","label":"Christmas Day"},{"date":"2026-12-26","label":"Boxing Day"}],
            "holiday_ranges":[{"from":"2026-12-25","to":"2026-12-26","label":"Christmas"}],
            "holiday_year":{"id":3,"label":"2026","start_date":"2026-01-01","end_date":"2026-12-31"},
            "leaves":[{"id":412,"type":"AL","type_label":"Annual leave","type_color_hex":"#60a5fa","start_date":"2026-11-30","end_date":"2026-12-02",
            "duration_days":3,"is_half_day":false,"session":"Full","status":"approved","status_label":"Approved"},
            {"id":413,"type":"AL","type_label":"Annual leave","type_color_hex":"#60a5fa","start_date":"2026-12-18","end_date":"2026-12-18",
            "duration_days":0.5,"is_half_day":true,"session":"Afternoon","status":"pending","status_label":"Pending"}]}}
        """.trimIndent()

        val calendar = (HrResponses.parseHolidayCalendar(200, body) as ClockingResult.Loaded).value

        assertEquals("2026-12", calendar.month)
        assertEquals(listOf("Christmas Day", "Boxing Day"), calendar.holidays.map { it.label })
        assertEquals(HolidayRange(from = "2026-12-25", to = "2026-12-26", label = "Christmas"), calendar.holidayRanges.single())
        assertEquals("2026", calendar.holidayYear?.label)
        assertEquals("2026-11-30", calendar.leaves.first().startDate)
        assertEquals("approved", calendar.leaves.first().status)
        assertTrue(calendar.leaves.last().isHalfDay)
        assertEquals("Afternoon", calendar.leaves.last().session)
    }

    @Test
    fun `a calendar leave carries its type value, so half-day types are told apart from full days`() {
        val body = """
            {"data":{"month":"2026-11","holidays":[],"holiday_ranges":[],"holiday_year":null,
            "leaves":[{"id":501,"type":"hd-morning","type_label":"Halfday Morning","type_color_hex":"#a3e635","start_date":"2026-11-12","end_date":"2026-11-12",
            "duration_days":0.5,"is_half_day":false,"session":"Full","leave_type_value":0.5,"status":"approved","status_label":"Approved"},
            {"id":502,"type":"AL","type_label":"Annual leave","type_color_hex":"#60a5fa","start_date":"2026-11-20","end_date":"2026-11-20",
            "duration_days":1,"is_half_day":false,"session":"Full","leave_type_value":1.0,"status":"pending","status_label":"Pending"},
            {"id":503,"type":"AL","type_label":"Annual leave","start_date":"2026-11-23","end_date":"2026-11-23","status":"approved","status_label":"Approved"}]}}
        """.trimIndent()

        val leaves = (HrResponses.parseHolidayCalendar(200, body) as ClockingResult.Loaded).value.leaves

        assertEquals(0.5, leaves[0].leaveTypeValue, 0.0)
        assertFalse(leaves[0].isHalfDay)
        assertTrue(leaves[0].isHalfDayDuration)
        assertEquals("Full", leaves[0].session)
        assertEquals(1.0, leaves[1].leaveTypeValue, 0.0)
        assertFalse(leaves[1].isHalfDayDuration)
        assertEquals(1.0, leaves[2].leaveTypeValue, 0.0)
        assertFalse(leaves[2].isHalfDayDuration)
    }

    @Test
    fun `a calendar without a holiday year or leaves still loads`() {
        val body = """{"data":{"month":"2026-02","holidays":[],"holiday_ranges":[],"holiday_year":null}}"""

        val calendar = (HrResponses.parseHolidayCalendar(200, body) as ClockingResult.Loaded).value

        assertNull(calendar.holidayYear)
        assertTrue(calendar.leaves.isEmpty())
    }

    @Test
    fun `a refused attachment download shows the server's message`() {
        assertEquals(AttachmentDownload.Refused("This action is unauthorized."), HrResponses.parseDownloadFailure(403, """{"message":"This action is unauthorized."}"""))
        assertEquals(AttachmentDownload.SessionExpired, HrResponses.parseDownloadFailure(401, ""))
        assertEquals(AttachmentDownload.ServerError(500), HrResponses.parseDownloadFailure(500, ""))
    }

    @Test
    fun `a leave request without files is sent as JSON with a real boolean`() {
        val input = LeaveRequestInput(
            type = "AL",
            startDate = LocalDate.of(2026, 11, 24),
            endDate = LocalDate.of(2026, 11, 24),
            isHalfDay = true,
            session = LeaveSession.MORNING,
            reason = "Dentist",
        )

        assertEquals(
            """{"type":"AL","start_date":"2026-11-24","end_date":"2026-11-24","is_half_day":true,"session":"Morning","reason":"Dentist"}""",
            HrResponses.leaveJson(input),
        )
        assertEquals(
            listOf("type" to "AL", "start_date" to "2026-11-24", "end_date" to "2026-11-24", "is_half_day" to "1", "session" to "Morning", "reason" to "Dentist"),
            HrResponses.leaveFields(input),
        )
    }

    @Test
    fun `an empty leave form leaves out what was not chosen`() {
        val input = LeaveRequestInput(type = null, startDate = null, endDate = null, isHalfDay = false, session = LeaveSession.FULL, reason = "")

        assertEquals("""{"is_half_day":false,"session":"Full","reason":""}""", HrResponses.leaveJson(input))
        assertEquals(listOf("is_half_day" to "0", "session" to "Full", "reason" to ""), HrResponses.leaveFields(input))
    }

    @Test
    fun `an adjustment sends only the clockings that are missing and the timesheet it corrects`() {
        val input = ClockingAdjustmentInput(
            date = LocalDate.of(2026, 11, 10),
            timesheetId = 8812,
            requestedStartAt = null,
            requestedEndAt = "17:30",
            reason = "Forgot to clock out",
        )

        assertEquals(
            """{"date":"2026-11-10","timesheet_id":8812,"requested_end_at":"17:30","reason":"Forgot to clock out"}""",
            HrResponses.clockingAdjustmentJson(input),
        )
        assertEquals(
            listOf("date" to "2026-11-10", "timesheet_id" to "8812", "requested_end_at" to "17:30", "reason" to "Forgot to clock out"),
            HrResponses.clockingAdjustmentFields(input),
        )
    }

    @Test
    fun `an overtime request leaves out empty values and the default lieu time`() {
        val input = OvertimeRequestInput(
            overtimeTypeId = 4,
            requestedDate = "2026-11-10",
            requestedStartAt = null,
            requestedEndAt = null,
            requestedDurationMinutes = 90,
            reason = null,
        )

        assertEquals("""{"overtime_type_id":4,"requested_date":"2026-11-10","requested_duration_minutes":90}""", HrResponses.overtimeJson(input))
        assertEquals(
            """{"overtime_type_id":4,"requested_date":"2026-11-10","requested_duration_minutes":90,"lieu_requested_minutes":30}""",
            HrResponses.overtimeJson(input.copy(lieuRequestedMinutes = 30)),
        )
    }
}
