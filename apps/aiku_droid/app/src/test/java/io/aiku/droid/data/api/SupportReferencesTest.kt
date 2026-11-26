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

    @Test
    fun `a problem report sends only the details it has`() {
        val fields = SupportResponses.fields(
            ProblemReport(
                category = ProblemCategory.UnknownBarcode,
                details = "  Scanned at the gate  ",
                screen = " ",
                errorCode = "AIKU-APP-001",
                lastScan = "5012345678900",
                warehouseId = 3,
            ),
        )

        assertEquals(
            listOf(
                "category" to "unknown_barcode",
                "details" to "Scanned at the gate",
                "error_code" to "AIKU-APP-001",
                "last_scan" to "5012345678900",
                "warehouse_id" to "3",
            ),
            fields,
        )
    }

    @Test
    fun `the report answer gives the ticket reference or what went wrong`() {
        assertEquals(ProblemReportResult.Sent("HELP-4810"), SupportResponses.parse(201, """{"data":{"reference":"HELP-4810"}}"""))
        assertEquals(ProblemReportResult.Invalid("Tell us what happened."), SupportResponses.parse(422, """{"message":"x","errors":{"details":["Tell us what happened."]}}"""))
        assertEquals(ProblemReportResult.TooMany, SupportResponses.parse(429, ""))
        assertEquals(ProblemReportResult.SessionExpired, SupportResponses.parse(401, ""))
        assertEquals(ProblemReportResult.ServerError(500), SupportResponses.parse(500, ""))
        assertEquals(ProblemReportResult.ServerError(201), SupportResponses.parse(201, "<html>"))
    }
}
