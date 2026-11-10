package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotePickedRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator

@Composable
fun PickedBaySheet(reference: String, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current

    PickedBayChooser(
        reference = reference,
        confirmLabel = R.string.goodsout_bay_set_as_picked,
        onConfirm = { navigator.switchSection(DeliveryNotePickedRoute(reference = reference)) },
        onDismiss = onDismiss,
    )
}

@Composable
internal fun ChangePickedBaySheet(reference: String, onDismiss: () -> Unit) {
    val toasts = LocalToasts.current
    val movedText = stringResource(R.string.goodsout_bay_changed_toast, reference)

    PickedBayChooser(
        reference = reference,
        confirmLabel = R.string.goodsout_bay_use,
        onConfirm = { toasts.show(movedText, ToastKind.Success) },
        onDismiss = onDismiss,
    )
}

@Composable
private fun PickedBayChooser(reference: String, @StringRes confirmLabel: Int, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    var selectedBay by remember { mutableIntStateOf(0) }
    val bays = GoodsOutSamples.pickedBays

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_bay_title),
        subtitle = stringResource(R.string.goodsout_bay_subtitle, reference),
        onDismiss = onDismiss,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            bays.forEachIndexed { index, bay ->
                ChoiceRow(
                    title = bay.title,
                    subtitle = bay.subtitle,
                    selected = index == selectedBay,
                    onClick = { selectedBay = index },
                )
            }
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_bay_note))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            AikuButton(
                text = stringResource(confirmLabel, bays[selectedBay].title),
                onClick = {
                    onDismiss()
                    onConfirm()
                },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
            )
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
