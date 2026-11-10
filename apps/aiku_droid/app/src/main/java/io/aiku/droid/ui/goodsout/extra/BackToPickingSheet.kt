package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse

@Composable
fun BackToPickingSheet(reference: String, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val waitingLines = GoodsOutExtraSamples.waitingLines.size

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_back_to_picking_title),
        subtitle = pluralStringResource(R.plurals.goodsout_extra_back_to_picking_subtitle, waitingLines, reference, waitingLines),
        onDismiss = onDismiss,
    ) {
        HintText(text = stringResource(R.string.goodsout_extra_back_to_picking_hint, GoodsOutExtraSamples.WAITING_BAY))
        SheetButtons {
            if (can.superviseGoodsOut) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_back_to_picking),
                    onClick = {
                        onDismiss()
                        navigator.open(DeliveryNotePickingRoute(reference))
                    },
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.goodsout_extra_keep_waiting),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
