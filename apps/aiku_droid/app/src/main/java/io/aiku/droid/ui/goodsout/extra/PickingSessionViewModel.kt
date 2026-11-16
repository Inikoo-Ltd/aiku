package io.aiku.droid.ui.goodsout.extra

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.NoteActionStatus
import io.aiku.droid.data.api.PickListEntry
import io.aiku.droid.data.api.PickingSession
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.api.PickingSessionWrite
import io.aiku.droid.data.api.ScanPackRequest
import io.aiku.droid.data.api.ScanPackStatus
import io.aiku.droid.data.api.ScanPickRequest
import io.aiku.droid.data.api.ScanPickStatus
import io.aiku.droid.data.api.SessionScanPackOutcome
import io.aiku.droid.data.api.SessionScanPickOutcome
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.Choices
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.ScanLogEntry
import io.aiku.droid.ui.goodsout.ScanLogStatus
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Instant

data class SessionScanTarget(
    val lineId: Long,
    val barcode: String,
    val code: String,
    val name: String,
    val deliveryNoteReference: String?,
    val locationCode: String?,
    val locationOrgStockId: Long?,
    val done: Double,
    val remaining: Double,
    val remainingLabel: String?,
)

sealed interface SessionEvent {
    data class Acted(val message: String?, val isNothingChanged: Boolean) : SessionEvent
    data class Refused(val message: String, val problem: GoodsOutProblem) : SessionEvent
    data class ScanFeedback(val message: String?) : SessionEvent
    data class LineDone(val code: String) : SessionEvent
    data class LocationDone(val location: String) : SessionEvent
    data object AllPicked : SessionEvent
    data object NowWaiting : SessionEvent
    data object AllPacked : SessionEvent
}

data class PickingSessionUiState(
    val session: PickingSession? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: PickingSessionWrite? = null,
    val event: SessionEvent? = null,
    val trolleys: Choices<Trolley> = Choices(),
    val currentPickKey: String? = null,
    val scanTarget: SessionScanTarget? = null,
    val scanLog: List<ScanLogEntry> = emptyList(),
    val queuedScans: Int = 0,
) {
    val isWorking: Boolean get() = working != null

    val currentPick: PickListEntry? get() = session?.pick(currentPickKey)

    val nextPick: PickListEntry? get() = session?.nextOpenPick(after = currentPickKey)
}

class PickingSessionHandoff {
    private var session: PickingSession? = null

    fun hand(session: PickingSession) {
        this.session = session
    }

    fun take(sessionId: Long): PickingSession? {
        val handed = session?.takeIf { it.id == sessionId }
        session = null
        return handed
    }
}

private sealed interface QueuedSessionScan {
    val barcode: String

    data class Pick(val request: ScanPickRequest) : QueuedSessionScan {
        override val barcode: String get() = request.barcode
    }

    data class Pack(val request: ScanPackRequest) : QueuedSessionScan {
        override val barcode: String get() = request.barcode
    }
}

internal fun PickingSession.nextOpenPick(after: String?): PickListEntry? {
    val index = pickList.indexOfFirst { it.key == after }
    return (pickList.drop(index + 1) + pickList.take(index + 1)).firstOrNull { !it.isDone && it.key != after }
}

internal fun PickingSession.openPickKey(current: String?): String? =
    pick(current)?.takeIf { !it.isDone }?.key ?: nextOpenPick(after = current)?.key

internal fun PickingSession.pickOf(deliveryNoteItemId: Long): PickListEntry? =
    pickList.firstOrNull { entry -> entry.lines.any { it.deliveryNoteItemId == deliveryNoteItemId } }

internal val PickingSession.isPickingOver: Boolean
    get() = sessionState == PickingSessionState.PickingFinished || sessionState == PickingSessionState.PackingFinished

