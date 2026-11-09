package io.aiku.droid.ui.scan

import androidx.annotation.DrawableRes
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ScanHardwareRoute
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private object CameraSample {
    const val TASK = "Goods in · Receiving"
    const val REFERENCE = "SD-1093"
    const val SKU = "SKU-01187"
    const val SKU_NAME = "Soy wax candle – vanilla"
    const val RECEIVED = 37
    const val EXPECTED = 72
}

@Composable
fun ScanCameraScreen() {
    val can = LocalWarehouse.current.can

    RequireAbility(isAllowed = can.canResolveScans(), title = stringResource(R.string.scan_camera_title)) {
        ScanCameraContent(canReceive = can.bookIn)
    }
}

@Composable
private fun ScanCameraContent(canReceive: Boolean) {
    val viewfinder = rememberScanDarkColors()
    val navigator = LocalAppNavigator.current
    var isTorchOn by rememberSaveable { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .drawBehind {
                drawRect(
                    brush = Brush.radialGradient(
                        colors = listOf(viewfinder.border, viewfinder.background),
                        center = Offset(size.width / 2, size.height * 0.35f),
                        radius = size.height * 0.7f,
                    ),
                )
            },
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 8.dp),
        ) {
            CameraRoundButton(
                icon = R.drawable.ic_scan_close,
                description = stringResource(R.string.scan_camera_close),
                viewfinder = viewfinder,
                onClick = navigator::back,
            )
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier
                    .weight(1f)
                    .fakeData(8.dp)
                    .padding(vertical = 4.dp),
            ) {
                Text(text = CameraSample.TASK, fontSize = 12.sp, color = viewfinder.textTertiary, maxLines = 1)
                Text(text = CameraSample.REFERENCE, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = viewfinder.onNav, maxLines = 1)
            }
            CameraRoundButton(
                icon = R.drawable.ic_scan_torch,
                description = stringResource(if (isTorchOn) R.string.scan_camera_torch_off else R.string.scan_camera_torch_on),
                viewfinder = viewfinder,
                onClick = { isTorchOn = !isTorchOn },
                isActive = isTorchOn,
            )
        }

        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp),
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
        ) {
            CameraModeSwitch(viewfinder = viewfinder, onScannerDevice = { navigator.open(ScanHardwareRoute) })
            ViewfinderPlaceholder(
                bracketColor = viewfinder.navAccent,
                laserColor = viewfinder.onScan,
                barcodeColor = viewfinder.text.copy(alpha = 0.5f),
            )
            Text(
                text = stringResource(R.string.scan_camera_hint),
                fontSize = 14.sp,
                color = viewfinder.onNav,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
            )
            LastScanToast()
        }

        CurrentItemSheet(canReceive = canReceive, onDone = navigator::back)
    }
}

@Composable
private fun CameraRoundButton(
    @DrawableRes icon: Int,
    description: String,
    viewfinder: AikuColors,
    onClick: () -> Unit,
    isActive: Boolean = false,
) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .background(if (isActive) colors.scan else viewfinder.background.copy(alpha = 0.6f))
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(
            painter = painterResource(icon),
            contentDescription = null,
            tint = if (isActive) colors.onScan else viewfinder.onNav,
            modifier = Modifier.size(22.dp),
        )
    }
}

@Composable
private fun CameraModeSwitch(viewfinder: AikuColors, onScannerDevice: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        modifier = Modifier
            .clip(CircleShape)
            .background(viewfinder.background.copy(alpha = 0.6f))
            .padding(4.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .height(34.dp)
                .clip(CircleShape)
                .background(colors.scan)
                .padding(horizontal = 12.dp),
        ) {
            Icon(painter = painterResource(R.drawable.ic_scan_camera), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(16.dp))
            Text(text = stringResource(R.string.scan_input_camera), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.onScan)
        }
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .height(34.dp)
                .clip(CircleShape)
                .clickable(role = Role.Button, onClick = onScannerDevice)
                .padding(horizontal = 12.dp),
        ) {
            Icon(painter = painterResource(R.drawable.ic_scan_device), contentDescription = null, tint = viewfinder.onNav, modifier = Modifier.size(16.dp))
            Text(text = stringResource(R.string.scan_input_device), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = viewfinder.onNav)
        }
    }
}

