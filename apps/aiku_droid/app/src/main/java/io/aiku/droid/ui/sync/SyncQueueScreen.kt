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
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.EmptyTone
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.lastUpdatedTime
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.ConflictRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant
import java.time.ZoneId

private const val QUANTITY = "quantity"

@Composable
fun SyncQueueScreen(viewModel: SyncQueueViewModel) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    AppScreen(
        header = { AppHeader(title = stringResource(R.string.sync_title), kicker = stringResource(R.string.sync_kicker)) },
        activeTab = MainTab.Menu,
        footer = if (state.isOnline && state.waitingCount > 0) {
            {
                AikuButton(
                    text = stringResource(R.string.offline_stock_sync_now),
                    onClick = viewModel::syncNow,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        } else {
            null
        },
    ) {
        if (!state.isOnline) {
            InlineAlert(title = stringResource(R.string.offline_stock_offline_title), body = stringResource(R.string.offline_stock_offline_body), tone = AlertTone.Warn)
        }
        if (state.needsYouCount > 0) {
            InlineAlert(
                title = pluralStringResource(R.plurals.offline_stock_needs_you_count, state.needsYouCount, state.needsYouCount),
                tone = AlertTone.Danger,
                actionLabel = stringResource(R.string.offline_stock_resolve),
                onAction = { navigator.open(ResolveConflictsRoute) },
            )
        }
        if (state.isEmpty) {
            EmptyState(
                icon = R.drawable.ic_ui_check,
                title = stringResource(R.string.offline_stock_queue_empty_title),
                body = stringResource(R.string.offline_stock_queue_empty_body),
                tone = EmptyTone.Success,
            )
        }
        state.groups.forEach { group ->
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                val groupTitle = recordTitle(group.record, group.title)
                GroupLabel(text = groupTitle)
                ListGroup {
                    group.actions.forEachIndexed { index, action ->
                        if (index > 0) {
                            RowDivider()
                        }
                        QueueRow(
                            action = action,
                            onUndo = { viewModel.undo(action.localId) },
                            onResolve = { navigator.open(ResolveConflictsRoute) },
                            onOpen = action.result?.operationId
                                ?.takeIf { action.state == QueuedActionState.APPLIED }
                                ?.let { operationId -> { navigator.open(ConflictRoute(id = operationId, title = groupTitle)) } },
                        )
                    }
                }
            }
        }
        Text(
            text = stringResource(R.string.offline_stock_queue_note),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}

@Composable
private fun QueueRow(action: QueuedAction, onUndo: () -> Unit, onResolve: () -> Unit, onOpen: (() -> Unit)?) {
    val colors = LocalAikuColors.current
    val locale = LocalConfiguration.current.locales[0]
    val needsYou = action.state == QueuedActionState.NEEDS_YOU
    val quantity = action.payload.doubleField(QUANTITY)?.let { quantityText(it) }
    val title = listOfNotNull(operationLabel(action.operation), quantity).joinToString(LIST_SEPARATOR)
    val subtitle = listOfNotNull(
        lastUpdatedTime(action.performedAt, Instant.now(), ZoneId.systemDefault(), locale),
        action.result?.message?.takeIf { needsYou && it.isNotBlank() },
    ).joinToString(LIST_SEPARATOR)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 52.dp)
            .then(
                when {
                    needsYou -> Modifier.clickable(role = Role.Button, onClick = onResolve)
                    onOpen != null -> Modifier.clickable(role = Role.Button, onClick = onOpen)
                    else -> Modifier
                },
            )
            .padding(start = 14.dp, end = 6.dp, top = 8.dp, bottom = 8.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            Text(text = subtitle, fontSize = 12.sp, color = colors.textTertiary)
        }
        queuedStateLabel(action.state)?.let { (label, tone) -> StatusBadge(text = label, tone = tone) }
        if (action.canUndo) {
            Text(
                text = stringResource(R.string.offline_stock_undo),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.cautionInk,
                modifier = Modifier
                    .heightIn(min = 44.dp)
                    .clip(ControlShape)
                    .clickable(role = Role.Button, onClick = onUndo)
                    .padding(horizontal = 10.dp, vertical = 12.dp),
            )
        }
    }
}
