package io.aiku.droid.ui.mywork

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MyWorkApi
import io.aiku.droid.data.api.MyWorkCounts
import io.aiku.droid.data.api.MyWorkRow
import io.aiku.droid.data.cache.CachedScreens
import io.aiku.droid.data.cache.ScreenCache
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Instant

data class MyWorkUiState(
    val rows: List<MyWorkRow> = emptyList(),
    val counts: MyWorkCounts? = null,
    val hasLoaded: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val cachedAt: Instant? = null,
) {
    val isEmpty: Boolean get() = hasLoaded && rows.isEmpty() && problem == null
    val lastUpdated: Instant? get() = cachedAt?.takeIf { problem != null }
}

class MyWorkViewModel(
    private val api: MyWorkApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long?,
    private val cache: ScreenCache,
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
        mutableState.update { it.copy(isRefreshing = isPull || it.cachedAt != null, problem = null) }

        loadJob = viewModelScope.launch {
            if (!mutableState.value.hasLoaded) {
                showCachedCopy(warehouseId)
            }
            when (val result = api.myWork(token, warehouseId)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(rows = result.value.rows, counts = result.value.counts, hasLoaded = true, isRefreshing = false, cachedAt = null)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isRefreshing = false, problem = result.problem()) }
            }
        }
    }

    private suspend fun showCachedCopy(warehouseId: Long) {
        val cached = cache.read(warehouseId, CachedScreens.myWork) ?: return
        mutableState.update {
            it.copy(rows = cached.value.rows, counts = cached.value.counts, hasLoaded = true, isRefreshing = true, cachedAt = cached.fetchedAt)
        }
    }
}
