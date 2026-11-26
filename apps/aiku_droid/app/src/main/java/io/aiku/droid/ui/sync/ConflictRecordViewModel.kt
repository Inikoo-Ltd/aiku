package io.aiku.droid.ui.sync

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.data.api.OfflineOperationRecord
import io.aiku.droid.data.api.OfflineOperationsApi
import io.aiku.droid.data.api.OfflineOperationsState
import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.data.api.OfflineResolveOutcome
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.SyncReason
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class ConflictLineResult(
    val line: OfflineOperationEntry,
    val outcome: OfflineOperationEntry? = null,
    val message: String = "",
    val problem: GoodsOutProblem? = null,
) {
    val isResolved: Boolean get() = outcome != null
}

data class ConflictRecordEvent(val resolved: Int, val total: Int)

data class ConflictRecordUiState(
    val lines: List<OfflineOperationEntry> = emptyList(),
    val hasLoaded: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: OfflineResolution? = null,
    val results: List<ConflictLineResult> = emptyList(),
    val event: ConflictRecordEvent? = null,
) {
    val group: ConflictGroup get() = ConflictGroup(record = lines.firstOrNull()?.record, lines = lines)

    val hasResults: Boolean get() = results.isNotEmpty()
}

class ConflictRecordViewModel(
    private val api: OfflineOperationsApi,
    private val sessions: SessionRepository,
    private val offlineWork: OfflineWork,
    private val record: OfflineOperationRecord,
) : ViewModel() {

    private val mutableState = MutableStateFlow(ConflictRecordUiState())
    val state: StateFlow<ConflictRecordUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        val current = mutableState.value
        if (loadJob?.isActive != true && current.working == null && !current.hasResults) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() = load(isPull = true)

    fun signInAgain() {
        sessions.end()
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun resolveAll(resolution: OfflineResolution) {
        val token = sessions.session.value?.token ?: return
        val current = mutableState.value
        if (current.working != null || resolution !in current.group.bulkResolutions) {
            return
        }
        val lines = current.lines
        mutableState.update { it.copy(working = resolution, event = null) }
        viewModelScope.launch {
            val results = mutableListOf<ConflictLineResult>()
            for (line in lines) {
                val operationId = line.key ?: continue
                val result = api.resolve(token, operationId, resolution)
                if (result == GoodsOutResult.SessionExpired) {
                    mutableState.update { it.copy(working = null, isSessionExpired = true) }
                    return@launch
                }
                results += lineResult(line, result)
            }
            val resolved = results.count { it.isResolved }
            mutableState.update { it.copy(working = null, results = results, event = ConflictRecordEvent(resolved = resolved, total = results.size)) }
            if (resolved > 0) {
                offlineWork.syncNow(SyncReason.MANUAL)
            }
        }
    }

    private fun lineResult(line: OfflineOperationEntry, result: GoodsOutResult<OfflineResolveOutcome>): ConflictLineResult = when (result) {
        is GoodsOutResult.Loaded -> ConflictLineResult(line = line, outcome = result.value.operation)
        is GoodsOutResult.Forbidden -> ConflictLineResult(line = line, message = result.message, problem = result.problem())
        is GoodsOutResult.NotFound -> ConflictLineResult(line = line, message = result.message, problem = result.problem())
        is GoodsOutResult.Invalid -> ConflictLineResult(line = line, message = result.message, problem = result.problem())
        else -> ConflictLineResult(line = line, problem = result.problem())
    }

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isRefreshing = isPull, problem = null) }
        loadJob = viewModelScope.launch {
            when (val result = api.operations(token, OfflineOperationsState.Open)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(lines = result.value.filter { line -> line.record == record }, hasLoaded = true, isRefreshing = false)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}
