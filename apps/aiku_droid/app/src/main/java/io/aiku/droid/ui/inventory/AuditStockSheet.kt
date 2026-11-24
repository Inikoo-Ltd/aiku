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
    locationOrgStockId: Long? = null,
) {
    val audited = detail.locations.filter { locationOrgStockId == null || it.locationOrgStockId == locationOrgStockId }
    val lines = audited.map { location ->
        AuditLine(
            locationOrgStockId = location.locationOrgStockId,
            title = location.location.code,
            subtitle = skoLocationChip(location),
            current = location.quantity,
            currentLabel = location.quantityLabel,
            packedIn = detail.packedIn,
        )
    }

    LaunchedEffect(Unit) { onLoadReasons() }

    AuditSheet(
        title = stringResource(R.string.inventory_audit_title, detail.code),
        subtitle = audited.singleOrNull()?.takeIf { locationOrgStockId != null }
            ?.let { stringResource(R.string.inventory_audit_one_subtitle, detail.name, it.location.code) }
            ?: stringResource(R.string.inventory_audit_subtitle, detail.name),
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
