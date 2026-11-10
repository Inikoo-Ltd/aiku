package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SetPickingLocationSheet(skuCode: String, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    var wholesaleLocation by remember { mutableIntStateOf(1) }
    var dropshippingLocation by remember { mutableIntStateOf(0) }
    val savedText = stringResource(R.string.goodsout_picking_locations_saved, skuCode)

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_set_picking_location_title, skuCode),
        subtitle = stringResource(R.string.goodsout_set_picking_location_subtitle, GoodsOutSamples.SKO_NAME),
        onDismiss = onDismiss,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            ProductPhoto(code = skuCode, size = 56.dp)
            Text(
                text = stringResource(R.string.goodsout_set_picking_location_hint),
                fontSize = 13.sp,
                color = colors.textTertiary,
                modifier = Modifier.weight(1f).padding(start = 12.dp),
            )
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            GroupLabel(text = GoodsOutSamples.CHANNEL_WHOLESALE)
            GoodsOutSamples.wholesalePickingLocations.forEachIndexed { index, location ->
                ChoiceRow(
                    title = location.title,
                    subtitle = location.subtitle,
                    selected = index == wholesaleLocation,
                    onClick = { wholesaleLocation = index },
                )
            }
            GroupLabel(text = GoodsOutSamples.CHANNEL_DROPSHIPPING)
            GoodsOutSamples.dropshippingPickingLocations.forEachIndexed { index, location ->
                ChoiceRow(
                    title = location.title,
                    subtitle = location.subtitle,
                    selected = index == dropshippingLocation,
                    onClick = { dropshippingLocation = index },
                )
            }
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_set_picking_location_note))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            if (can.moveStock) {
                AikuButton(
                    text = stringResource(R.string.goodsout_save_picking_locations),
                    onClick = {
                        toasts.show(savedText, ToastKind.Success)
                        onDismiss()
                    },
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
