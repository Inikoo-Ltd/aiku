package io.aiku.droid.ui.clocking

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.ui.BiasAlignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.QrClocking
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.navigation.ClockingErrorRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.scan.BarcodeSet
import io.aiku.droid.ui.scan.CameraBarcodePreview
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.scan.rememberCameraAccess
import io.aiku.droid.ui.theme.Inter
import kotlinx.coroutines.delay
import java.time.ZoneId

private const val SUCCESS_AUTO_CLOSE_MILLIS = 6000L
private const val VIEWFINDER_VERTICAL_BIAS = -0.2f
private val ViewfinderFrameSize = 260.dp
private val ViewfinderBracketArm = 50.dp
private val ViewfinderBracketStroke = 4.dp
private val ViewfinderBracketRadius = 12.dp
private val ViewfinderLineTop = 128.dp
private val ViewfinderLineInset = 24.dp

@Composable
fun ClockScanQrScreen() {
    val clocking = LocalClocking.current
    val navigator = LocalAppNavigator.current
    val state by clocking.state.collectAsStateWithLifecycle()
    val scan = state.scan
    val refusedFallback = stringResource(R.string.clocking_scan_refused_fallback)
    val failedMessage = (scan as? ClockingScanState.Failed)?.let { stringResource(R.string.clocking_scan_server_error, it.statusCode) }

    LaunchedEffect(scan) {
        val errorRoute = when (scan) {
            is ClockingScanState.Refused -> ClockingErrorRoute(
                kind = ClockingErrorKind.QrInvalid.key,
                message = scan.message.ifBlank { refusedFallback },
                scanned = scan.qrCode,
            )
            ClockingScanState.Offline -> ClockingErrorRoute(kind = ClockingErrorKind.Offline.key)
            is ClockingScanState.Failed -> ClockingErrorRoute(kind = ClockingErrorKind.QrInvalid.key, message = failedMessage)
            else -> null
        }
        if (errorRoute != null) {
            clocking.restartScan()
            navigator.switchSection(errorRoute)
        }
    }

    when (scan) {
        is ClockingScanState.Recorded -> ClockScanSuccess(
            result = scan.result,
            zone = state.zone,
            noteState = state.note,
            canEditNotes = state.status?.canEditNotes == true,
        )
        else -> ClockScanCamera(isSending = scan is ClockingScanState.Sending, onScanned = clocking::clockInWithQr)
    }
}

@Composable
private fun ClockScanCamera(isSending: Boolean, onScanned: (String) -> Unit) {
    val cameraAccess = rememberCameraAccess()

    ScanEffect(enabled = !isSending) { scan -> onScanned(scan.code) }

    if (cameraAccess.isRefused) {
        ClockingErrorScreen(error = ClockingErrorKind.CameraBlocked, message = null, scanned = null)
    } else {
        ClockScanViewfinder(showCamera = cameraAccess.isAllowed, isSending = isSending, onScanned = onScanned)
    }
}

