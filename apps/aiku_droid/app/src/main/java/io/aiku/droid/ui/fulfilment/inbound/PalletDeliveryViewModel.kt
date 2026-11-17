package io.aiku.droid.ui.fulfilment.inbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentInboundApi
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryWrite
import io.aiku.droid.data.api.PalletLocationTarget
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsin.GoodsInEvent
import io.aiku.droid.ui.goodsin.goodsInRefusal
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class BookInScanMiss(val code: String)

data class PalletDeliveryUiState(
    val delivery: PalletDelivery? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: PalletDeliveryWrite? = null,
    val event: GoodsInEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val openPalletId: Long? = null,
    val scanMiss: BookInScanMiss? = null,
) {
    val isWorking: Boolean get() = working != null

    val openPallet: Pallet? get() = openPalletId?.let { delivery?.pallet(it) }
}

private val PalletDeliveryWrite.writtenPalletId: Long?
    get() = when (this) {
        is PalletDeliveryWrite.BookIn -> palletId
        is PalletDeliveryWrite.NotReceived -> palletId
        is PalletDeliveryWrite.UndoNotReceived -> palletId
        else -> null
    }

class PalletDeliveryViewModel(
    private val api: FulfilmentInboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val deliveryId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(PalletDeliveryUiState())
    val state: StateFlow<PalletDeliveryUiState> = mutableState.asStateFlow()

    val finder = FulfilmentLocationFinder(
        api = api,
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

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun scanMissHandled() {
        mutableState.update { it.copy(scanMiss = null) }
    }

    fun openPallet(palletId: Long) {
        finder.reset()
        mutableState.update { it.copy(openPalletId = palletId, writeErrors = emptyMap()) }
    }

    fun closePallet() {
        finder.reset()
        mutableState.update { it.copy(openPalletId = null, writeErrors = emptyMap()) }
    }

    fun receive() = act(PalletDeliveryWrite.Receive)

    fun startBooking() = act(PalletDeliveryWrite.StartBooking)

    fun finishBooking() = act(PalletDeliveryWrite.FinishBooking)

    fun bookIn(palletId: Long, target: PalletLocationTarget) = act(PalletDeliveryWrite.BookIn(palletId = palletId, target = target))

    fun notReceived(palletId: Long) = act(PalletDeliveryWrite.NotReceived(palletId))

    fun undoNotReceived(palletId: Long) = act(PalletDeliveryWrite.UndoNotReceived(palletId))

    fun scanForBookIn(code: String) {
        val scanned = code.trim()
        val current = mutableState.value
        val delivery = current.delivery ?: return
        if (scanned.isEmpty() || current.isWorking) {
            return
        }
        val labelled = delivery.palletLabelled(scanned)
        val open = current.openPallet
        when {
            labelled != null -> openPallet(labelled.id)
            open != null && open.can(FulfilmentInboundActionKey.BOOK_IN) -> bookIn(open.id, PalletLocationTarget.Scanned(scanned))
            else -> mutableState.update { it.copy(scanMiss = BookInScanMiss(scanned)) }
        }
    }

    private fun act(write: PalletDeliveryWrite) {
        val token = token() ?: return
        if (mutableState.value.delivery == null || mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false, writeErrors = emptyMap()) }

        viewModelScope.launch {
            when (val result = api.writePalletDelivery(token, warehouseId, deliveryId, write)) {
                is GoodsOutResult.Loaded -> {
                    val outcome = result.value
                    val closesPallet = write.writtenPalletId != null
                    if (closesPallet) {
                        finder.reset()
                    }
                    mutableState.update {
                        it.copy(
                            delivery = outcome.delivery,
                            working = null,
                            problem = null,
                            openPalletId = if (closesPallet) null else it.openPalletId,
                            event = GoodsInEvent.Done(
                                action = write,
                                message = outcome.message,
                                isNothingChanged = outcome.status == GoodsInWriteStatus.NOTHING_CHANGED,
                            ),
                        )
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                else -> mutableState.update {
                    it.copy(
                        working = null,
                        writeErrors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty(),
                        event = result.goodsInRefusal(),
                    )
                }
            }
        }
    }

    private fun load() {
        val token = token() ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.palletDelivery(token, warehouseId, deliveryId)) {
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
