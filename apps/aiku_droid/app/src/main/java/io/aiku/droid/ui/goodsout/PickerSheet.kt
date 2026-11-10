package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
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
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData

@Composable
fun PickerSheet(reference: String, onDismiss: () -> Unit) {
    val toasts = LocalToasts.current
    var search by remember { mutableStateOf("") }
    var selectedPicker by remember { mutableIntStateOf(GoodsOutSamples.PICKER_SHEET_SELECTED) }
    val pickers = GoodsOutSamples.pickers
    val selectedName = pickers[selectedPicker].title
    val assignedText = stringResource(R.string.goodsout_picker_assigned, selectedName, reference)
    val removedText = stringResource(R.string.goodsout_picker_removed, reference)

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_picker_title),
        subtitle = stringResource(R.string.goodsout_picker_subtitle, reference, GoodsOutSamples.PICKER_NOW),
        onDismiss = onDismiss,
    ) {
        SearchField(
            value = search,
            onValueChange = { search = it },
            placeholder = stringResource(R.string.goodsout_picker_search),
            modifier = Modifier.fillMaxWidth(),
        )
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            pickers
                .withIndex()
                .filter { search.isBlank() || it.value.title.contains(search, ignoreCase = true) }
                .forEach { (index, picker) ->
                    ChoiceRow(
                        title = picker.title,
                        subtitle = picker.subtitle,
                        selected = index == selectedPicker,
                        onClick = { selectedPicker = index },
                    )
                }
        }
        GroupLabel(text = stringResource(R.string.goodsout_picker_packer))
        ChoiceRow(
            title = stringResource(R.string.goodsout_packer_same_as_picker),
            subtitle = stringResource(R.string.goodsout_packer_same_as_picker_hint),
            selected = true,
            onClick = {},
        )
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            AikuButton(
                text = stringResource(R.string.goodsout_picker_assign, selectedName),
                onClick = {
                    toasts.show(assignedText, ToastKind.Success)
                    onDismiss()
                },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
            )
            AikuButton(
                text = stringResource(R.string.goodsout_picker_remove),
                onClick = {
                    toasts.show(removedText, ToastKind.Success)
                    onDismiss()
                },
                style = ButtonStyle.Danger,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
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
