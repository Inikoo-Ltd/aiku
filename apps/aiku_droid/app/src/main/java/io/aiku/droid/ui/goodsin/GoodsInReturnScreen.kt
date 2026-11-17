package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalResources
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
import io.aiku.droid.data.api.CustomerReturn
import io.aiku.droid.data.api.CustomerReturnItem
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.NoteReference
import io.aiku.droid.data.api.PutType
import io.aiku.droid.data.api.ReturnPut
import io.aiku.droid.data.api.ReturnState
import io.aiku.droid.data.api.ReturnType
import io.aiku.droid.data.api.ReturnWrite
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StateTimeline
import io.aiku.droid.ui.components.TimelineEntry
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.forConfirm
import io.aiku.droid.ui.goodsout.DisabledActionNotice
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.momentText
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private const val DISPATCHED_STATE = "dispatched"
private const val CANCELLED_STATE = "cancelled"

private val returnSteps = listOf(
    ReturnState.RECEIVED to R.string.goodsin_return_step_received,
    ReturnState.RETURNING to R.string.goodsin_return_step_returning,
    ReturnState.RETURNED to R.string.goodsin_return_step_returned,
    ReturnState.DONE to R.string.goodsin_return_step_done,
)

private sealed interface ReturnSheet {
    data class Item(val itemId: Long, val action: ReturnItemAction) : ReturnSheet
    data class Undo(val put: ReturnPut) : ReturnSheet
    data object SetAsReturned : ReturnSheet
}

@Composable
fun GoodsInReturnScreen(customerReturn: ReturnViewModel, reference: String) {
    val state by customerReturn.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { customerReturn.onStart() }

    RequireAbility(ability = Ability.ViewReturns, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        val loaded = state.customerReturn
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = customerReturn::signInAgain)
            loaded != null -> ReturnContent(viewModel = customerReturn, state = state, customerReturn = loaded)
            else -> ReturnLoading(reference = reference, problem = state.problem, onRetry = customerReturn::retry)
        }
    }
}

@Composable
private fun ReturnLoading(reference: String, problem: GoodsOutProblem?, onRetry: () -> Unit) {
    AppScreen(
        header = { AppHeader(title = reference, kicker = stringResource(R.string.goodsin_kicker_return_plain)) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        if (problem == null) {
            SkeletonListCard(rows = 2)
            SkeletonListCard(rows = 3)
        } else {
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsin_return_failed, reference), onRetry = onRetry)
        }
    }
}

