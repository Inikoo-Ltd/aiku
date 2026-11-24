package io.aiku.droid.ui.components

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test

class LinearBarcodeTest {

    @Test
    fun `13 digit codes are drawn as EAN-13 like the website`() {
        assertEquals(LinearBarcodeFormat.Ean13, linearBarcodeFormatOf("5055796532315"))
        assertEquals(95, linearBarcodeOf("5055796532315")!!.width)
    }

    @Test
    fun `other codes are drawn as Code 128`() {
        assertEquals(LinearBarcodeFormat.Code128, linearBarcodeFormatOf("5055796579259N"))
        assertNotNull(linearBarcodeOf("5055796579259N"))
        assertEquals(LinearBarcodeFormat.Code128, linearBarcodeFormatOf("12345"))
    }

    @Test
    fun `an EAN-13 with a wrong check digit or a blank code is not drawn`() {
        assertNull(linearBarcodeOf("5055796532316"))
        assertNull(linearBarcodeOf("  "))
    }
}
