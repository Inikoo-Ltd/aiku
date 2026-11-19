package io.aiku.droid.ui.inventory

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FamilyCounts
import io.aiku.droid.data.api.FamilyQuery
import io.aiku.droid.data.api.FamilySko
import io.aiku.droid.data.api.FamilyState
import io.aiku.droid.data.api.FamilySummary
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.InventoryDashboard
import io.aiku.droid.data.api.OrgStockFamily
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class InventoryDashboardUiState(
    val dashboard: InventoryDashboard? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
)

class InventoryDashboardViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(InventoryDashboardUiState())
    val state: StateFlow<InventoryDashboardUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true) {
            retry()
        }
    }

    fun retry() = load()

    fun refresh() = load(isPull = true)

    fun signInAgain() {
        sessions.end()
    }

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.inventoryDashboard(token, warehouseId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(dashboard = result.value, isLoading = false, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}

data class SkoFamiliesUiState(
    val state: FamilyState = FamilyState.Active,
    val search: String = "",
    val list: PagedList<OrgStockFamily> = PagedList(),
    val counts: FamilyCounts? = null,
    val isSessionExpired: Boolean = false,
)

class SkoFamiliesViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val perPage: Int = FamilyQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(SkoFamiliesUiState())
    val state: StateFlow<SkoFamiliesUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: FamilyState) {
        if (state != mutableState.value.state) {
            mutableState.update { it.copy(state = state, list = PagedList()) }
            loadPage(1)
        }
    }

    fun search(text: String) {
        mutableState.update { it.copy(search = text) }
        loadPage(1, debounce = true)
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

    fun refresh() {
        if (sessions.session.value == null) {
            return
        }
        mutableState.update { it.copy(list = it.list.refreshing()) }
        loadPage(1)
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun loadPage(page: Int, debounce: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        listJob?.cancel()

        listJob = viewModelScope.launch {
            if (debounce) {
                delay(SEARCH_DEBOUNCE_MILLIS)
            }
            val current = mutableState.value
            mutableState.update { it.copy(list = if (page == 1 && !it.list.isRefreshing) PagedList<OrgStockFamily>().loading(1) else it.list.loading(page)) }
            val query = FamilyQuery(state = current.state, search = current.search, page = page, perPage = perPage)
            when (val result = api.families(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(list = it.list.loaded(result.value.families, result.value.meta) { family -> family.id }, counts = result.value.counts ?: it.counts)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }
}

data class SkoFamilyUiState(
    val family: FamilySummary? = null,
    val list: PagedList<FamilySko> = PagedList(),
    val isSessionExpired: Boolean = false,
)

class SkoFamilyViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val familyId: Long,
    private val perPage: Int = FamilyQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(SkoFamilyUiState())
    val state: StateFlow<SkoFamilyUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
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

    fun refresh() {
        if (sessions.session.value == null) {
            return
        }
        mutableState.update { it.copy(list = it.list.refreshing()) }
        loadPage(1)
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun loadPage(page: Int) {
        val token = sessions.session.value?.token ?: return
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.familySkos(token, warehouseId, familyId, page, perPage)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(family = result.value.family ?: it.family, list = it.list.loaded(result.value.skos, result.value.meta) { sko -> sko.id })
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }
}
