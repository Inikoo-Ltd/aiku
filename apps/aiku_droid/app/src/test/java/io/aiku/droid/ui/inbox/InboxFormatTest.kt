package io.aiku.droid.ui.inbox

import io.aiku.droid.R
import io.aiku.droid.data.api.InboxNotification
import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.OrganisationSummary
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.api.WorkEvent
import io.aiku.droid.data.api.WorkRecord
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.GoodsInReturnRoute
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.theme.AikuTheme
import io.aiku.droid.ui.theme.aikuColors
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate
import java.time.ZoneId

class InboxFormatTest {

    private val warehouses = listOf(1L, 2L)

    @Test
    fun `each kind of target opens its own screen in its own warehouse`() {
        assertEquals(
            RecordOpening(1, DeliveryNotePickingRoute(id = 991, reference = "AR24811")),
            WorkRecord(MyWorkKind.DeliveryNote, id = 991, reference = "AR24811", state = "handling", warehouseId = 1).openingIn(warehouses),
        )
        assertEquals(
            RecordOpening(2, PickingSessionRoute(id = 9, reference = "PS-9")),
            WorkRecord(MyWorkKind.PickingSession, id = 9, reference = "PS-9", state = "in_process", warehouseId = 2).openingIn(warehouses),
        )
        assertEquals(
            RecordOpening(1, GoodsInReturnRoute(id = 31, reference = "RET-31")),
            WorkRecord(MyWorkKind.CustomerReturn, id = 31, reference = "RET-31", state = "received", warehouseId = 1).openingIn(warehouses),
        )
        assertEquals(
            RecordOpening(1, ReturnPickItemsRoute(id = 7, reference = "acme-r004")),
            WorkRecord(MyWorkKind.PalletReturn, id = 7, reference = "acme-r004", state = "picking", warehouseId = 1, typeGroup = "items").openingIn(warehouses),
        )
    }

    @Test
    fun `the screen follows the record's state`() {
        assertEquals(
            DeliveryNoteCancelledRoute(id = 991, reference = "AR24811"),
            WorkRecord(MyWorkKind.DeliveryNote, id = 991, reference = "AR24811", state = "cancelled", warehouseId = 1).openingIn(warehouses)?.route,
        )
        assertEquals(
            DeliveryNotePackingRoute(id = 991, reference = "AR24811"),
            WorkRecord(MyWorkKind.DeliveryNote, id = 991, reference = "AR24811", state = "packing", warehouseId = 1).openingIn(warehouses)?.route,
        )
        assertEquals(
            ReturnPickPalletsRoute(id = 7, reference = "acme-r004"),
            WorkRecord(MyWorkKind.PalletReturn, id = 7, reference = "acme-r004", state = "confirmed", warehouseId = 1, typeGroup = null).openingIn(warehouses)?.route,
        )
        assertEquals(
            ReturnDispatchedRoute(id = 7, reference = "acme-r004"),
            WorkRecord(MyWorkKind.PalletReturn, id = 7, reference = "acme-r004", state = "cancel", warehouseId = 1, typeGroup = "pallets").openingIn(warehouses)?.route,
        )
    }

    @Test
    fun `a record outside the user's warehouses or without one cannot be opened`() {
        assertNull(WorkRecord(MyWorkKind.DeliveryNote, id = 991, reference = "AR24811", state = "handling", warehouseId = 9).openingIn(warehouses))
        assertNull(WorkRecord(MyWorkKind.DeliveryNote, id = 991, reference = "AR24811", state = "handling", warehouseId = null).openingIn(warehouses))
        assertNull(WorkRecord(MyWorkKind.DeliveryNote, id = 991, reference = "AR24811", state = "handling", warehouseId = 1).openingIn(emptyList()))
    }

    @Test
    fun `notifications split into today and earlier by the device's day`() {
        val zone = ZoneId.of("Asia/Jakarta")
        val lateYesterdayUtcButTodayHere = InboxNotification(id = "a", createdAt = "2026-11-17T18:30:00+00:00")
        val thisMorning = InboxNotification(id = "b", createdAt = "2026-11-18T02:00:00Z")
        val yesterdayHere = InboxNotification(id = "c", createdAt = "2026-11-17T16:59:00Z")
        val undated = InboxNotification(id = "d", createdAt = null)

        val sections = inboxSections(listOf(lateYesterdayUtcButTodayHere, thisMorning, yesterdayHere, undated), today = LocalDate.of(2026, 11, 18), zone = zone)

        assertEquals(listOf("a", "b"), sections.today.map { it.id })
        assertEquals(listOf("c", "d"), sections.earlier.map { it.id })
    }

