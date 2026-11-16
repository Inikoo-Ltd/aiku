package io.aiku.droid.ui.locations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.inventory.SheetButtons
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun RemoveSkusSheet(
    detail: LocationDetail,
    isWorking: Boolean,
    errorText: String?,
    onRemove: (List<Long>) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var selected by remember { mutableStateOf(emptySet<Long>()) }
    var isMissing by remember { mutableStateOf(false) }

    AikuBottomSheet(
        title = stringResource(R.string.locations_remove_title, detail.code),
        onDismiss = onDismiss,
        subtitle = stringResource(R.string.locations_remove_subtitle),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier) {
            detail.orgStocks.forEach { sko ->
                val isSelected = sko.id in selected
                val type = stockTypeLabel(sko.stockType)?.let { stringResource(it) }
                ChoiceRow(
                    title = sko.code,
                    selected = isSelected,
                    onClick = {
                        isMissing = false
                        selected = if (isSelected) selected - sko.id else selected + sko.id
                    },
                    subtitle = listOfNotNull(type, stringResource(R.string.locations_in_location, stockText(sko.stock))).joinToString(LIST_SEPARATOR),
                )
            }
        }
        DangerNote(text = stringResource(R.string.locations_remove_warning))
        if (isMissing) {
            Text(text = stringResource(R.string.locations_remove_missing), fontSize = 13.sp, color = colors.danger)
        }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        SheetButtons(
            confirmLabel = pluralStringResource(R.plurals.locations_remove_confirm, selected.size, selected.size),
            onConfirm = { if (selected.isEmpty()) isMissing = true else onRemove(selected.toList()) },
            onCancel = onDismiss,
            confirmStyle = ButtonStyle.Danger,
            isWorking = isWorking,
        )
    }
}
