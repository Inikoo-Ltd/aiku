package io.aiku.droid.ui.inbox

import androidx.annotation.DrawableRes
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import io.aiku.droid.R
import io.aiku.droid.data.api.InboxNotification
import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.api.WorkEvent
import io.aiku.droid.data.api.WorkRecord
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.ToneColors
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.mywork.workRoute
import io.aiku.droid.ui.theme.AikuColors
import java.time.Instant
import java.time.LocalDate
import java.time.OffsetDateTime
import java.time.ZoneId

data class RecordOpening(val warehouseId: Long, val route: Any)

fun WorkRecord.openingIn(warehouseIds: Collection<Long>): RecordOpening? {
    val warehouse = warehouseId?.takeIf { it in warehouseIds } ?: return null
    return RecordOpening(warehouseId = warehouse, route = workRoute(kind = kind, id = id, reference = reference, state = state, typeGroup = typeGroup))
}

fun RecordOpening.warehouseToSwitchTo(currentWarehouseId: Long?, warehouses: List<WarehouseAccess>): WarehouseAccess? =
    if (warehouseId == currentWarehouseId) null else warehouses.firstOrNull { it.id == warehouseId }

fun interface RecordOpener {
    fun open(opening: RecordOpening)
}

val LocalRecordOpener = staticCompositionLocalOf { RecordOpener { } }

data class InboxSections(val today: List<InboxNotification>, val earlier: List<InboxNotification>)

fun inboxSections(notifications: List<InboxNotification>, today: LocalDate, zone: ZoneId): InboxSections {
    val (todays, earlier) = notifications.partition { notification ->
        instantOf(notification.createdAt)?.atZone(zone)?.toLocalDate() == today
    }
    return InboxSections(today = todays, earlier = earlier)
}

data class InboxLook(@DrawableRes val icon: Int, val tone: Tone)

fun inboxLook(kind: String, event: String): InboxLook = when (event) {
    WorkEvent.ASSIGNED -> InboxLook(kindIcon(kind), Tone.Primary)
    WorkEvent.TAKEN_OVER -> InboxLook(R.drawable.ic_ui_person, Tone.Warn)
    WorkEvent.UNASSIGNED -> InboxLook(R.drawable.ic_ui_x_circle, Tone.Warn)
    WorkEvent.CANCELLED -> InboxLook(R.drawable.ic_ui_close, Tone.Danger)
    WorkEvent.READY_AGAIN -> InboxLook(R.drawable.ic_ui_check, Tone.Success)
    WorkEvent.MODIFIED -> InboxLook(R.drawable.ic_ui_edit, Tone.Warn)
    else -> InboxLook(kindIcon(kind), Tone.Neutral)
}

fun AikuColors.inboxRowBackground(isUnread: Boolean): Color = if (isUnread) soft else surface

fun AikuColors.inboxIconColors(tone: Tone, isUnread: Boolean): ToneColors {
    val colors = tone(tone)
    return if (colors.container == inboxRowBackground(isUnread)) colors.copy(container = softBorder) else colors
}

@DrawableRes
private fun kindIcon(kind: String): Int = when (MyWorkKind.ofKey(kind)) {
    MyWorkKind.DeliveryNote -> R.drawable.ic_ui_delivery_note
    MyWorkKind.PickingSession -> R.drawable.ic_ui_trolley
    MyWorkKind.CustomerReturn -> R.drawable.ic_ui_goods_in
    MyWorkKind.PalletReturn -> R.drawable.ic_ui_pallet
    null -> R.drawable.ic_ui_bell
}

internal fun instantOf(text: String?): Instant? {
    val value = text?.takeIf { it.isNotBlank() } ?: return null
    return runCatching { OffsetDateTime.parse(value).toInstant() }.getOrNull()
        ?: runCatching { Instant.parse(value) }.getOrNull()
}
