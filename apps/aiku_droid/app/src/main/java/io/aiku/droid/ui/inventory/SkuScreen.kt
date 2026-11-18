package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
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
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockLocation
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.locations.stockTypeLabel
import io.aiku.droid.ui.locations.stockTypeTone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private data class StockActionButtonSpec(val action: SkoAction, @StringRes val label: Int, @DrawableRes val icon: Int)

private val actionButtons = listOf(
    StockActionButtonSpec(SkoAction.Audit, R.string.inventory_action_audit, R.drawable.ic_ui_check),
    StockActionButtonSpec(SkoAction.Move, R.string.inventory_action_move, R.drawable.ic_ui_location),
    StockActionButtonSpec(SkoAction.Consume, R.string.inventory_action_consume, R.drawable.ic_ui_sko),
    StockActionButtonSpec(SkoAction.AddLocation, R.string.inventory_action_add_location, R.drawable.ic_ui_location),
)

@Composable
fun SkuScreen(sko: SkoViewModel, code: String) {
    val state by sko.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { sko.onStart() }

    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = sko::signInAgain)
        } else {
            SkuContent(state = state, sko = sko, code = code)
        }
    }
}

@Composable
private fun SkuContent(state: SkoUiState, sko: SkoViewModel, code: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val warehouse = LocalWarehouse.current
    var openSheet by remember { mutableStateOf<SkoAction?>(null) }
    val detail = state.orgStock
    val actions = detail?.allowedActions(warehouse.can).orEmpty()
    val shownCode = detail?.code ?: code
    val event = state.event
    val doneText = (event as? StockEvent.Done)?.let { done -> done.message ?: skoDoneText(done.action, shownCode) }
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
        sko.eventHandled()
    }

    AppScreen(
        header = {
            AppHeader(
                title = shownCode,
                kicker = detail?.stateLabel?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.inventory_sko_kicker, it) }
                    ?: stringResource(R.string.inventory_sko_card_title),
                actions = {
                    if (detail != null) {
                        HeaderIconButton(
                            icon = R.drawable.ic_ui_clock,
                            description = stringResource(R.string.section_stock_history),
                            onClick = { navigator.open(StockHistoryRoute(code = detail.code, orgStockId = detail.id)) },
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
            detail != null -> SkuBlocks(detail = detail, state = state, actions = actions, onAction = { openSheet = it })
            state.problem != null -> StockLoadProblem(problem = state.problem, title = stringResource(R.string.inventory_sko_failed), onRetry = sko::retry)
            else -> SkeletonListCard(rows = 4)
        }
    }

    if (detail != null) {
        SkuSheets(sheet = openSheet, detail = detail, state = state, sko = sko, onDismiss = { openSheet = null })
    }
}

@Composable
private fun skoDoneText(action: Any, code: String): String? = when (action) {
    is OrgStockWrite.Audit -> stringResource(R.string.inventory_audit_saved, code)
    is OrgStockWrite.Move -> stringResource(R.string.inventory_moved)
    is OrgStockWrite.Consume -> stringResource(R.string.inventory_consumed)
    is OrgStockWrite.AddLocation -> stringResource(R.string.inventory_location_added)
    is OrgStockWrite.RemoveLocations -> pluralStringResource(R.plurals.inventory_removed, action.locationOrgStockIds.size, action.locationOrgStockIds.size)
    else -> null
}

@Composable
private fun SkuSheets(sheet: SkoAction?, detail: OrgStockDetail, state: SkoUiState, sko: SkoViewModel, onDismiss: () -> Unit) {
    val errorText = state.writeErrors.values.flatten().firstOrNull()

    when (sheet) {
        SkoAction.Audit -> AuditStockSheet(
            detail = detail,
            state = state,
            onLoadReasons = sko::loadReasons,
            errorText = errorText,
            onSave = { counts -> sko.write(OrgStockWrite.Audit(counts)) },
            onDismiss = onDismiss,
        )
        SkoAction.Move -> MoveStockSheet(detail = detail, isWorking = state.isWorking, errorText = errorText, onMove = sko::write, onDismiss = onDismiss)
        SkoAction.Consume -> ConsumeStockSheet(detail = detail, isWorking = state.isWorking, errorText = errorText, onConsume = sko::write, onDismiss = onDismiss)
        SkoAction.AddLocation -> AddLocationSheet(
            detail = detail,
            finder = sko.finder,
            isWorking = state.isWorking,
            errorText = errorText,
            onAdd = sko::write,
            onDismiss = onDismiss,
        )
        SkoAction.RemoveLocations -> RemoveLocationsSheet(detail = detail, isWorking = state.isWorking, errorText = errorText, onRemove = sko::write, onDismiss = onDismiss)
        null -> Unit
    }
}

@Composable
private fun SkuBlocks(detail: OrgStockDetail, state: SkoUiState, actions: List<SkoAction>, onAction: (SkoAction) -> Unit) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    SkuSummary(detail = detail)
    val lines = listOfNotNull(
        detail.packedIn?.takeIf { it > 0 }?.let { packed ->
            val units = if (packed % 1.0 == 0.0) packed.toInt() else 2
            DetailLine(icon = R.drawable.ic_ui_sko, text = pluralStringResource(R.plurals.inventory_packed_in, units, stockText(packed)))
        },
        detail.barcode?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_scan, text = stringResource(R.string.inventory_sko_barcode, it)) },
        detail.unitBarcode?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_scan, text = stringResource(R.string.inventory_unit_barcode, it)) },
        detail.noteToPickers?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_edit, text = stringResource(R.string.inventory_picker_note, it)) },
        detail.noteToPackers?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_edit, text = stringResource(R.string.inventory_packer_note, it)) },
    )
    if (lines.isNotEmpty()) {
        DetailCard(title = stringResource(R.string.inventory_sko_card_title), lines = lines)
    }
    val coverDays = detail.cover.days
    InventoryStatGrid(
        stats = listOf(
            InventoryStat(value = stockText(detail.quantityInLocations, detail.quantityInLocationsLabel), label = stringResource(R.string.inventory_stat_in_locations)),
            InventoryStat(value = stockText(detail.quantityReserved, detail.quantityReservedLabel), label = stringResource(R.string.inventory_stat_reserved)),
            InventoryStat(value = stockText(detail.quantityToBePicked, detail.quantityToBePickedLabel), label = stringResource(R.string.inventory_stat_to_be_picked)),
            InventoryStat(
                value = coverDays?.let { days ->
                    val whole = days.toInt()
                    pluralStringResource(R.plurals.inventory_cover_days, whole, whole)
                } ?: stringResource(R.string.inventory_cover_unknown),
                label = stringResource(R.string.inventory_stat_cover),
            ),
        ),
        columns = 4,
    )
    InventorySectionHeading(text = stringResource(R.string.inventory_locations_heading), trailingText = countText(detail.locations.size))
    if (detail.locations.isEmpty()) {
        BoardFootnote(text = stringResource(R.string.inventory_no_locations))
    } else {
        ListGroup {
            detail.locations.forEachIndexed { index, location ->
                if (index > 0) {
                    RowDivider()
                }
                IconItemRow(
                    icon = R.drawable.ic_ui_location,
                    title = location.location.code,
                    subtitle = skoLocationSubtitle(location),
                    chip = skoLocationChip(location),
                    chipTone = stockTypeTone(location.stockType),
                    trailing = stockText(location.quantity, location.quantityLabel),
                    onClick = if (can.viewLocations) {
                        {
                            navigator.open(
                                LocationSkuRoute(
                                    locationId = location.location.id,
                                    locationOrgStockId = location.locationOrgStockId,
                                    locationCode = location.location.code,
                                    skoCode = detail.code,
                                ),
                            )
                        }
                    } else {
                        null
                    },
                )
            }
        }
    }
    state.movements?.takeIf { it.isNotEmpty() }?.let { movements ->
        InventorySectionHeading(
            text = stringResource(R.string.inventory_recent_movements),
            actionLabel = stringResource(R.string.inventory_all),
            onAction = { navigator.open(StockHistoryRoute(code = detail.code, orgStockId = detail.id)) },
        )
        ListGroup {
            movements.forEachIndexed { index, movement ->
                if (index > 0) {
                    RowDivider()
                }
                MovementRow(movement = movement, title = movement.location?.code ?: detail.code, showSko = false)
            }
        }
    }
    if (actions.isNotEmpty()) {
        StockActions(actions = actions, onAction = onAction)
    }
}

