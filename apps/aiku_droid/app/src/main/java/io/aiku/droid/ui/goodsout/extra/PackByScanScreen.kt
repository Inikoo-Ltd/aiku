package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.PickingSession
import io.aiku.droid.data.api.SessionActionKey
import io.aiku.droid.data.api.SessionDeliveryNote
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ScanPanel
import io.aiku.droid.ui.components.ScanPanelItem
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.CANCELLED_STATE
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.ScanHintCard
import io.aiku.droid.ui.goodsout.ScanLog
import io.aiku.droid.ui.goodsout.ScanToPickCamera
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.goodsout.pluralQuantity
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.goodsout.stageOf
import io.aiku.droid.ui.goodsout.stageTone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import kotlin.math.floor

private val packedStates = setOf(DeliveryNoteBucket.Packed.key, DeliveryNoteBucket.Finalised.key, DeliveryNoteBucket.Dispatched.key, CANCELLED_STATE)

private fun packingTone(note: SessionDeliveryNote): Tone? = when {
    note.state in packedStates -> Tone.Success
    note.packingPercentage > 0.0 -> Tone.Primary
    else -> null
}

@Composable
fun PackByScanScreen(session: PickingSessionViewModel, reference: String) {
    val state by session.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { session.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.session
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = session::signInAgain)
            loaded != null -> PackByScanContent(viewModel = session, state = state, session = loaded)
            else -> SessionLoading(title = stringResource(R.string.goodsout_extra_pack_by_scan), kicker = reference, problem = state.problem, onRetry = session::retry)
        }
    }
}

@Composable
private fun PackByScanContent(viewModel: PickingSessionViewModel, state: PickingSessionUiState, session: PickingSession) {
    val navigator = LocalAppNavigator.current
    val canScan = session.action(SessionActionKey.SCAN_PACK)?.takeIf { !it.isDisabled } != null
    val target = state.scanTarget?.takeIf { canScan }
    val nextNote = session.deliveryNotes.firstOrNull { it.state !in packedStates }
    var isCameraOn by remember { mutableStateOf(false) }

    SessionEvents(event = state.event, onSheetProblem = null, onActed = {}, onHandled = viewModel::eventHandled, onFinished = navigator::back)
    ScanEffect(enabled = canScan) { scan -> viewModel.scanToPack(scan.code) }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.goodsout_extra_pack_by_scan),
                kicker = listOf(
                    session.reference,
                    sessionStateLabel(session.state, session.stateLabel),
                    stringResource(R.string.goodsout_extra_packing_percent, percent(session.packingPercentage)),
                ).joinToString(KICKER_SEPARATOR),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        bottomPanel = target?.let { scanned ->
            {
                SessionPackPanel(
                    target = scanned,
                    isWorking = state.queuedScans > 0,
                    onPackAll = viewModel::packAllScanned,
                    onPackAmount = viewModel::packScannedAmount,
                    onClose = viewModel::closeScanPanel,
                )
            }
        },
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsout_extra_session_refresh_failed), onRetry = viewModel::retry) }
        if (canScan) {
            ScanHintCard(
                title = stringResource(R.string.goodsout_extra_scan_to_pack),
                caption = stringResource(R.string.goodsout_scan_listening),
                value = nextNote?.let { stringResource(R.string.goodsout_extra_delivery_note_reference, it.reference) } ?: stringResource(R.string.goodsout_extra_all_packed),
                detail = listOfNotNull(
                    nextNote?.let { note ->
                        listOf(
                            pluralStringResource(R.plurals.goodsout_extra_items, note.numberItems, note.numberItems),
                            stringResource(R.string.goodsout_extra_packing_percent, percent(note.packingPercentage)),
                        ).joinToString(KICKER_SEPARATOR)
                    },
                    if (state.queuedScans > 0) pluralStringResource(R.plurals.goodsout_scan_queued, state.queuedScans, state.queuedScans) else null,
                ).joinToString(KICKER_SEPARATOR),
            )
            ScanToPickCamera(isOn = isCameraOn, onToggle = { isCameraOn = !isCameraOn })
        } else {
            InlineAlert(
                title = stringResource(R.string.goodsout_extra_session_pack_off_title),
                body = stringResource(R.string.goodsout_extra_session_pack_off_body),
                tone = AlertTone.Info,
            )
        }
        SectionHeading(text = stringResource(R.string.section_delivery_notes))
        ListGroup {
            session.deliveryNotes.forEachIndexed { index, note ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = note.reference,
                    subtitle = listOfNotNull(
                        pluralStringResource(R.plurals.goodsout_extra_items, note.numberItems, note.numberItems),
                        if (note.isForCollection) stringResource(R.string.goodsout_collection) else null,
                    ).joinToString(KICKER_SEPARATOR),
                    chip = note.stateLabel.ifBlank { note.state },
                    chipTone = stageTone(stageOf(note.state)),
                    done = stringResource(R.string.goodsout_extra_percent, percent(note.packingPercentage)),
                    quantityTone = packingTone(note),
                    showPhoto = false,
                    onClick = { navigator.open(deliveryNoteRoute(id = note.id, reference = note.reference, state = note.state)) },
                )
            }
        }
        ScanLog(entries = state.scanLog, notFoundLabel = stringResource(R.string.goodsout_extra_scan_not_in_session))
        HintText(text = stringResource(R.string.goodsout_extra_pack_by_scan_hint))
    }
}

@Composable
private fun SessionPackPanel(target: SessionScanTarget, isWorking: Boolean, onPackAll: () -> Unit, onPackAmount: (Int) -> Unit, onClose: () -> Unit) {
    val remainingText = quantityText(target.remaining, target.remainingLabel)
    val maxAmount = maxOf(1, floor(target.remaining).toInt())
    var amount by remember(target.lineId) { mutableIntStateOf(1) }
    val shownAmount = amount.coerceIn(1, maxAmount)

    ScanPanel(
        item = ScanPanelItem(code = target.code, name = target.name, location = target.deliveryNoteReference),
        countLabel = stringResource(R.string.goodsout_scan_packed_chip, quantityText(target.done)),
        remainingLabel = pluralStringResource(R.plurals.goodsout_left_to_pack_label, pluralQuantity(target.remaining), remainingText),
        allLabel = stringResource(R.string.goodsout_pack_all, remainingText),
        onAll = onPackAll,
        amount = shownAmount,
        onAmountChange = { amount = it.coerceIn(1, maxAmount) },
        amountLabel = stringResource(R.string.goodsout_pack_amount, shownAmount),
        onAmount = { onPackAmount(shownAmount) },
        hint = stringResource(R.string.goodsout_scan_panel_hint),
        maxAmount = maxAmount,
        isWorking = isWorking,
        onClose = onClose,
    )
}
