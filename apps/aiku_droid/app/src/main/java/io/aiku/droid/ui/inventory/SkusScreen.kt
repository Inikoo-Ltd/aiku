package io.aiku.droid.ui.inventory

import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderSearchRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.SkuDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SkusScreen() {
    val can = LocalWarehouse.current.can
    val title = stringResource(R.string.inventory_title)

    RequireAbility(isAllowed = can.viewInventory, title = title, activeTab = AppTab.Inventory) {
        SkusContent(title = title)
    }
}

@Composable
private fun SkusContent(title: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    var query by remember { mutableStateOf("") }
    var selectedFilter by remember { mutableIntStateOf(0) }

    AppScreen(
        header = {
            AppHeader(
                title = title,
                kicker = warehouseKicker(),
                below = {
                    InventorySectionTabs(active = InventorySection.Skus)
                    HeaderSearchRow(
                        placeholder = stringResource(R.string.inventory_skus_search),
                        value = query,
                        onValueChange = { query = it },
                    )
                },
            )
        },
        activeTab = AppTab.Inventory,
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 16.dp),
        spacing = 10.dp,
    ) {
        FilterChips(
            options = listOf(
                FilterOption(label = stringResource(R.string.inventory_filter_all)),
                FilterOption(
                    label = stringResource(R.string.inventory_filter_with_count, stringResource(R.string.inventory_status_low), "17"),
                    dotColor = colors.warn,
                ),
                FilterOption(
                    label = stringResource(R.string.inventory_filter_with_count, stringResource(R.string.inventory_status_critical), "5"),
                    dotColor = colors.orange,
                ),
                FilterOption(
                    label = stringResource(R.string.inventory_filter_with_count, stringResource(R.string.inventory_status_out_of_stock), "3"),
                    dotColor = colors.dangerDot,
                ),
            ),
            selectedIndex = selectedFilter,
            onSelect = { selectedFilter = it },
            modifier = Modifier.fakeData(8.dp),
        )
        ListMetaRow(start = stringResource(R.string.inventory_skus_sorted)) {
            MetaText(text = stringResource(R.string.inventory_skus_columns))
        }
        ListGroup(modifier = Modifier.fakeData()) {
            InventorySkuSamples.forEachIndexed { index, sku ->
                if (index > 0) {
                    RowDivider()
                }
                StockRow(
                    title = sku.code,
                    subtitle = sku.name,
                    badges = {
                        StatusBadge(text = sku.familyCode, tone = Tone.Primary)
                        if (sku.level.isFlagged) {
                            StatusBadge(text = stringResource(sku.level.label), tone = sku.level.tone)
                        }
                    },
                    quantity = sku.stock,
                    quantityTone = Tone.Danger.takeIf { sku.level == InventoryStockLevel.OutOfStock },
                    caption = sku.unitValue,
                    onClick = { navigator.open(SkuDetailRoute(sku.code)) },
                )
            }
        }
        ListFooter(
            text = stringResource(R.string.inventory_showing, "5", "4,812"),
            modifier = Modifier.align(Alignment.CenterHorizontally).fakeData(8.dp),
        )
    }
}

@Preview(showSystemUi = true)
@Composable
private fun SkusScreenPreview() {
    AikuDroidTheme {
        SkusScreen()
    }
}
