package io.aiku.droid.ui.sync

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
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
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.inFooter
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.fractionGlyphs
import io.aiku.droid.ui.inventory.momentText
import io.aiku.droid.ui.inventory.stockFractionLabel
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.states.ErrorCode
import io.aiku.droid.ui.states.ErrorHelp
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
        showNeedYou = false,
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

internal val RESOLUTION_ORDER = listOf(
    OfflineResolution.Retry,
    OfflineResolution.Recount,
    OfflineResolution.PutBack,
    OfflineResolution.Done,
    OfflineResolution.KeepCount,
    OfflineResolution.Discard,
)

internal fun resolutionLook(resolution: OfflineResolution): ButtonLook = when (resolution) {
    OfflineResolution.Retry -> ButtonLook(ButtonStyle.Primary, R.drawable.ic_states_retry)
    OfflineResolution.Recount -> ButtonLook(ButtonStyle.Primary)
    OfflineResolution.PutBack -> ButtonLook(ButtonStyle.Primary)
    OfflineResolution.Done -> ButtonLook(ButtonStyle.Finish)
    OfflineResolution.KeepCount -> ButtonLook(ButtonStyle.Caution)
    OfflineResolution.Discard -> ButtonLook(ButtonStyle.Danger)
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
    val ordered = resolutions.sortedBy { RESOLUTION_ORDER.indexOf(it) }
    val looks = ordered.map(::resolutionLook)
    val mainIndex = looks.indexOfFirst { it.style == ButtonStyle.Primary || it.style == ButtonStyle.Finish }
    return {
        ordered.forEachIndexed { index, resolution ->
            val look = looks[index].inFooter(isFirst = index == mainIndex)
            AikuButton(
                text = resolutionLabel(resolution),
                onClick = { onResolve(resolution) },
                style = look.style,
                icon = look.icon,
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
    val instruction = instructionText(operation)
    val message = operation.message?.takeIf { it.isNotBlank() }

    if (instruction != null) {
        InlineAlert(title = instruction, body = message, tone = AlertTone.Warn)
    } else if (message != null) {
        InlineAlert(title = message, tone = AlertTone.Info)
    }
    ConflictItemCard(operation = operation)
    GroupLabel(text = stringResource(R.string.conflict_whats_different))
    DifferenceTable(rows = conflictDifferences(operation), packedIn = operation.item?.packedIn, actionTitle = entryOperationLabel(operation))
    reasonText(operation.reason)?.let { Text(text = it, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textSecondary, modifier = Modifier.padding(horizontal = 4.dp)) }
    ErrorCode.forSyncReason(operation.reason)?.let { ErrorHelp(code = it, modifier = Modifier.padding(horizontal = 4.dp)) }
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

private const val DIFFERENCE_LABEL_WEIGHT = 1.6f
private const val DIFFERENCE_NONE = "–"

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
private fun ConflictItemCard(operation: OfflineOperationEntry) {
    val colors = LocalAikuColors.current
    val zone = organisationZone()
    val item = operation.item
    val code = item?.skoCode ?: operation.labels.subtitle?.substringBefore(' ')
    val name = item?.skoName ?: operation.labels.subtitle?.substringAfter(' ', "")
    val quantities = item?.quantities
    val packedIn = item?.packedIn
    val recordLabel = when (item?.recordType) {
        "delivery_note" -> stringResource(R.string.mywork_kind_delivery_note)
        "return" -> stringResource(R.string.goodsin_kicker_return_plain)
        "stock_delivery" -> stringResource(R.string.goodsin_kicker_stock_delivery_plain)
        "location" -> stringResource(R.string.fulfilment_pallet_location)
        else -> null
    }
    val recordValue = item?.recordReference?.let { reference -> item.line?.let { "$reference · ${stringResource(R.string.conflict_line, it)}" } ?: reference }
        ?: operation.labels.title.takeIf { it.isNotBlank() }
    val facts = listOfNotNull(
        recordLabel?.let { label -> recordValue?.let { label to it } },
        operation.labels.locationCode?.takeIf { it.isNotBlank() && item?.recordType != "location" }?.let { stringResource(R.string.fulfilment_pallet_location) to it },
        quantities?.required?.let { stringResource(R.string.conflict_required) to conflictQuantity(it, packedIn) },
        quantities?.picked?.let { stringResource(R.string.dn_state_picked) to conflictQuantity(it, packedIn) },
        quantities?.notPicked?.takeIf { it > 0.0 }?.let { stringResource(R.string.goodsout_line_not_picked) to conflictQuantity(it, packedIn) },
        quantities?.packed?.takeIf { it > 0.0 }?.let { stringResource(R.string.dn_state_packed) to conflictQuantity(it, packedIn) },
        quantities?.stockNow?.let { stringResource(R.string.conflict_stock_now) to conflictQuantity(it, packedIn) },
        momentText(operation.performedAt, zone)?.let { stringResource(R.string.conflict_done_offline) to it },
        operation.conflictingUser?.name?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.conflict_changed_by) to it },
    )

    Column(modifier = Modifier.fillMaxWidth().clip(CardShape).background(colors.surface).border(1.dp, colors.border, CardShape)) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier.fillMaxWidth().padding(16.dp),
        ) {
            if (code != null) {
                ProductPhoto(code = code, imageUrl = item?.imageUrl, size = 64.dp)
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(
                    text = code ?: recordValue.orEmpty(),
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                name?.takeIf { it.isNotBlank() }?.let { Text(text = it, fontSize = 14.sp, lineHeight = 19.sp, color = colors.textSecondary) }
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    StatusBadge(text = entryOperationLabel(operation), tone = Tone.Primary)
                    entryStateLabel(operation)?.let { (label, tone) -> StatusBadge(text = label, tone = tone) }
                }
            }
        }
        facts.forEach { (label, value) ->
            HorizontalDivider(thickness = 1.dp, color = colors.border)
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth().heightIn(min = 44.dp).padding(horizontal = 16.dp, vertical = 10.dp),
            ) {
                Text(text = label, fontSize = 14.sp, color = colors.textTertiary, modifier = Modifier.weight(1f))
                Text(text = value, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text, textAlign = TextAlign.End)
            }
        }
    }
}

