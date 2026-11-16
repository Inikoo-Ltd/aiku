package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.OrgStockDetail

@Composable
fun AuditStockSheet(
    detail: OrgStockDetail,
    state: SkoUiState,
    onLoadReasons: () -> Unit,
    errorText: String?,
    onSave: (List<AuditCount>) -> Unit,
    onDismiss: () -> Unit,
) {
    val lines = detail.locations.map { location ->
        AuditLine(
            locationOrgStockId = location.locationOrgStockId,
            title = location.location.code,
            subtitle = skoLocationChip(location),
            current = location.quantity,
            currentLabel = location.quantityLabel,
        )
    }

    LaunchedEffect(Unit) { onLoadReasons() }

    AuditSheet(
        title = stringResource(R.string.inventory_audit_title, detail.code),
        subtitle = stringResource(R.string.inventory_audit_subtitle, detail.name),
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
