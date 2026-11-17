package io.aiku.droid.ui.fulfilment.inbound

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentInboundApi
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletAuditStart
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.data.api.PalletLocationTarget
import io.aiku.droid.data.api.PalletWrite
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

data object StartAudit

data class PalletUiState(
    val pallet: Pallet? = null,
    val isLoading: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val working: Any? = null,
    val event: GoodsInEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
    val startedAudit: PalletAuditStart? = null,
) {
    val isWorking: Boolean get() = working != null
}

fun Pallet.incidentStates(): List<PalletIncidentState> = listOfNotNull(
    PalletIncidentState.Damaged.takeIf { can(FulfilmentInboundActionKey.DAMAGED) },
    PalletIncidentState.Lost.takeIf { can(FulfilmentInboundActionKey.LOST) },
)

class PalletViewModel(
    private val api: FulfilmentInboundApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val palletId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(PalletUiState())
    val state: StateFlow<PalletUiState> = mutableState.asStateFlow()

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

    fun clearWriteErrors() {
        finder.reset()
        mutableState.update { it.copy(writeErrors = emptyMap()) }
    }

    fun move(target: PalletLocationTarget) = act(PalletWrite.Move(target))

    fun reportIncident(state: PalletIncidentState, message: String?) =
        act(PalletWrite.Incident(state = state, message = message?.trim()?.takeIf { it.isNotEmpty() }))

    fun startAudit() {
        val token = token() ?: return
        if (mutableState.value.pallet == null || mutableState.value.isWorking) {
            return
        }
        mutableState.update { it.copy(working = StartAudit, event = null, startedAudit = null) }

        viewModelScope.launch {
            when (val result = api.startPalletAudit(token, warehouseId, palletId)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(
                        working = null,
                        startedAudit = result.value,
                        event = GoodsInEvent.Done(action = StartAudit, message = result.value.message, isNothingChanged = false),
                    )
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(working = null, isSessionExpired = true) }
                else -> mutableState.update { it.copy(working = null, event = result.goodsInRefusal()) }
            }
        }
    }

    fun auditOpened() {
        mutableState.update { it.copy(startedAudit = null) }
    }

    private fun act(write: PalletWrite) {
        val token = token() ?: return
        if (mutableState.value.pallet == null || mutableState.value.isWorking) {
            return
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = write, event = null, isLoading = false, writeErrors = emptyMap()) }

        viewModelScope.launch {
            when (val result = api.writePallet(token, warehouseId, palletId, write)) {
                is GoodsOutResult.Loaded -> {
                    finder.reset()
                    mutableState.update {
                        it.copy(
                            pallet = result.value.pallet,
                            working = null,
                            problem = null,
                            event = GoodsInEvent.Done(
                                action = write,
                                message = result.value.message,
                                isNothingChanged = result.value.status == GoodsInWriteStatus.NOTHING_CHANGED,
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
            when (val result = api.pallet(token, warehouseId, palletId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(pallet = result.value, isLoading = false) }
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
