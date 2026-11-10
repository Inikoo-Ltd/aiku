package io.aiku.droid.ui.inventory

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone

internal enum class SkoBadge(@StringRes val label: Int, val tone: Tone) {
    Excess(R.string.inventory_status_excess, Tone.Primary),
    Ideal(R.string.inventory_status_ideal, Tone.Success),
    Low(R.string.inventory_status_low, Tone.Warn),
    Critical(R.string.inventory_status_critical, Tone.Danger),
    OutOfStock(R.string.inventory_status_out_of_stock, Tone.Danger),
    Abnormality(R.string.inventory_state_abnormality, Tone.Danger),
}

internal enum class LocationKind(@StringRes val label: Int, val tone: Tone) {
    Picking(R.string.inventory_location_picking, Tone.Primary),
    Storing(R.string.inventory_location_storing, Tone.Neutral),
}

internal enum class SalesChannel(@StringRes val label: Int) {
    Wholesale(R.string.inventory_channel_wholesale),
    Dropshipping(R.string.inventory_channel_dropshipping),
}

internal enum class MovementFilter(@StringRes val label: Int) {
    All(R.string.inventory_all),
    In(R.string.inventory_history_in),
    Out(R.string.inventory_history_out),
    Audit(R.string.inventory_history_audit),
}

internal enum class MovementType(@StringRes val label: Int, val filter: MovementFilter?) {
    Purchase(R.string.inventory_movement_purchase, MovementFilter.In),
    Picked(R.string.inventory_movement_picked, MovementFilter.Out),
    Audit(R.string.inventory_movement_audit, MovementFilter.Audit),
    LocationTransfer(R.string.inventory_movement_transfer, null),
    Consumption(R.string.inventory_movement_consumption, MovementFilter.Out),
}

internal data class SampleSko(
    val code: String,
    val name: String,
    val badge: SkoBadge,
    val stock: Int? = null,
    val locationCount: Int? = null,
    val detail: String? = null,
)

internal data class SampleFamily(
    val code: String,
    val name: String,
    @StringRes val state: Int,
    val skoCount: Int,
    val stockValue: String,
    val coverWeeks: Int,
)

internal data class SampleCount(@StringRes val label: Int, val count: Int?)

internal data class SampleDashboardTile(@StringRes val label: Int, val value: Int, val tone: Tone?, val opensSkos: Boolean, val labelArgument: Int? = null)

internal data class SampleMovement(
    val skoCode: String,
    val type: MovementType,
    val quantity: String,
    val tone: Tone,
    val detail: String,
)

internal data class SampleSkoLocation(
    val code: String,
    val kind: LocationKind,
    val channel: SalesChannel? = null,
    val minStock: Int? = null,
    val maxStock: Int? = null,
    val replenish: Int? = null,
    val lastAudit: String? = null,
    val stock: Int,
)

internal data class SampleSkoDetail(
    val isOnDemand: Boolean,
    val unitsPerSko: Int,
    val skoBarcode: String,
    val unitEan: String,
    val pickerNote: String,
    val inLocations: Int,
    val reserved: Int,
    val toBePicked: Int,
    val coverDays: Int,
    val locations: List<SampleSkoLocation>,
)

internal data class SampleAuditCount(val locationCode: String, val was: Int, val counted: Int)

internal data class SampleFoundLocation(val code: String, val detail: String)

internal const val SAMPLE_DROPSHIPPING_TO_REPLENISH = 3
internal const val SAMPLE_STOCK_VALUE = "[£ 412k]"
internal const val SAMPLE_UTILISATION_PERCENT = 86
internal const val SAMPLE_LOCATIONS_USED = 1104
internal const val SAMPLE_LOCATIONS_TOTAL = 1284
internal const val SAMPLE_CURRENT_SKOS = 4812
internal const val SAMPLE_ABNORMALITIES = 4
internal const val SAMPLE_MOVE_QUANTITY = 12
internal const val SAMPLE_CONSUME_QUANTITY = 1
internal const val SAMPLE_CONSUME_NOTE = "Showroom display"
internal const val SAMPLE_NEW_LOCATION_STOCK = 30

internal val sampleDashboardHeadlineTiles = listOf(
    SampleDashboardTile(R.string.inventory_tile_urgent_audit, 14, Tone.Warn, opensSkos = true),
    SampleDashboardTile(R.string.inventory_tile_to_replenish, 22, Tone.Primary, opensSkos = true, labelArgument = SAMPLE_DROPSHIPPING_TO_REPLENISH),
)

internal val sampleDashboardTiles = listOf(
    SampleDashboardTile(R.string.inventory_tile_replenishments, 9, null, opensSkos = false),
    SampleDashboardTile(R.string.inventory_tile_low_stock_audits, 14, null, opensSkos = false),
    SampleDashboardTile(R.string.inventory_tile_below_minimum, 31, null, opensSkos = false),
    SampleDashboardTile(R.string.inventory_tile_negative_stocks, 2, Tone.Danger, opensSkos = true),
    SampleDashboardTile(R.string.inventory_tile_wrong_weights, 5, Tone.Warn, opensSkos = false),
    SampleDashboardTile(R.string.inventory_tile_without_product, 3, Tone.Warn, opensSkos = false),
)

internal val sampleTodayMovements = listOf(
    SampleMovement("SKO-00412", MovementType.Purchase, "+120", Tone.Success, "into A-03-02 · Sam P. · 10 min ago"),
    SampleMovement("SKO-00733", MovementType.Picked, "−6", Tone.Neutral, "for delivery note AR24790 · you · 24 min ago"),
    SampleMovement("SKO-00953", MovementType.Audit, "−2", Tone.Warn, "Damaged stock · B-01-04 · Jo K. · 2 h ago"),
)

