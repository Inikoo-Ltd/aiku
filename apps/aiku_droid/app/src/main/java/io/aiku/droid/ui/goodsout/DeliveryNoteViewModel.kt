package io.aiku.droid.ui.goodsout

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryAddressFields
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.NoteActionStatus
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.data.api.Picker
import io.aiku.droid.data.api.ScanPackItem
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPackStatus
import io.aiku.droid.data.api.ScanPickItem
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.ScanPickStatus
import io.aiku.droid.data.api.Shipper
import io.aiku.droid.data.api.ShipperDirective
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.api.WriteErrorKey
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.io.File
import java.time.Instant

data class Choices<T>(
    val items: List<T> = emptyList(),
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
)

data class ScanTarget(
    val lineId: Long,
    val barcode: String,
    val locationCode: String?,
    val locationOrgStockId: Long?,
)

data class ScanLogEntry(
    val code: String,
    val at: Instant,
    val status: String,
    val quantity: Double = 0.0,
    val message: String? = null,
)

object ScanLogStatus {
    const val FAILED = "failed"
}

sealed interface DeliveryNoteEvent {
    data class Acted(val message: String?, val isNothingChanged: Boolean, val route: Any?, val warning: String? = null) : DeliveryNoteEvent
    data class Refused(val message: String, val problem: GoodsOutProblem) : DeliveryNoteEvent
    data class ScanFeedback(val message: String?) : DeliveryNoteEvent
    data class LineDone(val code: String, val isEverythingPicked: Boolean) : DeliveryNoteEvent
    data class LinePacked(val code: String) : DeliveryNoteEvent
}

sealed interface ShipmentFollowUp {
    val shipperId: Long

    data class ConfirmOverride(val message: String, override val shipperId: Long, val tracking: String?, val address: DeliveryAddressFields? = null) : ShipmentFollowUp
    data class FixAddress(val message: String, override val shipperId: Long, val overrideLock: Boolean = false) : ShipmentFollowUp
}

private sealed interface QueuedScan {
    val barcode: String

    data class Pick(val request: ScanPickRequest) : QueuedScan {
        override val barcode: String get() = request.barcode
    }

    data class Pack(val request: ScanPackRequest) : QueuedScan {
        override val barcode: String get() = request.barcode
    }
}

data class DeliveryNoteUiState(
    val note: DeliveryNote? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: DeliveryNoteWrite? = null,
    val event: DeliveryNoteEvent? = null,
    val trolleys: Choices<Trolley> = Choices(),
    val pickedBays: Choices<PickedBay> = Choices(),
    val pickers: Choices<Picker> = Choices(),
    val scanTarget: ScanTarget? = null,
    val scanLog: List<ScanLogEntry> = emptyList(),
    val queuedScans: Int = 0,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val shipmentFollowUp: ShipmentFollowUp? = null,
    val shippers: Choices<Shipper> = Choices(),
    val shipperDirective: ShipperDirective? = null,
) {
    val isWorking: Boolean get() = working != null
}

class DeliveryNoteHandoff {
    private var note: DeliveryNote? = null

    fun hand(note: DeliveryNote) {
        this.note = note
    }

    fun take(deliveryNoteId: Long): DeliveryNote? {
        val handed = note?.takeIf { it.id == deliveryNoteId }
        note = null
        return handed
    }
}

