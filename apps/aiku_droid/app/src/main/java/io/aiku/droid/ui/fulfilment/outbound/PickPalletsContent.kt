package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
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
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.ReturnPalletLine
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.forConfirm
import io.aiku.droid.ui.fulfilment.FulfilmentScanPrompt
import io.aiku.droid.ui.fulfilment.dotJoined
import io.aiku.droid.ui.goodsin.HelperNote
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.theme.LocalAikuColors

private sealed interface PalletSheet {
    data class Line(val lineId: Long) : PalletSheet
    data class NotPicked(val lineId: Long) : PalletSheet
    data object Start : PalletSheet
    data object Finish : PalletSheet
}

@Composable
internal fun PickPalletsContent(viewModel: PalletReturnViewModel, state: PalletReturnUiState, palletReturn: PalletReturn) {
    val resources = LocalResources.current
    var sheet by remember { mutableStateOf<PalletSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val openSheet: (PalletSheet) -> Unit = {
        sheetProblem = null
        sheet = it
    }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
    }
    val selected = palletReturn.pallets.firstOrNull { it.pallet.id == state.selectedPalletId && it.hasActions }
    val nextLine = selected ?: palletReturn.pallets.firstOrNull { it.can(PalletReturnActionKey.PICK) }
    val listensForScans = palletReturn.canScan || palletReturn.hasLineActions

    OutboundEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        doneText = { done -> returnDoneText(done.action, palletReturn, resources) },
        otherText = { event -> returnOtherText(event, resources) },
        onDone = { closeSheet() },
        onLineFound = { lineId -> openSheet(PalletSheet.Line(lineId)) },
    )
    ScanEffect(enabled = listensForScans) { scan -> viewModel.scan(scan.code) }

    AppScreen(
        header = { ReturnHeader(palletReturn = palletReturn) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = palletsFooter(
            palletReturn = palletReturn,
            nextLine = nextLine,
            isWorking = state.isWorking && sheet == null,
            onStart = { openSheet(PalletSheet.Start) },
            onFinish = { openSheet(PalletSheet.Finish) },
            onPick = { line -> viewModel.pickPallet(line.lineId) },
            onNotPicked = { line -> openSheet(PalletSheet.NotPicked(line.lineId)) },
        ),
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh, isEnabled = state.canRefresh),
    ) {
        ReturnRefreshProblem(problem = state.problem, onRetry = viewModel::retry)
        ReturnUnsureAlert(state = state, onRetry = viewModel::retryUnsure)
        ReturnStateBlock(palletReturn = palletReturn)
        ReturnNotices(palletReturn = palletReturn)
        if (listensForScans && nextLine != null) {
            FulfilmentScanPrompt(
                title = stringResource(R.string.fulfilment_return_scan_title),
                instruction = stringResource(R.string.fulfilment_return_scan_pallet),
                target = nextLine.pallet.reference,
                detail = nextLine.location?.code?.takeIf { it.isNotBlank() },
            )
        }
        if (palletReturn.pallets.isNotEmpty()) {
            SectionHeading(
                title = stringResource(R.string.fulfilment_pallets),
                caption = pluralStringResource(R.plurals.fulfilment_return_pallets_progress, palletReturn.pallets.size, picked(palletReturn), palletReturn.pallets.size),
            )
            ListGroup {
                palletReturn.pallets.forEachIndexed { index, line ->
                    if (index > 0) {
                        RowDivider()
                    }
                    PalletLineRow(line = line, onOpen = if (line.hasActions) ({ openSheet(PalletSheet.Line(line.lineId)) }) else null)
                }
            }
        }
        ReturnDetails(palletReturn = palletReturn)
        HelperNote(text = stringResource(R.string.fulfilment_out_pick_pallets_note))
    }

    when (val current = sheet) {
        is PalletSheet.Line -> palletReturn.palletLine(current.lineId)?.let { line ->
            PalletLineSheet(
                line = line,
                isWorking = state.isWorking,
                problem = sheetProblem,
                onPick = { viewModel.pickPallet(line.lineId) },
                onNotPicked = { openSheet(PalletSheet.NotPicked(line.lineId)) },
                onUndo = { viewModel.undo(line.lineId) },
                onDismiss = closeSheet,
            )
        }
        is PalletSheet.NotPicked -> palletReturn.palletLine(current.lineId)?.let { line ->
            ConfirmDialog(
                title = stringResource(R.string.fulfilment_out_not_picked_title, line.pallet.reference),
                body = stringResource(R.string.fulfilment_out_not_picked_pallet_body),
                confirmLabel = line.availableActions.firstOrNull { it.key == PalletReturnActionKey.NOT_PICKED }?.label?.ifBlank { null }
                    ?: stringResource(R.string.fulfilment_return_not_picked),
                onConfirm = {
                    sheet = null
                    viewModel.notPicked(line.lineId)
                },
                onDismiss = closeSheet,
                confirmLook = fulfilmentOutboundActionLook(PalletReturnActionKey.NOT_PICKED).forConfirm(),
            )
        }
        PalletSheet.Start -> StartPickingDialog(palletReturn = palletReturn, onConfirm = {
            sheet = null
            viewModel.startPicking()
        }, onDismiss = closeSheet)
        PalletSheet.Finish -> FinishPickingDialog(palletReturn = palletReturn, onConfirm = {
            sheet = null
            viewModel.finishPicking()
        }, onDismiss = closeSheet)
        null -> Unit
    }
}

