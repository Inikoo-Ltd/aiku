package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
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
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.ChatContextKind
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.data.api.NoteField
import io.aiku.droid.data.api.StaffRef
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.NoteCard
import io.aiku.droid.ui.components.PlatformChip
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StateTimeline
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.TimelineEntry
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.rememberFileOpener
import io.aiku.droid.ui.goodsout.extra.AssignedElsewhereCard
import io.aiku.droid.ui.goodsout.extra.BackToPickingSheet
import io.aiku.droid.ui.goodsout.extra.CancelDeliveryNoteSheet
import io.aiku.droid.ui.goodsout.extra.EditAddressSheet
import io.aiku.droid.ui.goodsout.extra.IncompleteSetNotice
import io.aiku.droid.ui.goodsout.extra.UndoStep
import io.aiku.droid.ui.goodsout.extra.UndoStepConfirmSheet
import io.aiku.droid.ui.goodsout.extra.joinedWithAnd
import io.aiku.droid.ui.navigation.AskCrmRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanToPickRoute
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

internal const val REPLACEMENT_TYPE = "replacement"

internal val DeliveryNote.stage: DeliveryNoteStage? get() = stageOf(state)

internal val DeliveryNoteStage?.isPicking: Boolean get() = this == DeliveryNoteStage.Picking || this == DeliveryNoteStage.Waiting

internal val DeliveryNoteStage?.isBeforePacking: Boolean
    get() = this == null || this == DeliveryNoteStage.ToDo || isPicking || this == DeliveryNoteStage.Picked

internal val DeliveryNoteStage?.isBeforeDispatch: Boolean
    get() = this != DeliveryNoteStage.Finalised && this != DeliveryNoteStage.Dispatched

@Composable
fun DeliveryNoteScreen(note: DeliveryNoteViewModel, reference: String) {
    val state by note.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { note.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.note
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = note::signInAgain)
            loaded != null -> DeliveryNoteContent(viewModel = note, state = state, note = loaded)
            else -> DeliveryNoteLoading(reference = reference, problem = state.problem, onRetry = note::retry)
        }
    }
}

@Composable
private fun DeliveryNoteLoading(reference: String, problem: GoodsOutProblem?, onRetry: () -> Unit) {
    AppScreen(
        header = { AppHeader(title = reference, kicker = stringResource(R.string.goodsout_card_delivery_note)) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        if (problem == null) {
            SkeletonListCard(rows = 2)
            SkeletonListCard(rows = 4)
        } else {
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_note_failed_title, reference), onRetry = onRetry)
        }
    }
}

@Composable
internal fun problemText(problem: GoodsOutProblem): String = stringResource(
    when (problem) {
        GoodsOutProblem.Offline -> R.string.goodsout_load_offline_body
        GoodsOutProblem.Forbidden -> R.string.goodsout_load_forbidden_body
        GoodsOutProblem.NotFound -> R.string.goodsout_load_not_found_body
        GoodsOutProblem.ServerError -> R.string.goodsout_load_server_body
    },
)

@Composable
internal fun DeliveryNoteEvents(
    event: DeliveryNoteEvent?,
    onSheetProblem: ((String) -> Unit)?,
    onActed: () -> Unit,
    onHandled: () -> Unit,
    onLineDone: (DeliveryNoteEvent.LineDone) -> Unit = {},
    followsRoute: Boolean = true,
) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val resources = LocalResources.current
    val problemTexts = GoodsOutProblem.entries.associateWith { problemText(it) }

    LaunchedEffect(event) {
        when (event) {
            is DeliveryNoteEvent.Acted -> {
                onActed()
                val warning = event.warning
                when {
                    warning != null -> toasts.show(listOfNotNull(event.message, warning).joinToString("\n"), ToastKind.Warning)
                    event.message != null -> toasts.show(event.message, if (event.isNothingChanged) ToastKind.Info else ToastKind.Success)
                }
                if (followsRoute) {
                    event.route?.let(navigator::switchSection)
                }
            }
            is DeliveryNoteEvent.Refused -> {
                val text = event.message.ifBlank { problemTexts.getValue(event.problem) }
                if (onSheetProblem != null) onSheetProblem(text) else toasts.show(text, ToastKind.Error)
            }
            is DeliveryNoteEvent.ScanFeedback -> event.message?.let { toasts.show(it, ToastKind.Error) }
            is DeliveryNoteEvent.LineDone -> {
                toasts.show(resources.getString(R.string.goodsout_scan_line_done_toast, event.code), ToastKind.Success)
                onLineDone(event)
            }
            is DeliveryNoteEvent.LinePacked -> toasts.show(resources.getString(R.string.goodsout_scan_line_packed_toast, event.code), ToastKind.Success)
            null -> return@LaunchedEffect
        }
        onHandled()
    }
}

