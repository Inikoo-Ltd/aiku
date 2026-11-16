package io.aiku.droid.ui.inventory

import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun AddLocationSheet(
    detail: OrgStockDetail,
    finder: LocationFinder,
    isWorking: Boolean,
    errorText: String?,
    onAdd: (OrgStockWrite) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val finderState by finder.state.collectAsStateWithLifecycle()
    var type by remember { mutableStateOf(LocationStockType.Storing) }
    var isMissing by remember { mutableStateOf(false) }
    val picked = finderState.picked
    val currentPicking = detail.locations.firstOrNull { it.stockType == LocationStockType.Picking }?.location?.code

    LaunchedEffect(finder) { finder.reset() }

    AikuBottomSheet(
        title = stringResource(R.string.inventory_add_title, detail.code),
        subtitle = stringResource(R.string.inventory_add_subtitle),
        onDismiss = onDismiss,
    ) {
        LocationPicker(
            state = finderState,
            placeholder = stringResource(R.string.inventory_add_search),
            onQueryChange = finder::search,
            onPick = { id ->
                isMissing = false
                finderState.results.firstOrNull { it.id == id }?.let(finder::pick)
            },
            onScanned = finder::scan,
        )
        SheetGroupLabel(text = stringResource(R.string.inventory_type))
        ChoiceRow(
            title = stringResource(R.string.inventory_location_storing),
            selected = type == LocationStockType.Storing,
            onClick = { type = LocationStockType.Storing },
        )
        ChoiceRow(
            title = stringResource(R.string.inventory_location_picking),
            subtitle = currentPicking?.let { stringResource(R.string.inventory_add_picking_hint_current, it) } ?: stringResource(R.string.inventory_add_picking_hint),
            selected = type == LocationStockType.Picking,
            onClick = { type = LocationStockType.Picking },
        )
        if (isMissing) {
            Text(text = stringResource(R.string.inventory_add_missing), fontSize = 13.sp, color = colors.danger)
        }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        SheetButtons(
            confirmLabel = picked?.let { stringResource(R.string.inventory_add_confirm, it.code) } ?: stringResource(R.string.inventory_action_add_location),
            onConfirm = {
                if (picked == null) {
                    isMissing = true
                } else {
                    onAdd(OrgStockWrite.AddLocation(locationId = picked.id, type = type))
                }
            },
            onCancel = onDismiss,
            isWorking = isWorking,
        )
    }
}