private fun picked(palletReturn: PalletReturn): Int = palletReturn.counts.picked.takeIf { palletReturn.counts.lines > 0 }
    ?: palletReturn.pallets.count { it.lineState == PICKED_LINE }

@Composable
private fun PalletLineRow(line: ReturnPalletLine, onOpen: (() -> Unit)?) {
    val colors = LocalAikuColors.current
    val contents = line.storedItems.map { stringResource(R.string.fulfilment_out_item_times, it.reference, it.quantity) }.joinToString(", ")
    val extra: (@Composable ColumnScope.() -> Unit)? = if (contents.isEmpty()) {
        null
    } else {
        { Text(text = contents, fontSize = 12.sp, color = colors.textTertiary, maxLines = 2) }
    }

    ItemRow(
        code = line.pallet.reference,
        subtitle = dotJoined(
            line.pallet.typeLabel,
            line.location?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.fulfilment_out_no_location),
            line.pallet.customerReference,
        ),
        chip = if (line.waitingCustomerService) stringResource(R.string.fulfilment_out_waiting_customer_service) else line.lineStateLabel.takeIf { it.isNotBlank() },
        chipTone = lineStateTone(line.lineState, line.waitingCustomerService),
        showPhoto = false,
        extra = extra,
        onClick = onOpen,
    )
}

