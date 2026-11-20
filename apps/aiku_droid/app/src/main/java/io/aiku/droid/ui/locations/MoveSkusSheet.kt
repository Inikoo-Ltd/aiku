package io.aiku.droid.ui.locations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.MoveSomeItem
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.inventory.DecimalStepper
import io.aiku.droid.ui.inventory.LocationFinder
import io.aiku.droid.ui.inventory.LocationPicker
import io.aiku.droid.ui.inventory.PickedLocation
import io.aiku.droid.ui.inventory.knownTargets
import io.aiku.droid.ui.inventory.SheetButtons
import io.aiku.droid.ui.inventory.SheetGroupLabel
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun MoveSkusSheet(
    detail: LocationDetail,
    moveAll: Boolean,
    onlyLinkId: Long?,
    finder: LocationFinder,
    isOnline: Boolean,
    isWorking: Boolean,
    errorText: String?,
    onMove: (LocationWrite) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val finderState by finder.state.collectAsStateWithLifecycle()
    val skos = detail.orgStocks.filter { onlyLinkId == null || it.locationOrgStockId == onlyLinkId }
    var quantities by remember { mutableStateOf(skos.associate { it.locationOrgStockId to if (onlyLinkId != null) it.stock else 0.0 }) }
    var removals by remember { mutableStateOf(emptySet<Long>()) }
    var removeAll by remember { mutableStateOf(false) }
    var missing by remember { mutableStateOf<Int?>(null) }
    var offlineTarget by remember { mutableStateOf<PickedLocation?>(null) }
    val knownTargets = detail.knownTargets()
    val target = if (isOnline) finderState.picked else offlineTarget
    val moving = skos.filter { (quantities[it.locationOrgStockId] ?: 0.0) > 0.0 }

    LaunchedEffect(finder) { finder.reset() }

    AikuBottomSheet(
        title = stringResource(if (moveAll) R.string.locations_move_all_title else R.string.locations_move_some_title, detail.code),
        subtitle = stringResource(if (moveAll) R.string.locations_move_all_subtitle else R.string.locations_move_some_subtitle),
        onDismiss = onDismiss,
    ) {
        SheetGroupLabel(text = stringResource(R.string.locations_move_target_label))
        if (isOnline) {
            LocationPicker(
                state = finderState,
                placeholder = stringResource(R.string.locations_move_target),
                onQueryChange = finder::search,
                onPick = { id -> finderState.results.firstOrNull { it.id == id }?.let(finder::pick) },
                onScanned = finder::scan,
            )
        } else if (knownTargets.isEmpty()) {
            InlineAlert(title = stringResource(R.string.offline_stock_another_location_offline), tone = AlertTone.Warn)
        } else {
            Text(text = stringResource(R.string.offline_stock_move_targets_offline), fontSize = 13.sp, color = colors.textTertiary)
            knownTargets.forEach { place ->
                ChoiceRow(
                    title = place.code,
                    selected = offlineTarget?.id == place.id,
                    onClick = { offlineTarget = PickedLocation(id = place.id, code = place.code) },
                )
            }
        }
        if (moveAll && isOnline) {
            TrailingCheckboxRow(
                label = stringResource(R.string.locations_move_remove_source, detail.code),
                checked = removeAll,
                onCheckedChange = { removeAll = it },
                labelSize = 14.sp,
            )
        } else if (!moveAll) {
            SheetGroupLabel(text = stringResource(R.string.locations_move_quantities))
            ListGroup {
                skos.forEachIndexed { index, sko ->
                    if (index > 0) {
                        RowDivider()
                    }
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
                        Text(text = sko.code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                        Text(text = stringResource(R.string.locations_in_location, stockText(sko.stock)), fontSize = 13.sp, color = colors.textTertiary)
                        DecimalStepper(
                            value = quantities[sko.locationOrgStockId] ?: 0.0,
                            onValueChange = { value -> quantities = quantities + (sko.locationOrgStockId to value) },
                            max = sko.stock.coerceAtLeast(0.0),
                        )
                        if (isOnline) {
                            TrailingCheckboxRow(
                                label = stringResource(R.string.locations_move_remove_after, detail.code),
                                checked = sko.locationOrgStockId in removals,
                                onCheckedChange = { checked -> removals = if (checked) removals + sko.locationOrgStockId else removals - sko.locationOrgStockId },
                                labelSize = 14.sp,
                            )
                        }
                    }
                }
            }
        }
        missing?.let { Text(text = stringResource(it), fontSize = 13.sp, color = colors.danger) }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        SheetButtons(
            confirmLabel = when {
                moveAll && target != null -> stringResource(R.string.locations_move_confirm, target.code)
                moveAll -> stringResource(R.string.locations_move_all)
                target != null -> pluralStringResource(R.plurals.locations_move_some_confirm, moving.size, moving.size, target.code)
                else -> stringResource(R.string.locations_move_some)
            },
            onConfirm = {
                when {
                    target == null -> missing = R.string.locations_move_target_missing
                    moveAll -> onMove(LocationWrite.MoveAll(targetLocationId = target.id, removeAfterMove = removeAll && isOnline))
                    moving.isEmpty() -> missing = R.string.locations_move_quantity_missing
                    else -> onMove(
                        LocationWrite.MoveSome(
                            targetLocationId = target.id,
                            items = moving.map { sko ->
                                MoveSomeItem(
                                    orgStockId = sko.id,
                                    quantity = quantities[sko.locationOrgStockId] ?: 0.0,
                                    removeAfterMove = isOnline && sko.locationOrgStockId in removals,
                                )
                            },
                        ),
                    )
                }
            },
            onCancel = onDismiss,
            isWorking = isWorking,
        )
    }
}
