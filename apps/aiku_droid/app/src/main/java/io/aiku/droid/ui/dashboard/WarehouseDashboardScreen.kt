package io.aiku.droid.ui.dashboard

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.moduleKicker
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.GoodsInBacklogRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private data class ModuleShortcut(@StringRes val label: Int, @DrawableRes val icon: Int, val route: Any)

internal data class StateCount(val value: String, val label: String, val tone: Tone)

@Composable
fun WarehouseDashboardScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val warehouse = LocalWarehouse.current
    val can = warehouse.can
    val shortcuts = listOfNotNull(
        ModuleShortcut(R.string.module_inventory, R.drawable.ic_ui_sko, SkusRoute).takeIf { can.viewInventory },
        ModuleShortcut(R.string.module_locations, R.drawable.ic_ui_location, LocationsRoute).takeIf { can.viewLocations },
        ModuleShortcut(R.string.module_goods_in, R.drawable.ic_ui_order, StockDeliveriesRoute).takeIf { can.viewGoodsIn },
        ModuleShortcut(R.string.module_goods_out, R.drawable.ic_ui_truck, DeliveryNotesRoute()).takeIf { can.viewGoodsOut },
        ModuleShortcut(R.string.dashboard_clocking, R.drawable.ic_ui_clock, ClockingRoute),
    )

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.section_dashboard),
                kicker = moduleKicker(R.string.home_module_warehouse),
                actions = {
                    HeaderIconButton(icon = R.drawable.ic_ui_search, description = stringResource(R.string.search_title), onClick = { navigator.open(SearchRoute) }, filled = true)
                },
            )
        },
        activeTab = MainTab.Menu,
    ) {
        ListGroup {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 60.dp)
                    .clickable(role = Role.Button) { navigator.showWarehouseSwitcher() }
                    .padding(horizontal = 14.dp, vertical = 10.dp),
            ) {
                Box(contentAlignment = Alignment.Center, modifier = Modifier.size(40.dp).clip(RoundedCornerShape(10.dp)).background(colors.soft)) {
                    Icon(painter = painterResource(R.drawable.ic_ui_area), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
                }
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                    Text(
                        text = listOf(warehouse.organisation.name, warehouse.name).filter { it.isNotBlank() }.joinToString(" · "),
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = colors.text,
                    )
                    Text(text = stringResource(R.string.dashboard_tap_to_change, warehouse.code), fontSize = 13.sp, color = colors.textTertiary)
                }
                Text(text = stringResource(R.string.menu_change), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            shortcuts.forEach { shortcut ->
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(10.dp))
                        .background(colors.surface)
                        .border(1.dp, colors.border, RoundedCornerShape(10.dp))
                        .clickable(role = Role.Button) { navigator.open(shortcut.route) }
                        .padding(horizontal = 2.dp, vertical = 10.dp),
                ) {
                    Icon(painter = painterResource(shortcut.icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
                    Text(text = stringResource(shortcut.label), fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary, maxLines = 1)
                }
            }
        }
        if (can.viewGoodsOut) {
            BacklogCard(
                title = stringResource(R.string.module_goods_out),
                subtitle = stringResource(R.string.dashboard_dispatching_backlog),
                counts = DashboardSamples.goodsOut,
                onView = { navigator.open(GoodsOutBacklogRoute) },
            ) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(text = stringResource(R.string.dashboard_dispatched_today), fontSize = 12.sp, color = colors.textTertiary)
                    Text(text = DashboardSamples.DISPATCHED_TODAY, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.text)
                }
                ProgressBar(fraction = DashboardSamples.DISPATCHED_FRACTION)
            }
        }
        if (can.viewGoodsIn) {
            BacklogCard(
                title = stringResource(R.string.module_goods_in),
                subtitle = stringResource(R.string.dashboard_incoming_hub),
                counts = DashboardSamples.goodsIn,
                onView = { navigator.open(GoodsInBacklogRoute) },
            )
        }
        if (can.viewInventory) {
            BacklogCard(
                title = stringResource(R.string.module_inventory),
                subtitle = DashboardSamples.SKOS_IN_WAREHOUSE,
                counts = DashboardSamples.inventory,
                onView = { navigator.open(InventoryDashboardRoute) },
            )
            Row(verticalAlignment = Alignment.Bottom, modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
                GroupLabel(text = stringResource(R.string.dashboard_stock_history_today), modifier = Modifier.weight(1f))
                Text(
                    text = stringResource(R.string.dashboard_all),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.link,
                    modifier = Modifier.clip(ChipShape).clickable(role = Role.Button) { navigator.open(StockHistoryRoute) }.padding(4.dp),
                )
            }
            ListGroup(modifier = Modifier.fakeData()) {
                DashboardSamples.movements.forEachIndexed { index, movement ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(code = movement.code, subtitle = movement.subtitle, chip = movement.chip, chipTone = movement.tone)
                }
            }
        }
        Text(
            text = stringResource(R.string.dashboard_my_work_note),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}

@Composable
private fun BacklogCard(
    title: String,
    subtitle: String,
    counts: List<StateCount>,
    onView: () -> Unit,
    extra: @Composable ColumnScope.() -> Unit = {},
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .fakeData()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(14.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth().clickable(role = Role.Button, onClick = onView),
        ) {
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = subtitle, fontSize = 12.sp, color = colors.textTertiary)
            }
            Text(text = stringResource(R.string.dashboard_view), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            counts.forEach { count ->
                val tone = colors.tone(count.tone)
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(2.dp),
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(8.dp))
                        .background(if (count.tone == Tone.Neutral) colors.background else tone.container)
                        .padding(horizontal = 2.dp, vertical = 8.dp),
                ) {
                    Text(text = count.value, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = if (count.tone == Tone.Neutral) colors.textSecondary else tone.content)
                    Text(text = count.label, fontSize = 10.sp, fontWeight = FontWeight.SemiBold, color = colors.textTertiary, maxLines = 1)
                }
            }
        }
        extra()
    }
}
