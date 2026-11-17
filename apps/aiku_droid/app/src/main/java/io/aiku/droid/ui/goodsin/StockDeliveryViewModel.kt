package io.aiku.droid.ui.goodsin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.CheckResult
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.PlaceTarget
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryWrite
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.LocationFinder
import io.aiku.droid.ui.scan.ScanProblem
import io.aiku.droid.ui.scan.scanProblem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.util.UUID

sealed interface GoodsInEvent {
    data class Done(val action: Any, val message: String?, val isNothingChanged: Boolean, val wasRaised: Boolean = false) : GoodsInEvent
    data class Refused(val message: String, val problem: GoodsOutProblem) : GoodsInEvent
}

sealed interface LineScanOutcome {
    data class Line(val itemId: Long) : LineScanOutcome
    data class Choose(val code: String, val itemIds: List<Long>) : LineScanOutcome
    data class NotOnDelivery(val code: String) : LineScanOutcome
    data class Failed(val code: String, val problem: ScanProblem) : LineScanOutcome
}

data class StockDeliveryUiState(
    val delivery: StockDelivery? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val chosenView: DeliveryView? = null,
    val working: StockDeliveryWrite? = null,
    val event: GoodsInEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val unsurePlacement: StockDeliveryWrite? = null,
    val lookingUpCode: String? = null,
    val scanOutcome: LineScanOutcome? = null,
) {
    val isWorking: Boolean get() = working != null

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
    private val newRequestId: () -> String = { UUID.randomUUID().toString() },
) : ViewModel() {

    private val mutableState = MutableStateFlow(StockDeliveryUiState(delivery = handoff.take(stockDeliveryId)))
    val state: StateFlow<StockDeliveryUiState> = mutableState.asStateFlow()

    val finder = LocationFinder(
        api = inventoryApi,
        token = ::token,
        warehouseId = warehouseId,
        scope = viewModelScope,
        onSessionExpired = ::expireSession,
    )

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isWorking) {
            load()
        }
    }

    fun retry() = load()

    fun signInAgain() {
        sessions.end()
    }

    fun handOver() {
        mutableState.value.delivery?.let(handoff::hand)
    }

    fun selectView(view: DeliveryView) {
        mutableState.update { it.copy(chosenView = view) }
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun receive() = act(StockDeliveryWrite.Receive)

    fun unreceive() = act(StockDeliveryWrite.Unreceive)

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
                    lineOutcome(scanned, lines)
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

    private fun lineOutcome(code: String, itemIds: List<Long>): LineScanOutcome = when (itemIds.size) {
        0 -> LineScanOutcome.NotOnDelivery(code)
        1 -> LineScanOutcome.Line(itemIds.single())
        else -> LineScanOutcome.Choose(code = code, itemIds = itemIds)
    }

    private fun act(write: StockDeliveryWrite) {
        val token = token() ?: return
        if (mutableState.value.delivery == null || mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false, writeErrors = emptyMap(), unsurePlacement = null) }

        viewModelScope.launch {
            when (val result = api.writeStockDelivery(token, warehouseId, stockDeliveryId, write)) {
                is GoodsOutResult.Loaded -> {
                    val outcome = result.value
                    val wasRaised = write is StockDeliveryWrite.Check && outcome.delivery.item(write.itemId)?.wasRaisedAbove(write.skoQuantity) == true
                    mutableState.update {
                        it.copy(
                            delivery = outcome.delivery,
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
                else -> {
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

    private fun load() {
        val token = token() ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.stockDelivery(token, warehouseId, stockDeliveryId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(delivery = result.value, isLoading = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, problem = result.problem()) }
            }
        }
    }

    private fun token(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableState.update { it.copy(isSessionExpired = true) }
    }
}
