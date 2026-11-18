package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.OutboundPalletRef
import io.aiku.droid.data.api.OutboundStoredItemRef
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnState
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.data.api.ReturnItemLine
import io.aiku.droid.data.api.ReturnStoredItem
import io.aiku.droid.data.api.StoredItemAuditType
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute

enum class ReturnScreen { PickPallets, PickItems, Picked, Dispatched }

private const val ACTIVE_STORED_ITEM = "active"
private const val DISCONTINUING_STORED_ITEM = "discontinuing"
private const val DISCONTINUED_STORED_ITEM = "discontinued"
private const val PICKING_LINE = "picking"
internal const val PICKED_LINE = "picked"
private const val NOT_PICKED_LINE = "not_picked"
private const val CANCELLED_LINE = "cancel"

val returnStages = listOf(PalletReturnState.CONFIRMED, PalletReturnState.PICKING, PalletReturnState.PICKED, PalletReturnState.DISPATCHED)

fun returnScreenFor(state: String, typeGroup: String): ReturnScreen = when (state) {
    PalletReturnState.PICKED -> ReturnScreen.Picked
    PalletReturnState.DISPATCHED, PalletReturnState.CANCEL -> ReturnScreen.Dispatched
    else -> if (typeGroup == PalletReturnTypeGroup.ITEMS) ReturnScreen.PickItems else ReturnScreen.PickPallets
}

val PalletReturn.screen: ReturnScreen get() = returnScreenFor(state, typeGroup)

fun returnRoute(screen: ReturnScreen, id: Long, reference: String): Any = when (screen) {
    ReturnScreen.PickPallets -> ReturnPickPalletsRoute(id, reference)
    ReturnScreen.PickItems -> ReturnPickItemsRoute(id, reference)
    ReturnScreen.Picked -> ReturnPickedRoute(id, reference)
    ReturnScreen.Dispatched -> ReturnDispatchedRoute(id, reference)
}

fun PalletReturnRow.route(): Any = returnRoute(returnScreenFor(state, typeGroup), id, reference)

fun PalletReturn.route(): Any = returnRoute(screen, id, reference)

internal fun OutboundPalletRef.matches(code: String): Boolean =
    reference.equals(code, ignoreCase = true) || customerReference?.equals(code, ignoreCase = true) == true

internal fun OutboundStoredItemRef.matches(code: String): Boolean =
    reference.equals(code, ignoreCase = true) || barcode?.equals(code, ignoreCase = true) == true

internal fun returnStateTone(state: String): Tone = when (state) {
    PalletReturnState.PICKING -> Tone.Primary
    PalletReturnState.PICKED, PalletReturnState.DISPATCHED -> Tone.Success
    PalletReturnState.CANCEL -> Tone.Danger
    else -> Tone.Neutral
}

internal fun lineStateTone(state: String, waitingCustomerService: Boolean): Tone = when {
    waitingCustomerService -> Tone.Warn
    state == PICKED_LINE -> Tone.Success
    state == NOT_PICKED_LINE || state == CANCELLED_LINE -> Tone.Danger
    state == PICKING_LINE -> Tone.Primary
    else -> Tone.Neutral
}

internal fun storedItemStateTone(state: String): Tone = when (state) {
    ACTIVE_STORED_ITEM -> Tone.Success
    DISCONTINUING_STORED_ITEM -> Tone.Warn
    DISCONTINUED_STORED_ITEM -> Tone.Danger
    else -> Tone.Neutral
}

internal fun palletStateTone(state: String?): Tone = when (state) {
    "storing" -> Tone.Success
    "damaged", "lost", "other_incident", "not_picked" -> Tone.Danger
    "picking", "picked" -> Tone.Primary
    else -> Tone.Neutral
}

internal fun auditTypeTone(type: String?, difference: Int?): Tone = when (type) {
    StoredItemAuditType.NO_CHANGE -> Tone.Success
    StoredItemAuditType.SUBTRACTION -> Tone.Warn
    StoredItemAuditType.ADDITION, StoredItemAuditType.SET_UP -> Tone.Primary
    else -> when {
        difference == null -> Tone.Neutral
        difference == 0 -> Tone.Success
        difference < 0 -> Tone.Warn
        else -> Tone.Primary
    }
}

internal fun itemLineTone(line: ReturnItemLine): Tone? = when {
    line.waitingCustomerService -> Tone.Warn
    line.quantityOrdered > 0 && line.quantityPicked >= line.quantityOrdered -> Tone.Success
    line.quantityPicked > 0 || line.quantityNotPicked > 0 -> Tone.Warn
    else -> null
}

data class PalletGroup(val pallet: OutboundPalletRef?, val locationCode: String?, val lines: List<Pair<ReturnStoredItem, ReturnItemLine>>)

internal fun palletGroups(storedItems: List<ReturnStoredItem>): List<PalletGroup> =
    storedItems
        .flatMap { item -> item.lines.map { line -> item to line } }
        .groupBy { (_, line) -> line.pallet?.id }
        .map { (_, lines) ->
            val first = lines.first().second
            PalletGroup(pallet = first.pallet, locationCode = first.location?.code?.takeIf { it.isNotBlank() }, lines = lines)
        }
        .sortedWith(compareBy<PalletGroup>({ it.locationCode == null }, { it.locationCode.orEmpty() }, { it.pallet?.reference.orEmpty() }))
