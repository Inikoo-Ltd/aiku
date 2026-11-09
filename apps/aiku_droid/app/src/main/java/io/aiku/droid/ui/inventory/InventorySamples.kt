package io.aiku.droid.ui.inventory

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone

internal enum class InventoryStockLevel(@StringRes val label: Int, val tone: Tone, val isFlagged: Boolean) {
    Excess(R.string.inventory_status_excess, Tone.Primary, isFlagged = false),
    Ideal(R.string.inventory_status_ideal, Tone.Success, isFlagged = false),
    Low(R.string.inventory_status_low, Tone.Warn, isFlagged = true),
    Critical(R.string.inventory_status_critical, Tone.Orange, isFlagged = true),
    OutOfStock(R.string.inventory_status_out_of_stock, Tone.Danger, isFlagged = true),
}

internal data class InventorySkuSample(
    val code: String,
    val name: String,
    val familyCode: String,
    val stock: String,
    val unitValue: String,
    val level: InventoryStockLevel,
)

internal val InventorySkuSamples = listOf(
    InventorySkuSample("SKU-00412", "Lavender essential oil 10ml", "EO", "1,240", "£1.85", InventoryStockLevel.Ideal),
    InventorySkuSample("SKU-00733", "Bath bomb – rose 180g", "BB", "36", "£0.95", InventoryStockLevel.Low),
    InventorySkuSample("SKU-00953", "Incense sticks – sandalwood", "IN", "8", "£0.40", InventoryStockLevel.Critical),
    InventorySkuSample("SKU-01187", "Soy wax candle – vanilla", "CA", "412", "£2.60", InventoryStockLevel.Ideal),
    InventorySkuSample("SKU-01402", "Eucalyptus essential oil 10ml", "EO", "0", "£1.70", InventoryStockLevel.OutOfStock),
)

internal data class InventoryLevelCount(val level: InventoryStockLevel, val count: String, val share: Float)

internal val InventoryLevelCounts = listOf(
    InventoryLevelCount(InventoryStockLevel.Excess, "289", 0.06f),
    InventoryLevelCount(InventoryStockLevel.Ideal, "4,498", 0.85f),
    InventoryLevelCount(InventoryStockLevel.Low, "17", 0.05f),
    InventoryLevelCount(InventoryStockLevel.Critical, "5", 0.02f),
    InventoryLevelCount(InventoryStockLevel.OutOfStock, "3", 0.02f),
)

internal data class InventoryAttentionSample(val code: String, val detail: String, val level: InventoryStockLevel)

internal val InventoryAttentionSamples = listOf(
    InventoryAttentionSample("SKU-01402", "Eucalyptus essential oil 10ml", InventoryStockLevel.OutOfStock),
    InventoryAttentionSample("SKU-00953", "Incense sticks – sandalwood · 8 left", InventoryStockLevel.Critical),
    InventoryAttentionSample("SKU-00733", "Bath bomb – rose 180g · 36 left", InventoryStockLevel.Low),
)

internal data class InventoryFamilySample(val code: String, val name: String, val summary: String, val stockValue: String)

internal val InventoryFamilySamples = listOf(
    InventoryFamilySample("BB", "Bath bombs", "42 SKUs · 2 low", "£3,920"),
    InventoryFamilySample("CA", "Candles", "118 SKUs", "£21,450"),
    InventoryFamilySample("EO", "Essential oils", "96 SKUs · 1 out of stock", "£18,300"),
    InventoryFamilySample("IN", "Incense", "64 SKUs · 1 critical", "£2,610"),
    InventoryFamilySample("SO", "Soaps", "57 SKUs", "£4,080"),
)

internal val InventoryFamilySkuSamples = listOf(
    InventorySkuSample("SKU-00733", "Bath bomb – rose 180g", "BB", "36", "£0.95", InventoryStockLevel.Low),
    InventorySkuSample("SKU-00734", "Bath bomb – lavender 180g", "BB", "210", "£0.95", InventoryStockLevel.Ideal),
    InventorySkuSample("SKU-00735", "Bath bomb – citrus 180g", "BB", "22", "£0.95", InventoryStockLevel.Low),
    InventorySkuSample("SKU-00740", "Bath bomb gift set ×6", "BB", "148", "£5.40", InventoryStockLevel.Ideal),
)

internal data class InventorySkuLocationSample(val code: String, val detail: String, val quantity: String, val isPicking: Boolean)

internal val InventorySkuLocationSamples = listOf(
    InventorySkuLocationSample("A-03-02", "Area A · last audit 02 Nov", "30", isPicking = true),
    InventorySkuLocationSample("B-01-04", "Area B · overflow", "6", isPicking = false),
)

internal val InventorySkuHistorySamples = listOf(
    StockMovement("Picked", "DN-24790 · A-03-02 · today 09:12", "−6", Tone.Danger),
    StockMovement("Location transfer", "B-01-04 → A-03-02 · yesterday", "20", Tone.Neutral),
    StockMovement("Purchase", "SD-1071 · B-01-04 · 04 Nov", "+48", Tone.Success),
)
