package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.ScanPackStatus
import io.aiku.droid.data.api.ScanPickStatus
import io.aiku.droid.data.api.ScanStatus
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ScanPanel
import io.aiku.droid.ui.components.ScanPanelItem
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.BarcodeSet
import io.aiku.droid.ui.scan.CameraBarcodePreview
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.Scan
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.scan.ScanSource
import io.aiku.droid.ui.scan.rememberCameraAccess
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.format.DateTimeFormatter
import java.util.Locale
import kotlin.math.floor

private const val SCAN_TIME_PATTERN = "HH:mm:ss"
private val CameraShape = RoundedCornerShape(12.dp)

@Composable
fun ScanToPickScreen(reference: String, note: DeliveryNoteViewModel?) {
    if (note == null) {
        SampleScanToPick(reference = reference)
        return
    }
    val state by note.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { note.onStart() }

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.note
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = note::signInAgain)
            loaded != null -> ScanToPickContent(viewModel = note, state = state, note = loaded)
            else -> AppScreen(
                header = { AppHeader(title = stringResource(R.string.goodsout_scan_to_pick), kicker = stringResource(R.string.goodsout_delivery_note_reference, reference)) },
                activeTab = MainTab.Menu,
                showBar = true,
            ) {
                val problem = state.problem
                if (problem == null) {
                    SkeletonListCard(rows = 3)
                } else {
                    GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_note_failed_title, reference), onRetry = note::retry)
                }
            }
        }
    }
}

@Composable
private fun ScanToPickContent(viewModel: DeliveryNoteViewModel, state: DeliveryNoteUiState, note: DeliveryNote) {
    val navigator = LocalAppNavigator.current
    val canScan = note.canScanToPick
    val target = state.scanTarget?.takeIf { canScan }
    val next = note.nextLineToPick
    var isCameraOn by remember { mutableStateOf(false) }

    DeliveryNoteEvents(
        event = state.event,
        onSheetProblem = null,
        onActed = {},
        onHandled = viewModel::eventHandled,
        onLineDone = { done -> if (done.isEverythingPicked) navigator.back() },
    )
    ScanEffect(enabled = canScan) { scan -> viewModel.scanToPick(scan.code) }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.goodsout_scan_to_pick),
                kicker = pluralStringResource(R.plurals.goodsout_scan_kicker, note.lineCounts.all, note.reference, note.lineCounts.done, note.lineCounts.all),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        bottomPanel = target?.let {
            {
                ScanToPickPanel(
                    note = note,
                    target = it,
                    isWorking = state.queuedScans > 0,
                    onPickAll = viewModel::pickAllScanned,
                    onPickAmount = viewModel::pickScannedAmount,
                    onClose = viewModel::closeScanPanel,
                )
            }
        },
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsout_note_refresh_failed_title), onRetry = viewModel::retry) }
        if (!canScan) {
            InlineAlert(title = stringResource(R.string.goodsout_scan_unavailable_title), body = stringResource(R.string.goodsout_scan_unavailable_body), tone = AlertTone.Info)
        } else {
            val code = next?.orgStock?.code.orEmpty()
            ScanHintCard(
                title = stringResource(R.string.goodsout_scan_listening),
                caption = stringResource(R.string.goodsout_scan_take_from),
                value = next?.defaultLocation()?.code?.takeIf { it.isNotBlank() } ?: code.ifBlank { stringResource(R.string.goodsout_all_lines_handled) },
                detail = listOfNotNull(
                    next?.let { line ->
                        stringResource(
                            R.string.goodsout_scan_card_detail,
                            code,
                            pluralStringResource(R.plurals.goodsout_left_on_item_label, pluralQuantity(line.quantityToPick), quantityText(line.quantityToPick, line.quantityToPickLabel)),
                        )
                    },
                    if (state.queuedScans > 0) pluralStringResource(R.plurals.goodsout_scan_queued, state.queuedScans, state.queuedScans) else null,
                ).joinToString(" · ").ifBlank { stringResource(R.string.goodsout_scan_nothing_left) },
            )
            ScanToPickCamera(isOn = isCameraOn, onToggle = { isCameraOn = !isCameraOn })
        }
        ScanLog(entries = state.scanLog)
        GoodsOutFootNote(text = stringResource(R.string.goodsout_scan_bluetooth_note))
    }
}