@Composable
private fun ClockScanViewfinder(showCamera: Boolean, isSending: Boolean, onScanned: (String) -> Unit) {
    val camera = rememberClockingDarkColors()
    val navigator = LocalAppNavigator.current
    val closeDescription = stringResource(R.string.clocking_scan_close_camera)

    Column(modifier = Modifier.fillMaxSize().background(camera.background)) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 12.dp),
        ) {
            Text(
                text = stringResource(R.string.clocking_scan_title),
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White,
                modifier = Modifier.weight(1f),
            )
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.14f))
                    .clickable(role = Role.Button, onClickLabel = closeDescription, onClick = navigator::back)
                    .semantics { contentDescription = closeDescription },
            ) {
                Icon(painter = painterResource(R.drawable.ic_ui_close), contentDescription = null, tint = Color.White, modifier = Modifier.size(22.dp))
            }
        }
        Box(modifier = Modifier.weight(1f).fillMaxWidth().background(camera.border)) {
            if (showCamera) {
                CameraBarcodePreview(barcodes = BarcodeSet.Clocking, onScanned = onScanned, modifier = Modifier.fillMaxSize(), isSingleShot = true)
            }
            Box(
                modifier = Modifier
                    .align(BiasAlignment(horizontalBias = 0f, verticalBias = VIEWFINDER_VERTICAL_BIAS))
                    .size(ViewfinderFrameSize)
                    .drawBehind {
                        val stroke = ViewfinderBracketStroke.toPx()
                        val arm = ViewfinderBracketArm.toPx()
                        listOf(
                            Offset(0f, 0f),
                            Offset(size.width - arm, 0f),
                            Offset(0f, size.height - arm),
                            Offset(size.width - arm, size.height - arm),
                        ).forEach { corner ->
                            clipRect(left = corner.x, top = corner.y, right = corner.x + arm, bottom = corner.y + arm) {
                                drawRoundRect(
                                    color = Color.White,
                                    topLeft = Offset(stroke / 2, stroke / 2),
                                    size = Size(size.width - stroke, size.height - stroke),
                                    cornerRadius = CornerRadius(ViewfinderBracketRadius.toPx()),
                                    style = Stroke(width = stroke),
                                )
                            }
                        }
                        val inset = ViewfinderLineInset.toPx()
                        val lineTop = ViewfinderLineTop.toPx()
                        val lineHeight = 2.dp.toPx()
                        val glow = 5.dp.toPx()
                        drawRect(
                            color = camera.success.copy(alpha = 0.35f),
                            topLeft = Offset(inset, lineTop - glow),
                            size = Size(size.width - 2 * inset, lineHeight + 2 * glow),
                        )
                        drawRect(
                            color = camera.success,
                            topLeft = Offset(inset, lineTop),
                            size = Size(size.width - 2 * inset, lineHeight),
                        )
                    },
            )
            if (isSending) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .padding(bottom = 24.dp)
                        .clip(RoundedCornerShape(999.dp))
                        .background(camera.background.copy(alpha = 0.8f))
                        .padding(horizontal = 16.dp, vertical = 10.dp)
                        .semantics { liveRegion = LiveRegionMode.Polite },
                ) {
                    CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                    Text(text = stringResource(R.string.clocking_scan_checking), fontSize = 14.sp, color = Color.White)
                }
            }
        }
        Text(
            text = stringResource(R.string.clocking_scan_hint),
            fontSize = 15.sp,
            color = Color.White,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 20.dp, bottom = 48.dp),
        )
    }
}