@Composable
private fun DeliveryNoteContent(viewModel: DeliveryNoteViewModel, state: DeliveryNoteUiState, note: DeliveryNote) {
    val navigator = LocalAppNavigator.current
    val opener = rememberFileOpener()
    val stage = note.stage
    val zone = organisationZone()
    var sheet by remember { mutableStateOf<NoteSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val openSheet: (NoteSheet) -> Unit = {
        sheetProblem = null
        sheet = it
    }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
    }
    val runAction: (DeliveryNoteAction) -> Unit = { action ->
        when (val step = note.stepFor(action)) {
            is ActionStep.Run -> viewModel.act(step.write)
            is ActionStep.Sheet -> openSheet(step.sheet)
            is ActionStep.Open -> {
                viewModel.handOver()
                navigator.open(step.route)
            }
            is ActionStep.Blocked -> Unit
        }
    }
    val openDocument: (String, String) -> Unit = { key, url ->
        opener.open(key = key, folder = documentsFolder(note), fileName = documentFileName(note, key), download = { target -> viewModel.downloadDocument(url, target) })
    }
    val kicker = if (stage == DeliveryNoteStage.ToDo) {
        note.stateLabel
    } else {
        channelName(note.channel, note.shop?.name) ?: note.stateLabel
    }
    val menuActions = note.menuActions
    val openChat = { navigator.open(AskCrmRoute(context = ChatContextKind.DeliveryNote.key, id = note.id, reference = note.reference)) }
    val scanTarget = state.scanTarget?.takeIf { note.canScanToPick || note.canScanToPack }
    val working = state.working

    DeliveryNoteEvents(
        event = state.event,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        onActed = closeSheet,
        onHandled = viewModel::eventHandled,
    )
    ScanEffect(enabled = note.canScanToPick || note.canScanToPack) { scan ->
        if (note.canScanToPack) viewModel.scanToPack(scan.code) else viewModel.scanToPick(scan.code)
    }

    AppScreen(
        header = {
            DeliveryNoteHeader(
                reference = note.reference,
                kicker = stringResource(R.string.goodsout_kicker_delivery_note, kicker),
                menuActions = menuActions,
                onMenuAction = runAction,
                onAskCrm = openChat,
                onDocuments = { openSheet(NoteSheet.Documents) },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        bottomPanel = scanTarget?.let { target -> { NoteScanPanel(note = note, target = target, state = state, viewModel = viewModel) } },
        footer = deliveryNoteFooter(note = note, loadingKey = if (state.isWorking && sheet == null) directActionKey(note) else null, onAction = runAction),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsout_note_refresh_failed_title), onRetry = viewModel::retry) }
        DeliveryNoteNotices(note = note)
        StateStepper(steps = deliveryNoteSteps(current = stage ?: DeliveryNoteStage.ToDo, isComplete = stage == DeliveryNoteStage.Dispatched))
        DeliveryNoteFlags(note = note)
        TakeOverNotice(note = note)
        IncompleteSets(note = note)
        when (stage) {
            DeliveryNoteStage.Finalised -> FinalisedSummary(note = note)
            DeliveryNoteStage.Dispatched -> DispatchedHero(note = note)
            else -> Unit
        }
        PeopleAndEquipment(note = note, onAction = runAction)
        if (stage.isBeforeDispatch) {
            DeliveryNoteQuickActions(onDocuments = { openSheet(NoteSheet.Documents) }, onAskCrm = openChat)
        }
        DeliveryNoteNoteCards(note = note, onEdit = { field -> openSheet(NoteSheet.EditNote(field)) })
        if (note.timeline.isNotEmpty()) {
            StateTimeline(entries = note.timeline.map { TimelineEntry(label = it.label, date = dateTimeText(it.at, zone)) })
        }
        OrderCard(note = note)
        ShippingCard(note = note, onAction = runAction)
        SummaryDetailCard(note = note)
        if (stage.isBeforeDispatch) {
            PackerMustAdd(consumables = note.consumables)
        }
        InsertsBlock(
            note = note,
            isPrinting = working == DeliveryNoteWrite.PrintInserts,
            openingLeafletId = opener.openingKey?.takeIf { it.startsWith(INSERT_KEY_PREFIX) }?.removePrefix(INSERT_KEY_PREFIX)?.toLongOrNull(),
            onOpenPdf = { leaflet ->
                opener.open(
                    key = INSERT_KEY_PREFIX + leaflet.id,
                    folder = documentsFolder(note),
                    fileName = documentFileName(note, INSERT_KEY_PREFIX + leaflet.id),
                    download = { target -> viewModel.downloadInsertPdf(leaflet.id, target) },
                    onDownloaded = viewModel::retry,
                )
            },
            onPrintAll = { viewModel.act(DeliveryNoteWrite.PrintInserts) },
        )
        BoxPackingListBlock(
            note = note,
            isOpening = opener.openingKey == PACKING_LIST_KEY,
            onOpenPdf = { url -> openDocument(PACKING_LIST_KEY, url) },
        )
        when {
            note.canScanToPick -> PickingScanCard(
                note = note,
                onClick = {
                    viewModel.handOver()
                    navigator.open(ScanToPickRoute(reference = note.reference, id = note.id))
                },
            )
            note.canScanToPack -> PackingScanCard(note = note, queuedScans = state.queuedScans)
            else -> Unit
        }
        DeliveryNoteLineList(note = note, onLineClick = { line -> openSheet(NoteSheet.Line(line.id)) })
        DeliveryNoteParcels(
            note = note,
            writeErrors = state.writeErrors,
            isSaving = working is DeliveryNoteWrite.SetParcels,
            onSave = { parcels -> viewModel.act(DeliveryNoteWrite.SetParcels(parcels)) },
        )
        ShipmentsBlock(
            note = note,
            openingKey = opener.openingKey,
            printingShipmentId = (working as? DeliveryNoteWrite.PrintLabel)?.shipmentId,
            onOpenLabel = { shipment ->
                shipment.label.url?.let { url ->
                    opener.open(
                        key = labelKey(shipment),
                        folder = documentsFolder(note),
                        fileName = documentFileName(note, labelKey(shipment)),
                        download = { target -> viewModel.downloadDocument(url, target) },
                    )
                }
            },
            onOpenLink = opener::openLink,
            onPrintLabel = { shipment -> viewModel.act(DeliveryNoteWrite.PrintLabel(shipment.id)) },
            onDelete = { shipment -> openSheet(NoteSheet.DeleteShipment(shipment.id)) },
            onAddAnother = runAction,
        )
        deliveryNoteFootNote(note)?.let { GoodsOutFootNote(text = stringResource(it)) }
    }
    NoteSheets(
        sheet = sheet,
        note = note,
        state = state,
        problem = sheetProblem,
        viewModel = viewModel,
        openingKey = opener.openingKey,
        onOpenDocument = { document -> openDocument(document.key, document.url) },
        onDismiss = closeSheet,
    )
}

