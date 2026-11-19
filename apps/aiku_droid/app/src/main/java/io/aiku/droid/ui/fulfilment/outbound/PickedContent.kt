package io.aiku.droid.ui.fulfilment.outbound

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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteShipment
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.PalletReturnState
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.inFooter
import io.aiku.droid.ui.components.rememberFileOpener
import io.aiku.droid.ui.fulfilment.FulfilmentRow
import io.aiku.droid.ui.fulfilment.FulfilmentSuccessHero
import io.aiku.droid.ui.goodsin.HelperNote
import io.aiku.droid.ui.goodsout.DeleteShipmentSheet
import io.aiku.droid.ui.goodsout.ReadOnlyParcels
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.momentText
import io.aiku.droid.ui.navigation.FulfilmentReturnsRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ReturnShipmentRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private const val NOT_PICKED_LINE = "not_picked"
private const val CANCELLED_LINE = "cancel"

private sealed interface PickedSheet {
    data object Dispatch : PickedSheet
    data class DeleteShipment(val shipment: DeliveryNoteShipment) : PickedSheet
}

private fun returnFolder(palletReturn: PalletReturn): String = "pallet-return-${palletReturn.id}"

private fun labelFileName(palletReturn: PalletReturn, shipment: DeliveryNoteShipment): String =
    "${palletReturn.reference.ifBlank { palletReturn.id.toString() }}-label-${shipment.id}.pdf"

@Composable
internal fun rememberShipmentHandlers(
    viewModel: PalletReturnViewModel,
    state: PalletReturnUiState,
    palletReturn: PalletReturn,
    canPrint: Boolean,
    canDelete: Boolean,
    onDelete: (DeliveryNoteShipment) -> Unit,
): ShipmentHandlers {
    val opener = rememberFileOpener()
    return ShipmentHandlers(
        openingKey = opener.openingKey,
        printingShipmentId = (state.working as? PalletReturnWrite.PrintLabel)?.shipmentId,
        canPrint = canPrint,
        canDelete = canDelete,
        onOpenLabel = { shipment ->
            opener.open(
                key = shipmentLabelKey(shipment),
                folder = returnFolder(palletReturn),
                fileName = labelFileName(palletReturn, shipment),
                download = { target -> viewModel.downloadLabel(shipment.id, target) },
            )
        },
        onOpenLink = opener::openLink,
        onPrintLabel = { shipment -> viewModel.printLabel(shipment.id) },
        onDelete = onDelete,
    )
}

