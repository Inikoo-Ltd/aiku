package io.aiku.droid.ui.locations

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone

internal enum class LocationKind(@StringRes val label: Int, val tone: Tone) {
    Stocks(R.string.locations_kind_stocks, Tone.Primary),
    Fulfilment(R.string.locations_kind_fulfilment, Tone.Neutral),
    Broken(R.string.locations_status_broken, Tone.Danger),
}

internal enum class SkoLocationType(@StringRes val label: Int, val tone: Tone) {
    Picking(R.string.locations_type_picking, Tone.Primary),
    Storing(R.string.locations_type_storing, Tone.Neutral),
}

internal data class SampleArea(
    val code: String,
    val name: String,
    val pickingOrder: Int,
    val locationCount: Int,
    val emptyCount: Int,
    val stockValue: String,
)

internal data class SampleLocation(
    val code: String,
    val kind: LocationKind,
    val area: String,
    val slots: Int? = null,
    val emptySlots: Int = 0,
    val weight: String? = null,
    val volume: String? = null,
    val pallets: Int? = null,
) {
    val isEmpty: Boolean get() = slots == null && pallets == null
    val isPartiallyEmpty: Boolean get() = slots != null && emptySlots > 0
}

internal data class SampleLocationFlag(@StringRes val label: Int, val isAllowed: Boolean)

internal data class SampleLocationSko(
    val code: String,
    val name: String,
    val type: SkoLocationType,
    val quantity: Int,
)

internal data class SampleAuditLine(val code: String, val was: Int, val counted: Int)

internal object LocationsSamples {
    const val AREA_COUNT = "6"
    const val LOCATION_COUNT = "1,284"
    const val EMPTY_LOCATION_COUNT = "180"
    const val PARTIALLY_EMPTY_LOCATION_COUNT = "96"
    const val UTILISATION = "86%"
    const val AUTO_REMOVE_ENABLED = true
    const val AUTO_REMOVE_CHANGED_BY = "[name]"
    const val AUTO_REMOVE_CHANGED_ON = "02 Nov"
    const val TO_REMOVE_NOW = "37"
    const val REMOVED_LAST_7_DAYS = "214"

    const val FILTER_TOTAL = 842
    val filterAreas = listOf("A · Ground floor", "B · Mezzanine", "C · Bulk", "D · Dispatch")

    val areas = listOf(
        SampleArea(code = "A", name = "Area A · Picking", pickingOrder = 1, locationCount = 412, emptyCount = 12, stockValue = "[£ 96k]"),
        SampleArea(code = "B", name = "Area B · Overflow", pickingOrder = 2, locationCount = 280, emptyCount = 44, stockValue = "[£ 61k]"),
        SampleArea(code = "D", name = "Area D · Fulfilment", pickingOrder = 4, locationCount = 160, emptyCount = 31, stockValue = "[£ 0]"),
    )

    val locations = listOf(
        SampleLocation(code = "A-03-02", kind = LocationKind.Stocks, area = "Area A", slots = 4, emptySlots = 0, weight = "32 / 120 kg", volume = "0.4 / 1.2 m³"),
        SampleLocation(code = "A-07-01", kind = LocationKind.Stocks, area = "Area A", slots = 3, emptySlots = 1, weight = "18 / 120 kg"),
        SampleLocation(code = "D-02-01", kind = LocationKind.Fulfilment, area = "Area D", pallets = 1),
        SampleLocation(code = "B-09-05", kind = LocationKind.Broken, area = "Area B"),
    )

    val locationFlags = listOf(
        SampleLocationFlag(label = R.string.locations_flag_stocks, isAllowed = true),
        SampleLocationFlag(label = R.string.locations_flag_dropshipping, isAllowed = true),
        SampleLocationFlag(label = R.string.locations_flag_fulfilment, isAllowed = false),
    )
    const val LOCATION_MAX_WEIGHT = "120 kg"
    const val LOCATION_MAX_VOLUME = "1.2 m³"
    const val LOCATION_SLOTS = 4
    const val LOCATION_EMPTY_SLOTS = 0
    const val LOCATION_AUDITED_ON = "28 Oct"
    const val LOCATION_SKO_COUNT = "4"

    val skos = listOf(
        SampleLocationSko(code = "SKO-00733", name = "Bath bomb – rose 180g", type = SkoLocationType.Picking, quantity = 30),
        SampleLocationSko(code = "SKO-00412", name = "Lavender essential oil 10ml", type = SkoLocationType.Picking, quantity = 48),
        SampleLocationSko(code = "SKO-01402", name = "Eucalyptus essential oil 10ml", type = SkoLocationType.Storing, quantity = 120),
    )

    val auditLines = listOf(
        SampleAuditLine(code = "SKO-00733", was = 30, counted = 28),
        SampleAuditLine(code = "SKO-00412", was = 48, counted = 48),
        SampleAuditLine(code = "SKO-01402", was = 120, counted = 124),
    )

    const val MOVE_TARGET_CODE = "A-09-04"
    const val MOVE_TARGET_AREA = "Area A"

    val removableSkos = skos.filter { it.type == SkoLocationType.Storing }

    const val MIN_STOCK = "12"
    const val MAX_STOCK = "48"
    const val ACTIVE_FOR_WHOLESALE = true
    const val ACTIVE_FOR_DROPSHIPPING = false
    const val DROPSHIPPING_PICKING_LOCATION = "B-02-01"
    const val NOTE = "Top shelf, behind the gift sets"

    fun area(code: String): SampleArea = areas.firstOrNull { it.code == code } ?: areas.first()

    fun location(code: String): SampleLocation = locations.firstOrNull { it.code == code } ?: locations.first()

    fun sko(code: String): SampleLocationSko = skos.firstOrNull { it.code == code } ?: skos.first()
}
