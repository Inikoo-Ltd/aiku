package io.aiku.droid.ui.goodsin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.CustomerReturn
import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsInResponses
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ReturnQuery
import io.aiku.droid.data.api.ReturnSort
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.api.ReturnWrite
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.data.cache.SavedLists
import io.aiku.droid.data.cache.ScreenCache
import io.aiku.droid.data.offline.NoOfflineWork
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.components.ListFilter
import io.aiku.droid.ui.components.OfflineRows
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.PagedList
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import io.aiku.droid.ui.sync.QueueEvent
import io.aiku.droid.ui.sync.RecordQueue
import io.aiku.droid.ui.sync.StatusGate
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

private const val HTTP_OK = 200

data class ReturnsUiState(
    val state: ReturnStateFilter = ReturnStateFilter.All,
    val search: String = "",
    val filter: ListFilter<ReturnSort> = ListFilter(),
    val list: PagedList<CustomerReturnRow> = PagedList(),
    val counts: StateCounts? = null,
    val isSessionExpired: Boolean = false,
    val offline: OfflineRows<CustomerReturnRow>? = null,
) {
    val lastUpdated: Instant? get() = offline?.savedAt

    fun query(page: Int, perPage: Int, filter: ListFilter<ReturnSort> = this.filter): ReturnQuery = ReturnQuery(
        state = state,
        search = search.trim().takeIf { it.isNotEmpty() },
        from = filter.dates.from,
        to = filter.dates.to,
        sort = filter.sort,
        page = page,
        perPage = perPage,
    )

    fun offlineTotalFor(draft: ListFilter<ReturnSort>): Int? = offline?.let { offlineReturns(it.rows, query(page = 1, perPage = 1, filter = draft), it.zone).size }
}

