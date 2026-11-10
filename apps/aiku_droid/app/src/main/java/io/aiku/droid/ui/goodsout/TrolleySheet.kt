package io.aiku.droid.ui.goodsout

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
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator

@Composable
fun TrolleySheet(reference: String, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    var selectedTrolley by remember { mutableIntStateOf(0) }
    val available = GoodsOutSamples.availableTrolleys

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_trolley_title),
        subtitle = stringResource(R.string.goodsout_delivery_note_reference, reference),
        onDismiss = onDismiss,
    ) {
        GroupLabel(text = stringResource(R.string.goodsout_trolley_available))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            available.forEachIndexed { index, trolley ->
                ChoiceRow(
                    title = trolley.title,
                    subtitle = trolley.subtitle,
                    selected = index == selectedTrolley,
                    onClick = { selectedTrolley = index },
                )
            }
        }
        GroupLabel(text = stringResource(R.string.goodsout_trolley_unavailable))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            GoodsOutSamples.unavailableTrolleys.forEach { trolley ->
                ChoiceRow(
                    title = trolley.title,
                    subtitle = trolley.subtitle,
                    selected = false,
                    onClick = {},
                    enabled = trolley.isAvailable,
                )
            }
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_trolley_note))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            AikuButton(
                text = stringResource(R.string.goodsout_trolley_start, available[selectedTrolley].title),
                onClick = {
                    onDismiss()
                    navigator.open(DeliveryNotePickingRoute(reference = reference))
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
