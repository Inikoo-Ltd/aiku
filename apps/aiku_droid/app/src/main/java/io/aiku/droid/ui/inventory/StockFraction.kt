package io.aiku.droid.ui.inventory

import java.math.BigDecimal
import java.math.RoundingMode
import kotlin.math.abs
import kotlin.math.floor

private const val START_EPSILON = 0.00001
private const val MAX_RETRIES = 3
private const val EPSILON_GROWTH = 10
private const val MAX_DENOMINATOR = 100
private const val SMALL_NUMBER = 0.01
private const val TINY_NUMBER = 0.0001
private const val TINY_DENOMINATOR = 10000.0
private const val NEAR_INTEGER = 0.001
private const val LABEL_DECIMALS = 3
private const val TOTAL_DECIMALS = 3

data class StockFractionParts(val whole: Double, val numerator: Double, val denominator: Double)

internal fun stockFraction(value: Double, packedIn: Double?): StockFractionParts {
    val (dividend, divisor) = smallestFactors(abs(value), START_EPSILON, 0)
    val split = divideWithRemainder(dividend, divisor)
    return riseDivisor(split, packedIn ?: 1.0)
}

internal fun stockFractionLabel(value: Double, packedIn: Double?): String {
    val parts = stockFraction(value, packedIn)
    val sign = if (value < 0) "-" else ""
    val whole = plainNumber(parts.whole)
    val fraction = "${plainNumber(parts.numerator)}/${plainNumber(parts.denominator)}"
    return when {
        parts.whole != 0.0 && parts.numerator != 0.0 -> "$sign$whole $fraction"
        parts.whole != 0.0 -> "$sign$whole"
        parts.numerator != 0.0 -> "$sign$fraction"
        else -> "0"
    }
}

internal fun stockTotal(value: Double): Double = BigDecimal.valueOf(value).setScale(TOTAL_DECIMALS, RoundingMode.HALF_UP).toDouble()

private fun plainNumber(value: Double): String =
    BigDecimal.valueOf(value).setScale(LABEL_DECIMALS, RoundingMode.HALF_UP).stripTrailingZeros().toPlainString()

private fun phpRound(value: Double): Double = if (value < 0) -floor(-value + 0.5) else floor(value + 0.5)

private fun smallestFactors(number: Double, epsilon: Double, retry: Int): Pair<Double, Double> {
    if (number == 0.0) return 0.0 to 1.0
    if (abs(number - TINY_NUMBER) < 0.000001) return 1.0 to TINY_DENOMINATOR
    if (abs(number - 2.0001) < START_EPSILON) return 2.0 to 1.0
    val absolute = abs(number)
    if (absolute < SMALL_NUMBER) return smallNumberFactors(number)
    val sign = if (number < 0) -1.0 else 1.0
    val nearest = phpRound(absolute)
    if (abs(absolute - nearest) < epsilon) return sign * nearest to 1.0
    if (absolute < 1) {
        var bestNumerator = 1.0
        var bestDenominator = 1.0
        var bestDiff = Double.MAX_VALUE
        for (denominator in 1..MAX_DENOMINATOR) {
            var numerator = 1
            while (numerator.toDouble() / denominator <= absolute + epsilon) {
                val diff = abs(numerator.toDouble() / denominator - absolute)
                if (diff < bestDiff) {
                    bestDiff = diff
                    bestNumerator = numerator.toDouble()
                    bestDenominator = denominator.toDouble()
                }
                numerator++
            }
        }
        if (bestDiff < epsilon) return sign * bestNumerator to bestDenominator
    } else {
        if (absolute - floor(absolute) < epsilon) return sign * absolute to 1.0
        val whole = floor(absolute)
        val (numerator, denominator) = smallestFactors(absolute - whole, epsilon, 0)
        return sign * (numerator + whole * denominator) to denominator
    }
    return if (retry < MAX_RETRIES) smallestFactors(number, epsilon * EPSILON_GROWTH, retry + 1) else sign to 1.0
}

private fun smallNumberFactors(number: Double): Pair<Double, Double> {
    val absolute = abs(number)
    val sign = if (number < 0) -1.0 else 1.0
    if (absolute < TINY_NUMBER) return sign to TINY_DENOMINATOR
    val nearest = phpRound(absolute)
    if (abs(absolute - nearest) < NEAR_INTEGER) return sign * nearest to 1.0
    if (absolute > 0) {
        val special = listOf(SMALL_NUMBER to 100.0, 0.001 to 1000.0, 0.002 to 500.0)
        special.firstOrNull { (at, _) -> abs(absolute - at) < if (at == SMALL_NUMBER) TINY_NUMBER else START_EPSILON }
            ?.let { (_, denominator) -> return sign to denominator }
        val denominator = phpRound(1 / absolute)
        if (denominator > 0) return sign to denominator
    }
    return sign to 1.0
}

private fun divideWithRemainder(dividend: Double, divisor: Double): StockFractionParts {
    if (divisor == 0.0) return StockFractionParts(0.0, dividend, divisor)
    val wholeDividend = dividend.toLong()
    val wholeDivisor = divisor.toLong()
    return StockFractionParts((wholeDividend / wholeDivisor).toDouble(), (wholeDividend % wholeDivisor).toDouble(), divisor)
}

private fun riseDivisor(parts: StockFractionParts, raiser: Double): StockFractionParts {
    if (parts.denominator == 0.0 || raiser == 0.0) return parts
    val numerator = parts.numerator * raiser / parts.denominator
    if (numerator != phpRound(numerator)) return parts
    return StockFractionParts(parts.whole, phpRound(numerator), raiser)
}
