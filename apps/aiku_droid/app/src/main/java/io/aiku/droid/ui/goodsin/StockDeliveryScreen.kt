package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.CheckResult
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryItem
import io.aiku.droid.data.api.StockDeliveryState
import io.aiku.droid.data.api.StockDeliveryWrite
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.forConfirm
import io.aiku.droid.ui.components.stockDeliverySteps
import io.aiku.droid.ui.goodsout.DisabledActionNotice
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.inventory.scanProblemText
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PlaceItemRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockDeliveryScanRoute
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.sync.OfflineCopyNote
import io.aiku.droid.ui.sync.QueueEventEffect
import io.aiku.droid.ui.sync.QueuedActionsBlock
import io.aiku.droid.ui.sync.QueuedRow
import io.aiku.droid.ui.sync.doubleField
import io.aiku.droid.ui.sync.longField
import java.time.ZoneId

private sealed interface DeliverySheet {
    data class Check(val itemId: Long) : DeliverySheet
    data class ChooseLine(val code: String, val itemIds: List<Long>) : DeliverySheet
    data class ChecklistResult(val key: String) : DeliverySheet
    data object More : DeliverySheet
    data object ConfirmCheckAll : DeliverySheet
    data class ConfirmUnreceive(val action: DeliveryNoteAction) : DeliverySheet
}

@Composable
fun StockDeliveryScreen(delivery: StockDeliveryViewModel, reference: String) {
    val state by delivery.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { delivery.onStart() }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        val loaded = state.delivery
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = delivery::signInAgain)
            loaded != null -> StockDeliveryContent(viewModel = delivery, state = state, delivery = loaded)
            else -> StockDeliveryLoading(
                reference = reference,
                problem = state.problem,
                onRetry = delivery::retry,
                refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = delivery::refresh),
            )
        }
    }
}

@Composable
internal fun StockDeliveryLoading(reference: String, problem: GoodsOutProblem?, onRetry: () -> Unit, refresh: PullRefresh? = null) {
    AppScreen(
        header = { AppHeader(title = reference, kicker = stringResource(R.string.goodsin_kicker_stock_delivery_plain)) },
        activeTab = MainTab.Menu,
        showBar = true,
        refresh = refresh,
    ) {
        if (problem == null) {
            SkeletonListCard(rows = 2)
            SkeletonListCard(rows = 4)
        } else {
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsin_delivery_failed, reference), onRetry = onRetry)
        }
    }
}

