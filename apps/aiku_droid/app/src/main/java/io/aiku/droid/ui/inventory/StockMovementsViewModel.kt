package io.aiku.droid.ui.inventory

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.MovementFlow
import io.aiku.droid.data.api.MovementPage
import io.aiku.droid.data.api.StockMovement
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

const val MOVEMENTS_PER_PAGE = 50

sealed interface MovementSource {
    val canFilterByFlow: Boolean

    data class Sko(val warehouseId: Long, val orgStockId: Long) : MovementSource {
        override val canFilterByFlow: Boolean get() = true
    }

    data class Location(val locationId: Long) : MovementSource {
        override val canFilterByFlow: Boolean get() = false
    }
}

internal suspend fun InventoryApi.movements(token: String, source: MovementSource, flow: MovementFlow?, page: Int, perPage: Int): GoodsOutResult<MovementPage> =
    when (source) {
        is MovementSource.Sko -> orgStockMovements(token, source.warehouseId, source.orgStockId, flow, page, perPage)
        is MovementSource.Location -> locationMovements(token, source.locationId, page, perPage)
    }

data class StockMovementsUiState(
    val flow: MovementFlow? = null,
    val list: PagedList<StockMovement> = PagedList(),
    val isSessionExpired: Boolean = false,
)

class StockMovementsViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val source: MovementSource,
    private val perPage: Int = MOVEMENTS_PER_PAGE,
) : ViewModel() {

    private val mutableState = MutableStateFlow(StockMovementsUiState())
    val state: StateFlow<StockMovementsUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null

    val canFilterByFlow: Boolean get() = source.canFilterByFlow

    fun onStart() {
        val list = mutableState.value.list
        if (list.loadingPage == null && (!list.hasLoaded || list.page <= 1)) {
            loadPage(1)
        }
    }

    fun selectFlow(flow: MovementFlow?) {
        if (!source.canFilterByFlow || flow == mutableState.value.flow) {
            return
        }
        mutableState.update { it.copy(flow = flow, list = PagedList()) }
        loadPage(1)
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
        val flow = mutableState.value.flow
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = api.movements(token, source, flow, page, perPage)) {
                is GoodsOutResult.Loaded -> mutableState.update { current ->
                    current.copy(list = current.list.loaded(result.value.movements, result.value.meta) { it.id })
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { it.copy(list = it.list.failed(page, result.problem())) }
            }
        }
    }
}
