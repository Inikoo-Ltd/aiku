package io.aiku.droid.ui.scan

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.inventory.MoveStockSheet
import io.aiku.droid.ui.locations.AuditLocationSheet
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

private const val SAMPLE_LOCATION = "A-03-02"
private const val SAMPLE_LOCATION_SUMMARY = "Area A · 6 SKOs · Operational"
private const val SAMPLE_SKO = "SKO-00733"

@Composable
fun ScanResultScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var isAuditOpen by remember { mutableStateOf(false) }
    var isMoveOpen by remember { mutableStateOf(false) }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.scan_result_title),
                kicker = stringResource(R.string.scan_result_kicker),
            )
        },
        activeTab = null,
        spacing = 16.dp,
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            modifier = Modifier
                .fillMaxWidth()
                .fakeData(cornerRadius = 8.dp)
                .clip(ControlShape)
                .background(colors.successSoft)
                .padding(horizontal = 14.dp, vertical = 12.dp),
        ) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.success, modifier = Modifier.size(20.dp))
            Text(
                text = buildAnnotatedString {
                    append(stringResource(R.string.scan_result_scanned_prefix))
                    withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(SAMPLE_LOCATION) }
                    append(stringResource(R.string.scan_result_scanned_location_suffix))
                },
                fontSize = 14.sp,
                color = colors.success,
                modifier = Modifier.weight(1f),
            )
        }
        Column(
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .fakeData()
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape)
                .padding(16.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Box(contentAlignment = Alignment.Center, modifier = Modifier.size(48.dp).clip(ControlShape).background(colors.soft)) {
                    Icon(painter = painterResource(R.drawable.ic_ui_locations), contentDescription = null, tint = colors.link, modifier = Modifier.size(24.dp))
                }
                Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = SAMPLE_LOCATION, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = SAMPLE_LOCATION_SUMMARY, fontSize = 13.sp, color = colors.textTertiary)
                }
            }
            AikuButton(text = stringResource(R.string.scan_result_open_location), onClick = { navigator.open(LocationRoute(SAMPLE_LOCATION)) }, modifier = Modifier.fillMaxWidth())
            if (can.moveStock) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    SoftAction(icon = R.drawable.ic_ui_clipboard, label = stringResource(R.string.scan_result_audit_location), onClick = { isAuditOpen = true }, modifier = Modifier.weight(1f))
                    SoftAction(icon = R.drawable.ic_ui_move, label = stringResource(R.string.scan_result_move_skos), onClick = { isMoveOpen = true }, modifier = Modifier.weight(1f))
                }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(
                text = stringResource(R.string.scan_result_what_opens),
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = colors.textSecondary,
                modifier = Modifier.padding(horizontal = 4.dp),
            )
            ListGroup {
                ScanTargetRow(icon = R.drawable.ic_ui_box, text = stringResource(R.string.scan_result_opens_sko))
                RowDivider()
                ScanTargetRow(icon = R.drawable.ic_ui_location_label, text = stringResource(R.string.scan_result_opens_location))
                RowDivider()
                ScanTargetRow(icon = R.drawable.ic_ui_delivery_note, text = stringResource(R.string.scan_result_opens_delivery_note))
                RowDivider()
                ScanTargetRow(icon = R.drawable.ic_ui_stock_delivery, text = stringResource(R.string.scan_result_opens_stock_delivery))
            }
        }
    }
    if (isAuditOpen) {
        AuditLocationSheet(locationCode = SAMPLE_LOCATION, onDismiss = { isAuditOpen = false })
    }
    if (isMoveOpen) {
        MoveStockSheet(skuCode = SAMPLE_SKO, onDismiss = { isMoveOpen = false }, fromLocationCode = SAMPLE_LOCATION)
    }
}

@Composable
private fun SoftAction(@DrawableRes icon: Int, label: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally),
        modifier = modifier
            .heightIn(min = 48.dp)
            .clip(ControlShape)
            .background(colors.soft)
            .border(1.dp, colors.softBorder, ControlShape)
            .clickable(role = Role.Button, onClick = onClick),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(18.dp))
        Text(text = label, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
    }
}

@Composable
private fun ScanTargetRow(@DrawableRes icon: Int, text: String) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(18.dp))
        Text(text = text, fontSize = 14.sp, color = colors.text, modifier = Modifier.weight(1f))
    }
}