private const val INSERT_KEY_PREFIX = "insert-"
private const val PACKING_LIST_KEY = DocumentKey.PACKING_LIST

private fun directActionKey(note: DeliveryNote): String? = note.footerActions.firstOrNull { note.stepFor(it) is ActionStep.Run }?.key

@Composable
private fun NoteSheets(
    sheet: NoteSheet?,
    note: DeliveryNote,
    state: DeliveryNoteUiState,
    problem: String?,
    viewModel: DeliveryNoteViewModel,
    openingKey: String?,
    onOpenDocument: (NoteDocument) -> Unit,
    onDismiss: () -> Unit,
) {
    val isWorking = state.isWorking
    when (sheet) {
        is NoteSheet.Trolley -> TrolleySheet(
            note = note,
            action = sheet.action,
            trolleys = state.trolleys,
            isWorking = isWorking,
            problem = problem,
            onLoad = viewModel::loadTrolleys,
            onConfirm = { trolleyId ->
                viewModel.act(
                    if (sheet.action.key == NoteActionKey.START_PICKING) DeliveryNoteWrite.StartPicking(trolleyId) else DeliveryNoteWrite.ChangeTrolley(trolleyId),
                )
            },
            onDismiss = onDismiss,
        )
        is NoteSheet.PickedBay -> PickedBaySheet(
            note = note,
            action = sheet.action,
            bays = state.pickedBays,
            isWorking = isWorking,
            problem = problem,
            onLoad = viewModel::loadPickedBays,
            onConfirm = { bayId ->
                viewModel.act(
                    if (sheet.action.key == NoteActionKey.SET_AS_PICKED) DeliveryNoteWrite.SetAsPicked(bayId) else DeliveryNoteWrite.ChangePickedBay(bayId),
                )
            },
            onDismiss = onDismiss,
        )
        NoteSheet.Picker -> PickerSheet(
            note = note,
            assign = note.action(NoteActionKey.ASSIGN_PICKER),
            remove = note.action(NoteActionKey.REMOVE_PICKER),
            pickers = state.pickers,
            isWorking = isWorking,
            problem = problem,
            onLoad = viewModel::loadPickers,
            onConfirm = { userId -> viewModel.act(DeliveryNoteWrite.SetPicker(userId)) },
            onDismiss = onDismiss,
        )
        is NoteSheet.Packer -> PickerSheet(
            note = note,
            assign = sheet.action,
            remove = null,
            pickers = state.pickers,
            isWorking = isWorking,
            problem = problem,
            onLoad = viewModel::loadPickers,
            onConfirm = { userId -> userId?.let { viewModel.act(DeliveryNoteWrite.SetPacker(it)) } },
            onDismiss = onDismiss,
            role = HandlerRole.Packer,
        )
        is NoteSheet.Cancel -> CancelDeliveryNoteSheet(
            note = note,
            action = sheet.action,
            isWorking = isWorking,
            problem = problem,
            onConfirm = { createReturn -> viewModel.act(DeliveryNoteWrite.Cancel(createReturn)) },
            onDismiss = onDismiss,
        )
        is NoteSheet.Address -> EditAddressSheet(
            reference = note.reference,
            initial = note.deliveryAddress?.fields,
            problem = problem,
            isWorking = isWorking,
            onSave = { address -> viewModel.act(DeliveryNoteWrite.EditDeliveryAddress(address = address, includesContact = note.canEditContact)) },
            onDismiss = onDismiss,
            saveLabel = stringResource(R.string.goodsout_extra_address_save),
            hint = stringResource(R.string.goodsout_extra_address_hint),
            showsContact = note.canEditContact,
        )
        is NoteSheet.EditNote -> EditNoteSheet(
            note = note,
            field = sheet.field,
            isWorking = isWorking,
            problem = problem,
            onSave = { text -> viewModel.act(DeliveryNoteWrite.SetNote(field = sheet.field, text = text)) },
            onDismiss = onDismiss,
        )
        is NoteSheet.Confirm -> ConfirmNoteActionSheet(
            note = note,
            action = sheet.action,
            isWorking = isWorking,
            problem = problem,
            onAct = viewModel::act,
            onDismiss = onDismiss,
        )
        is NoteSheet.Line -> note.line(sheet.lineId)?.let { line ->
            when {
                note.canWorkLines -> LineActionsSheet(note = note, line = line, isWorking = isWorking, problem = problem, onAct = viewModel::act, onDismiss = onDismiss)
                line.canBeOpenedForPacking(note) -> PackLineSheet(
                    note = note,
                    line = line,
                    isWorking = isWorking,
                    problem = problem,
                    writeErrors = state.writeErrors,
                    onAct = viewModel::act,
                    onDismiss = onDismiss,
                )
                else -> Unit
            }
        }
        NoteSheet.Documents -> DocumentsSheet(note = note, openingKey = openingKey, onOpen = onOpenDocument, onDismiss = onDismiss)
        is NoteSheet.DeleteShipment -> note.shipment(sheet.shipmentId)?.takeIf { it.canDelete }?.let { shipment ->
            DeleteShipmentSheet(
                shipment = shipment,
                isWorking = isWorking,
                problem = problem,
                onConfirm = { viewModel.act(DeliveryNoteWrite.DeleteShipment(shipment.id)) },
                onDismiss = onDismiss,
            )
        }
        null -> Unit
    }
}

