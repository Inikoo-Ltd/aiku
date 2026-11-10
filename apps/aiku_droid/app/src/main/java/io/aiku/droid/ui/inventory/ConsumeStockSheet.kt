package io.aiku.droid.ui.inventory

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
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData

@Composable
fun ConsumeStockSheet(skuCode: String, onDismiss: () -> Unit) {
    val toasts = LocalToasts.current
    val locations = remember { sampleSkoLocations.filter { it.stock > 0 } }
    var locationIndex by remember { mutableIntStateOf(0) }
    var quantity by remember { mutableIntStateOf(SAMPLE_CONSUME_QUANTITY) }
    var note by remember { mutableStateOf(SAMPLE_CONSUME_NOTE) }
    val location = locations[locationIndex]
    val consumedMessage = stringResource(R.string.inventory_consumed)

    AikuBottomSheet(
        title = stringResource(R.string.inventory_consume_title, skuCode),
        subtitle = stringResource(R.string.inventory_consume_subtitle),
        onDismiss = onDismiss,
    ) {
        ChoiceRow(
            title = location.code,
            subtitle = stringResource(R.string.inventory_in_location, location.stock),
            selected = true,
            onClick = {
                locationIndex = nextIndex(current = locationIndex, size = locations.size, isAllowed = { true })
                quantity = quantity.coerceAtMost(locations[locationIndex].stock)
            },
            modifier = Modifier.fakeData(10.dp),
        )
        SheetQuantityRow(
            value = quantity,
            onValueChange = { quantity = it },
            max = location.stock,
        )
        SheetTextField(
            value = note,
            onValueChange = { note = it },
            label = stringResource(R.string.inventory_note),
            modifier = Modifier.fakeData(8.dp),
        )
        SheetButtons(
            confirmLabel = stringResource(R.string.inventory_consume_confirm),
            onConfirm = {
                toasts.show(consumedMessage, ToastKind.Success)
                onDismiss()
            },
            onCancel = onDismiss,
        )
    }
}
