package io.aiku.droid.ui.scan

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class HardwareScanDetectorTest {

    private val detector = HardwareScanDetector(maxKeyGapMillis = 40, minCodeLength = 3)

    private fun type(text: String, startAt: Long, gapMillis: Long): Long {
        var at = startAt
        text.forEach { character ->
            assertTrue(detector.onKey(ScanKey.Typed(character), at).isConsumed)
            at += gapMillis
        }
        return at - gapMillis
    }

    @Test
    fun `a fast burst ending in Enter is a scan`() {
        val lastKeyAt = type("AR24811", startAt = 1_000, gapMillis = 8)

        val result = detector.onKey(ScanKey.Enter, lastKeyAt + 10)

        assertEquals(ScanKeyResult(isConsumed = true, code = "AR24811"), result)
    }

    @Test
    fun `slow typing followed by Enter is not a scan and Enter reaches the screen`() {
        val lastKeyAt = type("A-01", startAt = 1_000, gapMillis = 180)

        val result = detector.onKey(ScanKey.Enter, lastKeyAt + 150)

        assertEquals(ScanKeyResult.Passed, result)
    }

    @Test
    fun `a pause restarts the burst so only the fast part counts`() {
        val typedAt = type("ab", startAt = 1_000, gapMillis = 200)
        val lastKeyAt = type("5060123456789", startAt = typedAt + 500, gapMillis = 5)

        val result = detector.onKey(ScanKey.Enter, lastKeyAt + 5)

        assertEquals("5060123456789", result.code)
    }

    @Test
    fun `a burst shorter than the minimum is not a scan`() {
        val lastKeyAt = type("A1", startAt = 1_000, gapMillis = 5)

        assertEquals(ScanKeyResult.Passed, detector.onKey(ScanKey.Enter, lastKeyAt + 5))
    }

    @Test
    fun `Enter that comes late after the burst is not a scan`() {
        val lastKeyAt = type("A-03-02", startAt = 1_000, gapMillis = 5)

        assertEquals(ScanKeyResult.Passed, detector.onKey(ScanKey.Enter, lastKeyAt + 400))
    }

    @Test
    fun `Enter on its own passes through and the next scan still works`() {
        assertEquals(ScanKeyResult.Passed, detector.onKey(ScanKey.Enter, 1_000))

        val lastKeyAt = type("SKO-1", startAt = 2_000, gapMillis = 10)

        assertEquals("SKO-1", detector.onKey(ScanKey.Enter, lastKeyAt + 10).code)
    }

    @Test
    fun `shift between characters does not break a burst`() {
        assertTrue(detector.onKey(ScanKey.Typed('a'), 1_000).isConsumed)
        assertFalse(detector.onKey(ScanKey.Modifier, 1_004).isConsumed)
        assertTrue(detector.onKey(ScanKey.Typed('W'), 1_008).isConsumed)
        assertTrue(detector.onKey(ScanKey.Typed('O'), 1_016).isConsumed)

        assertEquals("aWO", detector.onKey(ScanKey.Enter, 1_020).code)
    }

    @Test
    fun `another key in the middle drops the burst`() {
        val lastKeyAt = type("AR2", startAt = 1_000, gapMillis = 5)
        assertFalse(detector.onKey(ScanKey.Other, lastKeyAt + 5).isConsumed)
        val nextKeyAt = type("4", startAt = lastKeyAt + 10, gapMillis = 5)

        assertNull(detector.onKey(ScanKey.Enter, nextKeyAt + 5).code)
    }

    @Test
    fun `a space only counts inside a burst`() {
        assertEquals(ScanKeyResult.Passed, detector.onKey(ScanKey.Typed(' '), 1_000))

        val lastKeyAt = type("PAL 0042", startAt = 2_000, gapMillis = 6)

        assertEquals("PAL 0042", detector.onKey(ScanKey.Enter, lastKeyAt + 6).code)
    }

    @Test
    fun `a scan code is trimmed`() {
        val lastKeyAt = type("AR24811  ", startAt = 1_000, gapMillis = 6)

        assertEquals("AR24811", detector.onKey(ScanKey.Enter, lastKeyAt + 6).code)
    }

    @Test
    fun `the same camera code is ignored while it stays in view`() {
        val filter = RepeatedScanFilter(quietMillis = 2_000)

        assertTrue(filter.accepts("AR24811", 1_000))
        assertFalse(filter.accepts("AR24811", 1_500))
        assertFalse(filter.accepts("AR24811", 3_000))
        assertTrue(filter.accepts("A-01-03", 3_100))
        assertTrue(filter.accepts("AR24811", 3_200))
        assertTrue(filter.accepts("AR24811", 6_000))
    }
}
