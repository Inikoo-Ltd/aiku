package io.aiku.droid.ui.profile

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
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.BuildConfig
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.states.StatePreview
import io.aiku.droid.ui.navigation.ClockingErrorRoute
import io.aiku.droid.ui.navigation.StatePreviewRoute
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppearanceRoute
import io.aiku.droid.ui.navigation.TimesheetsRoute
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.LanguageRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.NoWarehouseAccess
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private object ProfileSample {
    const val ROLE = "Warehouse staff"
    const val LANGUAGE = "English"
    const val NOTIFICATIONS = "On"
    const val WEEK_TOTAL = "6h 13m"
    const val SYNC_WAITING = 3
}

private enum class ProfileScanMode(@StringRes val label: Int, @StringRes val caption: Int?) {
    Automatic(R.string.profile_scan_automatic, R.string.profile_scan_automatic_caption),
    ScannerDevice(R.string.profile_scan_device, null),
    Camera(R.string.profile_scan_camera, null),
}

private fun initialsOf(displayName: String, username: String): String {
    val words = displayName.split(' ').filter { it.isNotBlank() }
    val letters = if (words.isNotEmpty()) words.take(2).map { it.first() } else username.take(1).toList()
    return letters.joinToString("").uppercase()
}

@Composable
fun ProfileScreen() {
    val colors = LocalAikuColors.current
    val staff = LocalStaff.current
    val warehouse = LocalWarehouse.current
    val hasWarehouse = LocalWarehouses.current.isNotEmpty()
    val navigator = LocalAppNavigator.current

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.profile_title),
                kicker = stringResource(R.string.profile_kicker),
            )
        },
    ) {
        UserCard(displayName = staff.displayName, username = staff.username)

        if (hasWarehouse) {
            ProfileSection(title = stringResource(R.string.profile_org_warehouse)) {
                WarehouseRow(label = "${warehouse.organisation.name} · ${warehouse.name}", onChange = navigator::showWarehouseSwitcher)
            }
        }

        ProfileSection(title = stringResource(R.string.profile_scanning)) {
            ScanningGroup()
        }

        ProfileSection(title = stringResource(R.string.profile_work)) {
            ListGroup {
                AppRow(
                    label = stringResource(R.string.profile_clocking),
                    value = stringResource(R.string.profile_clocked_in),
                    valueColor = colors.success,
                    isSampleValue = true,
                    onClick = { navigator.open(ClockingRoute) },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_clocking_history),
                    value = stringResource(R.string.profile_clocking_this_week, ProfileSample.WEEK_TOTAL),
                    isSampleValue = true,
                    onClick = { navigator.open(TimesheetsRoute) },
                )
            }
        }

        ProfileSection(title = stringResource(R.string.profile_app)) {
            ListGroup {
                AppRow(
                    label = stringResource(R.string.profile_appearance),
                    value = stringResource(R.string.profile_appearance_caption),
                    onClick = { navigator.open(AppearanceRoute) },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_language),
                    value = ProfileSample.LANGUAGE,
                    isSampleValue = true,
                    onClick = { navigator.open(LanguageRoute) },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_offline_sync),
                    value = pluralStringResource(R.plurals.profile_sync_waiting, ProfileSample.SYNC_WAITING, ProfileSample.SYNC_WAITING),
                    isSampleValue = true,
                    onClick = { navigator.open(SyncQueueRoute) },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_notifications),
                    value = ProfileSample.NOTIFICATIONS,
                    isSampleValue = true,
                    onClick = { navigator.openTab(MainTab.Inbox) },
                )
                RowDivider()
                AppRow(label = stringResource(R.string.profile_version), value = BuildConfig.VERSION_NAME)
            }
        }

        AikuButton(
            text = stringResource(R.string.profile_sign_out),
            onClick = navigator::signOut,
            style = ButtonStyle.Danger,
            icon = R.drawable.ic_ui_log_out,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )

        ProfileSection(title = stringResource(R.string.states_previews)) {
            SkeletonPreviews()
        }
    }
}

@Composable
private fun SkeletonPreviews() {
    val navigator = LocalAppNavigator.current
    val previews = listOf(
        R.string.states_preview_offline to StatePreviewRoute(StatePreview.Offline.kind),
        R.string.states_preview_session to StatePreviewRoute(StatePreview.SessionExpired.kind),
        R.string.states_preview_server to StatePreviewRoute(StatePreview.ServerError.kind),
        R.string.states_preview_loading to StatePreviewRoute(StatePreview.Loading.kind),
        R.string.states_preview_no_access to StatePreviewRoute(StatePreview.NoAccess.kind),
        R.string.states_preview_not_found to StatePreviewRoute(StatePreview.NotFound.kind),
        R.string.states_preview_camera to StatePreviewRoute(StatePreview.CameraBlocked.kind),
        R.string.states_preview_action_failed to StatePreviewRoute(StatePreview.ActionFailed.kind),
        R.string.states_preview_clocking_qr to ClockingErrorRoute("qr_invalid"),
        R.string.states_preview_clocking_location to ClockingErrorRoute("location_blocked"),
        R.string.states_preview_clocking_pin to ClockingErrorRoute("no_pin"),
        R.string.states_preview_clocking_offline to ClockingErrorRoute("offline"),
    )

    ListGroup(modifier = Modifier.fakeData()) {
        previews.forEachIndexed { index, (label, route) ->
            if (index > 0) {
                RowDivider()
            }
            ListRow(title = stringResource(label), titleSize = 15, onClick = { navigator.open(route) })
        }
    }
}

