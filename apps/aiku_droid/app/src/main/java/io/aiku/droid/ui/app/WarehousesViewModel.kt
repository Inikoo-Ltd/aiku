package io.aiku.droid.ui.app

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.R
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.api.WarehouseApi
import io.aiku.droid.data.api.WarehousesResult
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.settings.WarehouseSelection
import io.aiku.droid.ui.UiMessage
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
) : ViewModel() {

    private val mutableState = MutableStateFlow<WarehousesUiState>(WarehousesUiState.Loading)
    val state: StateFlow<WarehousesUiState> = mutableState.asStateFlow()

    init {
        viewModelScope.launch {
            sessions.session
                .map { it?.token }
                .distinctUntilChanged()
                .collect { token ->
                    if (token == null) {
                        mutableState.value = WarehousesUiState.Loading
                    } else {
                        load()
                    }
                }
        }
    }

    fun load() {
        val token = sessions.session.value?.token ?: return
        mutableState.value = WarehousesUiState.Loading

        viewModelScope.launch {
            mutableState.value = when (val result = warehouseApi.warehouses(token)) {
                is WarehousesResult.Loaded -> readyState(result.warehouses)
                WarehousesResult.SessionExpired -> WarehousesUiState.SessionExpired
                WarehousesResult.AppKeyRejected -> WarehousesUiState.Failed(UiMessage.FromResource(R.string.alert_app_key_body))
                WarehousesResult.Unreachable -> WarehousesUiState.Offline
                is WarehousesResult.ServerError -> WarehousesUiState.ServerError(result.statusCode)
            }
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

    private fun readyState(warehouses: List<WarehouseAccess>): WarehousesUiState {
        if (warehouses.isEmpty()) {
            return WarehousesUiState.NoWarehouses
        }
        val selected = warehouses.firstOrNull { it.id == selection.selectedWarehouseId } ?: warehouses.first()
        selection.selectedWarehouseId = selected.id
        return WarehousesUiState.Ready(warehouses, selected)
    }
}
