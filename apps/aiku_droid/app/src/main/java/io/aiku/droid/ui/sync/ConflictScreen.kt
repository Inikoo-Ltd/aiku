package io.aiku.droid.ui.sync

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.data.api.OfflineRecordKind
import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.momentText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ConflictScreen(viewModel: ConflictViewModel, title: String) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val navigator = LocalAppNavigator.current
    val operation = state.operation

    LifecycleEventEffect(Lifecycle.Event.ON_START) { viewModel.onStart() }
    ConflictEvents(event = state.event, onHandled = viewModel::eventHandled)

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = viewModel::signInAgain)
        return
    }

    AppScreen(
        header = {
            AppHeader(
                title = operation?.labels?.title?.ifBlank { null } ?: title,
                kicker = operation?.let { entryOperationLabel(it) } ?: stringResource(R.string.sync_conflicts_title),
            )
        },
        activeTab = MainTab.Menu,
        footer = conflictFooter(
            state = state,
            onResolve = viewModel::resolve,
            onRollback = { viewModel.rollback() },
            onBack = navigator::back,
        ),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.offline_stock_conflicts_failed), onRetry = viewModel::retry) }
        when {
            state.isGone && operation == null -> InlineAlert(title = stringResource(R.string.offline_stock_conflict_gone), tone = AlertTone.Info)
            operation != null -> ConflictDetails(operation = operation, state = state)
            state.problem == null -> SkeletonListCard(rows = 3)
        }
    }
}

@Composable
private fun ConflictEvents(event: ConflictEvent?, onHandled: () -> Unit) {
    val toasts = LocalToasts.current
    val resolvedText = stringResource(R.string.offline_stock_resolved)
    val rolledBackText = stringResource(R.string.offline_stock_rolled_back)
    val problemTexts = GoodsOutProblem.entries.associateWith { problemText(it) }

    LaunchedEffect(event) {
        when (event) {
            is ConflictEvent.Resolved -> toasts.show(resolvedText, ToastKind.Success)
            ConflictEvent.RolledBack -> toasts.show(rolledBackText, ToastKind.Success)
            is ConflictEvent.Refused -> toasts.show(event.message.ifBlank { problemTexts.getValue(event.problem) }, ToastKind.Error)
            null -> return@LaunchedEffect
        }
        onHandled()
    }
}

