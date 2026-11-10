package io.aiku.droid.ui.dashboard

import io.aiku.droid.ui.components.Tone

internal data class StockMovement(val code: String, val chip: String, val subtitle: String, val tone: Tone)

internal object DashboardSamples {
    const val DISPATCHED_TODAY = "142 of 228"
    const val DISPATCHED_FRACTION = 0.62f
    const val SKOS_IN_WAREHOUSE = "4,812 SKOs in this warehouse"

    val goodsOut = listOf(
        StateCount("47", "To do", Tone.Neutral),
        StateCount("17", "Picking", Tone.Primary),
        StateCount("2", "Waiting", Tone.Warn),
        StateCount("26", "Packed", Tone.Primary),
    )
    val goodsIn = listOf(
        StateCount("3", "Arriving", Tone.Neutral),
        StateCount("2", "Received", Tone.Primary),
        StateCount("1", "Checked", Tone.Primary),
        StateCount("1", "Booking in", Tone.Primary),
    )
    val inventory = listOf(
        StateCount("17", "Low", Tone.Warn),
        StateCount("5", "Critical", Tone.Danger),
        StateCount("3", "Out of stock", Tone.Danger),
    )
    val movements = listOf(
        StockMovement("SKO-00412", "Purchase +120", "into A-03-02 · Sam P. · 10 min ago", Tone.Success),
        StockMovement("SKO-00733", "Picked −6", "for delivery note AR24790 · you · 24 min ago", Tone.Primary),
    )
}
