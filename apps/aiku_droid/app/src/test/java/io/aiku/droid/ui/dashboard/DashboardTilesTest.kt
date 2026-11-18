package io.aiku.droid.ui.dashboard

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
import org.junit.Assert.assertEquals
import org.junit.Test

class DashboardTilesTest {

    private val goodsOut = DashboardGoodsOut(unassigned = 47, handling = 17, handlingBlocked = 2, picked = 9, packed = 26, inWarehouse = 101)

    @Test
    fun `each goods out tile opens the delivery notes in the bucket it counts`() {
        assertEquals(
            listOf(
                DashboardTile(R.string.dn_state_to_do, 47, Tone.Neutral, DeliveryNotesRoute(bucket = "unassigned")),
                DashboardTile(R.string.dn_state_picking, 17, Tone.Primary, DeliveryNotesRoute(bucket = "handling")),
                DashboardTile(R.string.dn_state_waiting, 2, Tone.Warn, DeliveryNotesRoute(bucket = "handling_blocked")),
                DashboardTile(R.string.dashboard_to_pack, 9, Tone.Primary, DeliveryNotesRoute(bucket = "picked")),
                DashboardTile(R.string.dn_state_packed, 26, Tone.Primary, DeliveryNotesRoute(bucket = "packed")),
            ),
            goodsOut.tiles(),
        )
    }

    @Test
    fun `every goods out bucket is one the delivery note list understands`() {
        goodsOut.tiles().forEach { tile ->
            val bucket = (tile.route as DeliveryNotesRoute).bucket
            assertEquals(bucket, DeliveryNoteBucket.ofKey(bucket)?.key)
        }
    }

    @Test
    fun `to pack is hidden when an older server does not send picked`() {
        val tiles = goodsOut.copy(picked = null).tiles()

        assertEquals(listOf(R.string.dn_state_to_do, R.string.dn_state_picking, R.string.dn_state_waiting, R.string.dn_state_packed), tiles.map { it.label })
    }

    @Test
    fun `waiting is neutral when nothing waits`() {
        assertEquals(Tone.Neutral, goodsOut.copy(handlingBlocked = 0).tiles().single { it.label == R.string.dn_state_waiting }.tone)
    }

    @Test
    fun `each goods in tile opens the stock deliveries in the state it counts`() {
        val goodsIn = DashboardGoodsIn(arriving = 3, received = 2, checked = 1, bookingIn = 4, returnsToProcess = 5)

        assertEquals(
            listOf(
                DashboardTile(R.string.goodsin_state_arriving, 3, Tone.Neutral, StockDeliveriesRoute(state = "dispatched")),
                DashboardTile(R.string.sd_state_received, 2, Tone.Primary, StockDeliveriesRoute(state = "received")),
                DashboardTile(R.string.sd_state_checked, 1, Tone.Primary, StockDeliveriesRoute(state = "checked")),
                DashboardTile(R.string.sd_state_booking_in, 4, Tone.Primary, StockDeliveriesRoute(state = "booking_in")),
            ),
            goodsIn.tiles(),
        )
        goodsIn.tiles().forEach { tile ->
            val state = (tile.route as StockDeliveriesRoute).state
            assertEquals(state, StockDeliveryStateFilter.ofKey(state)?.key)
        }
    }

    @Test
    fun `returns to process opens the returns still to process`() {
        assertEquals(GoodsInReturnsRoute(state = "to_process"), returnsToProcessRoute)
        assertEquals(ReturnStateFilter.ToProcess, ReturnStateFilter.ofKey(returnsToProcessRoute.state))
    }

    @Test
    fun `each inventory tile opens the SKOs it counts`() {
        val inventory = DashboardInventory(numberCurrentOrgStocks = 4812, belowMinimumStock = 17, negativeStocks = 5, numberAbnormalities = 0)

        assertEquals(
            listOf(
                DashboardTile(R.string.dashboard_below_minimum, 17, Tone.Warn, SkusRoute(bucket = "below_minimum")),
                DashboardTile(R.string.dashboard_negative_stock, 5, Tone.Danger, SkusRoute(bucket = "negative")),
                DashboardTile(R.string.dashboard_abnormal, 0, Tone.Neutral, SkusRoute(bucket = "abnormality")),
            ),
            inventory.tiles(),
        )
        assertEquals(
            listOf(SkoBucket.BelowMinimum, SkoBucket.Negative, SkoBucket.Abnormality),
            inventory.tiles().map { SkoBucket.ofKey((it.route as SkusRoute).bucket) },
        )
    }

    @Test
    fun `five tiles take two rows of three, up to four stay on one row`() {
        assertEquals(3, dashboardTileColumns(5))
        assertEquals(4, dashboardTileColumns(4))
        assertEquals(3, dashboardTileColumns(3))
        assertEquals(1, dashboardTileColumns(0))
    }
}