@Composable
private fun StockDeliveryContent(viewModel: StockDeliveryViewModel, state: StockDeliveryUiState, delivery: StockDelivery) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val scanFeedback = LocalScanController.current.feedback
    val resources = LocalResources.current
    val zone = organisationZone()
    var sheet by remember { mutableStateOf<DeliverySheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val view = state.view
    val unreceive = delivery.action(GoodsInActionKey.UNRECEIVE)
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
        viewModel.clearWriteErrors()
    }
    val openSheet: (DeliverySheet) -> Unit = {
        sheetProblem = null
        sheet = it
    }
    val openPlace: (StockDeliveryItem) -> Unit = { item ->
        viewModel.handOver()
        navigator.open(PlaceItemRoute(id = delivery.id, itemId = item.id, reference = delivery.reference, skoCode = item.code))
    }
    val openLine: (Long) -> Unit = { itemId ->
        val item = delivery.item(itemId)
        when {
            item == null -> Unit
            view == DeliveryView.Place -> if (item.canBePlaced && delivery.canPlace) openPlace(item)
            delivery.canCheck -> openSheet(DeliverySheet.Check(itemId))
        }
    }
    val outcome = state.scanOutcome
    val notOnDeliveryText = (outcome as? LineScanOutcome.NotOnDelivery)?.let { stringResource(R.string.goodsin_scan_not_on_delivery, it.code, delivery.reference) }
    val failedText = (outcome as? LineScanOutcome.Failed)?.let { scanProblemText(it.code, it.problem) }

    GoodsInEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        doneText = { done -> doneText(done, state.delivery ?: delivery) { id, args -> resources.getString(id, *args) } },
        onDone = { closeSheet() },
    )
    QueueEventEffect(event = state.queueEvent, reference = delivery.reference, onHandled = viewModel::queueEventHandled, onQueued = closeSheet)
    ScanEffect(enabled = delivery.scansLines(view) && sheet == null) { scan -> viewModel.scanLine(scan.code) }
    LaunchedEffect(outcome) {
        when (outcome) {
            is LineScanOutcome.Line -> openLine(outcome.itemId)
            is LineScanOutcome.Choose -> openSheet(DeliverySheet.ChooseLine(outcome.code, outcome.itemIds))
            is LineScanOutcome.NotOnDelivery -> {
                scanFeedback.rejected()
                notOnDeliveryText?.let { toasts.show(it, ToastKind.Info) }
            }
            is LineScanOutcome.Failed -> failedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        viewModel.scanOutcomeHandled()
    }

    AppScreen(
        header = {
            AppHeader(
                title = delivery.reference,
                kicker = stringResource(R.string.goodsin_kicker_stock_delivery, delivery.supplier?.label?.takeIf { it.isNotBlank() } ?: delivery.stateLabel),
                actions = {
                    if (unreceive != null) {
                        HeaderIconButton(
                            icon = R.drawable.ic_ui_more,
                            description = stringResource(R.string.more_actions),
                            onClick = { openSheet(DeliverySheet.More) },
                            filled = true,
                        )
                    }
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = deliveryFooter(
            delivery = delivery,
            view = view,
            working = state.working,
            onReceive = viewModel::receive,
            onCheckAll = { openSheet(DeliverySheet.ConfirmCheckAll) },
            onPlace = openPlace,
            onNextDelivery = { navigator.switchSection(StockDeliveriesRoute()) },
        ),
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh, isEnabled = !state.isWorking),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsin_delivery_refresh_failed), onRetry = viewModel::retry) }
        state.copyAt?.let { OfflineCopyNote(copyAt = it) }
        DeliveryStatus(delivery = delivery)
        OrdererCard(delivery = delivery, zone = zone)
        DeliveryInfoCard(delivery = delivery)
        SummaryCard(delivery = delivery)
        ViewTabs(options = delivery.views().map { it to viewLabel(delivery, it) }, active = view, onSelect = viewModel::selectView)
        state.lookingUpCode?.let { code -> InlineAlert(title = stringResource(R.string.scan_looking_up, code), tone = AlertTone.Info) }
        QueuedActionsBlock(rows = state.pending.map { QueuedRow(it, placementQueuedText(it, delivery)) }, onUndo = viewModel::undoQueued)
        when (view) {
            DeliveryView.Items -> ArrivingItems(delivery = delivery)
            DeliveryView.Check -> CheckItems(
                delivery = delivery,
                onScan = { navigator.open(StockDeliveryScanRoute(id = delivery.id, reference = delivery.reference)) },
                onOpen = { item -> openSheet(DeliverySheet.Check(item.id)) },
            )
            DeliveryView.Place -> PlaceItems(delivery = delivery, onOpen = openPlace)
            DeliveryView.Done -> DoneItems(delivery = delivery)
            DeliveryView.Checklist -> delivery.checklist?.let { checklist ->
                ChecklistView(
                    delivery = delivery,
                    checklist = checklist,
                    onOpenCheck = if (delivery.canRecordChecks) ({ key -> openSheet(DeliverySheet.ChecklistResult(key)) }) else null,
                )
            }
            null -> Unit
        }
    }

    when (val current = sheet) {
        is DeliverySheet.Check -> delivery.item(current.itemId)?.let { item ->
            CheckedQuantitySheet(
                item = item,
                isWorking = state.isWorking,
                problem = sheetProblem ?: state.writeErrors.values.flatten().firstOrNull(),
                onSave = { quantity -> viewModel.check(item.id, quantity) },
                onNotReceived = { viewModel.notReceived(item.id) },
                onDismiss = closeSheet,
            )
        }
        is DeliverySheet.ChooseLine -> ChooseLineSheet(
            code = current.code,
            items = current.itemIds.mapNotNull(delivery::item),
            onChoose = { item ->
                sheet = null
                openLine(item.id)
            },
            onDismiss = closeSheet,
        )
        is DeliverySheet.ChecklistResult -> {
            val checklist = delivery.checklist
            val check = checklist?.check(current.key)
            if (checklist != null && check != null) {
                CheckResultSheet(
                    check = check,
                    checklist = checklist,
                    isWorking = state.isWorking,
                    problem = sheetProblem,
                    errors = state.writeErrors,
                    onSave = { result, note, incidentType, quantity -> viewModel.recordCheck(check.key, result, note, incidentType, quantity) },
                    onDismiss = closeSheet,
                )
            }
        }
        DeliverySheet.More -> AikuBottomSheet(
            title = stringResource(R.string.goodsin_more_title, delivery.reference),
            onDismiss = closeSheet,
        ) {
            if (unreceive != null) {
                ListGroup {
                    ActionLinkRow(
                        icon = R.drawable.ic_ui_undo,
                        title = unreceive.label.ifBlank { stringResource(R.string.goodsin_unmark_received) },
                        subtitle = unreceive.disabledReason ?: stringResource(R.string.goodsin_unmark_received_body),
                        tone = ActionTone.Caution,
                        onClick = if (unreceive.isDisabled) null else ({ openSheet(DeliverySheet.ConfirmUnreceive(unreceive)) }),
                    )
                }
            }
        }
        DeliverySheet.ConfirmCheckAll -> ConfirmDialog(
            title = stringResource(R.string.goodsin_check_all_title),
            body = stringResource(R.string.goodsin_check_all_body),
            confirmLabel = stringResource(R.string.goodsin_check_all_items),
            confirmLook = goodsInActionLook(GoodsInActionKey.CHECK).forConfirm(),
            onConfirm = {
                sheet = null
                viewModel.checkAll()
            },
            onDismiss = closeSheet,
        )
        is DeliverySheet.ConfirmUnreceive -> ConfirmDialog(
            title = stringResource(R.string.goodsin_unmark_received_title, delivery.reference),
            body = stringResource(R.string.goodsin_unmark_received_body),
            confirmLabel = current.action.label.ifBlank { stringResource(R.string.goodsin_unmark_received) },
            onConfirm = {
                sheet = null
                viewModel.unreceive()
            },
            onDismiss = closeSheet,
            confirmLook = goodsInActionLook(GoodsInActionKey.UNRECEIVE).forConfirm(),
        )
        null -> Unit
    }
}

