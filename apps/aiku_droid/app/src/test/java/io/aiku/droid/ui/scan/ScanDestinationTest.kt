package io.aiku.droid.ui.scan

import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedLocation
import io.aiku.droid.data.api.ScannedSko
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.DeliveryNoteToDoRoute
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.ScanResultRoute
import io.aiku.droid.ui.navigation.SkuRoute
import org.junit.Assert.assertEquals
import org.junit.Test

class ScanDestinationTest {

    @Test
    fun `each kind of code opens its own screen`() {
        val sko = ScanLookup(code = "5060", type = ScanMatch.SKO, sko = ScannedSko(id = 812, code = "AWO-12"))
        val location = ScanLookup(code = "a-01-03", type = ScanMatch.LOCATION, location = ScannedLocation(id = 220, code = "A-01-03"))
        val note = ScanLookup(code = "AR24811", type = ScanMatch.DELIVERY_NOTE, deliveryNote = DeliveryNoteRow(id = 9912, reference = "AR24811", state = "handling_blocked"))
        val unknown = ScanLookup(code = "", type = ScanMatch.UNKNOWN)

        assertEquals(SkuRoute(code = "AWO-12"), sko.destination("5060").route())
        assertEquals(LocationRoute(code = "A-01-03"), location.destination("a-01-03").route())
        assertEquals(DeliveryNotePickingRoute(id = 9912, reference = "AR24811"), note.destination("AR24811").route())
        assertEquals(ScanResultRoute(code = "999"), unknown.destination("999").route())
        assertEquals(ScanDestination.Unknown("5060"), ScanLookup(code = "5060", type = ScanMatch.SKO).destination("5060"))
    }

    @Test
    fun `a delivery note opens the screen for its state`() {
        fun routeFor(state: String): Any = ScanDestination.DeliveryNote(DeliveryNoteRow(id = 1, reference = "AR1", state = state)).route()

        assertEquals(DeliveryNoteToDoRoute(id = 1, reference = "AR1"), routeFor("unassigned"))
        assertEquals(DeliveryNoteToDoRoute(id = 1, reference = "AR1"), routeFor("queued"))
        assertEquals(DeliveryNotePickingRoute(id = 1, reference = "AR1"), routeFor("handling"))
        assertEquals(DeliveryNotePackingRoute(id = 1, reference = "AR1"), routeFor("packed"))
        assertEquals(DeliveryNoteDispatchedRoute(id = 1, reference = "AR1"), routeFor("dispatched"))
        assertEquals(DeliveryNoteCancelledRoute(id = 1, reference = "AR1"), routeFor("cancelled"))
    }
}
