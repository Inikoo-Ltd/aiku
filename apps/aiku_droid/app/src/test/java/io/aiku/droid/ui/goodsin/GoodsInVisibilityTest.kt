package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.GoodsInHubKey
import io.aiku.droid.data.api.GoodsInHubRow
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.ui.components.WarehouseModule
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.goodsInEntry
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.GoodsInReturnsRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class GoodsInVisibilityTest {

    private val goodsInClerk = WarehouseAbilities(viewGoodsIn = true, bookIn = true)
    private val returnsClerk = WarehouseAbilities(viewReturns = true, handleReturns = true)
    private val supervisor = WarehouseAbilities(viewGoodsIn = true, viewReturns = true, viewFulfilment = true)

    @Test
    fun `goods in shows for stock delivery or return rights`() {
        assertTrue(WarehouseModule.GoodsIn.allowsCoreSections(goodsInClerk))
        assertTrue(WarehouseModule.GoodsIn.allowsCoreSections(returnsClerk))
        assertFalse(WarehouseModule.GoodsIn.allowsCoreSections(WarehouseAbilities(bookIn = true)))
    }

    @Test
    fun `backlog and stock deliveries need goods in view and returns need return view`() {
        assertEquals(
            listOf(WarehouseSection.GoodsInBacklog, WarehouseSection.StockDeliveries),
            WarehouseModule.GoodsIn.visibleSections(goodsInClerk),
        )
        assertEquals(listOf(WarehouseSection.GoodsInReturns), WarehouseModule.GoodsIn.visibleSections(returnsClerk))
        assertEquals(
            listOf(WarehouseSection.GoodsInBacklog, WarehouseSection.StockDeliveries, WarehouseSection.FulfilmentDeliveries, WarehouseSection.GoodsInReturns),
            WarehouseModule.GoodsIn.visibleSections(supervisor),
        )
        assertEquals(listOf(WarehouseSection.FulfilmentDeliveries), WarehouseModule.GoodsIn.visibleSections(WarehouseAbilities(viewFulfilment = true)))
    }

    @Test
    fun `the goods in shortcut opens what the user can see`() {
        assertEquals(StockDeliveriesRoute(), goodsInEntry(goodsInClerk))
        assertEquals(GoodsInReturnsRoute(), goodsInEntry(returnsClerk))
        assertNull(goodsInEntry(WarehouseAbilities(viewGoodsOut = true)))
    }

    @Test
    fun `hub rows show fulfilment only with fulfilment view and returns only with return view`() {
        val fulfilment = GoodsInHubRow(key = GoodsInHubKey.FULFILMENT)
        val returns = GoodsInHubRow(key = GoodsInHubKey.RETURNS)
        val suppliers = GoodsInHubRow(key = GoodsInHubKey.STOCK_DELIVERIES)

        assertFalse(fulfilment.isVisible(goodsInClerk))
        assertTrue(fulfilment.isVisible(supervisor))
        assertFalse(returns.isVisible(goodsInClerk))
        assertTrue(returns.isVisible(supervisor))
        assertTrue(suppliers.isVisible(goodsInClerk))
    }

    @Test
    fun `a hub card opens its list and a figure opens its state`() {
        val agents = GoodsInHubRow(key = GoodsInHubKey.AGENTS, arriving = 1, received = 0, checked = 0, bookingIn = 0, bookedInWeek = 2)
        val returns = GoodsInHubRow(key = GoodsInHubKey.RETURNS, received = 3, bookingIn = 1)

        assertEquals(StockDeliveriesRoute(source = "agents"), agents.route())
        assertEquals(StockDeliveriesRoute(source = "agents", state = "dispatched"), agents.route(HubFigure.Arriving))
        assertEquals(StockDeliveriesRoute(source = "agents", state = "booked_in"), agents.route(HubFigure.BookedInWeek))
        assertEquals(StockDeliveriesRoute(source = "suppliers", state = "booking_in"), GoodsInHubRow(key = GoodsInHubKey.STOCK_DELIVERIES).route(HubFigure.BookingIn))
        assertEquals(GoodsInReturnsRoute(state = "returning"), returns.route(HubFigure.BookingIn))
        assertEquals(GoodsInReturnsRoute(state = "received"), returns.route(HubFigure.Received))
        assertEquals(FulfilmentDeliveriesRoute, GoodsInHubRow(key = GoodsInHubKey.FULFILMENT).route(HubFigure.Arriving))
        assertEquals(listOf(HubFigure.Received, HubFigure.BookingIn), returns.counts().map { it.figure })
        assertEquals(5, agents.counts().size)
    }
}
