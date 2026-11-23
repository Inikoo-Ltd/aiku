package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.StockMovement
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class StockFractionTest {

    @Test
    fun `labels match the fractions the website draws for the same quantity and pack`() {
        val websiteLabels = listOf(
            "921.673913||921 31/46",
            "921.673913|46|921 31/46",
            "28.625|16|28 10/16",
            "0.75|4|3/4",
            "12|1|12",
            "0|6|0",
            "-3.5|2|-3 1/2",
            "32.673667|6|32 64/95",
            "3||3",
            "2.0001|1|2",
            "0.3333333|3|1/3",
            "0.3333333||1/3",
            "0.005||1/200",
            "0.0005||0",
            "0.01|1|1/100",
            "66.7||66 7/10",
            "66.7|10|66 7/10",
            "28.62||28 31/50",
            "0.37812||31/82",
            "1.37812|8|1 31/82",
            "48.125|16|48 2/16",
            "315.6666667|6|315 4/6",
            "0.2|5|1/5",
            "7.5|12|7 6/12",
            "0.001|1|1/1000",
            "0.002|1|1/500",
            "0.00005|1|1/10000",
            "1234.5|2|1234 1/2",
            "-0.25|4|-1/4",
            "99.99||99 99/100",
        )

        websiteLabels.forEach { case ->
            val (quantity, packedIn, expected) = case.split("|")
            assertEquals(case, expected, stockFractionLabel(quantity.toDouble(), packedIn.toDoubleOrNull()))
        }
    }

    @Test
    fun `the parts keep the pack as the denominator when the quantity divides into it`() {
        assertEquals(StockFractionParts(28.0, 10.0, 16.0), stockFraction(28.625, 16.0))
        assertEquals(StockFractionParts(921.0, 31.0, 46.0), stockFraction(921.673913, 6.0))
    }

    @Test
    fun `a movement shows the running stock in the web's fraction when the server sends one`() {
        val movement = StockMovement(id = 1, runningQuantityOrgStock = 2.915667, runningQuantityOrgStockLabel = "2 76/83")

        assertEquals("2 76/83", movement.runningLabel)
        assertNull(StockMovement(id = 2, runningQuantity = 4.0, runningQuantityOrgStockLabel = "9").runningLabel)
    }
}
