package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun StoredItemAuditScreen(reference: String) {
    RequireFulfilment(section = R.string.section_stored_pallets) {
        StoredItemAuditContent(reference = reference)
    }
}

@Composable
private fun StoredItemAuditContent(reference: String) {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val completedMessage = stringResource(R.string.fulfilment_audit_completed_toast, reference)

    AppScreen(
        header = {
            FulfilmentHeader(
                title = reference,
                kicker = dotJoined(
                    stringResource(R.string.fulfilment_audit_kicker),
                    stringResource(auditScopeSample.label),
                    stringResource(R.string.fulfilment_state_in_process),
                ),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.editFulfilment) {
                AikuButton(
                    text = stringResource(R.string.fulfilment_audit_complete),
                    onClick = {
                        toasts.show(completedMessage, ToastKind.Success)
                        navigator.back()
                    },
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            } else {
                FooterStatus(title = stringResource(R.string.fulfilment_state_in_process))
            }
        },
    ) {
        if (can.editFulfilment) {
            FulfilmentScanPrompt(
                title = stringResource(R.string.fulfilment_audit_scan_title),
                instruction = stringResource(R.string.fulfilment_audit_scan_instruction, AUDIT_PALLET_SAMPLE),
                target = AUDIT_LAST_SCANNED_SAMPLE,
                modifier = Modifier.fakeData(),
            )
        }
        ListGroup(modifier = Modifier.fakeData()) {
            auditLinesSample.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                AuditLineRow(line = line)
            }
            if (can.editFulfilment) {
                RowDivider()
                FulfilmentRow(
                    title = stringResource(R.string.fulfilment_audit_add_item),
                    subtitle = stringResource(R.string.fulfilment_audit_add_item_hint),
                    leading = { IconTile(icon = R.drawable.ic_ui_scan, tone = Tone.Neutral) },
                )
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_audit_footnote))
    }
}

@Composable
private fun AuditLineRow(line: AuditLineSample) {
    val counted = line.counted
    val difference = (counted ?: 0) - line.expected
    val (chip, tone) = when {
        counted == null -> null to null
        difference == 0 -> stringResource(R.string.fulfilment_audit_no_change) to Tone.Success
        difference < 0 -> stringResource(R.string.fulfilment_audit_subtraction, -difference) to Tone.Warn
        else -> stringResource(R.string.fulfilment_audit_addition, difference) to Tone.Primary
    }

    ItemRow(
        code = line.code,
        subtitle = if (counted == null) {
            stringResource(R.string.fulfilment_audit_expected_not_counted, line.expected)
        } else {
            stringResource(R.string.fulfilment_audit_expected, line.expected)
        },
        chip = chip,
        chipTone = tone ?: Tone.Neutral,
        done = (counted ?: 0).toString(),
        required = line.expected.toString(),
        quantityTone = tone,
    )
}
