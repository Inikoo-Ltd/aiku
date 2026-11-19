package io.aiku.droid.ui.dashboard

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.WarehouseDashboard
import io.aiku.droid.data.api.WarehouseDashboardApi
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class WarehouseDashboardUiState(
    val dashboard: WarehouseDashboard? = null,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
) {
    val isLoading: Boolean get() = dashboard == null && problem == null
}

class WarehouseDashboardViewModel(
    private val api: WarehouseDashboardApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long?,
) : ViewModel() {

    private val mutableState = MutableStateFlow(WarehouseDashboardUiState())
    val state: StateFlow<WarehouseDashboardUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        load(isPull = false)
    }

    fun refresh() {
        load(isPull = true)
    }

    fun retry() {
        load(isPull = false)
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun load(isPull: Boolean) {
        if (warehouseId == null) {
            mutableState.value = WarehouseDashboardUiState(dashboard = WarehouseDashboard())
            return
        }
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.dashboard(token, warehouseId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(dashboard = result.value, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}