@Composable
private fun DifferenceTable(rows: List<ConflictDifference>, packedIn: Double?, actionTitle: String) {
    val colors = LocalAikuColors.current
    val headerStyle = Modifier.padding(horizontal = 4.dp)

    Column(modifier = Modifier.fillMaxWidth().clip(CardShape).background(colors.surface).border(1.dp, colors.border, CardShape)) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth().background(colors.surfaceMuted).padding(horizontal = 12.dp, vertical = 10.dp),
        ) {
            Spacer(modifier = Modifier.weight(DIFFERENCE_LABEL_WEIGHT))
            DifferenceHeader(text = stringResource(R.string.sync_yours), modifier = headerStyle.weight(1f))
            DifferenceHeader(text = stringResource(R.string.sync_now_in_aiku), modifier = headerStyle.weight(1f))
            DifferenceHeader(text = stringResource(R.string.goodsin_difference), modifier = headerStyle.weight(1f))
        }
        rows.forEach { row ->
            HorizontalDivider(thickness = 1.dp, color = colors.border)
            val difference = row.difference
            val differenceColor = when {
                difference == null -> colors.textTertiary
                difference == 0.0 -> colors.success
                else -> colors.warnInk
            }
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp).padding(horizontal = 12.dp, vertical = 10.dp),
            ) {
                Column(modifier = Modifier.weight(DIFFERENCE_LABEL_WEIGHT), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = row.title ?: actionTitle, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text, maxLines = 2, overflow = TextOverflow.Ellipsis)
                    row.subtitle?.let { Text(text = it, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis) }
                }
                DifferenceValue(text = row.yours?.let { conflictQuantity(it, packedIn) } ?: DIFFERENCE_NONE, color = colors.link, modifier = Modifier.weight(1f))
                DifferenceValue(text = row.aiku?.let { conflictQuantity(it, packedIn) } ?: DIFFERENCE_NONE, color = colors.text, modifier = Modifier.weight(1f))
                DifferenceValue(text = difference?.let { signedQuantity(it, packedIn) } ?: DIFFERENCE_NONE, color = differenceColor, modifier = Modifier.weight(1f))
            }
        }
    }
}

@Composable
private fun DifferenceHeader(text: String, modifier: Modifier) {
    Text(text = text, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = LocalAikuColors.current.textTertiary, textAlign = TextAlign.End, maxLines = 2, modifier = modifier)
}

@Composable
private fun DifferenceValue(text: String, color: Color, modifier: Modifier) {
    Text(text = text, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = color, textAlign = TextAlign.End, maxLines = 1, modifier = modifier.padding(horizontal = 4.dp))
}

private fun conflictQuantity(value: Double, packedIn: Double?): String = if (packedIn != null) fractionGlyphs(stockFractionLabel(value, packedIn)) else quantityText(value)

private fun signedQuantity(value: Double, packedIn: Double?): String = when {
    value > 0.0 -> "+" + conflictQuantity(value, packedIn)
    value < 0.0 -> "−" + conflictQuantity(-value, packedIn)
    else -> "0"
}
