package io.aiku.droid.ui.dashboard

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.defaultMinSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.VerticalDivider
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SectionTitle
import io.aiku.droid.ui.components.StatTile
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.DeliveryNoteDetailRoute
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationDetailRoute
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.NotificationsRoute
import io.aiku.droid.ui.navigation.ProfileRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.navigation.StockDeliveryDetailRoute
import io.aiku.droid.ui.navigation.WarehouseMenuRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private const val ShortcutColumns = 4
private val HeaderOverlap = 52.dp
private val PillShape = RoundedCornerShape(percent = 50)
private val TileShape = RoundedCornerShape(10.dp)
private val NotificationDotRed = Color(0xFFEF4444)

private data class DashboardShortcut(
    @StringRes val label: Int,
    @DrawableRes val icon: Int,
    val onClick: () -> Unit,
)

@Composable
fun DashboardScreen() {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val shortcuts = listOfNotNull(
        DashboardShortcut(
            label = R.string.dashboard_shortcut_inventory,
            icon = R.drawable.ic_section_inventory,
            onClick = { navigator.openTab(AppTab.Inventory) },
        ).takeIf { can.viewInventory },
        DashboardShortcut(
            label = R.string.dashboard_shortcut_locations,
            icon = R.drawable.ic_section_locations,
            onClick = { navigator.open(LocationsRoute) },
        ).takeIf { can.viewLocations },
        DashboardShortcut(
            label = R.string.dashboard_shortcut_goods_in,
            icon = R.drawable.ic_section_goods_in,
            onClick = { navigator.openTab(AppTab.GoodsIn) },
        ).takeIf { can.viewGoodsIn },
        DashboardShortcut(
            label = R.string.dashboard_shortcut_goods_out,
            icon = R.drawable.ic_section_goods_out,
            onClick = { navigator.openTab(AppTab.GoodsOut) },
        ).takeIf { can.viewGoodsOut },
    )
    val hasShortcuts = shortcuts.isNotEmpty()

    AppScreen(
        header = { DashboardHeader(bottomPadding = if (hasShortcuts) 24.dp else 20.dp) },
        activeTab = AppTab.Dashboard,
        contentPadding = PaddingValues(0.dp),
        spacing = 0.dp,
    ) {
        if (hasShortcuts) {
            ShortcutsStrip(shortcuts)
        }
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 16.dp, end = 16.dp, top = if (hasShortcuts) 20.dp else 16.dp, bottom = 24.dp),
            verticalArrangement = Arrangement.spacedBy(20.dp),
        ) {
            if (can.viewGoodsOut) {
                GoodsOutCard()
            }
            if (can.viewGoodsIn) {
                GoodsInCard()
            }
            if (can.viewInventory) {
                InventoryCard()
            }
            AssignedToMeSection()
            if (can.viewInventory) {
                StockMovementsSection()
            }
        }
    }
}

@Composable
private fun DashboardHeader(bottomPadding: Dp) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val staff = LocalStaff.current
    val profileDescription = stringResource(R.string.dashboard_profile)

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(start = 20.dp, end = 20.dp, top = 12.dp, bottom = bottomPadding),
        verticalArrangement = Arrangement.spacedBy(18.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(colors.navActive)
                    .border(2.dp, colors.onNav.copy(alpha = 0.25f), CircleShape)
                    .clickable(role = Role.Button, onClickLabel = profileDescription) { navigator.open(ProfileRoute) }
                    .semantics { contentDescription = profileDescription },
            ) {
                Text(
                    text = initialsOf(staff.displayName, staff.username),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.navAccent,
                )
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = stringResource(R.string.dashboard_greeting),
                    fontSize = 13.sp,
                    lineHeight = 16.sp,
                    color = colors.onNavSecondary,
                    maxLines = 1,
                )
                Text(
                    text = staff.displayName,
                    fontSize = 19.sp,
                    lineHeight = 24.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.onNav,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            HeaderIconButton(
                icon = R.drawable.ic_menu,
                description = stringResource(R.string.nav_warehouse_menu),
                onClick = { navigator.open(WarehouseMenuRoute) },
                filled = true,
            )
            HeaderIconButton(
                icon = R.drawable.ic_search,
                description = stringResource(R.string.dashboard_search),
                onClick = { navigator.open(SearchRoute) },
                filled = true,
            )
            NotificationsButton(unreadCount = SampleUnreadNotifications, onClick = { navigator.open(NotificationsRoute) })
        }
        WarehouseSwitcherButton()
    }
}

