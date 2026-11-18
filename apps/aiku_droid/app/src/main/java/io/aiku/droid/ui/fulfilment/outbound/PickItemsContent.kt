package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
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
import io.aiku.droid.data.api.ReturnItemLine
import io.aiku.droid.data.api.ReturnOtherPallet
import io.aiku.droid.data.api.ReturnStoredItem
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.QuantityStepper
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

private sealed interface ItemSheet {
    data class Line(val lineId: Long) : ItemSheet
    data class NotPicked(val lineId: Long) : ItemSheet
    data class OtherPallet(val storedItemId: Long) : ItemSheet
    data object Start : ItemSheet
    data object Finish : ItemSheet
}

@Composable
internal fun PickItemsContent(viewModel: PalletReturnViewModel, state: PalletReturnUiState, palletReturn: PalletReturn) {
    val resources = LocalResources.current
    var sheet by remember { mutableStateOf<ItemSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val openSheet: (ItemSheet) -> Unit = {
        sheetProblem = null
        viewModel.clearWriteErrors()
        sheet = it
    }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
    }
    val groups = palletGroups(palletReturn.storedItems)
    val listensForScans = palletReturn.canScan || palletReturn.hasLineActions
    val scanGroup = groups.firstOrNull { it.pallet?.id == state.selectedPalletId }
        ?: groups.firstOrNull { group -> group.lines.any { (_, line) -> line.can(PalletReturnActionKey.PICK) } }
    val ordered = palletReturn.storedItems.sumOf { it.quantityOrdered }
    val picked = palletReturn.storedItems.sumOf { it.quantityPicked }
    val shortItem = palletReturn.storedItems.firstOrNull { it.canPickFromOtherPallet }

    OutboundEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        doneText = { done -> returnDoneText(done.action, palletReturn, resources) },
        otherText = { event -> returnOtherText(event, resources) },
        onDone = { closeSheet() },
        onLineFound = { lineId -> openSheet(ItemSheet.Line(lineId)) },
    )
    ScanEffect(enabled = listensForScans) { scan -> viewModel.scan(scan.code) }

    AppScreen(
        header = { ReturnHeader(palletReturn = palletReturn) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            val start = palletReturn.action(PalletReturnActionKey.START_PICKING)
            val finish = palletReturn.action(PalletReturnActionKey.FINISH_PICKING)
            val isWorking = state.isWorking && sheet == null
            when {
                start != null -> AikuButton(
                    text = start.label.ifBlank { stringResource(R.string.fulfilment_out_start_picking) },
                    onClick = { openSheet(ItemSheet.Start) },
                    style = fulfilmentOutboundActionLook(start.key).style,
                    icon = fulfilmentOutboundActionLook(start.key).icon,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
                finish != null -> AikuButton(
                    text = finish.label.ifBlank { stringResource(R.string.fulfilment_out_set_as_picked) },
                    onClick = { openSheet(ItemSheet.Finish) },
                    style = fulfilmentOutboundActionLook(finish.key).style,
                    icon = fulfilmentOutboundActionLook(finish.key).icon,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
                shortItem != null -> AikuButton(
                    text = stringResource(R.string.fulfilment_return_pick_from_other, shortItem.storedItem.reference),
                    onClick = { openSheet(ItemSheet.OtherPallet(shortItem.storedItem.id)) },
                    style = ButtonStyle.Soft,
                    icon = R.drawable.ic_ui_pallet,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
                else -> FooterStatus(
                    title = palletReturn.stateLabel,
                    body = pluralStringResource(R.plurals.fulfilment_return_items_progress, ordered, picked, ordered),
                )
            }
        },
    ) {
        ReturnRefreshProblem(problem = state.problem, onRetry = viewModel::retry)
        ReturnUnsureAlert(state = state, onRetry = viewModel::retryUnsure)
        ReturnStateBlock(palletReturn = palletReturn)
        ReturnNotices(palletReturn = palletReturn)
        if (listensForScans && scanGroup != null) {
            FulfilmentScanPrompt(
                title = stringResource(R.string.fulfilment_return_scan_title),
                instruction = stringResource(R.string.fulfilment_return_scan_pallet_items),
                target = dotJoined(scanGroup.pallet?.reference, scanGroup.locationCode),
            )
        }
        groups.forEach { group ->
            SectionHeading(
                title = stringResource(
                    R.string.fulfilment_return_from_pallet,
                    dotJoined(group.pallet?.reference, group.locationCode ?: stringResource(R.string.fulfilment_out_no_location)),
                ),
            )
            ListGroup {
                group.lines.forEachIndexed { index, (item, line) ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemLineRow(item = item, line = line, onOpen = if (line.hasActions || item.canPickFromOtherPallet) ({ openSheet(ItemSheet.Line(line.lineId)) }) else null)
                }
            }
        }
        ReturnDetails(palletReturn = palletReturn)
        HelperNote(text = stringResource(R.string.fulfilment_return_items_footnote))
    }

    when (val current = sheet) {
        is ItemSheet.Line -> palletReturn.itemLine(current.lineId)?.let { (item, line) ->
            ItemLineSheet(
                item = item,
                line = line,
                isWorking = state.isWorking,
                problem = sheetProblem,
                onPick = { quantity -> viewModel.pickItem(line.lineId, quantity) },
                onPickAll = { viewModel.pickAllOfItem(line.lineId) },
                onNotPicked = { openSheet(ItemSheet.NotPicked(line.lineId)) },
                onUndo = { viewModel.undo(line.lineId) },
                onOtherPallet = { openSheet(ItemSheet.OtherPallet(item.storedItem.id)) },
                onDismiss = closeSheet,
            )
        }
        is ItemSheet.NotPicked -> palletReturn.itemLine(current.lineId)?.let { (item, line) ->
            NotPickedItemSheet(
                item = item,
                line = line,
                isWorking = state.isWorking,
                problem = sheetProblem,
                onConfirm = { quantity -> viewModel.notPicked(line.lineId, quantity) },
                onDismiss = closeSheet,
            )
        }
        is ItemSheet.OtherPallet -> palletReturn.storedItem(current.storedItemId)?.let { item ->
            OtherPalletSheet(
                item = item,
                isWorking = state.isWorking,
                problem = sheetProblem,
                onConfirm = { other, quantity -> viewModel.pickFromOtherPallet(other.palletStoredItemId, quantity, item.shortLine?.lineId) },
                onDismiss = closeSheet,
            )
        }
        ItemSheet.Start -> StartPickingDialog(palletReturn = palletReturn, onConfirm = {
            sheet = null
            viewModel.startPicking()
        }, onDismiss = closeSheet)
        ItemSheet.Finish -> FinishPickingDialog(palletReturn = palletReturn, onConfirm = {
            sheet = null
            viewModel.finishPicking()
        }, onDismiss = closeSheet)
        null -> Unit
    }
}

@Composable
private fun ItemLineRow(item: ReturnStoredItem, line: ReturnItemLine, onOpen: (() -> Unit)?) {
    val colors = LocalAikuColors.current
    val toPick = line.quantityToPick.takeIf { it > 0 }?.let { stringResource(R.string.fulfilment_out_to_pick_on_pallet, it, line.quantityOnPallet) }
    val notPicked = line.quantityNotPicked.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.fulfilment_out_not_picked_count, it, it) }
    val extra: (@Composable ColumnScope.() -> Unit)? = if (line.waitingCustomerService) {
        { Text(text = stringResource(R.string.fulfilment_out_waiting_customer_service), fontSize = 12.sp, color = colors.warnInk) }
    } else {
        null
    }

    ItemRow(
        code = item.storedItem.reference,
        subtitle = dotJoined(item.storedItem.name, toPick, notPicked),
        done = line.quantityPicked.toString(),
        required = line.quantityOrdered.toString(),
        quantityTone = itemLineTone(line),
        showPhoto = false,
        extra = extra,
        onClick = onOpen,
    )
}