internal fun checkDoneText(done: GoodsInEvent.Done, delivery: StockDelivery, text: (Int, Array<out Any>) -> String): String? {
    val action = done.action as? StockDeliveryWrite.Check ?: return null
    val item = delivery.item(action.itemId)
    val code = item?.code.orEmpty()
    return when {
        done.wasRaised -> text(R.string.goodsin_toast_check_raised, arrayOf(code, quantityText(item?.skoChecked ?: 0.0, item?.skoCheckedLabel)))
        action.skoQuantity == 0.0 -> text(R.string.goodsin_toast_not_received, arrayOf(code))
        else -> text(R.string.goodsin_toast_checked, arrayOf(code, quantityText(action.skoQuantity)))
    }
}

private fun doneText(done: GoodsInEvent.Done, delivery: StockDelivery, text: (Int, Array<out Any>) -> String): String? = when (val action = done.action) {
    StockDeliveryWrite.Receive -> text(R.string.goodsin_toast_marked_received, arrayOf(delivery.reference))
    StockDeliveryWrite.Unreceive -> text(R.string.goodsin_toast_unmarked_received, arrayOf(delivery.reference))
    StockDeliveryWrite.CheckAll -> text(R.string.goodsin_toast_checked_all, arrayOf(delivery.reference))
    is StockDeliveryWrite.Check -> checkDoneText(done, delivery, text)
    is StockDeliveryWrite.RecordCheck -> text(if (action.result == CheckResult.Passed) R.string.goodsin_toast_check_passed else R.string.goodsin_toast_check_failed, emptyArray())
    else -> null
}

