package io.aiku.droid.ui.mywork

import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.MyWorkRow
import io.aiku.droid.data.api.MyWorkStep
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.DeliveryNoteToDoRoute
import io.aiku.droid.ui.navigation.GoodsInReturnRoute
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.Instant

class MyWorkFormatTest {

    private fun note(state: String) = MyWorkRow(kind = MyWorkKind.DeliveryNote, id = 123, reference = "AR24811", state = state)

    private fun palletReturn(state: String, typeGroup: String?) =
        MyWorkRow(kind = MyWorkKind.PalletReturn, id = 7, reference = "acme-r004", state = state, typeGroup = typeGroup)

    @Test
    fun `a delivery note opens the screen for its state, as the delivery note list does`() {
        assertEquals(DeliveryNoteToDoRoute(id = 123, reference = "AR24811"), note("queued").route())
        assertEquals(DeliveryNotePickingRoute(id = 123, reference = "AR24811"), note("handling").route())
        assertEquals(DeliveryNotePickingRoute(id = 123, reference = "AR24811"), note("handling_blocked").route())
        assertEquals(DeliveryNotePickedRoute(id = 123, reference = "AR24811"), note("picked").route())
        assertEquals(DeliveryNotePackingRoute(id = 123, reference = "AR24811"), note("packing").route())
    }

    @Test
    fun `a picking session and a customer return open their own screens`() {
        assertEquals(
            PickingSessionRoute(id = 9, reference = "PS-9"),
            MyWorkRow(kind = MyWorkKind.PickingSession, id = 9, reference = "PS-9", state = "handling").route(),
        )
        assertEquals(
            GoodsInReturnRoute(id = 31, reference = "RET-31"),
            MyWorkRow(kind = MyWorkKind.CustomerReturn, id = 31, reference = "RET-31", state = "received").route(),
        )
    }

    @Test
    fun `a pallet return opens the fulfilment return screen for its state and type`() {
        assertEquals(ReturnPickPalletsRoute(7, "acme-r004"), palletReturn("picking", PalletReturnTypeGroup.PALLETS).route())
        assertEquals(ReturnPickItemsRoute(7, "acme-r004"), palletReturn("picking", PalletReturnTypeGroup.ITEMS).route())
        assertEquals(ReturnPickPalletsRoute(7, "acme-r004"), palletReturn("picking", null).route())
        assertEquals(ReturnPickedRoute(7, "acme-r004"), palletReturn("picked", PalletReturnTypeGroup.ITEMS).route())
    }

    @Test
    fun `waiting is a warning, the next step is primary and work in progress is neutral`() {
        assertEquals(Tone.Warn, myWorkStepTone(MyWorkStep.WAITING))
        listOf(MyWorkStep.TO_PICK, MyWorkStep.TO_PACK, MyWorkStep.TO_START, MyWorkStep.TO_DISPATCH).forEach { assertEquals(Tone.Primary, myWorkStepTone(it)) }
        listOf(MyWorkStep.PICKING, MyWorkStep.PACKING, MyWorkStep.RETURNING, "something_new").forEach { assertEquals(Tone.Neutral, myWorkStepTone(it)) }
    }

    @Test
    fun `since reads as minutes, hours or days ago`() {
        val now = Instant.parse("2026-11-17T10:00:00Z")

        assertEquals(Elapsed(ElapsedUnit.JustNow, 0), elapsedSince("2026-11-17T09:59:30Z", now))
        assertEquals(Elapsed(ElapsedUnit.JustNow, 0), elapsedSince("2026-11-17T10:05:00+00:00", now))
        assertEquals(Elapsed(ElapsedUnit.Minutes, 58), elapsedSince("2026-11-17T09:02:00+00:00", now))
        assertEquals(Elapsed(ElapsedUnit.Hours, 2), elapsedSince("2026-11-17T09:02:00+02:00", now))
        assertEquals(Elapsed(ElapsedUnit.Days, 3), elapsedSince("2026-11-14T08:00:00Z", now))
        assertNull(elapsedSince(null, now))
        assertNull(elapsedSince("yesterday", now))
    }
}