@Composable
private fun ConfirmNoteActionSheet(
    note: DeliveryNote,
    action: DeliveryNoteAction,
    isWorking: Boolean,
    problem: String?,
    onAct: (DeliveryNoteWrite) -> Unit,
    onDismiss: () -> Unit,
) {
    when (action.key) {
        NoteActionKey.TAKE_OVER -> TakeOverSheet(note, action, isWorking, problem, onConfirm = { onAct(DeliveryNoteWrite.TakeOver) }, onDismiss = onDismiss)
        NoteActionKey.PUT_BACK_INCOMPLETE_SETS -> PutBackSheet(note, action, isWorking, problem, onConfirm = { onAct(DeliveryNoteWrite.PutBackIncompleteSets) }, onDismiss = onDismiss)
        NoteActionKey.SET_AS_PACKED -> SetAsPackedSheet(note, action, isWorking, problem, onConfirm = { onAct(DeliveryNoteWrite.SetAsPacked) }, onDismiss = onDismiss)
        NoteActionKey.AUTO_FINISH_WAITING -> AutoFinishWaitingSheet(note, action, isWorking, problem, onConfirm = { onAct(DeliveryNoteWrite.AutoFinishWaiting) }, onDismiss = onDismiss)
        NoteActionKey.BACK_TO_PICKING -> BackToPickingSheet(
            reference = note.reference,
            waitingLines = note.lines.count { it.quantityWaitingWarehouse > 0.0 || it.quantityWaitingCrm > 0.0 },
            bay = note.pickedBayLabels.joinToString(", ").takeIf { it.isNotBlank() },
            confirmLabel = action.label,
            onConfirm = { onAct(DeliveryNoteWrite.BackToPicking) },
            onDismiss = onDismiss,
            isWorking = isWorking,
            problem = problem,
        )
        NoteActionKey.UNDO_SET_AS_PICKED -> UndoStepConfirmSheet(
            reference = note.reference,
            step = UndoStep.SetAsPicked,
            onConfirm = { onAct(DeliveryNoteWrite.UndoSetAsPicked) },
            onBack = onDismiss,
            isWorking = isWorking,
            problem = problem,
        )
        NoteActionKey.UNDO_PACKING -> UndoStepConfirmSheet(
            reference = note.reference,
            step = UndoStep.Packing,
            onConfirm = { onAct(DeliveryNoteWrite.UndoPacking) },
            onBack = onDismiss,
            isWorking = isWorking,
            problem = problem,
        )
        NoteActionKey.UNPACK -> UndoStepConfirmSheet(
            reference = note.reference,
            step = UndoStep.Unpack,
            onConfirm = { onAct(DeliveryNoteWrite.Unpack) },
            onBack = onDismiss,
            isWorking = isWorking,
            problem = problem,
        )
        NoteActionKey.FINALISE_AND_DISPATCH -> DispatchConfirmSheet(note, action, isWorking, problem, onConfirm = { onAct(DeliveryNoteWrite.FinaliseAndDispatch) }, onDismiss = onDismiss)
        NoteActionKey.FINALISE -> DispatchConfirmSheet(note, action, isWorking, problem, onConfirm = { onAct(DeliveryNoteWrite.Finalise) }, onDismiss = onDismiss)
        NoteActionKey.DISPATCH -> DispatchConfirmSheet(note, action, isWorking, problem, onConfirm = { onAct(DeliveryNoteWrite.Dispatch) }, onDismiss = onDismiss)
        else -> Unit
    }
}

@Composable
private fun NoteScanPanel(note: DeliveryNote, target: ScanTarget, state: DeliveryNoteUiState, viewModel: DeliveryNoteViewModel) {
    if (note.canScanToPack) {
        ScanToPackPanel(
            note = note,
            target = target,
            isWorking = state.queuedScans > 0,
            onPackAll = viewModel::packAllScanned,
            onPackAmount = viewModel::packScannedAmount,
            onClose = viewModel::closeScanPanel,
        )
        return
    }
    ScanToPickPanel(
        note = note,
        target = target,
        isWorking = state.queuedScans > 0,
        onPickAll = viewModel::pickAllScanned,
        onPickAmount = viewModel::pickScannedAmount,
        onClose = viewModel::closeScanPanel,
    )
}

@Composable
private fun TakeOverNotice(note: DeliveryNote) {
    if (note.action(NoteActionKey.TAKE_OVER) == null) {
        return
    }
    if (note.stage == DeliveryNoteStage.Packing) {
        (note.packer ?: note.picker)?.name?.takeIf { it.isNotBlank() }?.let { packer ->
            AssignedElsewhereCard(title = stringResource(R.string.goodsout_packed_by, packer), body = stringResource(R.string.goodsout_packing_elsewhere))
        }
        return
    }
    note.picker?.name?.takeIf { it.isNotBlank() }?.let { picker ->
        AssignedElsewhereCard(title = stringResource(R.string.goodsout_extra_picked_by, picker), body = stringResource(R.string.goodsout_extra_assigned_elsewhere))
    }
}

