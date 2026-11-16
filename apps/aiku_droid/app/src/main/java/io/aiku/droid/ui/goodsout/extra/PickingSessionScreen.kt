package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.ChatContextKind
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.PickListEntry
import io.aiku.droid.data.api.PickingSession
import io.aiku.droid.data.api.PickingSessionWrite
import io.aiku.droid.data.api.SessionActionKey
import io.aiku.droid.data.api.SessionDeliveryNote
import io.aiku.droid.data.api.TrolleyRef
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.ActionConfirmSheet
import io.aiku.droid.ui.goodsout.DisabledActionNotice
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.IconInfoRow
import io.aiku.droid.ui.goodsout.ScanHintCard
import io.aiku.droid.ui.goodsout.TrolleyChoiceSheet
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.goodsout.stageOf
import io.aiku.droid.ui.goodsout.stageTone
import io.aiku.droid.ui.navigation.AskCrmRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PackByScanRoute
import io.aiku.droid.ui.navigation.PickByLocationRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private val footerKeys = listOf(
    SessionActionKey.SCAN_PICK,
    SessionActionKey.SCAN_PACK,
    SessionActionKey.START_PICKING,
    SessionActionKey.BACK_TO_PICKING,
    SessionActionKey.ADD_DELIVERY_NOTES,
    SessionActionKey.REMOVE_DELIVERY_NOTES,
)

private sealed interface SessionSheet {
    data object AttachTrolley : SessionSheet
    data class DetachTrolley(val trolley: TrolleyRef) : SessionSheet
    data class BackToPicking(val action: DeliveryNoteAction) : SessionSheet
}

@Composable
fun PickingSessionScreen(session: PickingSessionViewModel, reference: String) {
    val state by session.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { session.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.session
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = session::signInAgain)
            loaded != null -> PickingSessionContent(viewModel = session, state = state, session = loaded)
            else -> SessionLoading(title = reference, kicker = stringResource(R.string.goodsout_extra_picking_session), problem = state.problem, onRetry = session::retry)
        }
    }
}

@Composable
internal fun SessionLoading(title: String, kicker: String, problem: GoodsOutProblem?, onRetry: () -> Unit) {
    AppScreen(
        header = { AppHeader(title = title, kicker = kicker) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        if (problem == null) {
            SkeletonListCard(rows = 2)
            SkeletonListCard(rows = 4)
        } else {
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_extra_session_failed), onRetry = onRetry)
        }
    }
}

@Composable
internal fun SessionEvents(
    event: SessionEvent?,
    onSheetProblem: ((String) -> Unit)?,
    onActed: () -> Unit,
    onHandled: () -> Unit,
    onFinished: () -> Unit = {},
) {
    val toasts = LocalToasts.current
    val resources = LocalResources.current
    val problemTexts = GoodsOutProblem.entries.associateWith { problemText(it) }

    LaunchedEffect(event) {
        when (event) {
            is SessionEvent.Acted -> {
                onActed()
                event.message?.let { toasts.show(it, if (event.isNothingChanged) ToastKind.Info else ToastKind.Success) }
            }
            is SessionEvent.Refused -> {
                val text = event.message.ifBlank { problemTexts.getValue(event.problem) }
                if (onSheetProblem != null) onSheetProblem(text) else toasts.show(text, ToastKind.Error)
            }
            is SessionEvent.ScanFeedback -> event.message?.let { toasts.show(it, ToastKind.Error) }
            is SessionEvent.LineDone -> toasts.show(resources.getString(R.string.goodsout_scan_line_done_toast, event.code), ToastKind.Success)
            is SessionEvent.LocationDone -> toasts.show(resources.getString(R.string.goodsout_extra_location_done, event.location), ToastKind.Success)
            SessionEvent.AllPicked -> {
                toasts.show(resources.getString(R.string.goodsout_extra_session_all_picked), ToastKind.Success)
                onFinished()
            }
            SessionEvent.NowWaiting -> {
                toasts.show(resources.getString(R.string.goodsout_extra_session_now_waiting), ToastKind.Warning)
                onFinished()
            }
            SessionEvent.AllPacked -> {
                toasts.show(resources.getString(R.string.goodsout_extra_all_packed), ToastKind.Success)
                onFinished()
            }
            null -> return@LaunchedEffect
        }
        onHandled()
    }
}

@Composable
internal fun sessionKicker(session: PickingSession): String =
    listOf(stringResource(R.string.goodsout_extra_picking_session), sessionStateLabel(session.state, session.stateLabel)).joinToString(KICKER_SEPARATOR)

