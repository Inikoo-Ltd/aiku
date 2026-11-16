package io.aiku.droid.ui.goodsout

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DeliveryNoteCounts
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.DeliveryNoteSort
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutPagination
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.PickingSessionWrite
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.extra.PickingSessionHandoff
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.scan.ScanDestination
import io.aiku.droid.ui.scan.ScanProblem
import io.aiku.droid.ui.scan.destination
import io.aiku.droid.ui.scan.scanProblem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.LocalDate

private const val PREVIEW_DEBOUNCE_MILLIS = 300L
private const val LAST_WEEK_DAYS = 6L

enum class DateFilter {
    Today,
    Yesterday,
    LastSevenDays,
    ;

    fun range(today: LocalDate): ClosedRange<LocalDate> = when (this) {
        Today -> today..today
        Yesterday -> today.minusDays(1)..today.minusDays(1)
        LastSevenDays -> today.minusDays(LAST_WEEK_DAYS)..today
    }
}

fun defaultSortOf(bucket: DeliveryNoteBucket): DeliveryNoteSort =
    if (bucket == DeliveryNoteBucket.Dispatched || bucket == DeliveryNoteBucket.All) DeliveryNoteSort.DateNewest else DeliveryNoteSort.DateOldest

data class DeliveryNoteFilter(
    val bucket: DeliveryNoteBucket = DeliveryNoteBucket.Unassigned,
    val channel: DispatchChannel = DispatchChannel.All,
    val picker: PickerFilter? = null,
    val date: DateFilter? = null,
    val sort: DeliveryNoteSort? = null,
    val search: String = "",
) {
    val shownSort: DeliveryNoteSort get() = sort ?: defaultSortOf(bucket)

    val activeFilterCount: Int
        get() = listOf(channel != DispatchChannel.All, picker != null, date != null, search.isNotBlank()).count { it }

    val isFiltered: Boolean get() = activeFilterCount > 0 || sort != null

    fun withSort(chosen: DeliveryNoteSort): DeliveryNoteFilter = copy(sort = chosen.takeIf { it != defaultSortOf(bucket) })

    fun cleared(): DeliveryNoteFilter = DeliveryNoteFilter(bucket = bucket)

    fun query(today: LocalDate, page: Int, perPage: Int): DeliveryNoteQuery {
        val range = date?.range(today)
        return DeliveryNoteQuery(
            bucket = bucket,
            channel = channel,
            search = search.trim().takeIf { it.isNotEmpty() },
            from = range?.start,
            to = range?.endInclusive,
            picker = picker,
            sort = sort,
            page = page,
            perPage = perPage,
        )
    }
}

enum class GoodsOutProblem { Offline, ServerError, Forbidden, NotFound }

internal fun GoodsOutResult<*>.problem(): GoodsOutProblem = when (this) {
    GoodsOutResult.Unreachable -> GoodsOutProblem.Offline
    is GoodsOutResult.Forbidden -> GoodsOutProblem.Forbidden
    is GoodsOutResult.NotFound -> GoodsOutProblem.NotFound
    else -> GoodsOutProblem.ServerError
}

data class DeliveryNoteList(
    val notes: List<DeliveryNoteRow> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 0,
    val total: Int? = null,
    val loadingPage: Int? = null,
    val problem: GoodsOutProblem? = null,
    val failedPage: Int? = null,
) {
    val hasLoaded: Boolean get() = page > 0
    val hasMore: Boolean get() = hasLoaded && page < lastPage
    val canLoadMore: Boolean get() = hasMore && loadingPage == null
    val canLoadMoreOnScroll: Boolean get() = canLoadMore && problem == null
    val isLoadingMore: Boolean get() = (loadingPage ?: 0) > 1
    val firstPageProblem: GoodsOutProblem? get() = problem?.takeIf { failedPage == 1 }
    val nextPageProblem: GoodsOutProblem? get() = problem?.takeIf { (failedPage ?: 0) > 1 }

    fun loading(page: Int): DeliveryNoteList = copy(loadingPage = page, problem = null, failedPage = null)

    fun loaded(pageNotes: List<DeliveryNoteRow>, pagination: GoodsOutPagination): DeliveryNoteList {
        val loadedPage = pagination.currentPage
        val merged = if (loadedPage <= 1) pageNotes else (notes + pageNotes).distinctBy { it.id }
        return DeliveryNoteList(notes = merged, page = loadedPage, lastPage = pagination.lastPage, total = pagination.total)
    }

    fun failed(page: Int, problem: GoodsOutProblem): DeliveryNoteList = copy(loadingPage = null, problem = problem, failedPage = page)
}

