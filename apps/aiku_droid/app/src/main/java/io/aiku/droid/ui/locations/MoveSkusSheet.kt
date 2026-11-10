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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.ScanCameraRoute

@Composable
fun MoveSkusSheet(locationCode: String, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    var targetQuery by remember { mutableStateOf("") }
    var removesFromSource by remember { mutableStateOf(false) }
    val target = LocationsSamples.MOVE_TARGET_CODE
    val movedText = stringResource(R.string.locations_move_done, target)

    AikuBottomSheet(
        title = stringResource(R.string.locations_move_title, locationCode),
        onDismiss = onDismiss,
        subtitle = stringResource(R.string.locations_move_subtitle),
    ) {
        SearchScanField(
            placeholder = stringResource(R.string.locations_move_target),
            value = targetQuery,
            onValueChange = { targetQuery = it },
            onScan = {
                onDismiss()
                navigator.open(ScanCameraRoute)
            },
        )
        ChoiceRow(
            title = target,
            selected = true,
            onClick = {},
            modifier = Modifier.fakeData(cornerRadius = 10.dp),
            subtitle = listOf(LocationsSamples.MOVE_TARGET_AREA, stringResource(R.string.locations_empty)).joinToString(LIST_SEPARATOR),
        )
        TrailingCheckboxRow(
            label = stringResource(R.string.locations_move_remove_source, locationCode),
            checked = removesFromSource,
            onCheckedChange = { removesFromSource = it },
            labelSize = 14.sp,
        )
        ChoiceRow(
            title = stringResource(R.string.locations_move_reason_transfer),
            selected = true,
            onClick = {},
            subtitle = stringResource(R.string.locations_move_reason),
        )
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().padding(top = 4.dp)) {
            AikuButton(
                text = stringResource(R.string.locations_move_confirm, target),
                onClick = {
                    toasts.show(text = movedText, kind = ToastKind.Success)
                    onDismiss()
                },
                modifier = Modifier.fillMaxWidth(),
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
