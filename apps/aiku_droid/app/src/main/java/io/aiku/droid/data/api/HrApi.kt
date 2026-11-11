package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import java.io.File
import java.time.LocalDate
import java.time.YearMonth

object HrStatus {
    const val PENDING = "pending"
    const val APPROVED = "approved"
    const val REJECTED = "rejected"
    const val CANCELLED = "cancelled"
    const val RECORDED = "recorded"
}

object LeaveSession {
    const val FULL = "Full"
    const val MORNING = "Morning"
    const val AFTERNOON = "Afternoon"
}

private const val MEDICAL_CATEGORY = "medical"
private const val HALF_DAY_VALUE = 0.5

@Serializable
data class HrPagination(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    @SerialName("per_page") val perPage: Int = 10,
    val total: Int = 0,
)

@Serializable
data class HrAttachment(val id: Long, val name: String = "", val url: String = "")

@Serializable
data class PublicHoliday(val date: String, val label: String = "")

@Serializable
data class Leave(
    val id: Long,
    val type: String = "",
    @SerialName("type_label") val typeLabel: String = "",
    @SerialName("type_color_hex") val typeColorHex: String? = null,
    @SerialName("start_date") val startDate: String = "",
    @SerialName("end_date") val endDate: String = "",
    @SerialName("duration_days") val durationDays: Double = 0.0,
    @SerialName("is_half_day") val isHalfDay: Boolean = false,
    @SerialName("leave_type_value") val leaveTypeValue: Double = 1.0,
    val session: String? = null,
    val reason: String? = null,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String = "",
    @SerialName("approval_total_steps") val approvalTotalSteps: Int = 0,
    @SerialName("approval_completed_steps") val approvalCompletedSteps: Int = 0,
    @SerialName("rejection_reason") val rejectionReason: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    val attachments: List<HrAttachment> = emptyList(),
) {
    val isPending: Boolean get() = status == HrStatus.PENDING
    val isRejected: Boolean get() = status == HrStatus.REJECTED
    val isHalfDayDuration: Boolean get() = leaveTypeValue == HALF_DAY_VALUE
}

@Serializable
data class LeaveBalance(
    val id: Long? = null,
    @SerialName("annual_days") val annualDays: Double = 0.0,
    @SerialName("annual_used") val annualUsed: Double = 0.0,
    @SerialName("annual_remaining") val annualRemaining: Double = 0.0,
    @SerialName("medical_used") val medicalUsed: Double = 0.0,
    @SerialName("unpaid_used") val unpaidUsed: Double = 0.0,
)

@Serializable
data class LeaveTypeOption(
    val code: String,
    val label: String = "",
    val category: String = "",
    @SerialName("max_days_per_year") val maxDaysPerYear: Double? = null,
    val value: Double = 1.0,
) {
    val isMedical: Boolean get() = category == MEDICAL_CATEGORY
}

@Serializable
data class LeavePage(
    val leaves: List<Leave> = emptyList(),
    val pagination: HrPagination? = null,
    val balance: LeaveBalance? = null,
    @SerialName("annual_submitted_days") val annualSubmittedDays: Double = 0.0,
    @SerialName("annual_remaining_after_submission") val annualRemainingAfterSubmission: Double = 0.0,
    @SerialName("medical_request_count") val medicalRequestCount: Double = 0.0,
    @SerialName("unpaid_request_count") val unpaidRequestCount: Double = 0.0,
    @SerialName("type_options") val typeOptions: List<LeaveTypeOption> = emptyList(),
    val holidays: List<PublicHoliday> = emptyList(),
) {
    fun typeOf(leave: Leave): LeaveTypeOption? = typeOptions.firstOrNull { it.code == leave.type }
}

@Serializable
data class ClockingAdjustment(
    val id: Long,
    @SerialName("timesheet_id") val timesheetId: Long? = null,
    val date: String = "",
    @SerialName("original_start_time_local") val originalStartTimeLocal: String? = null,
    @SerialName("original_end_time_local") val originalEndTimeLocal: String? = null,
    @SerialName("requested_start_time_local") val requestedStartTimeLocal: String? = null,
    @SerialName("requested_end_time_local") val requestedEndTimeLocal: String? = null,
    val reason: String? = null,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String = "",
    @SerialName("approval_comment") val approvalComment: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    val attachments: List<HrAttachment> = emptyList(),
) {
    val isPending: Boolean get() = status == HrStatus.PENDING
    val isRejected: Boolean get() = status == HrStatus.REJECTED
}

@Serializable
data class ClockingAdjustmentPage(
    val adjustments: List<ClockingAdjustment> = emptyList(),
    val pagination: HrPagination? = null,
)

@Serializable
data class CancelledRequest(val id: Long, val cancelled: Boolean = false)