@Composable
private fun NotificationsButton(unreadCount: Int, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box {
        HeaderIconButton(
            icon = R.drawable.ic_bell,
            description = pluralStringResource(R.plurals.dashboard_notifications_unread, unreadCount, unreadCount),
            onClick = onClick,
            filled = true,
        )
        if (unreadCount > 0) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(top = 6.dp, end = 6.dp)
                    .fakeData(cornerRadius = 9.dp)
                    .defaultMinSize(minWidth = 18.dp)
                    .height(18.dp)
                    .clip(PillShape)
                    .background(NotificationDotRed)
                    .border(2.dp, colors.primary, PillShape)
                    .padding(horizontal = 5.dp),
            ) {
                Text(
                    text = unreadCount.toString(),
                    fontSize = 10.sp,
                    lineHeight = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                )
            }
        }
    }
}

@Composable
private fun WarehouseSwitcherButton() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val warehouse = LocalWarehouse.current
    val description = stringResource(R.string.dashboard_change_warehouse, warehouse.name)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .height(44.dp)
            .clip(ControlShape)
            .background(colors.navActive)
            .border(1.dp, colors.navRing, ControlShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = navigator::showWarehouseSwitcher)
            .semantics { contentDescription = description }
            .padding(horizontal = 12.dp),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_warehouse),
            contentDescription = null,
            tint = colors.navAccent,
            modifier = Modifier.size(20.dp),
        )
        Text(
            text = warehouse.name,
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.navAccent,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f, fill = false),
        )
        Text(text = warehouse.code, fontSize = 13.sp, color = colors.onNavSecondary, maxLines = 1)
        Icon(
            painter = painterResource(R.drawable.ic_chevron_down),
            contentDescription = null,
            tint = colors.onNavSecondary,
            modifier = Modifier.size(18.dp),
        )
    }
}

@Composable
private fun ShortcutsStrip(shortcuts: List<DashboardShortcut>) {
    val colors = LocalAikuColors.current

    Box(modifier = Modifier.fillMaxWidth()) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(HeaderOverlap)
                .background(colors.nav),
        )
        Row(
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            modifier = Modifier
                .padding(horizontal = 16.dp)
                .fillMaxWidth()
                .shadow(elevation = 8.dp, shape = CardShape, ambientColor = colors.shadow, spotColor = colors.shadow)
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape)
                .padding(8.dp),
        ) {
            shortcuts.forEach { ShortcutItem(it) }
            repeat(ShortcutColumns - shortcuts.size) { Spacer(modifier = Modifier.weight(1f)) }
        }
    }
}

