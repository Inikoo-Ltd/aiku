package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.OrgStockRow
import io.aiku.droid.data.api.PlaceRef
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.SkuRoute
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test
import java.util.Locale

class SkoListRowTest {

    private lateinit var deviceLocale: Locale

    @Before
    fun useUkLocale() {
        deviceLocale = Locale.getDefault()
        Locale.setDefault(Locale.UK)
    }

    @After
    fun restoreLocale() {
        Locale.setDefault(deviceLocale)
    }

    private val sko = OrgStockRow(id = 77, code = "AWO-12", name = "Lavender", quantityAvailable = 10.0, numberLocations = 2)

    private fun inLocation(quantity: Double?) = sko.copy(locationOrgStockId = 5501, location = PlaceRef(id = 220, code = "A-03-02"), locationQuantity = quantity)

    @Test
    fun `a row with a location shows the location code and its quantity, negative marked as such`() {
        assertEquals(SkoLocationLine(code = "A-03-02", quantity = "4", isNegative = true), inLocation(-4.0).locationLine())
        assertEquals(SkoLocationLine(code = "A-03-02", quantity = "2.5", isNegative = true), inLocation(-2.5).locationLine())
        assertEquals(SkoLocationLine(code = "A-03-02", quantity = "3", isNegative = false), inLocation(3.0).locationLine())
        assertEquals(SkoLocationLine(code = "A-03-02", quantity = "0", isNegative = false), inLocation(0.0).locationLine())
    }

    @Test
    fun `a location without a quantity shows only its code`() {
        assertEquals(SkoLocationLine(code = "A-03-02", quantity = null, isNegative = false), inLocation(null).locationLine())
    }

    @Test
    fun `a row without a location has no location line`() {
        assertNull(sko.locationLine())
        assertNull(sko.copy(locationOrgStockId = 5501).locationLine())
    }

    @Test
    fun `a row with a location opens the SKO in that location`() {
        assertEquals(
            LocationSkuRoute(locationId = 220, locationOrgStockId = 5501, locationCode = "A-03-02", skoCode = "AWO-12"),
            inLocation(-4.0).listRoute(),
        )
    }

    @Test
    fun `a row without a location opens the SKO page`() {
        assertEquals(SkuRoute(id = 77, code = "AWO-12"), sko.listRoute())
        assertEquals(SkuRoute(id = 77, code = "AWO-12"), sko.copy(locationOrgStockId = 5501).listRoute())
        assertEquals(SkuRoute(id = 77, code = "AWO-12"), sko.copy(location = PlaceRef(id = 220, code = "A-03-02")).listRoute())
    }

    @Test
    fun `rows are keyed by SKO and location link, or by SKO alone without a location`() {
        assertEquals(77L to 5501L, inLocation(-4.0).listKey())
        assertEquals(77L, sko.listKey())
    }
}
