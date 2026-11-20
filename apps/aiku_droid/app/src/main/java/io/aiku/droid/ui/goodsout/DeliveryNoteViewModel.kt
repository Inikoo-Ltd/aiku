package io.aiku.droid.ui.goodsout

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryAddressFields
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResponses
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.NoteActionOutcome
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
import io.aiku.droid.data.api.clientRequestId
import io.aiku.droid.data.api.isOfflineLineWork
import io.aiku.droid.data.api.waitsForPlatform
import io.aiku.droid.data.api.withClientRequestId
import io.aiku.droid.data.offline.NoOfflineWork
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
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
    val isQueued: Boolean = false,
)

object ScanLogStatus {
    const val FAILED = "failed"
    const val ONLINE_ONLY = "online_only"
}

sealed interface DeliveryNoteEvent {
    data class Acted(val message: String?, val isNothingChanged: Boolean, val route: Any?, val warning: String? = null) : DeliveryNoteEvent
    data class Refused(val message: String, val problem: GoodsOutProblem) : DeliveryNoteEvent
    data class ScanFeedback(val message: String?) : DeliveryNoteEvent
    data class LineDone(val code: String, val isEverythingPicked: Boolean) : DeliveryNoteEvent
    data class LinePacked(val code: String) : DeliveryNoteEvent
    data object Queued : DeliveryNoteEvent
    data class NeedsConnection(val reference: String) : DeliveryNoteEvent
    data class QueuedWorkLeft(val needsYou: Int, val waiting: Int) : DeliveryNoteEvent
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
    val isRefreshing: Boolean = false,
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
    val isOnline: Boolean = true,
    val isQueueing: Boolean = false,
    val offlineCopy: OfflineCopy? = null,
    val queued: List<QueuedLineWork> = emptyList(),
) {
    val isWorking: Boolean get() = working != null

    fun queuedFor(lineId: Long): List<QueuedLineWork> = queued.filter { it.lineId == lineId }

    val canRefresh: Boolean get() = !isWorking && queuedScans == 0
}

data class HandedNote(val note: DeliveryNote, val copy: OfflineCopy?)

class DeliveryNoteHandoff {
    private var handed: HandedNote? = null

    fun hand(note: DeliveryNote, copy: OfflineCopy? = null) {
        handed = HandedNote(note, copy)
    }

    fun take(deliveryNoteId: Long): DeliveryNote? = takeHanded(deliveryNoteId)?.note

    fun takeHanded(deliveryNoteId: Long): HandedNote? {
        val taken = handed?.takeIf { it.note.id == deliveryNoteId }
        handed = null
        return taken
    }
}

private val openQueueStates = setOf(QueuedActionState.WAITING, QueuedActionState.SENDING, QueuedActionState.NEEDS_YOU)

internal val DeliveryNoteWrite.changesStatus: Boolean
    get() = when (this) {
        is DeliveryNoteWrite.StartPicking,
        DeliveryNoteWrite.TakeOver,
        is DeliveryNoteWrite.SetAsPicked,
        DeliveryNoteWrite.SetAsPacked,
        DeliveryNoteWrite.PutBackIncompleteSets,
        DeliveryNoteWrite.AutoFinishWaiting,
        DeliveryNoteWrite.BackToPicking,
        DeliveryNoteWrite.UndoSetAsPicked,
        is DeliveryNoteWrite.SetPicker,
        DeliveryNoteWrite.StartPacking,
        DeliveryNoteWrite.UndoPacking,
        DeliveryNoteWrite.Unpack,
        DeliveryNoteWrite.FinaliseAndDispatch,
        DeliveryNoteWrite.Finalise,
        DeliveryNoteWrite.Dispatch,
        is DeliveryNoteWrite.Cancel,
        is DeliveryNoteWrite.SetPacker,
        -> true
        else -> false
    }

