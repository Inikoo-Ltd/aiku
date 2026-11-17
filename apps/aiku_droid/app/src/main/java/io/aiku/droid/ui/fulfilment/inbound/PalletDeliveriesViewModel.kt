package io.aiku.droid.ui.fulfilment.inbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FulfilmentInboundApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PalletDeliveryQuery
import io.aiku.droid.data.api.PalletDeliveryRow
import io.aiku.droid.data.api.PalletDeliveryStateFilter
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.PagedList
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.scan.scanProblem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PalletDeliveriesUiState(
    val state: PalletDeliveryStateFilter = PalletDeliveryStateFilter.Active,
    val search: String = "",
    val list: PagedList<PalletDeliveryRow> = PagedList(),
    val counts: StateCounts? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: StockScanOutcome? = null,
    val isSessionExpired: Boolean = false,
)

class PalletDeliveriesViewModel(
    private val api: FulfilmentInboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val perPage: Int = PalletDeliveryQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(PalletDeliveriesUiState())
    val state: StateFlow<PalletDeliveriesUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var searchJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: PalletDeliveryStateFilter) {
        if (state == mutableState.value.state) {
            return
        }
        searchJob?.cancel()
        mutableState.update { it.copy(state = state, list = PagedList()) }
        loadPage(1)
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
            mutableState.update { it.copy(scanOutcome = StockScanOutcome.Open(palletDeliveryRoute(listed.id, listed.reference, listed.state))) }
            return
        }
        val token = token() ?: return
        mutableState.update { it.copy(lookingUpCode = scanned, scanOutcome = null) }

        viewModelScope.launch {
            val outcome = when (val result = api.scan(token, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> result.value.palletDelivery?.takeIf { result.value.type == ScanMatch.PALLET_DELIVERY }
                    ?.let { StockScanOutcome.Open(palletDeliveryRoute(it.id, it.reference, it.state)) }
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

    private fun loadPage(page: Int) {
        val token = token() ?: return
        val current = mutableState.value
        val query = PalletDeliveryQuery(state = current.state, search = current.search.trim().takeIf { it.isNotEmpty() }, page = page, perPage = perPage)
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.palletDeliveries(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> mutableState.update { state ->
                    state.copy(
                        list = state.list.loaded(result.value.rows, result.value.meta) { it.id },
                        counts = result.value.counts ?: state.counts,
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