@Serializable
data class OvertimeRequest(
    val id: Long,
    @SerialName("overtime_type_id") val overtimeTypeId: Long? = null,
    @SerialName("overtime_type_name") val overtimeTypeName: String? = null,
    @SerialName("requested_date") val requestedDate: String = "",
    @SerialName("requested_start_at") val requestedStartAt: String? = null,
    @SerialName("requested_end_at") val requestedEndAt: String? = null,
    @SerialName("requested_duration_minutes") val requestedDurationMinutes: Int? = null,
    @SerialName("recorded_start_at") val recordedStartAt: String? = null,
    @SerialName("recorded_end_at") val recordedEndAt: String? = null,
    @SerialName("recorded_duration_minutes") val recordedDurationMinutes: Int? = null,
    @SerialName("lieu_requested_minutes") val lieuRequestedMinutes: Int = 0,
    val reason: String? = null,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String = "",
) {
    val isPending: Boolean get() = status == HrStatus.PENDING
}

@Serializable
data class OvertimeTypeOption(val value: Long, val label: String = "")

@Serializable
data class OvertimePage(
    @SerialName("overtime_requests") val overtimeRequests: List<OvertimeRequest> = emptyList(),
    val pagination: HrPagination? = null,
    @SerialName("type_options") val typeOptions: List<OvertimeTypeOption> = emptyList(),
)

@Serializable
data class HolidayRange(val from: String, val to: String, val label: String = "")

@Serializable
data class HolidayYear(
    val id: Long? = null,
    val label: String = "",
    @SerialName("start_date") val startDate: String? = null,
    @SerialName("end_date") val endDate: String? = null,
)

@Serializable
data class HolidayCalendar(
    val month: String = "",
    val holidays: List<PublicHoliday> = emptyList(),
    @SerialName("holiday_ranges") val holidayRanges: List<HolidayRange> = emptyList(),
    @SerialName("holiday_year") val holidayYear: HolidayYear? = null,
    val leaves: List<Leave> = emptyList(),
)

class UploadFile(val name: String, val mimeType: String, val readBytes: () -> ByteArray)

data class LeaveRequestInput(
    val type: String?,
    val startDate: LocalDate?,
    val endDate: LocalDate?,
    val isHalfDay: Boolean,
    val session: String,
    val reason: String,
    val attachments: List<UploadFile> = emptyList(),
)

data class ClockingAdjustmentInput(
    val date: LocalDate?,
    val timesheetId: Long?,
    val requestedStartAt: String?,
    val requestedEndAt: String?,
    val reason: String,
    val attachments: List<UploadFile> = emptyList(),
)

@Serializable
data class OvertimeRequestInput(
    @SerialName("overtime_type_id") val overtimeTypeId: Long?,
    @SerialName("requested_date") val requestedDate: String?,
    @SerialName("requested_start_at") val requestedStartAt: String?,
    @SerialName("requested_end_at") val requestedEndAt: String?,
    @SerialName("requested_duration_minutes") val requestedDurationMinutes: Int?,
    val reason: String?,
    @SerialName("lieu_requested_minutes") val lieuRequestedMinutes: Int = 0,
)

sealed interface HrWriteResult<out T> {
    data class Saved<T>(val value: T) : HrWriteResult<T>
    data class Invalid(val fieldErrors: Map<String, String>, val message: String) : HrWriteResult<Nothing>
    data class Refused(val message: String) : HrWriteResult<Nothing>
    data object SessionExpired : HrWriteResult<Nothing>
    data object Unreachable : HrWriteResult<Nothing>
    data class ServerError(val statusCode: Int) : HrWriteResult<Nothing>
}

sealed interface AttachmentDownload {
    data class Saved(val file: File, val mimeType: String?) : AttachmentDownload
    data class Refused(val message: String) : AttachmentDownload
    data object SessionExpired : AttachmentDownload
    data object Unreachable : AttachmentDownload
    data class ServerError(val statusCode: Int) : AttachmentDownload
}

interface HrApi {
    suspend fun leaves(token: String, page: Int, perPage: Int): ClockingResult<LeavePage>

    suspend fun requestLeave(token: String, input: LeaveRequestInput): HrWriteResult<Leave>

    suspend fun replaceLeaveAttachments(token: String, leaveId: Long, attachments: List<UploadFile>): HrWriteResult<Leave>

    suspend fun clockingAdjustments(token: String, page: Int, perPage: Int): ClockingResult<ClockingAdjustmentPage>

    suspend fun requestClockingAdjustment(token: String, input: ClockingAdjustmentInput): HrWriteResult<ClockingAdjustment>

    suspend fun cancelClockingAdjustment(token: String, adjustmentId: Long): HrWriteResult<CancelledRequest>

    suspend fun overtimeRequests(token: String, page: Int, perPage: Int): ClockingResult<OvertimePage>

    suspend fun requestOvertime(token: String, input: OvertimeRequestInput): HrWriteResult<OvertimeRequest>

    suspend fun updateOvertime(token: String, overtimeId: Long, input: OvertimeRequestInput): HrWriteResult<OvertimeRequest>

    suspend fun holidayCalendar(token: String, month: YearMonth): ClockingResult<HolidayCalendar>

    suspend fun downloadAttachment(token: String, url: String, target: File): AttachmentDownload
}
