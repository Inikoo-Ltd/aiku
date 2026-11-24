package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.PalletReturnState
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.data.api.StoredItemAuditActionKey
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.NoteCard
import io.aiku.droid.ui.components.NoteKind
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Step
import io.aiku.droid.ui.components.StepStatus
import io.aiku.droid.ui.fulfilment.dotJoined
import io.aiku.droid.ui.goodsin.goodsInDate
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

internal val fulfilmentOutboundActionLooks: Map<String, ButtonLook> = mapOf(
    PalletReturnActionKey.START_PICKING to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
    PalletReturnActionKey.PICK to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
    PalletReturnActionKey.ADD_SHIPMENT to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_truck),
    PalletReturnActionKey.FINISH_PICKING to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
    PalletReturnActionKey.DISPATCH to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
    PalletReturnActionKey.PRINT_INSERTS to ButtonLook(ButtonStyle.Soft, R.drawable.ic_ui_print),
    PalletReturnActionKey.UNDO to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
    PalletReturnActionKey.NOT_PICKED to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_x_circle),
    StoredItemAuditActionKey.COUNT to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_check),
    StoredItemAuditActionKey.COMPLETE to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
)

internal fun fulfilmentOutboundActionLook(key: String): ButtonLook = fulfilmentOutboundActionLooks[key] ?: ButtonLook(ButtonStyle.Outline)

private val stageLabels = mapOf(
    PalletReturnState.CONFIRMED to R.string.fulfilment_state_confirmed,
    PalletReturnState.PICKING to R.string.fulfilment_state_picking,
    PalletReturnState.PICKED to R.string.fulfilment_state_picked,
    PalletReturnState.DISPATCHED to R.string.fulfilment_state_dispatched,
)

@Composable
internal fun rememberPalletReturn(container: AppContainer, warehouseId: Long, returnId: Long, purpose: String): PalletReturnViewModel = viewModel(
    key = "pallet-return-$purpose-$warehouseId-$returnId",
    factory = viewModelFactory { initializer { PalletReturnViewModel(container.fulfilmentOutboundApi, container.sessions, warehouseId, returnId) } },
)

@Composable
internal fun PalletReturnFrame(
    viewModel: PalletReturnViewModel,
    reference: String,
    screen: ReturnScreen?,
    content: @Composable (PalletReturnUiState, PalletReturn) -> Unit,
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val loaded = state.palletReturn

    LifecycleEventEffect(Lifecycle.Event.ON_START) { viewModel.onStart() }

    when {
        state.isSessionExpired -> SessionExpiredScreen(onSignIn = viewModel::signInAgain)
        loaded != null -> {
            content(state, loaded)
            if (screen != null) {
                FollowReturnState(palletReturn = loaded, screen = screen, onHandOver = viewModel::handOver)
            }
        }
        else -> ReturnLoading(
            reference = reference,
            problem = state.problem,
            onRetry = viewModel::retry,
            refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh),
        )
    }
}

@Composable
private fun FollowReturnState(palletReturn: PalletReturn, screen: ReturnScreen, onHandOver: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val target = palletReturn.screen

    LaunchedEffect(target) {
        if (target != screen) {
            onHandOver()
            navigator.switchSection(palletReturn.route())
        }
    }
}

@Composable
private fun ReturnLoading(reference: String, problem: GoodsOutProblem?, onRetry: () -> Unit, refresh: PullRefresh) {
    AppScreen(
        header = { AppHeader(title = reference, kicker = stringResource(R.string.fulfilment_return_kicker)) },
        activeTab = MainTab.Menu,
        showBar = true,
        refresh = refresh,
    ) {
        if (problem == null) {
            SkeletonListCard(rows = 2)
            SkeletonListCard(rows = 4)
        } else {
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_out_return_failed, reference), onRetry = onRetry)
        }
    }
}

@Composable
internal fun ReturnHeader(palletReturn: PalletReturn, title: String = palletReturn.reference) {
    AppHeader(
        title = title,
        kicker = dotJoined(
            stringResource(R.string.fulfilment_return_kicker),
            palletReturn.typeLabel.ifBlank { stringResource(if (palletReturn.isItems) R.string.fulfilment_return_type_stored_items else R.string.fulfilment_return_type_pallets) },
            palletReturn.customer?.name,
        ),
    )
}

