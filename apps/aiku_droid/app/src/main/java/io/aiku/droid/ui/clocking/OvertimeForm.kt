package io.aiku.droid.ui.clocking

import io.aiku.droid.data.api.OvertimeRequestInput
import java.time.LocalDate
import java.time.LocalTime
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter

private const val MINUTES_PER_HOUR = 60
private val OffsetDateTimeFormat: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ssxxx")

internal fun overtimeDurationMinutes(hours: String, minutes: String): Int? {
    val wholeHours = hours.trim().toIntOrNull()
    val extraMinutes = minutes.trim().toIntOrNull()
    if (wholeHours == null && extraMinutes == null) {
        return null
    }
    return (wholeHours ?: 0) * MINUTES_PER_HOUR + (extraMinutes ?: 0)
}

internal fun overtimeRequestInput(
    typeId: Long?,
    date: LocalDate?,
    startTime: LocalTime?,
    durationMinutes: Int?,
    reason: String,
    zone: ZoneId,
    lieuRequestedMinutes: Int = 0,
): OvertimeRequestInput {
    val start = if (date != null && startTime != null) ZonedDateTime.of(date, startTime, zone) else null
    val end = durationMinutes?.takeIf { it > 0 }?.let { minutes -> start?.plusMinutes(minutes.toLong()) }

    return OvertimeRequestInput(
        overtimeTypeId = typeId,
        requestedDate = date?.toString(),
        requestedStartAt = start?.format(OffsetDateTimeFormat),
        requestedEndAt = end?.format(OffsetDateTimeFormat),
        requestedDurationMinutes = durationMinutes,
        reason = reason.trim().ifEmpty { null },
        lieuRequestedMinutes = lieuRequestedMinutes,
    )
}

internal fun overtimeStartTime(requestedStartAt: String?, zone: ZoneId): LocalTime? =
    parseClockingInstant(requestedStartAt)?.atZone(zone)?.toLocalTime()?.withSecond(0)?.withNano(0)
