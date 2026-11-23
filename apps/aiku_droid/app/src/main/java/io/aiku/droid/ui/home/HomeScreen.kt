package io.aiku.droid.ui.home

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
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
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.ui.clocking.ClockingSummaryLines
import io.aiku.droid.ui.clocking.LocalClocking
import io.aiku.droid.ui.clocking.clockingSummaryLines
import io.aiku.droid.ui.clocking.clockingWeekTotal
import io.aiku.droid.ui.clocking.rememberClockingNow
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.AvatarButton
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.CountBadge
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.LocalOfflineIndicator
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.SkeletonBlock
import io.aiku.droid.ui.components.goodsInEntry
import io.aiku.droid.ui.components.initialsOf
import io.aiku.droid.ui.components.lastUpdatedLabel
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.mywork.LocalWorkBadges
import io.aiku.droid.ui.mywork.goodsOutUnassignedBadge
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ProfileRoute
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.navigation.WarehouseDashboardRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute
import io.aiku.droid.ui.theme.LocalAikuColors
import io.aiku.droid.ui.update.AppUpdateBanner
import io.aiku.droid.update.AppUpdate
import java.time.Instant
import java.time.LocalTime

private const val ATTENTION_BORDER_ALPHA = 0.35f
private const val MORNING_ENDS_AT = 12
private const val AFTERNOON_ENDS_AT = 18
private const val SHORTCUT_COLUMNS = 4

private data class Shortcut(@StringRes val label: Int, @DrawableRes val icon: Int, val route: Any, val badge: Int? = null)

