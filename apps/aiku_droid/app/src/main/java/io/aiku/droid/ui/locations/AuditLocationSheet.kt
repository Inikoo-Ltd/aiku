package io.aiku.droid.ui.locations

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.ui.inventory.AuditDraft
import io.aiku.droid.ui.inventory.AuditLine
import io.aiku.droid.ui.inventory.AuditSheet

@Composable
fun AuditLocationSheet(
    detail: LocationDetail,
    onlyLinkId: Long?,
    state: LocationUiState,
    onLoadReasons: () -> Unit,
    errorText: String?,
    onSave: (List<AuditCount>) -> Unit,
    onDismiss: () -> Unit,
) {
    val lines = detail.orgStocks
        .filter { onlyLinkId == null || it.locationOrgStockId == onlyLinkId }
        .map { sko -> AuditLine(locationOrgStockId = sko.locationOrgStockId, title = sko.code, subtitle = sko.name.takeIf { it.isNotBlank() }, current = sko.stock, currentLabel = sko.stockLabel, packedIn = sko.packedIn) }

    LaunchedEffect(Unit) { onLoadReasons() }

    AuditSheet(
        title = stringResource(R.string.locations_audit_title, detail.code),
        subtitle = stringResource(if (onlyLinkId == null) R.string.locations_audit_subtitle else R.string.locations_audit_one_subtitle),
        initialDraft = AuditDraft(lines = lines),
        reasons = state.reasons,
        reasonsProblem = state.reasonsProblem,
        onRetryReasons = onLoadReasons,
        isWorking = state.isWorking,
        errorText = errorText,
        onSave = onSave,
        onDismiss = onDismiss,
    )
}
