package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.data.live.LiveChannelNames
import io.aiku.droid.ui.components.ActiveFiltersLine
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RecordRow
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SavedCopyNote
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SearchWithFilter
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.dateRangeText
import io.aiku.droid.ui.components.recordDate
import io.aiku.droid.ui.live.LIVE_BACKLOG_EVENTS
import io.aiku.droid.ui.live.LiveEffect
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.scan.ScanProblem
import io.aiku.droid.ui.states.ErrorCode
import io.aiku.droid.ui.states.ErrorHelp
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.flow.first

private const val LOAD_MORE_DISTANCE_DP = 600

@Composable
fun DeliveryNotesScreen(notes: DeliveryNotesViewModel) {
    val state by notes.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { notes.onStart() }
    LiveEffect(channel = LiveChannelNames.dispatchingBacklog(LocalWarehouse.current.organisation.id), events = LIVE_BACKLOG_EVENTS) { notes.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = notes::signInAgain)
        } else {
            DeliveryNotesContent(state = state, notes = notes)
        }
    }
}

@Composable
private fun DeliveryNotesContent(state: DeliveryNotesUiState, notes: DeliveryNotesViewModel) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val scanFeedback = LocalScanController.current.feedback
    val scrollState = rememberScrollState()
    var showFilter by remember { mutableStateOf(false) }
    val filter = state.filter
    val list = state.list
    val selection = state.selection
    val target = state.addToSession
    val problemTexts = GoodsOutProblem.entries.associateWith { problemText(it) }
    val stateOptions = stageBuckets.map { (stage, bucket) ->
        val label = stringResource(stage.label)
        val count = state.counts?.count(bucket, filter.channel)
        FilterOption(label = if (count == null) label else stringResource(R.string.goodsout_state_count, label, count))
    }
    val selectedStateIndex = stageBuckets.indexOfFirst { it.second == filter.bucket }
    val noNoteText = (state.scanOutcome as? ListScanOutcome.NoDeliveryNote)?.let { stringResource(R.string.goodsout_scan_no_delivery_note, it.code) }
    val scanFailedText = (state.scanOutcome as? ListScanOutcome.Failed)?.let { failed ->
        when (failed.problem) {
            ScanProblem.Offline -> stringResource(R.string.scan_failed_offline, failed.code)
            ScanProblem.Forbidden -> stringResource(R.string.scan_failed_forbidden, failed.code)
            ScanProblem.ServerError -> stringResource(R.string.scan_failed_server, failed.code)
        }
    }

    ScanEffect(enabled = !state.isSelecting) { scan -> notes.lookUpScan(scan.code) }
    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.notes.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = notes::loadMore)
    LaunchedEffect(state.selectionOutcome) {
        when (val outcome = state.selectionOutcome) {
            is SelectionOutcome.OpenSession -> navigator.open(outcome.route)
            is SelectionOutcome.NotesAdded -> {
                outcome.message?.let { toasts.show(it, ToastKind.Success) }
                navigator.back()
            }
            is SelectionOutcome.Refused -> toasts.show(outcome.message.ifBlank { problemTexts.getValue(outcome.problem) }, ToastKind.Error)
            null -> return@LaunchedEffect
        }
        notes.selectionOutcomeHandled()
    }
    LaunchedEffect(state.scanOutcome) {
        when (val outcome = state.scanOutcome) {
            is ListScanOutcome.Open -> navigator.open(deliveryNoteRoute(id = outcome.note.id, reference = outcome.note.reference, state = outcome.note.state))
            is ListScanOutcome.NoDeliveryNote -> {
                scanFeedback.rejected()
                noNoteText?.let { toasts.show(it, ToastKind.Info) }
            }
            is ListScanOutcome.Failed -> scanFailedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        notes.scanOutcomeHandled()
    }

    val searchAction: @Composable () -> Unit = {
        HeaderIconButton(
            icon = R.drawable.ic_ui_search,
            description = stringResource(R.string.goodsout_search_and_filter),
            onClick = { showFilter = true },
            filled = true,
            badgeCount = filter.panelCount,
        )
    }

    AppScreen(
        header = {
            when {
                target != null -> AppHeader(
                    title = stringResource(R.string.goodsout_extra_add_delivery_notes),
                    kicker = stringResource(R.string.goodsout_extra_session_kicker, target.reference),
                    actions = { searchAction() },
                )
                selection != null -> ModuleHeader(
                    section = WarehouseSection.DeliveryNotes,
                    title = pluralStringResource(R.plurals.goodsout_selected_count, selection.size, selection.size),
                    actions = {
                        HeaderIconButton(icon = R.drawable.ic_ui_close, description = stringResource(R.string.goodsout_selection_cancel), onClick = notes::clearSelection, filled = true)
                    },
                )
                else -> ModuleHeader(section = WarehouseSection.DeliveryNotes, actions = { searchAction() })
            }
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        footer = selection?.let { selected -> { SelectionFooter(state = state, selected = selected.size, notes = notes) } },
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = notes::refresh, isEnabled = !state.isSubmittingSelection),
    ) {
        FilterChips(
            options = stateOptions,
            selectedIndex = selectedStateIndex,
            onSelect = { index -> notes.selectBucket(stageBuckets[index].second) },
        )
        SearchWithFilter(filterCount = filter.panelCount, onFilter = { showFilter = true }) { modifier ->
            SearchField(
                value = filter.search,
                onValueChange = notes::search,
                placeholder = stringResource(R.string.goodsout_filter_search),
                modifier = modifier,
            )
        }
        if (filter.isFiltered) {
            ActiveFilters(filter = filter, onClear = notes::clearFilters)
        }
        state.lookingUpCode?.let { code ->
            InlineAlert(title = stringResource(R.string.scan_looking_up, code), tone = AlertTone.Info)
        }
        list.firstPageProblem?.let { problem ->
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_list_failed_title), onRetry = notes::retry)
        }
        state.lastUpdated?.let { savedAt -> SavedCopyNote(savedAt = savedAt) }
        when {
            list.notes.isNotEmpty() -> ListGroup {
                list.notes.forEachIndexed { index, note ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val open = { navigator.open(deliveryNoteRoute(id = note.id, reference = note.reference, state = note.state)) }
                    val canSelect = note.canAddToPickingSession
                    DeliveryNoteListRow(
                        note = note,
                        isSelected = selection?.contains(note.id)?.takeIf { canSelect },
                        onClick = if (selection != null && canSelect) ({ notes.toggleSelection(note) }) else open,
                        onLongClick = if (selection == null && canSelect) ({ notes.startSelection(note) }) else null,
                    )
                }
            }
            list.hasLoaded && (list.loadingPage == null || state.isRefreshing) -> EmptyState(
                icon = R.drawable.ic_ui_delivery_note,
                title = stringResource(R.string.goodsout_list_empty_title),
                body = stringResource(if (filter.isFiltered) R.string.goodsout_list_empty_filtered_body else R.string.goodsout_list_empty_body),
            )
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_list_more_failed_title), onRetry = notes::loadMore)
        }
        if (selection == null && list.notes.any { it.canAddToPickingSession }) {
            GoodsOutFootNote(text = stringResource(R.string.goodsout_selection_hint))
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_delivery_notes_note))
    }
    if (showFilter) {
        DeliveryNoteFilterSheet(
            filter = filter,
            totalFor = state::totalFor,
            onDraftChange = notes::previewTotal,
            onApply = { applied ->
                showFilter = false
                notes.applyFilter(applied)
            },
            onDismiss = { showFilter = false },
        )
    }
}

