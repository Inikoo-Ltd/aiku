package io.aiku.droid.ui.fulfilment.outbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FulfilmentOutboundApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PalletReturnCounts
import io.aiku.droid.data.api.PalletReturnQuery
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnStateFilter
import io.aiku.droid.data.api.PalletReturnTypeFilter
import io.aiku.droid.data.session.SessionRepository
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

data class PalletReturnsUiState(
    val state: PalletReturnStateFilter = PalletReturnStateFilter.Active,
    val type: PalletReturnTypeFilter? = null,
    val search: String = "",
    val list: PagedList<PalletReturnRow> = PagedList(),
    val counts: PalletReturnCounts? = null,
    val isSessionExpired: Boolean = false,
)

class PalletReturnsViewModel(
    private val api: FulfilmentOutboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val perPage: Int = PalletReturnQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(PalletReturnsUiState())
    val state: StateFlow<PalletReturnsUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var searchJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: PalletReturnStateFilter) {
        if (state != mutableState.value.state) {
            searchJob?.cancel()
            mutableState.update { it.copy(state = state, list = PagedList()) }
            loadPage(1)
        }
    }

    fun selectType(type: PalletReturnTypeFilter?) {
        if (type != mutableState.value.type) {
            searchJob?.cancel()
            mutableState.update { it.copy(type = type, list = PagedList()) }
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

    private fun loadPage(page: Int) {
        val token = sessions.session.value?.token ?: return
        val current = mutableState.value
        val query = PalletReturnQuery(
            state = current.state,
            type = current.type,
            search = current.search.trim().takeIf { it.isNotEmpty() },
            page = page,
            perPage = perPage,
        )
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.palletReturns(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(list = it.list.loaded(result.value.rows, result.value.meta) { row -> row.id }, counts = result.value.counts ?: it.counts)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }
}