@Composable
private fun PickingSessionContent(viewModel: PickingSessionViewModel, state: PickingSessionUiState, session: PickingSession) {
    val navigator = LocalAppNavigator.current
    var sheet by remember { mutableStateOf<SessionSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    var removing by remember { mutableStateOf<Set<Long>?>(null) }
    val openSheet: (SessionSheet) -> Unit = {
        sheetProblem = null
        sheet = it
    }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
    }
    val runAction: (DeliveryNoteAction) -> Unit = { action ->
        when (action.key) {
            SessionActionKey.START_PICKING -> viewModel.act(PickingSessionWrite.StartPicking)
            SessionActionKey.BACK_TO_PICKING -> openSheet(SessionSheet.BackToPicking(action))
            SessionActionKey.ADD_DELIVERY_NOTES -> navigator.open(DeliveryNotesRoute(sessionId = session.id, sessionReference = session.reference))
            SessionActionKey.REMOVE_DELIVERY_NOTES -> removing = emptySet()
            SessionActionKey.SCAN_PICK -> {
                viewModel.handOver()
                navigator.open(PickByLocationRoute(id = session.id, reference = session.reference))
            }
            SessionActionKey.SCAN_PACK -> {
                viewModel.handOver()
                navigator.open(PackByScanRoute(id = session.id, reference = session.reference))
            }
        }
    }
    val chosen = removing

    SessionEvents(
        event = state.event,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        onActed = {
            closeSheet()
            removing = null
        },
        onHandled = viewModel::eventHandled,
    )

    AppScreen(
        header = {
            AppHeader(
                title = session.reference,
                kicker = sessionKicker(session),
                actions = {
                    HeaderIconButton(
                        icon = R.drawable.ic_ui_chat,
                        description = stringResource(R.string.goodsout_extra_ask_crm),
                        onClick = { navigator.open(AskCrmRoute(context = ChatContextKind.PickingSession.key, id = session.id, reference = session.reference)) },
                        filled = true,
                    )
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (chosen != null) {
                RemoveNotesFooter(
                    count = chosen.size,
                    isWorking = state.working is PickingSessionWrite.RemoveDeliveryNotes,
                    onRemove = { viewModel.act(PickingSessionWrite.RemoveDeliveryNotes(chosen.toList())) },
                    onCancel = { removing = null },
                )
            } else {
                SessionFooter(session = session, loadingKey = if (state.working == PickingSessionWrite.StartPicking) SessionActionKey.START_PICKING else null, onAction = runAction)
            }
        },
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsout_extra_session_refresh_failed), onRetry = viewModel::retry) }
        SessionSummaryCard(session = session)
        SessionTrolleys(
            session = session,
            onAttach = { openSheet(SessionSheet.AttachTrolley) },
            onDetach = { trolley -> openSheet(SessionSheet.DetachTrolley(trolley)) },
        )
        session.nextOpenPick(after = null)?.let { next ->
            ScanHintCard(
                title = stringResource(R.string.goodsout_extra_pick_by_location),
                caption = stringResource(R.string.goodsout_extra_take_from_location),
                value = next.location?.code?.takeIf { it.isNotBlank() } ?: next.orgStock?.code.orEmpty(),
                detail = pickDetail(next),
                onClick = {
                    viewModel.handOver()
                    navigator.open(PickByLocationRoute(id = session.id, reference = session.reference))
                },
            )
        }
        SessionNotes(
            notes = session.deliveryNotes,
            isPacking = session.sessionState.isPacking,
            removing = chosen,
            onOpen = { note -> navigator.open(deliveryNoteRoute(id = note.id, reference = note.reference, state = note.state)) },
            onToggle = { note -> removing = chosen?.let { if (note.id in it) it - note.id else it + note.id } },
        )
        HintText(text = stringResource(R.string.goodsout_extra_session_hint))
    }
    when (val shown = sheet) {
        SessionSheet.AttachTrolley -> TrolleyChoiceSheet(
            title = stringResource(R.string.goodsout_extra_session_trolley_title),
            subtitle = stringResource(R.string.goodsout_extra_session_kicker, session.reference),
            trolleys = state.trolleys,
            isWorking = state.isWorking,
            problem = sheetProblem,
            onLoad = viewModel::loadTrolleys,
            confirmLabel = { trolley -> stringResource(R.string.goodsout_trolley_use, trolley.name) },
            onConfirm = { trolleyId -> trolleyId?.let { viewModel.act(PickingSessionWrite.AttachTrolley(it)) } },
            noTrolleyLabel = null,
            onDismiss = closeSheet,
        )
        is SessionSheet.DetachTrolley -> ActionConfirmSheet(
            title = stringResource(R.string.goodsout_extra_session_detach_title, shown.trolley.name),
            subtitle = stringResource(R.string.goodsout_extra_session_kicker, session.reference),
            confirmLabel = stringResource(R.string.goodsout_trolley_remove),
            confirmStyle = ButtonStyle.Danger,
            hint = stringResource(R.string.goodsout_extra_session_detach_hint),
            isWorking = state.isWorking,
            problem = sheetProblem,
            onConfirm = { viewModel.act(PickingSessionWrite.DetachTrolley(shown.trolley.id)) },
            onDismiss = closeSheet,
        )
        is SessionSheet.BackToPicking -> ActionConfirmSheet(
            title = stringResource(R.string.goodsout_extra_session_back_to_picking_title, session.reference),
            subtitle = pluralStringResource(
                R.plurals.goodsout_extra_delivery_notes_waiting,
                session.deliveryNotes.count { it.hasWaitingItems },
                session.deliveryNotes.count { it.hasWaitingItems },
            ),
            confirmLabel = shown.action.label,
            icon = R.drawable.ic_ui_undo,
            hint = stringResource(R.string.goodsout_extra_session_back_to_picking_hint),
            isWorking = state.isWorking,
            problem = sheetProblem,
            onConfirm = { viewModel.act(PickingSessionWrite.BackToPicking) },
            onDismiss = closeSheet,
        )
        null -> Unit
    }
}

