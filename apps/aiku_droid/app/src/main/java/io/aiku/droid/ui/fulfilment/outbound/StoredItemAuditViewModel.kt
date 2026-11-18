package io.aiku.droid.ui.fulfilment.outbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AuditItemTarget
import io.aiku.droid.data.api.FulfilmentOutboundApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OutboundStoredItemRef
import io.aiku.droid.data.api.OutboundWriteStatus
import io.aiku.droid.data.api.StoredItemAudit
import io.aiku.droid.data.api.StoredItemAuditWrite
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.Choices
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.SEARCH_DEBOUNCE_MILLIS
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.util.UUID

private const val SCANNED_ADD = 1

data class StoredItemAuditUiState(
    val audit: StoredItemAudit? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: StoredItemAuditWrite? = null,
    val queuedScans: Int = 0,
    val event: OutboundEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val unsure: StoredItemAuditWrite? = null,
    val lastScanned: String? = null,
    val choiceSearch: String = "",
    val choices: Choices<OutboundStoredItemRef> = Choices(),
) {
    val isWorking: Boolean get() = working != null
}

class StoredItemAuditViewModel(
    private val api: FulfilmentOutboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val auditId: Long,
    private val newRequestId: () -> String = { UUID.randomUUID().toString() },
) : ViewModel() {

    private val mutableState = MutableStateFlow(StoredItemAuditUiState())
    val state: StateFlow<StoredItemAuditUiState> = mutableState.asStateFlow()

    private val pending = ArrayDeque<StoredItemAuditWrite>()
    private var loadJob: Job? = null
    private var drainJob: Job? = null
    private var choicesJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && drainJob?.isActive != true) {
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

    fun clearWriteErrors() {
        mutableState.update { it.copy(writeErrors = emptyMap()) }
    }

    fun count(storedItemId: Long, auditedQuantity: Int) =
        act(StoredItemAuditWrite.Count(target = AuditItemTarget.Item(storedItemId), auditedQuantity = auditedQuantity.coerceAtLeast(0)))

    fun removeCount(deltaId: Long) = act(StoredItemAuditWrite.RemoveCount(deltaId))

    fun complete() = act(StoredItemAuditWrite.Complete)

    fun scan(code: String) {
        val scanned = code.trim()
        if (scanned.isEmpty() || mutableState.value.audit == null) {
            return
        }
        enqueue(StoredItemAuditWrite.Add(target = AuditItemTarget.Barcode(scanned), add = SCANNED_ADD, clientRequestId = newRequestId()))
    }

    fun retryUnsure() {
        if (mutableState.value.unsure == null || drainJob?.isActive == true) {
            return
        }
        mutableState.update { it.copy(unsure = null) }
        drainJob = viewModelScope.launch { drain() }
    }

    fun searchChoices(text: String) {
        mutableState.update { it.copy(choiceSearch = text) }
        choicesJob?.cancel()
        choicesJob = viewModelScope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            fetchChoices()
        }
    }

    fun loadChoices() {
        choicesJob?.cancel()
        choicesJob = viewModelScope.launch { fetchChoices() }
    }

    private suspend fun fetchChoices() {
        val token = token() ?: return
        mutableState.update { it.copy(choices = it.choices.copy(isLoading = true, problem = null)) }
        when (val result = api.auditStoredItemChoices(token, warehouseId, auditId, mutableState.value.choiceSearch.trim().takeIf { it.isNotEmpty() })) {
            is GoodsOutResult.Loaded -> mutableState.update { it.copy(choices = Choices(items = result.value)) }
            GoodsOutResult.SessionExpired -> mutableState.update { it.copy(choices = Choices(), isSessionExpired = true) }
            else -> mutableState.update { it.copy(choices = it.choices.copy(isLoading = false, problem = result.problem())) }
        }
    }

    private fun act(write: StoredItemAuditWrite) {
        if (mutableState.value.audit != null && drainJob?.isActive != true) {
            enqueue(write)
        }
    }

    private fun enqueue(write: StoredItemAuditWrite) {
        if (token() == null) {
            return
        }
        loadJob?.cancel()
        pending.addLast(write)
        mutableState.update { it.copy(queuedScans = pending.count { queued -> queued is StoredItemAuditWrite.Add }, isLoading = false, unsure = null) }
        if (drainJob?.isActive != true) {
            drainJob = viewModelScope.launch { drain() }
        }
    }

    private suspend fun drain() {
        while (pending.isNotEmpty()) {
            val token = token() ?: return
            val write = pending.first()
            mutableState.update { it.copy(working = write, writeErrors = emptyMap()) }
            when (val result = api.writeStoredItemAudit(token, warehouseId, auditId, write)) {
                is GoodsOutResult.Loaded -> {
                    pending.removeFirst()
                    val outcome = result.value
                    val scannedCode = ((write as? StoredItemAuditWrite.Add)?.target as? AuditItemTarget.Barcode)?.code
                    mutableState.update {
                        it.copy(
                            audit = outcome.audit,
                            problem = null,
                            lastScanned = scannedCode?.let { code -> outcome.audit.lineForCode(code)?.storedItem?.reference ?: code } ?: it.lastScanned,
                            event = OutboundEvent.Done(action = write, message = outcome.message, isNothingChanged = outcome.status == OutboundWriteStatus.NOTHING_CHANGED),
                        )
                    }
                }
                GoodsOutResult.SessionExpired -> {
                    pending.clear()
                    mutableState.update { it.copy(working = null, queuedScans = 0, isSessionExpired = true) }
                    return
                }
                else -> if (result.isUnsure && write is StoredItemAuditWrite.Add) {
                    mutableState.update { it.copy(working = null, unsure = write, event = result.outboundRefusal(write)) }
                    return
                } else {
                    pending.removeFirst()
                    mutableState.update {
                        it.copy(writeErrors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty(), event = result.outboundRefusal(write))
                    }
                }
            }
            mutableState.update { it.copy(working = null, queuedScans = pending.count { queued -> queued is StoredItemAuditWrite.Add }) }
        }
    }

    private fun load() {
        val token = token() ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.storedItemAudit(token, warehouseId, auditId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(audit = result.value, isLoading = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, problem = result.problem()) }
            }
        }
    }

    private fun token(): String? = sessions.session.value?.token
}
