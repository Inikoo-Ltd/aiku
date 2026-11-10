package io.aiku.droid.ui.locations

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.inventory.AuditStockSheet
import io.aiku.droid.ui.inventory.MoveStockSheet
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationSkuScreen(locationCode: String, skuCode: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val sko = LocationsSamples.sko(skuCode)
    var type by remember { mutableStateOf(sko.type) }
    var isActiveForWholesale by remember { mutableStateOf(LocationsSamples.ACTIVE_FOR_WHOLESALE) }
    var isActiveForDropshipping by remember { mutableStateOf(LocationsSamples.ACTIVE_FOR_DROPSHIPPING) }
    var showAudit by remember { mutableStateOf(false) }
    var showMove by remember { mutableStateOf(false) }
    val savedText = stringResource(R.string.locations_sko_saved)
    val canEdit = can.moveStock
    val footerActions: @Composable ColumnScope.() -> Unit = {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            if (canEdit) {
                AikuButton(
                    text = stringResource(R.string.locations_action_audit),
                    onClick = { showAudit = true },
                    modifier = Modifier.weight(1f),
                    style = ButtonStyle.Outline,
                    height = 52.dp,
                )
                AikuButton(
                    text = stringResource(R.string.locations_sko_move),
                    onClick = { showMove = true },
                    modifier = Modifier.weight(1f),
                    style = ButtonStyle.Outline,
                    height = 52.dp,
                )
                AikuButton(
                    text = stringResource(R.string.locations_sko_save),
                    onClick = {
                        toasts.show(text = savedText, kind = ToastKind.Success)
                        navigator.back()
                    },
                    modifier = Modifier.weight(1f),
                    height = 52.dp,
                )
            }
        }
    }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                AppHeader(
                    isSample = true,
                    title = stringResource(R.string.locations_sko_title, skuCode, locationCode),
                    kicker = stringResource(R.string.locations_sko_kicker),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = footerActions.takeIf { canEdit },
        ) {
            SkoSummaryCard(
                skuCode = skuCode,
                summary = stringResource(R.string.locations_sko_summary, sko.name, sko.quantity),
                onOpen = if (can.viewInventory) ({ navigator.open(SkuRoute(skuCode)) }) else null,
                modifier = Modifier.fakeData(),
            )
            LocationsHeading(text = stringResource(R.string.locations_heading_type))
            Column(verticalArrangement = Arrangement.spacedBy(14.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
                if (canEdit || type == SkoLocationType.Picking) {
                    LocationChoice(
                        title = stringResource(R.string.locations_type_picking),
                        isSelected = type == SkoLocationType.Picking,
                        onSelect = if (canEdit) ({ type = SkoLocationType.Picking }) else null,
                        subtitle = stringResource(R.string.locations_type_picking_help),
                    )
                }
                if (canEdit || type == SkoLocationType.Storing) {
                    LocationChoice(
                        title = stringResource(R.string.locations_type_storing),
                        isSelected = type == SkoLocationType.Storing,
                        onSelect = if (canEdit) ({ type = SkoLocationType.Storing }) else null,
                    )
                }
            }
            if (canEdit || isActiveForWholesale || isActiveForDropshipping) {
                LocationsHeading(text = stringResource(R.string.locations_heading_active_picking))
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min).fakeData(cornerRadius = 10.dp),
                ) {
                    if (canEdit || isActiveForWholesale) {
                        LocationChoice(
                            title = stringResource(R.string.locations_channel_wholesale),
                            isSelected = isActiveForWholesale,
                            onSelect = if (canEdit) ({ isActiveForWholesale = !isActiveForWholesale }) else null,
                            modifier = Modifier.weight(1f).fillMaxHeight(),
                            subtitle = stringResource(R.string.locations_channel_wholesale_help),
                            role = Role.Checkbox,
                        )
                    }
                    if (canEdit || isActiveForDropshipping) {
                        LocationChoice(
                            title = stringResource(R.string.locations_channel_dropshipping),
                            isSelected = isActiveForDropshipping,
                            onSelect = if (canEdit) ({ isActiveForDropshipping = !isActiveForDropshipping }) else null,
                            modifier = Modifier.weight(1f).fillMaxHeight(),
                            subtitle = stringResource(R.string.locations_channel_current, LocationsSamples.DROPSHIPPING_PICKING_LOCATION),
                            role = Role.Checkbox,
                        )
                    }
                }
            }
            LocationsStatRow(modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
                LocationsStatTile(value = LocationsSamples.MIN_STOCK, label = stringResource(R.string.locations_min_stock), modifier = Modifier.weight(1f))
                LocationsStatTile(value = LocationsSamples.MAX_STOCK, label = stringResource(R.string.locations_max_stock), modifier = Modifier.weight(1f))
            }
            ListGroup(modifier = Modifier.fakeData()) {
                LocationsTileRow(
                    icon = R.drawable.ic_ui_edit,
                    title = stringResource(R.string.locations_note),
                    subtitle = LocationsSamples.NOTE,
                    tone = Tone.Neutral,
                )
            }
            LocationsFootnote(text = stringResource(R.string.locations_sko_help))
        }
        if (showAudit) {
            AuditStockSheet(skuCode = skuCode, onDismiss = { showAudit = false }, locationCode = locationCode)
        }
        if (showMove) {
            MoveStockSheet(skuCode = skuCode, onDismiss = { showMove = false }, fromLocationCode = locationCode)
        }
    }
}

@Composable
private fun SkoSummaryCard(skuCode: String, summary: String, onOpen: (() -> Unit)?, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .then(if (onOpen != null) Modifier.clickable(role = Role.Button, onClick = onOpen) else Modifier)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        ProductPhoto(code = skuCode, size = 64.dp)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = skuCode, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = summary, fontSize = 13.sp, color = colors.textTertiary)
        }
    }
}
