package io.aiku.droid.ui.goodsin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AddableItem
import io.aiku.droid.data.api.CheckResult
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsInResponses
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.PlaceTarget
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryWrite
import io.aiku.droid.data.offline.NoOfflineWork
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.Choices
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.LocationFinder
import io.aiku.droid.ui.scan.ScanProblem
import io.aiku.droid.ui.scan.scanProblem
import io.aiku.droid.ui.sync.QueueEvent
import io.aiku.droid.ui.sync.RecordQueue
import io.aiku.droid.ui.sync.StatusGate
import java.time.Instant
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject

private const val HTTP_OK = 200

sealed interface GoodsInEvent {
    data class Done(val action: Any, val message: String?, val isNothingChanged: Boolean, val wasRaised: Boolean = false) : GoodsInEvent
    data class Refused(val message: String, val problem: GoodsOutProblem) : GoodsInEvent
}

sealed interface LineScanOutcome {
    data class Line(val itemId: Long) : LineScanOutcome
    data class Choose(val code: String, val itemIds: List<Long>) : LineScanOutcome
    data class NotOnDelivery(val code: String, val skoId: Long? = null) : LineScanOutcome
    data class Failed(val code: String, val problem: ScanProblem) : LineScanOutcome
}

data class StockDeliveryUiState(
    val delivery: StockDelivery? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val chosenView: DeliveryView? = null,
    val working: StockDeliveryWrite? = null,
    val event: GoodsInEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val unsurePlacement: StockDeliveryWrite? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: LineScanOutcome? = null,
    val pending: List<QueuedAction> = emptyList(),
    val copyAt: Instant? = null,
    val queueEvent: QueueEvent? = null,
    val addable: Choices<AddableItem> = Choices(),
) {
    val isWorking: Boolean get() = working != null

    val canAddItems: Boolean get() = delivery?.can(GoodsInActionKey.ADD_ITEM) == true

    val view: DeliveryView?
        get() {
            val loaded = delivery ?: return null
            return chosenView?.takeIf { it in loaded.views() } ?: loaded.defaultView()
        }
}

class StockDeliveryHandoff {
    private var delivery: StockDelivery? = null

    fun hand(delivery: StockDelivery) {
        this.delivery = delivery
    }

    fun take(stockDeliveryId: Long): StockDelivery? {
        val handed = delivery?.takeIf { it.id == stockDeliveryId }
        delivery = null
        return handed
    }
}

internal fun GoodsOutResult<*>.goodsInRefusal(): GoodsInEvent.Refused {
    val message = when (this) {
        is GoodsOutResult.Forbidden -> message
        is GoodsOutResult.NotFound -> message
        is GoodsOutResult.Invalid -> message
        else -> ""
    }
    return GoodsInEvent.Refused(message = message, problem = problem())
}

private val StockDeliveryWrite.isPlacement: Boolean get() = this is StockDeliveryWrite.Place || this is StockDeliveryWrite.PlaceAll

