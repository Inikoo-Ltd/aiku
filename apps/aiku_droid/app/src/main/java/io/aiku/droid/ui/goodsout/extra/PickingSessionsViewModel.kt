package io.aiku.droid.ui.goodsout.extra

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutPagination
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.PickingSessionQuery
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.PickingSessionSort
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.cache.SavedLists
import io.aiku.droid.data.cache.ScreenCache
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.components.ListFilter
import io.aiku.droid.ui.components.OfflineRows
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

data class PickingSessionList(
    val sessions: List<PickingSessionRow> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 0,
    val total: Int? = null,
    val loadingPage: Int? = null,
    val problem: GoodsOutProblem? = null,
    val failedPage: Int? = null,
) {
    val hasLoaded: Boolean get() = page > 0
    val canLoadMore: Boolean get() = hasLoaded && page < lastPage && loadingPage == null
    val canLoadMoreOnScroll: Boolean get() = canLoadMore && problem == null
    val isLoadingMore: Boolean get() = (loadingPage ?: 0) > 1
    val firstPageProblem: GoodsOutProblem? get() = problem?.takeIf { failedPage == 1 }
    val nextPageProblem: GoodsOutProblem? get() = problem?.takeIf { (failedPage ?: 0) > 1 }

    fun loading(page: Int): PickingSessionList = copy(loadingPage = page, problem = null, failedPage = null)

    fun loaded(rows: List<PickingSessionRow>, pagination: GoodsOutPagination): PickingSessionList {
        val loadedPage = pagination.currentPage
        val merged = if (loadedPage <= 1) rows else (sessions + rows).distinctBy { it.id }
        return PickingSessionList(sessions = merged, page = loadedPage, lastPage = pagination.lastPage, total = pagination.total)
    }

    fun failed(page: Int, problem: GoodsOutProblem): PickingSessionList = copy(loadingPage = null, problem = problem, failedPage = page)

    fun savedCopy(savedSessions: List<PickingSessionRow>): PickingSessionList =
        PickingSessionList(sessions = savedSessions, page = 1, lastPage = 1, total = savedSessions.size, problem = GoodsOutProblem.Offline, failedPage = 1)
}

data class PickingSessionsUiState(
    val state: PickingSessionState? = null,
    val search: String = "",
    val filter: ListFilter<PickingSessionSort> = ListFilter(),
    val list: PickingSessionList = PickingSessionList(),
    val isRefreshing: Boolean = false,
    val counts: Map<String, Int> = emptyMap(),
    val isSessionExpired: Boolean = false,
    val offline: OfflineRows<PickingSessionRow>? = null,
) {
    val lastUpdated: Instant? get() = offline?.savedAt

    fun query(page: Int, filter: ListFilter<PickingSessionSort> = this.filter): PickingSessionQuery = PickingSessionQuery(
        state = state,
        search = search.trim().takeIf { it.isNotEmpty() },
        sort = filter.sort,
        page = page,
    )

    fun offlineTotalFor(draft: ListFilter<PickingSessionSort>): Int? = offline?.let { offlinePickingSessions(it.rows, query(page = 1, filter = draft)).size }
}

class PickingSessionsViewModel(
    private val api: PickingSessionApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val cache: ScreenCache? = null,
) : ViewModel() {

    private val mutableState = MutableStateFlow(PickingSessionsUiState())
    val state: StateFlow<PickingSessionsUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var searchJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: PickingSessionState?) {
        if (state != mutableState.value.state) {
            searchJob?.cancel()
            mutableState.update { it.copy(state = state, list = PickingSessionList()) }
            loadPage(1)
        }
    }

    fun search(text: String) {
        mutableState.update { it.copy(search = text) }
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            mutableState.update { it.copy(list = PickingSessionList()) }
            loadPage(1)
        }
    }

    fun applyFilter(filter: ListFilter<PickingSessionSort>) {
        searchJob?.cancel()
        mutableState.update { it.copy(filter = filter, list = PickingSessionList()) }
        loadPage(1)
    }

    fun clearFilters() = applyFilter(ListFilter())

    fun loadMore() {
        val list = mutableState.value.list
        if (list.canLoadMore) {
            loadPage(list.page + 1)
        }
    }

    fun retry() = loadPage(mutableState.value.list.failedPage ?: 1)

    fun refresh() {
        if (sessions.session.value == null) {
            return
        }
        mutableState.update { it.copy(isRefreshing = true) }
        loadPage(1)
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun loadPage(page: Int) {
        val token = sessions.session.value?.token ?: return
        val query = mutableState.value.query(page = page)
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.pickingSessions(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> {
                    mutableState.update {
                        it.copy(
                            list = it.list.loaded(result.value.sessions, result.value.pagination),
                            isRefreshing = false,
                            counts = result.value.counts.ifEmpty { it.counts },
                            offline = null,
                        )
                    }
                    cache?.remember(warehouseId, SavedLists.pickingSessions, result.value.sessions)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isRefreshing = false, isSessionExpired = true) }
                GoodsOutResult.Unreachable -> if (page != 1 || !showSavedRows()) mutableState.update { it.copy(list = it.list.failed(page, result.problem()), isRefreshing = false) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem()), isRefreshing = false) }
            }
        }
    }

    private suspend fun showSavedRows(): Boolean {
        val saved = cache?.saved(warehouseId, SavedLists.pickingSessions) ?: return false
        val zone = ZoneId.systemDefault()
        val offline = OfflineRows(rows = saved.value, savedAt = saved.fetchedAt, zone = zone, today = LocalDate.now(zone))
        mutableState.update { it.copy(offline = offline, list = it.list.savedCopy(offlinePickingSessions(saved.value, it.query(page = 1))), isRefreshing = false) }
        return true
    }
}
