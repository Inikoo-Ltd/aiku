package io.aiku.droid.ui.clocking

import androidx.compose.runtime.staticCompositionLocalOf
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.data.api.QrClocking
import io.aiku.droid.data.api.ShiftSchedule
import io.aiku.droid.data.api.Timesheet
import io.aiku.droid.data.api.TimesheetPeriod
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.ZoneId
import java.time.temporal.TemporalAdjusters

enum class ClockingProblem { Offline, ServerError }

data class ClockingCoordinates(val latitude: Double, val longitude: Double)

data class ClockingScanSetup(val workScheduleId: Long? = null, val coordinates: ClockingCoordinates? = null)

sealed interface ClockingScanState {
    data object Waiting : ClockingScanState
    data class Sending(val qrCode: String) : ClockingScanState
    data class Recorded(val result: QrClocking) : ClockingScanState
    data class Refused(val qrCode: String, val message: String) : ClockingScanState
    data object Offline : ClockingScanState
    data class Failed(val statusCode: Int) : ClockingScanState
}

sealed interface ClockingNoteState {
    data object Idle : ClockingNoteState
    data object Saving : ClockingNoteState
    data object Saved : ClockingNoteState
    data class Failed(val problem: ClockingProblem) : ClockingNoteState
}

sealed interface ShiftsState {
    data object Loading : ShiftsState
    data class Loaded(val shifts: List<ShiftSchedule>) : ShiftsState
    data object Failed : ShiftsState
}

data class TimesheetWeekState(
    val requestedFrom: LocalDate? = null,
    val period: TimesheetPeriod? = null,
    val isLoading: Boolean = false,
    val problem: ClockingProblem? = null,
)

data class TimesheetDayState(
    val date: LocalDate,
    val timesheet: Timesheet? = null,
    val isLoading: Boolean = true,
    val problem: ClockingProblem? = null,
)

data class ClockingUiState(
    val status: ClockingStatus? = null,
    val isLoadingStatus: Boolean = false,
    val statusProblem: ClockingProblem? = null,
    val thisWeek: TimesheetPeriod? = null,
    val week: TimesheetWeekState = TimesheetWeekState(),
    val day: TimesheetDayState? = null,
    val shifts: ShiftsState = ShiftsState.Loading,
    val scanSetup: ClockingScanSetup = ClockingScanSetup(),
    val scan: ClockingScanState = ClockingScanState.Waiting,
    val note: ClockingNoteState = ClockingNoteState.Idle,
) {
    val zone: ZoneId
        get() = listOfNotNull(status?.timezone, thisWeek?.timezone)
            .firstNotNullOfOrNull { runCatching { ZoneId.of(it) }.getOrNull() }
            ?: ZoneId.systemDefault()

    val today: LocalDate get() = LocalDate.now(zone)

    val thisMonday: LocalDate get() = today.with(TemporalAdjusters.previousOrSame(DayOfWeek.MONDAY))
}

interface ClockingController {
    val state: StateFlow<ClockingUiState>

    fun refresh()

    fun loadWeek(from: LocalDate?)

    fun loadDay(date: LocalDate)

    fun loadShifts()

    fun prepareScan(setup: ClockingScanSetup)

    fun restartScan()

    fun clockInWithQr(qrCode: String)

    fun saveNote(clockingId: Long, note: String)
}

private object NoClocking : ClockingController {
    override val state: StateFlow<ClockingUiState> = MutableStateFlow(ClockingUiState()).asStateFlow()
    override fun refresh() = Unit
    override fun loadWeek(from: LocalDate?) = Unit
    override fun loadDay(date: LocalDate) = Unit
    override fun loadShifts() = Unit
    override fun prepareScan(setup: ClockingScanSetup) = Unit
    override fun restartScan() = Unit
    override fun clockInWithQr(qrCode: String) = Unit
    override fun saveNote(clockingId: Long, note: String) = Unit
}

val LocalClocking = staticCompositionLocalOf<ClockingController> { NoClocking }