@Composable
private fun ItemLineSheet(
    item: ReturnStoredItem,
    line: ReturnItemLine,
    isWorking: Boolean,
    problem: String?,
    onPick: (Int) -> Unit,
    onPickAll: () -> Unit,
    onNotPicked: () -> Unit,
    onUndo: () -> Unit,
    onOtherPallet: () -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val canPick = line.can(PalletReturnActionKey.PICK) && line.maxPick > 0
    var amount by remember(line.lineId, line.maxPick) { mutableIntStateOf(1) }

    AikuBottomSheet(
        title = item.storedItem.reference,
        subtitle = item.storedItem.name.takeIf { it.isNotBlank() },
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            if (canPick) {
                AikuButton(
                    text = stringResource(R.string.fulfilment_out_pick_amount, amount),
                    onClick = { onPick(amount) },
                    style = fulfilmentOutboundActionLook(PalletReturnActionKey.PICK).style,
                    icon = fulfilmentOutboundActionLook(PalletReturnActionKey.PICK).icon,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
                if (line.maxPick > 1) {
                    AikuButton(
                        text = pluralStringResource(R.plurals.fulfilment_out_pick_all, line.maxPick, line.maxPick),
                        onClick = onPickAll,
                        style = ButtonStyle.Soft,
                        icon = R.drawable.ic_ui_check_double,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
            if (item.canPickFromOtherPallet) {
                AikuButton(
                    text = stringResource(R.string.fulfilment_out_pick_from_other),
                    onClick = onOtherPallet,
                    style = ButtonStyle.Soft,
                    icon = R.drawable.ic_ui_pallet,
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
                    isLoading = isWorking && !canPick,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
    ) {
        Text(
            text = stringResource(
                R.string.fulfilment_out_line_from,
                dotJoined(line.pallet?.reference, line.location?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.fulfilment_out_no_location)),
            ),
            fontSize = 14.sp,
            color = colors.textSecondary,
        )
        Text(
            text = stringResource(R.string.fulfilment_out_line_quantities, line.quantityPicked, line.quantityOrdered, line.quantityToPick, line.quantityOnPallet),
            fontSize = 14.sp,
            color = colors.textSecondary,
        )
        if (line.quantityNotPicked > 0) {
            Text(text = pluralStringResource(R.plurals.fulfilment_out_not_picked_count, line.quantityNotPicked, line.quantityNotPicked), fontSize = 14.sp, color = colors.warnInk)
        }
        if (line.waitingCustomerService) {
            Text(text = stringResource(R.string.fulfilment_out_waiting_customer_service_body), fontSize = 14.sp, color = colors.warnInk)
        }
        if (canPick) {
            QuantityStepper(
                value = amount,
                onValueChange = { amount = it.coerceIn(1, line.maxPick) },
                min = 1,
                max = line.maxPick,
                showMax = true,
            )
        }
    }
}

@Composable
private fun NotPickedItemSheet(
    item: ReturnStoredItem,
    line: ReturnItemLine,
    isWorking: Boolean,
    problem: String?,
    onConfirm: (Int) -> Unit,
    onDismiss: () -> Unit,
) {
    val most = line.quantityToPick.coerceAtLeast(1)
    var amount by remember(line.lineId, most) { mutableIntStateOf(most) }

    AikuBottomSheet(
        title = stringResource(R.string.fulfilment_out_not_picked_title, item.storedItem.reference),
        subtitle = stringResource(R.string.fulfilment_out_line_from, dotJoined(line.pallet?.reference, line.location?.code)),
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = stringResource(R.string.fulfilment_out_set_not_picked_amount, amount),
                onClick = { onConfirm(amount) },
                style = fulfilmentOutboundActionLook(PalletReturnActionKey.NOT_PICKED).forConfirm().style,
                icon = fulfilmentOutboundActionLook(PalletReturnActionKey.NOT_PICKED).forConfirm().icon,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onDismiss, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
        },
    ) {
        QuantityStepper(
            value = amount,
            onValueChange = { amount = it.coerceIn(1, most) },
            min = 1,
            max = most,
            showMax = true,
            helper = stringResource(R.string.fulfilment_out_not_picked_item_hint),
        )
    }
}

@Composable
private fun OtherPalletSheet(
    item: ReturnStoredItem,
    isWorking: Boolean,
    problem: String?,
    onConfirm: (ReturnOtherPallet, Int) -> Unit,
    onDismiss: () -> Unit,
) {
    val others = item.otherPallets.filter { it.quantityAvailable > 0 }
    val shortLine = item.shortLine
    var chosenId by remember(item.storedItem.id) { mutableStateOf(others.firstOrNull()?.palletStoredItemId) }
    val chosen = others.firstOrNull { it.palletStoredItemId == chosenId }
    val most = chosen?.let { minOf(it.quantityAvailable, item.quantityToPick) }?.coerceAtLeast(1) ?: 1
    val suggested = (shortLine?.shortfall ?: 1).coerceIn(1, most)
    var amount by remember(chosenId, most) { mutableIntStateOf(suggested) }

    AikuBottomSheet(
        title = stringResource(R.string.fulfilment_return_pick_from_other, item.storedItem.reference),
        subtitle = shortLine?.let { line -> pluralStringResource(R.plurals.fulfilment_out_short_on_pallet, line.shortfall, line.shortfall, line.pallet?.reference.orEmpty()) },
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            if (chosen != null) {
                AikuButton(
                    text = pluralStringResource(R.plurals.fulfilment_out_pick_from_pallet, amount, amount, chosen.pallet?.reference.orEmpty()),
                    onClick = { onConfirm(chosen, amount) },
                    icon = fulfilmentOutboundActionLook(PalletReturnActionKey.PICK).icon,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
    ) {
        others.forEach { other ->
            ChoiceRow(
                title = other.pallet?.reference.orEmpty(),
                subtitle = dotJoined(
                    other.location?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.fulfilment_out_no_location),
                    pluralStringResource(R.plurals.fulfilment_out_available, other.quantityAvailable, other.quantityAvailable),
                ),
                selected = other.palletStoredItemId == chosenId,
                onClick = { chosenId = other.palletStoredItemId },
            )
        }
        if (chosen != null) {
            QuantityStepper(
                value = amount,
                onValueChange = { amount = it.coerceIn(1, most) },
                min = 1,
                max = most,
                showMax = true,
                helper = shortLine?.let { stringResource(R.string.fulfilment_out_other_pallet_hint, it.pallet?.reference.orEmpty()) },
            )
        }
    }
}
