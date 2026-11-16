package io.aiku.droid.ui.locations

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
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
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.inventory.dayText
import io.aiku.droid.ui.inventory.refusalText
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationScreen(location: LocationViewModel, code: String) {
    val state by location.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { location.onStart() }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = location::signInAgain)
        } else {
            LocationContent(state = state, location = location, code = code)
        }
    }
}

@Composable
private fun LocationContent(state: LocationUiState, location: LocationViewModel, code: String) {
    val toasts = LocalToasts.current
    val warehouse = LocalWarehouse.current
    val finder by location.finder.state.collectAsStateWithLifecycle()
    var openSheet by remember { mutableStateOf<LocationAction?>(null) }
    val detail = state.location
    val actions = detail?.allowedActions(warehouse.can).orEmpty()
    val shownCode = detail?.code ?: code
    val event = state.event
    val doneText = (event as? StockEvent.Done)?.let { done -> locationDoneText(done, shownCode, finder.picked?.code) }
    val refusedText = (event as? StockEvent.Refused)?.let { refusalText(it) }

    LaunchedEffect(event) {
        when (event) {
            is StockEvent.Done -> {
                doneText?.let { toasts.show(it, ToastKind.Success) }
                openSheet = null
            }
            is StockEvent.Refused -> refusedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        location.eventHandled()
    }

    AppScreen(
        header = {
            AppHeader(
                title = shownCode,
                kicker = detail?.let { loaded ->
                    stringResource(R.string.locations_location_kicker, listOfNotNull(loaded.summary.area?.label?.takeIf { it.isNotBlank() }, statusLabel(loaded.summary)).joinToString(LIST_SEPARATOR))
                } ?: stringResource(R.string.locations_location_kicker_short),
                actions = {
                    if (LocationAction.Audit in actions) {
                        HeaderIconButton(
                            icon = R.drawable.ic_ui_check,
                            description = stringResource(R.string.locations_audit_location),
                            onClick = { openSheet = LocationAction.Audit },
                            filled = true,
                        )
                    }
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        when {
            detail != null -> LocationBlocks(detail = detail, actions = actions, onAction = { openSheet = it })
            state.problem != null -> StockLoadProblem(problem = state.problem, title = stringResource(R.string.locations_location_failed), onRetry = location::retry)
            else -> SkeletonListCard(rows = 4)
        }
        LocationsFootnote(text = stringResource(R.string.locations_detail_help))
    }
    if (detail != null) {
        LocationSheets(sheet = openSheet, detail = detail, state = state, location = location, onDismiss = { openSheet = null })
    }
}

@Composable
private fun locationDoneText(done: StockEvent.Done, code: String, targetCode: String?): String? = when (val action = done.action) {
    is LocationWrite.Audit -> stringResource(R.string.locations_audit_saved, code)
    is LocationWrite.MoveAll, is LocationWrite.MoveSome -> stringResource(R.string.locations_move_done, targetCode ?: "")
    is LocationWrite.RemoveOrgStocks -> {
        val removed = done.removed ?: action.orgStockIds.size
        pluralStringResource(R.plurals.locations_removed, removed, removed, code)
    }
    else -> null
}

@Composable
internal fun LocationSheets(
    sheet: LocationAction?,
    detail: LocationDetail,
    state: LocationUiState,
    location: LocationViewModel,
    onDismiss: () -> Unit,
    onlyLinkId: Long? = null,
) {
    val errorText = state.writeErrors.values.flatten().firstOrNull()

    when (sheet) {
        LocationAction.Audit -> AuditLocationSheet(
            detail = detail,
            onlyLinkId = onlyLinkId,
            state = state,
            onLoadReasons = location::loadReasons,
            errorText = errorText,
            onSave = { counts -> location.write(LocationWrite.Audit(counts)) },
            onDismiss = onDismiss,
        )
        LocationAction.MoveAll, LocationAction.MoveSome -> MoveSkusSheet(
            detail = detail,
            moveAll = sheet == LocationAction.MoveAll,
            onlyLinkId = onlyLinkId,
            finder = location.finder,
            isWorking = state.isWorking,
            errorText = errorText,
            onMove = location::write,
            onDismiss = onDismiss,
        )
        LocationAction.Remove -> RemoveSkusSheet(
            detail = detail,
            isWorking = state.isWorking,
            errorText = errorText,
            onRemove = { ids -> location.write(LocationWrite.RemoveOrgStocks(ids)) },
            onDismiss = onDismiss,
        )
        null -> Unit
    }
}

@Composable
private fun LocationBlocks(detail: LocationDetail, actions: List<LocationAction>, onAction: (LocationAction) -> Unit) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val outlineOnSurface = Modifier.background(colors.surface, ControlShape)

    LocationOverviewCard(location = detail.summary)
    LocationsHeading(text = stringResource(R.string.locations_heading_skos), count = countText(detail.orgStocks.size))
    if (detail.orgStocks.isEmpty()) {
        LocationsFootnote(text = stringResource(R.string.locations_no_skos))
    } else {
        ListGroup {
            detail.orgStocks.forEachIndexed { index, sko ->
                if (index > 0) {
                    RowDivider()
                }
                val type = sko.stockType
                ItemRow(
                    code = sko.code,
                    subtitle = sko.name,
                    chip = stockTypeLabel(type)?.let { stringResource(it) },
                    chipTone = stockTypeTone(type),
                    done = stockText(sko.stock),
                    imageUrl = sko.imageUrl,
                    onClick = {
                        navigator.open(
                            LocationSkuRoute(locationId = detail.id, locationOrgStockId = sko.locationOrgStockId, locationCode = detail.code, skoCode = sko.code),
                        )
                    },
                )
            }
        }
    }
    if (actions.isNotEmpty()) {
        LocationsHeading(text = stringResource(R.string.locations_heading_actions))
        val buttons = actions.map { action ->
            when (action) {
                LocationAction.Audit -> Triple(action, R.string.locations_action_audit, R.drawable.ic_ui_check)
                LocationAction.MoveAll -> Triple(action, R.string.locations_action_move_all, R.drawable.ic_ui_location)
                LocationAction.MoveSome -> Triple(action, R.string.locations_action_move_some, R.drawable.ic_ui_location)
                LocationAction.Remove -> Triple(action, R.string.locations_action_remove, null)
            }
        }
        buttons.chunked(2).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                row.forEach { (action, label, icon) ->
                    AikuButton(
                        text = stringResource(label),
                        onClick = { onAction(action) },
                        modifier = if (action == LocationAction.Remove) Modifier.weight(1f) else outlineOnSurface.weight(1f),
                        style = if (action == LocationAction.Remove) ButtonStyle.Danger else ButtonStyle.Outline,
                        icon = icon,
                        height = 52.dp,
                    )
                }
                if (row.size == 1) {
                    Spacer(modifier = Modifier.weight(1f))
                }
            }
        }
    }
    ListGroup {
        if (detail.pallets.isEmpty()) {
            LocationsTileRow(
                icon = R.drawable.ic_ui_sko,
                title = stringResource(R.string.locations_pallets_title),
                subtitle = stringResource(R.string.locations_pallets_none),
                tone = Tone.Neutral,
            )
        } else {
            detail.pallets.forEachIndexed { index, pallet ->
                if (index > 0) {
                    RowDivider()
                }
                LocationsTileRow(
                    icon = R.drawable.ic_ui_sko,
                    title = pallet.reference,
                    subtitle = listOfNotNull(pallet.customerName, pallet.customerReference, pallet.stateLabel)
                        .filter { it.isNotBlank() }
                        .joinToString(LIST_SEPARATOR)
                        .ifBlank { stringResource(R.string.locations_pallets_title) },
                    tone = Tone.Neutral,
                )
            }
        }
        RowDivider()
        LocationsTileRow(
            icon = R.drawable.ic_ui_clock,
            title = stringResource(R.string.locations_stock_movements),
            subtitle = stringResource(R.string.locations_stock_movements_body),
            tone = Tone.Neutral,
            onClick = { navigator.open(StockHistoryRoute(code = detail.code, locationId = detail.id)) },
        )
    }
}

@Composable
private fun LocationOverviewCard(location: LocationSummary) {
    val colors = LocalAikuColors.current
    val zone = organisationZone()
    val flags = listOf(
        R.string.locations_flag_stocks to location.allowStocks,
        R.string.locations_flag_dropshipping to location.allowDropshipping,
        R.string.locations_flag_fulfilment to location.allowFulfilment,
    ).sortedByDescending { it.second }.map { (label, isAllowed) ->
        val text = stringResource(label)
        if (isAllowed) text else stringResource(R.string.locations_flag_not, text)
    }
    val capacity = listOfNotNull(capacityText(location.maxWeight, location.maxVolume), slotsText(location)).joinToString(LIST_SEPARATOR)
    val audited = dayText(location.lastAuditedAt, zone)

    Column(
        verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Text(text = stringResource(R.string.locations_overview), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = colors.text)
        OverviewLine(icon = R.drawable.ic_ui_sko, text = stringResource(R.string.locations_allow, flags.joinToString(LIST_SEPARATOR)))
        if (capacity.isNotBlank()) {
            OverviewLine(icon = R.drawable.ic_ui_weight, text = capacity)
        }
        location.barcode?.takeIf { it.isNotBlank() }?.let { barcode ->
            OverviewLine(icon = R.drawable.ic_ui_scan, text = stringResource(R.string.locations_barcode, barcode))
        }
        OverviewLine(
            icon = R.drawable.ic_ui_clock,
            text = audited?.let { stringResource(R.string.locations_audited, it) } ?: stringResource(R.string.locations_never_audited),
        )
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
