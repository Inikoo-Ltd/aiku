package io.aiku.droid.ui.inventory

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.MovementFlow
import io.aiku.droid.data.api.StockMovement
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.locations.locationSubtitle
import io.aiku.droid.ui.scan.BarcodeSet
import io.aiku.droid.ui.scan.CameraBarcodePreview
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.scan.ScanMissEffect
import io.aiku.droid.ui.scan.ScanProblem
import io.aiku.droid.ui.scan.rememberCameraAccess
import io.aiku.droid.ui.theme.LocalAikuColors

private val CameraShape = RoundedCornerShape(12.dp)

@Composable
internal fun StockLoadProblem(problem: GoodsOutProblem, title: String, onRetry: () -> Unit, modifier: Modifier = Modifier) {
    GoodsOutLoadProblem(problem = problem, title = title, onRetry = onRetry, modifier = modifier)
}

@Composable
internal fun refusalText(event: StockEvent.Refused): String = event.message.ifBlank { problemText(event.problem) }

@Composable
internal fun scanProblemText(code: String, problem: ScanProblem): String = when (problem) {
    ScanProblem.Offline -> stringResource(R.string.scan_failed_offline, code)
    ScanProblem.Forbidden -> stringResource(R.string.scan_failed_forbidden, code)
    ScanProblem.ServerError -> stringResource(R.string.scan_failed_server, code)
}

@Composable
internal fun countLabel(label: String, count: Int?): String =
    if (count == null) label else stringResource(R.string.inventory_chip_with_count, label, count)

@Composable
internal fun signedQuantity(value: Double, label: String? = null): String {
    val shown = label?.takeIf { it.isNotBlank() }?.trimStart('-', '+', '−')?.let(::fractionGlyphs)
    return when {
        value > 0 -> stringResource(R.string.inventory_delta_increase, shown ?: stockText(value))
        value < 0 -> stringResource(R.string.inventory_delta_decrease, shown ?: stockText(-value))
        else -> shown ?: stockText(value)
    }
}

@Composable
internal fun MovementRow(movement: StockMovement, title: String, showSko: Boolean) {
    val zone = organisationZone()
    val tone = when {
        movement.flow == MovementFlow.In.key -> Tone.Success
        movement.flow == MovementFlow.Audit.key && movement.quantity < 0 -> Tone.Warn
        movement.flow == MovementFlow.Audit.key -> Tone.Success
        else -> Tone.Neutral
    }
    val subtitle = listOfNotNull(
        movement.orgStockName?.takeIf { showSko && it.isNotBlank() },
        movement.reasonLabel?.takeIf { it.isNotBlank() },
        movement.parentReference?.takeIf { it.isNotBlank() },
        movement.note?.takeIf { it.isNotBlank() },
        movement.who,
        momentText(movement.date, zone),
        movement.running?.let { stringResource(R.string.inventory_running, stockText(it, movement.runningLabel)) },
    ).joinToString(LIST_SEPARATOR)

    ItemRow(
        code = title,
        subtitle = subtitle,
        chip = stringResource(R.string.inventory_movement_chip, movement.typeLabel.ifBlank { movement.type }, signedQuantity(movement.quantity, movement.quantityLabel)),
        chipTone = tone,
        showPhoto = false,
    )
}

