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
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshDefaults
import androidx.compose.material3.pulltorefresh.pullToRefresh
import androidx.compose.material3.pulltorefresh.rememberPullToRefreshState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.DashboardGoodsIn
import io.aiku.droid.data.api.DashboardGoodsOut
import io.aiku.droid.data.api.DashboardInventory
import io.aiku.droid.data.api.DispatchedToday
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.LastUpdatedNote
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.goodsInEntry
import io.aiku.droid.ui.components.moduleKicker
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.inventory.countText
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
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private data class ModuleShortcut(@StringRes val label: Int, @DrawableRes val icon: Int, val route: Any)

@Composable
fun WarehouseDashboardScreen(dashboard: WarehouseDashboardViewModel) {
    val state by dashboard.state.collectAsStateWithLifecycle()

    key(dashboard) {
        LifecycleEventEffect(Lifecycle.Event.ON_START) { dashboard.onStart() }
    }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = dashboard::signInAgain)
        return
    }

    WarehouseDashboardContent(state = state, onRefresh = dashboard::refresh, onRetry = dashboard::retry)
}

@Composable
private fun WarehouseDashboardContent(state: WarehouseDashboardUiState, onRefresh: () -> Unit, onRetry: () -> Unit) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val warehouse = LocalWarehouse.current
    val can = warehouse.can
    val refreshState = rememberPullToRefreshState()
    var headerHeight by remember { mutableIntStateOf(0) }
    val shortcuts = listOfNotNull(
        ModuleShortcut(R.string.module_inventory, R.drawable.ic_ui_sko, SkusRoute()).takeIf { can.viewInventory },
        ModuleShortcut(R.string.module_locations, R.drawable.ic_ui_location, LocationsRoute()).takeIf { can.viewLocations },
        goodsInEntry(can)?.let { ModuleShortcut(R.string.module_goods_in, R.drawable.ic_ui_order, it) },
        ModuleShortcut(R.string.module_goods_out, R.drawable.ic_ui_truck, DeliveryNotesRoute()).takeIf { can.viewGoodsOut },
        ModuleShortcut(R.string.dashboard_clocking, R.drawable.ic_ui_clock, ClockingRoute),
    )
    val figures = state.dashboard

    Box(modifier = Modifier.fillMaxSize().pullToRefresh(isRefreshing = state.isRefreshing, state = refreshState, onRefresh = onRefresh)) {
        AppScreen(
            header = {
                AppHeader(
                    title = stringResource(R.string.section_dashboard),
                    kicker = moduleKicker(R.string.home_module_warehouse),
                    modifier = Modifier.onSizeChanged { headerHeight = it.height },
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
            state.problem?.let { problem ->
                GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.dashboard_failed), onRetry = onRetry)
            }
            state.lastUpdated?.let { updatedAt -> LastUpdatedNote(updatedAt = updatedAt) }
            if (state.isLoading) {
                SkeletonListCard(rows = 2)
                SkeletonListCard(rows = 2)
            }
            figures?.goodsOut?.let { goodsOut -> GoodsOutBlock(goodsOut = goodsOut) }
            figures?.goodsIn?.let { goodsIn -> GoodsInBlock(goodsIn = goodsIn) }
            figures?.inventory?.let { inventory -> InventoryBlock(inventory = inventory) }
            Text(
                text = stringResource(R.string.dashboard_my_work_note),
                fontSize = 12.sp,
                lineHeight = 18.sp,
                color = colors.textQuaternary,
                modifier = Modifier.padding(horizontal = 4.dp),
            )
        }
        PullToRefreshDefaults.Indicator(
            state = refreshState,
            isRefreshing = state.isRefreshing,
            containerColor = colors.surface,
            color = colors.primary,
            modifier = Modifier.align(Alignment.TopCenter).offset { IntOffset(0, headerHeight) },
        )
    }
}