class PickingSessionViewModel(
    private val api: PickingSessionApi,
    private val goodsOut: GoodsOutApi,
    private val sessions: SessionRepository,
    private val sessionId: Long,
    private val warehouseId: Long,
    private val handoff: PickingSessionHandoff = PickingSessionHandoff(),
    private val clock: () -> Instant = Instant::now,
) : ViewModel() {

    private val mutableState = MutableStateFlow(
        handoff.take(sessionId).let { handed -> PickingSessionUiState(session = handed, currentPickKey = handed?.openPickKey(null)) },
    )
    val state: StateFlow<PickingSessionUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null
    private val pendingScans = ArrayDeque<QueuedSessionScan>()
    private var scanJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isWorking && mutableState.value.queuedScans == 0) {
            load()
        }
    }

    fun retry() = load()

    fun signInAgain() {
        sessions.end()
    }

    fun handOver() {
        mutableState.value.session?.let(handoff::hand)
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun act(write: PickingSessionWrite) {
        val token = sessions.session.value?.token ?: return
        if (mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false) }

        viewModelScope.launch {
            when (val result = api.writeSession(token, sessionId, write)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.withSession(result.value.session).copy(
                        working = null,
                        problem = null,
                        event = SessionEvent.Acted(message = result.value.message, isNothingChanged = result.value.status == NoteActionStatus.NOTHING_CHANGED),
                    )
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                else -> mutableState.update { it.copy(working = null, event = result.refusal()) }
            }
        }
    }

    fun loadTrolleys() {
        val token = sessions.session.value?.token ?: return
        if (mutableState.value.trolleys.isLoading) {
            return
        }
        mutableState.update { it.copy(trolleys = it.trolleys.copy(isLoading = true, problem = null)) }

        viewModelScope.launch {
            when (val result = goodsOut.trolleys(token, warehouseId, availableOnly = true)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(trolleys = Choices(items = result.value)) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(trolleys = it.trolleys.copy(isLoading = false), isSessionExpired = true) }
                else -> mutableState.update { it.copy(trolleys = it.trolleys.copy(isLoading = false, problem = result.problem())) }
            }
        }
    }

    fun nextLocation() {
        val session = mutableState.value.session ?: return
        val next = session.nextOpenPick(after = mutableState.value.currentPickKey) ?: return
        mutableState.update { it.copy(currentPickKey = next.key, scanTarget = null) }
    }

    fun scanToPick(code: String) {
        val barcode = code.trim()
        if (barcode.isNotEmpty()) {
            enqueue(QueuedSessionScan.Pick(ScanPickRequest(barcode = barcode, quantity = 1.0)))
        }
    }

    fun pickAllScanned() {
        val target = mutableState.value.scanTarget ?: return
        enqueue(QueuedSessionScan.Pick(ScanPickRequest(barcode = target.barcode, quantity = null, deliveryNoteItemId = target.lineId, locationOrgStockId = target.locationOrgStockId)))
    }

    fun pickScannedAmount(amount: Int) {
        val target = mutableState.value.scanTarget ?: return
        if (amount > 0) {
            enqueue(
                QueuedSessionScan.Pick(
                    ScanPickRequest(barcode = target.barcode, quantity = amount.toDouble(), deliveryNoteItemId = target.lineId, locationOrgStockId = target.locationOrgStockId),
                ),
            )
        }
    }

    fun scanToPack(code: String) {
        val barcode = code.trim()
        if (barcode.isNotEmpty()) {
            enqueue(QueuedSessionScan.Pack(ScanPackRequest(barcode = barcode, quantity = 1.0)))
        }
    }

    fun packAllScanned() {
        val target = mutableState.value.scanTarget ?: return
        enqueue(QueuedSessionScan.Pack(ScanPackRequest(barcode = target.barcode, quantity = null, deliveryNoteItemId = target.lineId)))
    }

    fun packScannedAmount(amount: Int) {
        val target = mutableState.value.scanTarget ?: return
        if (amount > 0) {
            enqueue(QueuedSessionScan.Pack(ScanPackRequest(barcode = target.barcode, quantity = amount.toDouble(), deliveryNoteItemId = target.lineId)))
        }
    }

    fun closeScanPanel() {
        mutableState.update { it.copy(scanTarget = null) }
    }

    private fun enqueue(scan: QueuedSessionScan) {
        pendingScans.addLast(scan)
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
            val failure = when (queued) {
                is QueuedSessionScan.Pick -> when (val result = api.sessionScanPick(token, sessionId, queued.request)) {
                    is GoodsOutResult.Loaded -> null.also { onPicked(queued.request, result.value) }
                    else -> result
                }
                is QueuedSessionScan.Pack -> when (val result = api.sessionScanPack(token, sessionId, queued.request)) {
                    is GoodsOutResult.Loaded -> null.also { onPacked(queued.request, result.value) }
                    else -> result
                }
            } ?: continue
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

    private fun onPicked(request: ScanPickRequest, outcome: SessionScanPickOutcome) {
        val before = mutableState.value
        val session = outcome.session
        val item = outcome.item
        val pickedBefore = item?.let { before.session?.pickLine(it.id)?.quantityPicked }
        val pickedNow = item?.let { picked -> (picked.quantityPicked - (pickedBefore ?: picked.quantityPicked)).coerceAtLeast(0.0) } ?: 0.0
        val isPicked = outcome.status == ScanPickStatus.PICKED
        val keepsTarget = outcome.status == ScanPickStatus.NOT_FOUND || outcome.status == ScanPickStatus.WRONG_STATE
        val scannedPick = item?.let { session.pickOf(it.id) }
        val previousPick = before.currentPick
        val focusedKey = scannedPick?.takeIf { !it.isDone }?.key ?: before.currentPickKey
        val nextKey = session.openPickKey(focusedKey)
        val finishedLocation = previousPick?.takeIf { isPicked && session.pick(it.key)?.isDone == true }
        val event = when {
            !isPicked -> SessionEvent.ScanFeedback(message = outcome.message)
            session.sessionState == PickingSessionState.HandlingBlocked -> SessionEvent.NowWaiting
            nextKey == null || session.isPickingOver -> SessionEvent.AllPicked
            finishedLocation != null -> SessionEvent.LocationDone(location = finishedLocation.location?.code.orEmpty())
            item != null && item.quantityToPick <= 0.0 -> SessionEvent.LineDone(code = item.code ?: request.barcode)
            else -> null
        }

        mutableState.update {
            it.copy(
                session = session,
                problem = null,
                currentPickKey = nextKey,
                scanTarget = when {
                    keepsTarget -> it.scanTarget
                    item == null || item.quantityToPick <= 0.0 -> null
                    else -> SessionScanTarget(
                        lineId = item.id,
                        barcode = request.barcode,
                        code = item.code ?: request.barcode,
                        name = item.name.orEmpty(),
                        deliveryNoteReference = outcome.deliveryNote?.reference,
                        locationCode = item.locationCode,
                        locationOrgStockId = item.locationOrgStockId,
                        done = item.quantityPicked,
                        remaining = item.quantityToPick,
                        remainingLabel = item.quantityToPickLabel,
                    )
                },
                scanLog = listOf(ScanLogEntry(code = request.barcode, at = clock(), status = outcome.status, quantity = pickedNow, message = outcome.message)) + it.scanLog,
                event = event ?: it.event,
            )
        }
    }

    private fun onPacked(request: ScanPackRequest, outcome: SessionScanPackOutcome) {
        val item = outcome.item
        val packedNow = request.quantity ?: mutableState.value.scanTarget?.takeIf { it.lineId == item?.id }?.remaining ?: 0.0
        val session = outcome.session
        val isPacked = outcome.status == ScanPackStatus.PACKED
        val keepsTarget = outcome.status == ScanPackStatus.NOT_FOUND || outcome.status == ScanPackStatus.WRONG_STATE
        val event = when {
            !isPacked -> SessionEvent.ScanFeedback(message = outcome.message)
            session.sessionState == PickingSessionState.PackingFinished -> SessionEvent.AllPacked
            item != null && item.quantityToPack <= 0.0 -> SessionEvent.LineDone(code = item.code ?: request.barcode)
            else -> null
        }

        mutableState.update {
            it.copy(
                session = session,
                problem = null,
                scanTarget = when {
                    keepsTarget -> it.scanTarget
                    item == null || item.quantityToPack <= 0.0 -> null
                    else -> SessionScanTarget(
                        lineId = item.id,
                        barcode = request.barcode,
                        code = item.code ?: request.barcode,
                        name = item.name.orEmpty(),
                        deliveryNoteReference = outcome.deliveryNote?.reference,
                        locationCode = null,
                        locationOrgStockId = null,
                        done = item.quantityPacked,
                        remaining = item.quantityToPack,
                        remainingLabel = item.quantityToPackLabel,
                    )
                },
                scanLog = listOf(ScanLogEntry(code = request.barcode, at = clock(), status = outcome.status, quantity = if (isPacked) packedNow else 0.0, message = outcome.message)) + it.scanLog,
                event = event ?: it.event,
            )
        }
    }

    private fun load() {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.pickingSession(token, sessionId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.withSession(result.value).copy(isLoading = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, problem = result.problem()) }
            }
        }
    }
}

private fun PickingSessionUiState.withSession(session: PickingSession): PickingSessionUiState =
    copy(session = session, currentPickKey = session.openPickKey(currentPickKey))

private fun GoodsOutResult<*>.refusal(): SessionEvent.Refused {
    val message = when (this) {
        is GoodsOutResult.Forbidden -> message
        is GoodsOutResult.NotFound -> message
        is GoodsOutResult.Invalid -> message
        else -> ""
    }
    return SessionEvent.Refused(message = message, problem = problem())
}
