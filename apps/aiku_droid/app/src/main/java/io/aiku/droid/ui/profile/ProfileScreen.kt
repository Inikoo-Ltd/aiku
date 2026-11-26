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
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.BuildConfig
import io.aiku.droid.R
import io.aiku.droid.data.api.StaffProfile
import io.aiku.droid.data.settings.AppLanguage
import io.aiku.droid.data.settings.ScanMode
import io.aiku.droid.data.settings.ScanPreferences
import io.aiku.droid.ui.clocking.LocalClocking
import io.aiku.droid.ui.clocking.clockingWeekTotal
import io.aiku.droid.ui.clocking.rememberClockingNow
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.LocalOfflineIndicator
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppearanceRoute
import io.aiku.droid.ui.navigation.ChangePasswordRoute
import io.aiku.droid.ui.navigation.ClockingErrorRoute
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.LanguageRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalStartScreen
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.NoWarehouseAccess
import io.aiku.droid.ui.navigation.StatePreviewRoute
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.navigation.TimesheetsRoute
import io.aiku.droid.ui.states.StatePreview
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class ProfileScanMode(val mode: ScanMode, @StringRes val label: Int, @StringRes val caption: Int?) {
    Automatic(ScanMode.Automatic, R.string.profile_scan_automatic, R.string.profile_scan_automatic_caption),
    ScannerDevice(ScanMode.Scanner, R.string.profile_scan_device, null),
    Camera(ScanMode.Camera, R.string.profile_scan_camera, null),
}

private fun initialsOf(displayName: String, username: String): String {
    val words = displayName.split(' ').filter { it.isNotBlank() }
    val letters = if (words.isNotEmpty()) words.take(2).map { it.first() } else username.take(1).toList()
    return letters.joinToString("").uppercase()
}

@Composable
fun ProfileScreen(
    details: StaffProfile? = null,
    language: AppLanguage = AppLanguage.English,
    isRefreshing: Boolean = false,
    onRefresh: () -> Unit = {},
    scanning: ScanPreferences = ScanPreferences(),
    onScanningChange: (ScanPreferences) -> Unit = {},
) {
    val colors = LocalAikuColors.current
    val context = LocalContext.current
    val staff = LocalStaff.current
    val warehouse = LocalWarehouse.current
    val hasWarehouse = LocalWarehouses.current.isNotEmpty()
    val syncWaiting by LocalOfflineIndicator.current.waitingCount.collectAsStateWithLifecycle()
    val navigator = LocalAppNavigator.current
    val clocking = LocalClocking.current
    val clockingState by clocking.state.collectAsStateWithLifecycle()
    val clockingStatus = clockingState.status
    val weekTotal = clockingWeekTotal(clockingState, rememberClockingNow())
    val noValue = stringResource(R.string.clocking_no_value)
    var areNotificationsAllowed by remember { mutableStateOf(context.areWorkNotificationsAllowed()) }
    val startScreen = LocalStartScreen.current
    var isChoosingStartScreen by rememberSaveable { mutableStateOf(false) }

    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { areNotificationsAllowed = context.areWorkNotificationsAllowed() }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.profile_title),
                kicker = stringResource(R.string.profile_kicker),
            )
        },
        refresh = PullRefresh(
            isRefreshing = isRefreshing,
            onRefresh = {
                onRefresh()
                clocking.refresh()
            },
        ),
    ) {
        UserCard(
            displayName = staff.displayName,
            username = staff.username,
            position = details?.position,
            organisation = details?.organisation?.name,
        )

        if (hasWarehouse) {
            ProfileSection(title = stringResource(R.string.profile_org_warehouse)) {
                WarehouseRow(label = "${warehouse.organisation.name} · ${warehouse.name}", onChange = navigator::showWarehouseSwitcher)
            }
        }

        ProfileSection(title = stringResource(R.string.profile_scanning)) {
            ScanningGroup(preferences = scanning, onChange = onScanningChange)
        }

        ProfileSection(title = stringResource(R.string.profile_work)) {
            ListGroup {
                AppRow(
                    label = stringResource(R.string.profile_clocking),
                    value = when (clockingStatus?.isClockedIn) {
                        true -> stringResource(R.string.clocking_status_clocked_in)
                        false -> stringResource(R.string.clocking_status_clocked_out)
                        null -> noValue
                    },
                    valueColor = if (clockingStatus?.isClockedIn == true) colors.success else null,
                    onClick = { navigator.open(ClockingRoute) },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_clocking_history),
                    value = weekTotal?.let { stringResource(R.string.profile_clocking_this_week, it) } ?: noValue,
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
                    value = stringResource(language.nativeName),
                    onClick = { navigator.open(LanguageRoute) },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_open_on),
                    value = stringResource(if (startScreen.isOpeningOnLastScreen) R.string.profile_open_on_last_screen else R.string.profile_open_on_home),
                    onClick = { isChoosingStartScreen = true },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_offline_sync),
                    value = pluralStringResource(R.plurals.profile_sync_waiting, syncWaiting, syncWaiting),
                    onClick = { navigator.open(SyncQueueRoute) },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_notifications),
                    value = stringResource(notificationsValue(areNotificationsAllowed)),
                    onClick = { context.openNotificationSettings() },
                )
                RowDivider()
                AppRow(
                    label = stringResource(R.string.profile_version),
                    value = stringResource(R.string.app_version_value, BuildConfig.VERSION_NAME, BuildConfig.VERSION_CODE),
                )
            }
        }

        ProfileSection(title = stringResource(R.string.profile_security)) {
            ListGroup {
                AppRow(
                    label = stringResource(R.string.profile_change_password),
                    onClick = { navigator.open(ChangePasswordRoute) },
                )
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

        if (BuildConfig.DEBUG) {
            ProfileSection(title = stringResource(R.string.states_previews)) {
                SkeletonPreviews()
            }
        }
    }

    if (isChoosingStartScreen) {
        StartScreenSheet(
            isOpeningOnLastScreen = startScreen.isOpeningOnLastScreen,
            onChoose = { isOn ->
                isChoosingStartScreen = false
                startScreen.updateOpenOnLastScreen(isOn)
            },
            onDismiss = { isChoosingStartScreen = false },
        )
    }
}