@Composable
private fun placementQueuedText(action: QueuedAction, delivery: StockDelivery): String {
    val item = action.payload.longField(GoodsInOfflinePayloads.STOCK_DELIVERY_ITEM_ID)?.let(delivery::item)
    val quantity = quantityText(action.payload.doubleField(GoodsInOfflinePayloads.QUANTITY) ?: 0.0)
    val linkId = action.payload.longField(GoodsInOfflinePayloads.LOCATION_ORG_STOCK_ID)
    val location = item?.locations?.firstOrNull { it.locationOrgStockId == linkId }?.location?.code
    return if (location != null) {
        stringResource(R.string.offline_stock_placed, quantity, item.code, location)
    } else {
        stringResource(R.string.offline_stock_placed_new, quantity, item?.code.orEmpty())
    }
}

@Composable
private fun viewLabel(delivery: StockDelivery, view: DeliveryView): String = when (view) {
    DeliveryView.Items -> stringResource(R.string.goodsin_tab_items)
    DeliveryView.Check -> stringResource(R.string.goodsin_chip_with_count, stringResource(R.string.goodsin_tab_check), delivery.items.count { it.needsCheck })
    DeliveryView.Place -> stringResource(R.string.goodsin_chip_with_count, stringResource(R.string.goodsin_tab_place), delivery.items.count { it.canBePlaced })
    DeliveryView.Done -> stringResource(R.string.goodsin_tab_done)
    DeliveryView.Checklist -> stringResource(R.string.goodsin_tab_checklist)
}

