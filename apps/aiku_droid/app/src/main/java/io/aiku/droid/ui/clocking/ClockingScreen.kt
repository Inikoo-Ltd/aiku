package io.aiku.droid.ui.clocking

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.ClockScanQrRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.TimesheetsRoute
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.delay

private const val LOCATION_DETECTION_MILLIS = 1500L
private val MethodTabsShape = RoundedCornerShape(12.dp)
private val MethodTabShape = RoundedCornerShape(8.dp)

private enum class ClockingLocationState { Found, Detecting }

@Composable
fun ClockingScreen() {
    val navigator = LocalAppNavigator.current
    var showAdjustment by remember { mutableStateOf(false) }
    var showWorkingHours by remember { mutableStateOf(false) }
    val methods = ClockingEnabledMethodsSample

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_title),
                kicker = clockingKicker(LocalStaff.current.displayName),
                tabs = clockingSectionTabs(active = ClockingSection.ClockInOut, onAdjustment = { showAdjustment = true }),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        if (methods.isEmpty()) {
            ClockingNoMethodCard()
        } else {
            var selectedMethod by rememberSaveable { mutableStateOf(methods.first()) }

            ClockingStatusCard(status = ClockingTodaySample)
            ClockingMethodTabs(methods = methods, selected = selectedMethod, onSelect = { selectedMethod = it })
            when (selectedMethod) {
                ClockingMethod.QrCode -> ClockingQrCodeMethod(
                    onOpenCamera = {
                        showWorkingHours = ClockingOrganisationHasShiftsSample
                        if (!ClockingOrganisationHasShiftsSample) {
                            navigator.open(ClockScanQrRoute)
                        }
                    },
                )
                ClockingMethod.Pin -> ClockingPinMethod()
                ClockingMethod.Barcode -> ClockingBarcodeMethod()
                ClockingMethod.CameraQr -> ClockingCameraQrMethod()
            }
        }
    }

    if (showWorkingHours) {
        WorkingHoursSheet(onDismiss = { showWorkingHours = false })
    }
    if (showAdjustment) {
        ClockingAdjustmentSheet(
            date = CLOCKING_ADJUSTMENT_DATE_SAMPLE,
            onDismiss = { showAdjustment = false },
            onRequested = { navigator.switchSection(TimesheetsRoute) },
        )
    }
}

@Composable
private fun ClockingNoMethodCard() {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ClockingPanelShape)
            .background(colors.surface)
            .border(1.dp, colors.border, ClockingPanelShape)
            .padding(horizontal = 20.dp, vertical = 28.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(52.dp).clip(CircleShape).background(colors.background)) {
            Icon(painter = painterResource(R.drawable.ic_ui_clock), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(26.dp))
        }
        Text(text = stringResource(R.string.clocking_no_methods_title), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
        Text(
            text = stringResource(R.string.clocking_no_methods_body),
            fontSize = 14.sp,
            lineHeight = 20.sp,
            color = colors.textTertiary,
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun ClockingStatusCard(status: ClockingStatusSample) {
    val colors = LocalAikuColors.current
    var isOpen by rememberSaveable { mutableStateOf(true) }
    val statusColor = if (status.isClockedIn) colors.success else colors.danger
    val statusContainer = if (status.isClockedIn) colors.successSoft else colors.dangerSoft
    val toggleLabel = stringResource(if (isOpen) R.string.clocking_details_hide else R.string.clocking_details_show)
    val noValue = stringResource(R.string.clocking_no_value)

    Column(
        modifier = Modifier
            .fakeData(cornerRadius = 16.dp)
            .fillMaxWidth()
            .clip(ClockingPanelShape)
            .background(colors.surface)
            .border(1.dp, colors.border, ClockingPanelShape),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier.fillMaxWidth().padding(16.dp),
        ) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(44.dp).clip(CircleShape).background(statusContainer)) {
                Icon(
                    painter = painterResource(if (status.isClockedIn) R.drawable.ic_ui_check else R.drawable.ic_ui_close),
                    contentDescription = null,
                    tint = statusColor,
                    modifier = Modifier.size(22.dp),
                )
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = stringResource(if (status.isClockedIn) R.string.clocking_status_clocked_in else R.string.clocking_status_clocked_out),
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = statusColor,
                )
                Text(
                    text = stringResource(
                        if (status.isClockedIn) R.string.clocking_status_since else R.string.clocking_status_last_clock_out,
                        status.statusTime,
                    ),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                )
            }
            Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = stringResource(R.string.clocking_status_date), fontSize = 11.sp, color = colors.textTertiary)
                Text(text = status.date, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            }
        }
        HorizontalDivider(thickness = 1.dp, color = colors.divider)
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .background(colors.surfaceMuted)
                .clickable(role = Role.Button, onClickLabel = toggleLabel, onClick = { isOpen = !isOpen })
                .padding(horizontal = 16.dp),
        ) {
            Text(
                text = stringResource(R.string.clocking_details, status.sessions.size),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.textSecondary,
                modifier = Modifier.weight(1f),
            )
            Icon(
                painter = painterResource(R.drawable.ic_ui_chevron_down),
                contentDescription = null,
                tint = colors.textSecondary,
                modifier = Modifier.size(18.dp).rotate(if (isOpen) 180f else 0f),
            )
        }
        if (isOpen) {
            HorizontalDivider(thickness = 1.dp, color = colors.divider)
            Column(
                verticalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 16.dp),
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        ClockingSummaryTile(label = stringResource(R.string.clocking_first_clock_in), value = status.firstClockIn, modifier = Modifier.weight(1f))
                        ClockingSummaryTile(label = stringResource(R.string.clocking_last_clock_out), value = status.lastClockOut ?: noValue, modifier = Modifier.weight(1f))
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        ClockingSummaryTile(label = stringResource(R.string.clocking_working), value = status.working, modifier = Modifier.weight(1f))
                        ClockingSummaryTile(label = stringResource(R.string.clocking_breaks), value = status.breaks, modifier = Modifier.weight(1f))
                    }
                }
                status.sessions.forEach { session -> ClockingSessionRow(session = session) }
            }
        }
    }
}