@Composable
internal fun ScanToPickCamera(isOn: Boolean, onToggle: () -> Unit) {
    val scans = LocalScanController.current

    AikuButton(
        text = stringResource(if (isOn) R.string.goodsout_scan_camera_off else R.string.goodsout_scan_camera_on),
        onClick = onToggle,
        style = ButtonStyle.Outline,
        icon = R.drawable.ic_ui_scan,
        modifier = Modifier.fillMaxWidth(),
    )
    if (!isOn) {
        return
    }
    val access = rememberCameraAccess()
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier.fillMaxWidth().height(220.dp).clip(CameraShape),
    ) {
        if (access.isAllowed) {
            CameraBarcodePreview(
                barcodes = BarcodeSet.Warehouse,
                onScanned = { code -> scans.emit(Scan(code = code, source = ScanSource.Camera)) },
                modifier = Modifier.fillMaxSize(),
            )
        } else if (access.isRefused) {
            Text(
                text = stringResource(R.string.scan_camera_off_body),
                fontSize = 14.sp,
                color = colors.textTertiary,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(16.dp),
            )
        }
    }
}

@Composable
internal fun ScanLog(entries: List<ScanLogEntry>, notFoundLabel: String = stringResource(R.string.goodsout_scan_not_on_note)) {
    if (entries.isEmpty()) {
        return
    }
    val zone = organisationZone()
    val formatter = remember(zone) { DateTimeFormatter.ofPattern(SCAN_TIME_PATTERN, Locale.getDefault()).withZone(zone) }

    GoodsOutHeading(title = stringResource(R.string.goodsout_scanned))
    ListGroup {
        entries.forEachIndexed { index, entry ->
            if (index > 0) {
                RowDivider()
            }
            val (chip, tone) = scanLogChip(entry, notFoundLabel)
            ItemRow(
                code = entry.code,
                subtitle = listOfNotNull(formatter.format(entry.at), entry.message).joinToString(" · "),
                chip = chip,
                chipTone = tone,
                showPhoto = false,
            )
        }
    }
}

@Composable
private fun scanLogChip(entry: ScanLogEntry, notFoundLabel: String): Pair<String, Tone> = when (entry.status) {
    ScanPickStatus.PICKED, ScanPackStatus.PACKED -> stringResource(R.string.goodsout_scan_plus, quantityText(entry.quantity)) to Tone.Success
    ScanPickStatus.ALREADY_PICKED, ScanPackStatus.ALREADY_PACKED, ScanPackStatus.NOTHING_TO_PACK -> stringResource(R.string.goodsout_scan_nothing_left_chip) to Tone.Warn
    ScanPickStatus.NO_STOCK -> stringResource(R.string.goodsout_scan_no_stock) to Tone.Danger
    ScanPickStatus.NOT_FOUND -> notFoundLabel to Tone.Danger
    ScanStatus.NOT_SCANNABLE -> stringResource(R.string.goodsout_scan_not_scannable) to Tone.Danger
    else -> stringResource(R.string.goodsout_scan_could_not_pick) to Tone.Danger
}