@Composable
internal fun PickedContent(viewModel: PalletReturnViewModel, state: PalletReturnUiState, palletReturn: PalletReturn) {
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    val can = LocalWarehouse.current.can
    var sheet by remember { mutableStateOf<PickedSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
    }
    val addShipment = palletReturn.action(PalletReturnActionKey.ADD_SHIPMENT)
    val dispatch = palletReturn.action(PalletReturnActionKey.DISPATCH)
    val printInserts = palletReturn.action(PalletReturnActionKey.PRINT_INSERTS)
    val openShipment = {
        viewModel.handOver()
        navigator.open(ReturnShipmentRoute(palletReturn.id, palletReturn.reference))
    }
    val handlers = rememberShipmentHandlers(
        viewModel = viewModel,
        state = state,
        palletReturn = palletReturn,
        canPrint = can.editFulfilment,
        canDelete = true,
        onDelete = { shipment ->
            sheetProblem = null
            sheet = PickedSheet.DeleteShipment(shipment)
        },
    )

    OutboundEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet is PickedSheet.DeleteShipment) ({ sheetProblem = it }) else null,
        doneText = { done -> returnDoneText(done.action, palletReturn, resources) },
        onDone = { closeSheet() },
    )

    AppScreen(
        header = { ReturnHeader(palletReturn = palletReturn) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            val firstShipment = addShipment?.takeIf { palletReturn.shipments.isEmpty() }
            if (firstShipment != null) {
                AikuButton(
                    text = firstShipment.label.ifBlank { stringResource(R.string.goodsout_add_shipment) },
                    onClick = openShipment,
                    style = fulfilmentOutboundActionLook(firstShipment.key).inFooter(isFirst = dispatch == null).style,
                    icon = fulfilmentOutboundActionLook(firstShipment.key).icon,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            when {
                dispatch != null -> AikuButton(
                    text = dispatch.label.ifBlank { stringResource(R.string.fulfilment_return_dispatch) },
                    onClick = {
                        sheetProblem = null
                        sheet = PickedSheet.Dispatch
                    },
                    style = fulfilmentOutboundActionLook(dispatch.key).style,
                    icon = fulfilmentOutboundActionLook(dispatch.key).icon,
                    isLoading = state.working == PalletReturnWrite.Dispatch,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
                palletReturn.dispatchBlocked != null -> BlockedNotice(
                    title = stringResource(R.string.fulfilment_return_dispatch),
                    reason = palletReturn.dispatchBlocked,
                )
                firstShipment == null -> FooterStatus(title = palletReturn.stateLabel)
            }
        },
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh, isEnabled = state.canRefresh),
    ) {
        ReturnRefreshProblem(problem = state.problem, onRetry = viewModel::retry)
        ReturnStateBlock(palletReturn = palletReturn)
        ReturnNotices(palletReturn = palletReturn)
        PickedSummary(palletReturn = palletReturn)
        if (palletReturn.inserts.total > 0) {
            val printButton: (@Composable () -> Unit)? = printInserts?.let { action ->
                @Composable {
                    AikuButton(
                        text = action.label.ifBlank { stringResource(R.string.fulfilment_out_print) },
                        onClick = viewModel::printInserts,
                        style = fulfilmentOutboundActionLook(action.key).style,
                        icon = fulfilmentOutboundActionLook(action.key).icon,
                        isLoading = state.working == PalletReturnWrite.PrintInserts,
                        height = 44.dp,
                    )
                }
            }
            ListGroup {
                FulfilmentRow(
                    title = stringResource(R.string.fulfilment_out_inserts),
                    subtitle = if (palletReturn.inserts.unprinted > 0) {
                        pluralStringResource(R.plurals.fulfilment_out_inserts_unprinted, palletReturn.inserts.unprinted, palletReturn.inserts.unprinted)
                    } else {
                        stringResource(R.string.fulfilment_out_inserts_printed)
                    },
                    leading = { IconTile(icon = R.drawable.ic_ui_print, tone = if (palletReturn.inserts.unprinted > 0) Tone.Warn else Tone.Success) },
                    trailing = printButton,
                )
            }
        }
        ReadOnlyParcels(parcels = palletReturn.parcels.map { it.asParcel() }, withBoxes = false)
        ReturnShipmentsBlock(shipments = palletReturn.shipments, handlers = handlers)
        if (addShipment != null && palletReturn.shipments.isNotEmpty()) {
            AikuButton(
                text = stringResource(R.string.goodsout_add_another_shipment),
                onClick = openShipment,
                style = ButtonStyle.Soft,
                icon = R.drawable.ic_ui_plus,
                height = 48.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        ReturnDetails(palletReturn = palletReturn)
        HelperNote(text = stringResource(R.string.fulfilment_out_picked_note))
    }

    when (val current = sheet) {
        PickedSheet.Dispatch -> DispatchDialog(palletReturn = palletReturn, onConfirm = {
            sheet = null
            viewModel.dispatch()
        }, onDismiss = closeSheet)
        is PickedSheet.DeleteShipment -> DeleteShipmentSheet(
            shipment = current.shipment,
            isWorking = state.working is PalletReturnWrite.DeleteShipment,
            problem = sheetProblem,
            onConfirm = { viewModel.deleteShipment(current.shipment.id) },
            onDismiss = closeSheet,
        )
        null -> Unit
    }
}

@Composable
private fun PickedSummary(palletReturn: PalletReturn) {
    val colors = LocalAikuColors.current
    val lines = if (palletReturn.isItems) {
        val picked = palletReturn.storedItems.sumOf { it.quantityPicked }
        val notPicked = palletReturn.storedItems.sumOf { it.quantityNotPicked }
        listOfNotNull(
            pluralStringResource(R.plurals.fulfilment_out_items_picked, picked, picked),
            notPicked.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.fulfilment_out_not_picked_count, it, it) },
        )
    } else {
        val picked = palletReturn.pallets.filter { it.lineState == PICKED_LINE }.map { it.pallet.reference }
        val notPicked = palletReturn.pallets.count { it.lineState == NOT_PICKED_LINE || it.waitingCustomerService }
        listOfNotNull(
            pluralStringResource(R.plurals.fulfilment_return_picked_pallets, picked.size, picked.size, picked.joinToString(", ")),
            notPicked.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.fulfilment_out_not_picked_count, it, it) },
        )
    }
    val isComplete = if (palletReturn.isItems) {
        palletReturn.storedItems.all { it.quantityNotPicked == 0 }
    } else {
        palletReturn.pallets.none { it.lineState == NOT_PICKED_LINE || it.waitingCustomerService }
    }

    AikuCard {
        Text(
            text = if (isComplete) stringResource(R.string.fulfilment_return_all_picked) else palletReturn.stateLabel,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
        )
        lines.forEach { Text(text = it, fontSize = 14.sp, color = colors.textTertiary) }
    }
}

