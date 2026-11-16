package io.aiku.droid.ui.scan

import android.content.Intent
import android.net.Uri
import android.provider.Settings
import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.BiasAlignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.allows
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalAppearanceController
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.LocalAikuColors
import io.aiku.droid.ui.theme.aikuColors

private const val VIEWFINDER_VERTICAL_BIAS = -0.2f
private val ViewfinderWidth = 280.dp
private val ViewfinderHeight = 200.dp
private val ViewfinderBracketArm = 44.dp
private val ViewfinderBracketStroke = 4.dp
private val ViewfinderBracketRadius = 12.dp

@Composable
fun ScanScreen(lookup: ScanLookupViewModel) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val state by lookup.state.collectAsStateWithLifecycle()
    val scans = LocalScanController.current
    var isTorchOn by remember { mutableStateOf(false) }

    val canLookUp = scanAbilities.any { can.allows(it) }

    ScanEffect(enabled = canLookUp) { scan -> lookup.lookUp(scan.code) }

    LaunchedEffect(state) {
        val found = state as? ScanLookupState.Found ?: return@LaunchedEffect
        lookup.destinationOpened()
        navigator.switchSection(found.destination.route())
    }

    if (state == ScanLookupState.SessionExpired) {
        SessionExpiredScreen(onSignIn = lookup::signInAgain)
        return
    }

    RequireAbility(
        isAllowed = canLookUp,
        title = stringResource(R.string.scan_title),
        activeTab = MainTab.Scan,
        missingPermission = scanAbilities.joinToString(" / ") { it.permission },
    ) {
        ScanViewfinderLayout(
            state = state,
            isTorchOn = isTorchOn,
            onToggleTorch = { isTorchOn = !isTorchOn },
            onCameraScan = { code -> scans.emit(Scan(code = code, source = ScanSource.Camera)) },
            onTypeCode = { navigator.open(SearchRoute) },
            onClose = navigator::back,
        )
    }
}

private val scanAbilities = listOf(Ability.ViewGoodsOut, Ability.ViewInventory, Ability.ViewLocations)

@Composable
private fun rememberCameraColors(): AikuColors {
    val theme = LocalAppearanceController.current.appearance.theme
    return remember(theme) { aikuColors(theme, isDark = true) }
}

@Composable
private fun ScanViewfinderLayout(
    state: ScanLookupState,
    isTorchOn: Boolean,
    onToggleTorch: () -> Unit,
    onCameraScan: (String) -> Unit,
    onTypeCode: () -> Unit,
    onClose: () -> Unit,
) {
    val camera = rememberCameraColors()
    val cameraAccess = rememberCameraAccess()

    CompositionLocalProvider(LocalAikuColors provides camera) {
        ScanViewfinderContent(
            state = state,
            camera = camera,
            isCameraAllowed = cameraAccess.isAllowed,
            isCameraRefused = cameraAccess.isRefused,
            isTorchOn = isTorchOn,
            onToggleTorch = onToggleTorch,
            onCameraScan = onCameraScan,
            onTypeCode = onTypeCode,
            onClose = onClose,
        )
    }
}

