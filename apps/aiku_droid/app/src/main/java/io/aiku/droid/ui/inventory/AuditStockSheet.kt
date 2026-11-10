package io.aiku.droid.ui.inventory

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.theme.LocalAikuColors

private val decreaseReasons = listOf(
    R.string.inventory_reason_count_shortage,
    R.string.inventory_reason_damaged,
    R.string.inventory_reason_lost,
    R.string.inventory_reason_expired,
    R.string.inventory_reason_picking_error,
)

private val increaseReasons = listOf(
    R.string.inventory_reason_count_gain,
    R.string.inventory_reason_recount,
    R.string.inventory_reason_returned,
    R.string.inventory_reason_supplier_over,
)

private const val DEFAULT_DECREASE_REASON = 1
private const val DEFAULT_INCREASE_REASON = 0

private fun auditCountsFor(locationCode: String?): List<SampleAuditCount> {
    if (locationCode == null) return sampleAuditCounts
    return sampleAuditCounts.filter { it.locationCode == locationCode }
        .ifEmpty { listOf(SampleAuditCount(locationCode, was = SAMPLE_NEW_LOCATION_STOCK, counted = SAMPLE_NEW_LOCATION_STOCK)) }
}

private fun reasonKey(locationCode: String, isIncrease: Boolean): String = "$locationCode:$isIncrease"

@Composable
fun AuditStockSheet(skuCode: String, onDismiss: () -> Unit, locationCode: String? = null) {
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val sko = sampleSkoByCode(skuCode)
    val audits = remember(locationCode) { auditCountsFor(locationCode) }
    var counted by remember(locationCode) { mutableStateOf(audits.map { it.counted }) }
    var editingIndex by remember { mutableStateOf<Int?>(null) }
    var reasons by remember { mutableStateOf(mapOf<String, Int>()) }
    val savedMessage = stringResource(R.string.inventory_audit_saved, skuCode)

    AikuBottomSheet(title = stringResource(R.string.inventory_audit_title, skuCode), onDismiss = onDismiss) {
        Text(
            text = stringResource(R.string.inventory_audit_subtitle, sko.name),
            fontSize = 14.sp,
            color = colors.textTertiary,
            modifier = Modifier.fakeData(4.dp),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            audits.forEachIndexed { index, audit ->
                if (index > 0) RowDivider()
                AuditCountRow(
                    audit = audit,
                    counted = counted[index],
                    isEditing = editingIndex == index,
                    onToggleEditing = { editingIndex = if (editingIndex == index) null else index },
                    onCountedChange = { value -> counted = counted.toMutableList().also { it[index] = value } },
                )
            }
        }
        audits.forEachIndexed { index, audit ->
            val delta = counted[index] - audit.was
            if (delta != 0) {
                val isIncrease = delta > 0
                val key = reasonKey(audit.locationCode, isIncrease)
                val options = if (isIncrease) increaseReasons else decreaseReasons
                val selectedReason = reasons[key] ?: if (isIncrease) DEFAULT_INCREASE_REASON else DEFAULT_DECREASE_REASON
                SheetGroupLabel(
                    text = stringResource(
                        if (isIncrease) R.string.inventory_audit_reason_increase else R.string.inventory_audit_reason_decrease,
                        audit.locationCode,
                    ),
                )
                options.forEachIndexed { optionIndex, reason ->
                    ChoiceRow(
                        title = stringResource(reason),
                        selected = optionIndex == selectedReason,
                        onClick = { reasons = reasons + (key to optionIndex) },
                    )
                }
            }
        }
        SheetButtons(
            confirmLabel = stringResource(R.string.inventory_audit_save),
            onConfirm = {
                toasts.show(savedMessage, ToastKind.Success)
                onDismiss()
            },
            onCancel = onDismiss,
        )
    }
}

@Composable
private fun AuditCountRow(
    audit: SampleAuditCount,
    counted: Int,
    isEditing: Boolean,
    onToggleEditing: () -> Unit,
    onCountedChange: (Int) -> Unit,
) {
    val colors = LocalAikuColors.current
    val delta = counted - audit.was
    val (deltaText, deltaTone) = when {
        delta < 0 -> stringResource(R.string.inventory_delta_decrease, -delta) to Tone.Warn
        delta > 0 -> stringResource(R.string.inventory_delta_increase, delta) to Tone.Success
        else -> stringResource(R.string.inventory_audit_no_change) to Tone.Success
    }

    Column(modifier = Modifier.fillMaxWidth()) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 60.dp)
                .clickable(role = Role.Button, onClick = onToggleEditing)
                .padding(horizontal = 14.dp, vertical = 10.dp),
        ) {
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(text = audit.locationCode, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(
                    text = stringResource(R.string.inventory_audit_was_counted, audit.was, counted),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                )
            }
            StatusBadge(text = deltaText, tone = deltaTone)
        }
        if (isEditing) {
            QuantityStepper(
                value = counted,
                onValueChange = onCountedChange,
                min = 0,
                modifier = Modifier.padding(start = 14.dp, end = 14.dp, bottom = 12.dp),
            )
        }
    }
}
