package io.aiku.droid.ui.fulfilment.inbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FulfilmentInboundApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletFilter
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.data.api.PalletQuery
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.PagedList
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.scan.scanProblem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PalletsUiState(
    val filter: PalletFilter = PalletFilter.Storing,
    val incidentState: PalletIncidentState? = null,
    val search: String = "",
    val list: PagedList<Pallet> = PagedList(),
    val counts: StateCounts? = null,
    val incidentCounts: StateCounts? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: StockScanOutcome? = null,
    val isSessionExpired: Boolean = false,
)

class PalletsViewModel(
    private val api: FulfilmentInboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    initialFilter: PalletFilter = PalletFilter.Storing,
    private val perPage: Int = PalletQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(PalletsUiState(filter = initialFilter))
    val state: StateFlow<PalletsUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var searchJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectFilter(filter: PalletFilter) {
        if (filter != mutableState.value.filter) {
            reload { it.copy(filter = filter, incidentState = null) }
        }
    }

    fun selectIncidentState(incidentState: PalletIncidentState?) {
        if (incidentState != mutableState.value.incidentState) {
            reload { it.copy(incidentState = incidentState) }
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

    fun lookUpScan(code: String) {
        val scanned = code.trim()
        if (scanned.isEmpty() || mutableState.value.lookingUpCode != null) {
            return
        }
        val listed = mutableState.value.list.rows.firstOrNull { it.reference.equals(scanned, ignoreCase = true) }
        if (listed != null) {
            mutableState.update { it.copy(scanOutcome = StockScanOutcome.Open(PalletRoute(id = listed.id, reference = listed.reference))) }
            return
        }
        val token = token() ?: return
        mutableState.update { it.copy(lookingUpCode = scanned, scanOutcome = null) }

        viewModelScope.launch {
            val outcome = when (val result = api.scan(token, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> result.value.pallet?.takeIf { result.value.type == ScanMatch.PALLET }
                    ?.let { StockScanOutcome.Open(PalletRoute(id = it.id, reference = it.reference)) }
                    ?: StockScanOutcome.NoMatch(scanned)
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

    private fun reload(change: (PalletsUiState) -> PalletsUiState) {
        searchJob?.cancel()
        mutableState.update { change(it).copy(list = PagedList()) }
        loadPage(1)
    }

    private fun loadPage(page: Int) {
        val token = token() ?: return
        val current = mutableState.value
        val query = PalletQuery(
            filter = current.filter,
            incidentState = current.incidentState,
            search = current.search.trim().takeIf { it.isNotEmpty() },
            page = page,
            perPage = perPage,
        )
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.pallets(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> mutableState.update { state ->
                    state.copy(
                        list = state.list.loaded(result.value.rows, result.value.meta) { it.id },
                        counts = result.value.counts ?: state.counts,
                        incidentCounts = result.value.incidentCounts ?: state.incidentCounts,
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