@Composable
private fun RowScope.ShortcutItem(shortcut: DashboardShortcut) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .weight(1f)
            .clip(ControlShape)
            .clickable(role = Role.Button, onClick = shortcut.onClick)
            .padding(start = 2.dp, end = 2.dp, top = 12.dp, bottom = 10.dp),
    ) {
        IconTile(icon = shortcut.icon, tone = Tone.Primary, size = 44.dp, iconSize = 24.dp)
        Text(
            text = stringResource(shortcut.label),
            fontSize = 12.sp,
            lineHeight = 16.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.textSecondary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun ModuleCardHeader(
    @DrawableRes icon: Int,
    title: String,
    subtitle: String,
    actionLabel: String,
    onAction: () -> Unit,
) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(text = title, fontSize = 16.sp, lineHeight = 20.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(
                text = subtitle,
                fontSize = 12.sp,
                lineHeight = 16.sp,
                color = colors.textTertiary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .heightIn(min = 44.dp)
                .clip(ChipShape)
                .clickable(role = Role.Button, onClick = onAction)
                .padding(horizontal = 6.dp),
        ) {
            Text(text = actionLabel, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary)
        }
    }
}

@Composable
private fun GoodsOutCard() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val summary = SampleGoodsOut
    val openDeliveryNotes = { navigator.openTab(AppTab.GoodsOut) }

    AikuCard(modifier = Modifier.fakeData(), spacing = 14.dp) {
        ModuleCardHeader(
            icon = R.drawable.ic_section_goods_out,
            title = stringResource(R.string.dashboard_goods_out_title),
            subtitle = stringResource(R.string.dashboard_goods_out_subtitle),
            actionLabel = stringResource(R.string.dashboard_view),
            onAction = openDeliveryNotes,
        )
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                StatTile(
                    label = stringResource(R.string.dashboard_unassigned),
                    value = summary.unassigned.toString(),
                    icon = R.drawable.ic_dashboard_unassigned,
                    onClick = openDeliveryNotes,
                    modifier = Modifier.weight(1f),
                )
                StatTile(
                    label = stringResource(R.string.dashboard_in_queue),
                    value = summary.queued.toString(),
                    icon = R.drawable.ic_dashboard_unassigned,
                    onClick = openDeliveryNotes,
                    modifier = Modifier.weight(1f),
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                StatTile(
                    label = stringResource(R.string.dashboard_handling),
                    value = summary.handling.toString(),
                    icon = R.drawable.ic_dashboard_handling,
                    caption = pluralStringResource(R.plurals.dashboard_blocked, summary.blocked, summary.blocked),
                    captionTone = Tone.Danger,
                    onClick = openDeliveryNotes,
                    modifier = Modifier.weight(1f),
                )
                StatTile(
                    label = stringResource(R.string.dashboard_packed),
                    value = summary.packed.toString(),
                    icon = R.drawable.ic_dashboard_packed,
                    onClick = openDeliveryNotes,
                    modifier = Modifier.weight(1f),
                )
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            val totalText = stringResource(R.string.dashboard_dispatched_total, summary.dispatchTarget.toString())
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    painter = painterResource(R.drawable.ic_dashboard_dispatched),
                    contentDescription = null,
                    tint = colors.success,
                    modifier = Modifier.size(16.dp),
                )
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = stringResource(R.string.dashboard_dispatched_today),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.textSecondary,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = buildAnnotatedString {
                        withStyle(SpanStyle(fontWeight = FontWeight.Bold, color = colors.text)) {
                            append(summary.dispatchedToday.toString())
                        }
                        append(" ")
                        append(totalText)
                    },
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                )
            }
            ProgressBar(fraction = summary.dispatchedToday.toFloat() / summary.dispatchTarget, height = 8.dp)
        }
    }
}

