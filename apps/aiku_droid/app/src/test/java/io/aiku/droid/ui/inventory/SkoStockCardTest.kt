package io.aiku.droid.ui.inventory

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class SkoStockCardTest {

    @Test
    fun `units are the SKO quantity times the units per SKO`() {
        assertEquals(35.0, skoUnits(2.915667, 12.0)!!, 0.0)
        assertEquals(18.0, skoUnits(3.0, 6.0)!!, 0.0)
        assertEquals(2.0, skoUnits(0.5, 4.0)!!, 0.0)
    }

    @Test
    fun `single unit SKOs and negative stock have no units line`() {
        assertNull(skoUnits(5.0, 1.0))
        assertNull(skoUnits(5.0, null))
        assertNull(skoUnits(-1.5, 12.0))
    }

    @Test
    fun `fractions are drawn like the website with raised and lowered digits`() {
        assertEquals("2 ⁷⁶⁄₈₃", fractionGlyphs("2 76/83"))
        assertEquals("¹⁄₁₂", fractionGlyphs("1/12"))
        assertEquals("−¹⁄₁₂", fractionGlyphs("−1/12"))
        assertEquals("running 255 ³⁄₄", fractionGlyphs("running 255 3/4"))
        assertEquals("921", fractionGlyphs("921"))
        assertEquals("0 / 2", fractionGlyphs("0 / 2"))
        assertEquals("12/11/2026", fractionGlyphs("12/11/2026"))
        assertEquals("31.5/46", fractionGlyphs("31.5/46"))
    }

    @Test
    fun `a changed count is shown as a web fraction, not a decimal`() {
        val line = AuditLine(locationOrgStockId = 1, title = "W9C2", current = 32.75, currentLabel = "32 3/4", packedIn = 4.0)

        assertEquals("33\u202F³⁄₄", countedText(line, 33.75))
        assertEquals("34", countedText(line, 34.0))
    }
}