class DeliveryNoteViewModel(
    private val api: GoodsOutApi,
    private val sessions: SessionRepository,
    private val deliveryNoteId: Long,
    private val handoff: DeliveryNoteHandoff = DeliveryNoteHandoff(),
    private val clock: () -> Instant = Instant::now,
) : ViewModel() {

    private val mutableState = MutableStateFlow(DeliveryNoteUiState(note = handoff.take(deliveryNoteId)))
    val state: StateFlow<DeliveryNoteUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null
    private val pendingScans = ArrayDeque<QueuedScan>()
    private var scanJob: Job? = null

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
        mutableState.value.note?.let(handoff::hand)
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun act(write: DeliveryNoteWrite) {
        val token = sessions.session.value?.token ?: return
        val before = mutableState.value.note ?: return
        if (mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false, writeErrors = emptyMap()) }

        viewModelScope.launch {
            when (val result = api.write(token, deliveryNoteId, write)) {
                is GoodsOutResult.Loaded -> {
                    val outcome = result.value
                    val route = routeChange(before, outcome.note)
                    if (route != null) {
                        handoff.hand(outcome.note)
                    }
                    mutableState.update {
                        it.copy(
                            note = outcome.note,
                            working = null,
                            problem = null,
                            shipmentFollowUp = null,
                            event = DeliveryNoteEvent.Acted(
                                message = outcome.message,
                                isNothingChanged = outcome.status == NoteActionStatus.NOTHING_CHANGED,
                                route = route,
                                warning = outcome.warning,
                            ),
                        )
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                else -> {
                    val errors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty()
                    val followUp = shipmentFollowUp(write, errors)
                    mutableState.update {
                        it.copy(
                            working = null,
                            writeErrors = errors,
                            shipmentFollowUp = followUp,
                            event = if (followUp == null) result.refusal() else null,
                        )
                    }
                }
            }
        }
    }

    fun confirmOverrideLock() {
        val followUp = mutableState.value.shipmentFollowUp as? ShipmentFollowUp.ConfirmOverride ?: return
        val address = followUp.address
        act(
            if (address != null) {
                DeliveryNoteWrite.RetryShipmentWithAddress(shipperId = followUp.shipperId, address = address, overrideLock = true)
            } else {
                DeliveryNoteWrite.AddShipment(shipperId = followUp.shipperId, tracking = followUp.tracking, overrideLock = true)
            },
        )
    }

    fun retryShipmentWithAddress(address: DeliveryAddressFields) {
        val followUp = mutableState.value.shipmentFollowUp as? ShipmentFollowUp.FixAddress ?: return
        act(DeliveryNoteWrite.RetryShipmentWithAddress(shipperId = followUp.shipperId, address = address, overrideLock = followUp.overrideLock))
    }

    fun dismissShipmentFollowUp() {
        mutableState.update { it.copy(shipmentFollowUp = null) }
    }

    fun loadShippers() {
        val token = sessions.session.value?.token ?: return
        if (mutableState.value.shippers.isLoading) {
            return
        }
        mutableState.update { it.copy(shippers = it.shippers.copy(isLoading = true, problem = null)) }

        viewModelScope.launch {
            when (val result = api.shippers(token, deliveryNoteId)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(shippers = Choices(items = result.value.shippers), shipperDirective = result.value.directive)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(shippers = it.shippers.copy(isLoading = false), isSessionExpired = true) }
                else -> mutableState.update { it.copy(shippers = it.shippers.copy(isLoading = false, problem = result.problem())) }
            }
        }
    }

    suspend fun downloadDocument(url: String, target: File): AttachmentDownload = download { token -> api.downloadDocument(token, url, target) }

    suspend fun downloadInsertPdf(leafletId: Long, target: File): AttachmentDownload =
        download { token -> api.downloadInsertPdf(token, deliveryNoteId, leafletId, target) }

    private suspend fun download(fetch: suspend (String) -> AttachmentDownload): AttachmentDownload {
        val token = sessions.session.value?.token ?: return AttachmentDownload.SessionExpired
        val result = fetch(token)
        if (result == AttachmentDownload.SessionExpired) {
            mutableState.update { it.copy(isSessionExpired = true) }
        }
        return result
    }

    fun loadTrolleys() {
        val warehouseId = mutableState.value.note?.warehouseId ?: return
        loadChoices(
            current = { it.trolleys },
            store = { state, choices -> state.copy(trolleys = choices) },
            fetch = { token -> api.trolleys(token, warehouseId, availableOnly = true) },
        )
    }

    fun loadPickedBays() {
        val warehouseId = mutableState.value.note?.warehouseId ?: return
        loadChoices(
            current = { it.pickedBays },
            store = { state, choices -> state.copy(pickedBays = choices) },
            fetch = { token -> api.pickedBays(token, warehouseId) },
        )
    }

    fun loadPickers() {
        val warehouseId = mutableState.value.note?.warehouseId ?: return
        loadChoices(
            current = { it.pickers },
            store = { state, choices -> state.copy(pickers = choices) },
            fetch = { token -> api.pickers(token, warehouseId) },
        )
    }

    fun scanToPick(code: String) {
        val barcode = code.trim()
        if (barcode.isEmpty()) {
            return
        }
        enqueueScan(QueuedScan.Pick(ScanPickRequest(barcode = barcode, quantity = 1.0)))
    }

    fun pickAllScanned() {
        val target = mutableState.value.scanTarget ?: return
        enqueueScan(
            QueuedScan.Pick(
                ScanPickRequest(barcode = target.barcode, quantity = null, deliveryNoteItemId = target.lineId, locationOrgStockId = target.locationOrgStockId),
            ),
        )
    }

    fun pickScannedAmount(amount: Int) {
        val target = mutableState.value.scanTarget ?: return
        if (amount <= 0) {
            return
        }
        enqueueScan(
            QueuedScan.Pick(
                ScanPickRequest(barcode = target.barcode, quantity = amount.toDouble(), deliveryNoteItemId = target.lineId, locationOrgStockId = target.locationOrgStockId),
            ),
        )
    }

    fun scanToPack(code: String) {
        val barcode = code.trim()
        if (barcode.isEmpty()) {
            return
        }
        enqueueScan(QueuedScan.Pack(ScanPackRequest(barcode = barcode, quantity = 1.0)))
    }

    fun packAllScanned() {
        val target = mutableState.value.scanTarget ?: return
        enqueueScan(QueuedScan.Pack(ScanPackRequest(barcode = target.barcode, quantity = null, deliveryNoteItemId = target.lineId)))
    }

    fun packScannedAmount(amount: Int) {
        val target = mutableState.value.scanTarget ?: return
        if (amount <= 0) {
            return
        }
        enqueueScan(QueuedScan.Pack(ScanPackRequest(barcode = target.barcode, quantity = amount.toDouble(), deliveryNoteItemId = target.lineId)))
    }

    fun closeScanPanel() {
        mutableState.update { it.copy(scanTarget = null) }
    }

    private fun enqueueScan(request: QueuedScan) {
        pendingScans.addLast(request)
        if (scanJob?.isActive == true) {
            mutableState.update { it.copy(queuedScans = pendingScans.size + 1) }
            return
        }
        mutableState.update { it.copy(queuedScans = pendingScans.size) }
        scanJob = viewModelScope.launch {
            drainScans()
            mutableState.update { it.copy(queuedScans = 0) }
        }
    }

    private suspend fun drainScans() {
        while (pendingScans.isNotEmpty()) {
            val queued = pendingScans.removeFirst()
            mutableState.update { it.copy(queuedScans = pendingScans.size + 1) }
            val token = sessions.session.value?.token ?: return
            val before = mutableState.value.note
            val failure = when (queued) {
                is QueuedScan.Pick -> when (val result = api.scanPick(token, deliveryNoteId, queued.request)) {
                    is GoodsOutResult.Loaded -> {
                        onScanPicked(queued.request, before, result.value.note, result.value.status, result.value.message, result.value.item)
                        null
                    }
                    else -> result
                }
                is QueuedScan.Pack -> when (val result = api.scanPack(token, deliveryNoteId, queued.request)) {
                    is GoodsOutResult.Loaded -> {
                        onScanPacked(queued.request, before, result.value.note, result.value.status, result.value.message, result.value.item)
                        null
                    }
                    else -> result
                }
            }
            if (failure == null) {
                continue
            }
            if (failure == GoodsOutResult.SessionExpired) {
                pendingScans.clear()
                mutableState.update { it.copy(isSessionExpired = true, queuedScans = 0) }
                return
            }
            val refusal = failure.refusal()
            mutableState.update {
                it.copy(
                    scanLog = listOf(ScanLogEntry(code = queued.barcode, at = clock(), status = ScanLogStatus.FAILED, message = refusal.message.ifBlank { null })) + it.scanLog,
                    event = refusal,
                )
            }
        }
    }

    private fun onScanPacked(request: ScanPackRequest, before: DeliveryNote?, note: DeliveryNote, status: String, message: String?, item: ScanPackItem?) {
        val line = item?.let { note.line(it.id) }
        val packedNow = line?.let { (it.quantityPacked - (before?.line(it.id)?.quantityPacked ?: it.quantityPacked)).coerceAtLeast(0.0) } ?: 0.0
        val entry = ScanLogEntry(code = request.barcode, at = clock(), status = status, quantity = packedNow, message = message)
        val keepsTarget = status == ScanPackStatus.NOT_FOUND || status == ScanPackStatus.WRONG_STATE
        val isLineDone = status == ScanPackStatus.PACKED && line != null && line.quantityToPack <= 0.0
        val event = when {
            status != ScanPackStatus.PACKED -> DeliveryNoteEvent.ScanFeedback(message = message)
            isLineDone -> DeliveryNoteEvent.LinePacked(code = line.orgStock?.code ?: item.code ?: request.barcode)
            else -> null
        }

        mutableState.update {
            it.copy(
                note = note,
                problem = null,
                scanTarget = when {
                    keepsTarget -> it.scanTarget
                    item == null || line == null || line.quantityToPack <= 0.0 -> null
                    else -> ScanTarget(lineId = line.id, barcode = request.barcode, locationCode = null, locationOrgStockId = null)
                },
                scanLog = listOf(entry) + it.scanLog,
                event = event ?: it.event,
            )
        }
    }

    private fun onScanPicked(request: ScanPickRequest, before: DeliveryNote?, note: DeliveryNote, status: String, message: String?, item: ScanPickItem?) {
        val line = item?.let { note.line(it.id) }
        val pickedNow = line?.let { (it.quantityPicked - (before?.line(it.id)?.quantityPicked ?: it.quantityPicked)).coerceAtLeast(0.0) } ?: 0.0
        val entry = ScanLogEntry(code = request.barcode, at = clock(), status = status, quantity = pickedNow, message = message)
        val keepsTarget = status == ScanPickStatus.NOT_FOUND || status == ScanPickStatus.WRONG_STATE
        val isLineDone = status == ScanPickStatus.PICKED && line != null && line.quantityToPick <= 0.0
        val event = when {
            status != ScanPickStatus.PICKED -> DeliveryNoteEvent.ScanFeedback(message = message)
            isLineDone -> DeliveryNoteEvent.LineDone(
                code = line.orgStock?.code ?: item.code ?: request.barcode,
                isEverythingPicked = note.lines.none { it.quantityToPick > 0.0 },
            )
            else -> null
        }

        mutableState.update {
            it.copy(
                note = note,
                problem = null,
                scanTarget = when {
                    keepsTarget -> it.scanTarget
                    item == null || line == null || line.quantityToPick <= 0.0 -> null
                    else -> ScanTarget(lineId = line.id, barcode = request.barcode, locationCode = item.locationCode, locationOrgStockId = item.locationOrgStockId)
                },
                scanLog = listOf(entry) + it.scanLog,
                event = event ?: it.event,
            )
        }
    }

    private fun <T> loadChoices(
        current: (DeliveryNoteUiState) -> Choices<T>,
        store: (DeliveryNoteUiState, Choices<T>) -> DeliveryNoteUiState,
        fetch: suspend (String) -> GoodsOutResult<List<T>>,
    ) {
        val token = sessions.session.value?.token ?: return
        if (current(mutableState.value).isLoading) {
            return
        }
        mutableState.update { store(it, current(it).copy(isLoading = true, problem = null)) }

        viewModelScope.launch {
            when (val result = fetch(token)) {
                is GoodsOutResult.Loaded -> mutableState.update { store(it, Choices(items = result.value)) }
                GoodsOutResult.SessionExpired -> mutableState.update { store(it, current(it).copy(isLoading = false)).copy(isSessionExpired = true) }
                else -> mutableState.update { store(it, current(it).copy(isLoading = false, problem = result.problem())) }
            }
        }
    }

    private fun load() {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.deliveryNote(token, deliveryNoteId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(note = result.value, isLoading = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, problem = result.problem()) }
            }
        }
    }
}