@Composable
private fun IncompleteSets(note: DeliveryNote) {
    if (note.incompleteSets.isEmpty() || !note.stage.isPicking) {
        return
    }
    val codes = joinedWithAnd(note.incompleteSets.map { it.code }.filter { it.isNotBlank() })
    val putBack = note.action(NoteActionKey.PUT_BACK_INCOMPLETE_SETS)

    IncompleteSetNotice(
        body = putBack?.let { stringResource(R.string.goodsout_incomplete_set_body_action, codes, it.label) }
            ?: stringResource(R.string.goodsout_incomplete_set_body, codes),
    )
    IncompleteSetParts(parts = note.incompleteSets)
}

private fun deliveryNoteFootNote(note: DeliveryNote): Int? {
    val stage = note.stage
    return when {
        stage.isPicking && note.canWorkLines -> if (note.canScanToPick) R.string.goodsout_picking_note else R.string.goodsout_picking_note_no_scan
        stage == DeliveryNoteStage.Picked && note.action(NoteActionKey.UNDO_SET_AS_PICKED) != null -> R.string.goodsout_picked_note
        stage == DeliveryNoteStage.Packing && note.canPackLines -> if (note.canScanToPack) R.string.goodsout_packing_note else R.string.goodsout_packing_note_no_scan
        note.action(NoteActionKey.FINALISE) != null -> R.string.goodsout_collection_note
        note.action(NoteActionKey.DISPATCH) != null -> if (note.isCollection) R.string.goodsout_collected_note else R.string.goodsout_dispatch_note
        else -> null
    }
}

@Composable
private fun deliveryNoteFooter(note: DeliveryNote, loadingKey: String?, onAction: (DeliveryNoteAction) -> Unit): (@Composable ColumnScope.() -> Unit)? {
    val stage = note.stage
    val items = note.footerItems

    return when {
        stage == DeliveryNoteStage.Dispatched -> null
        items.isNotEmpty() -> { { NoteFooterItems(items = items, loadingKey = loadingKey, onAction = onAction) } }
        stage.isBeforePacking && stage != DeliveryNoteStage.Picked -> {
            val (title, body) = pickingFooterStatus(note)
            return { FooterStatus(title = title, body = body) }
        }
        !note.isEditable || (!note.allowActions && (stage == DeliveryNoteStage.Picked || stage == DeliveryNoteStage.Packing)) -> {
            val (title, body) = viewOnlyStatus(note)
            return { FooterStatus(title = title, body = body) }
        }
        else -> {
            val body = stringResource(
                if (stage == DeliveryNoteStage.Packed && note.parcels.isEmpty()) R.string.goodsout_packed_needs_parcels else R.string.goodsout_nothing_to_do,
            )
            return { FooterStatus(title = note.stateLabel, body = body) }
        }
    }
}

@Composable
private fun pickingFooterStatus(note: DeliveryNote): Pair<String, String?> {
    val stage = note.stage
    val todo = note.lineCounts.todo
    return when {
        !note.isEditable || !note.allowActions -> viewOnlyStatus(note)
        stage == DeliveryNoteStage.Waiting -> stringResource(R.string.goodsout_waiting_status_title) to stringResource(R.string.goodsout_waiting_status_body)
        todo > 0 -> pluralStringResource(R.plurals.goodsout_lines_to_handle, todo, todo) to stringResource(R.string.goodsout_lines_to_handle_hint)
        else -> stringResource(R.string.goodsout_all_lines_handled) to (note.warning ?: note.readOnlyNotice)?.takeIf { it.isNotBlank() }
    }
}

@Composable
private fun viewOnlyStatus(note: DeliveryNote): Pair<String, String?> {
    val body = stringResource(
        when {
            !note.isEditable -> R.string.goodsout_view_only_no_right
            note.picker == null && note.packer == null -> R.string.goodsout_view_only_no_handler
            else -> R.string.goodsout_view_only_not_handler
        },
    )
    return stringResource(R.string.goodsout_view_only) to body
}

@Composable
private fun DeliveryNoteNotices(note: DeliveryNote) {
    note.readOnlyNotice?.takeIf { it.isNotBlank() }?.let { InlineAlert(title = it, tone = AlertTone.Info) }
    note.warning?.takeIf { it.isNotBlank() }?.let { InlineAlert(title = it, tone = AlertTone.Warn) }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun DeliveryNoteFlags(note: DeliveryNote) {
    val channel = channelName(note.channel, note.shop?.name)
    val isPriority = note.isPremiumDispatch || note.type == REPLACEMENT_TYPE
    val flags = listOfNotNull(
        if (isPriority) stringResource(R.string.goodsout_badge_priority) to Tone.Orange else null,
        if (note.customer?.isVip == true) stringResource(R.string.goodsout_badge_vip) to Tone.Lime else null,
        if (note.type == REPLACEMENT_TYPE) stringResource(R.string.goodsout_flag_replacement) to Tone.Orange else null,
        if (note.isCollection) stringResource(R.string.goodsout_collection) to Tone.Primary else null,
        if (note.isShippingByExternal) stringResource(R.string.goodsout_flag_shipped_by_channel) to Tone.Neutral else null,
        if (note.isExport) stringResource(R.string.goodsout_flag_export) to Tone.Neutral else null,
        if (note.hasExtraPacking) stringResource(R.string.goodsout_flag_extra_packing) to Tone.Warn else null,
        if (note.hasGiftMessage) stringResource(R.string.goodsout_flag_gift_message) to Tone.Neutral else null,
        if (note.isCashOnDelivery) stringResource(R.string.goodsout_flag_cash_on_delivery) to Tone.Warn else null,
    )
    if (channel == null && note.platform == null && flags.isEmpty()) {
        return
    }

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
        itemVerticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth(),
    ) {
        channel?.let { StatusBadge(text = it, tone = Tone.Primary) }
        note.platform?.name?.takeIf { it.isNotBlank() }?.let { PlatformChip(name = it) }
        flags.forEach { (label, tone) -> StatusBadge(text = label, tone = tone) }
    }
}

