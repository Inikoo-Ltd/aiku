package io.aiku.droid.ui.goodsout

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.DeliveryNoteCounts
import io.aiku.droid.data.api.DispatchPerson
import io.aiku.droid.data.api.DispatchRole
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class GoodsOutBacklogUiState(
    val counts: DeliveryNoteCounts? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val pickers: Choices<DispatchPerson> = Choices(),
    val packers: Choices<DispatchPerson> = Choices(),
)

class GoodsOutBacklogViewModel(
    private val api: GoodsOutApi,
    private val personnel: PickingSessionApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(GoodsOutBacklogUiState())
    val state: StateFlow<GoodsOutBacklogUiState> = mutableState.asStateFlow()

    private var countsJob: Job? = null
    private val personnelJobs = mutableMapOf<DispatchRole, Job>()

    fun onStart() {
        if (countsJob?.isActive != true) {
            load()
        }
        loadPersonnel(DispatchRole.Pickers)
    }

    fun retry() = load()

    fun loadPersonnel(role: DispatchRole) {
        val token = sessions.session.value?.token ?: return
        if (personnelJobs[role]?.isActive == true) {
            return
        }
        mutableState.update { it.withPersonnel(role, it.personnel(role).copy(isLoading = true, problem = null)) }

        personnelJobs[role] = viewModelScope.launch {
            when (val result = personnel.dispatchPersonnel(token, warehouseId, role)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.withPersonnel(role, Choices(items = result.value)) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.withPersonnel(role, it.personnel(role).copy(isLoading = false)).copy(isSessionExpired = true) }
                else -> mutableState.update { it.withPersonnel(role, it.personnel(role).copy(isLoading = false, problem = result.problem())) }
            }
        }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun load() {
        val token = sessions.session.value?.token ?: return
        countsJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }

        countsJob = viewModelScope.launch {
            when (val result = api.deliveryNoteCounts(token, warehouseId, picker = null)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(counts = result.value, isLoading = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, problem = result.problem()) }
            }
        }
    }
}

fun GoodsOutBacklogUiState.personnel(role: DispatchRole): Choices<DispatchPerson> = when (role) {
    DispatchRole.Pickers -> pickers
    DispatchRole.Packers -> packers
}

private fun GoodsOutBacklogUiState.withPersonnel(role: DispatchRole, choices: Choices<DispatchPerson>): GoodsOutBacklogUiState = when (role) {
    DispatchRole.Pickers -> copy(pickers = choices)
    DispatchRole.Packers -> copy(packers = choices)
}
