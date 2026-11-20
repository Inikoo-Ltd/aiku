package io.aiku.droid.ui.sync

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.ClockingResponses
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.data.api.OfflineOperationRecord
import io.aiku.droid.data.api.OfflineOperationsApi
import io.aiku.droid.data.api.OfflineOperationsState
import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.offline.SyncReason
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.jsonObject

private const val DATA = "data"
private const val REFERENCE = "reference"
private const val CODE = "code"

fun recordReference(body: String): String? = runCatching {
    val data = ClockingResponses.json.parseToJsonElement(body).jsonObject[DATA] as? JsonObject
    listOf(REFERENCE, CODE).firstNotNullOfOrNull { key -> (data?.get(key) as? JsonPrimitive)?.content?.takeIf { it.isNotBlank() } }
}.getOrNull()

data class QueueGroup(val record: OfflineRecordKey, val title: String?, val actions: List<QueuedAction>)

data class SyncQueueUiState(
    val groups: List<QueueGroup> = emptyList(),
    val isOnline: Boolean = true,
    val waitingCount: Int = 0,
    val needsYouCount: Int = 0,
) {
    val isEmpty: Boolean get() = groups.isEmpty()
}

class SyncQueueViewModel(
    private val offlineWork: OfflineWork,
    private val api: OfflineOperationsApi? = null,
    private val sessions: SessionRepository? = null,
) : ViewModel() {

    private val mutableState = MutableStateFlow(SyncQueueUiState())
    val state: StateFlow<SyncQueueUiState> = mutableState.asStateFlow()

    private val titles = mutableMapOf<OfflineRecordKey, String>()
    private val serverTitles = MutableStateFlow<Map<OfflineRecordKey, String>>(emptyMap())

    init {
        viewModelScope.launch {
            combine(offlineWork.queue, offlineWork.isOnline, offlineWork.waitingCount, offlineWork.needsYouCount, serverTitles) { queue, online, waiting, needsYou, _ ->
                SyncQueueUiState(groups = groups(queue), isOnline = online, waitingCount = waiting, needsYouCount = needsYou)
            }.collect { next -> mutableState.value = next }
        }
        viewModelScope.launch {
            val token = sessions?.session?.value?.token ?: return@launch
            val recent = api?.operations(token, OfflineOperationsState.Recent) as? GoodsOutResult.Loaded ?: return@launch
            serverTitles.value = recent.value.mapNotNull { entry ->
                val key = entry.record?.let(::recordKeyOf) ?: return@mapNotNull null
                val title = entry.labels.title?.substringBefore(TITLE_SEPARATOR)?.trim()?.takeIf { it.isNotBlank() } ?: return@mapNotNull null
                key to title
            }.toMap()
        }
    }

    fun syncNow() = offlineWork.syncNow(SyncReason.MANUAL)

    fun undo(localId: Long) = offlineWork.undo(localId)

    private suspend fun groups(queue: List<QueuedAction>): List<QueueGroup> = queue
        .filter { it.state != QueuedActionState.VOID }
        .sortedWith(compareBy<QueuedAction> { it.performedAt }.thenBy { it.localId })
        .groupBy { it.record }
        .map { (record, actions) -> QueueGroup(record = record, title = titleOf(record), actions = actions) }

    private suspend fun titleOf(record: OfflineRecordKey): String? {
        titles[record]?.let { return it }
        val title = offlineWork.downloaded(record)?.body?.let(::recordReference) ?: serverTitles.value[record]
        title?.let { titles[record] = it }
        return title
    }

    private fun recordKeyOf(record: OfflineOperationRecord): OfflineRecordKey? {
        val type = when (record.type) {
            "delivery_note" -> OfflineRecordType.DELIVERY_NOTE
            "return" -> OfflineRecordType.RETURN
            "stock_delivery" -> OfflineRecordType.STOCK_DELIVERY
            "location" -> OfflineRecordType.LOCATION
            "org_stock" -> OfflineRecordType.ORG_STOCK
            else -> return null
        }
        return OfflineRecordKey(type, record.id)
    }

    private companion object {
        const val TITLE_SEPARATOR = " · "
    }
}

data class ResolveConflictsUiState(
    val operations: List<OfflineOperationEntry> = emptyList(),
    val hasLoaded: Boolean = false,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
) {
    val isEmpty: Boolean get() = hasLoaded && operations.isEmpty()
}