@Composable
internal fun ReturnStateBlock(palletReturn: PalletReturn) {
    if (palletReturn.state == PalletReturnState.CANCEL) {
        InlineAlert(
            title = palletReturn.stateLabel.ifBlank { stringResource(R.string.fulfilment_out_state_cancelled) },
            body = stringResource(R.string.fulfilment_out_cancelled_body),
            tone = AlertTone.Danger,
        )
        return
    }
    val current = returnStages.indexOf(palletReturn.state)
    StateStepper(
        steps = returnStages.mapIndexed { index, stage ->
            Step(
                label = stringResource(stageLabels.getValue(stage)),
                status = when {
                    index < current || (index == current && stage == PalletReturnState.DISPATCHED) -> StepStatus.Done
                    index == current -> StepStatus.Current
                    else -> StepStatus.Upcoming
                },
            )
        },
    )
}

@Composable
internal fun ReturnNotices(palletReturn: PalletReturn) {
    if (palletReturn.inPickingSession) {
        InlineAlert(title = stringResource(R.string.fulfilment_out_in_session_title), body = stringResource(R.string.fulfilment_out_in_session_body), tone = AlertTone.Info)
    }
    if (palletReturn.isCollection) {
        InlineAlert(title = stringResource(R.string.fulfilment_return_collects), body = stringResource(R.string.fulfilment_return_collects_hint), tone = AlertTone.Info)
    }
}

@Composable
internal fun ReturnRefreshProblem(problem: GoodsOutProblem?, onRetry: () -> Unit) {
    problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.fulfilment_out_refresh_failed), onRetry = onRetry) }
}

@Composable
internal fun ReturnUnsureAlert(state: PalletReturnUiState, onRetry: () -> Unit) {
    val unsure = state.unsure ?: return
    val body = when (unsure) {
        is ReturnTask.Scan -> stringResource(R.string.fulfilment_out_unsure_scan, unsure.request.barcode)
        is ReturnTask.Write -> when (unsure.write) {
            is PalletReturnWrite.PickFromOtherPallet -> stringResource(R.string.fulfilment_out_unsure_other_pallet)
            else -> stringResource(R.string.fulfilment_out_unsure_pick)
        }
        is ReturnTask.CreateShipment -> stringResource(R.string.fulfilment_out_unsure_pick)
    }
    InlineAlert(
        title = stringResource(R.string.fulfilment_out_unsure_title),
        body = body,
        tone = AlertTone.Warn,
        actionLabel = stringResource(R.string.goodsout_try_again),
        onAction = onRetry,
    )
}

@Composable
internal fun ReturnDetails(palletReturn: PalletReturn) {
    val zone = organisationZone()
    val lines = listOfNotNull(
        palletReturn.customer?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        palletReturn.customerReference?.takeIf { it.isNotBlank() }?.let {
            DetailLine(icon = R.drawable.ic_ui_document, text = stringResource(R.string.fulfilment_out_customer_reference, it))
        },
        palletReturn.platform?.label?.takeIf { it.isNotBlank() }?.let {
            DetailLine(icon = R.drawable.ic_ui_order, text = stringResource(R.string.fulfilment_out_platform, it))
        },
        palletReturn.picker?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = stringResource(R.string.fulfilment_out_picker, it)) },
        goodsInDate(palletReturn.estimatedDeliveryDate, zone)?.let { DetailLine(icon = R.drawable.ic_ui_clock, text = stringResource(R.string.fulfilment_out_estimated_delivery, it)) },
    )
    if (lines.isNotEmpty()) {
        DetailCard(title = stringResource(R.string.fulfilment_out_details), lines = lines)
    }
    listOf(
        Triple(R.string.fulfilment_out_customer_notes, palletReturn.customerNotes, NoteKind.FulfilmentCustomer),
        Triple(R.string.fulfilment_out_public_notes, palletReturn.publicNotes, NoteKind.FulfilmentPublic),
        Triple(R.string.fulfilment_out_internal_notes, palletReturn.internalNotes, NoteKind.FulfilmentPrivate),
        Triple(R.string.fulfilment_out_collection_notes, palletReturn.collectionNotes, NoteKind.Plain),
    ).forEach { (title, text, kind) ->
        if (!text.isNullOrBlank()) {
            NoteCard(title = stringResource(title), text = text, icon = R.drawable.ic_ui_document, kind = kind)
        }
    }
}

@Composable
internal fun BlockedNotice(title: String, reason: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier.fillMaxWidth().heightIn(min = 44.dp).semantics(mergeDescendants = true) {},
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_lock), contentDescription = null, tint = colors.warn, modifier = Modifier.size(20.dp))
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = reason, fontSize = 13.sp, color = colors.warnInk)
        }
    }
}