@Composable
private fun personName(person: StaffRef?): String? {
    val staff = LocalStaff.current
    return person?.let { if (it.id == staff.userId) stringResource(R.string.goodsout_person_you, it.name) else it.name }
}

@Composable
private fun PeopleAndEquipment(note: DeliveryNote, onAction: (DeliveryNoteAction) -> Unit) {
    val stage = note.stage
    val changeLabel = stringResource(R.string.goodsout_change)
    val picker = personName(note.picker)
    val packer = personName(note.packer)
    val pickerAction = note.action(NoteActionKey.ASSIGN_PICKER) ?: note.action(NoteActionKey.REMOVE_PICKER)
    val packerAction = note.action(NoteActionKey.CHANGE_PACKER)
    val trolleyAction = note.action(NoteActionKey.CHANGE_TROLLEY)
    val bayAction = note.action(NoteActionKey.CHANGE_PICKED_BAY)
    val showPicker = picker != null || stage.isPicking || pickerAction != null
    val showPacker = packer != null || packerAction != null || stage == DeliveryNoteStage.Picked || stage == DeliveryNoteStage.Packing || stage == DeliveryNoteStage.Packed
    val bays = note.pickedBayLabels
    val rows = mutableListOf<@Composable () -> Unit>()

    if (showPicker) {
        rows += {
            IconInfoRow(
                icon = R.drawable.ic_ui_person,
                title = picker ?: stringResource(R.string.goodsout_no_picker),
                subtitle = stringResource(R.string.goodsout_role_picker),
                actionLabel = pickerAction?.let { changeLabel },
                onAction = pickerAction?.let { action -> { onAction(action) } },
            )
        }
    }
    if (showPacker) {
        rows += {
            IconInfoRow(
                icon = R.drawable.ic_ui_person,
                title = packer ?: picker?.let { stringResource(R.string.goodsout_packer_same_as, it) } ?: stringResource(R.string.goodsout_no_packer),
                subtitle = stringResource(R.string.goodsout_role_packer),
                actionLabel = packerAction?.let { changeLabel },
                onAction = packerAction?.let { action -> { onAction(action) } },
            )
        }
    }
    note.trolleys.forEach { trolley ->
        rows += {
            IconInfoRow(
                icon = R.drawable.ic_ui_trolley,
                title = trolley.name,
                subtitle = stringResource(R.string.goodsout_picking_into),
                actionLabel = trolleyAction?.let { changeLabel },
                onAction = trolleyAction?.let { action -> { onAction(action) } },
            )
        }
    }
    if (note.trolleys.isEmpty() && trolleyAction != null) {
        rows += {
            IconInfoRow(
                icon = R.drawable.ic_ui_trolley,
                title = stringResource(R.string.goodsout_no_trolley),
                subtitle = stringResource(R.string.goodsout_picking_into),
                actionLabel = changeLabel,
                onAction = { onAction(trolleyAction) },
            )
        }
    }
    bays.forEach { bay ->
        rows += {
            IconInfoRow(
                icon = R.drawable.ic_ui_area,
                title = bay,
                subtitle = stringResource(if (stage == DeliveryNoteStage.Picked) R.string.goodsout_bay_waiting_for_packing else R.string.goodsout_picked_bay),
                actionLabel = bayAction?.let { changeLabel },
                onAction = bayAction?.let { action -> { onAction(action) } },
            )
        }
    }
    if (bays.isEmpty() && bayAction != null) {
        rows += {
            IconInfoRow(
                icon = R.drawable.ic_ui_area,
                title = stringResource(R.string.goodsout_bay_none),
                subtitle = stringResource(R.string.goodsout_picked_bay),
                actionLabel = changeLabel,
                onAction = { onAction(bayAction) },
            )
        }
    }
    if (rows.isEmpty()) {
        return
    }

    ListGroup {
        rows.forEachIndexed { index, row ->
            if (index > 0) {
                RowDivider()
            }
            row()
        }
    }
}

@Composable
private fun DeliveryNoteNoteCards(note: DeliveryNote, onEdit: (NoteField) -> Unit) {
    val notes = note.notes

    if (!notes.shipping.text.isNullOrBlank() || notes.shipping.editable) {
        NoteCard(
            title = stringResource(R.string.goodsout_note_shipping_label),
            text = notes.shipping.text,
            icon = if (notes.shipping.editable) R.drawable.ic_ui_edit else R.drawable.ic_ui_lock,
            onClick = if (notes.shipping.editable) ({ onEdit(NoteField.Shipping) }) else null,
        )
    }
    if (!notes.customer.text.isNullOrBlank()) {
        NoteCard(
            title = stringResource(R.string.goodsout_note_customer),
            text = notes.customer.text,
            icon = R.drawable.ic_ui_lock,
        )
    }
    if (!notes.warehouse.text.isNullOrBlank() || notes.warehouse.editable) {
        NoteCard(
            title = stringResource(R.string.goodsout_note_warehouse),
            text = notes.warehouse.text,
            icon = if (notes.warehouse.editable) R.drawable.ic_ui_edit else R.drawable.ic_ui_lock,
            isWarning = !notes.warehouse.text.isNullOrBlank(),
            onClick = if (notes.warehouse.editable) ({ onEdit(NoteField.Warehouse) }) else null,
        )
    }
}

