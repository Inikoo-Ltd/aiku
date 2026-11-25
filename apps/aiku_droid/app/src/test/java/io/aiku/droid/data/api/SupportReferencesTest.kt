package io.aiku.droid.data.api

import java.time.Instant
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class SupportReferencesTest {

    @After
    fun tearDown() {
        SupportReferences.clear()
    }

    @Test
    fun `the reference comes from the server error body, and nothing else counts as one`() {
        assertEquals("0a1b2c3d4e5f", supportReferenceOf("""{"message":"Server Error","reference":"0a1b2c3d4e5f"}"""))
        assertNull(supportReferenceOf("""{"message":"Server Error"}"""))
        assertNull(supportReferenceOf("""{"reference":"  "}"""))
        assertNull(supportReferenceOf("<html>Bad gateway</html>"))
    }

    @Test
    fun `a reference is shown for ten minutes after the error`() {
        val at = Instant.parse("2026-11-25T10:00:00Z")
        SupportReferences.record(SupportReference(reference = "abc", statusCode = 500, at = at))

        assertEquals("abc", SupportReferences.recent(at.plusSeconds(9 * 60))?.reference)
        assertNull(SupportReferences.recent(at.plusSeconds(11 * 60)))
        assertNull(SupportReferences.recent(at.minusSeconds(1)))
    }
}
