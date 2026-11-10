package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class StockSheet { Audit, Move, Consume, AddLocation, RemoveLocations }

private data class StockAction(val label: String, @DrawableRes val icon: Int, val sheet: StockSheet)

private const val MIN_LOCATIONS_TO_MOVE = 2

@Composable
fun SkuScreen(code: String) {
    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        SkuContent(code = code)
    }
}

@Composable
private fun SkuContent(code: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val sko = sampleSkoByCode(code)
    val detail = sampleSkoDetail
    val state = if (sko.badge == SkoBadge.Abnormality) R.string.inventory_state_abnormality else R.string.inventory_state_active
    var openSheet by remember { mutableStateOf<StockSheet?>(null) }

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = code,
                kicker = stringResource(R.string.inventory_sko_kicker, stringResource(state)),
                actions = {
                    HeaderIconButton(
                        icon = R.drawable.ic_ui_clock,
                        description = stringResource(R.string.section_stock_history),
                        onClick = { navigator.open(StockHistoryRoute) },
                        filled = true,
                    )
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        SkuSummary(code = code, sko = sko, isOnDemand = detail.isOnDemand)
        DetailCard(
            title = stringResource(R.string.inventory_sko_card_title),
            lines = listOf(
                DetailLine(icon = R.drawable.ic_ui_sko, text = pluralStringResource(R.plurals.inventory_packed_in, detail.unitsPerSko, detail.unitsPerSko)),
                DetailLine(icon = R.drawable.ic_ui_scan, text = stringResource(R.string.inventory_barcodes, detail.skoBarcode, detail.unitEan)),
                DetailLine(icon = R.drawable.ic_ui_edit, text = stringResource(R.string.inventory_picker_note, detail.pickerNote)),
            ),
            modifier = Modifier.fakeData(),
        )
        InventoryStatGrid(
            stats = listOf(
                InventoryStat(value = detail.inLocations.toString(), label = stringResource(R.string.inventory_stat_in_locations)),
                InventoryStat(value = detail.reserved.toString(), label = stringResource(R.string.inventory_stat_reserved)),
                InventoryStat(value = detail.toBePicked.toString(), label = stringResource(R.string.inventory_stat_to_be_picked)),
                InventoryStat(
                    value = pluralStringResource(R.plurals.inventory_cover_days, detail.coverDays, detail.coverDays),
                    label = stringResource(R.string.inventory_stat_cover),
                ),
            ),
            columns = 4,
            modifier = Modifier.fakeData(10.dp),
        )
        InventorySectionHeading(
            text = stringResource(R.string.inventory_locations_heading),
            trailingText = detail.locations.size.toString(),
            trailingModifier = Modifier.fakeData(4.dp).padding(horizontal = 4.dp),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            detail.locations.forEachIndexed { index, location ->
                if (index > 0) RowDivider()
                IconItemRow(
                    icon = R.drawable.ic_ui_location,
                    title = location.code,
                    subtitle = locationSubtitle(location),
                    chip = locationChip(location),
                    chipTone = location.kind.tone,
                    trailing = location.stock.toString(),
                    onClick = if (can.viewLocations) {
                        { navigator.open(LocationSkuRoute(locationCode = location.code, skuCode = code)) }
                    } else {
                        null
                    },
                )
            }
        }
        if (can.moveStock) {
            StockActions(
                canMove = detail.locations.size >= MIN_LOCATIONS_TO_MOVE,
                onOpen = { openSheet = it },
            )
        }
    }

    when (openSheet) {
        StockSheet.Audit -> AuditStockSheet(skuCode = code, onDismiss = { openSheet = null })
        StockSheet.Move -> MoveStockSheet(skuCode = code, onDismiss = { openSheet = null })
        StockSheet.Consume -> ConsumeStockSheet(skuCode = code, onDismiss = { openSheet = null })
        StockSheet.AddLocation -> AddLocationSheet(skuCode = code, onDismiss = { openSheet = null })
        StockSheet.RemoveLocations -> RemoveLocationsSheet(skuCode = code, onDismiss = { openSheet = null })
        null -> Unit
    }
}

@Composable
private fun SkuSummary(code: String, sko: SampleSko, isOnDemand: Boolean) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth().fakeData(8.dp),
    ) {
        ProductPhoto(code = code, size = 96.dp)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(text = sko.name, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                StatusBadge(text = stringResource(sko.badge.label), tone = sko.badge.tone)
                if (isOnDemand) {
                    StatusBadge(text = stringResource(R.string.inventory_on_demand), tone = Tone.Primary)
                }
            }
        }
    }
}

@Composable
private fun StockActions(canMove: Boolean, onOpen: (StockSheet) -> Unit) {
    val actions = listOfNotNull(
        StockAction(stringResource(R.string.inventory_action_audit), R.drawable.ic_ui_check, StockSheet.Audit),
        if (canMove) StockAction(stringResource(R.string.inventory_action_move), R.drawable.ic_ui_location, StockSheet.Move) else null,
        StockAction(stringResource(R.string.inventory_action_consume), R.drawable.ic_ui_sko, StockSheet.Consume),
        StockAction(stringResource(R.string.inventory_action_add_location), R.drawable.ic_ui_location, StockSheet.AddLocation),
    )

    InventorySectionHeading(text = stringResource(R.string.inventory_stock_actions))
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        actions.chunked(2).forEach { rowActions ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                rowActions.forEach { action ->
                    StockActionButton(
                        text = action.label,
                        icon = action.icon,
                        onClick = { onOpen(action.sheet) },
                        modifier = Modifier.weight(1f),
                    )
                }
                if (rowActions.size == 1) {
                    Spacer(modifier = Modifier.weight(1f))
                }
            }
        }
    }
    StockActionButton(
        text = stringResource(R.string.inventory_action_remove_locations),
        onClick = { onOpen(StockSheet.RemoveLocations) },
        isDanger = true,
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun locationSubtitle(location: SampleSkoLocation): String {
    val minStock = location.minStock
    val maxStock = location.maxStock
    val replenish = location.replenish
    val levels = when {
        minStock != null && maxStock != null -> stringResource(R.string.inventory_min_max, minStock, maxStock)
        replenish != null -> stringResource(R.string.inventory_replenish, replenish)
        else -> null
    }
    val lastAudit = location.lastAudit
    val audit = if (lastAudit != null) stringResource(R.string.inventory_audited_on, lastAudit) else stringResource(R.string.inventory_never_audited)
    return listOfNotNull(levels, audit).joinToString(" · ")
}
