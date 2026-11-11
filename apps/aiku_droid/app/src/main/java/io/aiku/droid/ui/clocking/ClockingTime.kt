package io.aiku.droid.ui.clocking

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.produceState
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.data.api.Timesheet
import io.aiku.droid.data.api.TimesheetPeriod
import kotlinx.coroutines.delay
import java.time.Duration
import java.time.Instant
import java.time.LocalDate
import java.time.LocalTime
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter

private const val CLOCK_TICK_MILLIS = 30_000L
private const val SECONDS_PER_MINUTE = 60L
private const val MINUTES_PER_HOUR = 60L
private const val SESSION_STATUS_ERROR = "error"

private val TimeOfDay: DateTimeFormatter = DateTimeFormatter.ofPattern("HH:mm")
private val TimeOfDayWithSeconds: DateTimeFormatter = DateTimeFormatter.ofPattern("HH:mm:ss")

internal enum class TimesheetDayStatus { Complete, Open, Error }

internal fun parseClockingInstant(iso: String?): Instant? =
    iso?.let { runCatching { OffsetDateTime.parse(it).toInstant() }.getOrNull() }

internal fun parseClockingDate(iso: String?): LocalDate? =
    iso?.let { runCatching { LocalDate.parse(it) }.getOrNull() }

internal fun clockingTime(iso: String?, zone: ZoneId, withSeconds: Boolean = false): String? =
    parseClockingInstant(iso)
        ?.atZone(zone)
        ?.format(if (withSeconds) TimeOfDayWithSeconds else TimeOfDay)

internal fun scheduleTime(value: String?): String? =
    value?.let { runCatching { LocalTime.parse(it).format(TimeOfDay) }.getOrNull() }

internal fun ongoingSeconds(status: ClockingStatus, now: Instant): Long {
    if (!status.isClockedIn) {
        return 0
    }
    val since = parseClockingInstant(status.since) ?: return 0
    return Duration.between(since, now).seconds.coerceAtLeast(0)
}

internal fun todayWorkingSeconds(status: ClockingStatus, now: Instant): Long =
    (status.today?.workingSeconds ?: 0L) + ongoingSeconds(status, now)

internal fun timesheetWorkingSeconds(timesheet: Timesheet, status: ClockingStatus?, now: Instant): Long {
    val ongoing = status?.takeIf { it.today?.date == timesheet.date }?.let { ongoingSeconds(it, now) } ?: 0L
    return timesheet.workingSeconds + ongoing
}

internal fun weekWorkingSeconds(period: TimesheetPeriod, status: ClockingStatus?, now: Instant): Long {
    val closedSeconds = period.timesheets.sumOf { it.workingSeconds }
    val today = parseClockingDate(status?.today?.date)
    val from = parseClockingDate(period.from)
    val to = parseClockingDate(period.to)
    val coversToday = today != null && from != null && to != null && !today.isBefore(from) && !today.isAfter(to)
    val ongoing = status?.takeIf { coversToday }?.let { ongoingSeconds(it, now) } ?: 0L
    return closedSeconds + ongoing
}

internal fun timesheetDayStatus(timesheet: Timesheet, today: LocalDate): TimesheetDayStatus {
    val hasOpenSession = timesheet.sessions.any { it.isOpen }
    val hasError = timesheet.sessions.any { it.status == SESSION_STATUS_ERROR }
    return when {
        hasError -> TimesheetDayStatus.Error
        !hasOpenSession -> TimesheetDayStatus.Complete
        parseClockingDate(timesheet.date) == today -> TimesheetDayStatus.Open
        else -> TimesheetDayStatus.Error
    }
}

@Composable
internal fun durationText(seconds: Long): String {
    val totalMinutes = seconds.coerceAtLeast(0) / SECONDS_PER_MINUTE
    val hours = totalMinutes / MINUTES_PER_HOUR
    val minutes = totalMinutes % MINUTES_PER_HOUR
    return if (hours > 0) {
        stringResource(R.string.clocking_duration_hours_minutes, hours, minutes)
    } else {
        stringResource(R.string.clocking_duration_minutes, minutes)
    }
}

@Composable
internal fun rememberClockingNow(): Instant {
    val now by produceState(initialValue = Instant.now()) {
        while (true) {
            delay(CLOCK_TICK_MILLIS)
            value = Instant.now()
        }
    }
    return now
}
