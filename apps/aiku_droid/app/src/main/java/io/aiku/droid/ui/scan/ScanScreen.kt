package io.aiku.droid.ui.scan

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.ui.actions.AdjustStockSheet
import io.aiku.droid.ui.actions.AuditLocationSheet
import io.aiku.droid.ui.actions.TransferStockSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNoteDetailRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationDetailRoute
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.ScanHardwareRoute
import io.aiku.droid.ui.navigation.ScanSettingsRoute
import io.aiku.droid.ui.navigation.SkuDetailRoute
import io.aiku.droid.ui.navigation.StockDeliveryDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class ScanTarget(
    @StringRes val kindLabel: Int,
    @StringRes val openLabel: Int,
    @StringRes val opensLabel: Int,
    @DrawableRes val icon: Int,
) {
    Sku(R.string.scan_kind_sku, R.string.scan_open_sku, R.string.scan_opens_sku, R.drawable.ic_scan_sku),
    Location(R.string.scan_kind_location, R.string.scan_open_location, R.string.scan_opens_location, R.drawable.ic_section_locations),
    DeliveryNote(R.string.scan_kind_delivery_note, R.string.scan_open_delivery_note, R.string.scan_opens_delivery_note, R.drawable.ic_scan_delivery_note),
    StockDelivery(R.string.scan_kind_stock_delivery, R.string.scan_open_stock_delivery, R.string.scan_opens_stock_delivery, R.drawable.ic_scan_stock_delivery),
}

private enum class StockSheet(@StringRes val label: Int, @DrawableRes val icon: Int) {
    Audit(R.string.scan_action_audit, R.drawable.ic_actions_audit),
    Transfer(R.string.scan_action_transfer, R.drawable.ic_actions_transfer),
    Adjust(R.string.scan_action_adjust, R.drawable.ic_actions_adjust),
}

private data class SampleScanResult(val target: ScanTarget, val code: String, val summary: String)

private val SampleScanResults = listOf(
    SampleScanResult(ScanTarget.Location, "A-03-02", "Area A · 6 SKUs · Operational"),
    SampleScanResult(ScanTarget.Sku, "SKU-00733", "Bath bomb – rose 180g · 30 in A-03-02"),
    SampleScanResult(ScanTarget.DeliveryNote, "DN-24817", "Picking · 9 of 14 items"),
    SampleScanResult(ScanTarget.StockDelivery, "SD-1093", "Receiving · 37 of 72 units"),
)

private fun ScanTarget.isViewable(can: WarehouseAbilities): Boolean = when (this) {
    ScanTarget.Sku -> can.viewInventory
    ScanTarget.Location -> can.viewLocations
    ScanTarget.DeliveryNote -> can.viewGoodsOut
    ScanTarget.StockDelivery -> can.viewGoodsIn
}

private fun ScanTarget.route(code: String): Any = when (this) {
    ScanTarget.Sku -> SkuDetailRoute(code)
    ScanTarget.Location -> LocationDetailRoute(code)
    ScanTarget.DeliveryNote -> DeliveryNoteDetailRoute(code)
    ScanTarget.StockDelivery -> StockDeliveryDetailRoute(code)
}

private fun ScanTarget.stockActions(): List<StockSheet> = when (this) {
    ScanTarget.Location -> listOf(StockSheet.Audit, StockSheet.Transfer)
    ScanTarget.Sku -> listOf(StockSheet.Transfer, StockSheet.Adjust)
    ScanTarget.DeliveryNote, ScanTarget.StockDelivery -> emptyList()
}

private fun ScanTarget.sampleCode(): String = SampleScanResults.first { it.target == this }.code

@Composable
fun ScanScreen() {
    val can = LocalWarehouse.current.can

    RequireAbility(isAllowed = can.canResolveScans(), title = stringResource(R.string.scan_result_title)) {
        ScanResultContent(can)
    }
}