@Composable
private fun SkuSummary(detail: OrgStockDetail) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth(),
    ) {
        ProductPhoto(code = detail.code, size = 96.dp, imageUrl = detail.imageUrl)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(text = detail.name, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            detail.family?.let { family ->
                Text(text = listOf(family.code, family.name).filter { it.isNotBlank() }.joinToString(LIST_SEPARATOR), fontSize = 13.sp, color = colors.textTertiary)
            }
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                if (detail.stateLabel.isNotBlank()) {
                    StatusBadge(text = detail.stateLabel, tone = if (detail.state == ACTIVE_STATE) Tone.Success else Tone.Warn)
                }
                if (detail.isOnDemand) {
                    StatusBadge(text = stringResource(R.string.inventory_on_demand), tone = Tone.Primary)
                }
            }
        }
    }
}

@Composable
private fun StockActions(actions: List<SkoAction>, onAction: (SkoAction) -> Unit) {
    InventorySectionHeading(text = stringResource(R.string.inventory_stock_actions))
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        actionButtons.filter { it.action in actions }.chunked(2).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                row.forEach { spec ->
                    StockActionButton(
                        text = stringResource(spec.label),
                        icon = spec.icon,
                        onClick = { onAction(spec.action) },
                        modifier = Modifier.weight(1f),
                    )
                }
                if (row.size == 1) {
                    Spacer(modifier = Modifier.weight(1f))
                }
            }
        }
    }
    if (SkoAction.RemoveLocations in actions) {
        StockActionButton(
            text = stringResource(R.string.inventory_action_remove_locations),
            onClick = { onAction(SkoAction.RemoveLocations) },
            icon = R.drawable.ic_ui_trash,
            isDanger = true,
            modifier = Modifier.fillMaxWidth(),
        )
    }
    BoardFootnote(text = stringResource(R.string.inventory_stock_actions_footnote))
}