data class FilterPreview(val filter: DeliveryNoteFilter, val total: Int)

sealed interface ListScanOutcome {
    data class Open(val note: DeliveryNoteRow) : ListScanOutcome
    data class NoDeliveryNote(val code: String) : ListScanOutcome
    data class Failed(val code: String, val problem: ScanProblem) : ListScanOutcome
}

data class SessionTarget(val id: Long, val reference: String)

sealed interface SelectionOutcome {
    data class OpenSession(val route: PickingSessionRoute) : SelectionOutcome
    data class NotesAdded(val message: String?) : SelectionOutcome
    data class Refused(val message: String, val problem: GoodsOutProblem) : SelectionOutcome
}

data class DeliveryNotesUiState(
    val filter: DeliveryNoteFilter = DeliveryNoteFilter(),
    val list: DeliveryNoteList = DeliveryNoteList(),
    val canWorkInWarehouse: Boolean = false,
    val counts: DeliveryNoteCounts? = null,
    val preview: FilterPreview? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: ListScanOutcome? = null,
    val isSessionExpired: Boolean = false,
    val addToSession: SessionTarget? = null,
    val selection: Set<Long>? = if (addToSession != null) emptySet() else null,
    val isSubmittingSelection: Boolean = false,
    val selectionOutcome: SelectionOutcome? = null,
) {
    val isSelecting: Boolean get() = selection != null

    fun totalFor(draft: DeliveryNoteFilter): Int? = when {
        draft == filter && list.hasLoaded && list.loadingPage == null -> list.total
        else -> preview?.takeIf { it.filter == draft }?.total
    }
}

