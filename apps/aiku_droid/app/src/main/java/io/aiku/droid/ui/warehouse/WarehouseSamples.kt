package io.aiku.droid.ui.warehouse

internal const val WarehouseNearlyFullPercent = 90

internal data class WarehouseSampleArea(
    val code: String,
    val name: String,
    val locationCount: String,
    val emptyCount: String,
    val usedPercent: Int?,
    val stockValue: String,
) {
    val isNearlyFull: Boolean get() = (usedPercent ?: 0) >= WarehouseNearlyFullPercent
}

internal data class WarehouseSampleSummary(
    val locationCount: String,
    val emptyLocationCount: String,
    val brokenLocationCount: String,
)

internal val WarehouseSampleOverview = WarehouseSampleSummary(
    locationCount = "1,284",
    emptyLocationCount = "96",
    brokenLocationCount = "5",
)

internal val WarehouseSampleAreas = listOf(
    WarehouseSampleArea(code = "A", name = "Area A · Picking", locationCount = "420", emptyCount = "22", usedPercent = 82, stockValue = "£96k"),
    WarehouseSampleArea(code = "B", name = "Area B · Overflow", locationCount = "310", emptyCount = "41", usedPercent = 64, stockValue = "£41k"),
    WarehouseSampleArea(code = "C", name = "Area C · Bulk", locationCount = "186", emptyCount = "4", usedPercent = 91, stockValue = "£29k"),
    WarehouseSampleArea(code = "D", name = "Area D · Fulfilment pallets", locationCount = "240", emptyCount = "29", usedPercent = 70, stockValue = "—"),
    WarehouseSampleArea(code = "GI", name = "Goods-in bays", locationCount = "8", emptyCount = "3", usedPercent = null, stockValue = "£6k"),
    WarehouseSampleArea(code = "GO", name = "Dispatch bays", locationCount = "120", emptyCount = "18", usedPercent = null, stockValue = "£10k"),
)
