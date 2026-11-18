package io.aiku.droid.ui.fulfilment.outbound

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
import io.aiku.droid.data.api.DeliveryNoteShipment
import io.aiku.droid.data.api.ShipmentLabelKind
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsout.IconTile40
import io.aiku.droid.ui.theme.LocalAikuColors

internal fun shipmentLabelKey(shipment: DeliveryNoteShipment): String = "return-label-${shipment.id}"

internal class ShipmentHandlers(
    val openingKey: String?,
    val printingShipmentId: Long?,
    val canPrint: Boolean,
    val canDelete: Boolean,
    val onOpenLabel: (DeliveryNoteShipment) -> Unit,
    val onOpenLink: (String) -> Unit,
    val onPrintLabel: (DeliveryNoteShipment) -> Unit,
    val onDelete: (DeliveryNoteShipment) -> Unit,
)

@Composable
internal fun ReturnShipmentsBlock(shipments: List<DeliveryNoteShipment>, handlers: ShipmentHandlers) {
    if (shipments.isEmpty()) {
        return
    }
    SectionHeading(title = stringResource(R.string.goodsout_shipments))
    ListGroup {
        shipments.forEachIndexed { index, shipment ->
            if (index > 0) {
                RowDivider()
            }
            ReturnShipmentCard(shipment = shipment, handlers = handlers)
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ReturnShipmentCard(shipment: DeliveryNoteShipment, handlers: ShipmentHandlers) {
    val colors = LocalAikuColors.current
    val labelUrl = shipment.label.url?.takeIf { it.isNotBlank() }
    val labelAction: Pair<String, () -> Unit>? = when {
        labelUrl == null -> null
        shipment.label.kind == ShipmentLabelKind.DOWNLOAD -> stringResource(
            if (handlers.openingKey == shipmentLabelKey(shipment)) R.string.goodsout_opening else R.string.goodsout_label_pdf,
        ) to { handlers.onOpenLabel(shipment) }
        shipment.label.kind == ShipmentLabelKind.EXTERNAL -> stringResource(R.string.goodsout_label_open) to { handlers.onOpenLink(labelUrl) }
        else -> null
    }
    val canPrint = handlers.canPrint && shipment.isPrintable
    val canDelete = handlers.canDelete && shipment.canDelete

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
                    .then(if (url != null) Modifier.clickable(role = Role.Button) { handlers.onOpenLink(url) } else Modifier)
                    .padding(vertical = 12.dp),
            )
        }
        if (labelAction != null || canPrint || canDelete) {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(16.dp), modifier = Modifier.fillMaxWidth()) {
                labelAction?.let { (text, onClick) -> ShipmentLink(text = text, color = colors.link, onClick = onClick) }
                if (canPrint) {
                    ShipmentLink(
                        text = stringResource(if (handlers.printingShipmentId == shipment.id) R.string.goodsout_label_printing else R.string.goodsout_label_print),
                        color = colors.link,
                        onClick = { handlers.onPrintLabel(shipment) },
                    )
                }
                if (canDelete) {
                    ShipmentLink(text = stringResource(R.string.goodsout_delete), color = colors.danger, onClick = { handlers.onDelete(shipment) })
                }
            }
        }
    }
}

@Composable
private fun ShipmentLink(text: String, color: Color, onClick: () -> Unit) {
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
