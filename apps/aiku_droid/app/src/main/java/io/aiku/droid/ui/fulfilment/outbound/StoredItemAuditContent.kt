package io.aiku.droid.ui.fulfilment.outbound

import android.content.res.Resources
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.AuditItemTarget
import io.aiku.droid.data.api.FulfilmentOutboundErrorKey
import io.aiku.droid.data.api.OutboundStoredItemRef
import io.aiku.droid.data.api.StoredItemAudit
import io.aiku.droid.data.api.StoredItemAuditActionKey
import io.aiku.droid.data.api.StoredItemAuditLine
import io.aiku.droid.data.api.StoredItemAuditType
import io.aiku.droid.data.api.StoredItemAuditWrite
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.forConfirm
import io.aiku.droid.ui.fulfilment.FulfilmentRow
import io.aiku.droid.ui.fulfilment.FulfilmentScanPrompt
import io.aiku.droid.ui.fulfilment.dotJoined
import io.aiku.droid.ui.goodsin.HelperNote
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.ParcelInputField
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private data class CountTarget(val storedItemId: Long, val reference: String, val name: String, val expected: Int, val counted: Int?, val deltaId: Long?)

private sealed interface AuditSheet {
    data class Count(val target: CountTarget) : AuditSheet
    data object AddItem : AuditSheet
    data object Complete : AuditSheet
}

private fun StoredItemAuditLine.countTarget(): CountTarget = CountTarget(
    storedItemId = storedItem.id,
    reference = storedItem.reference,
    name = storedItem.name,
    expected = quantity,
    counted = auditedQuantity,
    deltaId = deltaId,
)

@Composable
internal fun StoredItemAuditContent(audit: StoredItemAuditViewModel, reference: String) {
    val state by audit.state.collectAsStateWithLifecycle()
    val loaded = state.audit

    LifecycleEventEffect(Lifecycle.Event.ON_START) { audit.onStart() }

    when {
        state.isSessionExpired -> SessionExpiredScreen(onSignIn = audit::signInAgain)
        loaded != null -> AuditContent(viewModel = audit, state = state, audit = loaded)
        else -> AuditLoading(
            reference = reference,
            problem = state.problem,
            onRetry = audit::retry,
            refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = audit::refresh),
        )
    }
}

@Composable
private fun AuditLoading(reference: String, problem: GoodsOutProblem?, onRetry: () -> Unit, refresh: PullRefresh) {
    AppScreen(
        header = { AppHeader(title = reference, kicker = stringResource(R.string.fulfilment_audit_kicker)) },
        activeTab = MainTab.Menu,
        showBar = true,
        refresh = refresh,
    ) {
        if (problem == null) {
            SkeletonListCard(rows = 2)
            SkeletonListCard(rows = 3)
        } else {
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_out_audit_failed, reference), onRetry = onRetry)
        }
    }
}