@Composable
private fun ClockScanSuccess(result: QrClocking, zone: ZoneId, noteState: ClockingNoteState, canEditNotes: Boolean) {
    val clocking = LocalClocking.current
    val light = rememberClockingLightColors()
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val noteSaved = stringResource(R.string.clocking_scan_note_saved)
    val noteNotSaved = stringResource(
        if (noteState == ClockingNoteState.Failed(ClockingProblem.Offline)) R.string.clocking_scan_note_offline else R.string.clocking_scan_note_failed,
    )
    val noteLabel = stringResource(R.string.clocking_scan_note)
    var note by rememberSaveable { mutableStateOf("") }
    var isNoteFocused by remember { mutableStateOf(false) }
    val noteFocusProgress = animateFocusProgress(isNoteFocused)
    var isClosed by remember { mutableStateOf(false) }
    val close = {
        if (!isClosed) {
            isClosed = true
            navigator.back()
        }
    }
    val title = when {
        result.clocking.isClockOut -> stringResource(R.string.clocking_status_clocked_out)
        result.clocking.isClockIn -> stringResource(R.string.clocking_status_clocked_in)
        else -> stringResource(R.string.clocking_scan_recorded)
    }
    val workingStart = scheduleTime(result.workingHours?.start)
    val workingEnd = scheduleTime(result.workingHours?.end)
    val isWaitingForUser = note.isNotEmpty() || isNoteFocused || noteState != ClockingNoteState.Idle

    LaunchedEffect(isWaitingForUser) {
        if (!isWaitingForUser) {
            delay(SUCCESS_AUTO_CLOSE_MILLIS)
            close()
        }
    }
    LaunchedEffect(noteState) {
        when (noteState) {
            ClockingNoteState.Saved -> {
                toasts.show(noteSaved, ToastKind.Success)
                close()
            }
            is ClockingNoteState.Failed -> toasts.show(noteNotSaved, ToastKind.Error)
            else -> Unit
        }
    }

    Box(contentAlignment = Alignment.Center, modifier = Modifier.fillMaxSize().background(light.success)) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .navigationBarsPadding()
                .padding(24.dp)
                .semantics { liveRegion = LiveRegionMode.Polite },
        ) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = Color.White, modifier = Modifier.size(88.dp))
            Text(text = title, fontSize = 34.sp, fontWeight = FontWeight.Bold, color = Color.White, textAlign = TextAlign.Center)
            Text(
                text = clockingTime(result.clocking.clockedAt, zone, withSeconds = true) ?: stringResource(R.string.clocking_no_value),
                fontSize = 44.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White,
            )
            if (result.isVisiting) {
                Text(
                    text = stringResource(R.string.clocking_scan_visiting),
                    fontSize = 16.sp,
                    lineHeight = 22.sp,
                    color = Color.White,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .widthIn(max = 300.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(Color.White.copy(alpha = 0.16f))
                        .padding(horizontal = 14.dp, vertical = 10.dp),
                )
            }
            if (workingStart != null && workingEnd != null) {
                Text(
                    text = stringResource(R.string.clocking_scan_working_hours, stringResource(R.string.clocking_range, workingStart, workingEnd)),
                    fontSize = 16.sp,
                    color = light.successSoft,
                    textAlign = TextAlign.Center,
                )
            }
            Column(
                verticalArrangement = Arrangement.spacedBy(10.dp),
                modifier = Modifier.widthIn(max = 320.dp).fillMaxWidth().padding(top = 12.dp),
            ) {
                if (canEditNotes) {
                    BasicTextField(
                        value = note,
                        onValueChange = { note = it },
                        singleLine = true,
                        textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, color = light.text),
                        cursorBrush = SolidColor(light.primary),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp)
                            .clip(ControlShape)
                            .background(Color.White)
                            .border(2.dp, light.focus.copy(alpha = noteFocusProgress), ControlShape)
                            .onFocusChanged { isNoteFocused = it.isFocused }
                            .semantics { contentDescription = noteLabel },
                        decorationBox = { inner ->
                            Box(contentAlignment = Alignment.CenterStart, modifier = Modifier.padding(horizontal = 14.dp)) {
                                if (note.isEmpty()) {
                                    Text(text = stringResource(R.string.clocking_scan_note_placeholder), fontSize = 15.sp, color = light.textQuaternary, maxLines = 1)
                                }
                                inner()
                            }
                        },
                    )
                }
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier
                            .weight(1f)
                            .heightIn(min = 48.dp)
                            .clip(ControlShape)
                            .border(1.dp, Color.White.copy(alpha = 0.6f), ControlShape)
                            .clickable(role = Role.Button, onClick = close),
                    ) {
                        Text(text = stringResource(R.string.clocking_scan_close), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Color.White)
                    }
                    if (canEditNotes) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier
                                .weight(1f)
                                .heightIn(min = 48.dp)
                                .clip(ControlShape)
                                .background(Color.White)
                                .clickable(
                                    role = Role.Button,
                                    enabled = noteState != ClockingNoteState.Saving,
                                    onClick = {
                                        if (note.isBlank()) {
                                            close()
                                        } else {
                                            clocking.saveNote(clockingId = result.clocking.id, note = note)
                                        }
                                    },
                                ),
                        ) {
                            if (noteState == ClockingNoteState.Saving) {
                                CircularProgressIndicator(color = light.success, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
                            } else {
                                Text(text = stringResource(R.string.clocking_scan_save_note), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = light.success)
                            }
                        }
                    }
                }
                Text(
                    text = stringResource(R.string.clocking_scan_auto_close),
                    fontSize = 12.sp,
                    color = light.successSoft,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}
