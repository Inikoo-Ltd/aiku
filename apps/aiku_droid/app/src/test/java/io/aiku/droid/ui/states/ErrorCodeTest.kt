package io.aiku.droid.ui.states

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class ErrorCodeTest {

    @Test
    fun `server answers map to the server error or the busy code`() {
        assertEquals(ErrorCode.ServerError, ErrorCode.forServerStatus(500))
        assertEquals(ErrorCode.ServerError, ErrorCode.forServerStatus(null))
        assertEquals(ErrorCode.Unavailable, ErrorCode.forServerStatus(503))
        assertEquals(ErrorCode.Unavailable, ErrorCode.forServerStatus(502))
    }

    @Test
    fun `every offline reason the server sends has its own code`() {
        val reasons = listOf(
            "over_required", "location_empty", "not_enough_stock", "other_change", "note_cancelled", "delivery_closed",
            "return_closed", "waiting_cleared", "in_picking_session", "not_handler", "over_packed", "over_checked",
            "over_expected", "setting_off", "wrong_state", "no_right", "not_found", "invalid",
        )
        val codes = reasons.map { ErrorCode.forSyncReason(it) }

        assertEquals(reasons.size, codes.filterNotNull().toSet().size)
        assertEquals(ErrorCode.SyncLocationEmpty, ErrorCode.forSyncReason("location_empty"))
        assertNull(ErrorCode.forSyncReason("something_new"))
        assertNull(ErrorCode.forSyncReason(null))
    }

    @Test
    fun `the help page opens in Spanish or Slovak when the app is, otherwise in English`() {
        assertEquals("https://aiku.io/docs/aiku-sync-002-es", ErrorCode.SyncLocationEmpty.docsUrl("es"))
        assertEquals("https://aiku.io/docs/aiku-app-001-sk", ErrorCode.Unreachable.docsUrl("sk"))
        assertEquals("https://aiku.io/docs/aiku-clk-003", ErrorCode.ClockingNoPin.docsUrl("de"))
    }

    @Test
    fun `codes are unique and keep their area prefix`() {
        val codes = ErrorCode.entries.map { it.code }

        assertEquals(codes.size, codes.toSet().size)
        assertEquals(30, codes.size)
        codes.forEach { assert(Regex("AIKU-(APP|CLK|SYNC)-\\d{3}").matches(it)) { it } }
    }
}
