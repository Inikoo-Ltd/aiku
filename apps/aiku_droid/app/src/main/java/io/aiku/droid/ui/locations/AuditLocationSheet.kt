package io.aiku.droid.ui.locations

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.inventory.AuditDraft
import io.aiku.droid.ui.inventory.AuditLine
import io.aiku.droid.ui.inventory.AuditSheet
import io.aiku.droid.ui.inventory.InlineScanCamera
import io.aiku.droid.ui.inventory.countedText
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun AuditLocationSheet(
    detail: LocationDetail,
    onlyLinkId: Long?,
    state: LocationUiState,
    onLoadReasons: () -> Unit,
    errorText: String?,
    onSave: (List<AuditCount>) -> Unit,
    onDismiss: () -> Unit,
    onFindSko: (String) -> Unit = {},
    onFoundSkoHandled: () -> Unit = {},
) {
    val lines = detail.orgStocks
        .filter { onlyLinkId == null || it.locationOrgStockId == onlyLinkId }
        .map { sko -> AuditLine(locationOrgStockId = sko.locationOrgStockId, title = sko.code, subtitle = sko.name.takeIf { it.isNotBlank() }, current = sko.stock, currentLabel = sko.stockLabel, packedIn = sko.packedIn) }

    LaunchedEffect(Unit) { onLoadReasons() }

    if (onlyLinkId != null) {
        AuditSheet(
            title = stringResource(R.string.locations_audit_title, detail.code),
            subtitle = stringResource(R.string.locations_audit_one_subtitle),
            initialDraft = AuditDraft(lines = lines),
            reasons = state.reasons,
            reasonsProblem = state.reasonsProblem,
            onRetryReasons = onLoadReasons,
            isWorking = state.isWorking,
            errorText = errorText,
            onSave = onSave,
            onDismiss = onDismiss,
        )
        return
    }

    BlindLocationAudit(
        detail = detail,
        lines = lines,
        state = state,
        onLoadReasons = onLoadReasons,
        errorText = errorText,
        onSave = onSave,
        onDismiss = onDismiss,
        onFindSko = onFindSko,
        onFoundSkoHandled = onFoundSkoHandled,
    )
}

@Composable
private fun BlindLocationAudit(
    detail: LocationDetail,
    lines: List<AuditLine>,
    state: LocationUiState,
    onLoadReasons: () -> Unit,
    errorText: String?,
    onSave: (List<AuditCount>) -> Unit,
    onDismiss: () -> Unit,
    onFindSko: (String) -> Unit,
    onFoundSkoHandled: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    var counted by remember { mutableStateOf(mapOf<Long, Double>()) }
    var editing by remember { mutableStateOf<Long?>(null) }
    var isSummary by remember { mutableStateOf(false) }
    var isScanning by remember { mutableStateOf(false) }
    var showNothingCounted by remember { mutableStateOf(false) }
    val left = lines.count { it.locationOrgStockId !in counted }
    val notSkoText = (state.foundSko as? FoundSko.NotSko)?.let { stringResource(R.string.locations_audit_found_not_sko, it.code) }
    val refusedText = (state.foundSko as? FoundSko.Refused)?.let { refused -> refused.message.ifBlank { problemText(refused.problem) } }

    ScanEffect(enabled = !isSummary) { scan -> onFindSko(scan.code) }
    LaunchedEffect(state.foundSko) {
        when (val found = state.foundSko) {
            is FoundSko.Here -> {
                isScanning = false
                editing = found.locationOrgStockId
            }
            is FoundSko.NotSko -> notSkoText?.let { toasts.show(it, ToastKind.Error) }
            is FoundSko.Refused -> refusedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        onFoundSkoHandled()
    }

    if (isSummary) {
        AuditSheet(
            title = stringResource(R.string.locations_audit_summary_title, detail.code),
            subtitle = stringResource(R.string.locations_audit_summary_subtitle),
            initialDraft = AuditDraft(lines = lines.filter { it.locationOrgStockId in counted }).let { draft -> draft.copy(counted = draft.counted + counted) },
            reasons = state.reasons,
            reasonsProblem = state.reasonsProblem,
            onRetryReasons = onLoadReasons,
            isWorking = state.isWorking,
            errorText = errorText,
            onSave = onSave,
            onDismiss = { isSummary = false },
        )
        return
    }

    AikuBottomSheet(
        title = stringResource(R.string.locations_audit_title, detail.code),
        subtitle = stringResource(R.string.locations_audit_blind_subtitle),
        onDismiss = onDismiss,
        footer = {
            if (showNothingCounted && counted.isEmpty()) {
                InlineAlert(title = stringResource(R.string.locations_audit_nothing_counted), tone = AlertTone.Warn)
            }
            AikuButton(
                text = if (left > 0) pluralStringResource(R.plurals.locations_audit_complete_left, left, left) else stringResource(R.string.locations_audit_complete),
                onClick = {
                    if (counted.isEmpty()) {
                        showNothingCounted = true
                    } else {
                        isSummary = true
                    }
                },
                icon = R.drawable.ic_ui_check,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onDismiss, style = ButtonStyle.Outline, modifier = Modifier.fillMaxWidth())
        },
    ) {
        ListGroup {
            lines.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                BlindCountRow(
                    line = line,
                    counted = counted[line.locationOrgStockId],
                    isEditing = editing == line.locationOrgStockId,
                    onToggle = { editing = if (editing == line.locationOrgStockId) null else line.locationOrgStockId },
                    onCount = { value -> counted = counted + (line.locationOrgStockId to value) },
                    onDone = { value ->
                        counted = counted + (line.locationOrgStockId to value)
                        editing = null
                    },
                )
            }
        }
        AikuButton(
            text = stringResource(R.string.locations_audit_found_sko),
            onClick = { isScanning = !isScanning },
            style = ButtonStyle.Outline,
            icon = R.drawable.ic_ui_plus,
            modifier = Modifier.fillMaxWidth(),
        )
        if (isScanning) {
            InlineScanCamera(onScanned = onFindSko)
        }
        state.findingSko?.let { Text(text = stringResource(R.string.locations_audit_finding_sko, it), fontSize = 13.sp, color = colors.textTertiary) }
        errorText?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
    }
}

@Composable
private fun BlindCountRow(
    line: AuditLine,
    counted: Double?,
    isEditing: Boolean,
    onToggle: () -> Unit,
    onCount: (Double) -> Unit,
    onDone: (Double) -> Unit,
) {
    val colors = LocalAikuColors.current
    val shown = counted ?: 0.0

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
                line.subtitle?.let { Text(text = it, fontSize = 13.sp, color = colors.textTertiary) }
            }
            if (counted != null) {
                StatusBadge(text = stringResource(R.string.locations_audit_counted, countedText(line, counted)), tone = Tone.Success)
            } else {
                StatusBadge(text = stringResource(R.string.locations_audit_to_count), tone = Tone.Neutral)
            }
        }
        if (isEditing) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(start = 14.dp, end = 14.dp, bottom = 12.dp)) {
                QuantityStepper(
                    value = shown,
                    valueText = countedText(line, shown),
                    onValueChange = onCount,
                    min = 0.0,
                    max = Double.MAX_VALUE,
                )
                AikuButton(
                    text = stringResource(R.string.locations_audit_count_done, countedText(line, shown)),
                    onClick = { onDone(shown) },
                    style = ButtonStyle.Soft,
                    icon = R.drawable.ic_ui_check,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}
