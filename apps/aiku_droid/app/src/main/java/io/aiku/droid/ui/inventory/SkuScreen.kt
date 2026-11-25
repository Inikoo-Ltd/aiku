package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
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
import androidx.compose.ui.semantics.Role
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
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.DetailLineAction
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.locations.stockTypeLabel
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.sync.OfflineCopyNote
import io.aiku.droid.ui.sync.QueueEventEffect
import io.aiku.droid.ui.sync.QueuedActionsBlock
import io.aiku.droid.ui.sync.QueuedRow
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
    var openSheet by remember { mutableStateOf<SkuSheet?>(null) }
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
    QueueEventEffect(event = state.queueEvent, reference = shownCode, onHandled = sko::queueEventHandled, onQueued = { openSheet = null })

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
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = sko::refresh, isEnabled = !state.isWorking),
    ) {
        state.copyAt?.let { OfflineCopyNote(copyAt = it) }
        if (detail != null) {
            QueuedActionsBlock(rows = state.pending.map { QueuedRow(it, skoQueuedText(it, detail)) }, onUndo = sko::undoQueued)
        }
        when {
            detail != null -> SkuBlocks(detail = detail, state = state, actions = actions, onSheet = { openSheet = it })
            state.problem != null -> StockLoadProblem(problem = state.problem, title = stringResource(R.string.inventory_sko_failed), onRetry = sko::retry)
            else -> SkeletonListCard(rows = 4)
        }
    }

    if (detail != null) {
        SkuSheets(sheet = openSheet, detail = detail, state = state, sko = sko, actions = actions, onSheet = { openSheet = it }, onDismiss = { openSheet = null })
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

private sealed interface SkuSheet {
    data class Action(val action: SkoAction) : SkuSheet
    data class LocationMenu(val locationOrgStockId: Long) : SkuSheet
    data class AuditLocation(val locationOrgStockId: Long) : SkuSheet
    data class RemoveLocation(val locationOrgStockId: Long) : SkuSheet
    data class Barcode(val barcode: ShownBarcode) : SkuSheet
}

@Composable
private fun SkuSheets(
    sheet: SkuSheet?,
    detail: OrgStockDetail,
    state: SkoUiState,
    sko: SkoViewModel,
    actions: List<SkoAction>,
    onSheet: (SkuSheet) -> Unit,
    onDismiss: () -> Unit,
) {
    val errorText = state.writeErrors.values.flatten().firstOrNull()
    val locationOf = { id: Long -> detail.locations.firstOrNull { it.locationOrgStockId == id } }

    when (sheet) {
        is SkuSheet.Action -> when (sheet.action) {
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
            SkoAction.RemoveLocations -> Unit
        }
        is SkuSheet.LocationMenu -> locationOf(sheet.locationOrgStockId)?.let { location ->
            LocationActionsSheet(detail = detail, location = location, actions = actions, onSheet = onSheet, onDismiss = onDismiss)
        }
        is SkuSheet.AuditLocation -> AuditStockSheet(
            detail = detail,
            state = state,
            onLoadReasons = sko::loadReasons,
            errorText = errorText,
            onSave = { counts -> sko.write(OrgStockWrite.Audit(counts)) },
            onDismiss = onDismiss,
            locationOrgStockId = sheet.locationOrgStockId,
        )
        is SkuSheet.RemoveLocation -> locationOf(sheet.locationOrgStockId)?.let { location ->
            RemoveLocationConfirm(
                detail = detail,
                location = location,
                onConfirm = {
                    sko.write(OrgStockWrite.RemoveLocations(listOf(location.locationOrgStockId)))
                    onDismiss()
                },
                onDismiss = onDismiss,
            )
        }
        is SkuSheet.Barcode -> BarcodeSheet(barcode = sheet.barcode, subtitle = "${detail.code}$LIST_SEPARATOR${detail.name}", onDismiss = onDismiss)
        null -> Unit
    }
}

@Composable
private fun SkuBlocks(detail: OrgStockDetail, state: SkoUiState, actions: List<SkoAction>, onSheet: (SkuSheet) -> Unit) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    SkuSummary(detail = detail)
    val lines = listOfNotNull(
        detail.packedIn?.takeIf { it > 0 }?.let { packed ->
            val units = if (packed % 1.0 == 0.0) packed.toInt() else 2
            DetailLine(icon = R.drawable.ic_ui_sko, text = pluralStringResource(R.plurals.inventory_packed_in, units, stockText(packed)))
        },
        detail.barcode?.takeIf { it.isNotBlank() }?.let { code ->
            DetailLine(
                icon = R.drawable.ic_ui_scan,
                text = stringResource(R.string.inventory_sko_barcode, code),
                action = barcodeAction(ShownBarcode(title = stringResource(R.string.inventory_barcode_sko_title), value = code), onSheet),
            )
        },
        detail.unitBarcode?.takeIf { it.isNotBlank() }?.let { code ->
            DetailLine(
                icon = R.drawable.ic_ui_scan,
                text = stringResource(R.string.inventory_unit_barcode, code),
                action = barcodeAction(ShownBarcode(title = stringResource(R.string.inventory_barcode_unit_title), value = code), onSheet),
            )
        },
        detail.noteToPickers?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_edit, text = stringResource(R.string.inventory_picker_note, it)) },
        detail.noteToPackers?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_edit, text = stringResource(R.string.inventory_packer_note, it)) },
    )
    if (lines.isNotEmpty()) {
        DetailCard(title = stringResource(R.string.inventory_sko_card_title), lines = lines)
    }
    val coverDays = detail.cover.days
    SkoStockCard(
        quantity = detail.quantityInLocations,
        quantityLabel = detail.quantityInLocationsLabel,
        packedIn = detail.packedIn,
        figures = listOf(
            SkoStockFigure(label = stringResource(R.string.inventory_stat_reserved), value = stockText(detail.quantityReserved, detail.quantityReservedLabel)),
            SkoStockFigure(label = stringResource(R.string.inventory_stat_to_be_picked), value = stockText(detail.quantityToBePicked, detail.quantityToBePickedLabel)),
            SkoStockFigure(
                label = stringResource(R.string.inventory_stat_cover),
                value = coverDays?.let { days ->
                    val whole = days.toInt()
                    pluralStringResource(R.plurals.inventory_cover_days, whole, whole)
                } ?: stringResource(R.string.inventory_cover_unknown),
            ),
        ),
        actions = listOfNotNull(
            SkoStockAction(label = stringResource(R.string.inventory_action_consume), icon = R.drawable.ic_fa_box_open, onClick = { onSheet(SkuSheet.Action(SkoAction.Consume)) }, style = ButtonStyle.Caution)
                .takeIf { SkoAction.Consume in actions },
            SkoStockAction(label = stringResource(R.string.inventory_action_move), icon = R.drawable.ic_fa_forklift, onClick = { onSheet(SkuSheet.Action(SkoAction.Move)) })
                .takeIf { SkoAction.Move in actions },
        ),
    )
    InventorySectionHeading(
        text = stringResource(R.string.inventory_locations_heading),
        trailingText = countText(detail.locations.size),
        actionLabel = stringResource(R.string.inventory_add_location_link).takeIf { SkoAction.AddLocation in actions },
        onAction = { onSheet(SkuSheet.Action(SkoAction.AddLocation)) },
    )
    if (detail.locations.isEmpty()) {
        BoardFootnote(text = stringResource(R.string.inventory_no_locations))
    } else {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            detail.locations.forEach { location ->
                val canAudit = SkoAction.Audit in actions
                val canRemove = SkoAction.RemoveLocations in actions
                SkoLocationCard(
                    location = location,
                    packedIn = detail.packedIn,
                    onClick = when {
                        canAudit || canRemove -> {
                            { onSheet(SkuSheet.LocationMenu(location.locationOrgStockId)) }
                        }
                        can.viewLocations -> {
                            { navigator.open(location.route(detail)) }
                        }
                        else -> null
                    },
                    onAudit = if (canAudit) {
                        { onSheet(SkuSheet.AuditLocation(location.locationOrgStockId)) }
                    } else {
                        null
                    },
                    onRemove = if (canRemove) {
                        { onSheet(SkuSheet.RemoveLocation(location.locationOrgStockId)) }
                    } else {
                        null
                    },
                )
            }
        }
        if (SkoAction.Audit in actions && detail.locations.size >= MIN_LOCATIONS_TO_COUNT_ALL) {
            LinkAction(text = stringResource(R.string.inventory_count_all), onClick = { onSheet(SkuSheet.Action(SkoAction.Audit)) })
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
                    StatusBadge(text = detail.stateLabel, tone = skoStateTone(detail.state))
                }
                if (detail.isOnDemand) {
                    StatusBadge(text = stringResource(R.string.inventory_on_demand), tone = Tone.Primary)
                }
            }
        }
    }
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

