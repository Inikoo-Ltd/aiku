package io.aiku.droid.ui.inventory

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FamilyQuery
import io.aiku.droid.data.api.FamilyState
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.OrgStockFamily
import io.aiku.droid.data.api.OrgStockRow
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.SkoBucket
import io.aiku.droid.data.api.SkoCounts
import io.aiku.droid.data.api.SkoQuery
import io.aiku.droid.data.api.SkoSort
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.scan.scanProblem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val PREVIEW_PER_PAGE = 1
private const val FAMILY_OPTIONS_PER_PAGE = 150
private const val MAX_FAMILY_PAGES = 10

internal fun OrgStockRow.listKey(): Any = locationOrgStockId?.let { id to it } ?: id

data class SkoFilter(
    val bucket: SkoBucket = SkoBucket.Current,
    val search: String = "",
    val familyId: Long? = null,
    val sort: SkoSort? = null,
) {
    val activeFilterCount: Int get() = if (familyId != null) 1 else 0

    val isFiltered: Boolean get() = activeFilterCount > 0 || sort != null

    fun cleared(): SkoFilter = SkoFilter(bucket = bucket, search = search)

    fun query(page: Int, perPage: Int): SkoQuery =
        SkoQuery(bucket = bucket, familyId = familyId, search = search.trim().takeIf { it.isNotEmpty() }, sort = sort, page = page, perPage = perPage)
}

data class SkoFilterPreview(val filter: SkoFilter, val total: Int)

data class SkosUiState(
    val filter: SkoFilter = SkoFilter(),
    val list: PagedList<OrgStockRow> = PagedList(),
    val counts: SkoCounts? = null,
    val currencyCode: String? = null,
    val preview: SkoFilterPreview? = null,
    val families: List<OrgStockFamily>? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: StockScanOutcome? = null,
    val isSessionExpired: Boolean = false,
) {
    fun totalFor(draft: SkoFilter): Int? = when {
        draft == filter && list.hasLoaded && list.loadingPage == null -> list.total
        else -> preview?.takeIf { it.filter == draft }?.total
    }
}

class SkosViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    initialBucket: SkoBucket = SkoBucket.Current,
    private val perPage: Int = SkoQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(SkosUiState(filter = SkoFilter(bucket = initialBucket)))
    val state: StateFlow<SkosUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var previewJob: Job? = null
    private var searchJob: Job? = null
    private var familiesJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectBucket(bucket: SkoBucket) {
        if (bucket != mutableState.value.filter.bucket) {
            applyFilter(mutableState.value.filter.copy(bucket = bucket))
        }
    }

    fun search(text: String) {
        mutableState.update { it.copy(filter = it.filter.copy(search = text)) }
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            mutableState.update { it.copy(list = PagedList()) }
            loadPage(1)
        }
    }

    fun applyFilter(filter: SkoFilter) {
        searchJob?.cancel()
        mutableState.update { it.copy(filter = filter, list = PagedList()) }
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
        loadPage(mutableState.value.list.failedPage ?: 1)
    }

    fun refresh() {
        if (sessions.session.value == null) {
            return
        }
        mutableState.update { it.copy(list = it.list.refreshing()) }
        loadPage(1)
    }

    fun previewTotal(draft: SkoFilter) {
        previewJob?.cancel()
        if (mutableState.value.totalFor(draft) != null) {
            return
        }
        val token = token() ?: return
        previewJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            val result = api.orgStocks(token, warehouseId, draft.query(page = 1, perPage = PREVIEW_PER_PAGE))
            if (result is GoodsOutResult.Loaded) {
                mutableState.update { it.copy(preview = SkoFilterPreview(draft, result.value.meta.total)) }
            }
        }
    }

    fun loadFamilies() {
        val token = token() ?: return
        if (mutableState.value.families != null || familiesJob?.isActive == true) {
            return
        }
        familiesJob = viewModelScope.launch {
            val families = mutableListOf<OrgStockFamily>()
            var page = 1
            var lastPage = 1
            while (page <= lastPage && page <= MAX_FAMILY_PAGES) {
                when (val result = api.families(token, warehouseId, FamilyQuery(state = FamilyState.Active, page = page, perPage = FAMILY_OPTIONS_PER_PAGE))) {
                    is GoodsOutResult.Loaded -> {
                        families += result.value.families
                        lastPage = result.value.meta.lastPage
                    }
                    GoodsOutResult.SessionExpired -> {
                        expireSession()
                        return@launch
                    }
                    else -> return@launch
                }
                page++
            }
            mutableState.update { it.copy(families = families) }
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
                    val sko = result.value.sko?.takeIf { result.value.type == ScanMatch.SKO }
                    if (sko != null) StockScanOutcome.Open(SkuRoute(id = sko.id, code = sko.code)) else StockScanOutcome.NoMatch(scanned)
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
            when (val result = api.orgStocks(token, warehouseId, filter.query(page = page, perPage = perPage))) {
                is GoodsOutResult.Loaded -> mutableState.update { current ->
                    current.copy(
                        list = current.list.loaded(result.value.rows, result.value.meta) { it.listKey() },
                        counts = result.value.counts ?: current.counts,
                        currencyCode = result.value.currencyCode ?: current.currencyCode,
                    )
                }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }

    private fun token(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableState.update { it.copy(isSessionExpired = true) }
    }
}
