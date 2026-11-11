package io.aiku.droid.ui.goodsin

import androidx.annotation.DrawableRes
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.rememberTabIndication
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ScanToPickRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private const val LASER_SWEEP_MILLIS = 2_200
private const val CAMERA_OVERLAY_ALPHA = 0.6f
private const val BARCODE_ALPHA = 0.5f
private const val LASER_GLOW_ALPHA = 0.35f

@Composable
fun ScanCameraScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val item = GoodsInSamples.checkItem(GoodsInSamples.CAMERA_ITEM_CODE)
    var scannedCount by remember { mutableIntStateOf(GoodsInSamples.CAMERA_START_COUNT) }
    var isTorchOn by remember { mutableStateOf(false) }
    var showQuantitySheet by remember { mutableStateOf(false) }
    val cameraBackground = Brush.radialGradient(colors = listOf(colors.toast, colors.scrim.copy(alpha = 1f)))

    Column(modifier = Modifier.fillMaxSize().background(cameraBackground)) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .padding(top = 8.dp, bottom = 16.dp),
        ) {
            CameraTopBar(
                isTorchOn = isTorchOn,
                onToggleTorch = { isTorchOn = !isTorchOn },
                onClose = { navigator.back() },
            )
            Spacer(modifier = Modifier.height(16.dp))
            CameraModeSwitch(onBluetoothScanner = { navigator.open(ScanToPickRoute(GoodsInSamples.SCAN_TO_PICK_REFERENCE)) })
            Spacer(modifier = Modifier.height(24.dp))
            Viewfinder(onScan = { if (scannedCount < item.delivered) scannedCount += 1 })
            Spacer(modifier = Modifier.height(16.dp))
            Text(text = stringResource(R.string.goodsin_point_at_barcode), fontSize = 14.sp, color = colors.onNav, textAlign = TextAlign.Center)
            Spacer(modifier = Modifier.height(16.dp))
            ScanFeedback(code = item.code, count = scannedCount, total = item.delivered)
        }
        CurrentItemPanel(
            item = item,
            scannedCount = scannedCount,
            canCheck = can.bookIn,
            onTypeCode = { navigator.open(SearchRoute) },
            onSetQuantity = { showQuantitySheet = true },
            onDone = { navigator.back() },
        )
    }

    if (showQuantitySheet) {
        CheckedQuantitySheet(reference = GoodsInSamples.CAMERA_REFERENCE, skuCode = item.code, onDismiss = { showQuantitySheet = false })
    }
}

@Composable
private fun CameraTopBar(isTorchOn: Boolean, onToggleTorch: () -> Unit, onClose: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
    ) {
        CameraRoundButton(icon = R.drawable.ic_ui_close, description = stringResource(R.string.goodsin_close_scanner), onClick = onClose)
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.weight(1f).fakeData(cornerRadius = 8.dp).padding(vertical = 2.dp),
        ) {
            Text(text = stringResource(R.string.goodsin_camera_context), fontSize = 12.sp, color = colors.borderStrong)
            Text(text = GoodsInSamples.CAMERA_REFERENCE, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White)
        }
        CameraRoundButton(
            icon = R.drawable.ic_ui_torch,
            description = stringResource(if (isTorchOn) R.string.goodsin_torch_off else R.string.goodsin_torch_on),
            onClick = onToggleTorch,
            isActive = isTorchOn,
        )
    }
}

@Composable
private fun CameraRoundButton(@DrawableRes icon: Int, description: String, onClick: () -> Unit, isActive: Boolean = false) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .background(if (isActive) colors.scan else colors.scrim.copy(alpha = CAMERA_OVERLAY_ALPHA))
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = if (isActive) colors.onScan else Color.White, modifier = Modifier.size(22.dp))
    }
}

@Composable
private fun CameraModeSwitch(onBluetoothScanner: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        modifier = Modifier
            .clip(CircleShape)
            .background(colors.scrim.copy(alpha = CAMERA_OVERLAY_ALPHA))
            .padding(horizontal = 2.dp),
    ) {
        CameraModePill(icon = R.drawable.ic_ui_camera, label = stringResource(R.string.goodsin_camera), isActive = true, onClick = {})
        CameraModePill(icon = R.drawable.ic_ui_scanner, label = stringResource(R.string.goodsin_bluetooth_scanner), isActive = false, onClick = onBluetoothScanner)
    }
}

@Composable
private fun CameraModePill(@DrawableRes icon: Int, label: String, isActive: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val content = if (isActive) colors.onScan else colors.onNav

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .height(44.dp)
            .selectable(
                selected = isActive,
                interactionSource = remember { MutableInteractionSource() },
                indication = rememberTabIndication(cornerRadius = 22.dp),
                role = Role.Tab,
                onClick = onClick,
            )
            .padding(horizontal = 2.dp, vertical = 5.dp)
            .clip(CircleShape)
            .background(if (isActive) colors.scan else Color.Transparent)
            .padding(horizontal = 12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(16.dp))
            Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = content, maxLines = 1)
        }
    }
}