@Composable
private fun ViewfinderPlaceholder(bracketColor: Color, laserColor: Color, barcodeColor: Color) {
    Box(
        modifier = Modifier
            .fakeData(12.dp)
            .widthIn(max = 300.dp)
            .fillMaxWidth()
            .height(190.dp),
    ) {
        Canvas(modifier = Modifier.fillMaxSize()) {
            val stroke = 4.dp.toPx()
            val arm = 36.dp.toPx()
            val corner = CornerRadius(10.dp.toPx())
            val cornerOrigins = listOf(
                Offset(0f, 0f),
                Offset(size.width - arm, 0f),
                Offset(0f, size.height - arm),
                Offset(size.width - arm, size.height - arm),
            )
            cornerOrigins.forEach { origin ->
                clipRect(left = origin.x, top = origin.y, right = origin.x + arm, bottom = origin.y + arm) {
                    drawRoundRect(
                        color = bracketColor,
                        topLeft = Offset(stroke / 2, stroke / 2),
                        size = Size(size.width - stroke, size.height - stroke),
                        cornerRadius = corner,
                        style = Stroke(width = stroke),
                    )
                }
            }

            val unit = 1.dp.toPx()
            val barcodeWidth = 160.dp.toPx()
            val barcodeHeight = 70.dp.toPx()
            val barcodeLeft = (size.width - barcodeWidth) / 2
            val barcodeTop = 60.dp.toPx()
            var stripeOffset = 0f
            while (stripeOffset < barcodeWidth) {
                drawRect(color = barcodeColor, topLeft = Offset(barcodeLeft + stripeOffset, barcodeTop), size = Size(3 * unit, barcodeHeight))
                if (stripeOffset + 6 * unit <= barcodeWidth) {
                    drawRect(color = barcodeColor, topLeft = Offset(barcodeLeft + stripeOffset + 5 * unit, barcodeTop), size = Size(unit, barcodeHeight))
                }
                stripeOffset += 10 * unit
            }

            val laserInset = 20.dp.toPx()
            val laserTop = 93.dp.toPx()
            drawRect(
                color = laserColor.copy(alpha = 0.35f),
                topLeft = Offset(laserInset, laserTop - 4 * unit),
                size = Size(size.width - 2 * laserInset, 10 * unit),
            )
            drawRect(
                color = laserColor,
                topLeft = Offset(laserInset, laserTop),
                size = Size(size.width - 2 * laserInset, 2 * unit),
            )
        }
    }
}

@Composable
private fun LastScanToast() {
    val solid = rememberScanSolidColors()
    val count = stringResource(R.string.scan_count_of, CameraSample.RECEIVED, CameraSample.EXPECTED)
    val message = buildAnnotatedString {
        withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(CameraSample.SKU) }
        append(" +1 · ")
        append(count)
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fakeData(8.dp)
            .fillMaxWidth()
            .clip(ControlShape)
            .background(solid.success)
            .semantics { liveRegion = LiveRegionMode.Polite }
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_scan_check), contentDescription = null, tint = solid.surface, modifier = Modifier.size(20.dp))
        Text(text = message, fontSize = 14.sp, color = solid.surface, modifier = Modifier.weight(1f))
        Text(text = stringResource(R.string.scan_camera_feedback), fontSize = 12.sp, color = solid.successSoft)
    }
}

@Composable
private fun CurrentItemSheet(canReceive: Boolean, onDone: () -> Unit) {
    val colors = LocalAikuColors.current
    val sheetShape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp)
    val progress = buildAnnotatedString {
        append(CameraSample.RECEIVED.toString())
        withStyle(SpanStyle(fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)) {
            append(" / ${CameraSample.EXPECTED}")
        }
    }

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(sheetShape)
            .background(colors.surface)
            .navigationBarsPadding()
            .padding(16.dp),
    ) {
        Box(
            modifier = Modifier
                .align(Alignment.CenterHorizontally)
                .size(width = 40.dp, height = 4.dp)
                .clip(CircleShape)
                .background(colors.borderStrong),
        )
        Column(
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fakeData(8.dp)
                .padding(8.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = CameraSample.SKU, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = CameraSample.SKU_NAME, fontSize = 13.sp, color = colors.textTertiary)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text(text = progress, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = stringResource(R.string.scan_camera_received), fontSize = 11.sp, color = colors.textTertiary)
                }
            }
            ProgressBar(fraction = CameraSample.RECEIVED.toFloat() / CameraSample.EXPECTED)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
            AikuButton(
                text = stringResource(R.string.scan_type_code),
                onClick = {},
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_scan_keyboard,
                modifier = Modifier.weight(1f),
            )
            if (canReceive) {
                AikuButton(
                    text = stringResource(R.string.scan_camera_enter_quantity),
                    onClick = {},
                    style = ButtonStyle.Soft,
                    modifier = Modifier.weight(1f),
                )
            }
        }
        AikuButton(
            text = stringResource(R.string.scan_camera_done),
            onClick = onDone,
            modifier = Modifier.fillMaxWidth(),
            height = 52.dp,
        )
    }
}

@Preview
@Composable
private fun ScanCameraScreenPreview() {
    AikuDroidTheme {
        ScanCameraScreen()
    }
}