@Composable
fun HomeScreen(update: AppUpdate.Available? = null, onDismissUpdate: () -> Unit = {}) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val hasWarehouse = LocalWarehouses.current.isNotEmpty()
    val clocking = LocalClocking.current
    val clockingState by clocking.state.collectAsStateWithLifecycle()
    val now = rememberClockingNow()
    val weekTotal = clockingWeekTotal(clockingState, now)
    val workBadges = LocalWorkBadges.current
    val needYou by LocalOfflineIndicator.current.needsYouCount.collectAsStateWithLifecycle()
    val badges by workBadges.badges.collectAsStateWithLifecycle()
    val shortcuts = listOfNotNull(
        Shortcut(R.string.module_goods_out, R.drawable.ic_fa_arrow_from_left, GoodsOutBacklogRoute, badges.goodsOutUnassignedBadge).takeIf { can.viewGoodsOut },
        goodsInEntry(can)?.let { Shortcut(R.string.module_goods_in, R.drawable.ic_fa_arrow_to_bottom, it) },
        Shortcut(R.string.module_inventory, R.drawable.ic_fa_pallet_alt, InventoryDashboardRoute).takeIf { can.viewInventory },
        Shortcut(R.string.module_locations, R.drawable.ic_fa_inventory, WarehouseOverviewRoute).takeIf { can.viewLocations },
    )

    LaunchedEffect(Unit) {
        clocking.refresh()
        workBadges.refresh()
    }

    AppScreen(
        header = { HomeHeader() },
        activeTab = MainTab.Home,
        spacing = 20.dp,
        showNeedYou = false,
        refresh = PullRefresh(
            isRefreshing = clockingState.isRefreshing,
            onRefresh = {
                clocking.refresh(isPull = true)
                workBadges.refresh()
            },
        ),
    ) {
        if (update != null) {
            AppUpdateBanner(update = update, onDismiss = onDismissUpdate)
        }
        ClockingCard(
            summary = clockingSummaryLines(clockingState, now),
            weekTotal = weekTotal?.let { stringResource(R.string.clocking_summary_this_week, it) },
            lastUpdated = clockingState.lastUpdated,
            onOpen = { navigator.open(ClockingRoute) },
        )
        ConflictsRow(needYou = needYou, onOpen = { navigator.open(ResolveConflictsRoute) })
        if (hasWarehouse && shortcuts.isNotEmpty()) {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                GroupLabel(text = stringResource(R.string.home_shortcuts))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                    shortcuts.forEach { shortcut ->
                        ShortcutTile(shortcut = shortcut, onClick = { navigator.open(shortcut.route) }, modifier = Modifier.weight(1f))
                    }
                    repeat(SHORTCUT_COLUMNS - shortcuts.size) { Box(modifier = Modifier.weight(1f)) }
                }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            GroupLabel(text = stringResource(R.string.home_your_modules))
            if (hasWarehouse) {
                ModuleRow(
                    icon = R.drawable.ic_fa_warehouse,
                    name = stringResource(R.string.home_module_warehouse),
                    subtitle = warehouseKicker(),
                    onClick = { navigator.open(WarehouseDashboardRoute) },
                )
            } else {
                Text(
                    text = stringResource(R.string.home_staff_note),
                    fontSize = 13.sp,
                    lineHeight = 19.sp,
                    color = colors.textTertiary,
                    modifier = Modifier.padding(horizontal = 4.dp),
                )
            }
        }
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 56.dp)
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape)
                .clickable(role = Role.Button) { navigator.open(ProfileRoute) }
                .padding(horizontal = 14.dp),
        ) {
            Text(text = stringResource(R.string.home_profile_settings), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text, modifier = Modifier.weight(1f))
            Icon(painter = painterResource(R.drawable.ic_ui_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun HomeHeader() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val staff = LocalStaff.current
    val warehouse = LocalWarehouse.current
    val firstName = staff.displayName.substringBefore(' ').ifBlank { staff.username }
    val greeting = when (LocalTime.now().hour) {
        in 0 until MORNING_ENDS_AT -> stringResource(R.string.home_greeting_morning, firstName)
        in MORNING_ENDS_AT until AFTERNOON_ENDS_AT -> stringResource(R.string.home_greeting_afternoon, firstName)
        else -> stringResource(R.string.home_greeting_evening, firstName)
    }
    val context = listOf(warehouse.organisation.name, warehouse.name).filter { it.isNotBlank() }.joinToString(" · ").ifBlank { staff.username }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 20.dp),
    ) {
        AvatarButton(
            initials = initialsOf(staff.displayName.ifBlank { staff.username }),
            description = stringResource(R.string.home_profile_description),
            onClick = { navigator.open(ProfileRoute) },
        )
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = context, fontSize = 12.sp, color = colors.onNavSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(text = greeting, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.onNav, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

@Composable
private fun ClockingCard(summary: ClockingSummaryLines?, weekTotal: String?, lastUpdated: Instant?, onOpen: () -> Unit) {
    val colors = LocalAikuColors.current
    val isClockedIn = summary?.isClockedIn == true
    val (iconBackground, iconTint) = if (isClockedIn) colors.successSoft to colors.success else colors.background to colors.textTertiary

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(16.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(44.dp).clip(CircleShape).background(iconBackground)) {
            Icon(painter = painterResource(R.drawable.ic_fa_user_clock), contentDescription = null, tint = iconTint, modifier = Modifier.size(22.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(if (summary == null) 8.dp else 2.dp)) {
            if (summary == null) {
                SkeletonBlock(modifier = Modifier.fillMaxWidth(0.6f).height(14.dp))
                SkeletonBlock(modifier = Modifier.fillMaxWidth(0.45f).height(11.dp))
            } else {
                Text(text = summary.title, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = summary.subtitle, fontSize = 13.sp, color = colors.textTertiary)
                if (weekTotal != null) {
                    Text(text = weekTotal, fontSize = 13.sp, color = colors.textTertiary)
                }
                if (lastUpdated != null) {
                    Text(text = lastUpdatedLabel(lastUpdated), fontSize = 12.sp, color = colors.textTertiary)
                }
            }
        }
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .heightIn(min = 44.dp)
                .clip(RoundedCornerShape(8.dp))
                .background(colors.primary)
                .border(1.dp, colors.primaryBorder, RoundedCornerShape(8.dp))
                .clickable(role = Role.Button, onClick = onOpen)
                .padding(horizontal = 14.dp),
        ) {
            Text(text = stringResource(R.string.home_clocking), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.onPrimary)
        }
    }
}

@Composable
private fun ShortcutTile(shortcut: Shortcut, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Box(modifier = modifier.fillMaxHeight()) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight()
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape)
                .clickable(role = Role.Button, onClick = onClick)
                .padding(start = 4.dp, end = 4.dp, top = 12.dp, bottom = 10.dp),
        ) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(40.dp).clip(RoundedCornerShape(10.dp)).background(colors.soft)) {
                Icon(painter = painterResource(shortcut.icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
            }
            Text(
                text = stringResource(shortcut.label),
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.textSecondary,
                textAlign = TextAlign.Center,
                lineHeight = 15.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }
        if (shortcut.badge != null) {
            CountBadge(
                count = shortcut.badge,
                modifier = Modifier.align(Alignment.TopEnd).offset(x = (-5).dp, y = 3.dp).padding(3.dp),
            )
        }
    }
}

@Composable
private fun ConflictsRow(needYou: Int, onOpen: () -> Unit) {
    val colors = LocalAikuColors.current
    val needsAttention = needYou > 0

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(if (needsAttention) colors.warnSoft else colors.surface)
            .border(1.dp, if (needsAttention) colors.warnInk.copy(alpha = ATTENTION_BORDER_ALPHA) else colors.border, CardShape)
            .clickable(role = Role.Button, onClick = onOpen)
            .padding(14.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier.size(44.dp).clip(RoundedCornerShape(10.dp)).background(if (needsAttention) colors.surface else colors.soft),
        ) {
            Icon(
                painter = painterResource(R.drawable.ic_fa_exclamation_triangle),
                contentDescription = null,
                tint = if (needsAttention) colors.warnInk else colors.link,
                modifier = Modifier.size(22.dp),
            )
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = stringResource(R.string.sync_conflicts_title), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = if (needsAttention) colors.warnInk else colors.text)
            Text(
                text = if (needsAttention) {
                    pluralStringResource(R.plurals.offline_indicator_need_you, needYou, needYou)
                } else {
                    stringResource(R.string.offline_stock_conflicts_empty_title)
                },
                fontSize = 13.sp,
                fontWeight = if (needsAttention) FontWeight.SemiBold else FontWeight.Normal,
                color = if (needsAttention) colors.warnInk else colors.textTertiary,
            )
        }
        Icon(painter = painterResource(R.drawable.ic_ui_chevron_right), contentDescription = null, tint = if (needsAttention) colors.warnInk else colors.faint, modifier = Modifier.size(20.dp))
    }
}

@Composable
private fun ModuleRow(
    @DrawableRes icon: Int,
    name: String,
    subtitle: String,
    onClick: () -> Unit,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(14.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(44.dp).clip(RoundedCornerShape(10.dp)).background(colors.soft)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(24.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = name, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
        }
        Icon(painter = painterResource(R.drawable.ic_ui_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
    }
}