@Composable
private fun Viewfinder(onScan: () -> Unit) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.goodsin_simulate_scan)
    val transition = rememberInfiniteTransition(label = "viewfinderLaser")
    val laserPosition by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(animation = tween(LASER_SWEEP_MILLIS, easing = FastOutSlowInEasing), repeatMode = RepeatMode.Reverse),
        label = "viewfinderLaserPosition",
    )

    Box(
        modifier = Modifier
            .size(width = 300.dp, height = 190.dp)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onScan)
            .semantics { contentDescription = description }
            .drawBehind {
                drawBarcode(color = colors.border.copy(alpha = BARCODE_ALPHA))
                val stroke = 4.dp.toPx()
                val arm = 36.dp.toPx()
                val radius = 10.dp.toPx()
                drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius)
                scale(scaleX = -1f, scaleY = 1f) { drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius) }
                scale(scaleX = 1f, scaleY = -1f) { drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius) }
                scale(scaleX = -1f, scaleY = -1f) { drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius) }
                val laserInset = 20.dp.toPx()
                val laserTravel = size.height - laserInset * 2
                val laserY = laserInset + laserTravel * laserPosition
                drawRect(
                    color = colors.scan.copy(alpha = LASER_GLOW_ALPHA),
                    topLeft = Offset(x = laserInset, y = laserY - 4.dp.toPx()),
                    size = Size(width = size.width - laserInset * 2, height = 10.dp.toPx()),
                )
                drawRect(color = colors.scan, topLeft = Offset(x = laserInset, y = laserY), size = Size(width = size.width - laserInset * 2, height = 2.dp.toPx()))
            },
    )
}

private fun DrawScope.drawCorner(color: Color, stroke: Float, arm: Float, radius: Float) {
    val inset = stroke / 2
    val path = Path().apply {
        moveTo(x = inset, y = arm)
        lineTo(x = inset, y = inset + radius)
        arcTo(
            rect = Rect(left = inset, top = inset, right = inset + radius * 2, bottom = inset + radius * 2),
            startAngleDegrees = 180f,
            sweepAngleDegrees = 90f,
            forceMoveTo = false,
        )
        lineTo(x = arm, y = inset)
    }
    drawPath(path = path, color = color, style = Stroke(width = stroke))
}

private fun DrawScope.drawBarcode(color: Color) {
    val unit = 1.dp.toPx()
    val left = 70.dp.toPx()
    val top = 60.dp.toPx()
    val right = left + 160.dp.toPx()
    val height = 70.dp.toPx()
    var x = left
    while (x < right) {
        drawRect(color = color, topLeft = Offset(x = x, y = top), size = Size(width = minOf(unit * 3, right - x), height = height))
        if (x + unit * 5 < right) {
            drawRect(color = color, topLeft = Offset(x = x + unit * 5, y = top), size = Size(width = unit, height = height))
        }
        x += unit * 10
    }
}

@Composable
private fun ScanFeedback(code: String, count: Int, total: Int) {
    val colors = LocalAikuColors.current
    val feedback = stringResource(R.string.goodsin_scan_feedback, count, total)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp)
            .fakeData(cornerRadius = 8.dp)
            .clip(ControlShape)
            .background(colors.success)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = Color.White, modifier = Modifier.size(20.dp))
        Text(
            text = buildAnnotatedString {
                withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(code) }
                append(" ")
                append(feedback)
            },
            fontSize = 14.sp,
            color = Color.White,
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun CurrentItemPanel(
    item: CheckItem,
    scannedCount: Int,
    canCheck: Boolean,
    onTypeCode: () -> Unit,
    onSetQuantity: () -> Unit,
    onDone: () -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp))
            .background(colors.surface)
            .navigationBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 28.dp),
    ) {
        Box(
            modifier = Modifier
                .align(Alignment.CenterHorizontally)
                .size(width = 40.dp, height = 4.dp)
                .clip(CircleShape)
                .background(colors.borderStrong),
        )
        Column(verticalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth().fakeData()) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                ProductPhoto(code = item.code)
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = item.code, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = item.name, fontSize = 13.sp, color = colors.textTertiary)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = buildAnnotatedString {
                            append(scannedCount.toString())
                            withStyle(SpanStyle(fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)) { append(" / ${item.delivered}") }
                        },
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = colors.text,
                    )
                    Text(text = stringResource(R.string.goodsin_camera_checked), fontSize = 11.sp, color = colors.textTertiary)
                }
            }
            ProgressBar(fraction = scannedCount.toFloat() / item.delivered)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
            PanelButton(
                text = stringResource(R.string.goodsin_type_code),
                icon = R.drawable.ic_ui_keyboard,
                container = Color.Transparent,
                content = colors.textSecondary,
                borderColor = colors.borderStrong,
                onClick = onTypeCode,
                modifier = Modifier.weight(1f),
            )
            if (canCheck) {
                PanelButton(
                    text = stringResource(R.string.goodsin_set_checked_quantity),
                    icon = null,
                    container = colors.soft,
                    content = colors.onScan,
                    borderColor = colors.softBorder,
                    onClick = onSetQuantity,
                    modifier = Modifier.weight(1f),
                )
            }
        }
        AikuButton(text = stringResource(R.string.goodsin_done_back), onClick = onDone, height = 52.dp, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun PanelButton(
    text: String,
    @DrawableRes icon: Int?,
    container: Color,
    content: Color,
    borderColor: Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(space = 8.dp, alignment = Alignment.CenterHorizontally),
        modifier = modifier
            .heightIn(min = 48.dp)
            .clip(ControlShape)
            .background(container)
            .border(width = 1.dp, color = borderColor, shape = ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 10.dp, vertical = 6.dp),
    ) {
        if (icon != null) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(18.dp))
        }
        Text(text = text, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = content, textAlign = TextAlign.Center)
    }
}