internal fun routeChange(before: DeliveryNote, after: DeliveryNote): Any? {
    val previous = deliveryNoteRoute(id = before.id, reference = before.reference, state = before.state)
    val next = deliveryNoteRoute(id = after.id, reference = after.reference, state = after.state)
    return next.takeIf { it::class != previous::class }
}

internal val DeliveryNote.canWorkLines: Boolean
    get() = state == DeliveryNoteBucket.Handling.key && allowActions

internal val DeliveryNote.canScanToPick: Boolean
    get() = canWorkLines && settings.allowScanToPick && !isDropshipping

internal val DeliveryNote.canPackLines: Boolean
    get() = (state == DeliveryNoteBucket.Packing.key || state == DeliveryNoteBucket.Packed.key) && allowActions

internal val DeliveryNote.canScanToPack: Boolean
    get() = state == DeliveryNoteBucket.Packing.key && allowActions && settings.allowScanToPack && !isDropshipping

internal fun shipmentFollowUp(write: DeliveryNoteWrite, errors: Map<String, List<String>>): ShipmentFollowUp? {
    val override = errors[WriteErrorKey.OVERRIDE_LOCK]?.firstOrNull()
    val address = errors[WriteErrorKey.ADDRESS]?.joinToString(" ")
    return when (write) {
        is DeliveryNoteWrite.AddShipment -> when {
            override != null -> ShipmentFollowUp.ConfirmOverride(message = override, shipperId = write.shipperId, tracking = write.tracking)
            address != null -> ShipmentFollowUp.FixAddress(message = address, shipperId = write.shipperId, overrideLock = write.overrideLock)
            else -> null
        }
        is DeliveryNoteWrite.RetryShipmentWithAddress -> when {
            override != null -> ShipmentFollowUp.ConfirmOverride(message = override, shipperId = write.shipperId, tracking = null, address = write.address)
            else -> (address ?: errors.values.flatten().firstOrNull())
                ?.let { ShipmentFollowUp.FixAddress(message = it, shipperId = write.shipperId, overrideLock = write.overrideLock) }
        }
        else -> null
    }
}

private fun GoodsOutResult<*>.refusal(): DeliveryNoteEvent.Refused {
    val message = when (this) {
        is GoodsOutResult.Forbidden -> message
        is GoodsOutResult.NotFound -> message
        is GoodsOutResult.Invalid -> message
        else -> ""
    }
    return DeliveryNoteEvent.Refused(message = message, problem = problem())
}