class StockDeliveryViewModel(
    private val api: GoodsInApi,
    inventoryApi: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val stockDeliveryId: Long,
    private val handoff: StockDeliveryHandoff = StockDeliveryHandoff(),
    offlineWork: OfflineWork = NoOfflineWork,
    private val newRequestId: () -> String = offlineWork::newClientRequestId,
) : ViewModel() {

    private val queue = RecordQueue(offlineWork, warehouseId, OfflineRecordKey(OfflineRecordType.STOCK_DELIVERY, stockDeliveryId))

    private var base: StockDelivery? = handoff.take(stockDeliveryId)

    private val mutableState = MutableStateFlow(StockDeliveryUiState(delivery = base?.withQueued(queue.pending.value)))
    val state: StateFlow<StockDeliveryUiState> = mutableState.asStateFlow()

    val finder = LocationFinder(
        api = inventoryApi,
        token = ::token,
        warehouseId = warehouseId,
        scope = viewModelScope,
        onSessionExpired = ::expireSession,
    )

    private var loadJob: Job? = null
    private var addableJob: Job? = null

    init {
        viewModelScope.launch {
            queue.pending.collect { actions ->
                mutableState.update { current ->
                    current.copy(
                        pending = actions.filter { it.operation == OfflineOperation.PLACE_STOCK_DELIVERY_ITEM && it.state != QueuedActionState.VOID },
                        delivery = base?.withQueued(actions) ?: current.delivery,
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

    fun signInAgain() {
        sessions.end()
    }

    fun handOver() {
        base?.let(handoff::hand)
    }

    fun queueEventHandled() {
        mutableState.update { it.copy(queueEvent = null) }
    }

    fun undoQueued(localId: Long) = queue.undo(localId)

    val isOnline: StateFlow<Boolean> = queue.online

    fun selectView(view: DeliveryView) {
        mutableState.update { it.copy(chosenView = view) }
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun receive() = act(StockDeliveryWrite.Receive)

    fun unreceive() = act(StockDeliveryWrite.Unreceive)

    fun cancel() = act(StockDeliveryWrite.Cancel)

    fun checkAll() = act(StockDeliveryWrite.CheckAll)

    fun check(itemId: Long, skoQuantity: Double) = act(StockDeliveryWrite.Check(itemId = itemId, skoQuantity = skoQuantity.coerceAtLeast(0.0)))

    fun notReceived(itemId: Long) = act(StockDeliveryWrite.Check(itemId = itemId, skoQuantity = 0.0))

    fun place(itemId: Long, quantity: Double, target: PlaceTarget, setAsPickingLocation: Boolean) = act(
        StockDeliveryWrite.Place(
            itemId = itemId,
            quantity = quantity,
            target = target,
            setAsPickingLocation = setAsPickingLocation,
            clientRequestId = newRequestId(),
        ),
    )

    fun placeAll(itemId: Long, target: PlaceTarget, setAsPickingLocation: Boolean) = act(
        StockDeliveryWrite.PlaceAll(itemId = itemId, target = target, setAsPickingLocation = setAsPickingLocation, clientRequestId = newRequestId()),
    )

    fun retryPlacement() {
        mutableState.value.unsurePlacement?.let(::act)
    }

    fun recordCheck(key: String, result: CheckResult, body: String?, incidentType: String?, incidentQuantity: Double?) = act(
        StockDeliveryWrite.RecordCheck(
            key = key,
            result = result,
            body = body?.trim()?.takeIf { it.isNotEmpty() },
            incidentType = incidentType.takeIf { result == CheckResult.Failed },
            incidentQuantity = incidentQuantity.takeIf { result == CheckResult.Failed },
        ),
    )

    fun clearWriteErrors() {
        mutableState.update { it.copy(writeErrors = emptyMap()) }
    }

    fun scanLine(code: String) {
        val scanned = code.trim()
        val delivery = mutableState.value.delivery ?: return
        if (scanned.isEmpty() || mutableState.value.lookingUpCode != null) {
            return
        }
        val candidates = delivery.scanCandidates(mutableState.value.view)
        val local = candidates.matchingCode(scanned)
        if (local.isNotEmpty()) {
            mutableState.update { it.copy(scanOutcome = lineOutcome(scanned, local.map { item -> item.id })) }
            return
        }
        val token = token() ?: return
        mutableState.update { it.copy(lookingUpCode = scanned, scanOutcome = null) }
        viewModelScope.launch {
            val outcome = when (val result = api.scan(token, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> {
                    val skoId = result.value.sko?.id?.takeIf { result.value.type == ScanMatch.SKO }
                    val lines = candidates.filter { skoId != null && it.sko?.id == skoId }.map { it.id }
                    lineOutcome(scanned, lines, skoId)
                }
                is GoodsOutResult.NotFound -> LineScanOutcome.NotOnDelivery(scanned)
                GoodsOutResult.SessionExpired -> null.also { expireSession() }
                else -> LineScanOutcome.Failed(scanned, result.scanProblem())
            }
            mutableState.update { it.copy(lookingUpCode = null, scanOutcome = outcome) }
        }
    }

    fun scanOutcomeHandled() {
        mutableState.update { it.copy(scanOutcome = null) }
    }

    fun loadAddable(search: String?, orgStockId: Long? = null) {
        val token = token() ?: return
        val delivery = mutableState.value.delivery ?: return
        mutableState.update { it.copy(addable = it.addable.copy(isLoading = true, problem = null)) }
        addableJob?.cancel()
        addableJob = viewModelScope.launch {
            when (val result = api.addableItems(token, warehouseId, delivery.id, search, orgStockId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(addable = Choices(items = result.value)) }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(addable = it.addable.copy(isLoading = false, problem = result.problem())) }
            }
        }
    }

    fun addItem(itemId: Long, skoQuantity: Double) = act(StockDeliveryWrite.AddItem(itemId = itemId, skoQuantity = skoQuantity))

    private fun lineOutcome(code: String, itemIds: List<Long>, skoId: Long? = null): LineScanOutcome = when (itemIds.size) {
        0 -> LineScanOutcome.NotOnDelivery(code, skoId)
        1 -> LineScanOutcome.Line(itemIds.single())
        else -> LineScanOutcome.Choose(code = code, itemIds = itemIds)
    }

    private fun act(write: StockDeliveryWrite) {
        val token = token() ?: return
        val shown = mutableState.value.delivery
        if (shown == null || mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update {
            it.copy(working = write, event = null, queueEvent = null, isLoading = false, isRefreshing = false, writeErrors = emptyMap(), unsurePlacement = null)
        }

        viewModelScope.launch {
            val queued = placement(write, shown)
            if (queued == null) {
                when (queue.statusGate()) {
                    StatusGate.OnlineOnly -> return@launch mutableState.update { it.copy(working = null, queueEvent = QueueEvent.OnlineOnly) }
                    StatusGate.NeedsYou -> return@launch mutableState.update { it.copy(working = null, queueEvent = QueueEvent.NeedsYou) }
                    StatusGate.Go -> Unit
                }
            } else if (queue.shouldQueue() && enqueue(queued)) {
                return@launch
            }
            when (val result = api.writeStockDelivery(token, warehouseId, stockDeliveryId, write)) {
                is GoodsOutResult.Loaded -> {
                    val outcome = result.value
                    val wasRaised = write is StockDeliveryWrite.Check && outcome.delivery.item(write.itemId)?.wasRaisedAbove(write.skoQuantity) == true
                    base = outcome.delivery
                    mutableState.update {
                        it.copy(
                            delivery = outcome.delivery.withQueued(queue.pending.value),
                            copyAt = null,
                            working = null,
                            problem = null,
                            event = GoodsInEvent.Done(
                                action = write,
                                message = outcome.message,
                                isNothingChanged = outcome.status == GoodsInWriteStatus.NOTHING_CHANGED,
                                wasRaised = wasRaised,
                            ),
                        )
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                else -> if (result != GoodsOutResult.Unreachable || queued == null || !enqueue(queued)) {
                    val errors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty()
                    val isUnsure = write.isPlacement && (result == GoodsOutResult.Unreachable || result is GoodsOutResult.ServerError)
                    mutableState.update {
                        it.copy(
                            working = null,
                            writeErrors = errors,
                            unsurePlacement = write.takeIf { isUnsure },
                            event = result.goodsInRefusal(),
                        )
                    }
                }
            }
        }
    }

    private fun placement(write: StockDeliveryWrite, shown: StockDelivery): JsonObject? = when (write) {
        is StockDeliveryWrite.Place -> GoodsInOfflinePayloads.place(
            stockDeliveryId = stockDeliveryId,
            itemId = write.itemId,
            quantity = write.quantity,
            target = write.target,
            setAsPickingLocation = write.setAsPickingLocation,
            clientRequestId = write.clientRequestId,
        )
        is StockDeliveryWrite.PlaceAll -> shown.item(write.itemId)?.skoToPlace?.takeIf { it > 0.0 }?.let { quantity ->
            GoodsInOfflinePayloads.place(
                stockDeliveryId = stockDeliveryId,
                itemId = write.itemId,
                quantity = quantity,
                target = write.target,
                setAsPickingLocation = write.setAsPickingLocation,
                clientRequestId = write.clientRequestId,
            )
        }
        else -> null
    }

    private suspend fun enqueue(payload: JsonObject): Boolean {
        queue.enqueue(OfflineOperation.PLACE_STOCK_DELIVERY_ITEM, payload) ?: return false
        mutableState.update { it.copy(working = null, queueEvent = QueueEvent.Queued(OfflineOperation.PLACE_STOCK_DELIVERY_ITEM)) }
        return true
    }

    private fun load(isPull: Boolean = false) {
        val token = token() ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            val loaded = queue.load(
                live = { api.stockDelivery(token, warehouseId, stockDeliveryId) },
                parse = { body -> GoodsInResponses.parseStockDelivery(HTTP_OK, body) },
            )
            when (val result = loaded.result) {
                is GoodsOutResult.Loaded -> {
                    base = result.value
                    mutableState.update {
                        it.copy(delivery = result.value.withQueued(queue.pending.value), copyAt = loaded.copyAt, isLoading = false, isRefreshing = false)
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }

    private fun token(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableState.update { it.copy(isSessionExpired = true) }
    }
}
