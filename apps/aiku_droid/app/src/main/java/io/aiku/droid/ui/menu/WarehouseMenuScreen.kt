package io.aiku.droid.ui.menu

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
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
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.AppearanceRoute
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.GoodsInBacklogRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.navigation.ShippersRoute
import io.aiku.droid.ui.navigation.SkuFamiliesRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private val PillShape = RoundedCornerShape(percent = 50)

private data class MenuBadge(val text: String, val tone: Tone)

private data class MenuEntry(
    @StringRes val label: Int,
    @DrawableRes val icon: Int,
    val onClick: () -> Unit,
    val tone: Tone = Tone.Primary,
    val badge: MenuBadge? = null,
)

private data class MenuGroup(
    @StringRes val title: Int,
    @DrawableRes val icon: Int?,
    val entries: List<MenuEntry>,
)

@Composable
fun WarehouseMenuScreen() {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val groups = listOfNotNull(
        MenuGroup(
            title = R.string.menu_group_inventory,
            icon = R.drawable.ic_section_inventory,
            entries = listOf(
                MenuEntry(R.string.menu_inventory_dashboard, R.drawable.ic_tab_dashboard, onClick = { navigator.open(InventoryDashboardRoute) }),
                MenuEntry(R.string.menu_sku_families, R.drawable.ic_menu_sku_families, onClick = { navigator.open(SkuFamiliesRoute) }),
                MenuEntry(
                    label = R.string.menu_skus,
                    icon = R.drawable.ic_menu_skus,
                    onClick = { navigator.open(SkusRoute) },
                    badge = MenuBadge(pluralStringResource(R.plurals.menu_badge_low, SampleLowStockSkus, SampleLowStockSkus), Tone.Warn),
                ),
            ),
        ).takeIf { can.viewInventory },
        MenuGroup(
            title = R.string.menu_group_locations,
            icon = R.drawable.ic_section_locations,
            entries = listOf(
                MenuEntry(R.string.menu_warehouse, R.drawable.ic_warehouse, onClick = { navigator.open(WarehouseOverviewRoute) }),
                MenuEntry(R.string.menu_areas, R.drawable.ic_warehouse_area, onClick = { navigator.open(AreasRoute) }),
                MenuEntry(R.string.menu_locations, R.drawable.ic_section_locations, onClick = { navigator.open(LocationsRoute) }),
            ),
        ).takeIf { can.viewLocations },
        MenuGroup(
            title = R.string.menu_group_goods_in,
            icon = R.drawable.ic_section_goods_in,
            entries = listOf(
                MenuEntry(R.string.menu_goods_in_backlog, R.drawable.ic_menu_backlog, onClick = { navigator.open(GoodsInBacklogRoute) }),
                MenuEntry(
                    label = R.string.menu_stock_deliveries,
                    icon = R.drawable.ic_dashboard_stock_delivery,
                    onClick = { navigator.open(StockDeliveriesRoute) },
                    badge = MenuBadge(pluralStringResource(R.plurals.menu_badge_received, SampleReceivedDeliveries, SampleReceivedDeliveries), Tone.Success),
                ),
            ),
        ).takeIf { can.viewGoodsIn },
        MenuGroup(
            title = R.string.menu_group_goods_out,
            icon = R.drawable.ic_section_goods_out,
            entries = listOf(
                MenuEntry(
                    label = R.string.menu_goods_out_backlog,
                    icon = R.drawable.ic_menu_backlog,
                    onClick = { navigator.open(GoodsOutBacklogRoute) },
                    badge = MenuBadge(pluralStringResource(R.plurals.menu_badge_unassigned, SampleUnassignedDeliveryNotes, SampleUnassignedDeliveryNotes), Tone.Neutral),
                ),
                MenuEntry(R.string.menu_delivery_notes, R.drawable.ic_dashboard_delivery_note, onClick = { navigator.open(DeliveryNotesRoute) }),
                MenuEntry(R.string.menu_shippers, R.drawable.ic_menu_shippers, onClick = { navigator.open(ShippersRoute) }),
            ),
        ).takeIf { can.viewGoodsOut },
        MenuGroup(
            title = R.string.menu_group_fulfilment,
            icon = null,
            entries = listOf(
                MenuEntry(R.string.menu_stored_pallets, R.drawable.ic_menu_pallets, onClick = {}, tone = Tone.Neutral),
                MenuEntry(R.string.menu_stored_items, R.drawable.ic_menu_stored_items, onClick = {}, tone = Tone.Neutral),
                MenuEntry(R.string.menu_fulfilment_deliveries, R.drawable.ic_menu_fulfilment_delivery, onClick = {}, tone = Tone.Neutral),
                MenuEntry(R.string.menu_fulfilment_returns, R.drawable.ic_menu_log_out, onClick = {}, tone = Tone.Neutral),
            ),
        ).takeIf { can.viewFulfilment },
    )

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.menu_title),
                showMenu = false,
                below = { MenuSearchLauncher(onOpen = { navigator.open(SearchRoute) }) },
            )
        },
        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 18.dp),
        spacing = 18.dp,
    ) {
        groups.forEach { MenuGroupSection(it) }
        AppearanceRow(onClick = { navigator.open(AppearanceRoute) })
        AikuButton(
            text = stringResource(R.string.menu_log_out),
            onClick = navigator::signOut,
            style = ButtonStyle.Danger,
            icon = R.drawable.ic_menu_log_out,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun MenuSearchLauncher(onOpen: () -> Unit) {
    val placeholder = stringResource(R.string.menu_search_placeholder)

    Box(modifier = Modifier.fillMaxWidth()) {
        SearchField(value = "", onValueChange = {}, placeholder = placeholder, modifier = Modifier.fillMaxWidth())
        Box(
            modifier = Modifier
                .matchParentSize()
                .clip(ControlShape)
                .clickable(role = Role.Button, onClickLabel = placeholder, onClick = onOpen),
        )
    }
}

@Composable
private fun MenuGroupSection(group: MenuGroup) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        GroupLabel(text = stringResource(group.title), icon = group.icon)
        ListGroup {
            group.entries.forEachIndexed { index, entry ->
                if (index > 0) {
                    RowDivider()
                }
                MenuRow(entry)
            }
        }
    }
}

