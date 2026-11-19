package io.aiku.droid.ui.goodsin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsInHubKey
import io.aiku.droid.data.api.GoodsInHubRow
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.api.StockDeliverySource
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.GoodsInReturnsRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

enum class HubFigure { Arriving, Received, Checked, BookingIn, BookedInWeek, PlacedWeek }

data class HubCount(val figure: HubFigure, val value: Int)

internal fun GoodsInHubRow.counts(): List<HubCount> = listOfNotNull(
    arriving?.let { HubCount(HubFigure.Arriving, it) },
    received?.let { HubCount(HubFigure.Received, it) },
    checked?.let { HubCount(HubFigure.Checked, it) },
    bookingIn?.let { HubCount(HubFigure.BookingIn, it) },
    bookedInWeek?.let { HubCount(HubFigure.BookedInWeek, it) },
    placedWeek?.let { HubCount(HubFigure.PlacedWeek, it) },
)

internal fun GoodsInHubRow.isVisible(can: WarehouseAbilities): Boolean = when (key) {
    GoodsInHubKey.FULFILMENT -> can.viewFulfilment
    GoodsInHubKey.RETURNS -> can.viewReturns
    else -> can.viewGoodsIn
}

internal fun GoodsInHubRow.route(figure: HubFigure? = null): Any? = when (key) {
    GoodsInHubKey.FULFILMENT -> FulfilmentDeliveriesRoute
    GoodsInHubKey.RETURNS -> GoodsInReturnsRoute(state = figure?.returnState()?.key)
    else -> stockSource()?.let { source -> StockDeliveriesRoute(source = source.key, state = figure?.deliveryState()?.key) }
}

private fun GoodsInHubRow.stockSource(): StockDeliverySource? = when (key) {
    GoodsInHubKey.STOCK_DELIVERIES -> StockDeliverySource.Suppliers
    GoodsInHubKey.AGENTS -> StockDeliverySource.Agents
    GoodsInHubKey.PARTNERS -> StockDeliverySource.Partners
    else -> null
}

private fun HubFigure.deliveryState(): StockDeliveryStateFilter = when (this) {
    HubFigure.Arriving -> StockDeliveryStateFilter.Dispatched
    HubFigure.Received -> StockDeliveryStateFilter.Received
    HubFigure.Checked -> StockDeliveryStateFilter.Checked
    HubFigure.BookingIn -> StockDeliveryStateFilter.BookingIn
    HubFigure.BookedInWeek -> StockDeliveryStateFilter.BookedIn
    HubFigure.PlacedWeek -> StockDeliveryStateFilter.Placed
}

private fun HubFigure.returnState(): ReturnStateFilter? = when (this) {
    HubFigure.Received -> ReturnStateFilter.Received
    HubFigure.BookingIn -> ReturnStateFilter.Returning
    else -> null
}

data class GoodsInBacklogUiState(
    val rows: List<GoodsInHubRow>? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
)

class GoodsInBacklogViewModel(
    private val api: GoodsInApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(GoodsInBacklogUiState())
    val state: StateFlow<GoodsInBacklogUiState> = mutableState.asStateFlow()

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() = load(isPull = true)

    fun signInAgain() {
        sessions.end()
    }

    private fun load(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }
        loadJob = viewModelScope.launch {
            when (val result = api.goodsInHub(token, warehouseId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(rows = result.value, isLoading = false, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }
}
