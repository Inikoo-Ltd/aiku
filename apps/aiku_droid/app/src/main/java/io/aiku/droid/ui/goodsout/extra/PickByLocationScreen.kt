package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.PickListEntry
import io.aiku.droid.data.api.PickListLine
import io.aiku.droid.data.api.PickingSession
import io.aiku.droid.data.api.SessionActionKey
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.EmptyTone
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ScanPanel
import io.aiku.droid.ui.components.ScanPanelItem
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.DeliveryNoteEvents
import io.aiku.droid.ui.goodsout.DeliveryNoteViewModel
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.LineActionsSheet
import io.aiku.droid.ui.goodsout.ScanLog
import io.aiku.droid.ui.goodsout.ScanToPickCamera
import io.aiku.droid.ui.goodsout.canWorkLines
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.goodsout.pluralQuantity
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlin.math.floor

@Composable
fun PickByLocationScreen(session: PickingSessionViewModel, reference: String, rememberNote: @Composable (Long) -> DeliveryNoteViewModel) {
    val state by session.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { session.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.session
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = session::signInAgain)
            loaded != null -> PickByLocationContent(viewModel = session, state = state, session = loaded, rememberNote = rememberNote)
            else -> SessionLoading(title = stringResource(R.string.goodsout_extra_pick_by_location), kicker = reference, problem = state.problem, onRetry = session::retry)
        }
    }
}

@Composable
private fun PickByLocationContent(
    viewModel: PickingSessionViewModel,
    state: PickingSessionUiState,
    session: PickingSession,
    rememberNote: @Composable (Long) -> DeliveryNoteViewModel,
) {
    val navigator = LocalAppNavigator.current
    val canScan = session.action(SessionActionKey.SCAN_PICK)?.takeIf { !it.isDisabled } != null
    val current = state.currentPick
    val next = state.nextPick
    val target = state.scanTarget?.takeIf { canScan }
    var isCameraOn by remember { mutableStateOf(false) }
    var manualLine by remember { mutableStateOf<PickListLine?>(null) }

    SessionEvents(event = state.event, onSheetProblem = null, onActed = {}, onHandled = viewModel::eventHandled, onFinished = navigator::back)
    ScanEffect(enabled = canScan && manualLine == null) { scan -> viewModel.scanToPick(scan.code) }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.goodsout_extra_pick_by_location),
                kicker = listOf(
                    session.reference,
                    sessionStateLabel(session.state, session.stateLabel),
                    stringResource(R.string.goodsout_extra_percent_picked, percent(session.pickingPercentage)),
                ).joinToString(KICKER_SEPARATOR),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = next?.let {
            {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_next_location),
                    onClick = viewModel::nextLocation,
                    style = ButtonStyle.Outline,
                    icon = R.drawable.ic_ui_chevron_right,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
        bottomPanel = target?.let { scanned ->
            {
                SessionPickPanel(
                    target = scanned,
                    imageUrl = session.pickOf(scanned.lineId)?.orgStock?.imageUrl,
                    isWorking = state.queuedScans > 0,
                    onPickAll = viewModel::pickAllScanned,
                    onPickAmount = viewModel::pickScannedAmount,
                    onClose = viewModel::closeScanPanel,
                )
            }
        },
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsout_extra_session_refresh_failed), onRetry = viewModel::retry) }
        if (current == null) {
            EmptyState(
                icon = R.drawable.ic_ui_check,
                title = stringResource(R.string.goodsout_extra_session_nothing_to_pick_title),
                body = stringResource(R.string.goodsout_extra_session_nothing_to_pick_body),
                tone = EmptyTone.Success,
            )
        } else {
            if (!canScan) {
                InlineAlert(
                    title = stringResource(R.string.goodsout_extra_session_scan_off_title),
                    body = stringResource(R.string.goodsout_extra_session_scan_off_body),
                    tone = AlertTone.Info,
                )
            }
            LocationCard(entry = current, isScanning = canScan, queuedScans = state.queuedScans)
            if (canScan) {
                ScanToPickCamera(isOn = isCameraOn, onToggle = { isCameraOn = !isCameraOn })
            }
            SectionHeading(text = stringResource(R.string.goodsout_extra_put_into))
            ListGroup {
                current.lines.forEachIndexed { index, line ->
                    if (index > 0) {
                        RowDivider()
                    }
                    PutIntoRow(line = line, onClick = { manualLine = line })
                }
            }
        }
        next?.let { upcoming ->
            SectionHeading(text = stringResource(R.string.goodsout_extra_next_location))
            ListGroup {
                ExtraRow(
                    title = upcoming.location?.code?.takeIf { it.isNotBlank() } ?: upcoming.orgStock?.code.orEmpty(),
                    subtitle = pickDetail(upcoming),
                    leading = { ExtraIconTile(icon = R.drawable.ic_ui_location, tone = Tone.Neutral) },
                    onClick = viewModel::nextLocation,
                )
            }
        }
        ScanLog(entries = state.scanLog, notFoundLabel = stringResource(R.string.goodsout_extra_scan_not_in_session))
        HintText(text = stringResource(if (canScan) R.string.goodsout_extra_pick_by_location_hint else R.string.goodsout_extra_pick_by_hand_hint))
    }
    manualLine?.let { line ->
        ManualPickSheet(
            note = rememberNote(line.deliveryNoteId),
            line = line,
            onPicked = viewModel::retry,
            onDismiss = { manualLine = null },
        )
    }
}

