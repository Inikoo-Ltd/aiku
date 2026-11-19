package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.AuditItemTarget
import io.aiku.droid.data.api.PalletReturnScanStatus
import io.aiku.droid.data.api.StoredItemAuditWrite
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class OutboundScanMissTest {

    private fun scanned(status: String) = OutboundEvent.Scanned(code = "MUG-BLUE", status = status, message = null, lineId = null)

    private val scannedAdd = StoredItemAuditWrite.Add(target = AuditItemTarget.Barcode("MUG-BLUE"), add = 1, clientRequestId = "r-1")

    @Test
    fun `a picked scan is not a miss`() {
        assertFalse(scanned(PalletReturnScanStatus.PICKED).isScanMiss)
    }

    @Test
    fun `every other scan status is a miss`() {
        listOf(
            PalletReturnScanStatus.NOT_FOUND,
            PalletReturnScanStatus.ALREADY_PICKED,
            PalletReturnScanStatus.NO_STOCK,
            PalletReturnScanStatus.WRONG_STATE,
        ).forEach { status -> assertTrue(status, scanned(status).isScanMiss) }
    }

    @Test
    fun `a code that is not on the return is a miss`() {
        assertTrue(OutboundEvent.NotOnReturn(code = "X").isScanMiss)
    }

    @Test
    fun `an audit refusing a scanned barcode is a miss`() {
        val refused = OutboundEvent.Refused(action = scannedAdd, message = "This stored item does not belong to the customer.", problem = GoodsOutProblem.ServerError)

        assertTrue(refused.isScanMiss)
    }

    @Test
    fun `losing the connection on a scan is not a miss`() {
        assertFalse(OutboundEvent.Refused(action = scannedAdd, message = "", problem = GoodsOutProblem.Offline).isScanMiss)
    }

    @Test
    fun `refusals of other actions and found lines are not misses`() {
        val counted = StoredItemAuditWrite.Count(target = AuditItemTarget.Item(1), auditedQuantity = 3)

        assertFalse(OutboundEvent.Refused(action = counted, message = "Not allowed", problem = GoodsOutProblem.Forbidden).isScanMiss)
        assertFalse(OutboundEvent.LineFound(lineId = 4).isScanMiss)
        assertFalse(OutboundEvent.PalletFound(palletId = 1, reference = "P-1").isScanMiss)
    }
}
