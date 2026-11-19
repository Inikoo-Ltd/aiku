package io.aiku.droid.ui.locations

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.LocationsOverview
import io.aiku.droid.data.api.WarehouseArea
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.stockRefusal
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class AutoRemoveChange(val enabled: Boolean)

data class WarehouseOverviewUiState(
    val overview: LocationsOverview? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val isSavingAutoRemove: Boolean = false,
    val event: StockEvent? = null,
)

class WarehouseOverviewViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(WarehouseOverviewUiState())
    val state: StateFlow<WarehouseOverviewUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isSavingAutoRemove) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() {
        if (!mutableState.value.isSavingAutoRemove) {
            load(isPull = true)
        }
    }

    fun setAutoRemove(enabled: Boolean) {
        val token = sessions.session.value?.token ?: return
        if (mutableState.value.overview == null || mutableState.value.isSavingAutoRemove) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(isSavingAutoRemove = true, isLoading = false, isRefreshing = false, event = null) }

        viewModelScope.launch {
            when (val result = api.setEmptyLocationsRemoval(token, warehouseId, enabled)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(
                        overview = it.overview?.copy(emptyLocationsRemoval = result.value),
                        isSavingAutoRemove = false,
                        event = StockEvent.Done(AutoRemoveChange(result.value.enabled)),
                    )
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSavingAutoRemove = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isSavingAutoRemove = false, event = result.stockRefusal()) }
            }
        }
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.locationsOverview(token, warehouseId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(overview = result.value, isLoading = false, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}

data class AreasUiState(
    val search: String = "",
    val areas: List<WarehouseArea>? = null,
    val currencyCode: String? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
) {
    fun area(id: Long): WarehouseArea? = areas?.firstOrNull { it.id == id }
}

class AreasViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(AreasUiState())
    val state: StateFlow<AreasUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null
    private var hasAskedCurrency = false

    fun onStart() {
        if (loadJob?.isActive != true) {
            load(debounce = false)
        }
        if (!hasAskedCurrency) {
            loadCurrency()
        }
    }

    fun search(text: String) {
        mutableState.update { it.copy(search = text) }
        load(debounce = true)
    }

    fun retry() = load(debounce = false)

    fun refresh() = load(debounce = false, isPull = true)

    fun signInAgain() {
        sessions.end()
    }

    private fun loadCurrency() {
        val token = sessions.session.value?.token ?: return
        hasAskedCurrency = true
        viewModelScope.launch {
            val result = api.locationsOverview(token, warehouseId)
            if (result is GoodsOutResult.Loaded) {
                mutableState.update { it.copy(currencyCode = result.value.currencyCode) }
            }
        }
    }

    private fun load(debounce: Boolean, isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isRefreshing = isPull) }

        loadJob = viewModelScope.launch {
            if (debounce) {
                delay(SEARCH_DEBOUNCE_MILLIS)
            }
            mutableState.update { it.copy(isLoading = true, problem = null) }
            when (val result = api.areas(token, warehouseId, mutableState.value.search)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(areas = result.value, isLoading = false, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}
