package io.aiku.droid.ui.goodsout.extra

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutPagination
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PickingSessionList(
    val sessions: List<PickingSessionRow> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 0,
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
        return PickingSessionList(sessions = merged, page = loadedPage, lastPage = pagination.lastPage)
    }

    fun failed(page: Int, problem: GoodsOutProblem): PickingSessionList = copy(loadingPage = null, problem = problem, failedPage = page)
}

data class PickingSessionsUiState(
    val state: PickingSessionState? = null,
    val list: PickingSessionList = PickingSessionList(),
    val counts: Map<String, Int> = emptyMap(),
    val isSessionExpired: Boolean = false,
)

class PickingSessionsViewModel(
    private val api: PickingSessionApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(PickingSessionsUiState())
    val state: StateFlow<PickingSessionsUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: PickingSessionState?) {
        if (state != mutableState.value.state) {
            mutableState.update { it.copy(state = state, list = PickingSessionList()) }
            loadPage(1)
        }
    }

    fun loadMore() {
        val list = mutableState.value.list
        if (list.canLoadMore) {
            loadPage(list.page + 1)
        }
    }

    fun retry() = loadPage(mutableState.value.list.failedPage ?: 1)

    fun signInAgain() {
        sessions.end()
    }

    private fun loadPage(page: Int) {
        val token = sessions.session.value?.token ?: return
        val state = mutableState.value.state
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.pickingSessions(token, warehouseId, state, page)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(
                        list = it.list.loaded(result.value.sessions, result.value.pagination),
                        counts = result.value.counts.ifEmpty { it.counts },
                    )
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }
}