@Composable
private fun ClockingSummaryTile(label: String, value: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(ClockingTileShape)
            .background(colors.background)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Text(text = label, fontSize = 11.sp, color = colors.textTertiary, maxLines = 1)
        Text(text = value, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1)
    }
}

@Composable
private fun ClockingSessionRow(session: ClockingSessionSample) {
    val colors = LocalAikuColors.current
    val noValue = stringResource(R.string.clocking_no_value)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ClockingTileShape)
            .border(1.dp, colors.border, ClockingTileShape)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.weight(1f)) {
            ClockingSessionTime(label = stringResource(R.string.clocking_clock_in), time = session.clockIn, modifier = Modifier.weight(1f))
            ClockingSessionTime(label = stringResource(R.string.clocking_clock_out), time = session.clockOut ?: noValue, modifier = Modifier.weight(1f))
        }
        Text(
            text = session.duration?.let { stringResource(R.string.clocking_session_duration, it) }
                ?: stringResource(R.string.clocking_session_ongoing),
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (session.duration == null) colors.link else colors.success,
        )
    }
}

@Composable
private fun ClockingSessionTime(label: String, time: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(2.dp), modifier = modifier) {
        Text(text = label, fontSize = 11.sp, color = colors.textTertiary, maxLines = 1)
        Text(text = time, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1)
    }
}

@Composable
private fun ClockingMethodTabs(methods: List<ClockingMethod>, selected: ClockingMethod, onSelect: (ClockingMethod) -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        modifier = Modifier
            .fakeData(cornerRadius = 12.dp)
            .fillMaxWidth()
            .clip(MethodTabsShape)
            .background(colors.border)
            .padding(4.dp)
            .selectableGroup(),
    ) {
        methods.forEach { method ->
            val isSelected = method == selected
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .weight(1f)
                    .heightIn(min = 44.dp)
                    .then(
                        if (isSelected) {
                            Modifier.shadow(elevation = 1.dp, shape = MethodTabShape, ambientColor = colors.shadow, spotColor = colors.shadow)
                        } else {
                            Modifier
                        },
                    )
                    .clip(MethodTabShape)
                    .background(if (isSelected) colors.surface else Color.Transparent)
                    .selectable(selected = isSelected, role = Role.Tab, onClick = { onSelect(method) })
                    .padding(horizontal = 4.dp),
            ) {
                Text(
                    text = stringResource(method.label),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isSelected) colors.link else colors.textTertiary,
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                )
            }
        }
    }
}

