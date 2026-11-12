package io.aiku.droid.ui.clocking

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.ClockingApi
import io.aiku.droid.data.api.ClockingNoteResult
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.QrClockingRequest
import io.aiku.droid.data.api.QrClockingResult
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.LocalDate

class ClockingViewModel(
    private val clockingApi: ClockingApi,
    private val sessions: SessionRepository,
) : ViewModel(), ClockingController {

    private val mutableState = MutableStateFlow(ClockingUiState())
    override val state: StateFlow<ClockingUiState> = mutableState.asStateFlow()

    private val mutableSessionExpired = MutableStateFlow(false)
    val isSessionExpired: StateFlow<Boolean> = mutableSessionExpired.asStateFlow()

    private var statusJob: Job? = null
    private var weekJob: Job? = null
    private var dayJob: Job? = null
    private var shiftsJob: Job? = null

    init {
        viewModelScope.launch {
            sessions.session
                .map { it?.token }
                .distinctUntilChanged()
                .collect { token ->
                    listOfNotNull(statusJob, weekJob, dayJob, shiftsJob).forEach { it.cancel() }
                    mutableState.value = ClockingUiState()
                    mutableSessionExpired.value = false
                    if (token != null) {
                        refresh()
                    }
                }
        }
    }

    override fun refresh() {
        val token = currentToken() ?: return
        statusJob?.cancel()
        mutableState.update { it.copy(isLoadingStatus = true) }

        statusJob = viewModelScope.launch {
            launch {
                when (val result = clockingApi.clocking(token)) {
                    is ClockingResult.Loaded -> mutableState.update { it.copy(status = result.value, isLoadingStatus = false, statusProblem = null) }
                    ClockingResult.SessionExpired -> expireSession()
                    else -> mutableState.update { it.copy(isLoadingStatus = false, statusProblem = problemOf(result)) }
                }
            }
            launch {
                when (val result = clockingApi.timesheets(token, from = null, to = null)) {
                    is ClockingResult.Loaded -> mutableState.update { it.copy(thisWeek = result.value) }
                    ClockingResult.SessionExpired -> expireSession()
                    else -> Unit
                }
            }
        }
    }

    override fun loadWeek(from: LocalDate?) {
        val token = currentToken() ?: return
        weekJob?.cancel()
        mutableState.update { current ->
            val keptPeriod = current.week.period.takeIf { current.week.requestedFrom == from }
            current.copy(week = TimesheetWeekState(requestedFrom = from, period = keptPeriod, isLoading = true))
        }

        weekJob = viewModelScope.launch {
            when (val result = clockingApi.timesheets(token, from = from, to = from?.plusDays(DAYS_AFTER_MONDAY))) {
                is ClockingResult.Loaded -> mutableState.update { current ->
                    current.copy(
                        week = current.week.copy(period = result.value, isLoading = false, problem = null),
                        thisWeek = if (from == null) result.value else current.thisWeek,
                    )
                }
                ClockingResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(week = it.week.copy(isLoading = false, problem = problemOf(result))) }
            }
        }
    }

    override fun loadDay(date: LocalDate) {
        val token = currentToken() ?: return
        dayJob?.cancel()
        mutableState.update { current ->
            val keptTimesheet = current.day?.takeIf { it.date == date }?.timesheet
            current.copy(day = TimesheetDayState(date = date, timesheet = keptTimesheet, isLoading = true))
        }

        dayJob = viewModelScope.launch {
            when (val result = clockingApi.timesheets(token, from = date, to = date)) {
                is ClockingResult.Loaded -> mutableState.update { current ->
                    val timesheet = result.value.timesheets.firstOrNull { it.date == date.toString() }
                    current.copy(day = TimesheetDayState(date = date, timesheet = timesheet, isLoading = false))
                }
                ClockingResult.SessionExpired -> expireSession()
                else -> mutableState.update { current ->
                    current.copy(day = current.day?.copy(isLoading = false, problem = problemOf(result)))
                }
            }
        }
    }

    override fun loadShifts() {
        val token = currentToken() ?: return
        shiftsJob?.cancel()
        mutableState.update { it.copy(shifts = ShiftsState.Loading) }

        shiftsJob = viewModelScope.launch {
            when (val result = clockingApi.shifts(token)) {
                is ClockingResult.Loaded -> mutableState.update { it.copy(shifts = ShiftsState.Loaded(result.value)) }
                ClockingResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(shifts = ShiftsState.Failed) }
            }
        }
    }

    override fun prepareScan(setup: ClockingScanSetup) {
        mutableState.update { it.copy(scanSetup = setup, scan = ClockingScanState.Waiting, note = ClockingNoteState.Idle) }
    }

    override fun restartScan() {
        mutableState.update { it.copy(scan = ClockingScanState.Waiting, note = ClockingNoteState.Idle) }
    }

    override fun clockInWithQr(qrCode: String) {
        val token = currentToken() ?: return
        if (mutableState.value.scan != ClockingScanState.Waiting) {
            return
        }
        val setup = mutableState.value.scanSetup
        mutableState.update { it.copy(scan = ClockingScanState.Sending(qrCode)) }

        viewModelScope.launch {
            val request = QrClockingRequest(
                qrCode = qrCode,
                latitude = setup.coordinates?.latitude,
                longitude = setup.coordinates?.longitude,
                workScheduleId = setup.workScheduleId,
            )
            val scan = when (val result = clockingApi.clockWithQr(token, request)) {
                is QrClockingResult.Recorded -> ClockingScanState.Recorded(result.result)
                is QrClockingResult.Refused -> ClockingScanState.Refused(qrCode = qrCode, message = result.message)
                QrClockingResult.Unreachable -> ClockingScanState.Offline
                is QrClockingResult.ServerError -> ClockingScanState.Failed(result.statusCode)
                QrClockingResult.SessionExpired -> {
                    expireSession()
                    ClockingScanState.Waiting
                }
            }
            mutableState.update { it.copy(scan = scan) }
            if (scan is ClockingScanState.Recorded) {
                refresh()
            }
        }
    }

    override fun saveNote(clockingId: Long, note: String) {
        val token = currentToken() ?: return
        if (mutableState.value.note == ClockingNoteState.Saving) {
            return
        }
        mutableState.update { it.copy(note = ClockingNoteState.Saving) }

        viewModelScope.launch {
            val noteState = when (val result = clockingApi.saveNote(token, clockingId, note.trim().ifEmpty { null })) {
                is ClockingNoteResult.Saved -> ClockingNoteState.Saved
                ClockingNoteResult.Unreachable -> ClockingNoteState.Failed(ClockingProblem.Offline)
                ClockingNoteResult.Refused, is ClockingNoteResult.ServerError -> ClockingNoteState.Failed(ClockingProblem.ServerError)
                ClockingNoteResult.SessionExpired -> {
                    expireSession()
                    ClockingNoteState.Idle
                }
            }
            mutableState.update { it.copy(note = noteState) }
            if (noteState == ClockingNoteState.Saved) {
                refresh()
            }
        }
    }

    private fun currentToken(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableSessionExpired.value = true
    }

    private fun problemOf(result: ClockingResult<*>): ClockingProblem =
        if (result == ClockingResult.Unreachable) ClockingProblem.Offline else ClockingProblem.ServerError

    private companion object {
        const val DAYS_AFTER_MONDAY = 6L
    }
}
