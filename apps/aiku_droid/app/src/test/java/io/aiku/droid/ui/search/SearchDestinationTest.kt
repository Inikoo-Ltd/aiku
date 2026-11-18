package io.aiku.droid.ui.search

import io.aiku.droid.data.api.SearchRow
import io.aiku.droid.data.api.SearchType
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.DeliveryNoteToDoRoute
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveryRoute
import io.aiku.droid.ui.navigation.GoodsInReturnRoute
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute
import io.aiku.droid.ui.navigation.SkuFamilyRoute
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.navigation.StockDeliveryRoute
import io.aiku.droid.ui.scan.route
import org.junit.Assert.assertEquals
import org.junit.Test

class SearchDestinationTest {

    private fun routeOf(type: SearchType, id: Long, code: String, state: String = "", typeGroup: String? = null): Any =
        SearchRow(type = type, id = id, code = code, title = code, state = state, typeGroup = typeGroup).destination().route()

    @Test
    fun `each record type opens its own screen by id`() {
        assertEquals(SkuRoute(id = 412, code = "SKO-00412"), routeOf(SearchType.Skos, 412, "SKO-00412"))
        assertEquals(SkuFamilyRoute(id = 7, code = "LV"), routeOf(SearchType.SkoFamilies, 7, "LV"))
        assertEquals(LocationRoute(id = 220, code = "A-03-02"), routeOf(SearchType.Locations, 220, "A-03-02"))
        assertEquals(StockDeliveryRoute(id = 41, reference = "SD-41"), routeOf(SearchType.StockDeliveries, 41, "SD-41"))
        assertEquals(GoodsInReturnRoute(id = 31, reference = "RET-31"), routeOf(SearchType.Returns, 31, "RET-31"))
        assertEquals(PalletRoute(id = 231, reference = "acme-p0231"), routeOf(SearchType.Pallets, 231, "acme-p0231"))
    }

    @Test
    fun `a delivery note opens the screen for its state`() {
        assertEquals(DeliveryNoteToDoRoute(id = 991, reference = "AR1"), routeOf(SearchType.DeliveryNotes, 991, "AR1", state = "unassigned"))
        assertEquals(DeliveryNotePickingRoute(id = 991, reference = "AR1"), routeOf(SearchType.DeliveryNotes, 991, "AR1", state = "handling"))
        assertEquals(DeliveryNotePackingRoute(id = 991, reference = "AR1"), routeOf(SearchType.DeliveryNotes, 991, "AR1", state = "packed"))
        assertEquals(DeliveryNoteDispatchedRoute(id = 991, reference = "AR1"), routeOf(SearchType.DeliveryNotes, 991, "AR1", state = "dispatched"))
    }

    @Test
    fun `a pallet delivery opens the screen for its state`() {
        assertEquals(FulfilmentDeliveryRoute(id = 4, reference = "acme-012"), routeOf(SearchType.PalletDeliveries, 4, "acme-012", state = "received"))
        assertEquals(FulfilmentBookInRoute(id = 4, reference = "acme-012"), routeOf(SearchType.PalletDeliveries, 4, "acme-012", state = "booking_in"))
        assertEquals(FulfilmentBookedInRoute(id = 4, reference = "acme-012"), routeOf(SearchType.PalletDeliveries, 4, "acme-012", state = "booked_in"))
    }

    @Test
    fun `a pallet return opens the screen for its state and type group`() {
        assertEquals(ReturnPickPalletsRoute(id = 8, reference = "acme-r1"), routeOf(SearchType.PalletReturns, 8, "acme-r1", state = "picking", typeGroup = "pallets"))
        assertEquals(ReturnPickItemsRoute(id = 8, reference = "acme-r1"), routeOf(SearchType.PalletReturns, 8, "acme-r1", state = "confirmed", typeGroup = "items"))
        assertEquals(ReturnPickPalletsRoute(id = 8, reference = "acme-r1"), routeOf(SearchType.PalletReturns, 8, "acme-r1", state = "confirmed", typeGroup = null))
        assertEquals(ReturnPickedRoute(id = 8, reference = "acme-r1"), routeOf(SearchType.PalletReturns, 8, "acme-r1", state = "picked", typeGroup = "items"))
        assertEquals(ReturnDispatchedRoute(id = 8, reference = "acme-r1"), routeOf(SearchType.PalletReturns, 8, "acme-r1", state = "dispatched", typeGroup = "pallets"))
    }

    @Test
    fun `a row without a code opens with its title`() {
        val row = SearchRow(type = SearchType.Locations, id = 5, code = "", title = "B-01")

        assertEquals(LocationRoute(id = 5, code = "B-01"), row.destination().route())
    }
}