private fun deliveryFooter(
    delivery: StockDelivery,
    view: DeliveryView?,
    working: StockDeliveryWrite?,
    onReceive: () -> Unit,
    onCheckAll: () -> Unit,
    onPlace: (StockDeliveryItem) -> Unit,
    onNextDelivery: () -> Unit,
): (@Composable ColumnScope.() -> Unit)? {
    when (view) {
        DeliveryView.Items -> {
            val receive = delivery.action(GoodsInActionKey.RECEIVE) ?: return null
            return {
                if (receive.isDisabled) {
                    DisabledActionNotice(action = receive)
                } else {
                    AikuButton(
                        text = receive.label.ifBlank { stringResource(R.string.goodsin_mark_received) },
                        onClick = onReceive,
                        style = goodsInActionLook(receive.key).style,
                        icon = goodsInActionLook(receive.key).icon,
                        isLoading = working == StockDeliveryWrite.Receive,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        }
        DeliveryView.Check -> {
            val check = delivery.action(GoodsInActionKey.CHECK) ?: return null
            if (delivery.items.none { it.needsCheck }) {
                return null
            }
            return {
                if (check.isDisabled) {
                    DisabledActionNotice(action = check)
                } else {
                    AikuButton(
                        text = stringResource(R.string.goodsin_check_all_items),
                        onClick = onCheckAll,
                        style = goodsInActionLook(check.key).style,
                        icon = goodsInActionLook(check.key).icon,
                        isLoading = working == StockDeliveryWrite.CheckAll,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        }
        DeliveryView.Place -> {
            val place = delivery.action(GoodsInActionKey.PLACE) ?: return null
            val next = delivery.nextToPlace() ?: return null
            return {
                if (place.isDisabled) {
                    DisabledActionNotice(action = place)
                } else {
                    AikuButton(
                        text = stringResource(R.string.goodsin_place_item, next.code),
                        onClick = { onPlace(next) },
                        style = goodsInActionLook(place.key).style,
                        icon = goodsInActionLook(place.key).icon,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        }
        DeliveryView.Done -> return {
            AikuButton(
                text = stringResource(R.string.goodsin_next_stock_delivery),
                onClick = onNextDelivery,
                icon = R.drawable.ic_ui_next,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        else -> return null
    }
}

@Composable
private fun DeliveryStatus(delivery: StockDelivery) {
    val stage = delivery.stage()
    when {
        stage != null -> StateStepper(steps = stockDeliverySteps(current = stage, isComplete = delivery.state == StockDeliveryState.PLACED))
        delivery.state == StockDeliveryState.NOT_RECEIVED -> InlineAlert(
            title = delivery.stateLabel.ifBlank { stringResource(R.string.goodsin_not_received) },
            body = stringResource(R.string.goodsin_status_not_received),
            tone = AlertTone.Danger,
        )
        delivery.state == StockDeliveryState.CANCELLED -> InlineAlert(
            title = delivery.stateLabel.ifBlank { stringResource(R.string.goodsin_state_cancelled) },
            body = stringResource(R.string.goodsin_status_cancelled),
            tone = AlertTone.Danger,
        )
    }
}

@Composable
private fun OrdererCard(delivery: StockDelivery, zone: ZoneId) {
    val supplier = delivery.supplier
    val lines = listOfNotNull(
        supplier?.let { listOf(it.name, it.code).filter(String::isNotBlank).joinToString(LIST_SEPARATOR) }?.takeIf { it.isNotBlank() }
            ?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        delivery.agentReference?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_document, text = stringResource(R.string.goodsin_agent_reference, it)) },
        delivery.purchaseOrders.map { it.reference }.filter { it.isNotBlank() }.takeIf { it.isNotEmpty() }
            ?.let { DetailLine(icon = R.drawable.ic_ui_document, text = stringResource(R.string.goodsin_purchase_orders, it.joinToString(", "))) },
        goodsInDate(delivery.dispatchedAt, zone)?.let { DetailLine(icon = R.drawable.ic_ui_truck, text = stringResource(R.string.goodsin_dispatched_on, it)) },
        goodsInDate(delivery.estimatedReceivingDate, zone)?.let { DetailLine(icon = R.drawable.ic_ui_clock, text = stringResource(R.string.goodsin_estimated_receiving, it)) },
        goodsInDate(delivery.receivedAt, zone)?.let { DetailLine(icon = R.drawable.ic_ui_check, text = stringResource(R.string.goodsin_received_on, it)) },
    )
    if (lines.isNotEmpty()) {
        DetailCard(title = stringResource(R.string.goodsin_orderer), lines = lines)
    }
}

@Composable
private fun DeliveryInfoCard(delivery: StockDelivery) {
    val info = delivery.delivery ?: return
    val fields = listOfNotNull(
        info.typeText?.let { DeliveryField(R.string.goodsin_field_type, it) },
        info.containerSizeText?.let { DeliveryField(R.string.goodsin_field_container_size, it) },
        info.incotermText?.let { DeliveryField(R.string.goodsin_field_incoterm, it) },
        info.portOfExportText?.let { DeliveryField(R.string.goodsin_field_port_export, it) },
        info.portOfImportText?.let { DeliveryField(R.string.goodsin_field_port_import, it) },
        info.containerNumberText?.let { DeliveryField(R.string.goodsin_field_container_number, it) },
        info.shipperReferenceText?.let { DeliveryField(R.string.goodsin_field_shipper_reference, it) },
        info.trackingNumberText?.let { DeliveryField(R.string.goodsin_field_tracking_number, it) },
        info.shippingAgentText?.let { DeliveryField(R.string.goodsin_field_shipping_agent, it) },
    )
    if (fields.isNotEmpty()) {
        FieldGridCard(title = stringResource(R.string.goodsin_delivery), fields = fields)
    }
}

@Composable
private fun SummaryCard(delivery: StockDelivery) {
    val summary = delivery.summary
    val isInGoodsIn = delivery.state !in StockDeliveryState.beforeReceiving
    val fields = listOfNotNull(
        DeliveryField(R.string.goodsin_items, countText(summary.numberItems)),
        DeliveryField(R.string.goodsin_field_received_checked, stringResource(R.string.goodsin_of, countText(summary.numberChecked), countText(summary.numberItems))).takeIf { isInGoodsIn },
        DeliveryField(R.string.goodsin_field_placed, countText(summary.numberPlaced)).takeIf { isInGoodsIn },
        DeliveryField(R.string.goodsin_field_new_skos, countText(summary.numberNewOrgStocks)),
        DeliveryField(R.string.goodsin_not_received, countText(summary.numberNotReceived)).takeIf { isInGoodsIn && summary.numberNotReceived > 0 },
        DeliveryField(R.string.goodsin_field_under, countText(summary.underDelivered)).takeIf { isInGoodsIn },
        DeliveryField(R.string.goodsin_field_over, countText(summary.overDelivered)).takeIf { isInGoodsIn },
    )
    FieldGridCard(title = stringResource(R.string.goodsin_items), fields = fields)
}

@Composable
private fun ArrivingItems(delivery: StockDelivery) {
    SectionHeading(
        title = stringResource(R.string.goodsin_tab_items),
        caption = pluralStringResource(R.plurals.goodsin_item_count, delivery.items.size, delivery.items.size),
    )
    ItemList(items = delivery.items) { item ->
        ItemRow(
            code = item.code,
            subtitle = listOfNotNull(item.name.takeIf { it.isNotBlank() }, item.purchaseOrderReference?.takeIf { it.isNotBlank() }).joinToString(LIST_SEPARATOR),
            chip = stringResource(R.string.goodsin_chip_new_sko).takeIf { item.isNewOrgStock },
            chipTone = Tone.Primary,
            done = quantityText(item.skoExpected, item.skoExpectedLabel),
            imageUrl = item.sko?.imageUrl,
        )
    }
    HelperNote(text = stringResource(R.string.goodsin_arriving_note))
}

@Composable
private fun CheckItems(delivery: StockDelivery, onScan: () -> Unit, onOpen: (StockDeliveryItem) -> Unit) {
    val items = delivery.itemsFor(DeliveryView.Check)
    val checkedCount = items.count { it.skoChecked != null }
    val canCheck = delivery.canCheck

    if (canCheck && items.any { it.needsCheck }) {
        ScanPromptCard(title = stringResource(R.string.goodsin_scan_to_check), body = stringResource(R.string.goodsin_scan_to_check_body), onClick = onScan)
    }
    SectionHeading(
        title = stringResource(R.string.goodsin_tab_check),
        caption = pluralStringResource(R.plurals.goodsin_checked_count, items.size, checkedCount, items.size),
    )
    if (items.isEmpty()) {
        HelperNote(text = stringResource(R.string.goodsin_check_nothing))
    }
    ItemList(items = items) { item ->
        val look = checkLook(item)
        ItemRow(
            code = item.code,
            subtitle = look.subtitle,
            chip = look.chip,
            chipTone = look.tone ?: Tone.Neutral,
            done = quantityText(item.skoChecked ?: 0.0, item.skoCheckedLabel),
            required = quantityText(item.skoExpected, item.skoExpectedLabel),
            quantityTone = look.tone,
            imageUrl = item.sko?.imageUrl,
            onClick = if (canCheck) ({ onOpen(item) }) else null,
        )
    }
    HelperNote(text = stringResource(R.string.goodsin_checking_note))
}

@Composable
private fun PlaceItems(delivery: StockDelivery, onOpen: (StockDeliveryItem) -> Unit) {
    val items = delivery.itemsFor(DeliveryView.Place)
    val placedCount = items.count { !it.canBePlaced }
    val canPlace = delivery.canPlace

    SectionHeading(
        title = stringResource(R.string.goodsin_place_checked),
        caption = pluralStringResource(R.plurals.goodsin_placed_count, items.size, placedCount, items.size),
    )
    if (items.isEmpty()) {
        HelperNote(text = stringResource(R.string.goodsin_place_nothing))
    }
    ItemList(items = items) { item ->
        val placedIn = item.placements.mapNotNull { it.location?.code?.takeIf(String::isNotBlank) }.distinct().joinToString(", ")
        val pickingLocation = item.locations.firstOrNull { it.isDefaultWholesale }?.location?.code
            ?: item.locations.firstOrNull { it.stockType == LocationStockType.Picking }?.location?.code
        val (chip, tone) = when {
            !item.canBePlaced -> stringResource(R.string.goodsin_chip_placed) to Tone.Success
            !item.hasPickingLocation -> stringResource(R.string.goodsin_no_picking_location) to Tone.Warn
            else -> null to Tone.Neutral
        }
        val subtitle = when {
            !item.canBePlaced && placedIn.isNotBlank() -> stringResource(R.string.goodsin_item_in_location, item.name, placedIn)
            pickingLocation != null -> stringResource(R.string.goodsin_item_picking_location, item.name, pickingLocation)
            else -> item.name
        }
        ItemRow(
            code = item.code,
            subtitle = subtitle,
            chip = chip,
            chipTone = tone,
            done = quantityText(item.skoPlaced, item.skoPlacedLabel),
            required = quantityText(item.skoChecked ?: 0.0, item.skoCheckedLabel),
            quantityTone = if (item.canBePlaced) null else Tone.Success,
            imageUrl = item.sko?.imageUrl,
            onClick = if (canPlace && item.canBePlaced) ({ onOpen(item) }) else null,
        )
    }
    HelperNote(text = stringResource(R.string.goodsin_booking_in_note))
}

@Composable
private fun DoneItems(delivery: StockDelivery) {
    val items = delivery.itemsFor(DeliveryView.Done)

    if (items.isEmpty()) {
        HelperNote(text = stringResource(R.string.goodsin_done_nothing))
    }
    ItemList(items = items) { item ->
        val placements = item.placements.map { placement ->
            stringResource(R.string.goodsin_placed_quantity, quantityText(placement.quantity, placement.quantityLabel), placement.location?.code.orEmpty())
        }
        ItemRow(
            code = item.code,
            subtitle = placements.joinToString(LIST_SEPARATOR).ifBlank { item.name },
            chip = item.stateLabel.takeIf { it.isNotBlank() },
            chipTone = if (item.state == StockDeliveryState.CANCELLED) Tone.Danger else Tone.Success,
            done = quantityText(item.skoPlaced, item.skoPlacedLabel),
            required = quantityText(item.skoChecked ?: item.skoExpected, item.skoCheckedLabel ?: item.skoExpectedLabel),
            quantityTone = Tone.Success,
            imageUrl = item.sko?.imageUrl,
        )
    }
    if (delivery.state == StockDeliveryState.BOOKED_IN || delivery.state == StockDeliveryState.PLACED) {
        HelperNote(text = stringResource(R.string.goodsin_booked_in_note))
    }
}

@Composable
private fun ItemList(items: List<StockDeliveryItem>, row: @Composable (StockDeliveryItem) -> Unit) {
    if (items.isEmpty()) {
        return
    }
    ListGroup {
        items.forEachIndexed { index, item ->
            if (index > 0) {
                RowDivider()
            }
            row(item)
        }
    }
}

@Composable
internal fun ChooseLineSheet(code: String, items: List<StockDeliveryItem>, onChoose: (StockDeliveryItem) -> Unit, onDismiss: () -> Unit) {
    AikuBottomSheet(
        title = stringResource(R.string.goodsin_choose_line_title),
        subtitle = pluralStringResource(R.plurals.goodsin_choose_line_subtitle, items.size, code, items.size),
        onDismiss = onDismiss,
    ) {
        items.forEach { item ->
            ChoiceRow(
                title = item.code,
                subtitle = listOfNotNull(
                    item.purchaseOrderReference?.takeIf { it.isNotBlank() },
                    expectedLine(item),
                    item.skoChecked?.let { stringResource(R.string.goodsin_line_checked, quantityText(it, item.skoCheckedLabel)) },
                ).joinToString(LIST_SEPARATOR),
                selected = false,
                onClick = { onChoose(item) },
            )
        }
    }
}
