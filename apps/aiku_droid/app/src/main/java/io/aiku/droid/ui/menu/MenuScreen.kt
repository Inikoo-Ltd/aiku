package io.aiku.droid.ui.menu

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.ui.clocking.LocalClocking
import io.aiku.droid.ui.clocking.clockingMenuSubtitle
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseModule
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.initialsOf
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.AppearanceRoute
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ProfileRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.navigation.TimesheetsRoute
import io.aiku.droid.ui.navigation.WarehouseDashboardRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private data class SectionBadge(val text: String, val isWarn: Boolean)

private val moduleIcons = mapOf(
    WarehouseModule.Inventory to R.drawable.ic_ui_pallet,
    WarehouseModule.Locations to R.drawable.ic_ui_locations,
    WarehouseModule.GoodsIn to R.drawable.ic_ui_goods_in,
    WarehouseModule.GoodsOut to R.drawable.ic_ui_goods_out,
)

private val sectionIcons = mapOf(
    WarehouseSection.InventoryDashboard to R.drawable.ic_ui_dashboard,
    WarehouseSection.SkuFamilies to R.drawable.ic_ui_families,
    WarehouseSection.Skus to R.drawable.ic_ui_box,
    WarehouseSection.StoredPallets to R.drawable.ic_ui_box,
    WarehouseSection.StoredItems to R.drawable.ic_ui_box,
    WarehouseSection.StockHistory to R.drawable.ic_ui_clock,
    WarehouseSection.WarehouseOverview to R.drawable.ic_ui_warehouse,
    WarehouseSection.Areas to R.drawable.ic_ui_areas,
    WarehouseSection.Locations to R.drawable.ic_ui_locations,
    WarehouseSection.GoodsInBacklog to R.drawable.ic_ui_backlog,
    WarehouseSection.StockDeliveries to R.drawable.ic_ui_stock_delivery,
    WarehouseSection.FulfilmentDeliveries to R.drawable.ic_ui_stock_delivery,
    WarehouseSection.GoodsInReturns to R.drawable.ic_ui_stock_delivery,
    WarehouseSection.GoodsOutBacklog to R.drawable.ic_ui_backlog,
    WarehouseSection.DeliveryNotes to R.drawable.ic_ui_delivery_note,
    WarehouseSection.PickingSessions to R.drawable.ic_ui_delivery_note,
    WarehouseSection.FulfilmentReturns to R.drawable.ic_ui_delivery_note,
    WarehouseSection.Trolleys to R.drawable.ic_ui_shipper,
    WarehouseSection.PickedBays to R.drawable.ic_ui_shipper,
)

private val sampleBadges = mapOf(
    WarehouseSection.Skus to SectionBadge(MenuSamples.LOW_SKOS, isWarn = true),
    WarehouseSection.StockDeliveries to SectionBadge(MenuSamples.RECEIVED_DELIVERIES, isWarn = false),
    WarehouseSection.GoodsOutBacklog to SectionBadge(MenuSamples.UNASSIGNED, isWarn = true),
)

@Composable
fun MenuScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val staff = LocalStaff.current
    val warehouse = LocalWarehouse.current
    val hasWarehouse = LocalWarehouses.current.isNotEmpty()
    val name = staff.displayName.ifBlank { staff.username }
    val clockingState by LocalClocking.current.state.collectAsStateWithLifecycle()
    val isClockedIn = clockingState.status?.isClockedIn == true

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.tab_menu),
                kicker = stringResource(R.string.menu_kicker),
                showBack = false,
                below = { MenuSearch(onClick = { navigator.open(SearchRoute) }) },
            )
        },
        activeTab = MainTab.Menu,
        spacing = 18.dp,
        contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, end = 16.dp, top = 18.dp, bottom = 16.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape)
                .clickable(role = Role.Button) { navigator.open(ProfileRoute) }
                .padding(horizontal = 14.dp, vertical = 12.dp),
        ) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(44.dp).clip(CircleShape).background(colors.navActive)) {
                Text(text = initialsOf(name), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.navAccent)
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = name, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = stringResource(R.string.home_profile_settings), fontSize = 13.sp, color = colors.textTertiary)
            }
            Chevron()
        }
        if (hasWarehouse) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 56.dp)
                    .clip(CardShape)
                    .background(colors.soft)
                    .border(1.dp, colors.softBorder, CardShape)
                    .clickable(role = Role.Button) { navigator.showWarehouseSwitcher() }
                    .padding(horizontal = 14.dp, vertical = 8.dp),
            ) {
                Icon(painter = painterResource(R.drawable.ic_ui_warehouse), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = stringResource(R.string.home_module_warehouse), fontSize = 12.sp, color = colors.textTertiary)
                    Text(
                        text = listOf(warehouse.organisation.name, warehouseKicker()).filter { it.isNotBlank() }.joinToString(" · "),
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = colors.text,
                    )
                }
                Text(text = stringResource(R.string.menu_change), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
            }
            ListGroup {
                MenuRow(
                    icon = R.drawable.ic_ui_dashboard,
                    title = stringResource(R.string.home_module_warehouse_dashboard),
                    subtitle = stringResource(R.string.menu_dashboard_subtitle),
                    onClick = { navigator.open(WarehouseDashboardRoute) },
                )
            }
            WarehouseModule.entries.forEach { module ->
                val sections = module.visibleSections(warehouse.can)
                if (sections.isNotEmpty()) {
                    ModuleGroup(module = module, sections = sections, onOpen = { navigator.open(it.route) })
                }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            GroupHeading(icon = R.drawable.ic_ui_clock, title = stringResource(R.string.module_clocking), note = stringResource(R.string.menu_all_staff))
            ListGroup {
                MenuRow(
                    icon = R.drawable.ic_ui_clock,
                    title = stringResource(R.string.menu_clocking),
                    subtitle = clockingMenuSubtitle(clockingState),
                    subtitleColor = if (isClockedIn) colors.success else null,
                    iconContainer = if (isClockedIn) colors.successSoft else null,
                    iconTint = if (isClockedIn) colors.success else null,
                    onClick = { navigator.open(ClockingRoute) },
                )
                RowDivider()
                MenuRow(icon = R.drawable.ic_ui_backlog, title = stringResource(R.string.menu_timesheets), onClick = { navigator.open(TimesheetsRoute) })
            }
        }
        ListGroup {
            MenuRow(
                icon = R.drawable.ic_ui_appearance,
                title = stringResource(R.string.menu_appearance),
                subtitle = stringResource(R.string.menu_appearance_subtitle),
                onClick = { navigator.open(AppearanceRoute) },
            )
        }
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 52.dp)
                .clip(ControlShape)
                .background(colors.surface)
                .border(1.dp, colors.dangerBorder, ControlShape)
                .clickable(role = Role.Button) { navigator.signOut() },
        ) {
            Icon(painter = painterResource(R.drawable.ic_ui_log_out), contentDescription = null, tint = colors.danger, modifier = Modifier.size(20.dp))
            Text(text = stringResource(R.string.menu_log_out), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.danger)
        }
    }
}

