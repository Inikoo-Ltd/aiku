package io.aiku.droid.ui.inventory

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.InventoryResponses
import io.aiku.droid.data.api.LocationOrgStockUpdate
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.data.api.PickingChannel
import io.aiku.droid.data.api.StockMovement
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.offline.NoOfflineWork
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.sync.QueueEvent
import io.aiku.droid.ui.sync.RecordQueue
import io.aiku.droid.ui.sync.StatusGate
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Instant

const val RECENT_MOVEMENTS = 5
private const val MIN_LOCATIONS_TO_MOVE = 2
private const val HTTP_OK = 200

enum class SkoAction { Audit, Move, Consume, AddLocation, RemoveLocations }

fun OrgStockDetail.allowedActions(warehouse: WarehouseAbilities): List<SkoAction> {
    if (!can.canEditStocks(warehouse)) {
        return emptyList()
    }
    return buildList {
        if (locations.isNotEmpty()) {
            add(SkoAction.Audit)
        }
        if (locations.size >= MIN_LOCATIONS_TO_MOVE) {
            add(SkoAction.Move)
        }
        if (locations.isNotEmpty()) {
            add(SkoAction.Consume)
        }
        add(SkoAction.AddLocation)
        if (locations.isNotEmpty()) {
            add(SkoAction.RemoveLocations)
        }
    }
}

data class PickingDefaults(val wholesale: Long?, val dropshipping: Long?)

data class SkoUiState(
    val orgStock: OrgStockDetail? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val movements: List<StockMovement>? = null,
    val reasons: StockMovementReasons? = null,
    val reasonsProblem: GoodsOutProblem? = null,
    val working: Any? = null,
    val event: StockEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val pending: List<QueuedAction> = emptyList(),
    val copyAt: Instant? = null,
    val queueEvent: QueueEvent? = null,
) {
    val isWorking: Boolean get() = working != null
}

class SkoViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val orgStockId: Long,
    private val withMovements: Boolean = true,
    offlineWork: OfflineWork = NoOfflineWork,
    private val reasonsMemory: MovementReasonsMemory = MovementReasonsMemory(),
) : ViewModel() {

    private val mutableState = MutableStateFlow(SkoUiState())
    val state: StateFlow<SkoUiState> = mutableState.asStateFlow()

    private val queue = RecordQueue(offlineWork, warehouseId, OfflineRecordKey(OfflineRecordType.ORG_STOCK, orgStockId))

    private var base: OrgStockDetail? = null

    val finder = LocationFinder(
        api = api,
        token = ::token,
        warehouseId = warehouseId,
        scope = viewModelScope,
        onSessionExpired = ::expireSession,
        excluded = { mutableState.value.orgStock?.locations?.map { it.location.id }?.toSet().orEmpty() },
    )

    private var loadJob: Job? = null

    init {
        viewModelScope.launch {
            queue.pending.collect { actions ->
                mutableState.update { current ->
                    current.copy(
                        pending = actions.filter { it.operation in stockOperations && it.state != QueuedActionState.VOID },
                        orgStock = base?.withQueued(actions) ?: current.orgStock,
                    )
                }
            }
        }
        queue.watchSettled(viewModelScope) {
            if (!mutableState.value.isWorking) {
                load()
            }
        }
    }

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isWorking) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() {
        if (!mutableState.value.isWorking) {
            load(isPull = true)
        }
    }

    fun loadReasons() {
        val token = token() ?: return
        if (mutableState.value.reasons != null) {
            return
        }
        reasonsMemory.reasons?.let { remembered ->
            mutableState.update { it.copy(reasons = remembered, reasonsProblem = null) }
            return
        }
        mutableState.update { it.copy(reasonsProblem = null) }
        viewModelScope.launch {
            when (val result = api.stockMovementReasons(token)) {
                is GoodsOutResult.Loaded -> {
                    reasonsMemory.reasons = result.value
                    mutableState.update { it.copy(reasons = result.value) }
                }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(reasonsProblem = result.problem()) }
            }
        }
    }

    fun queueEventHandled() {
        mutableState.update { it.copy(queueEvent = null) }
    }

    fun undoQueued(localId: Long) = queue.undo(localId)

    fun write(requested: OrgStockWrite) {
        val token = token() ?: return
        val write = requested.withRequestId(queue::newClientRequestId)
        val shown = mutableState.value.orgStock ?: return
        if (!startWorking(write)) {
            return
        }
        viewModelScope.launch {
            val queued = queuedWrite(write, shown)
            if (queued == null) {
                if (!passesStatusGate()) {
                    return@launch
                }
            } else if (queue.shouldQueue() && enqueue(queued)) {
                return@launch
            }
            when (val result = api.writeOrgStock(token, warehouseId, orgStockId, write)) {
                is GoodsOutResult.Loaded -> {
                    base = result.value.orgStock
                    mutableState.update {
                        it.copy(
                            orgStock = result.value.orgStock.withQueued(queue.pending.value),
                            copyAt = null,
                            working = null,
                            problem = null,
                            event = StockEvent.Done(action = write, message = result.value.message),
                        )
                    }
                    loadMovements(token)
                }
                GoodsOutResult.Unreachable -> if (queued == null || !enqueue(queued)) refuse(result)
                else -> refuse(result)
            }
        }
    }

    private fun queuedWrite(write: OrgStockWrite, shown: OrgStockDetail): QueuedStockWrite? = when (write) {
        is OrgStockWrite.Audit -> StockOfflinePayloads.auditOrgStock(
            orgStockId = orgStockId,
            counts = write.counts,
            versions = (base ?: shown).versions(),
            clientRequestId = write.clientRequestId ?: queue.newClientRequestId(),
        )
        is OrgStockWrite.Move -> StockOfflinePayloads.moveToRows(write.fromLocationOrgStockId, write.targets, write.clientRequestId ?: queue.newClientRequestId())
        else -> null
    }

    private suspend fun enqueue(queued: QueuedStockWrite): Boolean {
        queue.enqueue(queued.operation, queued.payload) ?: return false
        mutableState.update { it.copy(working = null, queueEvent = QueueEvent.Queued(queued.operation)) }
        return true
    }

    private suspend fun passesStatusGate(): Boolean {
        val event = when (queue.statusGate()) {
            StatusGate.Go -> return true
            StatusGate.OnlineOnly -> QueueEvent.OnlineOnly
            StatusGate.NeedsYou -> QueueEvent.NeedsYou
        }
        mutableState.update { it.copy(working = null, queueEvent = event) }
        return false
    }

    fun setPickingDefaults(defaults: PickingDefaults) {
        val token = token() ?: return
        val updates = pickingUpdates(defaults)
        if (updates.isEmpty() || !startWorking(defaults)) {
            return
        }
        viewModelScope.launch {
            if (!passesStatusGate()) {
                return@launch
            }
            for ((linkId, update) in updates) {
                val result = api.updateLocationOrgStock(token, linkId, update)
                if (result !is GoodsOutResult.Loaded) {
                    refuse(result)
                    return@launch
                }
            }
            when (val reloaded = api.orgStock(token, warehouseId, orgStockId)) {
                is GoodsOutResult.Loaded -> {
                    base = reloaded.value
                    mutableState.update {
                        it.copy(orgStock = reloaded.value.withQueued(queue.pending.value), copyAt = null, working = null, problem = null, event = StockEvent.Done(action = defaults))
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                else -> mutableState.update { it.copy(working = null, event = StockEvent.Done(action = defaults)) }
            }
        }
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun pickingUpdates(defaults: PickingDefaults): List<Pair<Long, LocationOrgStockUpdate>> {
        val locations = mutableState.value.orgStock?.locations.orEmpty()
        val wholesale = defaults.wholesale?.takeIf { id -> locations.any { it.locationOrgStockId == id && !it.isDefaultWholesale } }
        val dropshipping = defaults.dropshipping?.takeIf { id -> locations.any { it.locationOrgStockId == id && !it.isDefaultDropshipping } }
        return listOfNotNull(wholesale, dropshipping).distinct().map { linkId ->
            linkId to LocationOrgStockUpdate(
                makeDefaultFor = setOfNotNull(
                    PickingChannel.Wholesale.takeIf { linkId == wholesale },
                    PickingChannel.Dropshipping.takeIf { linkId == dropshipping },
                ),
            )
        }
    }

    private fun startWorking(action: Any): Boolean {
        if (mutableState.value.orgStock == null || mutableState.value.isWorking) {
            return false
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = action, event = null, isLoading = false, isRefreshing = false, writeErrors = emptyMap()) }
        return true
    }

    private fun refuse(result: GoodsOutResult<*>) {
        if (result == GoodsOutResult.SessionExpired) {
            mutableState.update { it.copy(working = null, isSessionExpired = true) }
            return
        }
        val errors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty()
        mutableState.update { it.copy(working = null, writeErrors = errors, event = result.stockRefusal()) }
    }

    private fun load(isPull: Boolean = false) {
        val token = token() ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            val loaded = queue.load(
                live = { api.orgStock(token, warehouseId, orgStockId) },
                parse = { body -> InventoryResponses.parseOrgStock(HTTP_OK, body) },
            )
            when (val result = loaded.result) {
                is GoodsOutResult.Loaded -> {
                    base = result.value
                    mutableState.update {
                        it.copy(orgStock = result.value.withQueued(queue.pending.value), copyAt = loaded.copyAt, isLoading = false, isRefreshing = false)
                    }
                    if (loaded.copyAt == null) {
                        loadMovements(token)
                        if (withMovements && result.value.locations.isNotEmpty()) {
                            loadReasons()
                        }
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }

    private suspend fun loadMovements(token: String) {
        if (!withMovements) {
            return
        }
        when (val result = api.orgStockMovements(token, warehouseId, orgStockId, flow = null, page = 1, perPage = RECENT_MOVEMENTS)) {
            is GoodsOutResult.Loaded -> mutableState.update { it.copy(movements = result.value.movements.take(RECENT_MOVEMENTS)) }
            GoodsOutResult.SessionExpired -> expireSession()
            else -> Unit
        }
    }

    private fun token(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableState.update { it.copy(isSessionExpired = true) }
    }
}
