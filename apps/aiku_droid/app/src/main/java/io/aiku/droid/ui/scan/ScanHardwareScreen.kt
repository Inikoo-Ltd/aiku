package io.aiku.droid.ui.scan

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
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private object HardwareSample {
    const val TASK = "Goods out · Picking"
    const val REFERENCE = "DN-24817"
    const val PROGRESS = "9 / 14"
    const val LOCATION = "A-03-02"
    const val SKU = "SKU-00733"
    const val SKU_NAME = "Bath bomb – rose 180g"
    const val PICKED = 3
    const val REQUIRED = 6
}

private data class SampleLastScan(val code: String, val note: String, val time: String, val isCounted: Boolean)

private val SampleLastScans = listOf(
    SampleLastScan(code = "SKU-00733", note = "+1", time = "09:41:20", isCounted = true),
    SampleLastScan(code = "SKU-00412", note = "wrong item, not counted", time = "09:41:12", isCounted = false),
    SampleLastScan(code = "A-03-02", note = "location", time = "09:40:58", isCounted = true),
)

@Composable
fun ScanHardwareScreen() {
    val can = LocalWarehouse.current.can

    RequireAbility(isAllowed = can.canResolveScans(), title = stringResource(R.string.scan_input_device)) {
        ScanHardwareContent(canPick = can.pickAndPack)
    }
}

@Composable
private fun ScanHardwareContent(canPick: Boolean) {
    val colors = LocalAikuColors.current

    Column(modifier = Modifier.fillMaxSize().background(colors.background)) {
        HardwareHeader()
        Column(
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
        ) {
            PickingSteps()
            ScanNowCard()
            LastScans()
        }
        if (canPick) {
            PickingActions()
        }
    }
}

@Composable
private fun HardwareHeader() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            HeaderIconButton(icon = R.drawable.ic_chevron_left, description = stringResource(R.string.nav_back), onClick = navigator::back)
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .weight(1f)
                    .fakeData(8.dp)
                    .padding(horizontal = 8.dp, vertical = 4.dp),
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(text = HardwareSample.TASK, fontSize = 12.sp, color = colors.onNavSecondary, maxLines = 1)
                    Text(text = HardwareSample.REFERENCE, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.onNav, maxLines = 1)
                }
                Text(text = HardwareSample.PROGRESS, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.onNav)
            }
        }
        ScannerStatusBar(onSwitchToCamera = { navigator.open(ScanCameraRoute) })
    }
}

@Composable
private fun ScannerStatusBar(onSwitchToCamera: () -> Unit) {
    val colors = LocalAikuColors.current
    val liveDot = rememberScanDarkColors().success

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fakeData(8.dp)
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.navActive)
            .border(1.dp, colors.navRing, ControlShape)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_scan_device), contentDescription = null, tint = colors.navAccent, modifier = Modifier.size(20.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(text = stringResource(R.string.scan_device_ready), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.navAccent)
            Text(text = stringResource(R.string.scan_device_trigger_hint), fontSize = 12.sp, color = colors.onNavSecondary)
        }
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(16.dp)
                .clip(CircleShape)
                .background(liveDot.copy(alpha = 0.3f)),
        ) {
            Box(modifier = Modifier.size(10.dp).clip(CircleShape).background(liveDot))
        }
        HeaderIconButton(
            icon = R.drawable.ic_scan_camera,
            description = stringResource(R.string.scan_switch_to_camera),
            onClick = onSwitchToCamera,
            filled = true,
            modifier = Modifier.size(40.dp),
        )
    }
}

@Composable
private fun PickingSteps() {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .fakeData(8.dp)
            .fillMaxWidth(),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally),
            modifier = Modifier
                .weight(1f)
                .height(36.dp)
                .clip(ControlShape)
                .background(colors.successSoft),
        ) {
            Icon(painter = painterResource(R.drawable.ic_scan_check), contentDescription = null, tint = colors.success, modifier = Modifier.size(14.dp))
            Text(text = stringResource(R.string.scan_step_location), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = colors.success, maxLines = 1)
        }
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .weight(1f)
                .height(36.dp)
                .clip(ControlShape)
                .background(colors.navActive),
        ) {
            Text(text = stringResource(R.string.scan_step_item), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.navAccent, maxLines = 1)
        }
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .weight(1f)
                .height(36.dp)
                .clip(ControlShape)
                .background(colors.surface)
                .border(1.dp, colors.border, ControlShape),
        ) {
            Text(text = stringResource(R.string.scan_step_quantity), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = colors.textTertiary, maxLines = 1)
        }
    }
}