@Composable
internal fun DispatchedContent(viewModel: PalletReturnViewModel, state: PalletReturnUiState, palletReturn: PalletReturn) {
    val navigator = LocalAppNavigator.current
    val zone = organisationZone()
    val handlers = rememberShipmentHandlers(viewModel = viewModel, state = state, palletReturn = palletReturn, canPrint = false, canDelete = false, onDelete = {})
    val isDispatched = palletReturn.state == PalletReturnState.DISPATCHED

    AppScreen(
        header = { ReturnHeader(palletReturn = palletReturn) },
        activeTab = MainTab.Menu,
        showBar = true,
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh, isEnabled = state.canRefresh),
    ) {
        ReturnRefreshProblem(problem = state.problem, onRetry = viewModel::retry)
        ReturnStateBlock(palletReturn = palletReturn)
        if (isDispatched) {
            val body = if (palletReturn.isItems) {
                val items = palletReturn.storedItems.sumOf { it.quantityPicked }
                pluralStringResource(R.plurals.fulfilment_out_items_dispatched, items, items)
            } else {
                val pallets = palletReturn.pallets.count { it.lineState != NOT_PICKED_LINE && it.lineState != CANCELLED_LINE }
                pluralStringResource(R.plurals.fulfilment_return_dispatched_body, pallets, pallets)
            }
            FulfilmentSuccessHero(
                title = stringResource(R.string.fulfilment_return_dispatched_title, palletReturn.reference),
                body = listOfNotNull(
                    body,
                    momentText(palletReturn.timelineAt(PalletReturnState.DISPATCHED), zone)?.let { stringResource(R.string.fulfilment_out_dispatched_at, it) },
                ).joinToString("\n"),
            )
        }
        ReadOnlyParcels(parcels = palletReturn.parcels.map { it.asParcel() }, withBoxes = false)
        ReturnShipmentsBlock(shipments = palletReturn.shipments, handlers = handlers)
        ReturnDetails(palletReturn = palletReturn)
        if (isDispatched && palletReturn.platform != null) {
            HelperNote(text = stringResource(R.string.fulfilment_return_dispatched_footnote))
        }
        AikuButton(
            text = stringResource(R.string.fulfilment_return_next),
            onClick = { navigator.switchSection(FulfilmentReturnsRoute) },
            icon = R.drawable.ic_ui_next,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
