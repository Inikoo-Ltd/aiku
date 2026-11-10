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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun PickLocationSheet(skuCode: String, onDismiss: () -> Unit) {
    PickLocationFlow(skuCode = skuCode, onDone = onDismiss, onCancel = onDismiss)
}

@Composable
internal fun PickLocationFlow(skuCode: String, onDone: () -> Unit, onCancel: () -> Unit) {
    var showSetPickingLocation by remember { mutableStateOf(false) }

    if (showSetPickingLocation) {
        SetPickingLocationSheet(skuCode = skuCode, onDismiss = { showSetPickingLocation = false })
    } else {
        PickLocationContent(
            skuCode = skuCode,
            onDone = onDone,
            onCancel = onCancel,
            onChangePickingLocation = { showSetPickingLocation = true },
        )
    }
}

@Composable
private fun PickLocationContent(skuCode: String, onDone: () -> Unit, onCancel: () -> Unit, onChangePickingLocation: () -> Unit) {
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val locations = GoodsOutSamples.pickLocations
    var selectedLocation by remember { mutableIntStateOf(GoodsOutSamples.PICK_LOCATION_SELECTED) }
    val left = GoodsOutSamples.LINE_SHEET_REQUIRED - GoodsOutSamples.LINE_SHEET_PICKED
    val selectedCode = locations[selectedLocation].code
    val pickedText = stringResource(R.string.goodsout_pick_from_toast, skuCode, selectedCode)

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_pick_location_title, skuCode),
        subtitle = pluralStringResource(R.plurals.goodsout_pick_location_subtitle, left, GoodsOutSamples.SKO_NAME, left),
        onDismiss = onCancel,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            ProductPhoto(code = skuCode, size = 56.dp)
            Text(
                text = stringResource(R.string.goodsout_pick_location_hint),
                fontSize = 13.sp,
                color = colors.textTertiary,
                modifier = Modifier.weight(1f).padding(start = 12.dp),
            )
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            locations.forEachIndexed { index, location ->
                BadgeChoiceRow(
                    title = location.code,
                    subtitle = location.subtitle,
                    badge = location.stock,
                    selected = index == selectedLocation,
                    onClick = { selectedLocation = index },
                    isBadgeDanger = !location.hasStock,
                    enabled = location.hasStock,
                )
            }
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_pick_location_note))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            AikuButton(
                text = stringResource(R.string.goodsout_pick_from, selectedCode),
                onClick = {
                    toasts.show(pickedText, ToastKind.Success)
                    onDone()
                },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
            )
            if (can.moveStock) {
                AikuButton(
                    text = stringResource(R.string.goodsout_change_picking_location),
                    onClick = onChangePickingLocation,
                    style = ButtonStyle.Outline,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onCancel,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