@Composable
private fun ReturnContent(viewModel: ReturnViewModel, state: ReturnUiState, customerReturn: CustomerReturn) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val resources = LocalResources.current
    val zone = organisationZone()
    var sheet by remember { mutableStateOf<ReturnSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val canHandle = can.handleReturns
    val canPutBack = canHandle && customerReturn.action(GoodsInActionKey.PUT_BACK)?.isDisabled == false
    val canUndo = canHandle && customerReturn.action(GoodsInActionKey.UNDO)?.isDisabled == false
    val start = customerReturn.action(GoodsInActionKey.START)?.takeIf { canHandle }
    val setAsReturned = customerReturn.action(GoodsInActionKey.SET_AS_RETURNED)?.takeIf { canHandle }
    val handled = customerReturn.items.count { it.isHandled }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
        viewModel.clearWriteErrors()
    }
    val openSheet: (ReturnSheet) -> Unit = {
        sheetProblem = null
        sheet = it
    }
    val customer = customerReturn.customer?.name?.takeIf { it.isNotBlank() }

    GoodsInEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        doneText = { done -> returnDoneText(done.action, state.customerReturn ?: customerReturn) { id, args -> resources.getString(id, *args) } },
        onDone = { closeSheet() },
    )

    AppScreen(
        header = {
            AppHeader(
                title = customerReturn.reference,
                kicker = customer?.let { stringResource(R.string.goodsin_kicker_return, it) } ?: customerReturn.typeLabel.ifBlank { stringResource(R.string.goodsin_kicker_return_plain) },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = returnFooter(
            start = start,
            setAsReturned = setAsReturned,
            working = state.working,
            onStart = viewModel::start,
            onSetAsReturned = { openSheet(ReturnSheet.SetAsReturned) },
        ),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsin_return_refresh_failed), onRetry = viewModel::retry) }
        if (customerReturn.state == ReturnState.CANCELLED) {
            InlineAlert(title = customerReturn.stateLabel, body = stringResource(R.string.goodsin_return_cancelled), tone = AlertTone.Danger)
        } else {
            ReturnStepBar(state = customerReturn.state)
        }
        ReturnDetails(customerReturn = customerReturn, canOpenNote = can.viewGoodsOut, onOpenNote = { note ->
            val noteState = if (customerReturn.type == ReturnType.CANCELLATION) CANCELLED_STATE else DISPATCHED_STATE
            navigator.open(deliveryNoteRoute(id = note.id, reference = note.reference, state = noteState))
        })
        if (customerReturn.timeline.isNotEmpty()) {
            StateTimeline(entries = customerReturn.timeline.map { TimelineEntry(label = it.label, date = momentText(it.at, zone)) })
        }
        SectionHeading(
            title = stringResource(R.string.goodsin_items),
            caption = pluralStringResource(R.plurals.goodsin_return_handled_count, customerReturn.items.size, handled, customerReturn.items.size),
        )
        customerReturn.items.forEach { item ->
            ReturnItemCard(
                item = item,
                canPutBack = canPutBack && item.hasRemaining,
                canUndo = canUndo,
                onAction = { action -> openSheet(ReturnSheet.Item(item.id, action)) },
                onUndo = { put -> openSheet(ReturnSheet.Undo(put)) },
            )
        }
        HelperNote(text = stringResource(if (customerReturn.state == ReturnState.RETURNING) R.string.goodsin_return_actions_note else R.string.goodsin_returns_note))
    }

    when (val current = sheet) {
        is ReturnSheet.Item -> customerReturn.item(current.itemId)?.let { item ->
            ReturnItemSheet(
                item = item,
                action = current.action,
                isWorking = state.isWorking,
                problem = sheetProblem,
                onConfirm = { quantity, locationOrgStockId ->
                    when (current.action) {
                        ReturnItemAction.BackToStock -> locationOrgStockId?.let { viewModel.backToStock(item.id, quantity, it) }
                        ReturnItemAction.Damaged -> viewModel.damaged(item.id, quantity)
                        ReturnItemAction.NotReturned -> viewModel.notReturned(item.id, quantity)
                    }
                },
                onDismiss = closeSheet,
            )
        }
        is ReturnSheet.Undo -> ConfirmDialog(
            title = stringResource(R.string.goodsin_undo_put_title),
            body = if (current.put.type == PutType.SOW) {
                stringResource(R.string.goodsin_undo_put_body_stock, quantityText(current.put.quantity, current.put.quantityLabel), current.put.location?.code.orEmpty())
            } else {
                stringResource(R.string.goodsin_undo_put_body)
            },
            confirmLabel = stringResource(R.string.goodsin_undo),
            confirmLook = goodsInActionLook(GoodsInActionKey.UNDO).forConfirm(),
            onConfirm = {
                sheet = null
                viewModel.undo(current.put.id)
            },
            onDismiss = closeSheet,
        )
        ReturnSheet.SetAsReturned -> ConfirmDialog(
            title = stringResource(R.string.goodsin_set_returned_title, customerReturn.reference),
            body = stringResource(R.string.goodsin_set_returned_body),
            confirmLabel = setAsReturned?.label?.ifBlank { null } ?: stringResource(R.string.goodsin_set_returned),
            confirmLook = goodsInActionLook(GoodsInActionKey.SET_AS_RETURNED).forConfirm(),
            onConfirm = {
                sheet = null
                viewModel.setAsReturned()
            },
            onDismiss = closeSheet,
        )
        null -> Unit
    }
}