@Composable
private fun MenuRow(entry: MenuEntry) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clickable(role = Role.Button, onClick = entry.onClick)
            .padding(horizontal = 16.dp),
    ) {
        IconTile(icon = entry.icon, tone = entry.tone, size = 36.dp, iconSize = 20.dp)
        Text(
            text = stringResource(entry.label),
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.text,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        entry.badge?.let { CountBadge(it) }
        Icon(
            painter = painterResource(R.drawable.ic_chevron_right),
            contentDescription = null,
            tint = colors.faint,
            modifier = Modifier.size(20.dp),
        )
    }
}

@Composable
private fun CountBadge(badge: MenuBadge) {
    val toneColors = LocalAikuColors.current.tone(badge.tone)

    Text(
        text = badge.text,
        fontSize = 12.sp,
        lineHeight = 16.sp,
        fontWeight = FontWeight.SemiBold,
        color = toneColors.content,
        maxLines = 1,
        modifier = Modifier
            .fakeData(cornerRadius = 11.dp)
            .clip(PillShape)
            .background(toneColors.container)
            .padding(horizontal = 8.dp, vertical = 3.dp),
    )
}

@Composable
private fun AppearanceRow(onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 10.dp),
    ) {
        IconTile(icon = R.drawable.ic_menu_appearance, tone = Tone.Primary, size = 36.dp, iconSize = 20.dp)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = stringResource(R.string.menu_appearance), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            Text(text = stringResource(R.string.menu_appearance_subtitle), fontSize = 13.sp, color = colors.textTertiary)
        }
        Icon(
            painter = painterResource(R.drawable.ic_chevron_right),
            contentDescription = null,
            tint = colors.faint,
            modifier = Modifier.size(20.dp),
        )
    }
}

private const val SampleLowStockSkus = 17
private const val SampleReceivedDeliveries = 2
private const val SampleUnassignedDeliveryNotes = 12

@Preview(heightDp = 1400)
@Composable
private fun WarehouseMenuScreenPreview() {
    AikuDroidTheme {
        WarehouseMenuScreen()
    }
}
