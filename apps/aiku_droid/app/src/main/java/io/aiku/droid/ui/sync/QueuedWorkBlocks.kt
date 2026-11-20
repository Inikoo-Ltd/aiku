package io.aiku.droid.ui.sync

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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.lastUpdatedTime
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.OnlineOnlyRoute
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant
import java.time.ZoneId

data class QueuedRow(val action: QueuedAction, val text: String)

@Composable
fun OfflineCopyNote(copyAt: Instant, modifier: Modifier = Modifier) {
    val locale = LocalConfiguration.current.locales[0]

    Text(
        text = stringResource(R.string.offline_stock_copy_age, lastUpdatedTime(copyAt, Instant.now(), ZoneId.systemDefault(), locale)),
        fontSize = 12.sp,
        lineHeight = 16.sp,
        color = LocalAikuColors.current.warnInk,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
fun queuedStateLabel(state: QueuedActionState): Pair<String, Tone>? = when (state) {
    QueuedActionState.WAITING -> stringResource(R.string.offline_stock_waiting) to Tone.Warn
    QueuedActionState.SENDING -> stringResource(R.string.offline_stock_sending) to Tone.Primary
    QueuedActionState.APPLIED -> stringResource(R.string.offline_stock_sent) to Tone.Success
    QueuedActionState.NEEDS_YOU -> stringResource(R.string.offline_stock_needs_you) to Tone.Danger
    QueuedActionState.VOID -> null
}

@Composable
fun operationLabel(operation: OfflineOperation): String = stringResource(
    when (operation) {
        OfflineOperation.PICK -> R.string.offline_stock_op_pick
        OfflineOperation.NOT_PICKED -> R.string.offline_stock_op_not_picked
        OfflineOperation.SET_WAITING_WAREHOUSE -> R.string.offline_stock_op_waiting_warehouse
        OfflineOperation.SET_WAITING_CRM -> R.string.offline_stock_op_waiting_crm
        OfflineOperation.PACK -> R.string.offline_stock_op_pack
        OfflineOperation.MOVE_STOCK -> R.string.offline_stock_op_move
        OfflineOperation.PLACE_STOCK_DELIVERY_ITEM -> R.string.offline_stock_op_place
        OfflineOperation.RETURN_PUT_BACK -> R.string.offline_stock_op_put_back
        OfflineOperation.RETURN_DAMAGED -> R.string.offline_stock_op_damaged
        OfflineOperation.RETURN_NOT_RETURNED -> R.string.offline_stock_op_not_returned
        OfflineOperation.AUDIT_LOCATION -> R.string.offline_stock_op_audit_location
        OfflineOperation.AUDIT_ORG_STOCK -> R.string.offline_stock_op_audit_sko
    },
)

@Composable
fun QueuedActionsBlock(rows: List<QueuedRow>, onUndo: (Long) -> Unit, modifier: Modifier = Modifier) {
    if (rows.isEmpty()) {
        return
    }
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = modifier.fillMaxWidth()) {
        GroupLabel(text = stringResource(R.string.offline_stock_queued_title))
        ListGroup {
            rows.forEachIndexed { index, row ->
                if (index > 0) {
                    RowDivider()
                }
                val needsYou = row.action.state == QueuedActionState.NEEDS_YOU
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 48.dp)
                        .then(if (needsYou) Modifier.clickable(role = Role.Button) { navigator.open(ResolveConflictsRoute) } else Modifier)
                        .padding(start = 14.dp, end = 6.dp, top = 6.dp, bottom = 6.dp),
                ) {
                    Text(text = row.text, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
                    queuedStateLabel(row.action.state)?.let { (label, tone) -> StatusBadge(text = label, tone = tone) }
                    if (row.action.canUndo) {
                        Text(
                            text = stringResource(R.string.offline_stock_undo),
                            fontSize = 14.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = colors.cautionInk,
                            modifier = Modifier
                                .heightIn(min = 44.dp)
                                .clip(ControlShape)
                                .clickable(role = Role.Button) { onUndo(row.action.localId) }
                                .padding(horizontal = 10.dp, vertical = 12.dp),
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun QueueEventEffect(event: QueueEvent?, reference: String, onHandled: () -> Unit, onQueued: () -> Unit = {}) {
    val toasts = LocalToasts.current
    val navigator = LocalAppNavigator.current
    val queuedText = stringResource(R.string.offline_stock_queued_toast)
    val needsYouText = stringResource(R.string.offline_stock_needs_you_first)

    LaunchedEffect(event) {
        when (event) {
            is QueueEvent.Queued -> {
                toasts.show(queuedText, ToastKind.Offline)
                onQueued()
            }
            QueueEvent.OnlineOnly -> navigator.open(OnlineOnlyRoute(reference))
            QueueEvent.NeedsYou -> {
                toasts.show(needsYouText, ToastKind.Warning)
                navigator.open(ResolveConflictsRoute)
            }
            null -> return@LaunchedEffect
        }
        onHandled()
    }
}
