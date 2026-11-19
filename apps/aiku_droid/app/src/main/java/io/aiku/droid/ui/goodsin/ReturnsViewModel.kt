package io.aiku.droid.ui.goodsin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.CustomerReturn
import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ReturnQuery
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.api.ReturnWrite
import io.aiku.droid.data.api.StateCounts
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

data class ReturnsUiState(
    val state: ReturnStateFilter = ReturnStateFilter.All,
    val search: String = "",
    val list: PagedList<CustomerReturnRow> = PagedList(),
    val counts: StateCounts? = null,
    val isSessionExpired: Boolean = false,
)

class ReturnsViewModel(
    private val api: GoodsInApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    initialState: ReturnStateFilter = ReturnStateFilter.All,
    private val perPage: Int = ReturnQuery.DEFAULT_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(ReturnsUiState(state = initialState))
    val state: StateFlow<ReturnsUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null
    private var searchJob: Job? = null

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectState(state: ReturnStateFilter) {
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
        val current = mutableState.value
        val query = ReturnQuery(state = current.state, search = current.search.trim().takeIf { it.isNotEmpty() }, page = page, perPage = perPage)
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.returns(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(list = it.list.loaded(result.value.rows, result.value.meta) { row -> row.id }, counts = result.value.counts ?: it.counts)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }
}

data class ReturnUiState(
    val customerReturn: CustomerReturn? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: ReturnWrite? = null,
    val event: GoodsInEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
) {
    val isWorking: Boolean get() = working != null
}

class ReturnViewModel(
    private val api: GoodsInApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val returnId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(ReturnUiState())
    val state: StateFlow<ReturnUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isWorking) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() {
        if (!mutableState.value.isWorking) {
            load(isPull = true)
        }
    }

    fun signInAgain() {
        sessions.end()
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun clearWriteErrors() {
        mutableState.update { it.copy(writeErrors = emptyMap()) }
    }

    fun start() = act(ReturnWrite.Start)

    fun backToStock(itemId: Long, quantity: Double?, locationOrgStockId: Long) =
        act(ReturnWrite.Returned(itemId = itemId, quantity = quantity, locationOrgStockId = locationOrgStockId))

    fun damaged(itemId: Long, quantity: Double?) = act(ReturnWrite.Damaged(itemId = itemId, quantity = quantity))

    fun notReturned(itemId: Long, quantity: Double?) = act(ReturnWrite.NotReturned(itemId = itemId, quantity = quantity))

    fun undo(putId: Long) = act(ReturnWrite.UndoPut(putId))

    fun setAsReturned() = act(ReturnWrite.SetAsReturned)

    private fun act(write: ReturnWrite) {
        val token = sessions.session.value?.token ?: return
        if (mutableState.value.customerReturn == null || mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false, isRefreshing = false, writeErrors = emptyMap()) }

        viewModelScope.launch {
            when (val result = api.writeReturn(token, warehouseId, returnId, write)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(
                        customerReturn = result.value.customerReturn,
                        working = null,
                        problem = null,
                        event = GoodsInEvent.Done(
                            action = write,
                            message = result.value.message,
                            isNothingChanged = result.value.status == GoodsInWriteStatus.NOTHING_CHANGED,
                        ),
                    )
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                else -> mutableState.update {
                    it.copy(working = null, writeErrors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty(), event = result.goodsInRefusal())
                }
            }
        }
    }

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.customerReturn(token, warehouseId, returnId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(customerReturn = result.value, isLoading = false, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}