@Composable
internal fun pickDetail(entry: PickListEntry): String {
    val notes = entry.lines.map { it.deliveryNoteId }.distinct().size
    return stringResource(
        R.string.goodsout_extra_location_pick_detail,
        entry.orgStock?.code.orEmpty(),
        quantityText(entry.quantityToPick, entry.quantityToPickLabel),
        pluralStringResource(R.plurals.goodsout_extra_delivery_notes, notes, notes),
    )
}

@Composable
private fun ColumnScope.SessionFooter(session: PickingSession, loadingKey: String?, onAction: (DeliveryNoteAction) -> Unit) {
    val actions = footerKeys.mapNotNull(session::action)
    val primary = actions.firstOrNull { !it.isDisabled }
    if (actions.isEmpty()) {
        FooterStatus(
            title = sessionStateLabel(session.state, session.stateLabel),
            body = stringResource(if (session.canWork) R.string.goodsout_nothing_to_do else R.string.goodsout_extra_session_view_only),
        )
        return
    }
    actions.forEach { action ->
        if (action.isDisabled) {
            DisabledActionNotice(action = action)
        } else {
            AikuButton(
                text = action.label,
                onClick = { onAction(action) },
                style = if (action == primary) ButtonStyle.Primary else ButtonStyle.Outline,
                icon = action.sessionIcon(),
                isLoading = action.key == loadingKey,
                height = if (action == primary) 52.dp else 48.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@DrawableRes
private fun DeliveryNoteAction.sessionIcon(): Int? = when (key) {
    SessionActionKey.SCAN_PICK, SessionActionKey.SCAN_PACK -> R.drawable.ic_ui_scan
    SessionActionKey.BACK_TO_PICKING -> R.drawable.ic_ui_undo
    else -> null
}

@Composable
private fun RemoveNotesFooter(count: Int, isWorking: Boolean, onRemove: () -> Unit, onCancel: () -> Unit) {
    if (count == 0) {
        FooterStatus(title = stringResource(R.string.goodsout_extra_session_remove_title), body = stringResource(R.string.goodsout_extra_session_remove_body))
    } else {
        AikuButton(
            text = pluralStringResource(R.plurals.goodsout_extra_session_remove, count, count),
            onClick = onRemove,
            style = ButtonStyle.Danger,
            isLoading = isWorking,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
    AikuButton(
        text = stringResource(R.string.goodsout_selection_cancel),
        onClick = onCancel,
        style = ButtonStyle.Outline,
        height = 48.dp,
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun SessionSummaryCard(session: PickingSession) {
    val colors = LocalAikuColors.current
    val isPacking = session.sessionState.isPacking

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            ExtraIconTile(icon = R.drawable.ic_ui_person, tone = Tone.Primary)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = stringResource(R.string.goodsout_extra_picker), fontSize = 12.sp, color = colors.textTertiary)
                Text(
                    text = sessionPersonName(session.picker) ?: stringResource(R.string.goodsout_no_picker),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.text,
                )
            }
            StatusBadge(text = sessionStateLabel(session.state, session.stateLabel), tone = sessionTone(session.sessionState))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            StatColumn(value = session.numberDeliveryNotes.toString(), label = stringResource(R.string.goodsout_extra_stat_delivery_notes), modifier = Modifier.weight(1f))
            StatColumn(value = session.numberItems.toString(), label = stringResource(R.string.goodsout_extra_stat_items), modifier = Modifier.weight(1f))
            StatColumn(value = session.numberTrolleys.toString(), label = stringResource(R.string.goodsout_extra_stat_trolleys), modifier = Modifier.weight(1f))
            StatColumn(
                value = stringResource(R.string.goodsout_extra_percent, percent(if (isPacking) session.packingPercentage else session.pickingPercentage)),
                label = stringResource(if (isPacking) R.string.goodsout_extra_stat_packed else R.string.goodsout_extra_stat_picked),
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun SessionTrolleys(session: PickingSession, onAttach: () -> Unit, onDetach: (TrolleyRef) -> Unit) {
    val canChange = session.canChangeTrolleys
    if (session.trolleys.isEmpty() && !canChange) {
        return
    }
    val removeLabel = stringResource(R.string.goodsout_extra_remove)

    SectionHeading(text = stringResource(R.string.section_trolleys))
    ListGroup {
        session.trolleys.forEachIndexed { index, trolley ->
            if (index > 0) {
                RowDivider()
            }
            IconInfoRow(
                icon = R.drawable.ic_ui_trolley,
                title = trolley.name,
                subtitle = stringResource(R.string.goodsout_extra_session_trolley_caption),
                actionLabel = if (canChange) removeLabel else null,
                onAction = if (canChange) ({ onDetach(trolley) }) else null,
            )
        }
        if (canChange) {
            if (session.trolleys.isNotEmpty()) {
                RowDivider()
            }
            IconInfoRow(
                icon = R.drawable.ic_ui_trolley,
                tone = Tone.Neutral,
                title = stringResource(if (session.trolleys.isEmpty()) R.string.goodsout_no_trolley else R.string.goodsout_extra_session_another_trolley),
                subtitle = stringResource(R.string.goodsout_extra_session_trolley_free_only),
                actionLabel = stringResource(R.string.goodsout_extra_add),
                onAction = onAttach,
            )
        }
    }
}

@Composable
private fun SessionNotes(
    notes: List<SessionDeliveryNote>,
    isPacking: Boolean,
    removing: Set<Long>?,
    onOpen: (SessionDeliveryNote) -> Unit,
    onToggle: (SessionDeliveryNote) -> Unit,
) {
    SectionHeading(
        text = stringResource(R.string.section_delivery_notes),
        trailing = pluralStringResource(R.plurals.goodsout_extra_delivery_notes, notes.size, notes.size),
    )
    if (notes.isEmpty()) {
        HintText(text = stringResource(R.string.goodsout_extra_session_no_notes))
        return
    }
    ListGroup {
        notes.forEachIndexed { index, note ->
            if (index > 0) {
                RowDivider()
            }
            val isSelected = removing?.contains(note.id)
            ExtraRow(
                title = note.reference,
                subtitle = listOfNotNull(
                    pluralStringResource(R.plurals.goodsout_extra_items, note.numberItems, note.numberItems),
                    if (isPacking) {
                        stringResource(R.string.goodsout_extra_packing_percent, percent(note.packingPercentage))
                    } else {
                        stringResource(R.string.goodsout_extra_percent_picked, percent(note.pickingPercentage))
                    },
                    if (note.isForCollection) stringResource(R.string.goodsout_collection) else null,
                    if (note.hasWaitingItems) stringResource(R.string.goodsout_extra_session_note_waiting) else null,
                ).joinToString(KICKER_SEPARATOR),
                chip = note.stateLabel.ifBlank { note.state },
                chipTone = stageTone(stageOf(note.state)),
                leading = isSelected?.let { selectedNow -> { RemoveMark(isSelected = selectedNow) } },
                role = if (isSelected != null) Role.Checkbox else Role.Button,
                modifier = if (isSelected != null) Modifier.semantics { selected = isSelected } else Modifier,
                onClick = if (isSelected != null) ({ onToggle(note) }) else ({ onOpen(note) }),
            )
        }
    }
}

@Composable
private fun RemoveMark(isSelected: Boolean) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(24.dp)
            .background(if (isSelected) colors.dangerSoft else colors.surface, CircleShape)
            .border(2.dp, if (isSelected) colors.danger else colors.borderStrong, CircleShape),
    ) {
        if (isSelected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.danger, modifier = Modifier.size(16.dp))
        }
    }
}