@Composable
private fun GoodsInCard() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val openStockDeliveries = { navigator.openTab(AppTab.GoodsIn) }
    val states = listOf(
        stringResource(R.string.dashboard_dispatched) to SampleGoodsIn.dispatched,
        stringResource(R.string.dashboard_received) to SampleGoodsIn.received,
        stringResource(R.string.dashboard_checked) to SampleGoodsIn.checked,
    )

    AikuCard(modifier = Modifier.fakeData(), spacing = 14.dp) {
        ModuleCardHeader(
            icon = R.drawable.ic_section_goods_in,
            title = stringResource(R.string.dashboard_goods_in_title),
            subtitle = stringResource(R.string.dashboard_goods_in_subtitle),
            actionLabel = stringResource(R.string.dashboard_view),
            onAction = openStockDeliveries,
        )
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(IntrinsicSize.Min)
                .clip(TileShape)
                .border(1.dp, colors.border, TileShape),
        ) {
            states.forEachIndexed { index, (label, count) ->
                if (index > 0) {
                    VerticalDivider(thickness = 1.dp, color = colors.border)
                }
                Column(
                    verticalArrangement = Arrangement.spacedBy(4.dp),
                    modifier = Modifier
                        .weight(1f)
                        .clickable(role = Role.Button, onClick = openStockDeliveries)
                        .padding(12.dp),
                ) {
                    Text(text = count.toString(), fontSize = 22.sp, lineHeight = 28.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(
                        text = label,
                        fontSize = 12.sp,
                        lineHeight = 16.sp,
                        fontWeight = FontWeight.Medium,
                        color = colors.textTertiary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}

@Composable
private fun InventoryCard() {
    val navigator = LocalAppNavigator.current
    val openSkus = { navigator.openTab(AppTab.Inventory) }

    AikuCard(modifier = Modifier.fakeData(), spacing = 14.dp) {
        ModuleCardHeader(
            icon = R.drawable.ic_section_inventory,
            title = stringResource(R.string.dashboard_inventory_title),
            subtitle = stringResource(R.string.dashboard_inventory_subtitle, SampleInventory.skuCount),
            actionLabel = stringResource(R.string.dashboard_skus),
            onAction = openSkus,
        )
        Row(
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.horizontalScroll(rememberScrollState()),
        ) {
            StockLevelChip(count = SampleInventory.low, label = stringResource(R.string.dashboard_low), tone = Tone.Warn, onClick = openSkus)
            StockLevelChip(count = SampleInventory.critical, label = stringResource(R.string.dashboard_critical), tone = Tone.Orange, onClick = openSkus)
            StockLevelChip(count = SampleInventory.outOfStock, label = stringResource(R.string.dashboard_out_of_stock), tone = Tone.Danger, onClick = openSkus)
        }
    }
}

@Composable
private fun StockLevelChip(count: Int, label: String, tone: Tone, onClick: () -> Unit) {
    val toneColors = LocalAikuColors.current.tone(tone)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .heightIn(min = 40.dp)
            .clip(ControlShape)
            .background(toneColors.container)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 12.dp),
    ) {
        Text(text = count.toString(), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = toneColors.content)
        Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = toneColors.content, maxLines = 1)
    }
}

@Composable
private fun AssignedToMeSection() {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val tasks = SampleAssignedTasks.filter { task ->
        when (task.kind) {
            AssignedTaskKind.DeliveryNote -> can.viewGoodsOut
            AssignedTaskKind.StockDelivery -> can.viewGoodsIn
            AssignedTaskKind.LocationAudit -> can.viewLocations
        }
    }
    if (tasks.isEmpty()) {
        return
    }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        SectionTitle(
            text = stringResource(R.string.dashboard_assigned_to_me),
            actionLabel = stringResource(R.string.dashboard_view_all),
            onAction = {},
        )
        ListGroup(modifier = Modifier.fakeData()) {
            tasks.forEachIndexed { index, task ->
                if (index > 0) {
                    RowDivider()
                }
                ListRow(
                    title = task.reference,
                    subtitle = task.description,
                    leading = { IconTile(icon = task.kind.icon, tone = task.kind.iconTone) },
                    badges = { StatusBadge(text = task.status, tone = task.statusTone) },
                    onClick = {
                        navigator.open(
                            when (task.kind) {
                                AssignedTaskKind.DeliveryNote -> DeliveryNoteDetailRoute(task.reference)
                                AssignedTaskKind.StockDelivery -> StockDeliveryDetailRoute(task.reference)
                                AssignedTaskKind.LocationAudit -> LocationDetailRoute(task.reference)
                            },
                        )
                    },
                )
            }
        }
    }
}

@Composable
private fun StockMovementsSection() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        SectionTitle(
            text = stringResource(R.string.dashboard_stock_movements),
            actionLabel = stringResource(R.string.dashboard_view_all),
            onAction = { navigator.open(InventoryDashboardRoute) },
        )
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .fakeData()
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape)
                .padding(horizontal = 16.dp, vertical = 4.dp),
        ) {
            SampleStockMovements.forEachIndexed { index, movement ->
                if (index > 0) {
                    RowDivider()
                }
                StockMovementRow(movement)
            }
        }
    }
}