@Composable
private fun LocationCard(entry: PickListEntry, isScanning: Boolean, queuedScans: Int) {
    val colors = LocalAikuColors.current
    val sko = entry.orgStock
    val code = sko?.code.orEmpty()

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.scan)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(18.dp))
            Text(
                text = stringResource(if (isScanning) R.string.goodsout_extra_scan_to_pick else R.string.goodsout_extra_pick_by_hand),
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = colors.onScan,
            )
        }
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            ProductPhoto(code = code, size = 64.dp, imageUrl = sko?.imageUrl)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = stringResource(R.string.goodsout_extra_take_from_location), fontSize = 13.sp, color = colors.onScan)
                Text(text = entry.location?.code?.takeIf { it.isNotBlank() } ?: code, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
                Text(text = listOf(code, sko?.name.orEmpty()).filter { it.isNotBlank() }.joinToString(KICKER_SEPARATOR), fontSize = 13.sp, color = colors.onScan)
            }
        }
        Text(
            text = listOfNotNull(
                pluralStringResource(R.plurals.goodsout_extra_to_pick, pluralQuantity(entry.quantityToPick), quantityText(entry.quantityToPick, entry.quantityToPickLabel)),
                if (queuedScans > 0) pluralStringResource(R.plurals.goodsout_scan_queued, queuedScans, queuedScans) else null,
            ).joinToString(KICKER_SEPARATOR),
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = colors.onScan,
        )
    }
}

@Composable
private fun PutIntoRow(line: PickListLine, onClick: () -> Unit) {
    val tone = when {
        line.quantityToPick <= 0.0 -> Tone.Success
        line.quantityPicked > 0.0 -> Tone.Primary
        else -> null
    }

    ItemRow(
        code = stringResource(R.string.goodsout_extra_delivery_note_reference, line.deliveryNoteReference),
        subtitle = if (line.quantityToPick > 0.0) {
            pluralStringResource(R.plurals.goodsout_extra_to_pick, pluralQuantity(line.quantityToPick), quantityText(line.quantityToPick))
        } else {
            stringResource(R.string.goodsout_line_picked)
        },
        done = quantityText(line.quantityPicked),
        required = quantityText(line.quantityRequired),
        quantityTone = tone,
        showPhoto = false,
        onClick = onClick,
    )
}

@Composable
private fun SessionPickPanel(
    target: SessionScanTarget,
    imageUrl: String?,
    isWorking: Boolean,
    onPickAll: () -> Unit,
    onPickAmount: (Int) -> Unit,
    onClose: () -> Unit,
) {
    val remainingText = quantityText(target.remaining, target.remainingLabel)
    val maxAmount = maxOf(1, floor(target.remaining).toInt())
    var amount by remember(target.lineId) { mutableIntStateOf(1) }
    val shownAmount = amount.coerceIn(1, maxAmount)

    ScanPanel(
        item = ScanPanelItem(
            code = target.code,
            name = target.name,
            location = listOfNotNull(target.locationCode, target.deliveryNoteReference).filter { it.isNotBlank() }.joinToString(KICKER_SEPARATOR),
            imageUrl = imageUrl,
        ),
        countLabel = stringResource(R.string.goodsout_scan_picked_chip, quantityText(target.done)),
        remainingLabel = pluralStringResource(R.plurals.goodsout_left_on_item_label, pluralQuantity(target.remaining), remainingText),
        allLabel = stringResource(R.string.goodsout_line_pick_all, remainingText),
        onAll = onPickAll,
        amount = shownAmount,
        onAmountChange = { amount = it.coerceIn(1, maxAmount) },
        amountLabel = stringResource(R.string.goodsout_pick_amount, shownAmount),
        onAmount = { onPickAmount(shownAmount) },
        hint = stringResource(R.string.goodsout_scan_panel_hint),
        maxAmount = maxAmount,
        isWorking = isWorking,
        onClose = onClose,
    )
}

@Composable
private fun ManualPickSheet(note: DeliveryNoteViewModel, line: PickListLine, onPicked: () -> Unit, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val state by note.state.collectAsStateWithLifecycle()
    var problem by remember { mutableStateOf<String?>(null) }
    val loaded = state.note
    val noteLine = loaded?.line(line.deliveryNoteItemId)
    val isReadOnly = loaded != null && !state.isLoading && !loaded.canWorkLines

    LaunchedEffect(Unit) { note.retry() }
    LaunchedEffect(isReadOnly) {
        if (isReadOnly) {
            onDismiss()
            navigator.open(deliveryNoteRoute(id = loaded.id, reference = loaded.reference, state = loaded.state))
        }
    }
    DeliveryNoteEvents(
        event = state.event,
        onSheetProblem = { problem = it },
        onActed = {
            onPicked()
            onDismiss()
        },
        onHandled = note::eventHandled,
        followsRoute = false,
    )

    if (loaded != null && noteLine != null && loaded.canWorkLines) {
        LineActionsSheet(note = loaded, line = noteLine, isWorking = state.isWorking, problem = problem, onAct = note::act, onDismiss = onDismiss)
        return
    }
    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_delivery_note_reference, line.deliveryNoteReference),
        onDismiss = onDismiss,
    ) {
        val loadProblem = state.problem
        if (loadProblem == null) {
            SkeletonListCard(rows = 3)
        } else {
            GoodsOutLoadProblem(problem = loadProblem, title = stringResource(R.string.goodsout_note_failed_title, line.deliveryNoteReference), onRetry = note::retry)
        }
    }
}