@Composable
private fun ScanViewfinderContent(
    state: ScanLookupState,
    camera: AikuColors,
    isCameraAllowed: Boolean,
    isCameraRefused: Boolean,
    isTorchOn: Boolean,
    onToggleTorch: () -> Unit,
    onCameraScan: (String) -> Unit,
    onTypeCode: () -> Unit,
    onClose: () -> Unit,
) {
    Column(modifier = Modifier.fillMaxSize().background(camera.background)) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 12.dp),
        ) {
            CameraRoundButton(icon = R.drawable.ic_ui_close, description = stringResource(R.string.scan_close), colors = camera, onClick = onClose)
            Column(modifier = Modifier.weight(1f)) {
                Text(text = warehouseKicker(), fontSize = 12.sp, color = camera.onNavSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(text = stringResource(R.string.scan_title), fontSize = 18.sp, fontWeight = FontWeight.Bold, color = camera.text)
            }
            if (isCameraAllowed) {
                CameraRoundButton(
                    icon = R.drawable.ic_ui_torch,
                    description = stringResource(if (isTorchOn) R.string.scan_torch_off else R.string.scan_torch_on),
                    colors = camera,
                    isActive = isTorchOn,
                    onClick = onToggleTorch,
                )
            }
        }
        Box(modifier = Modifier.weight(1f).fillMaxWidth().background(camera.surfaceMuted)) {
            if (isCameraAllowed) {
                CameraBarcodePreview(barcodes = BarcodeSet.Warehouse, onScanned = onCameraScan, isTorchOn = isTorchOn, modifier = Modifier.fillMaxSize())
            }
            Box(
                modifier = Modifier
                    .align(BiasAlignment(horizontalBias = 0f, verticalBias = VIEWFINDER_VERTICAL_BIAS))
                    .size(width = ViewfinderWidth, height = ViewfinderHeight)
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
                                    color = camera.scan,
                                    topLeft = Offset(stroke / 2, stroke / 2),
                                    size = Size(size.width - stroke, size.height - stroke),
                                    cornerRadius = CornerRadius(ViewfinderBracketRadius.toPx()),
                                    style = Stroke(width = stroke),
                                )
                            }
                        }
                    },
            )
            if (isCameraRefused) {
                CameraOffNotice(colors = camera, modifier = Modifier.align(Alignment.Center))
            }
            ScanStatusPill(state = state, colors = camera, modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 24.dp, start = 16.dp, end = 16.dp))
        }
        Column(
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .background(camera.surface)
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 20.dp),
        ) {
            Text(
                text = stringResource(R.string.scan_hint),
                fontSize = 14.sp,
                lineHeight = 20.sp,
                color = camera.textSecondary,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.scan_type_code),
                onClick = onTypeCode,
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_ui_keyboard,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun ScanStatusPill(state: ScanLookupState, colors: AikuColors, modifier: Modifier = Modifier) {
    val text = when (state) {
        is ScanLookupState.LookingUp -> stringResource(R.string.scan_looking_up, state.code)
        is ScanLookupState.Failed -> when (state.problem) {
            ScanProblem.Offline -> stringResource(R.string.scan_failed_offline, state.code)
            ScanProblem.Forbidden -> stringResource(R.string.scan_failed_forbidden, state.code)
            ScanProblem.ServerError -> stringResource(R.string.scan_failed_server, state.code)
        }
        else -> stringResource(R.string.scan_listening)
    }
    val isFailed = state is ScanLookupState.Failed

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .widthIn(max = 420.dp)
            .clip(RoundedCornerShape(999.dp))
            .background(if (isFailed) colors.dangerSoft else colors.background.copy(alpha = 0.85f))
            .padding(horizontal = 16.dp, vertical = 10.dp)
            .semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        if (state is ScanLookupState.LookingUp) {
            CircularProgressIndicator(color = colors.text, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
        } else {
            Box(modifier = Modifier.size(8.dp).clip(CircleShape).background(if (isFailed) colors.danger else colors.success))
        }
        Text(text = text, fontSize = 14.sp, color = if (isFailed) colors.dangerInk else colors.text, maxLines = 2, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
private fun CameraOffNotice(colors: AikuColors, modifier: Modifier = Modifier) {
    val context = LocalContext.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .padding(24.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, colors.border, ControlShape)
            .padding(16.dp),
    ) {
        Text(text = stringResource(R.string.states_camera_title), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center)
        Text(text = stringResource(R.string.scan_camera_off_body), fontSize = 13.sp, lineHeight = 18.sp, color = colors.textSecondary, textAlign = TextAlign.Center)
        Text(
            text = stringResource(R.string.states_camera_open_settings),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.link,
            modifier = Modifier
                .heightIn(min = 44.dp)
                .clip(ControlShape)
                .clickable(role = Role.Button) {
                    context.startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", context.packageName, null)))
                }
                .padding(horizontal = 12.dp, vertical = 12.dp),
        )
    }
}

@Composable
private fun CameraRoundButton(
    @DrawableRes icon: Int,
    description: String,
    colors: AikuColors,
    onClick: () -> Unit,
    isActive: Boolean = false,
) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .background(if (isActive) colors.scan else colors.surface)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = if (isActive) colors.onScan else colors.text, modifier = Modifier.size(22.dp))
    }
}
