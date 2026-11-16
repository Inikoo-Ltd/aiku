package io.aiku.droid.ui.inventory

import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun RemoveLocationsSheet(detail: OrgStockDetail, isWorking: Boolean, errorText: String?, onRemove: (OrgStockWrite) -> Unit, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    var selected by remember { mutableStateOf(emptySet<Long>()) }
    var isMissing by remember { mutableStateOf(false) }

    AikuBottomSheet(
        title = stringResource(R.string.inventory_remove_title, detail.code),
        subtitle = stringResource(R.string.inventory_remove_subtitle),
        onDismiss = onDismiss,
    ) {
        detail.locations.forEach { location ->
            val isSelected = location.locationOrgStockId in selected
            ChoiceRow(
                title = location.location.code,
                subtitle = listOfNotNull(
                    skoLocationChip(location),
                    stringResource(R.string.inventory_in_location, stockText(location.quantity, location.quantityLabel)),
                ).joinToString(LIST_SEPARATOR),
                selected = isSelected,
                onClick = {
                    isMissing = false
                    selected = if (isSelected) selected - location.locationOrgStockId else selected + location.locationOrgStockId
                },
            )
        }
        SheetDangerNote(text = stringResource(R.string.inventory_remove_warning))
        if (isMissing) {
            Text(text = stringResource(R.string.inventory_remove_missing), fontSize = 13.sp, color = colors.danger)
        }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        SheetButtons(
            confirmLabel = pluralStringResource(R.plurals.inventory_remove_confirm, selected.size, selected.size),
            confirmStyle = ButtonStyle.Danger,
            onConfirm = {
                if (selected.isEmpty()) {
                    isMissing = true
                } else {
                    onRemove(OrgStockWrite.RemoveLocations(detail.locations.map { it.locationOrgStockId }.filter { it in selected }))
                }
            },
            onCancel = onDismiss,
            isWorking = isWorking,
        )
    }
}