class DeliveryNoteViewModel(
    private val api: GoodsOutApi,
    private val sessions: SessionRepository,
    private val deliveryNoteId: Long,
    private val handoff: DeliveryNoteHandoff = DeliveryNoteHandoff(),
    private val clock: () -> Instant = Instant::now,
    private val offline: OfflineWork = NoOfflineWork,
) : ViewModel() {

    private val record = deliveryNoteRecord(deliveryNoteId)
    private val handed = handoff.takeHanded(deliveryNoteId)
    private var base: DeliveryNote? = handed?.note
    private var copySeenAt: Instant? = handed?.copy?.seenAt
    private var baseAt: Instant? = handed?.let { it.copy?.seenAt ?: clock() }
    private var pending: List<QueuedAction> = offline.pendingFor(record).value
    private val flushedIds = mutableSetOf<Long>()

    private val mutableState = MutableStateFlow(
        DeliveryNoteUiState(isOnline = offline.isOnline.value, offlineCopy = handed?.copy).withOverlay(),
    )
    val state: StateFlow<DeliveryNoteUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null
    private val pendingScans = ArrayDeque<QueuedScan>()
    private var scanJob: Job? = null

    init {
        viewModelScope.launch {
            offline.pendingFor(record).collect { actions ->
                val settled = pending.any { earlier -> earlier.localId !in flushedIds && actions.none { it.localId == earlier.localId } }
                pending = actions
                mutableState.update { it.withOverlay() }
                if (settled && offline.isOnline.value && loadJob?.isActive != true && !mutableState.value.isWorking) {
                    load()
                }
            }
        }
        viewModelScope.launch {
            offline.isOnline.collect { isOnline ->
                val cameBack = isOnline && !mutableState.value.isOnline
                mutableState.update { it.copy(isOnline = isOnline).withOverlay() }
                if (cameBack && mutableState.value.offlineCopy != null && !mutableState.value.isWorking) {
                    load()
                }
            }
        }
    }

    private fun DeliveryNoteUiState.withOverlay(): DeliveryNoteUiState = copy(
        note = base?.withQueued(pending, copySeenAt),
        queued = queuedLineWork(pending),
        isQueueing = offline.shouldQueue(record),
    )

    private fun showLive(note: DeliveryNote) {
        base = note
        baseAt = clock()
        copySeenAt = null
    }

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isWorking) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() {
        if (mutableState.value.canRefresh) {
            load(isPull = true)
        }
    }

    fun signInAgain() {
        sessions.end()
    }

    fun handOver() {
        base?.let { handoff.hand(it, mutableState.value.offlineCopy) }
    }

    fun undoQueued(localId: Long) {
        if (pending.any { it.localId == localId && it.state == QueuedActionState.WAITING }) {
            offline.undo(localId)
        }
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
        if (write.isOfflineLineWork) {
            actOnLine(token, before, write.withClientRequestId(write.clientRequestId ?: offline.newClientRequestId()))
            return
        }
        if (!offline.isOnline.value) {
            mutableState.update { it.copy(event = DeliveryNoteEvent.NeedsConnection(before.reference)) }
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false, isRefreshing = false, writeErrors = emptyMap()) }

        viewModelScope.launch {
            if (write.changesStatus && !flushQueuedWork()) {
                return@launch
            }
            handleWrite(before, write, api.write(token, deliveryNoteId, write))
        }
    }

    private fun actOnLine(token: String, before: DeliveryNote, write: DeliveryNoteWrite) {
        if (offline.shouldQueue(record)) {
            viewModelScope.launch { queueLine(before, write, isFallback = false) }
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false, isRefreshing = false, writeErrors = emptyMap()) }

        viewModelScope.launch {
            val result = api.write(token, deliveryNoteId, write)
            if (result == GoodsOutResult.Unreachable && queueLine(before, write, isFallback = true)) {
                mutableState.update { it.copy(working = null) }
                return@launch
            }
            handleWrite(before, write, result)
        }
    }

    private suspend fun flushQueuedWork(): Boolean {
        if (pending.none { it.state in openQueueStates }) {
            return true
        }
        flushedIds += pending.map { it.localId }
        if (offline.flush(record)) {
            return true
        }
        val left = offline.pendingFor(record).value
        mutableState.update {
            it.copy(
                working = null,
                event = DeliveryNoteEvent.QueuedWorkLeft(
                    needsYou = left.count { action -> action.state == QueuedActionState.NEEDS_YOU },
                    waiting = left.count { action -> action.state == QueuedActionState.WAITING || action.state == QueuedActionState.SENDING },
                ),
            )
        }
        return false
    }

    private suspend fun queueLine(note: DeliveryNote, write: DeliveryNoteWrite, isFallback: Boolean): Boolean {
        val clientRequestId = write.clientRequestId ?: offline.newClientRequestId()
        val event = when (val plan = offlinePlan(note, write, clientRequestId, pending)) {
            is OfflineLinePlan.Queue -> {
                plan.replaces?.let(offline::undo)
                if (!enqueue(note, plan.operation, plan.payload, clientRequestId)) {
                    return false
                }
                DeliveryNoteEvent.Queued
            }
            is OfflineLinePlan.Undo -> {
                offline.undo(plan.localId)
                DeliveryNoteEvent.Queued
            }
            OfflineLinePlan.NothingToDo -> if (isFallback) return false else DeliveryNoteEvent.Acted(message = null, isNothingChanged = true, route = null)
            OfflineLinePlan.OnlineOnly -> if (isFallback) return false else DeliveryNoteEvent.NeedsConnection(note.reference)
        }
        mutableState.update { it.copy(event = event) }
        return true
    }

    private suspend fun enqueue(note: DeliveryNote, operation: OfflineOperation, payload: JsonObject, clientRequestId: String): Boolean {
        val warehouseId = note.warehouseId ?: return false
        val seenAt = offline.downloaded(record)?.seenAt ?: copySeenAt
        return runCatching { offline.enqueue(warehouseId, operation, record, payload, clientRequestId, seenAt) }.isSuccess
    }

    private fun handleWrite(before: DeliveryNote, write: DeliveryNoteWrite, result: GoodsOutResult<NoteActionOutcome>) {
        when (result) {
            is GoodsOutResult.Loaded -> {
                val outcome = result.value
                val route = routeChange(before, outcome.note)
                if (route != null) {
                    handoff.hand(outcome.note)
                }
                showLive(outcome.note)
                mutableState.update {
                    it.copy(
                        offlineCopy = null,
                        working = null,
                        problem = null,
                        shipmentFollowUp = null,
                        event = DeliveryNoteEvent.Acted(
                            message = outcome.message,
                            isNothingChanged = outcome.status == NoteActionStatus.NOTHING_CHANGED,
                            route = route,
                            warning = outcome.warning,
                        ),
                    ).withOverlay()
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
                if (result is GoodsOutResult.Unreachable && write.waitsForPlatform) {
                    load()
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
        if (offline.shouldQueue(record)) {
            scanOffline(barcode = barcode, isPacking = false, quantity = 1.0, target = null)
            return
        }
        enqueueScan(QueuedScan.Pick(ScanPickRequest(barcode = barcode, quantity = 1.0)))
    }

    fun pickAllScanned() {
        val target = mutableState.value.scanTarget ?: return
        if (offline.shouldQueue(record)) {
            scanOffline(barcode = target.barcode, isPacking = false, quantity = null, target = target)
            return
        }
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
        if (offline.shouldQueue(record)) {
            scanOffline(barcode = target.barcode, isPacking = false, quantity = amount.toDouble(), target = target)
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
        if (offline.shouldQueue(record)) {
            scanOffline(barcode = barcode, isPacking = true, quantity = 1.0, target = null)
            return
        }
        enqueueScan(QueuedScan.Pack(ScanPackRequest(barcode = barcode, quantity = 1.0)))
    }

    fun packAllScanned() {
        val target = mutableState.value.scanTarget ?: return
        if (offline.shouldQueue(record)) {
            scanOffline(barcode = target.barcode, isPacking = true, quantity = null, target = target)
            return
        }
        enqueueScan(QueuedScan.Pack(ScanPackRequest(barcode = target.barcode, quantity = null, deliveryNoteItemId = target.lineId)))
    }

    fun packScannedAmount(amount: Int) {
        val target = mutableState.value.scanTarget ?: return
        if (amount <= 0) {
            return
        }
        if (offline.shouldQueue(record)) {
            scanOffline(barcode = target.barcode, isPacking = true, quantity = amount.toDouble(), target = target)
            return
        }
        enqueueScan(QueuedScan.Pack(ScanPackRequest(barcode = target.barcode, quantity = amount.toDouble(), deliveryNoteItemId = target.lineId)))
    }

    private fun scanOffline(barcode: String, isPacking: Boolean, quantity: Double?, target: ScanTarget?) {
        val note = mutableState.value.note ?: return
        val matching = if (target != null) listOfNotNull(note.line(target.lineId)) else note.lines.filter { it.matchesScan(barcode) }
        val workable = matching.filterNot { it.inPickingSession }
        val left: (DeliveryNoteLine) -> Double = if (isPacking) ({ it.quantityToPack }) else ({ it.quantityToPick })
        val line = workable.firstOrNull { left(it) > 0.0 }
        val missStatus = when {
            matching.isEmpty() -> ScanPickStatus.NOT_FOUND
            workable.isEmpty() -> ScanLogStatus.ONLINE_ONLY
            line == null -> if (isPacking) ScanPackStatus.ALREADY_PACKED else ScanPickStatus.ALREADY_PICKED
            else -> null
        }
        if (line == null || missStatus != null) {
            logOfflineScanMiss(barcode, missStatus ?: ScanPickStatus.NOT_FOUND)
            return
        }
        val remaining = left(line)
        val amount = offlineQuantity(minOf(quantity ?: remaining, remaining))
        val locationOrgStockId = if (isPacking) null else note.pickLocation(line, target?.locationOrgStockId)
        if (!isPacking && locationOrgStockId == null) {
            logOfflineScanMiss(barcode, ScanPickStatus.NO_STOCK)
            return
        }
        val clientRequestId = offline.newClientRequestId()
        val operation = if (isPacking) OfflineOperation.PACK else OfflineOperation.PICK
        val payload = if (locationOrgStockId == null) {
            GoodsOutOfflinePayload.pack(note.id, line.id, amount, clientRequestId)
        } else {
            GoodsOutOfflinePayload.pick(note.id, line.id, locationOrgStockId, amount, clientRequestId)
        }

        viewModelScope.launch {
            if (!enqueue(note, operation, payload, clientRequestId)) {
                logOfflineScanMiss(barcode, ScanLogStatus.FAILED)
                return@launch
            }
            val isLineDone = remaining - amount <= 0.0
            val code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: barcode
            val event = when {
                !isLineDone -> null
                isPacking -> DeliveryNoteEvent.LinePacked(code = code)
                else -> DeliveryNoteEvent.LineDone(code = code, isEverythingPicked = note.lines.none { it.id != line.id && it.quantityToPick > 0.0 })
            }
            val entry = ScanLogEntry(
                code = barcode,
                at = clock(),
                status = if (isPacking) ScanPackStatus.PACKED else ScanPickStatus.PICKED,
                quantity = amount,
                isQueued = true,
            )
            mutableState.update {
                it.copy(
                    scanTarget = if (isLineDone) null else ScanTarget(
                        lineId = line.id,
                        barcode = barcode,
                        locationCode = line.locations.firstOrNull { location -> location.locationOrgStockId == locationOrgStockId }?.code,
                        locationOrgStockId = locationOrgStockId,
                    ),
                    scanLog = listOf(entry) + it.scanLog,
                    event = event ?: it.event,
                )
            }
        }
    }

    private fun logOfflineScanMiss(barcode: String, status: String) {
        mutableState.update {
            it.copy(
                scanLog = listOf(ScanLogEntry(code = barcode, at = clock(), status = status)) + it.scanLog,
                event = DeliveryNoteEvent.ScanFeedback(message = null),
            )
        }
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

        showLive(note)
        mutableState.update {
            it.copy(
                offlineCopy = null,
                problem = null,
                scanTarget = when {
                    keepsTarget -> it.scanTarget
                    item == null || line == null || line.quantityToPack <= 0.0 -> null
                    else -> ScanTarget(lineId = line.id, barcode = request.barcode, locationCode = null, locationOrgStockId = null)
                },
                scanLog = listOf(entry) + it.scanLog,
                event = event ?: it.event,
            ).withOverlay()
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

        showLive(note)
        mutableState.update {
            it.copy(
                offlineCopy = null,
                problem = null,
                scanTarget = when {
                    keepsTarget -> it.scanTarget
                    item == null || line == null || line.quantityToPick <= 0.0 -> null
                    else -> ScanTarget(lineId = line.id, barcode = request.barcode, locationCode = item.locationCode, locationOrgStockId = item.locationOrgStockId)
                },
                scanLog = listOf(entry) + it.scanLog,
                event = event ?: it.event,
            ).withOverlay()
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

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            if (!offline.isOnline.value && (showDownloaded() || base != null)) {
                mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = null) }
                return@launch
            }
            when (val result = api.deliveryNote(token, deliveryNoteId)) {
                is GoodsOutResult.Loaded -> {
                    showLive(result.value)
                    mutableState.update { it.copy(isLoading = false, isRefreshing = false, offlineCopy = null).withOverlay() }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                GoodsOutResult.Unreachable -> if (!showDownloaded()) {
                    mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
                }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }

    private suspend fun showDownloaded(): Boolean {
        val downloaded = offline.downloaded(record) ?: return false
        val shownAt = baseAt
        if (base != null && shownAt != null && !downloaded.seenAt.isAfter(shownAt)) {
            return false
        }
        val note = (GoodsOutResponses.parseDeliveryNote(HTTP_OK, downloaded.body) as? GoodsOutResult.Loaded)?.value ?: return false
        base = note
        baseAt = downloaded.seenAt
        copySeenAt = downloaded.seenAt
        mutableState.update {
            it.copy(
                isLoading = false,
                isRefreshing = false,
                problem = null,
                offlineCopy = OfflineCopy(seenAt = downloaded.seenAt, downloadedAt = downloaded.downloadedAt),
            ).withOverlay()
        }
        return true
    }
}

private const val HTTP_OK = 200

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