internal val sampleMovements = listOf(
    SampleMovement("SKO-00412", MovementType.Purchase, "+120", Tone.Success, "A-03-02 · Sam P. · 10:22 · running 220"),
    SampleMovement("SKO-00733", MovementType.Picked, "−6", Tone.Neutral, "A-03-02 · you · 10:08 · delivery note AR24790 · running 36"),
    SampleMovement("SKO-00953", MovementType.Audit, "−2", Tone.Warn, "B-01-04 · Damaged stock · Jo K. · 08:40 · running 0"),
    SampleMovement("SKO-01187", MovementType.LocationTransfer, "24", Tone.Primary, "C-04-02 → A-07-01 · Wrong bin location · Sam P."),
    SampleMovement("SKO-01402", MovementType.Audit, "+4", Tone.Success, "D-01-01 · Count gain (stock found) · Jo K."),
    SampleMovement("SKO-00740", MovementType.Consumption, "−1", Tone.Neutral, "A-05-03 · Internal consumption · showroom"),
)

internal val sampleSkoStateCounts = listOf(
    SampleCount(R.string.inventory_chip_current, SAMPLE_CURRENT_SKOS),
    SampleCount(R.string.inventory_chip_discontinued, null),
    SampleCount(R.string.inventory_chip_abnormalities, SAMPLE_ABNORMALITIES),
    SampleCount(R.string.inventory_all, null),
)

internal const val SKO_ABNORMALITIES_CHIP = 2

internal val sampleSkos = listOf(
    SampleSko("SKO-00733", "Bath bomb – rose 180g", SkoBadge.Low, stock = 36, locationCount = 3),
    SampleSko("SKO-00412", "Lavender essential oil 10ml", SkoBadge.Ideal, stock = 220, locationCount = 2),
    SampleSko("SKO-01187", "Soy wax candle – vanilla", SkoBadge.Critical, stock = 4, locationCount = 1),
    SampleSko("SKO-00953", "Incense sticks – sandalwood", SkoBadge.OutOfStock, stock = 0, locationCount = 1),
    SampleSko("SKO-01402", "Eucalyptus essential oil 10ml", SkoBadge.Excess, stock = 960, locationCount = 2),
)

internal val sampleAbnormalSkos = listOf(
    SampleSko("SKO-X-0192", "[Name]", SkoBadge.Abnormality, detail = "no stock record match"),
    SampleSko("SKO-X-0207", "[Name]", SkoBadge.Abnormality),
)

internal val sampleFamilyStateCounts = listOf(
    SampleCount(R.string.inventory_state_active, 214),
    SampleCount(R.string.inventory_state_in_process, 3),
    SampleCount(R.string.inventory_state_discontinuing, 6),
    SampleCount(R.string.inventory_state_discontinued, null),
)

internal val sampleFamilies = listOf(
    SampleFamily("BB", "Bath bombs", R.string.inventory_state_active, 42, "[£ 3.9k]", 6),
    SampleFamily("EO", "Essential oils", R.string.inventory_state_active, 118, "[£ 21k]", 9),
    SampleFamily("CA", "Candles", R.string.inventory_state_active, 64, "[£ 8.2k]", 4),
)

internal val sampleFamilySkos = listOf(
    SampleSko("SKO-00733", "Bath bomb – rose 180g", SkoBadge.Low, stock = 36),
    SampleSko("SKO-00734", "Bath bomb – lavender 180g", SkoBadge.Ideal, stock = 210),
    SampleSko("SKO-00740", "Bath bomb gift set ×6", SkoBadge.Excess, stock = 148),
)

internal val sampleSkoLocations = listOf(
    SampleSkoLocation("A-03-02", LocationKind.Picking, SalesChannel.Wholesale, minStock = 12, maxStock = 48, lastAudit = "28 Oct", stock = 30),
    SampleSkoLocation("B-02-01", LocationKind.Picking, SalesChannel.Dropshipping, minStock = 6, maxStock = 24, stock = 0),
    SampleSkoLocation("C-04-02", LocationKind.Storing, replenish = 24, lastAudit = "02 Nov", stock = 6),
)

internal val sampleRemovableLocations = sampleSkoLocations.drop(1)

internal val sampleSkoDetail = SampleSkoDetail(
    isOnDemand = true,
    unitsPerSko = 6,
    skoBarcode = "[CODE128]",
    unitEan = "[5060000000017]",
    pickerNote = "fragile, keep upright",
    inLocations = 36,
    reserved = 12,
    toBePicked = 6,
    coverDays = 9,
    locations = sampleSkoLocations,
)

internal val sampleAuditCounts = listOf(
    SampleAuditCount("A-03-02", was = 30, counted = 28),
    SampleAuditCount("C-04-02", was = 6, counted = 6),
)

internal val sampleFoundLocation = SampleFoundLocation("A-09-04", "Area A · empty")

internal val sampleFilterFamilies = listOf("Bath bombs", "Essential oils", "Incense", "Soap", "Candles", "Gemstones")

internal val sampleFilterAreas = listOf("A · Ground floor", "B · Mezzanine", "C · Bulk", "D · Dispatch")

internal fun sampleSkoByCode(code: String): SampleSko =
    (sampleSkos + sampleFamilySkos + sampleAbnormalSkos).firstOrNull { it.code == code } ?: sampleSkos.first().copy(code = code)

internal fun sampleFamilyByCode(code: String): SampleFamily =
    sampleFamilies.firstOrNull { it.code == code } ?: sampleFamilies.first()
