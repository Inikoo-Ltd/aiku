package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.navigation.WarehouseMenuRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun warehouseKicker(extra: String? = null): String {
    val warehouse = LocalWarehouse.current
    return listOfNotNull(warehouse.name, warehouse.code, extra).joinToString(" · ")
}

@Composable
fun AppScreen(
    header: @Composable () -> Unit,
    modifier: Modifier = Modifier,
    activeTab: AppTab? = null,
    contentPadding: PaddingValues = PaddingValues(16.dp),
    spacing: Dp = 16.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxSize().background(colors.background)) {
        header()
        Column(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(contentPadding),
            verticalArrangement = Arrangement.spacedBy(spacing),
            content = content,
        )
        AppTabBar(active = activeTab)
    }
}

@Composable
fun AppHeader(
    title: String,
    modifier: Modifier = Modifier,
    kicker: String? = warehouseKicker(),
    showBack: Boolean = true,
    showMenu: Boolean = true,
    actions: @Composable RowScope.() -> Unit = {},
    below: @Composable ColumnScope.() -> Unit = {},
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            if (showBack) {
                HeaderIconButton(icon = R.drawable.ic_chevron_left, description = stringResource(R.string.nav_back), onClick = navigator::back)
            }
            Column(modifier = Modifier.weight(1f).padding(start = if (showBack) 0.dp else 4.dp)) {
                if (kicker != null) {
                    Text(text = kicker, fontSize = 12.sp, color = colors.onNavSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
                Text(
                    text = title,
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.onNav,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            actions()
            if (showMenu) {
                HeaderIconButton(
                    icon = R.drawable.ic_menu,
                    description = stringResource(R.string.nav_warehouse_menu),
                    onClick = { navigator.open(WarehouseMenuRoute) },
                    filled = true,
                )
            }
        }
        below()
    }
}

@Composable
fun HeaderIconButton(
    @DrawableRes icon: Int,
    description: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    filled: Boolean = false,
) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(44.dp)
            .clip(RoundedCornerShape(8.dp))
            .background(if (filled) Color.White.copy(alpha = 0.14f) else Color.Transparent)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.onNav, modifier = Modifier.size(22.dp))
    }
}

data class HeaderTab(
    val label: String,
    @DrawableRes val icon: Int?,
    val isActive: Boolean,
    val onClick: () -> Unit,
    val description: String? = null,
)

@Composable
fun HeaderTabs(tabs: List<HeaderTab>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        modifier = modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        tabs.forEach { tab ->
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier
                    .heightIn(min = 40.dp)
                    .clip(RoundedCornerShape(8.dp))
                    .background(if (tab.isActive) colors.navActive else Color.Transparent)
                    .then(if (tab.isActive) Modifier.border(1.dp, colors.navRing, RoundedCornerShape(8.dp)) else Modifier)
                    .clickable(role = Role.Tab, onClick = tab.onClick)
                    .then(if (tab.description != null) Modifier.semantics { contentDescription = tab.description } else Modifier)
                    .padding(horizontal = 12.dp),
            ) {
                val tint = if (tab.isActive) colors.navAccent else colors.onNav
                tab.icon?.let { Icon(painter = painterResource(it), contentDescription = null, tint = tint, modifier = Modifier.size(18.dp)) }
                if (tab.label.isNotBlank()) {
                    Text(text = tab.label, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = tint)
                }
            }
        }
    }
}

@Composable
fun HeaderSearchRow(
    placeholder: String,
    modifier: Modifier = Modifier,
    value: String = "",
    onValueChange: (String) -> Unit = {},
    onScan: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Row(modifier = modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
        SearchField(value = value, onValueChange = onValueChange, placeholder = placeholder, modifier = Modifier.weight(1f))
        ScanSquareButton(onClick = onScan ?: { navigator.open(ScanRoute) })
    }
}

@Composable
fun ScanSquareButton(onClick: () -> Unit, modifier: Modifier = Modifier, size: Dp = 48.dp) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.nav_scan)

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(size)
            .clip(RoundedCornerShape(8.dp))
            .background(colors.scan)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(24.dp))
    }
}

private data class TabItem(val tab: AppTab, @StringRes val label: Int, @DrawableRes val icon: Int)

@Composable
fun AppTabBar(active: AppTab?, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    val leading = listOfNotNull(
        TabItem(AppTab.Dashboard, R.string.tab_dashboard, R.drawable.ic_tab_dashboard),
        TabItem(AppTab.Inventory, R.string.tab_inventory, R.drawable.ic_tab_inventory).takeIf { can.viewInventory },
    )
    val trailing = listOfNotNull(
        TabItem(AppTab.GoodsIn, R.string.tab_goods_in, R.drawable.ic_tab_goods_in).takeIf { can.viewGoodsIn },
        TabItem(AppTab.GoodsOut, R.string.tab_goods_out, R.drawable.ic_tab_goods_out).takeIf { can.viewGoodsOut },
    )

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .fillMaxWidth()
            .background(colors.nav)
            .navigationBarsPadding()
            .padding(horizontal = 8.dp, vertical = 8.dp),
    ) {
        leading.forEach { TabBarItem(it, isActive = it.tab == active, onClick = { navigator.openTab(it.tab) }) }
        Box(modifier = Modifier.weight(1f), contentAlignment = Alignment.Center) {
            ScanTabButton(onClick = { navigator.open(ScanRoute) })
        }
        trailing.forEach { TabBarItem(it, isActive = it.tab == active, onClick = { navigator.openTab(it.tab) }) }
    }
}

@Composable
private fun RowScope.TabBarItem(item: TabItem, isActive: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val tint = if (isActive) colors.navAccent else colors.onNavSecondary

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
        modifier = Modifier
            .weight(1f)
            .padding(horizontal = 4.dp)
            .heightIn(min = if (isActive) 52.dp else 48.dp)
            .clip(RoundedCornerShape(8.dp))
            .background(if (isActive) colors.navActive else Color.Transparent)
            .then(if (isActive) Modifier.border(1.dp, colors.navRing, RoundedCornerShape(8.dp)) else Modifier)
            .clickable(role = Role.Tab, onClick = onClick),
    ) {
        Icon(painter = painterResource(item.icon), contentDescription = null, tint = tint, modifier = Modifier.size(24.dp))
        Text(text = stringResource(item.label), fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = tint, maxLines = 1)
    }
}

@Composable
private fun ScanTabButton(onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.nav_scan)
    val shape = RoundedCornerShape(12.dp)

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .offset(y = (-20).dp)
            .size(60.dp)
            .shadow(elevation = 8.dp, shape = shape, ambientColor = colors.shadow, spotColor = colors.shadow)
            .clip(shape)
            .background(colors.scan)
            .border(4.dp, colors.divider, shape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(28.dp))
    }
}
