package io.aiku.droid.ui.inventory

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
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
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData

@Composable
fun RemoveLocationsSheet(skuCode: String, onDismiss: () -> Unit) {
    val toasts = LocalToasts.current
    val locations = sampleRemovableLocations
    var selected by remember { mutableStateOf(setOf(locations.first().code)) }
    val removedMessage = pluralStringResource(R.plurals.inventory_removed, selected.size, selected.size)

    AikuBottomSheet(
        title = stringResource(R.string.inventory_remove_title, skuCode),
        subtitle = stringResource(R.string.inventory_remove_subtitle),
        onDismiss = onDismiss,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(14.dp), modifier = Modifier.fakeData(10.dp)) {
            locations.forEach { location ->
                val isSelected = location.code in selected
                ChoiceRow(
                    title = location.code,
                    subtitle = stringResource(R.string.inventory_pair, locationChip(location), stringResource(R.string.inventory_in_location, location.stock)),
                    selected = isSelected,
                    onClick = { selected = if (isSelected) selected - location.code else selected + location.code },
                )
            }
        }
        SheetDangerNote(text = stringResource(R.string.inventory_remove_warning))
        SheetButtons(
            confirmLabel = pluralStringResource(R.plurals.inventory_remove_confirm, selected.size, selected.size),
            confirmStyle = ButtonStyle.Danger,
            onConfirm = {
                if (selected.isNotEmpty()) {
                    toasts.show(removedMessage, ToastKind.Success)
                }
                onDismiss()
            },
            onCancel = onDismiss,
        )
    }
}