@Composable
private fun ProfileSection(title: String, content: @Composable ColumnScope.() -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
        GroupLabel(text = title)
        content()
    }
}

@Composable
private fun UserCard(displayName: String, username: String) {
    val colors = LocalAikuColors.current

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
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(56.dp)
                .clip(CircleShape)
                .background(colors.navActive),
        ) {
            Text(text = initialsOf(displayName, username), fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.navAccent)
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = displayName,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = username,
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                Text(text = " · ", fontSize = 13.sp, color = colors.textTertiary)
                Text(
                    text = ProfileSample.ROLE,
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                    maxLines = 1,
                    modifier = Modifier
                        .fakeData(4.dp)
                        .padding(horizontal = 4.dp, vertical = 1.dp),
                )
            }
        }
    }
}

@Composable
private fun WarehouseRow(label: String, onChange: () -> Unit) {
    val colors = LocalAikuColors.current
    val changeLabel = stringResource(R.string.profile_change_warehouse)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .clickable(role = Role.Button, onClickLabel = changeLabel, onClick = onChange)
            .padding(horizontal = 16.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_warehouse), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        Text(
            text = label,
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.text,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        Text(text = stringResource(R.string.profile_change), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
    }
}

@Composable
private fun ScanningGroup() {
    var mode by rememberSaveable { mutableStateOf(ProfileScanMode.Automatic) }
    var isSoundOn by rememberSaveable { mutableStateOf(true) }
    var isVibrationOn by rememberSaveable { mutableStateOf(true) }

    ListGroup(modifier = Modifier.fakeData(12.dp)) {
        Column(modifier = Modifier.selectableGroup()) {
            ProfileScanMode.entries.forEach { option ->
                ScanModeRow(option = option, isSelected = option == mode, onSelect = { mode = option })
                RowDivider()
            }
        }
        ScanCheckRow(label = stringResource(R.string.profile_scan_sound), isChecked = isSoundOn, onToggle = { isSoundOn = it })
        RowDivider()
        ScanCheckRow(label = stringResource(R.string.profile_scan_vibrate), isChecked = isVibrationOn, onToggle = { isVibrationOn = it })
    }
}

@Composable
private fun ScanModeRow(option: ProfileScanMode, isSelected: Boolean, onSelect: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .selectable(selected = isSelected, role = Role.RadioButton, onClick = onSelect)
            .padding(horizontal = 16.dp, vertical = 8.dp),
    ) {
        RadioButton(
            selected = isSelected,
            onClick = null,
            colors = RadioButtonDefaults.colors(selectedColor = colors.link, unselectedColor = colors.borderStrong),
        )
        Column(modifier = Modifier.weight(1f)) {
            Text(text = stringResource(option.label), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            if (option.caption != null) {
                Text(text = stringResource(option.caption), fontSize = 12.sp, color = colors.textTertiary)
            }
        }
    }
}

@Composable
private fun ScanCheckRow(label: String, isChecked: Boolean, onToggle: (Boolean) -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 52.dp)
            .toggleable(value = isChecked, role = Role.Checkbox, onValueChange = onToggle)
            .padding(horizontal = 16.dp),
    ) {
        Text(text = label, fontSize = 15.sp, color = colors.text, modifier = Modifier.weight(1f))
        Checkbox(
            checked = isChecked,
            onCheckedChange = null,
            colors = CheckboxDefaults.colors(
                checkedColor = colors.link,
                uncheckedColor = colors.borderStrong,
                checkmarkColor = colors.surface,
            ),
        )
    }
}

@Composable
private fun AppRow(
    label: String,
    value: String,
    valueColor: Color? = null,
    isSampleValue: Boolean = false,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 52.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 16.dp),
    ) {
        Text(text = label, fontSize = 15.sp, color = colors.text, modifier = Modifier.weight(1f))
        Text(
            text = value,
            fontSize = 14.sp,
            color = valueColor ?: colors.textTertiary,
            maxLines = 1,
            modifier = if (isSampleValue) Modifier.fakeData(4.dp).padding(horizontal = 6.dp, vertical = 2.dp) else Modifier,
        )
    }
}

@Preview(heightDp = 1500)
@Composable
private fun ProfileScreenPreview() {
    AikuDroidTheme {
        ProfileScreen()
    }
}

@Preview(heightDp = 1100)
@Composable
private fun ProfileScreenWithoutWarehousePreview() {
    AikuDroidTheme {
        CompositionLocalProvider(LocalWarehouses provides emptyList(), LocalWarehouse provides NoWarehouseAccess) {
            ProfileScreen()
        }
    }
}
