package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

private const val HTTP_OK = 200
private const val HTTP_BAD_REQUEST = 400
private const val HTTP_UNAUTHORISED = 401
private const val HTTP_FORBIDDEN = 403
private const val HTTP_NOT_FOUND = 404
private const val HTTP_UNPROCESSABLE = 422
private const val SHIFT_TYPE = "shift"

@Serializable
private data class DataResponse<T>(val data: T)

@Serializable
private data class QrClockingResponse(
    val success: Boolean = false,
    val message: String = "",
    val machine: ClockingMachineSummary? = null,
    @SerialName("is_visiting") val isVisiting: Boolean = false,
    @SerialName("working_hours") val workingHours: ClockingWorkingHours? = null,
    val clocking: RecordedClocking? = null,
)

@Serializable
private data class SavedNote(val id: Long, val notes: String? = null)

@Serializable
private data class RefusalResponse(
    val message: String = "",
    val errors: Map<String, List<String>> = emptyMap(),
) {
    val firstMessage: String
        get() = errors.values.flatten().firstOrNull { it.isNotBlank() } ?: message
}

object ClockingResponses {
    val json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        coerceInputValues = true
    }

    fun parseClocking(statusCode: Int, body: String): ClockingResult<ClockingStatus> =
        parseData<ClockingStatus>(statusCode, body)

    fun parseTimesheets(statusCode: Int, body: String): ClockingResult<TimesheetPeriod> =
        parseData<TimesheetPeriod>(statusCode, body)

    fun parseShifts(statusCode: Int, body: String): ClockingResult<List<ShiftSchedule>> =
        when (val result = parseData<List<ShiftSchedule>>(statusCode, body)) {
            is ClockingResult.Loaded -> ClockingResult.Loaded(result.value.filter { it.type == SHIFT_TYPE && it.isActive })
            else -> result
        }

    fun parseQrClocking(statusCode: Int, body: String): QrClockingResult = when (statusCode) {
        HTTP_OK -> decode<QrClockingResponse>(body)
            ?.let { response ->
                val clocking = response.clocking
                if (response.success && clocking != null) {
                    QrClockingResult.Recorded(
                        QrClocking(
                            machine = response.machine,
                            isVisiting = response.isVisiting,
                            workingHours = response.workingHours,
                            clocking = clocking,
                        ),
                    )
                } else {
                    QrClockingResult.Refused(response.message)
                }
            }
            ?: QrClockingResult.ServerError(statusCode)
        HTTP_BAD_REQUEST, HTTP_UNPROCESSABLE -> QrClockingResult.Refused(decode<RefusalResponse>(body)?.firstMessage.orEmpty())
        HTTP_UNAUTHORISED, HTTP_FORBIDDEN -> QrClockingResult.SessionExpired
        else -> QrClockingResult.ServerError(statusCode)
    }

    fun parseNote(statusCode: Int, body: String): ClockingNoteResult = when (statusCode) {
        HTTP_OK -> decode<DataResponse<SavedNote>>(body)
            ?.let { ClockingNoteResult.Saved(it.data.notes) }
            ?: ClockingNoteResult.ServerError(statusCode)
        HTTP_UNAUTHORISED -> ClockingNoteResult.SessionExpired
        HTTP_FORBIDDEN, HTTP_NOT_FOUND, HTTP_UNPROCESSABLE -> ClockingNoteResult.Refused
        else -> ClockingNoteResult.ServerError(statusCode)
    }

    fun qrClockingBody(request: QrClockingRequest): String = json.encodeToString(request)

    fun noteBody(notes: String?): String = buildJsonObject { put("notes", notes) }.toString()

    private inline fun <reified T> parseData(statusCode: Int, body: String): ClockingResult<T> = when (statusCode) {
        HTTP_OK -> decode<DataResponse<T>>(body)
            ?.let { ClockingResult.Loaded(it.data) }
            ?: ClockingResult.ServerError(statusCode)
        HTTP_UNAUTHORISED, HTTP_FORBIDDEN -> ClockingResult.SessionExpired
        HTTP_NOT_FOUND -> ClockingResult.AppKeyRejected
        else -> ClockingResult.ServerError(statusCode)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()
}
