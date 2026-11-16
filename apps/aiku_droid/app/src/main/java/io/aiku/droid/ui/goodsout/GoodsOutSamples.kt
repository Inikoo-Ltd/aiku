package io.aiku.droid.ui.goodsout

internal enum class LineStatus { ToHandle, Partial, Picked, Packed, Waiting, NotPicked }

internal data class SampleChoice(val title: String, val subtitle: String)

internal data class SampleScanEntry(val skuCode: String, val detail: String, val isPicked: Boolean)

internal object GoodsOutSamples {
    const val SAMPLE_DELIVERY_NOTE_ID = 0L
    const val CHANNEL_WHOLESALE = "Wholesale"
    const val CHANNEL_DROPSHIPPING = "Dropshipping"
    const val PICKING_LOCATION = "A-03-02"

    const val SKO_NAME = "Bath bomb – rose 180g"

    val wholesalePickingLocations = listOf(
        SampleChoice("A-03-02", "30 stocks"),
        SampleChoice("C-04-02", "14 stocks"),
    )

    val dropshippingPickingLocations = listOf(
        SampleChoice("B-02-01", "Empty"),
        SampleChoice("C-04-02", "14 stocks"),
    )

    const val SCAN_PICKED_COUNT = 8
    const val SCAN_TOTAL_COUNT = 14
    const val SCAN_QUEUED = "Each scan picks 1 · 2 queued"
    const val SCAN_SKO = "SKO-00733"
    const val SCAN_REMAINING = 23
    const val SCAN_PICKED = 1
    const val SCAN_AMOUNT = 6

    val scannedEntries = listOf(
        SampleScanEntry("SKO-00733", "09:41:20 · It belongs to this order", isPicked = true),
        SampleScanEntry("SKO-00412", "09:41:12 · not on this delivery note", isPicked = false),
        SampleScanEntry("SKO-00733", "09:40:58", isPicked = true),
    )
}