private fun returnDoneText(action: Any, customerReturn: CustomerReturn, text: (Int, Array<out Any>) -> String): String? = when (action) {
    ReturnWrite.Start -> text(R.string.goodsin_toast_return_started, arrayOf(customerReturn.reference))
    ReturnWrite.SetAsReturned -> text(R.string.goodsin_toast_returned, arrayOf(customerReturn.reference))
    is ReturnWrite.Returned -> text(R.string.goodsin_toast_back_to_stock, arrayOf(customerReturn.item(action.itemId)?.code.orEmpty()))
    is ReturnWrite.Damaged -> text(R.string.goodsin_toast_damaged, arrayOf(customerReturn.item(action.itemId)?.code.orEmpty()))
    is ReturnWrite.NotReturned -> text(R.string.goodsin_toast_not_returned, arrayOf(customerReturn.item(action.itemId)?.code.orEmpty()))
    is ReturnWrite.UndoPut -> text(R.string.goodsin_toast_put_undone, emptyArray())
    else -> null
}

private fun returnFooter(
    start: DeliveryNoteAction?,
    setAsReturned: DeliveryNoteAction?,
    working: ReturnWrite?,
    onStart: () -> Unit,
    onSetAsReturned: () -> Unit,
): (@Composable ColumnScope.() -> Unit)? {
    val action = start ?: setAsReturned ?: return null
    return {
        if (action.isDisabled) {
            DisabledActionNotice(action = action)
        } else if (action == start) {
            AikuButton(
                text = action.label.ifBlank { stringResource(R.string.goodsin_start_return) },
                onClick = onStart,
                style = goodsInActionLook(action.key).style,
                icon = goodsInActionLook(action.key).icon,
                isLoading = working == ReturnWrite.Start,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        } else {
            AikuButton(
                text = action.label.ifBlank { stringResource(R.string.goodsin_set_returned) },
                onClick = onSetAsReturned,
                style = goodsInActionLook(action.key).style,
                icon = goodsInActionLook(action.key).icon,
                isLoading = working == ReturnWrite.SetAsReturned,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun ReturnStepBar(state: String) {
    val colors = LocalAikuColors.current
    val currentStep = returnSteps.indexOfFirst { it.first == state }
    val isComplete = state == ReturnState.DONE

    Row(horizontalArrangement = Arrangement.spacedBy(3.dp), modifier = Modifier.fillMaxWidth()) {
        returnSteps.forEachIndexed { index, (_, label) ->
            val (container, content) = when {
                index < currentStep || (isComplete && index == currentStep) -> colors.success to colors.onPrimary
                index == currentStep -> colors.primary to colors.onPrimary
                else -> colors.background to colors.textTertiary
            }
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .weight(1f)
                    .height(26.dp)
                    .clip(RoundedCornerShape(6.dp))
                    .background(container),
            ) {
                Text(text = stringResource(label), fontSize = 10.sp, fontWeight = FontWeight.Bold, color = content, maxLines = 1)
            }
        }
    }
}

@Composable
private fun ReturnDetails(customerReturn: CustomerReturn, canOpenNote: Boolean, onOpenNote: (NoteReference) -> Unit) {
    val lines = listOfNotNull(
        customerReturn.customer?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        customerReturn.order?.reference?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_order, text = stringResource(R.string.goodsin_return_order, it)) },
        customerReturn.typeLabel.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_document, text = stringResource(R.string.goodsin_return_type, it)) },
        customerReturn.handler?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = stringResource(R.string.goodsin_return_handler, it)) },
    )
    val note = customerReturn.deliveryNote
    if (lines.isNotEmpty()) {
        DetailCard(title = stringResource(R.string.goodsin_return_details), lines = lines)
    }
    if (note != null && note.reference.isNotBlank()) {
        ListGroup {
            ActionLinkRow(
                icon = R.drawable.ic_ui_delivery_note,
                title = note.reference,
                subtitle = stringResource(R.string.goodsin_return_original_note),
                tone = ActionTone.Primary,
                onClick = if (canOpenNote) ({ onOpenNote(note) }) else null,
            )
        }
    }
}