@Composable
private fun ClockingQrCodeMethod(onOpenCamera: () -> Unit) {
    val colors = LocalAikuColors.current
    var location by remember { mutableStateOf(ClockingLocationState.Found) }

    LaunchedEffect(location) {
        if (location == ClockingLocationState.Detecting) {
            delay(LOCATION_DETECTION_MILLIS)
            location = ClockingLocationState.Found
        }
    }

    Column(verticalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
        ClockingLocationCard(state = location, onDetect = { location = ClockingLocationState.Detecting })
        AikuButton(
            text = stringResource(R.string.clocking_open_camera),
            onClick = onOpenCamera,
            icon = R.drawable.ic_ui_camera,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        Text(
            text = stringResource(R.string.clocking_open_camera_helper),
            fontSize = 12.sp,
            lineHeight = 17.sp,
            color = colors.textQuaternary,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun ClockingLocationCard(state: ClockingLocationState, onDetect: () -> Unit) {
    val colors = LocalAikuColors.current
    val isFound = state == ClockingLocationState.Found
    val accent = if (isFound) colors.success else colors.textTertiary

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fakeData()
            .fillMaxWidth()
            .clip(CardShape)
            .background(if (isFound) colors.successSoft else colors.surfaceMuted)
            .border(1.dp, if (isFound) colors.success else colors.borderStrong, CardShape)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(40.dp).clip(CircleShape).background(colors.surface)) {
                Icon(painter = painterResource(R.drawable.ic_ui_map_pin), contentDescription = null, tint = accent, modifier = Modifier.size(20.dp))
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = stringResource(if (isFound) R.string.clocking_location_found else R.string.clocking_location_detecting),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                )
                Text(
                    text = if (isFound) {
                        stringResource(R.string.clocking_location_coordinates, CLOCKING_LOCATION_SAMPLE)
                    } else {
                        stringResource(R.string.clocking_location_detect_first)
                    },
                    fontSize = 12.sp,
                    color = colors.textTertiary,
                )
            }
        }
        AikuButton(
            text = stringResource(if (isFound) R.string.clocking_location_update else R.string.clocking_location_detect),
            onClick = onDetect,
            style = ButtonStyle.Outline,
            modifier = Modifier.fillMaxWidth().clip(ControlShape).background(colors.surface),
        )
    }
}

@Composable
private fun ClockingPersonalCodeCard(helper: String, content: @Composable ColumnScope.() -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(14.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ClockingPanelShape)
            .background(colors.surface)
            .border(1.dp, colors.border, ClockingPanelShape)
            .padding(horizontal = 16.dp, vertical = 20.dp),
    ) {
        Text(text = helper, fontSize = 14.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
        content()
    }
}

@Composable
private fun ClockingPinMethod() {
    val colors = LocalAikuColors.current

    ClockingPersonalCodeCard(helper = stringResource(R.string.clocking_pin_helper)) {
        Text(
            text = CLOCKING_PIN_SAMPLE,
            fontSize = 44.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 13.sp,
            color = colors.text,
            modifier = Modifier.fakeData(cornerRadius = 8.dp).padding(start = 21.dp, end = 8.dp),
        )
    }
}

@Composable
private fun ClockingBarcodeMethod() {
    val colors = LocalAikuColors.current
    val light = rememberClockingLightColors()
    val description = stringResource(R.string.clocking_barcode_description)
    val bars = ClockingPersonalCodesSample.barcode

    ClockingPersonalCodeCard(helper = stringResource(R.string.clocking_barcode_helper)) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier.fakeData(cornerRadius = 8.dp).padding(4.dp),
        ) {
            Canvas(
                modifier = Modifier
                    .widthIn(max = 280.dp)
                    .fillMaxWidth()
                    .height(96.dp)
                    .clip(ControlShape)
                    .background(Color.White)
                    .border(1.dp, colors.border, ControlShape)
                    .semantics { contentDescription = description },
            ) {
                val gap = 2.dp.toPx()
                val top = 10.dp.toPx()
                val barHeight = size.height - 2 * top
                var left = 14.dp.toPx()
                bars.forEach { bar ->
                    val width = bar.widthDp.dp.toPx()
                    if (bar.isDark) {
                        drawRect(color = light.text, topLeft = Offset(left, top), size = Size(width, barHeight))
                    }
                    left += width + gap
                }
            }
            Text(
                text = CLOCKING_EMPLOYEE_CODE_SAMPLE,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                letterSpacing = 2.6.sp,
                color = colors.textSecondary,
            )
        }
    }
}

@Composable
private fun ClockingCameraQrMethod() {
    val colors = LocalAikuColors.current
    val light = rememberClockingLightColors()
    val description = stringResource(R.string.clocking_camera_qr_description)
    val cells = ClockingPersonalCodesSample.qrCells

    ClockingPersonalCodeCard(helper = stringResource(R.string.clocking_camera_qr_helper)) {
        Canvas(
            modifier = Modifier
                .fakeData(cornerRadius = 8.dp)
                .widthIn(max = 200.dp)
                .fillMaxWidth()
                .aspectRatio(1f)
                .clip(ControlShape)
                .background(Color.White)
                .border(1.dp, colors.border, ControlShape)
                .semantics { contentDescription = description },
        ) {
            val inset = 12.dp.toPx()
            val cell = (size.width - 2 * inset) / CLOCKING_QR_MODULES
            cells.forEachIndexed { index, isDark ->
                if (isDark) {
                    drawRect(
                        color = light.text,
                        topLeft = Offset(inset + index % CLOCKING_QR_MODULES * cell, inset + index / CLOCKING_QR_MODULES * cell),
                        size = Size(cell, cell),
                    )
                }
            }
        }
    }
}
