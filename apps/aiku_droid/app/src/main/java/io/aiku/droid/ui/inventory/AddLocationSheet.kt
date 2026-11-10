package io.aiku.droid.ui.inventory

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
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.ScanCameraRoute

@Composable
fun AddLocationSheet(skuCode: String, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val found = sampleFoundLocation
    var query by remember { mutableStateOf("") }
    var kind by remember { mutableStateOf(LocationKind.Storing) }
    val addedMessage = stringResource(R.string.inventory_added, found.code)

    AikuBottomSheet(
        title = stringResource(R.string.inventory_add_title, skuCode),
        subtitle = stringResource(R.string.inventory_add_subtitle),
        onDismiss = onDismiss,
    ) {
        SearchScanField(
            placeholder = stringResource(R.string.inventory_add_search),
            value = query,
            onValueChange = { query = it },
            onScan = {
                onDismiss()
                navigator.open(ScanCameraRoute)
            },
        )
        ChoiceRow(
            title = found.code,
            subtitle = found.detail,
            selected = true,
            onClick = {},
            modifier = Modifier.fakeData(10.dp),
        )
        SheetGroupLabel(text = stringResource(R.string.inventory_type))
        ChoiceRow(
            title = stringResource(R.string.inventory_location_storing),
            selected = kind == LocationKind.Storing,
            onClick = { kind = LocationKind.Storing },
        )
        ChoiceRow(
            title = stringResource(R.string.inventory_location_picking),
            subtitle = stringResource(R.string.inventory_add_picking_hint),
            selected = kind == LocationKind.Picking,
            onClick = { kind = LocationKind.Picking },
        )
        SheetButtons(
            confirmLabel = stringResource(R.string.inventory_add_confirm, found.code),
            onConfirm = {
                toasts.show(addedMessage, ToastKind.Success)
                onDismiss()
            },
            onCancel = onDismiss,
        )
    }
}