class ResolveConflictsViewModel(
    private val api: OfflineOperationsApi,
    private val sessions: SessionRepository,
) : ViewModel() {

    private val mutableState = MutableStateFlow(ResolveConflictsUiState())
    val state: StateFlow<ResolveConflictsUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() = load(isPull = true)

    fun signInAgain() {
        sessions.end()
    }

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }
        loadJob = viewModelScope.launch {
            when (val result = api.operations(token, OfflineOperationsState.Open)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(operations = result.value, hasLoaded = true, isLoading = false, isRefreshing = false)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}

sealed interface ConflictEvent {
    data class Resolved(val resolution: OfflineResolution) : ConflictEvent
    data object RolledBack : ConflictEvent
    data class Refused(val message: String, val problem: GoodsOutProblem) : ConflictEvent
}

data class ConflictUiState(
    val operation: OfflineOperationEntry? = null,
    val isLoading: Boolean = false,
    val isGone: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: Any? = null,
    val ranAgain: List<OfflineOperationEntry> = emptyList(),
    val resolvedWith: OfflineResolution? = null,
    val isClosed: Boolean = false,
    val event: ConflictEvent? = null,
) {
    val isWorking: Boolean get() = working != null
}

data object RollbackRequest

class ConflictViewModel(
    private val api: OfflineOperationsApi,
    private val sessions: SessionRepository,
    private val offlineWork: OfflineWork,
    private val operationId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(ConflictUiState())
    val state: StateFlow<ConflictUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isWorking && !mutableState.value.isClosed) {
            load()
        }
    }

    fun retry() = load()

    fun signInAgain() {
        sessions.end()
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun resolve(resolution: OfflineResolution) {
        val token = sessions.session.value?.token ?: return
        val operation = mutableState.value.operation ?: return
        if (mutableState.value.isWorking || resolution !in operation.allowedResolutions) {
            return
        }
        mutableState.update { it.copy(working = resolution, event = null) }
        viewModelScope.launch {
            when (val result = api.resolve(token, operationId, resolution)) {
                is GoodsOutResult.Loaded -> {
                    mutableState.update {
                        it.copy(
                            operation = result.value.operation,
                            ranAgain = result.value.results,
                            resolvedWith = resolution,
                            isClosed = true,
                            working = null,
                            event = ConflictEvent.Resolved(resolution),
                        )
                    }
                    offlineWork.syncNow(SyncReason.MANUAL)
                }
                else -> refuse(result)
            }
        }
    }

    fun rollback(reason: String? = null) {
        val token = sessions.session.value?.token ?: return
        val operation = mutableState.value.operation ?: return
        if (mutableState.value.isWorking || !operation.canRollback) {
            return
        }
        mutableState.update { it.copy(working = RollbackRequest, event = null) }
        viewModelScope.launch {
            when (val result = api.rollback(token, operationId, reason)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(operation = result.value, isClosed = true, working = null, event = ConflictEvent.RolledBack)
                }
                else -> refuse(result)
            }
        }
    }

    private fun refuse(result: GoodsOutResult<*>) {
        if (result == GoodsOutResult.SessionExpired) {
            mutableState.update { it.copy(working = null, isSessionExpired = true) }
            return
        }
        val message = when (result) {
            is GoodsOutResult.Forbidden -> result.message
            is GoodsOutResult.NotFound -> result.message
            is GoodsOutResult.Invalid -> result.message
            else -> ""
        }
        mutableState.update { it.copy(working = null, event = ConflictEvent.Refused(message, result.problem())) }
    }

    private fun load() {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }
        loadJob = viewModelScope.launch {
            when (val found = find(token, OfflineOperationsState.Open) ?: find(token, OfflineOperationsState.Recent) ?: GoodsOutResult.Loaded(null)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    val entry = found.value
                    it.copy(operation = entry ?: it.operation, isGone = entry == null, isLoading = false)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, problem = found.problem()) }
            }
        }
    }

    private suspend fun find(token: String, state: OfflineOperationsState): GoodsOutResult<OfflineOperationEntry?>? =
        when (val result = api.operations(token, state)) {
            is GoodsOutResult.Loaded -> result.value.firstOrNull { it.key == operationId }?.let { GoodsOutResult.Loaded(it) }
                ?: GoodsOutResult.Loaded<OfflineOperationEntry?>(null).takeIf { state == OfflineOperationsState.Recent }
            GoodsOutResult.SessionExpired -> GoodsOutResult.SessionExpired
            GoodsOutResult.Unreachable -> GoodsOutResult.Unreachable
            is GoodsOutResult.Forbidden -> result
            is GoodsOutResult.NotFound -> result
            is GoodsOutResult.Invalid -> result
            is GoodsOutResult.ServerError -> result
        }
}