@Composable
private fun PalletLineSheet(
    line: ReturnPalletLine,
    isWorking: Boolean,
    problem: String?,
    onPick: () -> Unit,
    onNotPicked: () -> Unit,
    onUndo: () -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current

    AikuBottomSheet(
        title = line.pallet.reference,
        subtitle = dotJoined(line.pallet.typeLabel, line.location?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.fulfilment_out_no_location)),
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            if (line.can(PalletReturnActionKey.PICK)) {
                AikuButton(
                    text = actionLabel(line.availableActions, PalletReturnActionKey.PICK) ?: stringResource(R.string.fulfilment_return_pick_pallet, line.pallet.reference),
                    onClick = onPick,
                    style = fulfilmentOutboundActionLook(PalletReturnActionKey.PICK).style,
                    icon = fulfilmentOutboundActionLook(PalletReturnActionKey.PICK).icon,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            if (line.can(PalletReturnActionKey.NOT_PICKED)) {
                AikuButton(
                    text = actionLabel(line.availableActions, PalletReturnActionKey.NOT_PICKED) ?: stringResource(R.string.fulfilment_return_not_picked),
                    onClick = onNotPicked,
                    style = fulfilmentOutboundActionLook(PalletReturnActionKey.NOT_PICKED).style,
                    icon = fulfilmentOutboundActionLook(PalletReturnActionKey.NOT_PICKED).icon,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            if (line.can(PalletReturnActionKey.UNDO)) {
                AikuButton(
                    text = actionLabel(line.availableActions, PalletReturnActionKey.UNDO) ?: stringResource(R.string.fulfilment_out_undo),
                    onClick = onUndo,
                    style = fulfilmentOutboundActionLook(PalletReturnActionKey.UNDO).style,
                    icon = fulfilmentOutboundActionLook(PalletReturnActionKey.UNDO).icon,
                    isLoading = isWorking && !line.can(PalletReturnActionKey.PICK),
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
    ) {
        Text(
            text = if (line.waitingCustomerService) stringResource(R.string.fulfilment_out_waiting_customer_service_body) else line.lineStateLabel,
            fontSize = 14.sp,
            color = colors.textSecondary,
        )
        if (line.storedItems.isNotEmpty()) {
            ListGroup {
                line.storedItems.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(code = item.reference, subtitle = item.name, done = item.quantity.toString(), showPhoto = false)
                }
            }
        }
    }
}

private fun palletsFooter(
    palletReturn: PalletReturn,
    nextLine: ReturnPalletLine?,
    isWorking: Boolean,
    onStart: () -> Unit,
    onFinish: () -> Unit,
    onPick: (ReturnPalletLine) -> Unit,
    onNotPicked: (ReturnPalletLine) -> Unit,
): @Composable ColumnScope.() -> Unit = {
    val start = palletReturn.action(PalletReturnActionKey.START_PICKING)
    val finish = palletReturn.action(PalletReturnActionKey.FINISH_PICKING)
    when {
        start != null -> AikuButton(
            text = start.label.ifBlank { stringResource(R.string.fulfilment_out_start_picking) },
            onClick = onStart,
            style = fulfilmentOutboundActionLook(start.key).style,
            icon = fulfilmentOutboundActionLook(start.key).icon,
            isLoading = isWorking,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        finish != null -> AikuButton(
            text = finish.label.ifBlank { stringResource(R.string.fulfilment_out_set_as_picked) },
            onClick = onFinish,
            style = fulfilmentOutboundActionLook(finish.key).style,
            icon = fulfilmentOutboundActionLook(finish.key).icon,
            isLoading = isWorking,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        nextLine != null && (nextLine.can(PalletReturnActionKey.PICK) || nextLine.can(PalletReturnActionKey.NOT_PICKED)) ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                if (nextLine.can(PalletReturnActionKey.NOT_PICKED)) {
                    AikuButton(
                        text = stringResource(R.string.fulfilment_return_not_picked),
                        onClick = { onNotPicked(nextLine) },
                        style = fulfilmentOutboundActionLook(PalletReturnActionKey.NOT_PICKED).style,
                        icon = fulfilmentOutboundActionLook(PalletReturnActionKey.NOT_PICKED).icon,
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
                if (nextLine.can(PalletReturnActionKey.PICK)) {
                    AikuButton(
                        text = stringResource(R.string.fulfilment_return_pick_pallet, nextLine.pallet.reference),
                        onClick = { onPick(nextLine) },
                        style = fulfilmentOutboundActionLook(PalletReturnActionKey.PICK).style,
                        icon = fulfilmentOutboundActionLook(PalletReturnActionKey.PICK).icon,
                        isLoading = isWorking,
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        else -> FooterStatus(
            title = palletReturn.stateLabel,
            body = pluralStringResource(R.plurals.fulfilment_return_pallets_progress, palletReturn.pallets.size, picked(palletReturn), palletReturn.pallets.size),
        )
    }
}
