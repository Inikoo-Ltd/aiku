package io.aiku.droid.ui.sync

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.OfflineInstruction
import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.data.api.OfflineOutcomeStatus
import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.quantityText

fun offlineOperationOf(wire: String): OfflineOperation? = OfflineOperation.entries.firstOrNull { it.wire == wire }

@Composable
fun recordTitle(record: OfflineRecordKey, reference: String?): String {
    val shown = reference.orEmpty()
    return stringResource(
        when (record.type) {
            OfflineRecordType.DELIVERY_NOTE -> R.string.offline_stock_record_note
            OfflineRecordType.RETURN -> R.string.offline_stock_record_return
            OfflineRecordType.STOCK_DELIVERY -> R.string.offline_stock_record_delivery
            OfflineRecordType.LOCATION -> R.string.offline_stock_record_location
            OfflineRecordType.ORG_STOCK -> R.string.offline_stock_record_sko
        },
        shown,
    ).trim()
}

@Composable
fun entryOperationLabel(entry: OfflineOperationEntry): String = offlineOperationOf(entry.operation)?.let { operationLabel(it) } ?: entry.operation

@Composable
fun entryStateLabel(entry: OfflineOperationEntry): Pair<String, Tone>? = when (entry.currentState) {
    OfflineOutcomeStatus.APPLIED, OfflineOutcomeStatus.ALREADY_DONE -> stringResource(R.string.offline_stock_state_applied) to Tone.Success
    OfflineOutcomeStatus.PARTLY_APPLIED -> stringResource(R.string.offline_stock_state_partly) to Tone.Warn
    OfflineOutcomeStatus.REFUSED -> stringResource(R.string.offline_stock_state_refused) to Tone.Danger
    OfflineOutcomeStatus.CONFLICT -> stringResource(R.string.offline_stock_state_conflict) to Tone.Danger
    OfflineOutcomeStatus.BLOCKED -> stringResource(R.string.offline_stock_state_blocked) to Tone.Neutral
    EntryState.RESOLVED -> stringResource(R.string.offline_stock_state_resolved) to Tone.Success
    EntryState.ROLLED_BACK -> stringResource(R.string.offline_stock_state_rolled_back) to Tone.Neutral
    EntryState.EXPIRED -> stringResource(R.string.offline_stock_state_expired) to Tone.Danger
    else -> null
}

object EntryState {
    const val RESOLVED = "resolved"
    const val ROLLED_BACK = "rolled_back"
    const val EXPIRED = "expired"
}

@Composable
fun instructionText(entry: OfflineOperationEntry): String? {
    val location = entry.labels.locationCode?.takeIf { it.isNotBlank() }
    val missing = entry.missingQuantity
    val requested = entry.requestedQuantity
    return when (entry.instruction) {
        OfflineInstruction.PUT_BACK -> when {
            location != null && entry.status == OfflineOutcomeStatus.PARTLY_APPLIED && missing != null ->
                stringResource(R.string.offline_stock_do_put_back_other, quantityText(missing), location)
            location != null && requested != null -> stringResource(R.string.offline_stock_do_put_back, quantityText(missing ?: requested), location)
            else -> stringResource(R.string.offline_stock_do_put_back_plain)
        }
        OfflineInstruction.UNPACK -> stringResource(R.string.offline_stock_do_unpack, quantityText(missing ?: requested ?: 0.0))
        OfflineInstruction.KEEP_IN_TOTE -> stringResource(R.string.offline_stock_do_keep_in_tote)
        OfflineInstruction.PICK_AGAIN -> stringResource(R.string.offline_stock_do_pick_again)
        OfflineInstruction.RECOUNT -> stringResource(R.string.offline_stock_do_recount)
        OfflineInstruction.CHECK_SHELF -> stringResource(R.string.offline_stock_do_check_shelf)
        else -> null
    }
}

@Composable
fun reasonText(reason: String?): String? = when (reason) {
    "over_required" -> stringResource(R.string.offline_stock_reason_over_required)
    "over_packed" -> stringResource(R.string.offline_stock_reason_over_packed)
    "over_checked" -> stringResource(R.string.offline_stock_reason_over_checked)
    "over_expected" -> stringResource(R.string.offline_stock_reason_over_expected)
    "location_empty" -> stringResource(R.string.offline_stock_reason_location_empty)
    "not_enough_stock" -> stringResource(R.string.offline_stock_reason_not_enough_stock)
    "wrong_state" -> stringResource(R.string.offline_stock_reason_wrong_state)
    "not_handler" -> stringResource(R.string.offline_stock_reason_not_handler)
    "note_cancelled" -> stringResource(R.string.offline_stock_reason_note_cancelled)
    "return_closed" -> stringResource(R.string.offline_stock_reason_return_closed)
    "delivery_closed" -> stringResource(R.string.offline_stock_reason_delivery_closed)
    "waiting_cleared" -> stringResource(R.string.offline_stock_reason_waiting_cleared)
    "setting_off" -> stringResource(R.string.offline_stock_reason_setting_off)
    "in_picking_session" -> stringResource(R.string.offline_stock_reason_in_picking_session)
    "other_change" -> stringResource(R.string.offline_stock_reason_other_change)
    "not_found" -> stringResource(R.string.offline_stock_reason_not_found)
    "no_right" -> stringResource(R.string.offline_stock_reason_no_right)
    "invalid" -> stringResource(R.string.offline_stock_reason_invalid)
    else -> null
}

@Composable
fun resolutionLabel(resolution: OfflineResolution): String = stringResource(
    when (resolution) {
        OfflineResolution.PutBack -> R.string.offline_stock_resolve_put_back
        OfflineResolution.Done -> R.string.offline_stock_resolve_done
        OfflineResolution.Retry -> R.string.offline_stock_resolve_retry
        OfflineResolution.Recount -> R.string.offline_stock_resolve_recount
        OfflineResolution.Discard -> R.string.offline_stock_resolve_discard
        OfflineResolution.KeepCount -> R.string.offline_stock_resolve_keep_count
    },
)
