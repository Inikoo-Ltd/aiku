package io.aiku.droid.ui.fulfilment.outbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.FulfilmentOutboundApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.OutboundWriteStatus
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.PalletReturnScanRequest
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.data.api.ParcelWrite
import io.aiku.droid.data.api.ReturnAddress
import io.aiku.droid.data.api.Shipper
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.Choices
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import java.io.File
import java.util.UUID
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val SCANNED_QUANTITY = 1

sealed interface ReturnTask {
    data class Write(val write: PalletReturnWrite) : ReturnTask
    data class Scan(val request: PalletReturnScanRequest) : ReturnTask
    data class CreateShipment(val parcels: List<ParcelWrite>?, val shipment: PalletReturnWrite.AddShipment) : ReturnTask
}

private val ReturnTask.isRetryable: Boolean
    get() = when (this) {
        is ReturnTask.Scan -> true
        is ReturnTask.Write -> write is PalletReturnWrite.PickItem || write is PalletReturnWrite.PickFromOtherPallet
        is ReturnTask.CreateShipment -> false
    }

private val ReturnTask.shownAs: PalletReturnWrite?
    get() = when (this) {
        is ReturnTask.Write -> write
        is ReturnTask.CreateShipment -> shipment
        is ReturnTask.Scan -> null
    }

class PalletReturnHandoff {
    private var palletReturn: PalletReturn? = null

    fun hand(palletReturn: PalletReturn) {
        this.palletReturn = palletReturn
    }

    fun take(returnId: Long): PalletReturn? {
        val handed = palletReturn?.takeIf { it.id == returnId }
        palletReturn = null
        return handed
    }

    companion object {
        val shared = PalletReturnHandoff()
    }
}

data class PalletReturnUiState(
    val palletReturn: PalletReturn? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: PalletReturnWrite? = null,
    val scanning: String? = null,
    val queuedScans: Int = 0,
    val event: OutboundEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val unsure: ReturnTask? = null,
    val selectedPalletId: Long? = null,
    val shippers: Choices<Shipper> = Choices(),
    val addresses: Choices<ReturnAddress> = Choices(),
) {
    val isWorking: Boolean get() = working != null || scanning != null

    val canRefresh: Boolean get() = !isWorking && queuedScans == 0
}

