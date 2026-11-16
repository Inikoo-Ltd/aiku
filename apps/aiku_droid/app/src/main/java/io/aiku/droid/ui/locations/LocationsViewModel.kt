package io.aiku.droid.ui.locations

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.LocationOccupancy
import io.aiku.droid.data.api.LocationQuery
import io.aiku.droid.data.api.LocationScope
import io.aiku.droid.data.api.LocationSort
import io.aiku.droid.data.api.LocationStatusFilter
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.data.api.LocationTab
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.WarehouseArea
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.PagedList
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.scan.scanProblem
import kotlinx.coroutines.Job
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val COUNT_PER_PAGE = 1

data class LocationFilter(
    val tab: LocationTab = LocationTab.All,
    val search: String = "",
    val areaIds: Set<Long> = emptySet(),
    val statuses: Set<LocationStatusFilter> = emptySet(),
    val scopes: Set<LocationScope> = emptySet(),
    val occupancies: Set<LocationOccupancy> = emptySet(),
    val sort: LocationSort? = null,
) {
    val activeFilterCount: Int get() = areaIds.size + statuses.size + scopes.size + occupancies.size

    val isFiltered: Boolean get() = activeFilterCount > 0 || sort != null

    fun cleared(): LocationFilter = LocationFilter(tab = tab, search = search)

    fun query(page: Int, perPage: Int, areaId: Long? = null, tab: LocationTab = this.tab): LocationQuery = LocationQuery(
        tab = tab,
        search = search.trim().takeIf { it.isNotEmpty() },
        areaId = areaId,
        areaIds = areaIds,
        statuses = statuses,
        scopes = scopes,
        occupancies = occupancies,
        sort = sort,
        page = page,
        perPage = perPage,
    )
}

data class LocationFilterPreview(val filter: LocationFilter, val total: Int)

data class LocationsUiState(
    val filter: LocationFilter = LocationFilter(),
    val list: PagedList<LocationSummary> = PagedList(),
    val tabCounts: Map<LocationTab, Int> = emptyMap(),
    val preview: LocationFilterPreview? = null,
    val areas: List<WarehouseArea>? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: StockScanOutcome? = null,
    val isSessionExpired: Boolean = false,
) {
    fun totalFor(draft: LocationFilter): Int? = when {
        draft == filter && list.hasLoaded && list.loadingPage == null -> list.total
        else -> preview?.takeIf { it.filter == draft }?.total
    }

    fun countOf(tab: LocationTab): Int? = if (tab == filter.tab && list.hasLoaded && list.loadingPage == null) list.total else tabCounts[tab]
}

class LocationsViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    initialTab: LocationTab = LocationTab.All,
    private val areaId: Long? = null,
    private val perPage: Int = LocationQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(LocationsUiState(filter = LocationFilter(tab = initialTab)))
    val state: StateFlow<LocationsUiState> = mutableState.asStateFlow()

    private val countsTabs: Boolean = areaId == null
    private var listJob: Job? = null
    private var countsJob: Job? = null
    private var previewJob: Job? = null
    private var searchJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage != null) {
            return
        }
        if (!list.hasLoaded || list.page <= 1) {
            loadPage(1)
        }
        loadCounts()
    }

    fun selectTab(tab: LocationTab) {
        if (tab != mutableState.value.filter.tab) {
            mutableState.update { it.copy(filter = it.filter.copy(tab = tab), list = PagedList()) }
            loadPage(1)
        }
    }

    fun search(text: String) {
        mutableState.update { it.copy(filter = it.filter.copy(search = text)) }
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            mutableState.update { it.copy(list = PagedList()) }
            loadPage(1)
            loadCounts()
        }
    }

    fun applyFilter(filter: LocationFilter) {
        searchJob?.cancel()
        mutableState.update { it.copy(filter = filter, list = PagedList()) }
        loadPage(1)
        loadCounts()
    }

    fun clearFilters() = applyFilter(mutableState.value.filter.cleared())

    fun loadMore() {
        val list = mutableState.value.list
        if (list.canLoadMore) {
            loadPage(list.page + 1)
        }
    }

    fun retry() {
        loadPage(mutableState.value.list.failedPage ?: 1)
    }

    fun loadAreas() {
        val token = token() ?: return
        if (mutableState.value.areas != null) {
            return
        }
        viewModelScope.launch {
            when (val result = api.areas(token, warehouseId, search = null)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(areas = result.value) }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> Unit
            }
        }
    }

    fun previewTotal(draft: LocationFilter) {
        previewJob?.cancel()
        if (mutableState.value.totalFor(draft) != null) {
            return
        }
        val token = token() ?: return
        previewJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            val result = api.locations(token, warehouseId, draft.query(page = 1, perPage = COUNT_PER_PAGE, areaId = areaId))
            if (result is GoodsOutResult.Loaded) {
                mutableState.update { it.copy(preview = LocationFilterPreview(draft, result.value.meta.total)) }
            }
        }
    }

    fun lookUpScan(code: String) {
        val scanned = code.trim()
        val token = token() ?: return
        if (scanned.isEmpty() || mutableState.value.lookingUpCode != null) {
            return
        }
        mutableState.update { it.copy(lookingUpCode = scanned, scanOutcome = null) }

        viewModelScope.launch {
            val outcome = when (val result = api.scan(token, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> {
                    val location = result.value.location?.takeIf { result.value.type == ScanMatch.LOCATION }
                    if (location != null) StockScanOutcome.Open(LocationRoute(id = location.id, code = location.code)) else StockScanOutcome.NoMatch(scanned)
                }
                is GoodsOutResult.NotFound -> StockScanOutcome.NoMatch(scanned)
                GoodsOutResult.SessionExpired -> null.also { expireSession() }
                else -> StockScanOutcome.Failed(scanned, result.scanProblem())
            }
            mutableState.update { it.copy(lookingUpCode = null, scanOutcome = outcome) }
        }
    }

    fun scanOutcomeHandled() {
        mutableState.update { it.copy(scanOutcome = null) }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun loadPage(page: Int) {
        val token = token() ?: return
        val filter = mutableState.value.filter
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.locations(token, warehouseId, filter.query(page = page, perPage = perPage, areaId = areaId))) {
                is GoodsOutResult.Loaded -> mutableState.update { current ->
                    current.copy(list = current.list.loaded(result.value.locations, result.value.meta) { it.id })
                }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }

    private fun loadCounts() {
        if (!countsTabs) {
            return
        }
        val token = token() ?: return
        val filter = mutableState.value.filter
        countsJob?.cancel()

        countsJob = viewModelScope.launch {
            val results = LocationTab.entries
                .map { tab -> async { tab to api.locations(token, warehouseId, filter.query(page = 1, perPage = COUNT_PER_PAGE, tab = tab)) } }
                .awaitAll()
            if (results.any { (_, result) -> result == GoodsOutResult.SessionExpired }) {
                expireSession()
                return@launch
            }
            val counts = results.mapNotNull { (tab, result) -> (result as? GoodsOutResult.Loaded)?.let { tab to it.value.meta.total } }.toMap()
            mutableState.update { it.copy(tabCounts = counts) }
        }
    }

    private fun token(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableState.update { it.copy(isSessionExpired = true) }
    }
}
