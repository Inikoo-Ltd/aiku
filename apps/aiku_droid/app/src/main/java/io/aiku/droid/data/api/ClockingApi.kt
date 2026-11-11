package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import java.time.LocalDate

private const val CLOCKED_IN = "clocked_in"
private const val CLOCKED_OUT = "clocked_out"
private const val CLOCK_IN = "clock_in"
private const val CLOCK_OUT = "clock_out"

@Serializable
data class ClockingOrganisation(
    val id: Long,
    val code: String = "",
    val name: String = "",
)

@Serializable
data class ClockingEmployee(
    val id: Long,
    val name: String = "",
    val organisation: ClockingOrganisation? = null,
)

@Serializable
data class ClockingPlace(val id: Long, val name: String = "")

@Serializable
data class ClockingEvent(
    val id: Long,
    @SerialName("clocked_at") val clockedAt: String? = null,
    val type: String? = null,
    @SerialName("is_late") val isLate: Boolean = false,
    val notes: String? = null,
    val workplace: ClockingPlace? = null,
    @SerialName("clocking_machine") val clockingMachine: ClockingPlace? = null,
)

@Serializable
data class ClockingSession(
    val id: Long,
    val sequence: Int = 0,
    val status: String? = null,
    @SerialName("is_open") val isOpen: Boolean = false,
    @SerialName("starts_at") val startsAt: String? = null,
    @SerialName("ends_at") val endsAt: String? = null,
    val duration: Long? = null,
    @SerialName("clock_in") val clockIn: ClockingEvent? = null,
    @SerialName("clock_out") val clockOut: ClockingEvent? = null,
) {
    val clockingCount: Int get() = listOfNotNull(clockIn, clockOut).size
}

@Serializable
data class ClockingDaySummary(
    val date: String,
    @SerialName("first_clock_in") val firstClockIn: String? = null,
    @SerialName("last_clock_out") val lastClockOut: String? = null,
    @SerialName("working_seconds") val workingSeconds: Long = 0,
    @SerialName("breaks_seconds") val breaksSeconds: Long = 0,
)

@Serializable
data class ClockingStatus(
    val employee: ClockingEmployee? = null,
    val timezone: String? = null,
    val status: String = CLOCKED_OUT,
    val since: String? = null,
    val today: ClockingDaySummary? = null,
    val sessions: List<ClockingSession> = emptyList(),
    val methods: List<String> = emptyList(),
    val code: String? = null,
    @SerialName("has_shifts") val hasShifts: Boolean = false,
    @SerialName("can_edit_notes") val canEditNotes: Boolean = false,
) {
    val isClockedIn: Boolean get() = status == CLOCKED_IN
}

@Serializable
data class Timesheet(
    val date: String,
    val id: Long? = null,
    @SerialName("first_clock_in") val firstClockIn: String? = null,
    @SerialName("last_clock_out") val lastClockOut: String? = null,
    @SerialName("working_seconds") val workingSeconds: Long = 0,
    @SerialName("breaks_seconds") val breaksSeconds: Long = 0,
    @SerialName("start_time_local") val startTimeLocal: String? = null,
    @SerialName("end_time_local") val endTimeLocal: String? = null,
    @SerialName("number_time_trackers") val numberTimeTrackers: Int = 0,
    @SerialName("number_open_time_trackers") val numberOpenTimeTrackers: Int = 0,
    @SerialName("clock_in_count") val clockInCount: Int = 0,
    @SerialName("clock_out_count") val clockOutCount: Int = 0,
    @SerialName("has_pending_adjustment") val hasPendingAdjustment: Boolean = false,
    val sessions: List<ClockingSession> = emptyList(),
)

@Serializable
data class TimesheetStatistics(
    @SerialName("on_time") val onTime: Int = 0,
    @SerialName("late_clock_in") val lateClockIn: Int = 0,
    @SerialName("early_clock_out") val earlyClockOut: Int = 0,
    @SerialName("no_clock_out") val noClockOut: Int = 0,
    val invalid: Int = 0,
    val absent: Int = 0,
    val total: Int = 0,
)

@Serializable
data class TimesheetPeriod(
    val from: String? = null,
    val to: String? = null,
    val timezone: String? = null,
    val statistics: TimesheetStatistics? = null,
    @SerialName("target_seconds") val targetSeconds: Long? = null,
    val timesheets: List<Timesheet> = emptyList(),
)

@Serializable
data class ShiftSchedule(
    val id: Long,
    val name: String = "",
    val type: String = "",
    @SerialName("is_active") val isActive: Boolean = false,
)

@Serializable
data class QrClockingRequest(
    @SerialName("qr_code") val qrCode: String,
    val latitude: Double? = null,
    val longitude: Double? = null,
    @SerialName("work_schedule_id") val workScheduleId: Long? = null,
)

@Serializable
data class ClockingMachineSummary(
    val id: Long,
    val name: String = "",
    @SerialName("workplace_id") val workplaceId: Long? = null,
)

@Serializable
data class ClockingWorkingHours(
    val start: String? = null,
    val end: String? = null,
    val name: String? = null,
)

@Serializable
data class RecordedClocking(
    val id: Long,
    @SerialName("clocked_at") val clockedAt: String? = null,
    val type: String? = null,
    @SerialName("is_late") val isLate: Boolean = false,
) {
    val isClockOut: Boolean get() = type == CLOCK_OUT
    val isClockIn: Boolean get() = type == CLOCK_IN
}

@Serializable
data class QrClocking(
    val machine: ClockingMachineSummary? = null,
    @SerialName("is_visiting") val isVisiting: Boolean = false,
    @SerialName("working_hours") val workingHours: ClockingWorkingHours? = null,
    val clocking: RecordedClocking,
)

sealed interface ClockingResult<out T> {
    data class Loaded<T>(val value: T) : ClockingResult<T>
    data object SessionExpired : ClockingResult<Nothing>
    data object AppKeyRejected : ClockingResult<Nothing>
    data object Unreachable : ClockingResult<Nothing>
    data class ServerError(val statusCode: Int) : ClockingResult<Nothing>
}

sealed interface QrClockingResult {
    data class Recorded(val result: QrClocking) : QrClockingResult
    data class Refused(val message: String) : QrClockingResult
    data object SessionExpired : QrClockingResult
    data object Unreachable : QrClockingResult
    data class ServerError(val statusCode: Int) : QrClockingResult
}

sealed interface ClockingNoteResult {
    data class Saved(val notes: String?) : ClockingNoteResult
    data object Refused : ClockingNoteResult
    data object SessionExpired : ClockingNoteResult
    data object Unreachable : ClockingNoteResult
    data class ServerError(val statusCode: Int) : ClockingNoteResult
}

interface ClockingApi {
    suspend fun clocking(token: String): ClockingResult<ClockingStatus>

    suspend fun timesheets(token: String, from: LocalDate?, to: LocalDate?): ClockingResult<TimesheetPeriod>

    suspend fun shifts(token: String): ClockingResult<List<ShiftSchedule>>

    suspend fun clockWithQr(token: String, request: QrClockingRequest): QrClockingResult

    suspend fun saveNote(token: String, clockingId: Long, notes: String?): ClockingNoteResult
}
