package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StockHistoryRoute

@Composable
fun InventoryDashboardScreen() {
    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        InventoryDashboardContent()
    }
}

@Composable
private fun InventoryDashboardContent() {
    val navigator = LocalAppNavigator.current
    val openSkos = { navigator.open(SkusRoute) }

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.InventoryDashboard,
                title = stringResource(R.string.inventory_dashboard_title),
                actions = {
                    HeaderIconButton(
                        icon = R.drawable.ic_ui_scan,
                        description = stringResource(R.string.inventory_manage_barcodes),
                        onClick = { navigator.open(ScanCameraRoute) },
                        filled = true,
                    )
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        InventoryStatGrid(
            stats = sampleDashboardHeadlineTiles.map { tile -> dashboardStat(tile = tile, openSkos = openSkos) },
            columns = 2,
            modifier = Modifier.fakeData(10.dp),
        )
        InventoryStatGrid(
            stats = sampleDashboardTiles.map { tile -> dashboardStat(tile = tile, openSkos = openSkos) },
            columns = 2,
            modifier = Modifier.fakeData(10.dp),
        )
        DetailCard(
            title = warehouseKicker(),
            lines = listOf(
                DetailLine(icon = R.drawable.ic_ui_sko, text = stringResource(R.string.inventory_stock_value_line, SAMPLE_STOCK_VALUE)),
                DetailLine(
                    icon = R.drawable.ic_ui_location,
                    text = pluralStringResource(
                        R.plurals.inventory_location_utilisation,
                        SAMPLE_LOCATIONS_TOTAL,
                        SAMPLE_UTILISATION_PERCENT,
                        SAMPLE_LOCATIONS_USED,
                        SAMPLE_LOCATIONS_TOTAL,
                    ),
                ),
            ),
            modifier = Modifier.fakeData(),
        )
        InventorySectionHeading(
            text = stringResource(R.string.inventory_history_today),
            actionLabel = stringResource(R.string.inventory_all),
            onAction = { navigator.open(StockHistoryRoute) },
        )
        ListGroup(modifier = Modifier.fakeData()) {
            sampleTodayMovements.forEachIndexed { index, movement ->
                if (index > 0) RowDivider()
                ItemRow(
                    code = movement.skoCode,
                    subtitle = movement.detail,
                    chip = stringResource(R.string.inventory_movement_chip, stringResource(movement.type.label), movement.quantity),
                    chipTone = movement.tone,
                )
            }
        }
    }
}

@Composable
private fun dashboardStat(tile: SampleDashboardTile, openSkos: () -> Unit): InventoryStat {
    val labelArgument = tile.labelArgument
    val label = if (labelArgument != null) stringResource(tile.label, labelArgument) else stringResource(tile.label)
    return InventoryStat(
        value = tile.value.toString(),
        label = label,
        tone = tile.tone,
        onClick = if (tile.opensSkos) openSkos else null,
    )
}