@Composable
private fun StockMovementRow(movement: StockMovement) {
    val colors = LocalAikuColors.current
    val toneColors = colors.tone(movement.tone)

    Row(
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 12.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(32.dp)
                .clip(CircleShape)
                .background(toneColors.container),
        ) {
            Icon(painter = painterResource(movement.icon), contentDescription = null, tint = toneColors.content, modifier = Modifier.size(16.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = buildAnnotatedString {
                    withStyle(SpanStyle(fontWeight = FontWeight.SemiBold)) {
                        append(movement.headline)
                    }
                    append(" · ")
                    append(movement.detail)
                },
                fontSize = 14.sp,
                lineHeight = 20.sp,
                color = colors.text,
            )
            Text(text = movement.meta, fontSize = 12.sp, lineHeight = 16.sp, color = colors.textTertiary)
        }
    }
}

private fun initialsOf(displayName: String, username: String): String {
    val initials = displayName
        .split(' ')
        .filter { it.isNotBlank() }
        .take(2)
        .joinToString(separator = "") { it.first().uppercase() }
    return initials.ifEmpty { username.take(1).uppercase() }
}

private const val SampleUnreadNotifications = 3

private data class GoodsOutSummary(
    val unassigned: Int,
    val queued: Int,
    val handling: Int,
    val blocked: Int,
    val packed: Int,
    val dispatchedToday: Int,
    val dispatchTarget: Int,
)

private val SampleGoodsOut = GoodsOutSummary(
    unassigned = 12,
    queued = 36,
    handling = 18,
    blocked = 2,
    packed = 23,
    dispatchedToday = 142,
    dispatchTarget = 228,
)

private data class GoodsInSummary(val dispatched: Int, val received: Int, val checked: Int)

private val SampleGoodsIn = GoodsInSummary(dispatched = 3, received = 2, checked = 1)

private data class InventorySummary(val skuCount: String, val low: Int, val critical: Int, val outOfStock: Int)

private val SampleInventory = InventorySummary(skuCount = "4,812", low = 17, critical = 5, outOfStock = 3)

private enum class AssignedTaskKind(@DrawableRes val icon: Int, val iconTone: Tone) {
    DeliveryNote(R.drawable.ic_dashboard_delivery_note, Tone.Primary),
    StockDelivery(R.drawable.ic_dashboard_stock_delivery, Tone.Success),
    LocationAudit(R.drawable.ic_dashboard_audit, Tone.Warn),
}

private data class AssignedTask(
    val kind: AssignedTaskKind,
    val reference: String,
    val status: String,
    val statusTone: Tone,
    val description: String,
)

private val SampleAssignedTasks = listOf(
    AssignedTask(AssignedTaskKind.DeliveryNote, "DN-24817", "Handling", Tone.Neutral, "Delivery note · 14 items"),
    AssignedTask(AssignedTaskKind.StockDelivery, "SD-1093", "Received", Tone.Success, "Stock delivery · 6 items to check"),
    AssignedTask(AssignedTaskKind.LocationAudit, "A-12-03", "In Process", Tone.Lime, "Stock audit · location"),
)

private data class StockMovement(
    val headline: String,
    val detail: String,
    val meta: String,
    @DrawableRes val icon: Int,
    val tone: Tone,
)

private val SampleStockMovements = listOf(
    StockMovement("Purchase +120", "SKU-00412 into A-03-02", "In · Sam P. · 10 min ago", R.drawable.ic_section_goods_in, Tone.Success),
    StockMovement("Picked −6", "SKU-00733 for DN-24790", "Out · You · 24 min ago", R.drawable.ic_section_goods_out, Tone.Primary),
    StockMovement("Location transfer 40", "B-01-04 → A-07-01", "No change · Jo K. · 1 h ago", R.drawable.ic_dashboard_transfer, Tone.Neutral),
    StockMovement("Adjustment −2", "SKU-00953 · damaged", "Out · Sam P. · 2 h ago", R.drawable.ic_dashboard_adjustment, Tone.Warn),
)

@Preview(heightDp = 1800)
@Composable
private fun DashboardScreenPreview() {
    AikuDroidTheme {
        DashboardScreen()
    }
}