@Composable
private fun ReturnItemCard(
    item: CustomerReturnItem,
    canPutBack: Boolean,
    canUndo: Boolean,
    onAction: (ReturnItemAction) -> Unit,
    onUndo: (ReturnPut) -> Unit,
) {
    val colors = LocalAikuColors.current
    val breakdown = listOf(
        stringResource(R.string.goodsin_return_breakdown_returned, quantityText(item.returned, item.returnedLabel)),
        stringResource(R.string.goodsin_return_breakdown_damaged, quantityText(item.damaged, item.damagedLabel)),
        stringResource(R.string.goodsin_return_breakdown_not_returned, quantityText(item.notReturned, item.notReturnedLabel)),
        stringResource(R.string.goodsin_return_breakdown_remaining, quantityText(item.remaining, item.remainingLabel)),
    ).joinToString(LIST_SEPARATOR)
    val processed = item.returned + item.damaged + item.notReturned

    ListGroup {
        ItemRow(
            code = item.code.ifBlank { stringResource(R.string.goodsout_line_unknown_sko) },
            subtitle = item.sko?.name.orEmpty(),
            chip = if (item.isHandled) stringResource(R.string.goodsin_return_item_handled) else null,
            chipTone = Tone.Success,
            done = quantityText(processed),
            required = quantityText(item.expected, item.expectedLabel),
            quantityTone = if (item.isHandled) Tone.Success else null,
            imageUrl = item.sko?.imageUrl,
        )
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().padding(start = 14.dp, end = 14.dp, bottom = 12.dp)) {
            Text(text = breakdown, fontSize = 13.sp, color = colors.textTertiary)
            item.puts.forEach { put -> PutRow(put = put, onUndo = if (canUndo) ({ onUndo(put) }) else null) }
            if (canPutBack) {
                AikuButton(
                    text = stringResource(R.string.goodsin_back_to_stock),
                    onClick = { onAction(ReturnItemAction.BackToStock) },
                    style = goodsInActionLook(GoodsInActionKey.PUT_BACK).style,
                    icon = goodsInActionLook(GoodsInActionKey.PUT_BACK).icon,
                    height = 48.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    AikuButton(
                        text = stringResource(R.string.goodsin_damaged),
                        onClick = { onAction(ReturnItemAction.Damaged) },
                        style = ButtonStyle.Danger,
                        icon = R.drawable.ic_ui_warning,
                        height = 48.dp,
                        modifier = Modifier.weight(1f),
                    )
                    AikuButton(
                        text = stringResource(R.string.goodsin_not_returned),
                        onClick = { onAction(ReturnItemAction.NotReturned) },
                        style = ButtonStyle.Danger,
                        icon = R.drawable.ic_ui_x_circle,
                        height = 48.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
    }
}

@Composable
private fun PutRow(put: ReturnPut, onUndo: (() -> Unit)?) {
    val colors = LocalAikuColors.current
    val text = listOfNotNull(
        quantityText(put.quantity, put.quantityLabel),
        put.typeLabel.takeIf { it.isNotBlank() } ?: put.type,
        put.location?.code?.takeIf { it.isNotBlank() },
        put.by?.name?.takeIf { it.isNotBlank() },
    ).joinToString(LIST_SEPARATOR)

    RowDivider()
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().heightIn(min = 44.dp)) {
        Text(text = text, fontSize = 13.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
        if (onUndo != null) {
            Text(
                text = stringResource(R.string.goodsin_undo),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.cautionInk,
                modifier = Modifier
                    .heightIn(min = 44.dp)
                    .clip(ControlShape)
                    .clickable(role = Role.Button, onClick = onUndo)
                    .padding(horizontal = 10.dp, vertical = 12.dp),
            )
        }
    }
}
