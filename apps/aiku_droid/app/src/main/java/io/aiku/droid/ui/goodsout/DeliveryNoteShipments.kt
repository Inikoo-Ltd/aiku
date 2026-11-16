package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.DeliveryNoteShipment
import io.aiku.droid.data.api.ShipmentLabelKind
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.theme.LocalAikuColors

internal fun labelKey(shipment: DeliveryNoteShipment): String = "label-${shipment.id}"

@Composable
internal fun ShipmentsBlock(
    note: DeliveryNote,
    openingKey: String?,
    printingShipmentId: Long?,
    onOpenLabel: (DeliveryNoteShipment) -> Unit,
    onOpenLink: (String) -> Unit,
    onPrintLabel: (DeliveryNoteShipment) -> Unit,
    onDelete: (DeliveryNoteShipment) -> Unit,
    onAddAnother: (DeliveryNoteAction) -> Unit,
) {
    if (note.shipments.isEmpty()) {
        return
    }

    GoodsOutHeading(title = stringResource(R.string.goodsout_shipments))
    ListGroup {
        note.shipments.forEachIndexed { index, shipment ->
            if (index > 0) {
                RowDivider()
            }
            ShipmentCard(
                shipment = shipment,
                canPrintLabels = note.shipping.canPrintLabels,
                isOpeningLabel = openingKey == labelKey(shipment),
                isPrinting = printingShipmentId == shipment.id,
                onOpenLabel = { onOpenLabel(shipment) },
                onOpenLink = onOpenLink,
                onPrintLabel = { onPrintLabel(shipment) },
                onDelete = { onDelete(shipment) },
            )
        }
    }
    note.addAnotherShipment?.let { action ->
        AikuButton(
            text = stringResource(R.string.goodsout_add_another_shipment),
            onClick = { onAddAnother(action) },
            style = ButtonStyle.Outline,
            icon = R.drawable.ic_ui_truck,
            height = 48.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ShipmentCard(
    shipment: DeliveryNoteShipment,
    canPrintLabels: Boolean,
    isOpeningLabel: Boolean,
    isPrinting: Boolean,
    onOpenLabel: () -> Unit,
    onOpenLink: (String) -> Unit,
    onPrintLabel: () -> Unit,
    onDelete: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val label = shipment.label
    val labelUrl = label.url?.takeIf { it.isNotBlank() }
    val labelAction = when {
        labelUrl == null -> null
        label.kind == ShipmentLabelKind.DOWNLOAD -> stringResource(if (isOpeningLabel) R.string.goodsout_opening else R.string.goodsout_label_pdf) to onOpenLabel
        label.kind == ShipmentLabelKind.EXTERNAL -> stringResource(R.string.goodsout_label_open) to { onOpenLink(labelUrl) }
        else -> null
    }
    val canPrint = canPrintLabels && shipment.isPrintable

    Column(modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            IconTile40(icon = R.drawable.ic_ui_truck, tone = Tone.Success)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = shipment.shipper?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_shipment),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                )
                if (shipment.trackingNumbers.isEmpty()) {
                    Text(text = stringResource(R.string.goodsout_shipment_no_tracking), fontSize = 13.sp, color = colors.textTertiary)
                }
            }
        }
        shipment.trackingNumbers.forEachIndexed { index, tracking ->
            val url = shipment.trackingUrl(index)
            Text(
                text = stringResource(R.string.goodsout_shipment_tracking, tracking),
                fontSize = 14.sp,
                fontWeight = if (url != null) FontWeight.SemiBold else FontWeight.Normal,
                color = if (url != null) colors.link else colors.textSecondary,
                textDecoration = if (url != null) TextDecoration.Underline else null,
                modifier = Modifier
                    .heightIn(min = 44.dp)
                    .clip(ChipShape)
                    .then(if (url != null) Modifier.clickable(role = Role.Button) { onOpenLink(url) } else Modifier)
                    .padding(vertical = 12.dp),
            )
        }
        if (labelAction != null || canPrint || shipment.canDelete) {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(16.dp), modifier = Modifier.fillMaxWidth()) {
                labelAction?.let { (text, onClick) -> ShipmentAction(text = text, color = colors.link, onClick = onClick) }
                if (canPrint) {
                    ShipmentAction(
                        text = stringResource(if (isPrinting) R.string.goodsout_label_printing else R.string.goodsout_label_print),
                        color = colors.link,
                        onClick = onPrintLabel,
                    )
                }
                if (shipment.canDelete) {
                    ShipmentAction(text = stringResource(R.string.goodsout_delete), color = colors.danger, onClick = onDelete)
                }
            }
        }
    }
}

@Composable
private fun ShipmentAction(text: String, color: Color, onClick: () -> Unit) {
    Text(
        text = text,
        fontSize = 14.sp,
        fontWeight = FontWeight.SemiBold,
        color = color,
        modifier = Modifier
            .heightIn(min = 44.dp)
            .clip(ChipShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 4.dp, vertical = 12.dp),
    )
}

@Composable
internal fun DeleteShipmentSheet(shipment: DeliveryNoteShipment, isWorking: Boolean, problem: String?, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    val shipper = shipment.shipper?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_shipment)

    ActionConfirmSheet(
        title = stringResource(R.string.goodsout_delete_shipment_title, shipper),
        subtitle = shipment.trackingNumbers.joinToString(", ").takeIf { it.isNotEmpty() }?.let { stringResource(R.string.goodsout_shipment_tracking, it) },
        confirmLabel = stringResource(R.string.goodsout_delete_shipment),
        icon = R.drawable.ic_ui_close,
        confirmStyle = ButtonStyle.Danger,
        hint = stringResource(R.string.goodsout_delete_shipment_hint),
        isWorking = isWorking,
        problem = problem,
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}
