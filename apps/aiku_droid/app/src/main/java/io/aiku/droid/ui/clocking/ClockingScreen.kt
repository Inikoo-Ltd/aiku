package io.aiku.droid.ui.clocking

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
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
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.common.BitMatrix
import com.google.zxing.oned.Code128Writer
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import io.aiku.droid.R
import io.aiku.droid.data.api.ClockingSession
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.SkeletonStatCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.rememberTabIndication
import io.aiku.droid.ui.navigation.ClockScanQrRoute
import io.aiku.droid.ui.navigation.ClockingErrorRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.util.Locale
import kotlin.math.floor

private const val BARCODE_QUIET_ZONE_MODULES = 10
private const val QR_QUIET_ZONE_MODULES = 2
private val MethodTabsShape = RoundedCornerShape(12.dp)
private val MethodTabShape = RoundedCornerShape(8.dp)

@Composable
fun ClockingScreen() {
    val navigator = LocalAppNavigator.current
    val clocking = LocalClocking.current
    val state by clocking.state.collectAsStateWithLifecycle()
    var showWorkingHours by remember { mutableStateOf(false) }
    var cameraCoordinates by remember { mutableStateOf<ClockingCoordinates?>(null) }
    var selectedMethod by rememberSaveable { mutableStateOf<ClockingMethod?>(null) }
    val status = state.status
    val methods = ClockingMethod.fromKeys(status?.methods.orEmpty())
    val method = selectedMethod?.takeIf { it in methods } ?: methods.firstOrNull()
    val now = rememberClockingNow()
    val location = rememberClockingLocation(
        isActive = method == ClockingMethod.QrCode,
        onPermissionDenied = { navigator.open(ClockingErrorRoute(kind = ClockingErrorKind.LocationBlocked.key)) },
    )
    val openCamera = {
        if (status?.hasShifts == true) {
            cameraCoordinates = location.coordinates
            showWorkingHours = true
        } else {
            clocking.prepareScan(ClockingScanSetup(coordinates = location.coordinates))
            navigator.open(ClockScanQrRoute)
        }
    }

    LaunchedEffect(Unit) { clocking.refresh() }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_title),
                kicker = clockingKicker(LocalStaff.current.displayName),
                tabs = clockingSectionTabs(active = ClockingSection.ClockInOut),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        state.statusProblem?.let { problem -> ClockingLoadProblem(problem = problem, onRetry = clocking::refresh) }
        when {
            status == null && state.statusProblem == null -> {
                SkeletonStatCard()
                SkeletonListCard(rows = 2)
            }
            status == null -> Unit
            method == null -> ClockingNoMethodCard()
            else -> {
                ClockingStatusCard(status = status, zone = state.zone, now = now)
                ClockingMethodTabs(methods = methods, selected = method, onSelect = { selectedMethod = it })
                when (method) {
                    ClockingMethod.QrCode -> ClockingQrCodeMethod(location = location, onOpenCamera = openCamera)
                    ClockingMethod.Pin -> ClockingPinMethod(code = status.code)
                    ClockingMethod.Barcode -> ClockingBarcodeMethod(code = status.code)
                    ClockingMethod.CameraQr -> ClockingCameraQrMethod(code = status.code)
                }
            }
        }
    }

    if (showWorkingHours) {
        WorkingHoursSheet(
            onDismiss = { showWorkingHours = false },
            onConfirm = { workScheduleId ->
                showWorkingHours = false
                clocking.prepareScan(ClockingScanSetup(workScheduleId = workScheduleId, coordinates = cameraCoordinates))
                navigator.open(ClockScanQrRoute)
            },
        )
    }
}