@Composable
private fun OrderCard(note: DeliveryNote) {
    val customer = note.customer
    val orderReference = note.order?.reference?.takeIf { it.isNotBlank() } ?: note.reference
    val typeLabel = when (note.type) {
        REPLACEMENT_TYPE -> stringResource(R.string.goodsout_type_replacement)
        else -> stringResource(R.string.goodsout_type_order)
    }
    val customerLine = customer?.name?.takeIf { it.isNotBlank() }?.let { name ->
        customer.reference?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.goodsout_customer_reference, name, it) } ?: name
    }
    val lines = listOfNotNull(
        DetailLine(icon = R.drawable.ic_ui_order, text = stringResource(R.string.goodsout_order_reference_type, orderReference, typeLabel)),
        customerLine?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        customer?.companyName?.takeIf { it.isNotBlank() && it != customer.name }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        customer?.contactName?.takeIf { it.isNotBlank() && it != customer.name }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        customer?.email?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_mail, text = it) },
        customer?.phone?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_phone, text = it) },
    )

    DetailCard(title = stringResource(R.string.goodsout_card_order), lines = lines)
}

@Composable
private fun ShippingCard(note: DeliveryNote, onAction: (DeliveryNoteAction) -> Unit) {
    val editAddress = note.action(NoteActionKey.EDIT_DELIVERY_ADDRESS)?.takeIf { !it.isDisabled }
    val address = note.deliveryAddress?.lines?.filter { it.isNotBlank() }.orEmpty()
    val line = when {
        note.isCollection -> DetailLine(icon = R.drawable.ic_ui_warehouse, text = stringResource(R.string.goodsout_collection_hint))
        address.isNotEmpty() -> DetailLine(icon = R.drawable.ic_ui_location, text = address.joinToString(", "))
        else -> DetailLine(icon = R.drawable.ic_ui_location, text = stringResource(R.string.goodsout_no_address))
    }
    val external = note.shipping.external?.engineLabel?.takeIf { it.isNotBlank() }?.let {
        DetailLine(icon = R.drawable.ic_ui_truck, text = stringResource(R.string.goodsout_extra_label_from_platform, it))
    }

    DetailCard(
        title = stringResource(if (note.isCollection) R.string.goodsout_collection else R.string.goodsout_card_shipping),
        lines = listOfNotNull(line, external),
        actionLabel = editAddress?.let { it.label.ifBlank { stringResource(R.string.goodsout_address_edit) } },
        actionModifier = Modifier.heightIn(min = 44.dp),
        onAction = editAddress?.let { action -> { onAction(action) } },
    )
}

@Composable
private fun SummaryDetailCard(note: DeliveryNote) {
    val summary = note.summary
    val items = pluralStringResource(R.plurals.goodsout_summary_items, pluralQuantity(summary.numberItems), quantityText(summary.numberItems))
    val skos = pluralStringResource(R.plurals.goodsout_summary_skos, pluralQuantity(summary.numberSkos), quantityText(summary.numberSkos))
    val units = pluralStringResource(R.plurals.goodsout_summary_units, pluralQuantity(summary.numberUnits), quantityText(summary.numberUnits))
    val minutes = listOfNotNull(
        minutesText(summary.estimatedPickingMinutes)?.let { stringResource(R.string.goodsout_summary_picking_minutes, it) },
        minutesText(summary.estimatedPackingMinutes)?.let { stringResource(R.string.goodsout_summary_packing_minutes, it) },
    ).joinToString(", ")
    val lines = listOfNotNull(
        DetailLine(icon = R.drawable.ic_ui_sko, text = stringResource(R.string.goodsout_summary_contents, items, skos, units)),
        minutes.takeIf { it.isNotEmpty() }?.let { DetailLine(icon = R.drawable.ic_ui_clock, text = it) },
        weightText(summary.estimatedWeightKg)?.let { DetailLine(icon = R.drawable.ic_ui_weight, text = it) },
    )

    DetailCard(title = stringResource(R.string.goodsout_card_delivery_note), lines = lines)
}

@Composable
private fun DeliveryNoteLineList(note: DeliveryNote, onLineClick: (DeliveryNoteLine) -> Unit) {
    val stage = note.stage
    val title = when {
        stage == DeliveryNoteStage.Picked -> stringResource(R.string.dn_state_picked)
        stage.isBeforePacking -> stringResource(R.string.goodsout_items_by_location)
        else -> stringResource(R.string.goodsout_lines)
    }
    val counts = note.lineCounts

    GoodsOutHeading(
        title = title,
        trailing = if (counts.all > 0) pluralStringResource(R.plurals.goodsout_lines_done_of, counts.all, counts.done, counts.all) else null,
    )
    if (note.lines.isEmpty()) {
        Text(text = stringResource(R.string.goodsout_no_lines), fontSize = 14.sp, color = LocalAikuColors.current.textTertiary)
        return
    }
    ListGroup {
        note.lines.forEachIndexed { index, line ->
            if (index > 0) {
                RowDivider()
            }
            DeliveryNoteLineRow(
                line = line,
                stage = stage,
                onClick = if (note.canWorkLines || line.canBeOpenedForPacking(note)) ({ onLineClick(line) }) else null,
            )
        }
    }
}

