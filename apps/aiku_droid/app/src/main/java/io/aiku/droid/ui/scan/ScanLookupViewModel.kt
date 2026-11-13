package io.aiku.droid.ui.scan

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.ScanResultRoute
import io.aiku.droid.ui.navigation.SkuRoute
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface ScanDestination {
    data class Sko(val code: String) : ScanDestination
    data class Location(val code: String) : ScanDestination
    data class DeliveryNote(val note: DeliveryNoteRow) : ScanDestination
    data class Unknown(val code: String) : ScanDestination
}

fun ScanLookup.destination(scannedCode: String): ScanDestination {
    val sko = sko
    val location = location
    val note = deliveryNote
    return when {
        type == ScanMatch.SKO && sko != null -> ScanDestination.Sko(sko.code)
        type == ScanMatch.LOCATION && location != null -> ScanDestination.Location(location.code)
        type == ScanMatch.DELIVERY_NOTE && note != null -> ScanDestination.DeliveryNote(note)
        else -> ScanDestination.Unknown(code.ifBlank { scannedCode })
    }
}

fun ScanDestination.route(): Any = when (this) {
    is ScanDestination.Sko -> SkuRoute(code = code)
    is ScanDestination.Location -> LocationRoute(code = code)
    is ScanDestination.DeliveryNote -> deliveryNoteRoute(id = note.id, reference = note.reference, state = note.state)
    is ScanDestination.Unknown -> ScanResultRoute(code = code)
}

enum class ScanProblem { Offline, ServerError, Forbidden }

sealed interface ScanLookupState {
    data object Listening : ScanLookupState
    data class LookingUp(val code: String) : ScanLookupState
    data class Found(val destination: ScanDestination) : ScanLookupState
    data class Failed(val code: String, val problem: ScanProblem) : ScanLookupState
    data object SessionExpired : ScanLookupState
}

internal fun GoodsOutResult<*>.scanProblem(): ScanProblem = when (this) {
    GoodsOutResult.Unreachable -> ScanProblem.Offline
    is GoodsOutResult.Forbidden -> ScanProblem.Forbidden
    else -> ScanProblem.ServerError
}

class ScanLookupViewModel(
    private val api: GoodsOutApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow<ScanLookupState>(ScanLookupState.Listening)
    val state: StateFlow<ScanLookupState> = mutableState.asStateFlow()

    fun lookUp(code: String) {
        val scanned = code.trim()
        val token = sessions.session.value?.token ?: return
        if (scanned.isEmpty() || mutableState.value is ScanLookupState.LookingUp) {
            return
        }
        mutableState.value = ScanLookupState.LookingUp(scanned)

        viewModelScope.launch {
            mutableState.value = when (val result = api.scan(token, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> ScanLookupState.Found(result.value.destination(scanned))
                is GoodsOutResult.NotFound -> ScanLookupState.Found(ScanDestination.Unknown(scanned))
                GoodsOutResult.SessionExpired -> ScanLookupState.SessionExpired
                else -> ScanLookupState.Failed(scanned, result.scanProblem())
            }
        }
    }

    fun destinationOpened() {
        mutableState.value = ScanLookupState.Listening
    }

    fun signInAgain() {
        sessions.end()
    }
}
