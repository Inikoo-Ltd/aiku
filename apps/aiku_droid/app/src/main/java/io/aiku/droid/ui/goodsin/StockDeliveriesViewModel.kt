package io.aiku.droid.ui.goodsin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.DispatchedBucket
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.data.api.StockDeliveryQuery
import io.aiku.droid.data.api.StockDeliveryRow
import io.aiku.droid.data.api.StockDeliverySort
import io.aiku.droid.data.api.StockDeliverySource
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.PagedList
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.scan.ScanDestination
import io.aiku.droid.ui.scan.destination
import io.aiku.droid.ui.scan.route
import io.aiku.droid.ui.scan.scanProblem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val PREVIEW_PER_PAGE = 1

data class StockDeliveryFilter(
    val state: StockDeliveryStateFilter = StockDeliveryStateFilter.Active,
    val source: StockDeliverySource? = null,
    val search: String = "",
    val dispatched: DispatchedBucket? = null,
    val sort: StockDeliverySort? = null,
) {
    val activeFilterCount: Int get() = listOfNotNull(source, dispatched).size

    val isFiltered: Boolean get() = activeFilterCount > 0 || sort != null

    fun cleared(): StockDeliveryFilter = StockDeliveryFilter(state = state, search = search)

    fun query(page: Int, perPage: Int): StockDeliveryQuery = StockDeliveryQuery(
        state = state,
        source = source,
        search = search.trim().takeIf { it.isNotEmpty() },
        dispatched = dispatched,
        sort = sort,
        page = page,
        perPage = perPage,
    )
}

data class StockDeliveryFilterPreview(val filter: StockDeliveryFilter, val total: Int)

data class StockDeliveriesUiState(
    val filter: StockDeliveryFilter = StockDeliveryFilter(),
    val list: PagedList<StockDeliveryRow> = PagedList(),
    val counts: StateCounts? = null,
    val preview: StockDeliveryFilterPreview? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: StockScanOutcome? = null,
    val isSessionExpired: Boolean = false,
) {
    fun totalFor(draft: StockDeliveryFilter): Int? = when {
        draft == filter && list.hasLoaded && list.loadingPage == null -> list.total
        else -> preview?.takeIf { it.filter == draft }?.total
    }
}

class StockDeliveriesViewModel(
    private val api: GoodsInApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    initialFilter: StockDeliveryFilter = StockDeliveryFilter(),
    private val perPage: Int = StockDeliveryQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(StockDeliveriesUiState(filter = initialFilter))
    val state: StateFlow<StockDeliveriesUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var previewJob: Job? = null
    private var searchJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: StockDeliveryStateFilter) {
        if (state != mutableState.value.filter.state) {
            applyFilter(mutableState.value.filter.copy(state = state))
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

    fun applyFilter(filter: StockDeliveryFilter) {
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

    fun previewTotal(draft: StockDeliveryFilter) {
        previewJob?.cancel()
        if (mutableState.value.totalFor(draft) != null) {
            return
        }
        val token = token() ?: return
        previewJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            val result = api.stockDeliveries(token, warehouseId, draft.query(page = 1, perPage = PREVIEW_PER_PAGE))
            if (result is GoodsOutResult.Loaded) {
                mutableState.update { it.copy(preview = StockDeliveryFilterPreview(draft, result.value.meta.total)) }
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
                is GoodsOutResult.Loaded -> when (val destination = result.value.destination(scanned)) {
                    is ScanDestination.StockDelivery -> StockScanOutcome.Open(destination.route())
                    else -> StockScanOutcome.NoMatch(scanned)
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
            when (val result = api.stockDeliveries(token, warehouseId, filter.query(page = page, perPage = perPage))) {
                is GoodsOutResult.Loaded -> mutableState.update { current ->
                    current.copy(
                        list = current.list.loaded(result.value.rows, result.value.meta) { it.id },
                        counts = result.value.counts ?: current.counts,
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
