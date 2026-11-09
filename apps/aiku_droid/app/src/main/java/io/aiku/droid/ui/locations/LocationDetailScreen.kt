package io.aiku.droid.ui.locations

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
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
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SectionTitle
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.inventory.DetailTab
import io.aiku.droid.ui.inventory.MovementList
import io.aiku.droid.ui.inventory.StockAction
import io.aiku.droid.ui.inventory.StockActionBar
import io.aiku.droid.ui.inventory.StockDetailHeader
import io.aiku.droid.ui.inventory.StockDetailScaffold
import io.aiku.droid.ui.inventory.StockRow
import io.aiku.droid.ui.inventory.StockSheet
import io.aiku.droid.ui.inventory.StockSheetHost
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.navigation.SkuDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationDetailScreen(code: String) {
    val can = LocalWarehouse.current.can

    RequireAbility(isAllowed = can.viewLocations, title = code) {
        LocationDetailContent(
            code = code,
            canMoveStock = can.moveStock,
            canEditLocation = can.editLocations,
            canViewInventory = can.viewInventory,
        )
    }
}

@Composable
private fun LocationDetailContent(code: String, canMoveStock: Boolean, canEditLocation: Boolean, canViewInventory: Boolean) {
    val navigator = LocalAppNavigator.current
    var openSheet by remember { mutableStateOf<StockSheet?>(null) }

    StockDetailScaffold(
        header = {
            StockDetailHeader(
                kicker = stringResource(R.string.locations_detail_kicker),
                kickerIcon = R.drawable.ic_section_locations,
                title = code,
                tabs = listOf(
                    DetailTab(label = stringResource(R.string.locations_tab_overview), icon = R.drawable.ic_locations_overview),
                    DetailTab(label = stringResource(R.string.locations_tab_skus), icon = R.drawable.ic_inventory_skus),
                    DetailTab(label = stringResource(R.string.locations_tab_pallets), icon = R.drawable.ic_locations_pallet),
                    DetailTab(label = stringResource(R.string.locations_tab_movements), icon = R.drawable.ic_locations_transfer),
                    DetailTab(label = stringResource(R.string.locations_tab_history), icon = R.drawable.ic_locations_history, showLabel = false),
                ),
                moreDescription = stringResource(R.string.locations_more_actions),
                showMore = canEditLocation,
            )
        },
        bottomBar = {
            if (canMoveStock) {
                StockActionBar(
                    onScan = { navigator.open(ScanRoute) },
                    secondary = StockAction(
                        label = stringResource(R.string.locations_detail_audit),
                        icon = R.drawable.ic_locations_audit,
                        onClick = { openSheet = StockSheet.Audit },
                    ),
                    primary = StockAction(
                        label = stringResource(R.string.locations_detail_transfer),
                        icon = R.drawable.ic_locations_transfer,
                        onClick = { openSheet = StockSheet.Transfer },
                    ),
                )
            } else {
                AppTabBar(active = null)
            }
        },
    ) {
        LocationDetailsCard()
        Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fakeData()) {
            SectionTitle(
                text = stringResource(R.string.locations_detail_skus, "6"),
                icon = R.drawable.ic_inventory_skus,
                actionLabel = stringResource(R.string.locations_detail_view_all),
                onAction = {},
            )
            ListGroup {
                LocationsSkuSamples.forEachIndexed { index, sku ->
                    if (index > 0) {
                        RowDivider()
                    }
                    StockRow(
                        title = sku.code,
                        subtitle = sku.name,
                        quantity = sku.quantity,
                        quantitySize = 18.sp,
                        onClick = if (canViewInventory) {
                            { navigator.open(SkuDetailRoute(sku.code)) }
                        } else {
                            null
                        },
                    )
                }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            SectionTitle(text = stringResource(R.string.locations_detail_movements), icon = R.drawable.ic_locations_transfer)
            MovementList(movements = LocationsMovementSamples, modifier = Modifier.fakeData())
        }
    }
    StockSheetHost(sheet = openSheet, onDismiss = { openSheet = null })
}

@Composable
private fun LocationDetailsCard() {
    val colors = LocalAikuColors.current

    AikuCard(modifier = Modifier.fakeData()) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            DetailBadge(text = stringResource(R.string.locations_status_operational), tone = Tone.Success) {
                Box(modifier = Modifier.size(6.dp).clip(RoundedCornerShape(999.dp)).background(colors.success))
            }
            DetailBadge(text = stringResource(R.string.locations_legend_stocks), tone = Tone.Primary) {
                Icon(painter = painterResource(R.drawable.ic_locations_stocks), contentDescription = null, tint = colors.link, modifier = Modifier.size(12.dp))
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            DetailValue(label = stringResource(R.string.locations_detail_area), value = "Area A", icon = R.drawable.ic_locations_areas, modifier = Modifier.weight(1f))
            DetailValue(label = stringResource(R.string.locations_detail_stock_value), value = "£2,140", modifier = Modifier.weight(1f))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            DetailValue(label = stringResource(R.string.locations_detail_weight), value = "120 / 250 kg", modifier = Modifier.weight(1f))
            DetailValue(label = stringResource(R.string.locations_detail_cbm), value = "0.8 / 1.2", modifier = Modifier.weight(1f))
        }
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Row(modifier = Modifier.fillMaxWidth()) {
                Text(text = stringResource(R.string.locations_detail_capacity), fontSize = 12.sp, color = colors.textTertiary, modifier = Modifier.weight(1f))
                Text(text = "48%", fontSize = 12.sp, color = colors.textTertiary)
            }
            ProgressBar(fraction = 0.48f, height = 8.dp)
        }
    }
}

@Composable
private fun DetailBadge(text: String, tone: Tone, leading: @Composable RowScope.() -> Unit) {
    val toneColors = LocalAikuColors.current.tone(tone)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(5.dp),
        modifier = Modifier
            .clip(ChipShape)
            .background(toneColors.container)
            .padding(horizontal = 8.dp, vertical = 3.dp),
    ) {
        leading()
        Text(text = text, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = toneColors.content)
    }
}

@Composable
private fun DetailValue(label: String, value: String, modifier: Modifier = Modifier, @DrawableRes icon: Int? = null) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(2.dp), modifier = modifier) {
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1)
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            if (icon != null) {
                Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(14.dp))
            }
            Text(text = value, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
        }
    }
}

@Preview(showSystemUi = true)
@Composable
private fun LocationDetailScreenPreview() {
    AikuDroidTheme {
        LocationDetailScreen(code = "A-03-02")
    }
}
