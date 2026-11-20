package io.aiku.droid.ui.sync

import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.EmptyTone
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.ConflictRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ResolveConflictsScreen(viewModel: ResolveConflictsViewModel) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    LifecycleEventEffect(Lifecycle.Event.ON_START) { viewModel.onStart() }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = viewModel::signInAgain)
        return
    }

    AppScreen(
        header = { AppHeader(title = stringResource(R.string.sync_conflicts_title), kicker = stringResource(R.string.sync_title)) },
        activeTab = MainTab.Menu,
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.offline_stock_conflicts_failed), onRetry = viewModel::retry) }
        when {
            state.isEmpty -> EmptyState(
                icon = R.drawable.ic_ui_check,
                title = stringResource(R.string.offline_stock_conflicts_empty_title),
                body = stringResource(R.string.offline_stock_conflicts_empty_body),
                tone = EmptyTone.Success,
            )
            state.operations.isNotEmpty() -> ListGroup {
                state.operations.forEachIndexed { index, operation ->
                    if (index > 0) {
                        RowDivider()
                    }
                    OperationRow(operation = operation, onOpen = { id -> navigator.open(ConflictRoute(id = id, title = operation.labels.title)) })
                }
            }
            !state.hasLoaded && state.problem == null -> SkeletonListCard(rows = 3)
        }
        Text(
            text = stringResource(R.string.offline_stock_conflicts_note),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}

@Composable
private fun OperationRow(operation: OfflineOperationEntry, onOpen: (Long) -> Unit) {
    val id = operation.key
    val title = operation.labels.title.ifBlank { entryOperationLabel(operation) }
    val subtitle = listOfNotNull(
        instructionText(operation),
        reasonText(operation.reason),
        operation.message?.takeIf { it.isNotBlank() },
    ).joinToString(LIST_SEPARATOR)

    ListRow(
        title = title,
        subtitle = subtitle.ifBlank { null },
        badges = { entryStateLabel(operation)?.let { (label, tone) -> StatusBadge(text = label, tone = tone) } },
        onClick = id?.let { { onOpen(it) } },
    )
}
