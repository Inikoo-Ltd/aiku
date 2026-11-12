package io.aiku.droid.ui.clocking

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import java.time.Instant

internal data class ClockingSummaryLines(val title: String, val subtitle: String, val isClockedIn: Boolean)

@Composable
internal fun clockingSummaryLines(state: ClockingUiState, now: Instant): ClockingSummaryLines? {
    val status = state.status
    if (status == null) {
        return state.statusProblem?.let {
            ClockingSummaryLines(
                title = stringResource(R.string.clocking_title),
                subtitle = stringResource(R.string.clocking_summary_unavailable),
                isClockedIn = false,
            )
        }
    }
    val zone = state.zone
    if (status.isClockedIn) {
        val since = clockingTime(status.since, zone) ?: stringResource(R.string.clocking_no_value)
        val organisation = status.employee?.organisation?.name.orEmpty()
        return ClockingSummaryLines(
            title = stringResource(R.string.clocking_summary_clocked_in_for, durationText(todayWorkingSeconds(status, now))),
            subtitle = if (organisation.isBlank()) {
                stringResource(R.string.clocking_summary_since, since)
            } else {
                stringResource(R.string.clocking_summary_since_at, since, organisation)
            },
            isClockedIn = true,
        )
    }
    val lastClockOut = clockingTime(status.today?.lastClockOut, zone)
    return ClockingSummaryLines(
        title = stringResource(R.string.clocking_status_clocked_out),
        subtitle = lastClockOut?.let { stringResource(R.string.clocking_summary_last_clock_out, it) }
            ?: stringResource(R.string.clocking_status_no_clocking_today),
        isClockedIn = false,
    )
}

@Composable
internal fun clockingMenuSubtitle(state: ClockingUiState): String? {
    val status = state.status ?: return null
    if (!status.isClockedIn) {
        return stringResource(R.string.clocking_status_clocked_out)
    }
    val since = clockingTime(status.since, state.zone) ?: return stringResource(R.string.clocking_status_clocked_in)
    return stringResource(R.string.clocking_summary_clocked_in_since, since)
}

@Composable
internal fun clockingWeekTotal(state: ClockingUiState, now: Instant): String? =
    state.thisWeek?.let { durationText(weekWorkingSeconds(it, state.status, now)) }
