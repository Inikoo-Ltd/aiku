package io.aiku.droid.ui.clocking

import androidx.compose.runtime.staticCompositionLocalOf
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.HolidayCalendar
import io.aiku.droid.data.api.HrApi
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.YearMonth

data class ClockingCalendarUiState(
    val months: Map<YearMonth, HolidayCalendar> = emptyMap(),
    val loadingMonth: YearMonth? = null,
    val failedMonth: YearMonth? = null,
    val problem: ClockingProblem? = null,
)

interface ClockingCalendarController {
    val state: StateFlow<ClockingCalendarUiState>

    fun loadMonth(month: YearMonth)
}

class ClockingCalendarViewModel(
    private val hrApi: HrApi,
    private val sessions: SessionRepository,
) : ViewModel(), ClockingCalendarController {

    private val mutableState = MutableStateFlow(ClockingCalendarUiState())
    override val state: StateFlow<ClockingCalendarUiState> = mutableState.asStateFlow()

    private val mutableSessionExpired = MutableStateFlow(false)
    val isSessionExpired: StateFlow<Boolean> = mutableSessionExpired.asStateFlow()

    private var monthJob: Job? = null

    init {
        onEachSessionToken(sessions) {
            monthJob?.cancel()
            mutableState.value = ClockingCalendarUiState()
            mutableSessionExpired.value = false
        }
    }

    override fun loadMonth(month: YearMonth) {
        val token = sessions.session.value?.token ?: return
        monthJob?.cancel()
        mutableState.update { it.copy(loadingMonth = month, failedMonth = null, problem = null) }

        monthJob = viewModelScope.launch {
            when (val result = hrApi.holidayCalendar(token, month)) {
                is ClockingResult.Loaded -> mutableState.update { it.copy(months = it.months + (month to result.value), loadingMonth = null) }
                ClockingResult.SessionExpired -> mutableSessionExpired.value = true
                else -> mutableState.update { it.copy(loadingMonth = null, failedMonth = month, problem = clockingProblemOf(result)) }
            }
        }
    }
}

private object NoClockingCalendar : ClockingCalendarController {
    override val state: StateFlow<ClockingCalendarUiState> = MutableStateFlow(ClockingCalendarUiState()).asStateFlow()
    override fun loadMonth(month: YearMonth) = Unit
}

val LocalClockingCalendar = staticCompositionLocalOf<ClockingCalendarController> { NoClockingCalendar }
