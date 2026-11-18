package io.aiku.droid.ui.search

import io.aiku.droid.R
import io.aiku.droid.data.api.SearchRow
import io.aiku.droid.data.api.SearchType
import io.aiku.droid.ui.components.LabelCase
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test
import java.util.Locale

class SearchFormatTest {

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

    private fun sko(quantity: Double?) = SearchRow(type = SearchType.Skos, id = 412, code = "SKO-00412", title = "SKO-00412", quantity = quantity)

    @Test
    fun `an SKO's quantity is labelled as stock in locations`() {
        assertEquals(SearchQuantity(R.plurals.search_sko_in_locations, 1240, "1240"), sko(1240.0).quantityLabel())
        assertEquals(SearchQuantity(R.plurals.search_sko_in_locations, 1, "1"), sko(1.0).quantityLabel())
        assertEquals(SearchQuantity(R.plurals.search_sko_in_locations, 0, "0"), sko(0.0).quantityLabel())
    }

    @Test
    fun `a part quantity keeps its decimals and takes the plural form`() {
        assertEquals(SearchQuantity(R.plurals.search_sko_in_locations, 2, "1.5"), sko(1.5).quantityLabel())
        assertEquals(SearchQuantity(R.plurals.search_sko_in_locations, 2, "-2.25"), sko(-2.25).quantityLabel())
    }

    @Test
    fun `rows without a quantity, or that are not SKOs, show no quantity`() {
        assertNull(sko(null).quantityLabel())
        assertNull(SearchRow(type = SearchType.Locations, id = 220, code = "A-03-02", quantity = 12.0).quantityLabel())
    }

    @Test
    fun `search headers keep the label as written`() {
        assertEquals(LabelCase.AsWritten, SearchLabelCase)
        assertEquals("SKOs · 5+", SearchLabelCase.applyTo("SKOs · 5+"))
        assertEquals("Delivery notes · 2", SearchLabelCase.applyTo("Delivery notes · 2"))
        assertEquals("SKOS · 5+", LabelCase.Upper.applyTo("SKOs · 5+"))
    }
}