@Composable
internal fun ClockingLoadProblem(problem: ClockingProblem, onRetry: () -> Unit, modifier: Modifier = Modifier) {
    InlineAlert(
        title = stringResource(R.string.clocking_load_failed_title),
        body = stringResource(if (problem == ClockingProblem.Offline) R.string.clocking_load_offline_body else R.string.clocking_load_server_body),
        tone = AlertTone.Warn,
        actionLabel = stringResource(R.string.clocking_error_try_again),
        onAction = onRetry,
        modifier = modifier,
    )
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
private fun ClockingStatusCard(status: ClockingStatus, zone: ZoneId, now: Instant) {
    val colors = LocalAikuColors.current
    var isOpen by rememberSaveable { mutableStateOf(true) }
    val isClockedIn = status.isClockedIn
    val statusColor = if (isClockedIn) colors.success else colors.danger
    val statusContainer = if (isClockedIn) colors.successSoft else colors.dangerSoft
    val toggleLabel = stringResource(if (isOpen) R.string.clocking_details_hide else R.string.clocking_details_show)
    val noValue = stringResource(R.string.clocking_no_value)
    val today = status.today
    val lastClockOut = clockingTime(today?.lastClockOut, zone, withSeconds = true)
    val statusLine = when {
        isClockedIn -> stringResource(R.string.clocking_status_since, clockingTime(status.since, zone, withSeconds = true) ?: noValue)
        lastClockOut != null -> stringResource(R.string.clocking_status_last_clock_out, lastClockOut)
        else -> stringResource(R.string.clocking_status_no_clocking_today)
    }
    val date = today?.date ?: LocalDate.now(zone).toString()

    Column(
        modifier = Modifier
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
                    painter = painterResource(if (isClockedIn) R.drawable.ic_ui_check else R.drawable.ic_ui_close),
                    contentDescription = null,
                    tint = statusColor,
                    modifier = Modifier.size(22.dp),
                )
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = stringResource(if (isClockedIn) R.string.clocking_status_clocked_in else R.string.clocking_status_clocked_out),
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = statusColor,
                )
                Text(text = statusLine, fontSize = 13.sp, color = colors.textTertiary)
            }
            Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = stringResource(R.string.clocking_status_date), fontSize = 11.sp, color = colors.textTertiary)
                Text(text = clockingDateLabel(date, CLOCKING_DAY_TITLE_PATTERN), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
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
                        ClockingSummaryTile(
                            label = stringResource(R.string.clocking_first_clock_in),
                            value = clockingTime(today?.firstClockIn, zone, withSeconds = true) ?: noValue,
                            modifier = Modifier.weight(1f),
                        )
                        ClockingSummaryTile(label = stringResource(R.string.clocking_last_clock_out), value = lastClockOut ?: noValue, modifier = Modifier.weight(1f))
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        ClockingSummaryTile(
                            label = stringResource(R.string.clocking_working),
                            value = durationText(todayWorkingSeconds(status, now)),
                            modifier = Modifier.weight(1f),
                        )
                        ClockingSummaryTile(
                            label = stringResource(R.string.clocking_breaks),
                            value = durationText(today?.breaksSeconds ?: 0L),
                            modifier = Modifier.weight(1f),
                        )
                    }
                }
                status.sessions.forEach { session -> ClockingSessionRow(session = session, zone = zone) }
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
private fun ClockingSessionRow(session: ClockingSession, zone: ZoneId) {
    val colors = LocalAikuColors.current
    val noValue = stringResource(R.string.clocking_no_value)
    val notes = listOfNotNull(session.clockIn?.notes, session.clockOut?.notes).filter { it.isNotBlank() }
    val duration = session.duration
    val durationLabel = when {
        session.isOpen -> stringResource(R.string.clocking_session_ongoing)
        duration != null -> stringResource(R.string.clocking_session_duration, durationText(duration))
        else -> noValue
    }

    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ClockingTileShape)
            .border(1.dp, colors.border, ClockingTileShape)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.weight(1f)) {
                ClockingSessionTime(
                    label = stringResource(R.string.clocking_clock_in),
                    time = clockingTime(session.clockIn?.clockedAt ?: session.startsAt, zone, withSeconds = true) ?: noValue,
                    modifier = Modifier.weight(1f),
                )
                ClockingSessionTime(
                    label = stringResource(R.string.clocking_clock_out),
                    time = clockingTime(session.clockOut?.clockedAt ?: session.endsAt, zone, withSeconds = true) ?: noValue,
                    modifier = Modifier.weight(1f),
                )
            }
            Text(
                text = durationLabel,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = if (session.isOpen) colors.link else colors.success,
            )
        }
        notes.forEach { note ->
            Text(text = stringResource(R.string.clocking_session_note, note), fontSize = 12.sp, lineHeight = 17.sp, color = colors.textSecondary)
        }
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
                    .selectable(
                        selected = isSelected,
                        interactionSource = remember { MutableInteractionSource() },
                        indication = rememberTabIndication(cornerRadius = 8.dp),
                        role = Role.Tab,
                        onClick = { onSelect(method) },
                    )
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
private fun ClockingQrCodeMethod(location: ClockingLocation, onOpenCamera: () -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
        ClockingLocationCard(state = location.state, onDetect = location.detect)
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
    val isFound = state is ClockingLocationState.Found
    val accent = if (isFound) colors.success else colors.textTertiary
    val title = when (state) {
        is ClockingLocationState.Found -> stringResource(R.string.clocking_location_found)
        ClockingLocationState.Detecting -> stringResource(R.string.clocking_location_detecting)
        ClockingLocationState.NeedsPermission -> stringResource(R.string.clocking_location_needed)
        ClockingLocationState.Unavailable -> stringResource(R.string.clocking_location_unavailable)
        ClockingLocationState.NotFound -> stringResource(R.string.clocking_location_not_found)
    }
    val detail = when (state) {
        is ClockingLocationState.Found -> {
            val coordinates = String.format(Locale.ROOT, "%.5f, %.5f", state.coordinates.latitude, state.coordinates.longitude)
            state.accuracyMeters?.let { stringResource(R.string.clocking_location_accuracy, coordinates, it) } ?: coordinates
        }
        ClockingLocationState.Unavailable -> stringResource(R.string.clocking_location_unavailable_body)
        ClockingLocationState.NotFound -> stringResource(R.string.clocking_location_not_found_body)
        else -> stringResource(R.string.clocking_location_detect_first)
    }

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
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
                Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = detail, fontSize = 12.sp, lineHeight = 17.sp, color = colors.textTertiary)
            }
        }
        AikuButton(
            text = stringResource(if (isFound) R.string.clocking_location_update else R.string.clocking_location_detect),
            onClick = onDetect,
            style = ButtonStyle.Outline,
            isLoading = state == ClockingLocationState.Detecting,
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
private fun ClockingNoCodeCard() {
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
        ClockingIconCircle(icon = R.drawable.ic_ui_pin, tone = Tone.Neutral, size = 52.dp, iconSize = 26.dp)
        Text(text = stringResource(R.string.clocking_error_no_pin_title), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
        Text(
            text = stringResource(R.string.clocking_error_no_pin_body),
            fontSize = 14.sp,
            lineHeight = 20.sp,
            color = colors.textTertiary,
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun ClockingPinMethod(code: String?) {
    val colors = LocalAikuColors.current

    if (code == null) {
        ClockingNoCodeCard()
        return
    }
    ClockingPersonalCodeCard(helper = stringResource(R.string.clocking_pin_helper)) {
        Text(
            text = code,
            fontSize = 44.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 13.sp,
            color = colors.text,
            maxLines = 1,
            modifier = Modifier.padding(start = 13.dp),
        )
    }
}

@Composable
private fun ClockingBarcodeMethod(code: String?) {
    val colors = LocalAikuColors.current
    val light = rememberClockingLightColors()
    val description = stringResource(R.string.clocking_barcode_description)
    val modules = remember(code) { code?.let { runCatching { Code128Writer().encode(it) }.getOrNull() } }

    if (code == null || modules == null || modules.isEmpty()) {
        ClockingNoCodeCard()
        return
    }
    ClockingPersonalCodeCard(helper = stringResource(R.string.clocking_barcode_helper)) {
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
            val moduleWidth = floor(size.width / (modules.size + 2 * BARCODE_QUIET_ZONE_MODULES)).coerceAtLeast(1f)
            val left = (size.width - moduleWidth * modules.size) / 2
            val top = 10.dp.toPx()
            val barHeight = size.height - 2 * top
            modules.forEachIndexed { index, isDark ->
                if (isDark) {
                    drawRect(color = light.text, topLeft = Offset(left + index * moduleWidth, top), size = Size(moduleWidth, barHeight))
                }
            }
        }
        Text(text = code, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 2.6.sp, color = colors.textSecondary)
    }
}

@Composable
private fun ClockingCameraQrMethod(code: String?) {
    val colors = LocalAikuColors.current
    val light = rememberClockingLightColors()
    val description = stringResource(R.string.clocking_camera_qr_description)
    val matrix = remember(code) { code?.let { qrMatrixOf(it) } }

    if (matrix == null) {
        ClockingNoCodeCard()
        return
    }
    ClockingPersonalCodeCard(helper = stringResource(R.string.clocking_camera_qr_helper)) {
        Canvas(
            modifier = Modifier
                .widthIn(max = 200.dp)
                .fillMaxWidth()
                .aspectRatio(1f)
                .clip(ControlShape)
                .background(Color.White)
                .border(1.dp, colors.border, ControlShape)
                .semantics { contentDescription = description },
        ) {
            val cell = floor(size.width / matrix.width).coerceAtLeast(1f)
            val inset = (size.width - cell * matrix.width) / 2
            for (y in 0 until matrix.height) {
                for (x in 0 until matrix.width) {
                    if (matrix[x, y]) {
                        drawRect(color = light.text, topLeft = Offset(inset + x * cell, inset + y * cell), size = Size(cell, cell))
                    }
                }
            }
        }
    }
}

private fun qrMatrixOf(code: String): BitMatrix? = runCatching {
    QRCodeWriter().encode(
        code,
        BarcodeFormat.QR_CODE,
        0,
        0,
        mapOf(EncodeHintType.MARGIN to QR_QUIET_ZONE_MODULES, EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.M),
    )
}.getOrNull()