@Composable
internal fun skoLocationChip(location: OrgStockLocation): String? {
    val type = stockTypeLabel(location.stockType)?.let { stringResource(it) } ?: return null
    val channels = listOfNotNull(
        if (location.isDefaultWholesale) stringResource(R.string.inventory_channel_wholesale) else null,
        if (location.isDefaultDropshipping) stringResource(R.string.inventory_channel_dropshipping) else null,
    )
    return (listOf(type) + channels).joinToString(LIST_SEPARATOR)
}

@Composable
private fun skoLocationSubtitle(location: OrgStockLocation): String {
    val zone = organisationZone()
    val min = location.minStock
    val max = location.maxStock
    val replenish = location.replenishmentStock
    val levels = when {
        min != null && max != null -> stringResource(R.string.inventory_min_max, stockText(min), stockText(max))
        min != null -> stringResource(R.string.inventory_min, stockText(min))
        max != null -> stringResource(R.string.inventory_max, stockText(max))
        replenish != null -> stringResource(R.string.inventory_replenish, stockText(replenish))
        else -> null
    }
    val audited = dayText(location.auditedAt, zone)?.let { stringResource(R.string.inventory_audited_on, it) } ?: stringResource(R.string.inventory_never_audited)
    return listOfNotNull(levels, audited).joinToString(LIST_SEPARATOR)
}
