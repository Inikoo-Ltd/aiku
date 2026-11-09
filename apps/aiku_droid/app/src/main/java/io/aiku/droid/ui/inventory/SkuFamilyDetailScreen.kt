package io.aiku.droid.ui.inventory

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SectionTitle
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.SkuDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SkuFamilyDetailScreen(code: String) {
    val can = LocalWarehouse.current.can
    val familyName = InventoryFamilySamples.firstOrNull { it.code == code }?.name
    val title = listOfNotNull(code, familyName).joinToString(" · ")

    RequireAbility(isAllowed = can.viewInventory, title = title, activeTab = AppTab.Inventory) {
        SkuFamilyDetailContent(title = title)
    }
}

@Composable
private fun SkuFamilyDetailContent(title: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    var selectedFilter by remember { mutableIntStateOf(0) }

    AppScreen(
        header = {
            AppHeader(
                title = title,
                kicker = stringResource(R.string.inventory_family_kicker),
                showMenu = false,
            )
        },
        activeTab = AppTab.Inventory,
    ) {
        StatStrip(
            values = listOf(
                StripValue(value = "42", label = stringResource(R.string.inventory_family_skus)),
                StripValue(value = "4,130", label = stringResource(R.string.inventory_family_units)),
                StripValue(value = "£3.9k", label = stringResource(R.string.inventory_family_stock_value)),
            ),
            modifier = Modifier.fakeData(),
        )
        FilterChips(
            options = listOf(
                FilterOption(label = stringResource(R.string.inventory_filter_with_count, stringResource(R.string.inventory_filter_all), "42")),
                FilterOption(
                    label = stringResource(R.string.inventory_filter_with_count, stringResource(R.string.inventory_status_low), "2"),
                    dotColor = colors.warn,
                ),
            ),
            selectedIndex = selectedFilter,
            onSelect = { selectedFilter = it },
            modifier = Modifier.fakeData(8.dp),
        )
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            SectionTitle(text = stringResource(R.string.inventory_family_skus), icon = R.drawable.ic_inventory_skus)
            ListGroup(modifier = Modifier.fakeData()) {
                InventoryFamilySkuSamples.forEachIndexed { index, sku ->
                    if (index > 0) {
                        RowDivider()
                    }
                    StockRow(
                        title = sku.code,
                        subtitle = sku.name,
                        badges = {
                            if (sku.level.isFlagged) {
                                StatusBadge(text = stringResource(sku.level.label), tone = sku.level.tone)
                            }
                        },
                        quantity = sku.stock,
                        showChevron = false,
                        onClick = { navigator.open(SkuDetailRoute(sku.code)) },
                    )
                }
            }
        }
    }
}

@Preview(showSystemUi = true)
@Composable
private fun SkuFamilyDetailScreenPreview() {
    AikuDroidTheme {
        SkuFamilyDetailScreen(code = "BB")
    }
}
