package io.aiku.droid.ui.fulfilment.outbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FulfilmentOutboundApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.data.api.StoredItemDetail
import io.aiku.droid.data.api.StoredItemQuery
import io.aiku.droid.data.api.StoredItemRow
import io.aiku.droid.data.api.StoredItemStateFilter
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.PagedList
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class StoredItemSheetState(
    val row: StoredItemRow,
    val detail: StoredItemDetail? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
)

data class StoredItemsUiState(
    val state: StoredItemStateFilter = StoredItemStateFilter.Active,
    val search: String = "",
    val list: PagedList<StoredItemRow> = PagedList(),
    val counts: StateCounts? = null,
    val opened: StoredItemSheetState? = null,
    val isSessionExpired: Boolean = false,
)

class StoredItemsViewModel(
    private val api: FulfilmentOutboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val perPage: Int = StoredItemQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(StoredItemsUiState())
    val state: StateFlow<StoredItemsUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var searchJob: Job? = null
    private var detailJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: StoredItemStateFilter) {
        if (state != mutableState.value.state) {
            searchJob?.cancel()
            mutableState.update { it.copy(state = state, list = PagedList()) }
            loadPage(1)
        }
    }

    fun search(text: String) {
        mutableState.update { it.copy(search = text) }
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            mutableState.update { it.copy(list = PagedList()) }
            loadPage(1)
        }
    }

    fun loadMore() {
        val list = mutableState.value.list
        if (list.canLoadMore) {
            loadPage(list.page + 1)
        }
    }

    fun retry() {
        loadPage(mutableState.value.list.failedPage ?: 1)
    }

    fun signInAgain() {
        sessions.end()
    }

    fun open(row: StoredItemRow) {
        mutableState.update { it.copy(opened = StoredItemSheetState(row = row)) }
        loadDetail()
    }

    fun close() {
        detailJob?.cancel()
        mutableState.update { it.copy(opened = null) }
    }

    fun retryDetail() = loadDetail()

    private fun loadDetail() {
        val token = sessions.session.value?.token ?: return
        val opened = mutableState.value.opened ?: return
        detailJob?.cancel()
        mutableState.update { it.copy(opened = opened.copy(isLoading = true, problem = null)) }

        detailJob = viewModelScope.launch {
            val result = api.storedItem(token, warehouseId, opened.row.id)
            mutableState.update { current ->
                val sheet = current.opened?.takeIf { it.row.id == opened.row.id } ?: return@update current
                when (result) {
                    is GoodsOutResult.Loaded -> current.copy(opened = sheet.copy(detail = result.value, isLoading = false))
                    GoodsOutResult.SessionExpired -> current.copy(opened = null, isSessionExpired = true)
                    else -> current.copy(opened = sheet.copy(isLoading = false, problem = result.problem()))
                }
            }
        }
    }

    private fun loadPage(page: Int) {
        val token = sessions.session.value?.token ?: return
        val current = mutableState.value
        val query = StoredItemQuery(state = current.state, search = current.search.trim().takeIf { it.isNotEmpty() }, page = page, perPage = perPage)
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.storedItems(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(list = it.list.loaded(result.value.rows, result.value.meta) { row -> row.id }, counts = result.value.counts ?: it.counts)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }
}
