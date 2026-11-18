package io.aiku.droid.ui.components

import org.junit.Assert.assertEquals
import org.junit.Test

class ScrollFadeTest {

    @Test
    fun `an edge fades only while chips are hidden past it`() {
        assertEquals(0f, fadeStrength(hiddenPixels = 0, fadePixels = 56f), 0f)
        assertEquals(0.5f, fadeStrength(hiddenPixels = 28, fadePixels = 56f), 0f)
        assertEquals(1f, fadeStrength(hiddenPixels = 400, fadePixels = 56f), 0f)
    }

    @Test
    fun `a row too narrow for a fade draws none`() {
        assertEquals(0f, fadeStrength(hiddenPixels = 400, fadePixels = 0f), 0f)
    }
}