@Composable
private fun AuditContent(viewModel: StoredItemAuditViewModel, state: StoredItemAuditUiState, audit: StoredItemAudit) {
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    var sheet by remember { mutableStateOf<AuditSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val openSheet: (AuditSheet) -> Unit = {
        sheetProblem = null
        viewModel.clearWriteErrors()
        sheet = it
    }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
    }
    val canCount = audit.can(StoredItemAuditActionKey.COUNT)
    val canAdd = audit.can(StoredItemAuditActionKey.ADD_ITEM)
    val complete = audit.action(StoredItemAuditActionKey.COMPLETE)
    val palletReference = audit.pallet?.reference.orEmpty()

    OutboundEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet is AuditSheet.Count) ({ sheetProblem = it }) else null,
        doneText = { done -> auditDoneText(done.action, audit, resources) },
        onDone = { done ->
            if (done.action !is StoredItemAuditWrite.Add) {
                closeSheet()
            }
            if (done.action == StoredItemAuditWrite.Complete) {
                navigator.back()
            }
        },
    )
    ScanEffect(enabled = canCount) { scan -> viewModel.scan(scan.code) }

    AppScreen(
        header = {
            AppHeader(
                title = audit.reference.ifBlank { palletReference },
                kicker = dotJoined(
                    stringResource(R.string.fulfilment_audit_kicker),
                    stringResource(R.string.fulfilment_out_audit_pallet, palletReference),
                    audit.stateLabel,
                ),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (complete != null) {
                AikuButton(
                    text = complete.label.ifBlank { stringResource(R.string.fulfilment_audit_complete) },
                    onClick = { openSheet(AuditSheet.Complete) },
                    style = fulfilmentOutboundActionLook(complete.key).style,
                    icon = fulfilmentOutboundActionLook(complete.key).icon,
                    isLoading = state.working == StoredItemAuditWrite.Complete,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            } else {
                FooterStatus(
                    title = audit.stateLabel,
                    body = pluralStringResource(R.plurals.fulfilment_out_audit_counted, audit.counts.lines, audit.counts.counted, audit.counts.lines),
                )
            }
        },
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh, isEnabled = state.canRefresh),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.fulfilment_out_refresh_failed), onRetry = viewModel::retry) }
        state.unsure?.let { unsure ->
            InlineAlert(
                title = stringResource(R.string.fulfilment_out_unsure_title),
                body = stringResource(R.string.fulfilment_out_unsure_scan, ((unsure as? StoredItemAuditWrite.Add)?.target as? AuditItemTarget.Barcode)?.code.orEmpty()),
                tone = AlertTone.Warn,
                actionLabel = stringResource(R.string.goodsout_try_again),
                onAction = viewModel::retryUnsure,
            )
        }
        AuditPalletCard(audit = audit)
        if (canCount) {
            FulfilmentScanPrompt(
                title = stringResource(R.string.fulfilment_audit_scan_title),
                instruction = stringResource(R.string.fulfilment_audit_scan_instruction, palletReference),
                target = state.lastScanned ?: stringResource(R.string.fulfilment_out_nothing_scanned),
                detail = when {
                    state.queuedScans > 0 -> pluralStringResource(R.plurals.fulfilment_out_scans_waiting, state.queuedScans, state.queuedScans)
                    state.lastScanned != null -> stringResource(R.string.fulfilment_out_last_scanned)
                    else -> null
                },
            )
        }
        SectionHeading(
            title = stringResource(R.string.fulfilment_stored_items),
            caption = pluralStringResource(R.plurals.fulfilment_out_audit_counted, audit.counts.lines, audit.counts.counted, audit.counts.lines),
        )
        if (audit.lines.isEmpty() && !canAdd) {
            HelperNote(text = stringResource(R.string.fulfilment_out_audit_no_items))
        } else {
            ListGroup {
                audit.lines.forEachIndexed { index, line ->
                    if (index > 0) {
                        RowDivider()
                    }
                    AuditLineRow(line = line, onOpen = if (canCount) ({ openSheet(AuditSheet.Count(line.countTarget())) }) else null)
                }
                if (canAdd) {
                    if (audit.lines.isNotEmpty()) {
                        RowDivider()
                    }
                    FulfilmentRow(
                        title = stringResource(R.string.fulfilment_audit_add_item),
                        subtitle = stringResource(R.string.fulfilment_out_audit_add_item_hint),
                        leading = { IconTile(icon = R.drawable.ic_ui_search, tone = Tone.Neutral) },
                        onClick = {
                            viewModel.searchChoices("")
                            openSheet(AuditSheet.AddItem)
                        },
                    )
                }
            }
        }
        HelperNote(text = stringResource(R.string.fulfilment_out_audit_note))
    }

    when (val current = sheet) {
        is AuditSheet.Count -> CountSheet(
            target = current.target,
            isWorking = state.working is StoredItemAuditWrite.Count || state.working is StoredItemAuditWrite.RemoveCount,
            fieldError = state.writeErrors[FulfilmentOutboundErrorKey.AUDITED_QUANTITY]?.firstOrNull(),
            problem = sheetProblem,
            onSave = { quantity -> viewModel.count(current.target.storedItemId, quantity) },
            onRemove = current.target.deltaId?.let { deltaId -> { viewModel.removeCount(deltaId) } },
            onDismiss = closeSheet,
        )
        AuditSheet.AddItem -> AddItemSheet(
            state = state,
            onSearch = viewModel::searchChoices,
            onRetry = viewModel::loadChoices,
            onChoose = { item ->
                openSheet(AuditSheet.Count(CountTarget(storedItemId = item.id, reference = item.reference, name = item.name, expected = 0, counted = null, deltaId = null)))
            },
            onDismiss = closeSheet,
        )
        AuditSheet.Complete -> ConfirmDialog(
            title = stringResource(R.string.fulfilment_out_complete_title, audit.reference.ifBlank { palletReference }),
            body = stringResource(R.string.fulfilment_out_complete_body, palletReference),
            confirmLabel = complete?.label?.ifBlank { null } ?: stringResource(R.string.fulfilment_audit_complete),
            confirmLook = fulfilmentOutboundActionLook(StoredItemAuditActionKey.COMPLETE).forConfirm(),
            onConfirm = {
                sheet = null
                viewModel.complete()
            },
            onDismiss = closeSheet,
        )
        null -> Unit
    }
}

private fun auditDoneText(action: Any, audit: StoredItemAudit, resources: Resources): String? = when (action) {
    is StoredItemAuditWrite.Count -> (action.target as? AuditItemTarget.Item)?.let { target ->
        resources.getString(R.string.fulfilment_out_toast_counted, audit.line(target.storedItemId)?.storedItem?.reference.orEmpty(), action.auditedQuantity)
    }
    is StoredItemAuditWrite.Add -> (action.target as? AuditItemTarget.Barcode)?.let { target ->
        audit.lineForCode(target.code)?.let { line -> resources.getString(R.string.fulfilment_out_toast_counted, line.storedItem.reference, line.auditedQuantity ?: 0) }
    }
    is StoredItemAuditWrite.RemoveCount -> resources.getString(R.string.fulfilment_out_toast_count_removed)
    StoredItemAuditWrite.Complete -> resources.getString(R.string.fulfilment_audit_completed_toast, audit.reference)
    else -> null
}

