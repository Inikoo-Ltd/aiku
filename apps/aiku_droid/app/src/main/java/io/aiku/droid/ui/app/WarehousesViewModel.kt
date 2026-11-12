package io.aiku.droid.ui.app

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.api.WarehouseApi
import io.aiku.droid.data.api.WarehousesResult
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.data.session.WarehouseCache
import io.aiku.droid.data.settings.WarehouseSelection
import io.aiku.droid.ui.UiMessage
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch

sealed interface WarehousesUiState {
    data object Loading : WarehousesUiState
    data object Offline : WarehousesUiState
    data class ServerError(val statusCode: Int) : WarehousesUiState
    data class Failed(val message: UiMessage) : WarehousesUiState
    data object SessionExpired : WarehousesUiState
    data object NoWarehouses : WarehousesUiState
    data class Ready(val warehouses: List<WarehouseAccess>, val selected: WarehouseAccess) : WarehousesUiState
}

class WarehousesViewModel(
    private val warehouseApi: WarehouseApi,
    private val authApi: AuthApi,
    private val sessions: SessionRepository,
    private val selection: WarehouseSelection,
    private val cache: WarehouseCache,
) : ViewModel() {

    private val mutableState = MutableStateFlow<WarehousesUiState>(WarehousesUiState.Loading)
    val state: StateFlow<WarehousesUiState> = mutableState.asStateFlow()

    private var fetch: Job? = null

    init {
        viewModelScope.launch {
            sessions.session
                .map { it?.token }
                .distinctUntilChanged()
                .collect { token ->
                    if (token == null) {
                        fetch?.cancel()
                        cache.clear()
                        mutableState.value = WarehousesUiState.Loading
                    } else {
                        load()
                    }
                }
        }
    }

    fun load() {
        val session = sessions.session.value ?: return
        fetch?.cancel()
        mutableState.value = WarehousesUiState.Loading

        fetch = viewModelScope.launch {
            mutableState.value = when (val result = warehouseApi.warehouses(session.token)) {
                is WarehousesResult.Loaded -> loaded(session, result.warehouses)
                WarehousesResult.SessionExpired -> expired()
                WarehousesResult.AppKeyRejected -> WarehousesUiState.Failed(UiMessage.FromResource(R.string.alert_app_key_body))
                WarehousesResult.Unreachable -> cache.read(session.userId)?.let(::readyState) ?: WarehousesUiState.Offline
                is WarehousesResult.ServerError -> WarehousesUiState.ServerError(result.statusCode)
            }
        }
    }

    fun refresh() {
        val session = sessions.session.value ?: return
        if (fetch?.isActive == true) {
            return
        }
        when (mutableState.value) {
            is WarehousesUiState.Ready, WarehousesUiState.NoWarehouses -> refreshQuietly(session)
            WarehousesUiState.Offline, is WarehousesUiState.ServerError, is WarehousesUiState.Failed -> load()
            WarehousesUiState.Loading, WarehousesUiState.SessionExpired -> Unit
        }
    }

    fun select(warehouseId: Long) {
        val ready = mutableState.value as? WarehousesUiState.Ready ?: return
        val chosen = ready.warehouses.firstOrNull { it.id == warehouseId } ?: return
        selection.selectedWarehouseId = chosen.id
        mutableState.value = ready.copy(selected = chosen)
    }

    fun signInAgain() {
        sessions.end()
    }

    fun signOut() {
        val session = sessions.session.value ?: return
        sessions.end()
        viewModelScope.launch { authApi.logout(session.token) }
    }

    private fun refreshQuietly(session: StaffSession) {
        fetch = viewModelScope.launch {
            when (val result = warehouseApi.warehouses(session.token)) {
                is WarehousesResult.Loaded -> mutableState.value = loaded(session, result.warehouses)
                WarehousesResult.SessionExpired -> mutableState.value = expired()
                WarehousesResult.AppKeyRejected, WarehousesResult.Unreachable, is WarehousesResult.ServerError -> Unit
            }
        }
    }

    private fun loaded(session: StaffSession, warehouses: List<WarehouseAccess>): WarehousesUiState {
        cache.save(session.userId, warehouses)
        return readyState(warehouses)
    }

    private fun expired(): WarehousesUiState {
        cache.clear()
        return WarehousesUiState.SessionExpired
    }

    private fun readyState(warehouses: List<WarehouseAccess>): WarehousesUiState {
        if (warehouses.isEmpty()) {
            return WarehousesUiState.NoWarehouses
        }
        val selected = warehouses.firstOrNull { it.id == selection.selectedWarehouseId } ?: warehouses.first()
        selection.selectedWarehouseId = selected.id
        return WarehousesUiState.Ready(warehouses, selected)
    }
}
