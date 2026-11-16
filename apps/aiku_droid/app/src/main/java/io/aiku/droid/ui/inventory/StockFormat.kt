package io.aiku.droid.ui.inventory

import io.aiku.droid.ui.goodsout.quantityText
import java.text.NumberFormat
import java.time.Instant
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Currency
import java.util.Locale
import kotlin.math.abs
import kotlin.math.roundToInt

private const val DATE_PATTERN = "d MMM"
private const val DATE_TIME_PATTERN = "d MMM HH:mm"
private const val WHOLE_MONEY_FROM = 1000.0
private const val PERCENT = 100
private const val MONEY_DECIMALS = 2

internal fun moneyText(value: Double, currencyCode: String?): String {
    val locale = Locale.getDefault()
    val currency = currencyCode?.takeIf { it.isNotBlank() }?.let { runCatching { Currency.getInstance(it.uppercase()) }.getOrNull() }
    val format = if (currency != null) NumberFormat.getCurrencyInstance(locale).apply { this.currency = currency } else NumberFormat.getNumberInstance(locale)
    if (abs(value) >= WHOLE_MONEY_FROM) {
        format.minimumFractionDigits = 0
        format.maximumFractionDigits = 0
    } else {
        format.maximumFractionDigits = MONEY_DECIMALS
    }
    return format.format(value)
}

internal fun countText(value: Int): String = NumberFormat.getIntegerInstance(Locale.getDefault()).format(value)

internal fun percentOf(fraction: Double): Int = (fraction * PERCENT).roundToInt()

internal fun stockText(value: Double, label: String? = null): String = quantityText(value, label)

private fun instantOf(isoDateTime: String?): Instant? {
    val text = isoDateTime?.takeIf { it.isNotBlank() } ?: return null
    return runCatching { OffsetDateTime.parse(text).toInstant() }.getOrNull() ?: runCatching { Instant.parse(text) }.getOrNull()
}

internal fun dayText(isoDateTime: String?, zone: ZoneId): String? =
    instantOf(isoDateTime)?.let { DateTimeFormatter.ofPattern(DATE_PATTERN, Locale.getDefault()).withZone(zone).format(it) }

internal fun momentText(isoDateTime: String?, zone: ZoneId): String? =
    instantOf(isoDateTime)?.let { DateTimeFormatter.ofPattern(DATE_TIME_PATTERN, Locale.getDefault()).withZone(zone).format(it) }
