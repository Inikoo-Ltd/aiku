package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.lastUpdatedTime
import io.aiku.droid.ui.inventory.stockFractionLabel
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant
import java.time.ZoneId

@Composable
internal fun OfflineCopyNotice(copy: OfflineCopy) {
    val locale = LocalConfiguration.current.locales[0]
    val time = lastUpdatedTime(copy.seenAt, Instant.now(), ZoneId.systemDefault(), locale)

    InlineAlert(
        title = stringResource(R.string.goodsout_offline_copy_title, time),
        body = stringResource(R.string.goodsout_offline_copy_body),
        tone = AlertTone.Info,
    )
}

@Composable
internal fun queuedWorkText(work: QueuedLineWork, line: DeliveryNoteLine): String {
    val quantity = quantityText(work.quantity, stockFractionLabel(work.quantity, line.orgStock?.packedIn))
    return when (work.operation) {
        OfflineOperation.PICK -> line.locations.firstOrNull { it.locationOrgStockId == work.locationOrgStockId }?.code?.takeIf { it.isNotBlank() }
            ?.let { stringResource(R.string.goodsout_offline_queued_pick, quantity, it) }
            ?: stringResource(R.string.goodsout_offline_queued_pick_plain, quantity)
        OfflineOperation.NOT_PICKED -> stringResource(R.string.goodsout_offline_queued_not_picked, quantity)
        OfflineOperation.SET_WAITING_WAREHOUSE -> stringResource(R.string.goodsout_offline_queued_waiting_warehouse, quantity)
        OfflineOperation.SET_WAITING_CRM -> stringResource(R.string.goodsout_offline_queued_waiting_crm, quantity)
        else -> stringResource(R.string.goodsout_offline_queued_pack, quantity)
    }
}

@Composable
internal fun queuedWorkState(work: QueuedLineWork): String =
    stringResource(if (work.needsYou) R.string.goodsout_offline_needs_you else R.string.goodsout_offline_waiting_to_sync)

@Composable
internal fun ColumnScope.QueuedWorkMarks(work: List<QueuedLineWork>, line: DeliveryNoteLine) {
    val colors = LocalAikuColors.current
    work.forEach { item ->
        Text(
            text = listOf(queuedWorkText(item, line), queuedWorkState(item)).joinToString(" · "),
            fontSize = 12.sp,
            lineHeight = 16.sp,
            color = if (item.needsYou) colors.danger else colors.warn,
        )
    }
}

@Composable
internal fun QueuedWorkGroup(work: List<QueuedLineWork>, line: DeliveryNoteLine, onUndo: (Long) -> Unit) {
    if (work.isEmpty()) {
        return
    }
    GroupLabel(text = stringResource(R.string.goodsout_offline_queued_group))
    ListGroup {
        work.forEachIndexed { index, item ->
            if (index > 0) {
                RowDivider()
            }
            IconInfoRow(
                icon = if (item.needsYou) R.drawable.ic_ui_warning else R.drawable.ic_ui_clock,
                tone = if (item.needsYou) Tone.Danger else Tone.Warn,
                title = queuedWorkText(item, line),
                subtitle = queuedWorkState(item),
                actionLabel = if (item.canUndo) stringResource(R.string.goodsout_undo) else null,
                onAction = if (item.canUndo) ({ onUndo(item.localId) }) else null,
            )
        }
    }
}
