package io.aiku.droid.ui.inventory

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
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
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun InventoryDashboardScreen() {
    val warehouse = LocalWarehouse.current
    val title = stringResource(R.string.inventory_dashboard_title)

    RequireAbility(isAllowed = warehouse.can.viewInventory, title = title, activeTab = AppTab.Inventory) {
        AppScreen(
            header = {
                AppHeader(
                    title = title,
                    kicker = stringResource(R.string.inventory_kicker, warehouse.name),
                    below = { InventorySectionTabs(active = InventorySection.Dashboard) },
                )
            },
            activeTab = AppTab.Inventory,
        ) {
            TotalsRow()
            StockStatusCard()
            MovementsTodayCard()
            NeedsAttentionSection()
        }
    }
}

@Composable
private fun TotalsRow() {
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
        TotalCard(
            label = stringResource(R.string.inventory_dashboard_skus),
            value = "4,812",
            caption = stringResource(R.string.inventory_dashboard_in_families, "186"),
            modifier = Modifier.weight(1f),
        )
        TotalCard(
            label = stringResource(R.string.inventory_dashboard_stock_value),
            value = "£182k",
            caption = stringResource(R.string.inventory_dashboard_at_unit_value),
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun TotalCard(label: String, value: String, caption: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = modifier
            .fakeData()
            .fillMaxHeight()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(14.dp),
    ) {
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1)
        Text(text = value, fontSize = 26.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1)
        Text(text = caption, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1)
    }
}

private fun AikuColors.levelColor(level: InventoryStockLevel): Color = when (level) {
    InventoryStockLevel.Excess -> link
    InventoryStockLevel.Ideal -> success
    InventoryStockLevel.Low -> warn
    InventoryStockLevel.Critical -> orange
    InventoryStockLevel.OutOfStock -> dangerDot
}

@Composable
private fun StockStatusCard() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val pill = RoundedCornerShape(999.dp)

    AikuCard(modifier = Modifier.fakeData()) {
        Text(
            text = stringResource(R.string.inventory_dashboard_stock_status),
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
        )
        Row(
            horizontalArrangement = Arrangement.spacedBy(2.dp),
            modifier = Modifier.fillMaxWidth().height(12.dp).clip(pill),
        ) {
            InventoryLevelCounts.forEach { entry ->
                Box(modifier = Modifier.weight(entry.share).fillMaxHeight().background(colors.levelColor(entry.level)))
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            InventoryLevelCounts.chunked(2).forEach { pair ->
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
                    pair.forEach { entry ->
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            modifier = Modifier
                                .weight(1f)
                                .heightIn(min = 32.dp)
                                .clip(ControlShape)
                                .clickable(role = Role.Button, onClick = { navigator.open(SkusRoute) }),
                        ) {
                            Box(modifier = Modifier.size(10.dp).clip(RoundedCornerShape(3.dp)).background(colors.levelColor(entry.level)))
                            Text(text = stringResource(entry.level.label), fontSize = 13.sp, color = colors.text, modifier = Modifier.weight(1f))
                            Text(text = entry.count, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = colors.text)
                        }
                    }
                    if (pair.size == 1) {
                        Box(modifier = Modifier.weight(1f))
                    }
                }
            }
        }
    }
}

@Composable
private fun MovementsTodayCard() {
    val colors = LocalAikuColors.current

    AikuCard(modifier = Modifier.fakeData()) {
        Text(
            text = stringResource(R.string.inventory_dashboard_movements_today),
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
        )
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            MovementTile(
                value = "312",
                label = stringResource(R.string.inventory_dashboard_movement_in),
                valueColor = colors.success,
                container = colors.successSoft,
                modifier = Modifier.weight(1f),
            )
            MovementTile(
                value = "540",
                label = stringResource(R.string.inventory_dashboard_movement_out),
                valueColor = colors.link,
                container = colors.soft,
                modifier = Modifier.weight(1f),
            )
            MovementTile(
                value = "28",
                label = stringResource(R.string.inventory_dashboard_movement_transfers),
                valueColor = colors.text,
                container = colors.background,
                modifier = Modifier.weight(1f),
            )
            MovementTile(
                value = "4",
                label = stringResource(R.string.inventory_dashboard_movement_adjustments),
                valueColor = colors.warn,
                container = colors.warnSoft,
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun MovementTile(value: String, label: String, valueColor: Color, container: Color, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(ControlShape)
            .background(container)
            .padding(horizontal = 4.dp, vertical = 10.dp),
    ) {
        Text(text = value, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = valueColor, maxLines = 1)
        Text(text = label, fontSize = 11.sp, color = colors.textSecondary, maxLines = 1, textAlign = TextAlign.Center)
    }
}

@Composable
private fun NeedsAttentionSection() {
    val navigator = LocalAppNavigator.current

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        SectionTitle(text = stringResource(R.string.inventory_dashboard_needs_attention))
        ListGroup(modifier = Modifier.fakeData()) {
            InventoryAttentionSamples.forEachIndexed { index, sku ->
                if (index > 0) {
                    RowDivider()
                }
                StockRow(
                    title = sku.code,
                    subtitle = sku.detail,
                    trailing = { StatusBadge(text = stringResource(sku.level.label), tone = sku.level.tone) },
                    showChevron = false,
                    onClick = { navigator.open(SkuDetailRoute(sku.code)) },
                )
            }
        }
    }
}

@Preview(showSystemUi = true)
@Composable
private fun InventoryDashboardScreenPreview() {
    AikuDroidTheme {
        InventoryDashboardScreen()
    }
}
