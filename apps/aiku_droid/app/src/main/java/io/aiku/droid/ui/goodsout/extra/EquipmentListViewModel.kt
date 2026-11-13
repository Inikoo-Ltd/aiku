package io.aiku.droid.ui.goodsout.extra

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class EquipmentListUiState<T>(
    val items: List<T> = emptyList(),
    val hasLoaded: Boolean = false,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
)

class EquipmentListViewModel<T>(
    private val sessions: SessionRepository,
    private val fetch: suspend (String) -> GoodsOutResult<List<T>>,
) : ViewModel() {

    private val mutableState = MutableStateFlow(EquipmentListUiState<T>())
    val state: StateFlow<EquipmentListUiState<T>> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true) {
            load()
        }
    }

    fun retry() = load()

    fun signInAgain() {
        sessions.end()
    }

    private fun load() {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = fetch(token)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(items = result.value, hasLoaded = true, isLoading = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, problem = result.problem()) }
            }
        }
    }
}
