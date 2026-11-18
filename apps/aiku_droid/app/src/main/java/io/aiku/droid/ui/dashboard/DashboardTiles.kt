package io.aiku.droid.ui.dashboard

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.data.api.DashboardGoodsIn
import io.aiku.droid.data.api.DashboardGoodsOut
import io.aiku.droid.data.api.DashboardInventory
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.api.SkoBucket
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.GoodsInReturnsRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute

private const val MAX_TILES_PER_ROW = 4

data class DashboardTile(@StringRes val label: Int, val value: Int, val tone: Tone, val route: Any)

fun DashboardGoodsOut.tiles(): List<DashboardTile> = listOfNotNull(
    DashboardTile(R.string.dn_state_to_do, unassigned, Tone.Neutral, deliveryNotesIn(DeliveryNoteBucket.Unassigned)),
    DashboardTile(R.string.dn_state_picking, handling, Tone.Primary, deliveryNotesIn(DeliveryNoteBucket.Handling)),
    DashboardTile(R.string.dn_state_waiting, handlingBlocked, toneWhenAny(handlingBlocked, Tone.Warn), deliveryNotesIn(DeliveryNoteBucket.HandlingBlocked)),
    picked?.let { count -> DashboardTile(R.string.dashboard_to_pack, count, Tone.Primary, deliveryNotesIn(DeliveryNoteBucket.Picked)) },
    DashboardTile(R.string.dn_state_packed, packed, Tone.Primary, deliveryNotesIn(DeliveryNoteBucket.Packed)),
)

fun DashboardGoodsIn.tiles(): List<DashboardTile> = listOf(
    DashboardTile(R.string.goodsin_state_arriving, arriving, Tone.Neutral, stockDeliveriesIn(StockDeliveryStateFilter.Dispatched)),
    DashboardTile(R.string.sd_state_received, received, Tone.Primary, stockDeliveriesIn(StockDeliveryStateFilter.Received)),
    DashboardTile(R.string.sd_state_checked, checked, Tone.Primary, stockDeliveriesIn(StockDeliveryStateFilter.Checked)),
    DashboardTile(R.string.sd_state_booking_in, bookingIn, Tone.Primary, stockDeliveriesIn(StockDeliveryStateFilter.BookingIn)),
)

val returnsToProcessRoute = GoodsInReturnsRoute(state = ReturnStateFilter.ToProcess.key)

fun DashboardInventory.tiles(): List<DashboardTile> = listOf(
    DashboardTile(R.string.dashboard_below_minimum, belowMinimumStock, toneWhenAny(belowMinimumStock, Tone.Warn), SkusRoute(bucket = SkoBucket.BelowMinimum.key)),
    DashboardTile(R.string.dashboard_negative_stock, negativeStocks, toneWhenAny(negativeStocks, Tone.Danger), SkusRoute(bucket = SkoBucket.Negative.key)),
    DashboardTile(R.string.dashboard_abnormal, numberAbnormalities, toneWhenAny(numberAbnormalities, Tone.Danger), SkusRoute(bucket = SkoBucket.Abnormality.key)),
)

fun dashboardTileColumns(tileCount: Int): Int = when {
    tileCount <= 0 -> 1
    tileCount <= MAX_TILES_PER_ROW -> tileCount
    else -> (tileCount + 1) / 2
}

private fun deliveryNotesIn(bucket: DeliveryNoteBucket) = DeliveryNotesRoute(bucket = bucket.key)

private fun stockDeliveriesIn(state: StockDeliveryStateFilter) = StockDeliveriesRoute(state = state.key)

private fun toneWhenAny(count: Int, tone: Tone): Tone = if (count > 0) tone else Tone.Neutral
