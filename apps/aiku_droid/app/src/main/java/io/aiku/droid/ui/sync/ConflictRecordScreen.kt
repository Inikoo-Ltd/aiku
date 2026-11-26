package io.aiku.droid.ui.sync

import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.inFooter
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.navigation.ConflictRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ConflictRecordScreen(viewModel: ConflictRecordViewModel, title: String) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val group = state.group

    LifecycleEventEffect(Lifecycle.Event.ON_START) { viewModel.onStart() }
    ConflictRecordEvents(event = state.event, onHandled = viewModel::eventHandled)

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = viewModel::signInAgain)
        return
    }

    AppScreen(
        header = { AppHeader(title = title, kicker = stringResource(R.string.sync_conflicts_title)) },
        activeTab = MainTab.Menu,
        showNeedYou = false,
        refresh = if (state.hasResults) null else PullRefresh(isRefreshing = state.isRefreshing, onRefresh = viewModel::refresh),
        footer = recordFooter(state = state, onResolveAll = viewModel::resolveAll, onBack = navigator::back),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.offline_stock_conflicts_failed), onRetry = viewModel::retry) }
        when {
            state.hasResults -> LineResults(results = state.results)
            state.hasLoaded && state.lines.isEmpty() -> InlineAlert(title = stringResource(R.string.offline_stock_conflicts_empty_title), tone = AlertTone.Info)
            state.lines.isNotEmpty() -> {
                Text(
                    text = pluralStringResource(R.plurals.sync_conflicts_record_lines, state.lines.size, state.lines.size),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                    modifier = Modifier.padding(horizontal = 4.dp),
                )
                ListGroup {
                    state.lines.forEachIndexed { index, line ->
                        if (index > 0) {
                            RowDivider()
                        }
                        OperationRow(
                            operation = line,
                            showItem = true,
                            onOpen = { id -> navigator.open(ConflictRoute(id = id, title = line.labels.title)) },
                        )
                    }
                }
                if (group.bulkResolutions.isNotEmpty()) {
                    Text(
                        text = stringResource(R.string.sync_conflicts_bulk_note),
                        fontSize = 12.sp,
                        lineHeight = 18.sp,
                        color = colors.textQuaternary,
                        modifier = Modifier.padding(horizontal = 4.dp),
                    )
                }
            }
            state.problem == null -> SkeletonListCard(rows = 3)
        }
    }
}

@Composable
private fun ConflictRecordEvents(event: ConflictRecordEvent?, onHandled: () -> Unit) {
    val toasts = LocalToasts.current
    val text = event?.let { pluralStringResource(R.plurals.sync_conflicts_resolved_count, it.total, it.resolved, it.total) }

    LaunchedEffect(event) {
        if (event == null || text == null) {
            return@LaunchedEffect
        }
        toasts.show(text, if (event.resolved == event.total) ToastKind.Success else ToastKind.Error)
        onHandled()
    }
}

private fun recordFooter(
    state: ConflictRecordUiState,
    onResolveAll: (OfflineResolution) -> Unit,
    onBack: () -> Unit,
): (@Composable ColumnScope.() -> Unit)? {
    if (state.hasResults) {
        return {
            AikuButton(text = stringResource(R.string.offline_stock_back_to_list), onClick = onBack, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
        }
    }
    val resolutions = state.group.bulkResolutions.sortedBy { RESOLUTION_ORDER.indexOf(it) }
    if (resolutions.isEmpty()) {
        return null
    }
    val looks = resolutions.map(::resolutionLook)
    val mainIndex = looks.indexOfFirst { it.style == ButtonStyle.Primary || it.style == ButtonStyle.Finish }
    val count = state.lines.size
    return {
        resolutions.forEachIndexed { index, resolution ->
            val look = looks[index].inFooter(isFirst = index == mainIndex)
            AikuButton(
                text = pluralStringResource(R.plurals.sync_conflicts_resolve_all, count, resolutionLabel(resolution), count),
                onClick = { onResolveAll(resolution) },
                style = look.style,
                icon = look.icon,
                isLoading = state.working == resolution,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun LineResults(results: List<ConflictLineResult>) {
    GroupLabel(text = stringResource(R.string.sync_conflicts_results))
    ListGroup {
        results.forEachIndexed { index, result ->
            if (index > 0) {
                RowDivider()
            }
            val outcome = result.outcome
            val failure = result.message.ifBlank { result.problem?.let { problemText(it) }.orEmpty() }
            ListRow(
                title = result.line.labels.title.ifBlank { entryOperationLabel(result.line) },
                subtitle = if (outcome != null) result.line.labels.subtitle else failure.ifBlank { null },
                badges = {
                    if (outcome != null) {
                        entryStateLabel(outcome)?.let { (label, tone) -> StatusBadge(text = label, tone = tone) }
                    } else {
                        StatusBadge(text = stringResource(R.string.sync_conflicts_line_not_done), tone = Tone.Danger)
                    }
                },
                showChevron = false,
            )
        }
    }
}
