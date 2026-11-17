package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.FulfilmentInboundApi
import io.aiku.droid.data.api.FulfilmentLocation
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PalletLocationTarget
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class FulfilmentLocationFinderState(
    val query: String = "",
    val results: List<FulfilmentLocation> = emptyList(),
    val isSearching: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val picked: FulfilmentLocation? = null,
) {
    val target: PalletLocationTarget? get() = picked?.let { PalletLocationTarget.Picked(locationId = it.id, code = it.code) }
}

class FulfilmentLocationFinder(
    private val api: FulfilmentInboundApi,
    private val token: () -> String?,
    private val warehouseId: Long,
    private val scope: CoroutineScope,
    private val onSessionExpired: () -> Unit,
) {
    private val mutableState = MutableStateFlow(FulfilmentLocationFinderState())
    val state: StateFlow<FulfilmentLocationFinderState> = mutableState.asStateFlow()

    private var searchJob: Job? = null

    fun reset() {
        searchJob?.cancel()
        mutableState.value = FulfilmentLocationFinderState()
    }

    fun search(query: String) {
        mutableState.update { it.copy(query = query) }
        searchJob?.cancel()
        val needle = query.trim()
        if (needle.isEmpty()) {
            mutableState.update { it.copy(results = emptyList(), isSearching = false, problem = null) }
            return
        }
        searchJob = scope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            runSearch(needle)
        }
    }

    fun pick(location: FulfilmentLocation) {
        mutableState.update { it.copy(picked = location) }
    }

    private suspend fun runSearch(needle: String) {
        val currentToken = token() ?: return
        mutableState.update { it.copy(isSearching = true, problem = null) }
        when (val result = api.fulfilmentLocations(currentToken, warehouseId, needle)) {
            is GoodsOutResult.Loaded -> mutableState.update { current ->
                val refreshed = current.picked?.let { chosen -> result.value.firstOrNull { it.id == chosen.id } }
                current.copy(results = result.value, isSearching = false, picked = refreshed ?: current.picked)
            }
            GoodsOutResult.SessionExpired -> {
                mutableState.update { it.copy(isSearching = false) }
                onSessionExpired()
            }
            else -> mutableState.update { it.copy(isSearching = false, problem = result.problem()) }
        }
    }
}
