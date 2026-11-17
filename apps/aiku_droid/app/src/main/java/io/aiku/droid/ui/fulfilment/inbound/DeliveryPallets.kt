package io.aiku.droid.ui.fulfilment.inbound

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentLocation
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletDeliveryWrite
import io.aiku.droid.data.api.PalletLocationTarget
import io.aiku.droid.data.api.PalletState
import io.aiku.droid.data.api.PalletStoredItem
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.KeyValueRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.fulfilment.dotJoined
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.inventory.SheetGroupLabel
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun DeliveryPalletList(pallets: List<Pallet>, onOpen: (Pallet) -> Unit, isOpenable: (Pallet) -> Boolean = { true }) {
    if (pallets.isEmpty()) {
        return
    }
    ListGroup {
        pallets.forEachIndexed { index, pallet ->
            if (index > 0) {
                RowDivider()
            }
            ItemRow(
                code = pallet.reference,
                subtitle = palletSubtitle(pallet, showCustomer = false),
                chip = palletChip(pallet),
                chipTone = palletTone(pallet),
                showPhoto = false,
                onClick = if (isOpenable(pallet)) ({ onOpen(pallet) }) else null,
            )
        }
    }
}

@Composable
internal fun StoredItemList(items: List<PalletStoredItem>) {
    if (items.isEmpty()) {
        return
    }
    ListGroup {
        items.forEachIndexed { index, item ->
            if (index > 0) {
                RowDivider()
            }
            ItemRow(
                code = item.reference,
                subtitle = dotJoined(item.name, item.barcode),
                chip = item.stateLabel?.takeIf { it.isNotBlank() && item.state != ACTIVE_STORED_ITEM },
                done = countText(item.quantity),
                showPhoto = false,
            )
        }
    }
}

private const val ACTIVE_STORED_ITEM = "active"

@Composable
internal fun DeliveryPalletSheet(
    pallet: Pallet,
    working: PalletDeliveryWrite?,
    onNotReceived: () -> Unit,
    onUndoNotReceived: () -> Unit,
    onDismiss: () -> Unit,
) {
    val notReceived = pallet.action(FulfilmentInboundActionKey.NOT_RECEIVED)
    val undo = pallet.action(FulfilmentInboundActionKey.UNDO_NOT_RECEIVED)

    AikuBottomSheet(
        title = pallet.reference,
        subtitle = dotJoined(palletTypeText(pallet), quotedReference(pallet.customerReference)),
        onDismiss = onDismiss,
        footer = if (notReceived == null && undo == null) {
            null
        } else {
            {
                if (undo != null) {
                    AikuButton(
                        text = undo.label.ifBlank { stringResource(R.string.fulfilment_inbound_undo_not_received) },
                        onClick = onUndoNotReceived,
                        style = fulfilmentInboundActionLook(undo.key).style,
                        icon = fulfilmentInboundActionLook(undo.key).icon,
                        isLoading = working is PalletDeliveryWrite.UndoNotReceived,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
                if (notReceived != null) {
                    AikuButton(
                        text = notReceived.label.ifBlank { stringResource(R.string.fulfilment_not_received) },
                        onClick = onNotReceived,
                        style = fulfilmentInboundActionLook(notReceived.key).style,
                        icon = fulfilmentInboundActionLook(notReceived.key).icon,
                        isLoading = working is PalletDeliveryWrite.NotReceived,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        },
    ) {
        PalletFactsCard(pallet = pallet)
        if (pallet.storedItems.isNotEmpty()) {
            SheetGroupLabel(text = stringResource(R.string.fulfilment_stored_items))
            StoredItemList(items = pallet.storedItems)
        }
    }
}

@Composable
private fun PalletFactsCard(pallet: Pallet) {
    AikuCard(padding = 14.dp, spacing = 8.dp) {
        KeyValueRow(label = stringResource(R.string.fulfilment_pallet_state), value = pallet.stateLabel.ifBlank { pallet.state }, valueTone = palletTone(pallet))
        KeyValueRow(label = stringResource(R.string.fulfilment_pallet_location), value = palletLocationText(pallet))
        pallet.notes?.takeIf { it.isNotBlank() }?.let { KeyValueRow(label = stringResource(R.string.fulfilment_inbound_notes), value = it) }
    }
}

@Composable
internal fun BookInPanel(
    pallet: Pallet,
    finderState: FulfilmentLocationFinderState,
    fieldError: String?,
    working: PalletDeliveryWrite?,
    onQueryChange: (String) -> Unit,
    onPick: (FulfilmentLocation) -> Unit,
    onScanned: (String) -> Unit,
    onBookIn: (PalletLocationTarget) -> Unit,
    onNotReceived: () -> Unit,
    onClose: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val canBookIn = pallet.can(FulfilmentInboundActionKey.BOOK_IN)
    val notReceived = pallet.action(FulfilmentInboundActionKey.NOT_RECEIVED)
    val target = finderState.target
    val isBooked = pallet.state == PalletState.BOOKED_IN

    AikuCard(padding = 14.dp, spacing = 10.dp) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(text = pallet.reference, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
            palletChip(pallet)?.let { StatusBadge(text = it, tone = palletTone(pallet)) }
        }
        Text(
            text = dotJoined(palletTypeText(pallet), quotedReference(pallet.customerReference), palletLocationText(pallet)),
            fontSize = 14.sp,
            color = colors.textTertiary,
        )
        if (canBookIn) {
            SectionHeading(title = stringResource(if (isBooked) R.string.fulfilment_inbound_change_location else R.string.fulfilment_inbound_scan_location))
            PalletLocationPicker(state = finderState, onQueryChange = onQueryChange, onPick = onPick, onScanned = onScanned)
        }
        fieldError?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        if (canBookIn && target != null) {
            AikuButton(
                text = stringResource(R.string.fulfilment_book_in_at, target.code),
                onClick = { onBookIn(target) },
                style = fulfilmentInboundActionLook(FulfilmentInboundActionKey.BOOK_IN).style,
                icon = fulfilmentInboundActionLook(FulfilmentInboundActionKey.BOOK_IN).icon,
                isLoading = working is PalletDeliveryWrite.BookIn,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            if (notReceived != null) {
                AikuButton(
                    text = notReceived.label.ifBlank { stringResource(R.string.fulfilment_not_received) },
                    onClick = onNotReceived,
                    style = fulfilmentInboundActionLook(notReceived.key).style,
                    icon = fulfilmentInboundActionLook(notReceived.key).icon,
                    isLoading = working is PalletDeliveryWrite.NotReceived,
                    height = 48.dp,
                    modifier = Modifier.weight(1f),
                )
            }
            AikuButton(
                text = stringResource(R.string.fulfilment_inbound_choose_another),
                onClick = onClose,
                style = ButtonStyle.Outline,
                height = 48.dp,
                modifier = Modifier.weight(1f),
            )
        }
    }
}