@Composable
private fun GoodsOutBlock(goodsOut: DashboardGoodsOut) {
    val navigator = LocalAppNavigator.current

    BacklogCard(
        title = stringResource(R.string.module_goods_out),
        subtitle = stringResource(R.string.dashboard_in_warehouse, countText(goodsOut.inWarehouse)),
        tiles = goodsOut.tiles(),
        onView = { navigator.open(GoodsOutBacklogRoute) },
    ) {
        goodsOut.dispatchedToday?.let { dispatched ->
            FigureLine(label = stringResource(R.string.dashboard_dispatched), value = dispatchedText(dispatched))
        }
    }
}

@Composable
private fun GoodsInBlock(goodsIn: DashboardGoodsIn) {
    val navigator = LocalAppNavigator.current

    BacklogCard(
        title = stringResource(R.string.module_goods_in),
        subtitle = stringResource(R.string.dashboard_incoming_hub),
        tiles = goodsIn.tiles(),
        onView = { navigator.open(GoodsInBacklogRoute) },
    ) {
        goodsIn.returnsToProcess?.let { returns ->
            FigureLine(label = stringResource(R.string.dashboard_returns_to_process), value = countText(returns), onClick = { navigator.open(returnsToProcessRoute) })
        }
    }
}

@Composable
private fun InventoryBlock(inventory: DashboardInventory) {
    val navigator = LocalAppNavigator.current

    BacklogCard(
        title = stringResource(R.string.module_inventory),
        subtitle = pluralStringResource(R.plurals.dashboard_current_skos, inventory.numberCurrentOrgStocks, countText(inventory.numberCurrentOrgStocks)),
        tiles = inventory.tiles(),
        onView = { navigator.open(InventoryDashboardRoute) },
    )
}

@Composable
private fun dispatchedText(dispatched: DispatchedToday): String {
    val today = countText(dispatched.count)
    val yesterday = dispatched.yesterdayByNow
    return if (yesterday == null) {
        stringResource(R.string.dashboard_dispatched_count, today)
    } else {
        stringResource(R.string.dashboard_dispatched_compared, today, countText(yesterday))
    }
}

@Composable
private fun FigureLine(label: String, value: String, onClick: (() -> Unit)? = null) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
        modifier = Modifier
            .fillMaxWidth()
            .then(if (onClick != null) Modifier.heightIn(min = 32.dp).clip(RoundedCornerShape(6.dp)).clickable(role = Role.Button, onClick = onClick) else Modifier),
    ) {
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary)
        Text(text = value, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.text)
    }
}

@Composable
private fun BacklogCard(
    title: String,
    subtitle: String,
    tiles: List<DashboardTile>,
    onView: () -> Unit,
    extra: @Composable ColumnScope.() -> Unit = {},
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val columns = dashboardTileColumns(tiles.size)

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(14.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(6.dp)).clickable(role = Role.Button, onClick = onView),
        ) {
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = subtitle, fontSize = 12.sp, color = colors.textTertiary)
            }
            Text(text = stringResource(R.string.dashboard_view), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            tiles.chunked(columns).forEach { row ->
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    row.forEach { tile -> CountTile(tile = tile, onClick = { navigator.open(tile.route) }, modifier = Modifier.weight(1f)) }
                    repeat(columns - row.size) { Spacer(modifier = Modifier.weight(1f)) }
                }
            }
        }
        extra()
    }
}

@Composable
private fun CountTile(tile: DashboardTile, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val tone = colors.tone(tile.tone)
    val isNeutral = tile.tone == Tone.Neutral

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .heightIn(min = 44.dp)
            .clip(RoundedCornerShape(8.dp))
            .background(if (isNeutral) colors.background else tone.container)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 4.dp, vertical = 8.dp),
    ) {
        Text(
            text = countText(tile.value),
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            color = if (isNeutral) colors.textSecondary else tone.content,
            maxLines = 1,
        )
        Text(
            text = stringResource(tile.label),
            fontSize = 10.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.textTertiary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}