class DeliveryNotesViewModel(
    private val api: GoodsOutApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val today: () -> LocalDate,
    private val pickingSessions: PickingSessionApi,
    initialFilter: DeliveryNoteFilter = DeliveryNoteFilter(),
    addToSession: SessionTarget? = null,
    private val perPage: Int = DeliveryNoteQuery.DEFAULT_PER_PAGE,
    private val sessionHandoff: PickingSessionHandoff = PickingSessionHandoff(),
) : ViewModel() {

    private val mutableState = MutableStateFlow(DeliveryNotesUiState(filter = initialFilter, addToSession = addToSession))
    val state: StateFlow<DeliveryNotesUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var countsJob: Job? = null
    private var previewJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        when {
            !list.hasLoaded && list.loadingPage == null -> {
                loadCounts()
                loadPage(1)
            }
            list.hasLoaded && list.loadingPage == null -> {
                loadCounts()
                if (list.page <= 1) {
                    loadPage(1)
                }
            }
        }
    }

    fun selectBucket(bucket: DeliveryNoteBucket) {
        if (bucket != mutableState.value.filter.bucket) {
            applyFilter(mutableState.value.filter.copy(bucket = bucket))
        }
    }

    fun applyFilter(filter: DeliveryNoteFilter) {
        val previous = mutableState.value.filter
        val isPickerChanged = filter.picker != previous.picker
        mutableState.update { it.copy(filter = filter, list = DeliveryNoteList(), counts = if (isPickerChanged) null else it.counts) }
        if (isPickerChanged) {
            loadCounts()
        }
        loadPage(1)
    }

    fun clearFilters() = applyFilter(mutableState.value.filter.cleared())

    fun loadMore() {
        val list = mutableState.value.list
        if (list.canLoadMore) {
            loadPage(list.page + 1)
        }
    }

    fun retry() {
        val list = mutableState.value.list
        loadPage(list.failedPage ?: 1)
        if (mutableState.value.counts == null) {
            loadCounts()
        }
    }

    fun previewTotal(draft: DeliveryNoteFilter) {
        previewJob?.cancel()
        val current = mutableState.value
        if (current.totalFor(draft) != null) {
            return
        }
        val token = currentToken() ?: return
        previewJob = viewModelScope.launch {
            delay(PREVIEW_DEBOUNCE_MILLIS)
            val result = api.deliveryNotes(token, warehouseId, draft.query(today(), page = 1, perPage = 1))
            if (result is GoodsOutResult.Loaded) {
                mutableState.update { it.copy(preview = FilterPreview(draft, result.value.pagination.total)) }
            }
        }
    }

    fun lookUpScan(code: String) {
        val scanned = code.trim()
        val token = currentToken() ?: return
        if (scanned.isEmpty() || mutableState.value.lookingUpCode != null) {
            return
        }
        mutableState.update { it.copy(lookingUpCode = scanned, scanOutcome = null) }

        viewModelScope.launch {
            val outcome = when (val result = api.scan(token, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> when (val destination = result.value.destination(scanned)) {
                    is ScanDestination.DeliveryNote -> ListScanOutcome.Open(destination.note)
                    else -> ListScanOutcome.NoDeliveryNote(scanned)
                }
                is GoodsOutResult.NotFound -> ListScanOutcome.NoDeliveryNote(scanned)
                GoodsOutResult.SessionExpired -> null.also { expireSession() }
                else -> ListScanOutcome.Failed(scanned, result.scanProblem())
            }
            mutableState.update { it.copy(lookingUpCode = null, scanOutcome = outcome) }
        }
    }

    fun scanOutcomeHandled() {
        mutableState.update { it.copy(scanOutcome = null) }
    }

    fun startSelection(note: DeliveryNoteRow) {
        if (note.canAddToPickingSession && !mutableState.value.isSubmittingSelection) {
            mutableState.update { it.copy(selection = it.selection.orEmpty() + note.id) }
        }
    }

    fun toggleSelection(note: DeliveryNoteRow) {
        val current = mutableState.value
        val selection = current.selection ?: return
        if (current.isSubmittingSelection || !note.canAddToPickingSession) {
            return
        }
        val toggled = if (note.id in selection) selection - note.id else selection + note.id
        mutableState.update { it.copy(selection = toggled.takeIf { chosen -> chosen.isNotEmpty() || it.addToSession != null }) }
    }

    fun clearSelection() {
        mutableState.update { it.copy(selection = if (it.addToSession != null) emptySet() else null) }
    }

    fun submitSelection() {
        val current = mutableState.value
        val ids = current.selection?.toList()?.takeIf { it.isNotEmpty() } ?: return
        val token = currentToken() ?: return
        if (current.isSubmittingSelection) {
            return
        }
        mutableState.update { it.copy(isSubmittingSelection = true, selectionOutcome = null) }

        viewModelScope.launch {
            val target = current.addToSession
            val outcome = if (target == null) {
                when (val result = pickingSessions.createPickingSession(token, warehouseId, ids)) {
                    is GoodsOutResult.Loaded -> {
                        sessionHandoff.hand(result.value)
                        SelectionOutcome.OpenSession(PickingSessionRoute(id = result.value.id, reference = result.value.reference))
                    }
                    else -> result.selectionRefusal()
                }
            } else {
                when (val result = pickingSessions.writeSession(token, target.id, PickingSessionWrite.AddDeliveryNotes(ids))) {
                    is GoodsOutResult.Loaded -> SelectionOutcome.NotesAdded(result.value.message)
                    else -> result.selectionRefusal()
                }
            }
            if (outcome == null) {
                mutableState.update { it.copy(isSubmittingSelection = false) }
                expireSession()
                return@launch
            }
            mutableState.update {
                it.copy(
                    isSubmittingSelection = false,
                    selectionOutcome = outcome,
                    selection = if (outcome is SelectionOutcome.Refused) it.selection else if (it.addToSession != null) emptySet() else null,
                )
            }
        }
    }

    fun selectionOutcomeHandled() {
        mutableState.update { it.copy(selectionOutcome = null) }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun loadPage(page: Int) {
        val token = currentToken() ?: return
        val filter = mutableState.value.filter
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.deliveryNotes(token, warehouseId, filter.query(today(), page, perPage))) {
                is GoodsOutResult.Loaded -> mutableState.update { current ->
                    current.copy(
                        list = current.list.loaded(result.value.notes, result.value.pagination),
                        canWorkInWarehouse = result.value.canWorkInWarehouse,
                    )
                }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }

    private fun loadCounts() {
        val token = currentToken() ?: return
        val picker = mutableState.value.filter.picker
        countsJob?.cancel()

        countsJob = viewModelScope.launch {
            when (val result = api.deliveryNoteCounts(token, warehouseId, picker)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(counts = result.value) }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> Unit
            }
        }
    }

    private fun currentToken(): String? = sessions.session.value?.token

    private fun GoodsOutResult<*>.selectionRefusal(): SelectionOutcome.Refused? {
        val message = when (this) {
            GoodsOutResult.SessionExpired -> return null
            is GoodsOutResult.Forbidden -> message
            is GoodsOutResult.NotFound -> message
            is GoodsOutResult.Invalid -> message
            else -> ""
        }
        return SelectionOutcome.Refused(message = message, problem = problem())
    }

    private fun expireSession() {
        mutableState.update { it.copy(isSessionExpired = true) }
    }
}