    @Test
    fun `each event has its own icon and tone, unknown ones a neutral bell`() {
        assertEquals(InboxLook(R.drawable.ic_ui_delivery_note, Tone.Primary), inboxLook("delivery_note", WorkEvent.ASSIGNED))
        assertEquals(InboxLook(R.drawable.ic_ui_trolley, Tone.Primary), inboxLook("picking_session", WorkEvent.ASSIGNED))
        assertEquals(InboxLook(R.drawable.ic_ui_close, Tone.Danger), inboxLook("delivery_note", WorkEvent.CANCELLED))
        assertEquals(InboxLook(R.drawable.ic_ui_check, Tone.Success), inboxLook("return", WorkEvent.READY_AGAIN))
        assertEquals(InboxLook(R.drawable.ic_ui_edit, Tone.Warn), inboxLook("delivery_note", WorkEvent.MODIFIED))
        assertEquals(InboxLook(R.drawable.ic_ui_person, Tone.Warn), inboxLook("pallet_return", WorkEvent.TAKEN_OVER))
        assertEquals(InboxLook(R.drawable.ic_ui_x_circle, Tone.Warn), inboxLook("pallet_return", WorkEvent.UNASSIGNED))
        assertEquals(InboxLook(R.drawable.ic_ui_bell, Tone.Neutral), inboxLook("something_new", "something_happened"))
    }

    @Test
    fun `opening a record in another warehouse switches to it and names it`() {
        val opening = RecordOpening(warehouseId = 2, route = PickingSessionRoute(id = 9, reference = "PS-9"))

        assertEquals(secondWarehouse, opening.warehouseToSwitchTo(currentWarehouseId = 1, warehouses = userWarehouses))
        assertEquals(secondWarehouse, opening.warehouseToSwitchTo(currentWarehouseId = null, warehouses = userWarehouses))
    }

    @Test
    fun `opening a record in the current warehouse does not switch, so no toast`() {
        val opening = RecordOpening(warehouseId = 1, route = PickingSessionRoute(id = 9, reference = "PS-9"))

        assertNull(opening.warehouseToSwitchTo(currentWarehouseId = 1, warehouses = userWarehouses))
    }

    @Test
    fun `a warehouse the user no longer has is never switched to`() {
        val opening = RecordOpening(warehouseId = 7, route = PickingSessionRoute(id = 9, reference = "PS-9"))

        assertNull(opening.warehouseToSwitchTo(currentWarehouseId = 1, warehouses = userWarehouses))
    }

    @Test
    fun `every event icon sits in a circle that stands out from its row, read or unread, in every theme`() {
        val events = listOf(WorkEvent.ASSIGNED, WorkEvent.TAKEN_OVER, WorkEvent.UNASSIGNED, WorkEvent.CANCELLED, WorkEvent.READY_AGAIN, WorkEvent.MODIFIED, "something_new")
        val kinds = MyWorkKind.entries.map { it.key }
        val failures = AikuTheme.entries.flatMap { theme ->
            listOf(false, true).flatMap { isDark ->
                val colors = aikuColors(theme, isDark)
                listOf(false, true).flatMap { isUnread ->
                    kinds.flatMap { kind ->
                        events.mapNotNull { event ->
                            val tone = inboxLook(kind, event).tone
                            "${theme.name} dark=$isDark unread=$isUnread $kind $event"
                                .takeIf { colors.inboxIconColors(tone, isUnread).container == colors.inboxRowBackground(isUnread) }
                        }
                    }
                }
            }
        }

        assertTrue(failures.joinToString("\n"), failures.isEmpty())
    }

    @Test
    fun `the assigned circle keeps the primary tone on a read row`() {
        val colors = aikuColors(AikuTheme.DeepSea, isDark = false)

        assertEquals(colors.tone(Tone.Primary), colors.inboxIconColors(Tone.Primary, isUnread = false))
        assertEquals(colors.link, colors.inboxIconColors(Tone.Primary, isUnread = true).content)
    }

    private val secondWarehouse = WarehouseAccess(
        id = 2,
        slug = "wh2",
        code = "WH2",
        name = "Second warehouse",
        organisation = OrganisationSummary(id = 3, slug = "aw", name = "AW"),
        can = WarehouseAbilities(viewGoodsOut = true),
    )

    private val userWarehouses = listOf(secondWarehouse.copy(id = 1, slug = "wh1", code = "WH1", name = "Main warehouse"), secondWarehouse)
}