@Composable
private fun StartScreenSheet(isOpeningOnLastScreen: Boolean, onChoose: (Boolean) -> Unit, onDismiss: () -> Unit) {
    AikuBottomSheet(title = stringResource(R.string.profile_open_on), onDismiss = onDismiss) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.selectableGroup()) {
            ChoiceRow(
                title = stringResource(R.string.profile_open_on_home),
                subtitle = stringResource(R.string.profile_open_on_home_caption),
                selected = !isOpeningOnLastScreen,
                onClick = { onChoose(false) },
            )
            ChoiceRow(
                title = stringResource(R.string.profile_open_on_last_screen),
                subtitle = stringResource(R.string.profile_open_on_last_screen_caption),
                selected = isOpeningOnLastScreen,
                onClick = { onChoose(true) },
            )
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
private fun UserCard(displayName: String, username: String, position: String?, organisation: String?) {
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
                if (position != null) {
                    Text(text = " · ", fontSize = 13.sp, color = colors.textTertiary)
                    Text(
                        text = position,
                        fontSize = 13.sp,
                        color = colors.textTertiary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f, fill = false),
                    )
                }
            }
            if (organisation != null) {
                Text(
                    text = organisation,
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
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
private fun ScanningGroup(preferences: ScanPreferences, onChange: (ScanPreferences) -> Unit) {
    ListGroup {
        Column(modifier = Modifier.selectableGroup()) {
            ProfileScanMode.entries.forEach { option ->
                ScanModeRow(option = option, isSelected = option.mode == preferences.mode, onSelect = { onChange(preferences.copy(mode = option.mode)) })
                RowDivider()
            }
        }
        ScanCheckRow(
            label = stringResource(R.string.profile_scan_sound),
            isChecked = preferences.isSoundOn,
            onToggle = { onChange(preferences.copy(isSoundOn = it)) },
        )
        RowDivider()
        ScanCheckRow(
            label = stringResource(R.string.profile_scan_vibrate),
            isChecked = preferences.isVibrationOn,
            onToggle = { onChange(preferences.copy(isVibrationOn = it)) },
        )
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
    value: String? = null,
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
        if (value != null) {
            Text(
                text = value,
                fontSize = 14.sp,
                color = valueColor ?: colors.textTertiary,
                maxLines = 1,
                modifier = if (isSampleValue) Modifier.fakeData(4.dp).padding(horizontal = 6.dp, vertical = 2.dp) else Modifier,
            )
        }
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
