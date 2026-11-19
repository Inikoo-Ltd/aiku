package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.AuditItemTarget
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OutboundStoredItemRef
import io.aiku.droid.data.api.OutboundWriteStatus
import io.aiku.droid.data.api.StoredItemAudit
import io.aiku.droid.data.api.StoredItemAuditActionKey
import io.aiku.droid.data.api.StoredItemAuditLine
import io.aiku.droid.data.api.StoredItemAuditOutcome
import io.aiku.droid.data.api.StoredItemAuditWrite
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class StoredItemAuditViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeFulfilmentOutboundApi()
    private var requestIds = 0

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun audit(counted: Int?, vararg keys: String) = StoredItemAudit(
        id = 12,
        reference = "acme-sia014",
        lines = listOf(
            StoredItemAuditLine(storedItem = OutboundStoredItemRef(id = 1, reference = "MUG-BLUE", barcode = "5060"), quantity = 40, auditedQuantity = counted, deltaId = counted?.let { 77 }),
        ),
        availableActions = actions(*keys),
    )

    private fun viewModel(loaded: StoredItemAudit): StoredItemAuditViewModel {
        api.audit = GoodsOutResult.Loaded(loaded)
        return StoredItemAuditViewModel(api, FakeSessions(), warehouseId = 1, auditId = 12) {
            requestIds += 1
            "req-$requestIds"
        }.also { it.onStart() }
    }

    private fun answer(after: StoredItemAudit, message: String? = null): (StoredItemAuditWrite) -> GoodsOutResult<StoredItemAuditOutcome> =
        { GoodsOutResult.Loaded(StoredItemAuditOutcome(audit = after, status = OutboundWriteStatus.DONE, message = message)) }

    @Test
    fun `pulling to refresh loads the audit again`() {
        val detail = viewModel(audit(counted = null))
        api.audit = GoodsOutResult.Loaded(audit(counted = 3))

        detail.refresh()

        assertEquals(audit(counted = 3), detail.state.value.audit)
        assertFalse(detail.state.value.isRefreshing)
    }

    @Test
    fun `counting, removing a count and completing send their writes`() {
        api.auditWriteResults = answer(audit(38, StoredItemAuditActionKey.COUNT, StoredItemAuditActionKey.COMPLETE))
        val detail = viewModel(audit(null, StoredItemAuditActionKey.COUNT))

        detail.count(storedItemId = 1, auditedQuantity = 38)
        detail.removeCount(77)
        detail.complete()

        assertEquals(
            listOf(
                StoredItemAuditWrite.Count(target = AuditItemTarget.Item(1), auditedQuantity = 38),
                StoredItemAuditWrite.RemoveCount(77),
                StoredItemAuditWrite.Complete,
            ),
            api.auditWrites,
        )
        assertEquals(38, detail.state.value.audit?.lines?.single()?.auditedQuantity)
        assertEquals(StoredItemAuditWrite.Complete, (detail.state.value.event as OutboundEvent.Done).action)
    }

    @Test
    fun `a scan adds one with a fresh request id and shows the last scanned item`() {
        api.auditWriteResults = answer(audit(1, StoredItemAuditActionKey.COUNT))
        val detail = viewModel(audit(null, StoredItemAuditActionKey.COUNT))

        detail.scan(" 5060 ")
        detail.scan("5060")

        assertEquals(
            listOf(
                StoredItemAuditWrite.Add(target = AuditItemTarget.Barcode("5060"), add = 1, clientRequestId = "req-1"),
                StoredItemAuditWrite.Add(target = AuditItemTarget.Barcode("5060"), add = 1, clientRequestId = "req-2"),
            ),
            api.auditWrites,
        )
        assertEquals("MUG-BLUE", detail.state.value.lastScanned)
        assertEquals(0, detail.state.value.queuedScans)
    }

    @Test
    fun `an unanswered scan is retried with the same request id`() {
        api.auditWriteResults = { GoodsOutResult.Unreachable }
        val detail = viewModel(audit(null, StoredItemAuditActionKey.COUNT))

        detail.scan("5060")

        assertTrue(detail.state.value.unsure is StoredItemAuditWrite.Add)
        assertEquals(GoodsOutProblem.Offline, (detail.state.value.event as OutboundEvent.Refused).problem)

        api.auditWriteResults = answer(audit(1, StoredItemAuditActionKey.COUNT))
        detail.retryUnsure()

        assertEquals(listOf("req-1", "req-1"), api.auditWrites.map { (it as StoredItemAuditWrite.Add).clientRequestId })
        assertNull(detail.state.value.unsure)
        assertEquals("MUG-BLUE", detail.state.value.lastScanned)
    }

    @Test
    fun `a refused count keeps the audit and shows the server error`() {
        api.auditWriteResults = { GoodsOutResult.Invalid("That item is not this customer's.", mapOf("barcode" to listOf("That item is not this customer's."))) }
        val detail = viewModel(audit(null, StoredItemAuditActionKey.COUNT))

        detail.scan("999")

        assertEquals("That item is not this customer's.", (detail.state.value.event as OutboundEvent.Refused).message)
        assertEquals(listOf("That item is not this customer's."), detail.state.value.writeErrors["barcode"])
        assertNull(detail.state.value.unsure)
        assertNull(detail.state.value.lastScanned)
        assertFalse(detail.state.value.isWorking)
    }

    @Test
    fun `the add item search asks for the customer's other items`() {
        api.choices = GoodsOutResult.Loaded(listOf(OutboundStoredItemRef(id = 5, reference = "CANDLE")))
        val detail = viewModel(audit(null, StoredItemAuditActionKey.ADD_ITEM))

        detail.searchChoices("can")
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(listOf<String?>("can"), api.choiceSearches)
        assertEquals("CANDLE", detail.state.value.choices.items.single().reference)

        detail.searchChoices(" ")
        dispatcher.scheduler.advanceTimeBy(301)

        assertNull(api.choiceSearches.last())
    }
}