@Composable
private fun MenuSearch(onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .height(46.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .clickable(role = Role.Button, onClickLabel = stringResource(R.string.menu_search_label), onClick = onClick)
            .padding(horizontal = 14.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_search_alt), contentDescription = null, tint = colors.textQuaternary, modifier = Modifier.size(20.dp))
        Text(text = stringResource(R.string.menu_search_placeholder), fontSize = 15.sp, color = colors.textQuaternary, maxLines = 1)
    }
}

@Composable
private fun ModuleGroup(module: WarehouseModule, sections: List<WarehouseSection>, onOpen: (WarehouseSection) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        GroupHeading(icon = moduleIcons.getValue(module), title = stringResource(module.label))
        ListGroup {
            sections.forEachIndexed { index, section ->
                if (index > 0) {
                    RowDivider()
                }
                MenuRow(
                    icon = sectionIcons.getValue(section),
                    title = stringResource(section.label),
                    badge = sampleBadges[section],
                    isFulfilment = section.isFulfilment,
                    onClick = { onOpen(section) },
                )
            }
        }
    }
}

@Composable
private fun GroupHeading(@DrawableRes icon: Int, title: String, note: String? = null) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.padding(horizontal = 4.dp),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(16.dp))
        Text(text = title.uppercase(), fontSize = 12.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.7.sp, color = colors.textTertiary)
        if (note != null) {
            Text(text = note, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = colors.textQuaternary)
        }
    }
}

@Composable
private fun MenuRow(
    @DrawableRes icon: Int,
    title: String,
    onClick: () -> Unit,
    subtitle: String? = null,
    subtitleColor: Color? = null,
    badge: SectionBadge? = null,
    isFulfilment: Boolean = false,
    iconContainer: Color? = null,
    iconTint: Color? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 8.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(36.dp).clip(ControlShape).background(iconContainer ?: colors.soft)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = iconTint ?: colors.link, modifier = Modifier.size(20.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            if (subtitle != null) {
                Text(
                    text = subtitle,
                    fontSize = 12.sp,
                    color = subtitleColor ?: colors.textTertiary,
                )
            }
        }
        if (isFulfilment) {
            Text(
                text = stringResource(R.string.menu_fulfilment_tag),
                fontSize = 11.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.textTertiary,
                modifier = Modifier.border(1.dp, colors.borderStrong, ChipShape).padding(horizontal = 6.dp, vertical = 2.dp),
            )
        }
        if (badge != null) {
            Text(
                text = badge.text,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = if (badge.isWarn) colors.warn else colors.success,
                modifier = Modifier
                    .fakeData(cornerRadius = 999.dp)
                    .clip(RoundedCornerShape(999.dp))
                    .background(if (badge.isWarn) colors.warnSoft else colors.successSoft)
                    .padding(horizontal = 8.dp, vertical = 3.dp),
            )
        }
        Chevron()
    }
}

@Composable
private fun Chevron() {
    Icon(painter = painterResource(R.drawable.ic_ui_next), contentDescription = null, tint = LocalAikuColors.current.faint, modifier = Modifier.size(20.dp))
}

internal object MenuSamples {
    const val LOW_SKOS = "17 low"
    const val RECEIVED_DELIVERIES = "2 received"
    const val UNASSIGNED = "12 unassigned"
}
