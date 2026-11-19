package io.aiku.droid.ui.components

import org.junit.Assert.assertEquals
import org.junit.Test
import java.time.Instant
import java.time.ZoneId
import java.util.Locale

class LastUpdatedTest {

    private val zone = ZoneId.of("Europe/London")
    private val now = Instant.parse("2026-11-19T16:30:00Z")

    @Test
    fun `a copy from today shows only its time`() {
        assertEquals("09:05", lastUpdatedTime(Instant.parse("2026-11-19T09:05:00Z"), now, zone, Locale.UK))
    }

    @Test
    fun `an older copy also shows its day`() {
        assertEquals("17 Nov 14:20", lastUpdatedTime(Instant.parse("2026-11-17T14:20:00Z"), now, zone, Locale.UK))
    }
}
