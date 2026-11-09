package io.aiku.droid.ui.inventory

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppTabBar
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SectionTitle
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationDetailRoute
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SkuDetailScreen(code: String) {
    val can = LocalWarehouse.current.can

    RequireAbility(isAllowed = can.viewInventory, title = code, activeTab = AppTab.Inventory) {
        SkuDetailContent(code = code, canMoveStock = can.moveStock, canViewLocations = can.viewLocations)
    }
}

@Composable
private fun SkuDetailContent(code: String, canMoveStock: Boolean, canViewLocations: Boolean) {
    val navigator = LocalAppNavigator.current
    var openSheet by remember { mutableStateOf<StockSheet?>(null) }

    StockDetailScaffold(
        header = {
            StockDetailHeader(
                kicker = stringResource(R.string.inventory_sku_kicker),
                kickerIcon = R.drawable.ic_inventory_skus,
                title = code,
                tabs = listOf(
                    DetailTab(label = stringResource(R.string.inventory_sku_tab_overview), icon = R.drawable.ic_locations_overview),
                    DetailTab(label = stringResource(R.string.inventory_sku_tab_stock_history), icon = R.drawable.ic_inventory_stock_history),
                    DetailTab(label = stringResource(R.string.inventory_sku_tab_supplier_product), icon = R.drawable.ic_inventory_supplier),
                    DetailTab(label = stringResource(R.string.inventory_sku_tab_products), icon = R.drawable.ic_inventory_products),
                    DetailTab(label = stringResource(R.string.inventory_sku_tab_images), icon = R.drawable.ic_inventory_images, showLabel = false),
                ),
                moreDescription = stringResource(R.string.inventory_sku_more_actions),
                showMore = canMoveStock,
            )
        },
        bottomBar = {
            if (canMoveStock) {
                StockActionBar(
                    onScan = { navigator.open(ScanRoute) },
                    secondary = StockAction(
                        label = stringResource(R.string.inventory_sku_adjustment),
                        icon = R.drawable.ic_inventory_adjustment,
                        onClick = { openSheet = StockSheet.Adjust },
                    ),
                    primary = StockAction(
                        label = stringResource(R.string.inventory_sku_transfer),
                        icon = R.drawable.ic_locations_transfer,
                        onClick = { openSheet = StockSheet.Transfer },
                    ),
                )
            } else {
                AppTabBar(active = AppTab.Inventory)
            }
        },
    ) {
        SkuSummaryCard()
        StatStrip(
            values = listOf(
                StripValue(value = "36", label = stringResource(R.string.inventory_sku_stock)),
                StripValue(value = "2", label = stringResource(R.string.inventory_sku_locations)),
                StripValue(value = "£34", label = stringResource(R.string.inventory_sku_stock_value)),
            ),
            modifier = Modifier.fakeData(),
        )
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            SectionTitle(text = stringResource(R.string.inventory_sku_locations), icon = R.drawable.ic_section_locations)
            ListGroup(modifier = Modifier.fakeData()) {
                InventorySkuLocationSamples.forEachIndexed { index, location ->
                    if (index > 0) {
                        RowDivider()
                    }
                    StockRow(
                        title = location.code,
                        subtitle = location.detail,
                        badges = {
                            if (location.isPicking) {
                                StatusBadge(text = stringResource(R.string.inventory_sku_picking), tone = Tone.Primary)
                            }
                        },
                        quantity = location.quantity,
                        quantitySize = 18.sp,
                        verticalPadding = 14.dp,
                        onClick = if (canViewLocations) {
                            { navigator.open(LocationDetailRoute(location.code)) }
                        } else {
                            null
                        },
                    )
                }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            SectionTitle(
                text = stringResource(R.string.inventory_sku_stock_history),
                icon = R.drawable.ic_inventory_stock_history,
                actionLabel = stringResource(R.string.inventory_sku_view_all),
                onAction = {},
            )
            MovementList(movements = InventorySkuHistorySamples, modifier = Modifier.fakeData())
        }
    }
    StockSheetHost(sheet = openSheet, onDismiss = { openSheet = null })
}

@Composable
private fun SkuSummaryCard() {
    val colors = LocalAikuColors.current

    AikuCard(modifier = Modifier.fakeData()) {
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
                modifier = Modifier
                    .size(88.dp)
                    .clip(ControlShape)
                    .background(colors.background)
                    .border(1.dp, colors.border, ControlShape),
            ) {
                Icon(painter = painterResource(R.drawable.ic_inventory_images), contentDescription = null, tint = colors.textQuaternary, modifier = Modifier.size(24.dp))
                Text(text = stringResource(R.string.inventory_sku_image), fontSize = 11.sp, color = colors.textQuaternary)
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(text = "Bath bomb – rose 180g", fontSize = 16.sp, fontWeight = FontWeight.Bold, lineHeight = 22.sp, color = colors.text)
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    StatusBadge(text = stringResource(R.string.inventory_sku_family, "BB"), tone = Tone.Primary)
                    StatusBadge(text = stringResource(InventoryStockLevel.Low.label), tone = InventoryStockLevel.Low.tone)
                }
                Text(text = stringResource(R.string.inventory_sku_unit_value, "£0.95"), fontSize = 13.sp, color = colors.textTertiary)
            }
        }
    }
}

@Preview(showSystemUi = true)
@Composable
private fun SkuDetailScreenPreview() {
    AikuDroidTheme {
        SkuDetailScreen(code = "SKU-00733")
    }
}
