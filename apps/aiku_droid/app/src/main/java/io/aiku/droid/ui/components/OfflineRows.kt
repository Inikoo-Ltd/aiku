package io.aiku.droid.ui.components

import java.time.Instant
import java.time.LocalDate
import java.time.OffsetDateTime
import java.time.ZoneId

private const val ISO_DATE_LENGTH = 10

data class OfflineRows<T>(
    val rows: List<T>,
    val savedAt: Instant,
    val zone: ZoneId,
    val today: LocalDate,
    val userId: Long? = null,
)

internal fun rowInstant(value: String?): Instant? {
    val text = value?.trim()?.takeIf { it.isNotEmpty() } ?: return null
    return runCatching { OffsetDateTime.parse(text).toInstant() }.getOrNull() ?: runCatching { Instant.parse(text) }.getOrNull()
}

internal fun rowDay(value: String?, zone: ZoneId): LocalDate? {
    val text = value?.trim()?.takeIf { it.isNotEmpty() } ?: return null
    return rowInstant(text)?.atZone(zone)?.toLocalDate() ?: runCatching { LocalDate.parse(text.take(ISO_DATE_LENGTH)) }.getOrNull()
}

internal fun matchesSearch(search: String?, vararg fields: String?): Boolean {
    val wanted = search?.trim()?.takeIf { it.isNotEmpty() } ?: return true
    return fields.any { field -> field?.contains(wanted, ignoreCase = true) == true }
}

internal fun withinDays(value: String?, from: LocalDate?, to: LocalDate?, zone: ZoneId): Boolean {
    if (from == null && to == null) {
        return true
    }
    val day = rowDay(value, zone) ?: return false
    return (from == null || !day.isBefore(from)) && (to == null || !day.isAfter(to))
}

internal fun nameKey(value: String?): String? = value?.trim()?.takeIf { it.isNotEmpty() }?.lowercase()

internal fun <T, K : Comparable<K>> List<T>.sortedNullsLast(
    descending: Boolean,
    id: (T) -> Long,
    idDescending: Boolean = descending,
    key: (T) -> K?,
): List<T> {
    val byKey: Comparator<T> = compareBy(if (descending) nullsLast(reverseOrder()) else nullsLast(naturalOrder())) { row: T -> key(row) }
    val byId: Comparator<T> = if (idDescending) compareByDescending(id) else compareBy(id)
    return sortedWith(byKey.then(byId))
}
