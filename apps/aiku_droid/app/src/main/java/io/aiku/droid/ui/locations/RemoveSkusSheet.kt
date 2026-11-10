package io.aiku.droid.ui.locations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
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

@Composable
fun RemoveSkusSheet(locationCode: String, onDismiss: () -> Unit) {
    val toasts = LocalToasts.current
    val removableSkos = LocationsSamples.removableSkos
    var selectedCodes by remember { mutableStateOf(removableSkos.map { it.code }.toSet()) }
    val selectedCount = selectedCodes.size
    val removedText = pluralStringResource(R.plurals.locations_removed, selectedCount, selectedCount, locationCode)

    AikuBottomSheet(
        title = stringResource(R.string.locations_remove_title, locationCode),
        onDismiss = onDismiss,
        subtitle = stringResource(R.string.locations_remove_subtitle),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            removableSkos.forEach { sko ->
                val isSelected = sko.code in selectedCodes
                ChoiceRow(
                    title = sko.code,
                    selected = isSelected,
                    onClick = { selectedCodes = if (isSelected) selectedCodes - sko.code else selectedCodes + sko.code },
                    subtitle = stringResource(R.string.locations_remove_row, stringResource(sko.type.label), sko.quantity),
                )
            }
        }
        DangerNote(text = stringResource(R.string.locations_remove_warning))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().padding(top = 4.dp)) {
            AikuButton(
                text = pluralStringResource(R.plurals.locations_remove_confirm, selectedCount, selectedCount),
                onClick = {
                    if (selectedCount > 0) {
                        toasts.show(text = removedText, kind = ToastKind.Success)
                    }
                    onDismiss()
                },
                modifier = Modifier.fillMaxWidth(),
                style = ButtonStyle.Danger,
                height = 52.dp,
            )
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                modifier = Modifier.fillMaxWidth(),
                style = ButtonStyle.Outline,
                height = 52.dp,
            )
        }
    }
}