internal fun lineStatusOf(line: DeliveryNoteLine, stage: DeliveryNoteStage?): LineStatus {
    val done = if (stage.isBeforePacking) line.quantityPicked else line.quantityPacked
    return when {
        line.quantityWaitingWarehouse > 0 || line.quantityWaitingCrm > 0 -> LineStatus.Waiting
        !stage.isBeforePacking && line.isPackingDone -> LineStatus.Packed
        line.quantityNotPicked > 0 && line.quantityPicked <= 0 -> LineStatus.NotPicked
        stage.isBeforePacking && line.isPicked -> LineStatus.Picked
        done > 0 -> LineStatus.Partial
        else -> LineStatus.ToHandle
    }
}

@Composable
private fun lineStatusChip(line: DeliveryNoteLine, status: LineStatus, stage: DeliveryNoteStage?): String? = when (status) {
    LineStatus.Picked -> stringResource(R.string.goodsout_line_picked)
    LineStatus.Packed -> stringResource(R.string.goodsout_line_packed)
    LineStatus.Waiting -> if (line.quantityWaitingWarehouse > 0) {
        stringResource(R.string.goodsout_line_waiting_warehouse, quantityText(line.quantityWaitingWarehouse, line.quantityWaitingWarehouseLabel))
    } else {
        stringResource(R.string.goodsout_line_waiting_crm, quantityText(line.quantityWaitingCrm, line.quantityWaitingCrmLabel))
    }
    LineStatus.NotPicked -> line.pickings.firstNotNullOfOrNull { picking -> picking.notPickedReasonLabel?.takeIf { picking.isNotPicked && it.isNotBlank() } }
        ?.let { stringResource(R.string.goodsout_line_not_picked_reason, it) }
        ?: stringResource(R.string.goodsout_line_not_picked)
    LineStatus.Partial, LineStatus.ToHandle -> when {
        line.quantityNotPicked > 0 -> stringResource(R.string.goodsout_line_some_not_picked, quantityText(line.quantityNotPicked, line.quantityNotPickedLabel))
        stage == DeliveryNoteStage.Packing && line.quantityToPack > 0 -> stringResource(R.string.goodsout_line_to_pack, quantityText(line.quantityToPack, line.quantityToPackLabel))
        else -> null
    }
}

@Composable
private fun DeliveryNoteLineRow(line: DeliveryNoteLine, stage: DeliveryNoteStage?, onClick: (() -> Unit)?) {
    val colors = LocalAikuColors.current
    val status = lineStatusOf(line, stage)
    val sko = line.orgStock
    val locations = line.locations.filter { it.code.isNotBlank() }.map { location ->
        val stock = location.stockLabel?.takeIf { it.isNotBlank() } ?: location.stock?.let { quantityText(it) }
        if (stock == null) location.code else stringResource(R.string.goodsout_location_stock, location.code, stock)
    }.joinToString(", ")
    val subtitle = listOf(locations, sko?.name.orEmpty()).filter { it.isNotBlank() }.joinToString(" · ")
    val done = if (stage.isBeforePacking) quantityText(line.quantityPicked, line.quantityPickedLabel) else quantityText(line.quantityPacked, line.quantityPackedLabel)
    val required = if (stage.isBeforePacking) quantityText(line.quantityRequired, line.quantityRequiredLabel) else quantityText(line.quantityPicked, line.quantityPickedLabel)
    val boxes = if (stage.isBeforePacking) null else lineBoxesText(line)
    val details = listOfNotNull(
        line.noteToPickers?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.goodsout_line_note_pickers, it) },
        line.noteToPackers?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.goodsout_line_note_packers, it) },
        line.unNumbersText?.let { stringResource(R.string.goodsout_line_un_numbers, it) },
        line.replacementReason?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.goodsout_line_replacement, it) },
        if (line.isIndivisibleSet) stringResource(R.string.goodsout_line_indivisible_set) else null,
    )
    val pickings = line.pickings.map { picking -> listOfNotNull(pickingText(picking), pickingDetail(picking)).joinToString(" · ") } + listOfNotNull(boxes)
    val chip = lineStatusChip(line, status, stage)

    ItemRow(
        code = sko?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_line_unknown_sko),
        subtitle = subtitle,
        chip = chip,
        chipTone = when {
            chip == null || !status.isUnfinished -> lineChipTone(status)
            line.quantityNotPicked > 0 -> Tone.Danger
            else -> Tone.Primary
        },
        done = done,
        required = required,
        quantityTone = lineQuantityTone(status),
        imageUrl = sko?.imageUrl,
        extra = if (details.isEmpty() && pickings.isEmpty()) {
            null
        } else {
            {
                pickings.forEach { picking ->
                    Text(text = picking, fontSize = 12.sp, lineHeight = 16.sp, color = colors.textTertiary)
                }
                details.forEach { detail ->
                    Text(text = detail, fontSize = 12.sp, lineHeight = 16.sp, color = colors.warnInk)
                }
            }
        },
        onClick = onClick,
    )
}

private val LineStatus.isUnfinished: Boolean get() = this == LineStatus.Partial || this == LineStatus.ToHandle

internal fun DeliveryNote.parcelWeightKg(): Double? =
    parcels.mapNotNull { it.weightKg }.takeIf { it.isNotEmpty() }?.sum()