@Composable
private fun ScanResultContent(can: WarehouseAbilities) {
    val navigator = LocalAppNavigator.current
    val result = SampleScanResults.firstOrNull { it.target.isViewable(can) }
    val viewableTargets = ScanTarget.entries.filter { it.isViewable(can) }
    var openSheet by remember { mutableStateOf<StockSheet?>(null) }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.scan_result_title),
                kicker = stringResource(R.string.scan_result_kicker),
                showMenu = false,
            )
        },
    ) {
        if (result != null) {
            ScannedBanner(result)
            ScanResultCard(
                result = result,
                actions = if (can.moveStock) result.target.stockActions() else emptyList(),
                onOpen = { navigator.open(result.target.route(result.code)) },
                onAction = { openSheet = it },
            )
        }

        ScanSection(title = stringResource(R.string.scan_again)) {
            ScanLinkRow(icon = R.drawable.ic_scan_camera, label = stringResource(R.string.scan_input_camera), onClick = { navigator.open(ScanCameraRoute) })
            RowDivider()
            ScanLinkRow(icon = R.drawable.ic_scan_device, label = stringResource(R.string.scan_input_device), onClick = { navigator.open(ScanHardwareRoute) })
            RowDivider()
            ScanLinkRow(icon = R.drawable.ic_scan_settings, label = stringResource(R.string.scan_settings_title), onClick = { navigator.open(ScanSettingsRoute) })
        }

        ScanSection(title = stringResource(R.string.scan_what_opens)) {
            viewableTargets.forEachIndexed { index, target ->
                if (index > 0) RowDivider()
                ScanLinkRow(
                    icon = target.icon,
                    label = stringResource(target.opensLabel),
                    onClick = { navigator.open(target.route(target.sampleCode())) },
                )
            }
        }
    }

    when (openSheet) {
        StockSheet.Transfer -> TransferStockSheet(onDismiss = { openSheet = null })
        StockSheet.Adjust -> AdjustStockSheet(onDismiss = { openSheet = null })
        StockSheet.Audit -> AuditLocationSheet(onDismiss = { openSheet = null })
        null -> Unit
    }
}

@Composable
private fun ScannedBanner(result: SampleScanResult) {
    val colors = LocalAikuColors.current
    val scanned = stringResource(R.string.scan_scanned)
    val kind = stringResource(result.target.kindLabel)
    val message = buildAnnotatedString {
        append(scanned)
        append(" ")
        withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(result.code) }
        append(" · ")
        append(kind)
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fakeData(8.dp)
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.successSoft)
            .border(1.dp, colors.successSoft, ControlShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_scan_check), contentDescription = null, tint = colors.success, modifier = Modifier.size(20.dp))
        Text(text = message, fontSize = 14.sp, color = colors.success, modifier = Modifier.weight(1f))
    }
}

@Composable
private fun ScanResultCard(
    result: SampleScanResult,
    actions: List<StockSheet>,
    onOpen: () -> Unit,
    onAction: (StockSheet) -> Unit,
) {
    val colors = LocalAikuColors.current

    AikuCard(modifier = Modifier.fakeData(12.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            IconTile(icon = result.target.icon, tone = Tone.Primary, size = 48.dp, iconSize = 24.dp)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = result.code, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = result.summary, fontSize = 13.sp, color = colors.textTertiary)
            }
        }
        AikuButton(text = stringResource(result.target.openLabel), onClick = onOpen, modifier = Modifier.fillMaxWidth())
        if (actions.isNotEmpty()) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                actions.forEach { action ->
                    QuickActionButton(action = action, onClick = { onAction(action) }, modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun QuickActionButton(action: StockSheet, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally),
        modifier = modifier
            .heightIn(min = 48.dp)
            .clip(ControlShape)
            .background(colors.soft)
            .border(1.dp, colors.softBorder, ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 8.dp),
    ) {
        Icon(painter = painterResource(action.icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(16.dp))
        Text(
            text = stringResource(action.label),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.link,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun ScanSection(title: String, content: @Composable ColumnScope.() -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            text = title,
            fontSize = 14.sp,
            fontWeight = FontWeight.Bold,
            color = colors.textSecondary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
        ListGroup(content = content)
    }
}

@Composable
private fun ScanLinkRow(@DrawableRes icon: Int, label: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 48.dp)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(18.dp))
        Text(text = label, fontSize = 14.sp, color = colors.text, modifier = Modifier.weight(1f))
        Icon(painter = painterResource(R.drawable.ic_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
    }
}

@Preview
@Composable
private fun ScanScreenPreview() {
    AikuDroidTheme {
        ScanScreen()
    }
}
