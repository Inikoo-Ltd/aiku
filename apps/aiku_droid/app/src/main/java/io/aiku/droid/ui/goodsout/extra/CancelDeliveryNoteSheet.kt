package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse

@Composable
fun CancelDeliveryNoteSheet(reference: String, onDismiss: () -> Unit) {
    var showMore by remember { mutableStateOf(false) }

    if (showMore) {
        DeliveryNoteMoreSheet(reference = reference, onDismiss = onDismiss)
    } else {
        CancelDeliveryNoteConfirmSheet(reference = reference, onBack = { showMore = true }, onDone = onDismiss)
    }
}

@Composable
internal fun CancelDeliveryNoteConfirmSheet(reference: String, onBack: () -> Unit, onDone: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_cancel_title, reference),
        subtitle = stringResource(R.string.goodsout_extra_cancel_subtitle),
        onDismiss = onBack,
    ) {
        HintText(text = stringResource(R.string.goodsout_extra_cancel_hint))
        SheetButtons {
            if (can.superviseGoodsOut) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_cancel_delivery_note),
                    onClick = {
                        onDone()
                        navigator.open(DeliveryNoteCancelledRoute(reference))
                    },
                    style = ButtonStyle.Danger,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.goodsout_extra_keep_it),
                onClick = onBack,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