@Composable
internal fun InlineScanCamera(onScanned: (String) -> Unit, modifier: Modifier = Modifier) {
    val access = rememberCameraAccess()
    val colors = LocalAikuColors.current

    Box(contentAlignment = Alignment.Center, modifier = modifier.fillMaxWidth().height(200.dp).clip(CameraShape)) {
        if (access.isAllowed) {
            CameraBarcodePreview(barcodes = BarcodeSet.Warehouse, onScanned = onScanned, isSingleShot = true, modifier = Modifier.fillMaxSize())
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
internal fun LocationPicker(
    state: LocationFinderState,
    placeholder: String,
    onQueryChange: (String) -> Unit,
    onPick: (Long) -> Unit,
    onScanned: (String) -> Unit,
) {
    val colors = LocalAikuColors.current
    var isCameraOn by remember { mutableStateOf(false) }
    val picked = state.picked

    ScanEffect { scan -> onScanned(scan.code) }
    ScanMissEffect(miss = state.notALocation)

    SearchScanField(
        placeholder = placeholder,
        value = state.query,
        onValueChange = onQueryChange,
        onScan = { isCameraOn = !isCameraOn },
    )
    if (isCameraOn) {
        InlineScanCamera(onScanned = { code ->
            isCameraOn = false
            onScanned(code)
        })
    }
    state.lookingUp?.let { code -> Text(text = stringResource(R.string.scan_looking_up, code), fontSize = 13.sp, color = colors.textTertiary) }
    state.notALocation?.let { code -> Text(text = stringResource(R.string.inventory_not_a_location, code), fontSize = 13.sp, color = colors.danger) }
    state.problem?.let { Text(text = problemText(it), fontSize = 13.sp, color = colors.danger) }
    if (picked != null && state.results.none { it.id == picked.id }) {
        ChoiceRow(title = picked.code, subtitle = picked.area, selected = true, onClick = {})
    }
    state.results.forEach { location ->
        ChoiceRow(
            title = location.code,
            subtitle = locationSubtitle(location),
            selected = location.id == picked?.id,
            onClick = { onPick(location.id) },
        )
    }
    if (state.isSearching) {
        LoadingMore()
    } else if (state.query.isNotBlank() && state.results.isEmpty() && state.problem == null && picked == null) {
        Text(text = stringResource(R.string.inventory_no_location_found, state.query.trim()), fontSize = 13.sp, color = colors.textTertiary)
    }
}

@Composable
internal fun AuditSheet(
    title: String,
    subtitle: String?,
    initialDraft: AuditDraft,
    reasons: StockMovementReasons?,
    reasonsProblem: GoodsOutProblem?,
    onRetryReasons: () -> Unit,
    isWorking: Boolean,
    errorText: String?,
    onSave: (List<AuditCount>) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var draft by remember { mutableStateOf(initialDraft) }
    var editing by remember { mutableStateOf(initialDraft.lines.singleOrNull()?.locationOrgStockId) }
    var showMissing by remember { mutableStateOf(false) }
    val missing = draft.linesNeedingReason

    AikuBottomSheet(title = title, subtitle = subtitle, onDismiss = onDismiss) {
        ListGroup {
            draft.lines.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                AuditCountRow(
                    line = line,
                    counted = draft.countedOf(line),
                    isEditing = editing == line.locationOrgStockId,
                    onToggle = { editing = if (editing == line.locationOrgStockId) null else line.locationOrgStockId },
                    onCountedChange = { draft = draft.withCount(line.locationOrgStockId, it) },
                )
            }
        }
        draft.lines.forEach { line ->
            val direction = draft.direction(line) ?: return@forEach
            SheetGroupLabel(
                text = stringResource(
                    if (direction == AuditDirection.Increase) R.string.inventory_audit_reason_increase else R.string.inventory_audit_reason_decrease,
                    line.title,
                ),
            )
            when {
                reasons != null -> reasons.forDirection(direction).forEach { reason ->
                    ChoiceRow(
                        title = reason.label,
                        selected = draft.reasonOf(line) == reason.key,
                        onClick = { draft = draft.withReason(line.locationOrgStockId, direction, reason.key) },
                    )
                }
                reasonsProblem != null -> InlineAlert(
                    title = stringResource(R.string.inventory_reasons_failed),
                    body = problemText(reasonsProblem),
                    tone = AlertTone.Warn,
                    actionLabel = stringResource(R.string.goodsout_try_again),
                    onAction = onRetryReasons,
                )
                else -> LoadingMore()
            }
        }
        SheetTextField(value = draft.note, onValueChange = { draft = draft.copy(note = it) }, label = stringResource(R.string.inventory_note_optional))
        if (showMissing && missing.isNotEmpty()) {
            InlineAlert(
                title = stringResource(R.string.inventory_audit_reason_missing, missing.joinToString(LIST_SEPARATOR) { it.title }),
                tone = AlertTone.Danger,
            )
        }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        BoardFootnote(text = stringResource(R.string.inventory_audit_help))
        SheetButtons(
            confirmLabel = stringResource(R.string.inventory_audit_save),
            onConfirm = {
                val counts = draft.counts()
                if (counts == null) {
                    showMissing = true
                } else {
                    onSave(counts)
                }
            },
            onCancel = onDismiss,
            confirmLook = ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_check),
            isWorking = isWorking,
        )
    }
}

@Composable
private fun AuditCountRow(line: AuditLine, counted: Double, isEditing: Boolean, onToggle: () -> Unit, onCountedChange: (Double) -> Unit) {
    val colors = LocalAikuColors.current
    val delta = counted - line.current
    val tone = when {
        delta < 0 -> Tone.Warn
        delta > 0 -> Tone.Success
        else -> Tone.Neutral
    }
    val deltaText = if (delta == 0.0) stringResource(R.string.inventory_audit_no_change) else signedQuantity(delta)
    val currentText = stockText(line.current, line.currentLabel)

    Column(modifier = Modifier.fillMaxWidth()) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 60.dp)
                .clickable(role = Role.Button, onClick = onToggle)
                .padding(horizontal = 14.dp, vertical = 10.dp),
        ) {
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(text = line.title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(
                    text = listOfNotNull(line.subtitle, stringResource(R.string.inventory_audit_was_counted, currentText, stockText(counted))).joinToString(LIST_SEPARATOR),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                )
            }
            StatusBadge(text = deltaText, tone = tone)
        }
        if (isEditing) {
            QuantityStepper(
                value = counted,
                valueText = stockText(counted),
                onValueChange = onCountedChange,
                min = 0.0,
                max = Double.MAX_VALUE,
                modifier = Modifier.padding(start = 14.dp, end = 14.dp, bottom = 12.dp),
            )
        }
    }
}

@Composable
internal fun DecimalStepper(value: Double, onValueChange: (Double) -> Unit, max: Double, modifier: Modifier = Modifier, helper: String? = null) {
    QuantityStepper(
        value = value,
        valueText = stockText(value),
        onValueChange = onValueChange,
        min = 0.0,
        max = max,
        modifier = modifier,
        helper = helper,
    )
}