@Composable
private fun AuditPalletCard(audit: StoredItemAudit) {
    val pallet = audit.pallet
    val lines = listOfNotNull(
        pallet?.let { DetailLine(icon = R.drawable.ic_ui_pallet, text = dotJoined(it.reference, it.customerReference)) },
        DetailLine(icon = R.drawable.ic_ui_location, text = pallet?.location?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.fulfilment_out_no_location)),
        audit.customer?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
    )
    DetailCard(title = stringResource(R.string.fulfilment_type_pallet), lines = lines)
}

@Composable
private fun AuditLineRow(line: StoredItemAuditLine, onOpen: (() -> Unit)?) {
    val difference = line.difference
    val chip = when {
        difference == null -> if (line.isNewItem) stringResource(R.string.fulfilment_out_audit_new_item) else null
        line.auditType == StoredItemAuditType.SET_UP -> line.auditTypeLabel?.takeIf { it.isNotBlank() } ?: stringResource(R.string.fulfilment_out_audit_set_up)
        difference == 0 -> stringResource(R.string.fulfilment_audit_no_change)
        difference < 0 -> stringResource(R.string.fulfilment_audit_subtraction, -difference)
        else -> stringResource(R.string.fulfilment_audit_addition, difference)
    }
    val tone = auditTypeTone(line.auditType, difference)

    ItemRow(
        code = line.storedItem.reference,
        subtitle = dotJoined(
            line.storedItem.name,
            if (line.isCounted) stringResource(R.string.fulfilment_audit_expected, line.quantity) else stringResource(R.string.fulfilment_audit_expected_not_counted, line.quantity),
        ),
        chip = chip,
        chipTone = tone,
        done = (line.auditedQuantity ?: 0).toString(),
        required = line.quantity.toString(),
        quantityTone = tone.takeIf { line.isCounted },
        showPhoto = false,
        onClick = onOpen,
    )
}

@Composable
private fun CountSheet(
    target: CountTarget,
    isWorking: Boolean,
    fieldError: String?,
    problem: String?,
    onSave: (Int) -> Unit,
    onRemove: (() -> Unit)?,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var text by remember(target) { mutableStateOf((target.counted ?: target.expected).toString()) }
    var isMissing by remember(target) { mutableStateOf(false) }
    val counted = text.trim().toIntOrNull()

    AikuBottomSheet(
        title = target.reference,
        subtitle = target.name.takeIf { it.isNotBlank() },
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem.takeIf { fieldError == null })
            AikuButton(
                text = stringResource(R.string.fulfilment_out_save_count),
                onClick = {
                    if (counted == null) isMissing = true else onSave(counted)
                },
                style = fulfilmentOutboundActionLook(StoredItemAuditActionKey.COUNT).style,
                icon = fulfilmentOutboundActionLook(StoredItemAuditActionKey.COUNT).icon,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            if (onRemove != null) {
                AikuButton(
                    text = stringResource(R.string.fulfilment_out_remove_count),
                    onClick = onRemove,
                    style = ButtonStyle.Danger,
                    icon = R.drawable.ic_ui_trash,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
    ) {
        Text(text = stringResource(R.string.fulfilment_audit_expected, target.expected), fontSize = 14.sp, color = colors.textSecondary)
        ParcelInputField(
            label = stringResource(R.string.fulfilment_out_counted),
            value = text,
            isWholeNumber = true,
            isError = isMissing || fieldError != null,
            onValueChange = {
                text = it
                isMissing = false
            },
            modifier = Modifier.fillMaxWidth(),
        )
        when {
            fieldError != null -> Text(text = fieldError, fontSize = 12.sp, color = colors.danger)
            isMissing -> Text(text = stringResource(R.string.fulfilment_out_count_missing), fontSize = 12.sp, color = colors.danger)
        }
    }
}

@Composable
private fun AddItemSheet(
    state: StoredItemAuditUiState,
    onSearch: (String) -> Unit,
    onRetry: () -> Unit,
    onChoose: (OutboundStoredItemRef) -> Unit,
    onDismiss: () -> Unit,
) {
    val choices = state.choices

    AikuBottomSheet(
        title = stringResource(R.string.fulfilment_audit_add_item),
        onDismiss = onDismiss,
        pinnedTop = {
            SearchField(
                value = state.choiceSearch,
                onValueChange = onSearch,
                placeholder = stringResource(R.string.fulfilment_out_search_stored_items),
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        when {
            choices.problem != null -> GoodsOutLoadProblem(problem = choices.problem, title = stringResource(R.string.fulfilment_out_choices_failed), onRetry = onRetry)
            choices.isLoading && choices.items.isEmpty() -> SkeletonListCard(rows = 3)
            choices.items.isEmpty() -> HelperNote(text = stringResource(R.string.fulfilment_out_choices_none))
            else -> ListGroup {
                choices.items.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = item.reference,
                        subtitle = dotJoined(item.name, item.barcode),
                        showPhoto = false,
                        onClick = { onChoose(item) },
                    )
                }
            }
        }
    }
}
