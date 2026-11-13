package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun PickLocationSheet(line: DeliveryNoteLine, selectedId: Long?, onChoose: (Long) -> Unit, onBack: () -> Unit) {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_line_unknown_sko)
    var showSetPickingLocation by remember { mutableStateOf(false) }

    if (showSetPickingLocation) {
        SetPickingLocationSheet(skuCode = code, onDismiss = { showSetPickingLocation = false })
        return
    }

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_pick_location_title, code),
        subtitle = listOfNotNull(
            line.orgStock?.name?.takeIf { it.isNotBlank() },
            stringResource(R.string.goodsout_pick_location_to_pick, quantityText(line.quantityToPick, line.quantityToPickLabel)),
        ).joinToString(" · "),
        onDismiss = onBack,
        footer = {
            if (can.moveStock) {
                AikuButton(
                    text = stringResource(R.string.goodsout_change_picking_location),
                    onClick = { showSetPickingLocation = true },
                    style = ButtonStyle.Outline,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
                )
            }
            AikuButton(
                text = stringResource(R.string.goodsout_back),
                onClick = onBack,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            ProductPhoto(code = code, size = 56.dp, imageUrl = line.orgStock?.imageUrl)
            Text(
                text = stringResource(R.string.goodsout_pick_location_hint),
                fontSize = 13.sp,
                color = colors.textTertiary,
                modifier = Modifier.weight(1f).padding(start = 12.dp),
            )
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            line.locations.forEach { location ->
                val id = location.locationOrgStockId ?: return@forEach
                val stock = location.stock ?: 0.0
                val picked = location.quantityPicked ?: 0.0
                BadgeChoiceRow(
                    title = location.code,
                    subtitle = if (picked > 0.0) stringResource(R.string.goodsout_location_picked_here, quantityText(picked)) else null,
                    badge = if (stock > 0.0) {
                        stringResource(R.string.goodsout_location_in_stock, location.stockLabel?.takeIf { it.isNotBlank() } ?: quantityText(stock))
                    } else {
                        stringResource(R.string.goodsout_location_empty)
                    },
                    selected = id == selectedId,
                    onClick = { onChoose(id) },
                    isBadgeDanger = stock <= 0.0,
                    enabled = stock > 0.0 || picked > 0.0,
                )
            }
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_pick_location_note))
    }
}
