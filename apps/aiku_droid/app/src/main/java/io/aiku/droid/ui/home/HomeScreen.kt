package io.aiku.droid.ui.home

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.AvatarButton
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.CountBadge
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.initialsOf
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ProfileRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.WarehouseDashboardRoute
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.LocalTime

private const val MORNING_ENDS_AT = 12
private const val AFTERNOON_ENDS_AT = 18
private const val SHORTCUT_COLUMNS = 4

private data class Shortcut(@StringRes val label: Int, @DrawableRes val icon: Int, val route: Any, val sampleBadge: Int? = null)

@Composable
fun HomeScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val hasWarehouse = LocalWarehouses.current.isNotEmpty()
    val shortcuts = listOfNotNull(
        Shortcut(R.string.module_goods_out, R.drawable.ic_ui_goods_out, DeliveryNotesRoute, HomeSamples.GOODS_OUT_BADGE).takeIf { can.viewGoodsOut },
        Shortcut(R.string.module_goods_in, R.drawable.ic_ui_goods_in, StockDeliveriesRoute).takeIf { can.viewGoodsIn },
        Shortcut(R.string.module_inventory, R.drawable.ic_ui_pallet, SkusRoute).takeIf { can.viewInventory },
        Shortcut(R.string.module_locations, R.drawable.ic_ui_locations, LocationsRoute).takeIf { can.viewLocations },
    )

    AppScreen(header = { HomeHeader() }, activeTab = MainTab.Home, spacing = 20.dp) {
        ClockingCard(isClockedIn = hasWarehouse, onOpen = { navigator.open(ClockingRoute) })
        if (hasWarehouse && shortcuts.isNotEmpty()) {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                GroupLabel(text = stringResource(R.string.home_shortcuts))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    shortcuts.forEach { shortcut ->
                        ShortcutTile(shortcut = shortcut, onClick = { navigator.open(shortcut.route) }, modifier = Modifier.weight(1f))
                    }
                    repeat(SHORTCUT_COLUMNS - shortcuts.size) { Box(modifier = Modifier.weight(1f)) }
                }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            GroupLabel(text = stringResource(R.string.home_your_modules))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                if (hasWarehouse) {
                    ModuleTile(
                        icon = R.drawable.ic_ui_warehouse,
                        name = stringResource(R.string.home_module_warehouse),
                        subtitle = warehouseKicker(),
                        isSample = false,
                        onClick = { navigator.open(WarehouseDashboardRoute) },
                        modifier = Modifier.weight(1f),
                    )
                }
                ModuleTile(
                    icon = R.drawable.ic_ui_clock,
                    name = stringResource(R.string.module_clocking),
                    subtitle = if (hasWarehouse) HomeSamples.WEEK_HOURS else HomeSamples.STAFF_WEEK_HOURS,
                    isSample = true,
                    onClick = { navigator.open(ClockingRoute) },
                    modifier = Modifier.weight(1f),
                )
                if (!hasWarehouse) {
                    Box(modifier = Modifier.weight(1f))
                }
            }
            if (!hasWarehouse) {
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
private fun ClockingCard(isClockedIn: Boolean, onOpen: () -> Unit) {
    val colors = LocalAikuColors.current
    val (iconBackground, iconTint) = if (isClockedIn) colors.successSoft to colors.success else colors.background to colors.textTertiary

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
        modifier = Modifier
            .fillMaxWidth()
            .fakeData()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(16.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(44.dp).clip(CircleShape).background(iconBackground)) {
            Icon(painter = painterResource(R.drawable.ic_ui_clock), contentDescription = null, tint = iconTint, modifier = Modifier.size(22.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = if (isClockedIn) HomeSamples.CLOCKED_IN else HomeSamples.CLOCKED_OUT, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = if (isClockedIn) HomeSamples.CLOCKED_IN_SINCE else HomeSamples.LAST_CLOCKING, fontSize = 13.sp, color = colors.textTertiary)
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

    Box(modifier = modifier) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .fillMaxWidth()
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
                maxLines = 1,
            )
        }
        if (shortcut.sampleBadge != null) {
            CountBadge(
                count = shortcut.sampleBadge,
                modifier = Modifier.align(Alignment.TopEnd).offset(x = (-5).dp, y = 3.dp).fakeData(cornerRadius = 12.dp).padding(3.dp),
            )
        }
    }
}

@Composable
private fun ModuleTile(
    @DrawableRes icon: Int,
    name: String,
    subtitle: String,
    isSample: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .heightIn(min = 112.dp)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(14.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(40.dp).clip(RoundedCornerShape(10.dp)).background(colors.soft)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
        }
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(
                text = subtitle,
                fontSize = 12.sp,
                lineHeight = 16.sp,
                color = colors.textTertiary,
                modifier = if (isSample) Modifier.fakeData(cornerRadius = 4.dp).padding(2.dp) else Modifier,
            )
        }
    }
}