@Composable
internal fun ScanToPickPanel(
    note: DeliveryNote,
    target: ScanTarget,
    isWorking: Boolean,
    onPickAll: () -> Unit,
    onPickAmount: (Int) -> Unit,
    onClose: () -> Unit,
) {
    val line = note.line(target.lineId) ?: return
    val remaining = line.quantityToPick
    val remainingText = quantityText(remaining, line.quantityToPickLabel)
    val maxAmount = maxOf(1, floor(remaining).toInt())
    var amount by remember(target.lineId) { mutableIntStateOf(1) }
    val shownAmount = amount.coerceIn(1, maxAmount)
    val code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: target.barcode

    ScanPanel(
        item = ScanPanelItem(
            code = code,
            name = line.orgStock?.name.orEmpty(),
            location = target.locationCode ?: line.defaultLocation()?.code,
            imageUrl = line.orgStock?.imageUrl,
        ),
        countLabel = stringResource(R.string.goodsout_scan_picked_chip, quantityText(line.quantityPicked, line.quantityPickedLabel)),
        remainingLabel = pluralStringResource(R.plurals.goodsout_left_on_item_label, pluralQuantity(remaining), remainingText),
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
private fun SampleScanToPick(reference: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    var remaining by remember { mutableIntStateOf(GoodsOutSamples.SCAN_REMAINING) }
    var picked by remember { mutableIntStateOf(GoodsOutSamples.SCAN_PICKED) }
    var amount by remember { mutableIntStateOf(GoodsOutSamples.SCAN_AMOUNT) }
    val skuCode = GoodsOutSamples.SCAN_SKO
    val lineDoneText = stringResource(R.string.goodsout_scan_line_done_toast, skuCode)
    val pickedAmountText = pluralStringResource(R.plurals.goodsout_scan_picked_toast, amount, amount, skuCode)

    fun pick(count: Int) {
        picked += count
        remaining -= count
        if (remaining <= 0) {
            toasts.show(lineDoneText, ToastKind.Success)
            navigator.back()
        } else {
            toasts.show(pickedAmountText, ToastKind.Success)
            amount = amount.coerceAtMost(remaining)
        }
    }

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                AppHeader(
                    isSample = true,
                    title = stringResource(R.string.goodsout_scan_to_pick),
                    kicker = pluralStringResource(R.plurals.goodsout_scan_kicker, GoodsOutSamples.SCAN_TOTAL_COUNT, reference, GoodsOutSamples.SCAN_PICKED_COUNT, GoodsOutSamples.SCAN_TOTAL_COUNT),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            bottomPanel = {
                ScanPanel(
                    item = ScanPanelItem(code = skuCode, name = GoodsOutSamples.SKO_NAME, location = GoodsOutSamples.PICKING_LOCATION),
                    countLabel = stringResource(R.string.goodsout_scan_picked_chip, picked.toString()),
                    remainingLabel = pluralStringResource(R.plurals.goodsout_left_on_item, remaining, remaining),
                    allLabel = pluralStringResource(R.plurals.goodsout_pick_all, remaining, remaining),
                    onAll = { pick(remaining) },
                    amount = amount,
                    onAmountChange = { amount = it.coerceIn(1, remaining) },
                    amountLabel = stringResource(R.string.goodsout_pick_amount, amount),
                    onAmount = { pick(amount) },
                    hint = stringResource(R.string.goodsout_scan_panel_hint),
                    maxAmount = remaining,
                    modifier = Modifier.fakeData(cornerRadius = 16.dp),
                )
            },
        ) {
            ScanHintCard(
                title = stringResource(R.string.goodsout_scan_listening),
                caption = stringResource(R.string.goodsout_scan_take_from),
                value = GoodsOutSamples.PICKING_LOCATION,
                detail = GoodsOutSamples.SCAN_QUEUED,
                modifier = Modifier.fakeData(),
            )
            GoodsOutHeading(title = stringResource(R.string.goodsout_scanned))
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutSamples.scannedEntries.forEachIndexed { index, entry ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = entry.skuCode,
                        subtitle = entry.detail,
                        chip = if (entry.isPicked) stringResource(R.string.goodsout_scan_plus_one) else stringResource(R.string.goodsout_scan_could_not_pick),
                        chipTone = if (entry.isPicked) Tone.Success else Tone.Danger,
                    )
                }
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_scan_bluetooth_note))
        }
    }
}
