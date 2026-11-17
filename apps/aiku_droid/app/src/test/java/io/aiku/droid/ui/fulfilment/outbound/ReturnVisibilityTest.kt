package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnState
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.data.api.ReturnOtherPallet
import io.aiku.droid.data.api.StoredItemAudit
import io.aiku.droid.data.api.StoredItemAuditActionKey
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ReturnVisibilityTest {

    @Test
    fun `a return row opens the screen for its state and type`() {
        assertEquals(ReturnPickPalletsRoute(7, "r"), PalletReturnRow(id = 7, reference = "r", state = PalletReturnState.CONFIRMED, typeGroup = PalletReturnTypeGroup.PALLETS).route())
        assertEquals(ReturnPickItemsRoute(7, "r"), PalletReturnRow(id = 7, reference = "r", state = PalletReturnState.PICKING, typeGroup = PalletReturnTypeGroup.ITEMS).route())
        assertEquals(ReturnPickedRoute(7, "r"), PalletReturnRow(id = 7, reference = "r", state = PalletReturnState.PICKED, typeGroup = PalletReturnTypeGroup.ITEMS).route())
        assertEquals(ReturnDispatchedRoute(7, "r"), PalletReturnRow(id = 7, reference = "r", state = PalletReturnState.DISPATCHED).route())
        assertEquals(ReturnDispatchedRoute(7, "r"), PalletReturnRow(id = 7, reference = "r", state = PalletReturnState.CANCEL).route())
    }

    @Test
    fun `return actions show only when the server lists them without a disabled reason`() {
        val palletReturn = palletReturn(state = "picked", keys = listOf(PalletReturnActionKey.ADD_SHIPMENT)).copy(
            availableActions = listOf(
                DeliveryNoteAction(key = PalletReturnActionKey.ADD_SHIPMENT, label = "Add shipment"),
                DeliveryNoteAction(key = PalletReturnActionKey.DISPATCH, label = "Dispatch", disabledReason = "Add a shipment first."),
            ),
        )

        assertTrue(palletReturn.can(PalletReturnActionKey.ADD_SHIPMENT))
        assertFalse(palletReturn.can(PalletReturnActionKey.DISPATCH))
        assertNull(palletReturn.action(PalletReturnActionKey.PRINT_INSERTS))
        assertFalse(palletReturn(state = "picked").can(PalletReturnActionKey.DISPATCH))
    }

    @Test
    fun `line actions follow each line's available actions`() {
        val picked = palletLine(55, 10, "p1", "picked", PalletReturnActionKey.UNDO)
        val toPick = palletLine(56, 11, "p2", "picking", PalletReturnActionKey.PICK, PalletReturnActionKey.NOT_PICKED)
        val viewOnly = palletLine(57, 12, "p3", "picking")

        assertTrue(picked.can(PalletReturnActionKey.UNDO))
        assertFalse(picked.can(PalletReturnActionKey.PICK))
        assertTrue(toPick.can(PalletReturnActionKey.NOT_PICKED))
        assertFalse(viewOnly.hasActions)
        assertTrue(palletReturn(pallets = listOf(viewOnly, toPick)).hasLineActions)
        assertFalse(palletReturn(pallets = listOf(viewOnly)).hasLineActions)
    }

    @Test
    fun `an item line picks at most what is to pick and on the pallet, and a short item offers another pallet only to pickers`() {
        val short = itemLine(62, 12, "p2", ordered = 3, picked = 0, toPick = 3, onPallet = 1, PalletReturnActionKey.PICK, PalletReturnActionKey.NOT_PICKED)
        val item = storedItem(1, "GIFT-L", toPick = 3, lines = listOf(short), others = listOf(ReturnOtherPallet(palletStoredItemId = 88, quantityAvailable = 5)))
        val viewOnly = storedItem(1, "GIFT-L", toPick = 3, lines = listOf(short.copy(availableActions = emptyList())), others = item.otherPallets)
        val nothingElsewhere = storedItem(1, "GIFT-L", toPick = 3, lines = listOf(short), others = listOf(ReturnOtherPallet(palletStoredItemId = 88, quantityAvailable = 0)))

        assertEquals(1, short.maxPick)
        assertEquals(2, short.shortfall)
        assertEquals(62L, item.shortLine?.lineId)
        assertTrue(item.canPickFromOtherPallet)
        assertFalse(viewOnly.canPickFromOtherPallet)
        assertFalse(nothingElsewhere.canPickFromOtherPallet)
        assertFalse(short.isFullyPicked)
        assertTrue(short.copy(quantityPicked = 1, quantityNotPicked = 2).isFullyPicked)
    }

    @Test
    fun `item lines group by pallet in walk order`() {
        val items = listOf(
            storedItem(1, "A", toPick = 0, lines = listOf(itemLine(1, 20, "p-20", 1, 0, 1, 1, location = "D-04-06"), itemLine(2, 10, "p-10", 1, 0, 1, 1, location = "D-03-01"))),
            storedItem(2, "B", toPick = 0, lines = listOf(itemLine(3, 30, "p-30", 1, 0, 1, 1), itemLine(4, 10, "p-10", 1, 0, 1, 1, location = "D-03-01"))),
        )

        val groups = palletGroups(items)

        assertEquals(listOf("p-10", "p-20", "p-30"), groups.map { it.pallet?.reference })
        assertEquals(listOf("A", "B"), groups.first().lines.map { (item, _) -> item.storedItem.reference })
        assertNull(groups.last().locationCode)
    }

    @Test
    fun `audit actions show only while offered`() {
        val open = StoredItemAudit(id = 1, availableActions = actions(StoredItemAuditActionKey.COUNT, StoredItemAuditActionKey.ADD_ITEM))
        val completed = StoredItemAudit(id = 1)

        assertTrue(open.can(StoredItemAuditActionKey.COUNT))
        assertFalse(open.can(StoredItemAuditActionKey.COMPLETE))
        assertFalse(completed.can(StoredItemAuditActionKey.COUNT))
        assertFalse(completed.can(StoredItemAuditActionKey.ADD_ITEM))
    }

    @Test
    fun `scanned codes match pallets and stored items without case`() {
        val line = palletLine(56, 11, "acme-p0121", customerReference = "Spring 2")

        assertTrue(line.pallet.matches("ACME-P0121"))
        assertTrue(line.pallet.matches("spring 2"))
        assertFalse(line.pallet.matches("acme-p012"))
    }
}