private fun conflictFooter(
    state: ConflictUiState,
    onResolve: (OfflineResolution) -> Unit,
    onRollback: () -> Unit,
    onBack: () -> Unit,
): (@Composable ColumnScope.() -> Unit)? {
    val operation = state.operation ?: return null
    if (state.isClosed) {
        return {
            AikuButton(text = stringResource(R.string.offline_stock_back_to_list), onClick = onBack, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
        }
    }
    val resolutions = operation.allowedResolutions
    if (resolutions.isEmpty() && !operation.canRollback) {
        return null
    }
    return {
        resolutions.forEachIndexed { index, resolution ->
            AikuButton(
                text = resolutionLabel(resolution),
                onClick = { onResolve(resolution) },
                style = if (index == 0) ButtonStyle.Primary else ButtonStyle.Outline,
                isLoading = state.working == resolution,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (operation.canRollback) {
            AikuButton(
                text = stringResource(R.string.offline_stock_rollback),
                onClick = onRollback,
                style = ButtonStyle.Caution,
                isLoading = state.working == RollbackRequest,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun ConflictDetails(operation: OfflineOperationEntry, state: ConflictUiState) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val zone = organisationZone()
    val instruction = instructionText(operation)
    val message = operation.message?.takeIf { it.isNotBlank() }
    val yours = listOfNotNull(
        listOfNotNull(entryOperationLabel(operation), operation.requestedQuantity?.let { quantityText(it) }).joinToString(" "),
        operation.labels.subtitle?.takeIf { it.isNotBlank() },
        operation.labels.locationCode?.takeIf { it.isNotBlank() },
        momentText(operation.performedAt, zone),
    ) + operation.countedRows.mapNotNull { row ->
        row.counted?.let { stringResource(R.string.offline_stock_row_count, row.locationCode.orEmpty(), quantityText(it)) }
    }
    val aiku = listOfNotNull(
        operation.appliedQuantity?.let { stringResource(R.string.offline_stock_recorded, quantityText(it)) },
        stringResource(R.string.offline_stock_counted_since).takeIf { operation.countedSince },
        operation.conflictingUser?.name?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.offline_stock_changed_by, it) },
    ) + operation.countedRows.mapNotNull { row ->
        row.quantity?.let { stringResource(R.string.offline_stock_row_count, row.locationCode.orEmpty(), quantityText(it)) }
    }

    entryStateLabel(operation)?.let { (label, tone) -> Row { StatusBadge(text = label, tone = tone) } }
    if (instruction != null) {
        InlineAlert(title = instruction, body = message, tone = AlertTone.Warn)
    } else if (message != null) {
        InlineAlert(title = message, tone = AlertTone.Info)
    }
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
        CompareCard(title = stringResource(R.string.sync_yours), lines = yours, background = colors.soft, ink = colors.link, modifier = Modifier.weight(1f))
        CompareCard(title = stringResource(R.string.sync_now_in_aiku), lines = aiku, background = colors.background, ink = colors.textSecondary, modifier = Modifier.weight(1f))
    }
    reasonText(operation.reason)?.let { Text(text = it, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textSecondary, modifier = Modifier.padding(horizontal = 4.dp)) }
    operation.taskReference?.takeIf { it.isNotBlank() }?.let { Row { StatusBadge(text = stringResource(R.string.offline_stock_task, it), tone = Tone.Primary) } }
    operation.blockedBySequence?.let {
        Text(text = stringResource(R.string.offline_stock_blocked_by, it), fontSize = 13.sp, color = colors.textTertiary, modifier = Modifier.padding(horizontal = 4.dp))
    }
    if (state.resolvedWith == OfflineResolution.Recount) {
        recountTarget(operation)?.let { (route, code) ->
            AikuButton(
                text = stringResource(R.string.offline_stock_open_record, code),
                onClick = { navigator.open(route) },
                style = ButtonStyle.Outline,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
    if (state.ranAgain.isNotEmpty()) {
        GroupLabel(text = stringResource(R.string.offline_stock_ran_again))
        ListGroup {
            state.ranAgain.forEachIndexed { index, result ->
                if (index > 0) {
                    RowDivider()
                }
                ListRow(
                    title = result.labels.title.ifBlank { entryOperationLabel(result) },
                    subtitle = result.message?.takeIf { it.isNotBlank() },
                    badges = { entryStateLabel(result)?.let { (label, tone) -> StatusBadge(text = label, tone = tone) } },
                    showChevron = false,
                )
            }
        }
    }
}

private fun recountTarget(operation: OfflineOperationEntry): Pair<Any, String>? {
    val record = operation.record ?: return null
    val code = operation.labels.locationCode?.takeIf { it.isNotBlank() } ?: operation.labels.title
    return when (record.type) {
        OfflineRecordKind.LOCATION -> LocationRoute(id = record.id, code = code) to code
        OfflineRecordKind.ORG_STOCK -> SkuRoute(id = record.id, code = operation.labels.title) to operation.labels.title
        else -> null
    }
}

@Composable
private fun CompareCard(title: String, lines: List<String>, background: Color, ink: Color, modifier: Modifier = Modifier) {
    Column(
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier.clip(ControlShape).background(background).padding(horizontal = 10.dp, vertical = 8.dp),
    ) {
        Text(text = title, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = ink)
        lines.forEach { line -> Text(text = line, fontSize = 12.sp, lineHeight = 17.sp, color = ink) }
    }
}
