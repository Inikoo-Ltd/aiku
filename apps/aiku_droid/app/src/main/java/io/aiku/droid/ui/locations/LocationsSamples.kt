package io.aiku.droid.ui.locations

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.inventory.StockMovement

internal enum class LocationsUsage(@DrawableRes val icon: Int, @StringRes val description: Int) {
    Stocks(R.drawable.ic_locations_stocks, R.string.locations_allows_stocks),
    Fulfilment(R.drawable.ic_locations_pallet, R.string.locations_allows_fulfilment),
}

internal enum class LocationsFlag(@StringRes val label: Int, val tone: Tone) {
    AuditInProcess(R.string.locations_flag_audit, Tone.Lime),
    Broken(R.string.locations_flag_broken, Tone.Danger),
}

internal data class LocationsListSample(
    val code: String,
    val summary: String,
    val stockValue: String,
    val usage: LocationsUsage?,
    val flag: LocationsFlag?,
)

internal val LocationsAreaSamples = listOf("Area A", "Area B")

internal val LocationsListSamples = listOf(
    LocationsListSample("A-03-02", "Area A · 6 SKUs · 120 kg · 0.8 CBM", "£2,140", LocationsUsage.Stocks, null),
    LocationsListSample("A-07-01", "Area A · 3 SKUs · 64 kg · 0.4 CBM", "£980", LocationsUsage.Stocks, null),
    LocationsListSample("A-12-03", "Area A · 4 SKUs · 38 kg · 0.3 CBM", "£610", LocationsUsage.Stocks, LocationsFlag.AuditInProcess),
    LocationsListSample("B-01-04", "Area B · 2 SKUs · 210 kg · 1.6 CBM", "£1,325", LocationsUsage.Stocks, null),
    LocationsListSample("B-02-01", "Area B · 2 pallets · 480 kg · 2.4 CBM", "—", LocationsUsage.Fulfilment, null),
    LocationsListSample("C-04-02", "Area C · empty · not available for put-away", "£0", null, LocationsFlag.Broken),
)

internal data class LocationsSkuSample(val code: String, val name: String, val quantity: String)

internal val LocationsSkuSamples = listOf(
    LocationsSkuSample("SKU-00733", "Bath bomb – rose 180g", "30"),
    LocationsSkuSample("SKU-00412", "Lavender essential oil 10ml", "120"),
    LocationsSkuSample("SKU-01187", "Soy wax candle – vanilla", "48"),
)

internal val LocationsMovementSamples = listOf(
    StockMovement("Picked · SKU-00733", "DN-24790 · today 09:12", "−6", Tone.Danger),
    StockMovement("Purchase · SKU-00412", "SD-1090 · today 08:55", "+120", Tone.Success),
)
