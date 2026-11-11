package io.aiku.droid.data.api

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

private const val HTTP_OK = 200
private const val HTTP_CREATED = 201
private const val HTTP_UNAUTHORISED = 401
private const val HTTP_FORBIDDEN = 403
private const val HTTP_NOT_FOUND = 404
private const val HTTP_UNPROCESSABLE = 422
private const val MULTIPART_TRUE = "1"
private const val MULTIPART_FALSE = "0"

@Serializable
private data class HrData<T>(val data: T)

@Serializable
private data class HrRefusal(
    val message: String = "",
    val errors: Map<String, List<String>> = emptyMap(),
) {
    val fieldErrors: Map<String, String>
        get() = errors.mapNotNull { (field, messages) -> messages.firstOrNull { it.isNotBlank() }?.let { field to it } }.toMap()
}

object HrResponses {
    private val json = ClockingResponses.json

    fun parseLeaves(statusCode: Int, body: String): ClockingResult<LeavePage> = parseRead(statusCode, body)

    fun parseClockingAdjustments(statusCode: Int, body: String): ClockingResult<ClockingAdjustmentPage> = parseRead(statusCode, body)

    fun parseOvertimeRequests(statusCode: Int, body: String): ClockingResult<OvertimePage> = parseRead(statusCode, body)

    fun parseHolidayCalendar(statusCode: Int, body: String): ClockingResult<HolidayCalendar> = parseRead(statusCode, body)

    fun parseLeave(statusCode: Int, body: String): HrWriteResult<Leave> = parseWrite(statusCode, body)

    fun parseClockingAdjustment(statusCode: Int, body: String): HrWriteResult<ClockingAdjustment> = parseWrite(statusCode, body)

    fun parseCancellation(statusCode: Int, body: String): HrWriteResult<CancelledRequest> = parseWrite(statusCode, body)

    fun parseOvertimeRequest(statusCode: Int, body: String): HrWriteResult<OvertimeRequest> = parseWrite(statusCode, body)

    fun parseDownloadFailure(statusCode: Int, body: String): AttachmentDownload = when (statusCode) {
        HTTP_UNAUTHORISED -> AttachmentDownload.SessionExpired
        HTTP_FORBIDDEN, HTTP_NOT_FOUND -> AttachmentDownload.Refused(decode<HrRefusal>(body)?.message.orEmpty())
        else -> AttachmentDownload.ServerError(statusCode)
    }

    fun leaveFields(input: LeaveRequestInput): List<Pair<String, String>> = listOfNotNull(
        input.type?.let { "type" to it },
        input.startDate?.let { "start_date" to it.toString() },
        input.endDate?.let { "end_date" to it.toString() },
        "is_half_day" to if (input.isHalfDay) MULTIPART_TRUE else MULTIPART_FALSE,
        "session" to input.session,
        "reason" to input.reason,
    )

    fun leaveJson(input: LeaveRequestInput): String = buildJsonObject {
        input.type?.let { put("type", it) }
        input.startDate?.let { put("start_date", it.toString()) }
        input.endDate?.let { put("end_date", it.toString()) }
        put("is_half_day", input.isHalfDay)
        put("session", input.session)
        put("reason", input.reason)
    }.toString()

    fun clockingAdjustmentFields(input: ClockingAdjustmentInput): List<Pair<String, String>> = listOfNotNull(
        input.date?.let { "date" to it.toString() },
        input.timesheetId?.let { "timesheet_id" to it.toString() },
        input.requestedStartAt?.let { "requested_start_at" to it },
        input.requestedEndAt?.let { "requested_end_at" to it },
        "reason" to input.reason,
    )

    fun clockingAdjustmentJson(input: ClockingAdjustmentInput): String = buildJsonObject {
        input.date?.let { put("date", it.toString()) }
        input.timesheetId?.let { put("timesheet_id", it) }
        input.requestedStartAt?.let { put("requested_start_at", it) }
        input.requestedEndAt?.let { put("requested_end_at", it) }
        put("reason", input.reason)
    }.toString()

    fun overtimeJson(input: OvertimeRequestInput): String = json.encodeToString(input)

    private inline fun <reified T> parseRead(statusCode: Int, body: String): ClockingResult<T> = when (statusCode) {
        HTTP_OK -> decode<HrData<T>>(body)
            ?.let { ClockingResult.Loaded(it.data) }
            ?: ClockingResult.ServerError(statusCode)
        HTTP_UNAUTHORISED, HTTP_FORBIDDEN -> ClockingResult.SessionExpired
        HTTP_NOT_FOUND -> ClockingResult.AppKeyRejected
        else -> ClockingResult.ServerError(statusCode)
    }

    private inline fun <reified T> parseWrite(statusCode: Int, body: String): HrWriteResult<T> = when (statusCode) {
        HTTP_OK, HTTP_CREATED -> decode<HrData<T>>(body)
            ?.let { HrWriteResult.Saved(it.data) }
            ?: HrWriteResult.ServerError(statusCode)
        HTTP_UNAUTHORISED -> HrWriteResult.SessionExpired
        HTTP_FORBIDDEN, HTTP_NOT_FOUND -> HrWriteResult.Refused(decode<HrRefusal>(body)?.message.orEmpty())
        HTTP_UNPROCESSABLE -> decode<HrRefusal>(body)
            ?.let { HrWriteResult.Invalid(fieldErrors = it.fieldErrors, message = it.message) }
            ?: HrWriteResult.Invalid(fieldErrors = emptyMap(), message = "")
        else -> HrWriteResult.ServerError(statusCode)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()
}