class PalletReturnViewModel(
    private val api: FulfilmentOutboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val returnId: Long,
    private val handoff: PalletReturnHandoff = PalletReturnHandoff.shared,
    private val newRequestId: () -> String = { UUID.randomUUID().toString() },
) : ViewModel() {

    private val mutableState = MutableStateFlow(PalletReturnUiState(palletReturn = handoff.take(returnId)))
    val state: StateFlow<PalletReturnUiState> = mutableState.asStateFlow()

    private val pending = ArrayDeque<ReturnTask>()
    private var loadJob: Job? = null
    private var drainJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && drainJob?.isActive != true) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() {
        if (drainJob?.isActive != true) {
            load(isPull = true)
        }
    }

    fun signInAgain() {
        sessions.end()
    }

    fun handOver() {
        mutableState.value.palletReturn?.let(handoff::hand)
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun clearWriteErrors() {
        mutableState.update { it.copy(writeErrors = emptyMap()) }
    }

    fun startPicking() = act(PalletReturnWrite.StartPicking)

    fun finishPicking() = act(PalletReturnWrite.FinishPicking)

    fun dispatch() = act(PalletReturnWrite.Dispatch)

    fun printInserts() = act(PalletReturnWrite.PrintInserts)

    fun pickPallet(lineId: Long) = act(PalletReturnWrite.PickPallet(lineId))

    fun pickItem(lineId: Long, quantity: Int) = act(PalletReturnWrite.PickItem(lineId = lineId, quantity = quantity.coerceAtLeast(1), clientRequestId = newRequestId()))

    fun pickAllOfItem(lineId: Long) = act(PalletReturnWrite.PickItem(lineId = lineId, quantity = null, clientRequestId = newRequestId()))

    fun notPicked(lineId: Long, quantity: Int? = null) = act(PalletReturnWrite.NotPicked(lineId = lineId, quantity = quantity?.coerceAtLeast(1)))

    fun undo(lineId: Long) = act(PalletReturnWrite.Undo(lineId))

    fun pickFromOtherPallet(palletStoredItemId: Long, quantity: Int, fromLineId: Long?) = act(
        PalletReturnWrite.PickFromOtherPallet(
            palletStoredItemId = palletStoredItemId,
            quantity = quantity.coerceAtLeast(1),
            fromLineId = fromLineId,
            clientRequestId = newRequestId(),
        ),
    )

    fun setParcels(parcels: List<ParcelWrite>) = act(PalletReturnWrite.SetParcels(parcels))

    fun createShipment(shipperId: Long, tracking: String?, parcels: List<ParcelWrite>?) {
        if (!canAct()) {
            return
        }
        enqueue(ReturnTask.CreateShipment(parcels = parcels, shipment = PalletReturnWrite.AddShipment(shipperId = shipperId, tracking = tracking?.trim()?.takeIf { it.isNotEmpty() })))
    }

    fun deleteShipment(shipmentId: Long) = act(PalletReturnWrite.DeleteShipment(shipmentId))

    fun printLabel(shipmentId: Long) = act(PalletReturnWrite.PrintLabel(shipmentId))

    fun retryUnsure() {
        if (mutableState.value.unsure == null || drainJob?.isActive == true) {
            return
        }
        mutableState.update { it.copy(unsure = null) }
        drainJob = viewModelScope.launch { drain() }
    }

    fun scan(code: String) {
        val scanned = code.trim()
        val palletReturn = mutableState.value.palletReturn ?: return
        if (scanned.isEmpty()) {
            return
        }
        if (palletReturn.canScan) {
            enqueue(ReturnTask.Scan(PalletReturnScanRequest(barcode = scanned, quantity = SCANNED_QUANTITY, clientRequestId = newRequestId())))
        } else {
            mutableState.update { it.copy(event = localMatch(palletReturn, scanned)) }
        }
    }

    fun selectPallet(palletId: Long?) {
        mutableState.update { it.copy(selectedPalletId = palletId) }
    }

    fun loadShippers() {
        val token = token() ?: return
        if (mutableState.value.shippers.isLoading) {
            return
        }
        mutableState.update { it.copy(shippers = it.shippers.copy(isLoading = true, problem = null)) }
        viewModelScope.launch {
            when (val result = api.palletReturnShippers(token, warehouseId, returnId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(shippers = Choices(items = result.value)) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(shippers = it.shippers.copy(isLoading = false), isSessionExpired = true) }
                else -> mutableState.update { it.copy(shippers = it.shippers.copy(isLoading = false, problem = result.problem())) }
            }
        }
    }

    fun loadAddresses() {
        val token = token() ?: return
        if (mutableState.value.addresses.isLoading) {
            return
        }
        mutableState.update { it.copy(addresses = it.addresses.copy(isLoading = true, problem = null)) }
        viewModelScope.launch {
            when (val result = api.palletReturnAddresses(token, warehouseId, returnId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(addresses = Choices(items = result.value)) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(addresses = it.addresses.copy(isLoading = false), isSessionExpired = true) }
                else -> mutableState.update { it.copy(addresses = it.addresses.copy(isLoading = false, problem = result.problem())) }
            }
        }
    }

    fun setLeaving(collection: Boolean, addressId: Long?) = act(PalletReturnWrite.SetLeaving(collection = collection, addressId = addressId))

    suspend fun downloadLabel(shipmentId: Long, target: File): AttachmentDownload {
        val token = token() ?: return AttachmentDownload.SessionExpired
        val result = api.downloadShipmentLabel(token, warehouseId, returnId, shipmentId, target)
        if (result == AttachmentDownload.SessionExpired) {
            mutableState.update { it.copy(isSessionExpired = true) }
        }
        return result
    }

    private fun localMatch(palletReturn: PalletReturn, code: String): OutboundEvent {
        val palletLine = palletReturn.pallets.firstOrNull { it.pallet.matches(code) }
        if (palletLine != null) {
            mutableState.update { it.copy(selectedPalletId = palletLine.pallet.id) }
            return OutboundEvent.LineFound(palletLine.lineId)
        }
        val itemLines = palletReturn.storedItems.flatMap { it.lines }
        val pallet = itemLines.firstNotNullOfOrNull { line -> line.pallet?.takeIf { it.matches(code) } }
        if (pallet != null) {
            mutableState.update { it.copy(selectedPalletId = pallet.id) }
            return OutboundEvent.PalletFound(palletId = pallet.id, reference = pallet.reference)
        }
        val item = palletReturn.storedItems.firstOrNull { it.storedItem.matches(code) } ?: return OutboundEvent.NotOnReturn(code)
        val selected = mutableState.value.selectedPalletId
        val line = item.lines.firstOrNull { it.pallet?.id == selected && it.hasActions }
            ?: item.lines.firstOrNull { it.can(PalletReturnActionKey.PICK) }
            ?: item.lines.firstOrNull { it.hasActions }
            ?: item.lines.firstOrNull()
            ?: return OutboundEvent.NotOnReturn(code)
        return OutboundEvent.LineFound(line.lineId)
    }

    private fun canAct(): Boolean = token() != null && mutableState.value.palletReturn != null && drainJob?.isActive != true

    private fun act(write: PalletReturnWrite) {
        if (canAct()) {
            enqueue(ReturnTask.Write(write))
        }
    }

    private fun enqueue(task: ReturnTask) {
        if (token() == null) {
            return
        }
        loadJob?.cancel()
        pending.addLast(task)
        mutableState.update { it.copy(queuedScans = pending.count { queued -> queued is ReturnTask.Scan }, isLoading = false, isRefreshing = false, unsure = null) }
        if (drainJob?.isActive != true) {
            drainJob = viewModelScope.launch { drain() }
        }
    }

    private suspend fun drain() {
        while (pending.isNotEmpty()) {
            val token = token() ?: return
            val task = pending.first()
            mutableState.update {
                it.copy(
                    working = task.shownAs,
                    scanning = (task as? ReturnTask.Scan)?.request?.barcode,
                    writeErrors = emptyMap(),
                )
            }
            val failure = when (task) {
                is ReturnTask.Write -> runWrite(token, task.write, task.write)
                is ReturnTask.CreateShipment -> runShipment(token, task)
                is ReturnTask.Scan -> runScan(token, task.request)
            }
            when {
                failure == null -> pending.removeFirst()
                failure == GoodsOutResult.SessionExpired -> {
                    pending.clear()
                    mutableState.update { it.copy(working = null, scanning = null, queuedScans = 0, isSessionExpired = true) }
                    return
                }
                failure.isUnsure && task.isRetryable -> {
                    mutableState.update { it.copy(working = null, scanning = null, unsure = task, event = failure.outboundRefusal(task.shownAs)) }
                    return
                }
                else -> {
                    pending.removeFirst()
                    mutableState.update {
                        it.copy(
                            writeErrors = (failure as? GoodsOutResult.Invalid)?.errors.orEmpty(),
                            event = failure.outboundRefusal(task.shownAs),
                        )
                    }
                }
            }
            mutableState.update { it.copy(working = null, scanning = null, queuedScans = pending.count { queued -> queued is ReturnTask.Scan }) }
        }
    }

    private suspend fun runWrite(token: String, write: PalletReturnWrite, shownAs: PalletReturnWrite): GoodsOutResult<*>? =
        when (val result = api.writePalletReturn(token, warehouseId, returnId, write)) {
            is GoodsOutResult.Loaded -> {
                val outcome = result.value
                mutableState.update {
                    it.copy(
                        palletReturn = outcome.palletReturn,
                        problem = null,
                        event = OutboundEvent.Done(
                            action = shownAs,
                            message = outcome.message,
                            isNothingChanged = outcome.status == OutboundWriteStatus.NOTHING_CHANGED,
                        ),
                    )
                }
                null
            }
            else -> result
        }

    private suspend fun runShipment(token: String, task: ReturnTask.CreateShipment): GoodsOutResult<*>? {
        if (task.parcels != null) {
            when (val saved = api.writePalletReturn(token, warehouseId, returnId, PalletReturnWrite.SetParcels(task.parcels))) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(palletReturn = saved.value.palletReturn) }
                else -> return saved
            }
        }
        return runWrite(token, task.shipment, task.shipment)
    }

    private suspend fun runScan(token: String, request: PalletReturnScanRequest): GoodsOutResult<*>? =
        when (val result = api.scanPalletReturn(token, warehouseId, returnId, request)) {
            is GoodsOutResult.Loaded -> {
                val outcome = result.value
                mutableState.update {
                    it.copy(
                        palletReturn = outcome.palletReturn,
                        problem = null,
                        event = OutboundEvent.Scanned(code = request.barcode, status = outcome.status, message = outcome.message, lineId = outcome.lineId),
                    )
                }
                null
            }
            else -> result
        }

    private fun load(isPull: Boolean = false) {
        val token = token() ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.palletReturn(token, warehouseId, returnId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(palletReturn = result.value, isLoading = false, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }

    private fun token(): String? = sessions.session.value?.token
}
