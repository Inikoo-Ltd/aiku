package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.MovementReason
import io.aiku.droid.data.api.StockMovementReasons
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class AuditDraftTest {

    private val draft = AuditDraft(
        lines = listOf(
            AuditLine(locationOrgStockId = 5501, title = "A-03-02", current = 30.0),
            AuditLine(locationOrgStockId = 5502, title = "C-04-02", current = 6.5),
        ),
    )

    @Test
    fun `an unchanged count needs no reason`() {
        assertEquals(
            listOf(
                AuditCount(locationOrgStockId = 5501, quantity = 30.0, reason = null, note = null),
                AuditCount(locationOrgStockId = 5502, quantity = 6.5, reason = null, note = null),
            ),
            draft.counts(),
        )
    }

    @Test
    fun `a changed count needs a reason of its own direction`() {
        val counted = draft.withCount(5501, 28.0).withCount(5502, 7.5)

        assertNull(counted.counts())
        assertEquals(listOf("A-03-02", "C-04-02"), counted.linesNeedingReason.map { it.title })

        val wrongDirection = counted.withReason(5501, AuditDirection.Increase, "count_gain")
        assertNull(wrongDirection.reasonOf(wrongDirection.lines[0]))

        val reasoned = wrongDirection
            .withReason(5501, AuditDirection.Decrease, "damaged")
            .withReason(5502, AuditDirection.Increase, "count_gain")
            .copy(note = "  Wet box  ")

        assertEquals(
            listOf(
                AuditCount(locationOrgStockId = 5501, quantity = 28.0, reason = "damaged", note = "Wet box"),
                AuditCount(locationOrgStockId = 5502, quantity = 7.5, reason = "count_gain", note = "Wet box"),
            ),
            reasoned.counts(),
        )
    }

    @Test
    fun `turning a count back drops the need for a reason and a count never goes below zero`() {
        val back = draft.withCount(5501, 29.0).withCount(5501, 30.0).withCount(5502, -3.0)

        assertEquals(0.0, back.countedOf(back.lines[1]), 0.0)
        assertEquals(listOf("C-04-02"), back.linesNeedingReason.map { it.title })
        assertNull(back.direction(back.lines[0]))
    }

    @Test
    fun `reasons are offered for the direction of the change`() {
        val reasons = StockMovementReasons(
            increase = listOf(MovementReason("count_gain", "Count Gain")),
            decrease = listOf(MovementReason("count_short", "Count Shortage"), MovementReason("damaged", "Damaged Stock")),
        )

        assertEquals(listOf("count_gain"), reasons.forDirection(AuditDirection.Increase).map { it.key })
        assertEquals(2, reasons.forDirection(AuditDirection.Decrease).size)
        assertTrue(draft.linesNeedingReason.isEmpty())
    }
}