class ReturnsViewModel(
    private val api: GoodsInApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    initialState: ReturnStateFilter = ReturnStateFilter.All,
    private val perPage: Int = ReturnQuery.DEFAULT_PER_PAGE,
    private val cache: ScreenCache? = null,
    private val zone: () -> ZoneId = ZoneId::systemDefault,
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

    fun applyFilter(filter: ListFilter<ReturnSort>) {
        searchJob?.cancel()
        mutableState.update { it.copy(filter = filter, list = PagedList()) }
        loadPage(1)
    }

    fun clearFilters() = applyFilter(ListFilter())

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
        val query = mutableState.value.query(page = page, perPage = perPage)
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.returns(token, warehouseId, query)) {
                is GoodsOutResult.Loaded -> {
                    mutableState.update {
                        it.copy(list = it.list.loaded(result.value.rows, result.value.meta) { row -> row.id }, counts = result.value.counts ?: it.counts, offline = null)
                    }
                    cache?.remember(warehouseId, SavedLists.returns, result.value.rows)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                GoodsOutResult.Unreachable -> if (page != 1 || !showSavedRows()) mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }

    private suspend fun showSavedRows(): Boolean {
        val saved = cache?.saved(warehouseId, SavedLists.returns) ?: return false
        val rowsZone = zone()
        val offline = OfflineRows(rows = saved.value, savedAt = saved.fetchedAt, zone = rowsZone, today = LocalDate.now(rowsZone))
        mutableState.update { it.copy(offline = offline, list = it.list.savedCopy(offlineReturns(saved.value, it.query(page = 1, perPage = perPage), rowsZone))) }
        return true
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
    val pending: List<QueuedAction> = emptyList(),
    val copyAt: Instant? = null,
    val queueEvent: QueueEvent? = null,
) {
    val isWorking: Boolean get() = working != null
}

class ReturnViewModel(
    private val api: GoodsInApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val returnId: Long,
    offlineWork: OfflineWork = NoOfflineWork,
) : ViewModel() {

    private val mutableState = MutableStateFlow(ReturnUiState())
    val state: StateFlow<ReturnUiState> = mutableState.asStateFlow()

    private val queue = RecordQueue(offlineWork, warehouseId, OfflineRecordKey(OfflineRecordType.RETURN, returnId))

    private var base: CustomerReturn? = null

    private var loadJob: Job? = null

    init {
        viewModelScope.launch {
            queue.pending.collect { actions ->
                mutableState.update { current ->
                    current.copy(
                        pending = actions.filter { it.operation in returnOperations && it.state != QueuedActionState.VOID },
                        customerReturn = base?.withQueued(actions) ?: current.customerReturn,
                    )
                }
            }
        }
        queue.watchSettled(viewModelScope) {
            if (!mutableState.value.isWorking) {
                load()
            }
        }
    }

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

    fun queueEventHandled() {
        mutableState.update { it.copy(queueEvent = null) }
    }

    fun clearWriteErrors() {
        mutableState.update { it.copy(writeErrors = emptyMap()) }
    }

    fun undoQueued(localId: Long) = queue.undo(localId)

    fun start() = act(ReturnWrite.Start)

    fun backToStock(itemId: Long, quantity: Double?, locationOrgStockId: Long) =
        act(ReturnWrite.Returned(itemId = itemId, quantity = quantity, locationOrgStockId = locationOrgStockId, clientRequestId = queue.newClientRequestId()))

    fun damaged(itemId: Long, quantity: Double?) = act(ReturnWrite.Damaged(itemId = itemId, quantity = quantity, clientRequestId = queue.newClientRequestId()))

    fun notReturned(itemId: Long, quantity: Double?) = act(ReturnWrite.NotReturned(itemId = itemId, quantity = quantity, clientRequestId = queue.newClientRequestId()))

    fun undo(putId: Long) = act(ReturnWrite.UndoPut(putId))

    fun setAsReturned() = act(ReturnWrite.SetAsReturned)

    private fun act(write: ReturnWrite) {
        val token = sessions.session.value?.token ?: return
        val shown = mutableState.value.customerReturn
        if (shown == null || mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, queueEvent = null, isLoading = false, isRefreshing = false, writeErrors = emptyMap()) }

        viewModelScope.launch {
            val queued = queuedWrite(write, shown)
            if (queued == null) {
                when (queue.statusGate()) {
                    StatusGate.OnlineOnly -> return@launch mutableState.update { it.copy(working = null, queueEvent = QueueEvent.OnlineOnly) }
                    StatusGate.NeedsYou -> return@launch mutableState.update { it.copy(working = null, queueEvent = QueueEvent.NeedsYou) }
                    StatusGate.Go -> Unit
                }
            } else if (queue.shouldQueue() && enqueue(queued)) {
                return@launch
            }
            when (val result = api.writeReturn(token, warehouseId, returnId, write)) {
                is GoodsOutResult.Loaded -> {
                    base = result.value.customerReturn
                    mutableState.update {
                        it.copy(
                            customerReturn = result.value.customerReturn.withQueued(queue.pending.value),
                            copyAt = null,
                            working = null,
                            problem = null,
                            event = GoodsInEvent.Done(
                                action = write,
                                message = result.value.message,
                                isNothingChanged = result.value.status == GoodsInWriteStatus.NOTHING_CHANGED,
                            ),
                        )
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                GoodsOutResult.Unreachable -> if (queued == null || !enqueue(queued)) refuse(GoodsOutResult.Unreachable)
                else -> refuse(result)
            }
        }
    }

    private fun queuedWrite(write: ReturnWrite, shown: CustomerReturn): QueuedReturnWrite? =
        GoodsInOfflinePayloads.returnPut(returnId, write) { itemId -> shown.item(itemId)?.remaining }

    private suspend fun enqueue(queued: QueuedReturnWrite): Boolean {
        queue.enqueue(queued.operation, queued.payload) ?: return false
        mutableState.update { it.copy(working = null, queueEvent = QueueEvent.Queued(queued.operation)) }
        return true
    }

    private fun refuse(result: GoodsOutResult<*>) {
        mutableState.update {
            it.copy(working = null, writeErrors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty(), event = result.goodsInRefusal())
        }
    }

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            val loaded = queue.load(
                live = { api.customerReturn(token, warehouseId, returnId) },
                parse = { body -> GoodsInResponses.parseReturn(HTTP_OK, body) },
            )
            when (val result = loaded.result) {
                is GoodsOutResult.Loaded -> {
                    base = result.value
                    mutableState.update {
                        it.copy(
                            customerReturn = result.value.withQueued(queue.pending.value),
                            copyAt = loaded.copyAt,
                            isLoading = false,
                            isRefreshing = false,
                        )
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}
