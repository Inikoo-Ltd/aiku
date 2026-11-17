package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedPallet
import io.aiku.droid.data.api.ScannedPalletDelivery
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveryRoute
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.scan.ScanDestination
import io.aiku.droid.ui.scan.destination
import io.aiku.droid.ui.scan.route
import org.junit.Assert.assertEquals
import org.junit.Test

class InboundScanTest {

    @Test
    fun `the global scan opens a pallet page or the delivery screen for its state`() {
        val pallet = ScanLookup(code = "acme-p0231", type = ScanMatch.PALLET, pallet = ScannedPallet(id = 231, reference = "acme-p0231"))
        val received = ScanLookup(code = "pad-acme-012", type = ScanMatch.PALLET_DELIVERY, palletDelivery = ScannedPalletDelivery(id = 4, reference = "acme-012", state = "received"))
        val bookedIn = ScanLookup(code = "acme-011", type = ScanMatch.PALLET_DELIVERY, palletDelivery = ScannedPalletDelivery(id = 3, reference = "acme-011", state = "booked_in"))

        assertEquals(PalletRoute(id = 231, reference = "acme-p0231"), pallet.destination("acme-p0231").route())
        assertEquals(FulfilmentDeliveryRoute(id = 4, reference = "acme-012"), received.destination("pad-acme-012").route())
        assertEquals(FulfilmentBookedInRoute(id = 3, reference = "acme-011"), bookedIn.destination("acme-011").route())
        assertEquals(ScanDestination.Unknown("acme-p9"), ScanLookup(code = "acme-p9", type = ScanMatch.PALLET).destination("acme-p9"))
    }
}