@Composable
private fun SelectionFooter(state: DeliveryNotesUiState, selected: Int, notes: DeliveryNotesViewModel) {
    val target = state.addToSession
    if (selected == 0) {
        FooterStatus(title = stringResource(R.string.goodsout_selection_empty_title), body = stringResource(R.string.goodsout_selection_empty_body))
        return
    }
    AikuButton(
        text = if (target == null) {
            pluralStringResource(R.plurals.goodsout_create_session_from_selected, selected, selected)
        } else {
            pluralStringResource(R.plurals.goodsout_add_to_session, selected, selected, target.reference)
        },
        onClick = notes::submitSelection,
        icon = R.drawable.ic_ui_plus,
        isLoading = state.isSubmittingSelection,
        height = 52.dp,
        modifier = Modifier.fillMaxWidth(),
    )
    if (target == null) {
        AikuButton(
            text = stringResource(R.string.goodsout_selection_cancel),
            onClick = notes::clearSelection,
            style = ButtonStyle.Outline,
            height = 48.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun SelectionMark(isSelected: Boolean) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(24.dp)
            .background(if (isSelected) colors.primary else colors.surface, CircleShape)
            .border(2.dp, if (isSelected) colors.primary else colors.borderStrong, CircleShape),
    ) {
        if (isSelected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.onPrimary, modifier = Modifier.size(16.dp))
        }
    }
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun DeliveryNoteListRow(note: DeliveryNoteRow, isSelected: Boolean?, onClick: () -> Unit, onLongClick: (() -> Unit)?) {
    val stage = stageOf(note.state)

    RecordRow(
        reference = note.reference,
        date = recordDate(note.date, organisationZone()),
        party = note.customerName,
        details = listOfNotNull(
            channelName(note.channel, note.shop?.name),
            if (note.isCollection) stringResource(R.string.goodsout_collection) else null,
            pluralStringResource(R.plurals.goodsout_items_count, note.numberItems, note.numberItems),
            weightText(note.estimatedWeightKg),
        ),
        modifier = Modifier
            .then(if (isSelected != null) Modifier.semantics { selected = isSelected } else Modifier)
            .combinedClickable(role = if (isSelected != null) Role.Checkbox else Role.Button, onLongClick = onLongClick, onClick = onClick),
        leading = isSelected?.let { selectedNow -> { SelectionMark(isSelected = selectedNow) } },
        badges = {
            StatusBadge(text = note.stateLabel.ifBlank { stage?.let { stringResource(it.label) }.orEmpty() }, tone = stageTone(stage))
            if (note.isPriority) {
                StatusBadge(text = stringResource(R.string.goodsout_badge_priority), tone = Tone.Orange)
            }
            if (note.isCustomerVip) {
                StatusBadge(text = stringResource(R.string.goodsout_badge_vip), tone = Tone.Lime)
            }
            if (note.waitingCount > 0) {
                StatusBadge(text = pluralStringResource(R.plurals.goodsout_badge_waiting, note.waitingCount, note.waitingCount), tone = Tone.Warn)
            }
        },
    )
}

@Composable
private fun ActiveFilters(filter: DeliveryNoteFilter, onClear: () -> Unit) {
    val parts = listOfNotNull(
        filter.search.trim().takeIf { it.isNotEmpty() }?.let { stringResource(R.string.goodsout_filter_search_quoted, it) },
        filter.channel.takeIf { it != DispatchChannel.All }?.let { stringResource(channelLabel(it)) },
        filter.picker?.let { stringResource(pickerLabel(it)) },
        filter.date?.let { stringResource(dateLabel(it)) },
        dateRangeText(filter.range)?.let { stringResource(R.string.filter_labelled, stringResource(R.string.goodsout_filter_date), it) },
        filter.sort?.let { stringResource(R.string.goodsout_sorted_by, stringResource(sortLabel(it)).lowercase()) },
    )

    ActiveFiltersLine(parts = parts, onClear = onClear)
}

@Composable
internal fun GoodsOutLoadProblem(problem: GoodsOutProblem, title: String, onRetry: () -> Unit, modifier: Modifier = Modifier) {
    InlineAlert(
        title = title,
        body = stringResource(
            when (problem) {
                GoodsOutProblem.Offline -> R.string.goodsout_load_offline_body
                GoodsOutProblem.Forbidden -> R.string.goodsout_load_forbidden_body
                GoodsOutProblem.NotFound -> R.string.goodsout_load_not_found_body
                GoodsOutProblem.ServerError -> R.string.goodsout_load_server_body
            },
        ),
        tone = AlertTone.Warn,
        actionLabel = if (problem == GoodsOutProblem.Forbidden) null else stringResource(R.string.goodsout_try_again),
        onAction = onRetry,
        modifier = modifier,
        footer = { ErrorHelp(code = problem.errorCode, modifier = Modifier.padding(top = 4.dp)) },
    )
}

@Composable
internal fun LoadingMore() {
    val colors = LocalAikuColors.current
    val loading = stringResource(R.string.loading)

    Box(contentAlignment = Alignment.Center, modifier = Modifier.fillMaxWidth().heightIn(min = 44.dp).semantics { contentDescription = loading }) {
        CircularProgressIndicator(color = colors.link, strokeWidth = 2.5.dp, modifier = Modifier.size(22.dp))
    }
}

@Composable
internal fun LoadMoreOnScroll(scrollState: ScrollState, itemCount: Int, canLoadMore: Boolean, onLoadMore: () -> Unit) {
    val currentOnLoadMore by rememberUpdatedState(onLoadMore)
    val distance = with(LocalDensity.current) { LOAD_MORE_DISTANCE_DP.dp.roundToPx() }

    LaunchedEffect(scrollState, itemCount, canLoadMore) {
        if (canLoadMore) {
            snapshotFlow { scrollState.maxValue - scrollState.value <= distance }.first { it }
            currentOnLoadMore()
        }
    }
}
