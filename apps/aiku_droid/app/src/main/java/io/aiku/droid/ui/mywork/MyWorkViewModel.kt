package io.aiku.droid.ui.mywork

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MyWorkApi
import io.aiku.droid.data.api.MyWorkCounts
import io.aiku.droid.data.api.MyWorkRow
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class MyWorkUiState(
    val rows: List<MyWorkRow> = emptyList(),
    val counts: MyWorkCounts? = null,
    val hasLoaded: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
) {
    val isEmpty: Boolean get() = hasLoaded && rows.isEmpty() && problem == null
}

class MyWorkViewModel(
    private val api: MyWorkApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long?,
) : ViewModel() {

    private val mutableState = MutableStateFlow(MyWorkUiState())
    val state: StateFlow<MyWorkUiState> = mutableState.asStateFlow()

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
            mutableState.value = MyWorkUiState(hasLoaded = true)
            return
        }
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.myWork(token, warehouseId)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(rows = result.value.rows, counts = result.value.counts, hasLoaded = true, isRefreshing = false)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}