private const val MIN_LOCATIONS_TO_COUNT_ALL = 2

internal fun OrgStockLocation.route(detail: OrgStockDetail): LocationSkuRoute = LocationSkuRoute(
    locationId = location.id,
    locationOrgStockId = locationOrgStockId,
    locationCode = location.code,
    skoCode = detail.code,
)

@Composable
private fun barcodeAction(barcode: ShownBarcode, onSheet: (SkuSheet) -> Unit): DetailLineAction =
    DetailLineAction(icon = R.drawable.ic_fa_barcode, description = stringResource(R.string.inventory_show_barcode), onClick = { onSheet(SkuSheet.Barcode(barcode)) })

@Composable
private fun LocationActionsSheet(
    detail: OrgStockDetail,
    location: OrgStockLocation,
    actions: List<SkoAction>,
    onSheet: (SkuSheet) -> Unit,
    onDismiss: () -> Unit,
) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    AikuBottomSheet(
        title = location.location.code,
        subtitle = stringResource(R.string.inventory_in_location, stockText(location.quantity, location.quantityLabel)),
        onDismiss = onDismiss,
    ) {
        if (SkoAction.Audit in actions) {
            AikuButton(
                text = stringResource(R.string.inventory_location_audit),
                style = ButtonStyle.Outline,
                height = 52.dp,
                icon = R.drawable.ic_fa_clipboard_check,
                onClick = { onSheet(SkuSheet.AuditLocation(location.locationOrgStockId)) },
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (can.viewLocations) {
            AikuButton(
                text = stringResource(R.string.inventory_location_open),
                style = ButtonStyle.Outline,
                height = 52.dp,
                icon = R.drawable.ic_ui_location,
                onClick = {
                    onDismiss()
                    navigator.open(location.route(detail))
                },
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (SkoAction.RemoveLocations in actions) {
            AikuButton(
                text = stringResource(R.string.inventory_location_remove),
                style = ButtonStyle.Danger,
                height = 52.dp,
                icon = R.drawable.ic_fa_unlink,
                onClick = { onSheet(SkuSheet.RemoveLocation(location.locationOrgStockId)) },
                modifier = Modifier.fillMaxWidth(),
            )
        }
        AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onDismiss, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun RemoveLocationConfirm(detail: OrgStockDetail, location: OrgStockLocation, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    val code = location.location.code
    ConfirmDialog(
        title = stringResource(R.string.inventory_remove_one_title, code),
        body = if (location.quantity > 0.0) {
            stringResource(R.string.inventory_remove_one_writeoff, stockText(location.quantity, location.quantityLabel), code)
        } else {
            stringResource(R.string.inventory_remove_one_empty, code, detail.code)
        },
        confirmLabel = stringResource(R.string.inventory_remove_one_confirm),
        confirmLook = ButtonLook(ButtonStyle.DangerStrong, R.drawable.ic_fa_unlink),
        onConfirm = onConfirm,
        onDismiss = onDismiss,
    )
}

@Composable
private fun LinkAction(text: String, onClick: () -> Unit) {
    Text(
        text = text,
        fontSize = 14.sp,
        fontWeight = FontWeight.SemiBold,
        color = LocalAikuColors.current.link,
        modifier = Modifier
            .heightIn(min = 48.dp)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 4.dp, vertical = 14.dp),
    )
}