@Composable
private fun ScanNowCard() {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fakeData(12.dp)
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(2.dp, colors.primary, CardShape)
            .padding(horizontal = 16.dp, vertical = 20.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .clip(CircleShape)
                .background(colors.soft)
                .padding(horizontal = 10.dp, vertical = 4.dp),
        ) {
            Icon(painter = painterResource(R.drawable.ic_section_locations), contentDescription = null, tint = colors.link, modifier = Modifier.size(14.dp))
            Text(
                text = stringResource(R.string.scan_location_confirmed, HardwareSample.LOCATION),
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.link,
            )
        }
        Text(text = stringResource(R.string.scan_device_scan_item), fontSize = 14.sp, color = colors.textTertiary)
        Text(
            text = HardwareSample.SKU,
            fontSize = 32.sp,
            lineHeight = 36.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = (-0.64).sp,
            color = colors.text,
        )
        Text(text = HardwareSample.SKU_NAME, fontSize = 15.sp, color = colors.textSecondary, textAlign = TextAlign.Center)
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(top = 4.dp)) {
            Text(
                text = HardwareSample.PICKED.toString(),
                fontSize = 40.sp,
                lineHeight = 44.sp,
                fontWeight = FontWeight.Bold,
                color = colors.link,
                modifier = Modifier.alignByBaseline(),
            )
            Text(
                text = pluralStringResource(R.plurals.scan_device_picked_of, HardwareSample.REQUIRED, HardwareSample.REQUIRED),
                fontSize = 18.sp,
                color = colors.textTertiary,
                modifier = Modifier.alignByBaseline(),
            )
        }
        Text(text = stringResource(R.string.scan_device_hint), fontSize = 12.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
    }
}

@Composable
private fun LastScans() {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            text = stringResource(R.string.scan_last_scans),
            fontSize = 14.sp,
            fontWeight = FontWeight.Bold,
            color = colors.textSecondary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
        Column(
            modifier = Modifier
                .fakeData(12.dp)
                .fillMaxWidth()
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape)
                .padding(horizontal = 14.dp, vertical = 4.dp),
        ) {
            SampleLastScans.forEachIndexed { index, scan ->
                if (index > 0) RowDivider()
                LastScanRow(scan)
            }
        }
    }
}

@Composable
private fun LastScanRow(scan: SampleLastScan) {
    val colors = LocalAikuColors.current
    val label = buildAnnotatedString {
        append(scan.code)
        append(" ")
        withStyle(SpanStyle(color = if (scan.isCounted) colors.textTertiary else colors.dangerInk)) { append(scan.note) }
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier.fillMaxWidth().padding(vertical = 10.dp),
    ) {
        Icon(
            painter = painterResource(if (scan.isCounted) R.drawable.ic_scan_check else R.drawable.ic_scan_close),
            contentDescription = null,
            tint = if (scan.isCounted) colors.success else colors.danger,
            modifier = Modifier.size(18.dp),
        )
        Text(text = label, fontSize = 14.sp, color = colors.text, modifier = Modifier.weight(1f))
        Text(text = scan.time, fontSize = 12.sp, color = colors.textTertiary)
    }
}

@Composable
private fun PickingActions() {
    val colors = LocalAikuColors.current

    Column(modifier = Modifier.fillMaxWidth().background(colors.surface)) {
        HorizontalDivider(thickness = 1.dp, color = colors.border)
        Row(
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(horizontal = 16.dp, vertical = 12.dp),
        ) {
            PickingActionButton(
                text = stringResource(R.string.scan_not_enough_stock),
                contentColor = colors.danger,
                borderColor = colors.dangerBorder,
                icon = R.drawable.ic_alert_triangle,
                onClick = {},
                modifier = Modifier.weight(1f),
            )
            PickingActionButton(
                text = stringResource(R.string.scan_skip),
                contentColor = colors.textSecondary,
                borderColor = colors.borderStrong,
                onClick = {},
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun PickingActionButton(
    text: String,
    contentColor: Color,
    borderColor: Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    @DrawableRes icon: Int? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
        modifier = modifier
            .heightIn(min = 52.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, borderColor, ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 8.dp),
    ) {
        if (icon != null) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = contentColor, modifier = Modifier.size(18.dp))
        }
        Text(
            text = text,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = contentColor,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Preview
@Composable
private fun ScanHardwareScreenPreview() {
    AikuDroidTheme {
        ScanHardwareScreen()
    }
}
