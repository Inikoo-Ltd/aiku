package io.aiku.droid.ui.locations

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
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
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
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
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationScreen(code: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val location = LocationsSamples.location(code)
    var showAudit by remember { mutableStateOf(false) }
    var showMove by remember { mutableStateOf(false) }
    var showRemove by remember { mutableStateOf(false) }
    val status = stringResource(if (location.kind == LocationKind.Broken) R.string.locations_status_broken else R.string.locations_status_operational)
    val outlineOnSurface = Modifier.background(colors.surface, ControlShape)

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                AppHeader(
                    isSample = true,
                    title = code,
                    kicker = stringResource(R.string.locations_location_kicker, location.area, status),
                    actions = {
                        if (can.moveStock) {
                            HeaderIconButton(
                                icon = R.drawable.ic_ui_check,
                                description = stringResource(R.string.locations_audit_location),
                                onClick = { showAudit = true },
                                filled = true,
                            )
                        }
                    },
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            LocationOverviewCard(code = code, modifier = Modifier.fakeData())
            LocationsHeading(text = stringResource(R.string.locations_heading_skos), sampleCount = LocationsSamples.LOCATION_SKO_COUNT)
            ListGroup(modifier = Modifier.fakeData()) {
                LocationsSamples.skos.forEachIndexed { index, sko ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = sko.code,
                        subtitle = sko.name,
                        chip = stringResource(sko.type.label),
                        chipTone = sko.type.tone,
                        done = sko.quantity.toString(),
                        onClick = { navigator.open(LocationSkuRoute(locationCode = code, skuCode = sko.code)) },
                    )
                }
            }
            if (can.moveStock) {
                LocationsHeading(text = stringResource(R.string.locations_heading_actions))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    AikuButton(
                        text = stringResource(R.string.locations_action_audit),
                        onClick = { showAudit = true },
                        modifier = outlineOnSurface.weight(1f),
                        style = ButtonStyle.Outline,
                        icon = R.drawable.ic_ui_check,
                        height = 52.dp,
                    )
                    AikuButton(
                        text = stringResource(R.string.locations_action_move_all),
                        onClick = { showMove = true },
                        modifier = outlineOnSurface.weight(1f),
                        style = ButtonStyle.Outline,
                        icon = R.drawable.ic_ui_location,
                        height = 52.dp,
                    )
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    AikuButton(
                        text = stringResource(R.string.locations_action_move_some),
                        onClick = { showMove = true },
                        modifier = outlineOnSurface.weight(1f),
                        style = ButtonStyle.Outline,
                        icon = R.drawable.ic_ui_location,
                        height = 52.dp,
                    )
                    AikuButton(
                        text = stringResource(R.string.locations_action_remove),
                        onClick = { showRemove = true },
                        modifier = Modifier.weight(1f),
                        style = ButtonStyle.Danger,
                        height = 52.dp,
                    )
                }
            }
            ListGroup(modifier = Modifier.fakeData()) {
                LocationsTileRow(
                    icon = R.drawable.ic_ui_sko,
                    title = stringResource(R.string.locations_pallets_title),
                    subtitle = stringResource(R.string.locations_pallets_none),
                    tone = Tone.Neutral,
                )
                if (can.viewInventory) {
                    RowDivider()
                    LocationsTileRow(
                        icon = R.drawable.ic_ui_clock,
                        title = stringResource(R.string.locations_stock_movements),
                        subtitle = stringResource(R.string.locations_stock_movements_body),
                        tone = Tone.Neutral,
                        onClick = { navigator.open(StockHistoryRoute) },
                    )
                }
            }
            LocationsFootnote(text = stringResource(R.string.locations_detail_help))
        }
        if (showAudit) {
            AuditLocationSheet(locationCode = code, onDismiss = { showAudit = false })
        }
        if (showMove) {
            MoveSkusSheet(locationCode = code, onDismiss = { showMove = false })
        }
        if (showRemove) {
            RemoveSkusSheet(locationCode = code, onDismiss = { showRemove = false })
        }
    }
}

@Composable
private fun LocationOverviewCard(code: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val allowedFlags = LocationsSamples.locationFlags.sortedByDescending { it.isAllowed }.map { flag ->
        val label = stringResource(flag.label)
        if (flag.isAllowed) label else stringResource(R.string.locations_flag_not, label)
    }
    val slots = pluralStringResource(
        R.plurals.locations_slots,
        LocationsSamples.LOCATION_SLOTS,
        LocationsSamples.LOCATION_SLOTS,
        LocationsSamples.LOCATION_EMPTY_SLOTS,
    )

    Column(
        verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Text(text = stringResource(R.string.locations_overview), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = colors.text)
        OverviewLine(icon = R.drawable.ic_ui_sko, text = stringResource(R.string.locations_allow, allowedFlags.joinToString(LIST_SEPARATOR)))
        OverviewLine(
            icon = R.drawable.ic_ui_weight,
            text = stringResource(R.string.locations_capacity, LocationsSamples.LOCATION_MAX_WEIGHT, LocationsSamples.LOCATION_MAX_VOLUME, slots),
        )
        OverviewLine(icon = R.drawable.ic_ui_scan, text = stringResource(R.string.locations_barcode, code.lowercase()))
        OverviewLine(icon = R.drawable.ic_ui_clock, text = stringResource(R.string.locations_audited, LocationsSamples.LOCATION_AUDITED_ON))
    }
}

@Composable
private fun OverviewLine(@DrawableRes icon: Int, text: String) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier.fillMaxWidth().heightIn(min = 28.dp),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(16.dp))
        Text(text = text, fontSize = 14.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
    }
}
