package io.aiku.droid.ui.locations

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.Edited
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationOrgStock
import io.aiku.droid.data.api.LocationOrgStockUpdate
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.PickingChannel
import io.aiku.droid.data.api.StockLevel
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.LocationFinder
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.canEditLocations
import io.aiku.droid.ui.inventory.canEditStocks
import io.aiku.droid.ui.inventory.stockRefusal
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

enum class LocationAction { Audit, MoveAll, MoveSome, Remove }

fun LocationDetail.allowedActions(warehouse: WarehouseAbilities): List<LocationAction> = buildList {
    if (orgStocks.isEmpty()) {
        return@buildList
    }
    if (can.canEditStocks(warehouse)) {
        add(LocationAction.Audit)
    }
    if (can.canEditLocations(warehouse)) {
        add(LocationAction.MoveAll)
        add(LocationAction.MoveSome)
        add(LocationAction.Remove)
    }
}

data class SettingsSaved(val locationOrgStockId: Long)

data class LocationUiState(
    val location: LocationDetail? = null,
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
    val reasons: StockMovementReasons? = null,
    val reasonsProblem: GoodsOutProblem? = null,
    val working: Any? = null,
    val event: StockEvent? = null,
    val writeErrors: Map<String, List<String>> = emptyMap(),
) {
    val isWorking: Boolean get() = working != null
}

class LocationViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    warehouseId: Long,
    private val locationId: Long,
) : ViewModel() {

    private val mutableState = MutableStateFlow(LocationUiState())
    val state: StateFlow<LocationUiState> = mutableState.asStateFlow()

    val finder = LocationFinder(
        api = api,
        token = ::token,
        warehouseId = warehouseId,
        scope = viewModelScope,
        onSessionExpired = ::expireSession,
        excluded = { setOf(locationId) },
    )

    private var loadJob: Job? = null

    fun onStart() {
        if (loadJob?.isActive != true && !mutableState.value.isWorking) {
            load()
        }
    }

    fun retry() = load()

    fun refresh() {
        if (!mutableState.value.isWorking) {
            load(isPull = true)
        }
    }

    fun loadReasons() {
        val token = token() ?: return
        if (mutableState.value.reasons != null) {
            return
        }
        mutableState.update { it.copy(reasonsProblem = null) }
        viewModelScope.launch {
            when (val result = api.stockMovementReasons(token)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(reasons = result.value) }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(reasonsProblem = result.problem()) }
            }
        }
    }

    fun write(write: LocationWrite) {
        val token = token() ?: return
        if (!startWorking(write)) {
            return
        }
        viewModelScope.launch {
            when (val result = api.writeLocation(token, locationId, write)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(
                        location = result.value.location,
                        working = null,
                        problem = null,
                        event = StockEvent.Done(action = write, removed = result.value.removed),
                    )
                }
                else -> refuse(result)
            }
        }
    }

    fun update(locationOrgStockId: Long, update: LocationOrgStockUpdate) {
        val token = token() ?: return
        if (update.isEmpty || !startWorking(update)) {
            return
        }
        viewModelScope.launch {
            when (val result = api.updateLocationOrgStock(token, locationOrgStockId, update)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(location = result.value, working = null, problem = null, event = StockEvent.Done(action = SettingsSaved(locationOrgStockId)))
                }
                else -> refuse(result)
            }
        }
    }

    fun eventHandled() {
        mutableState.update { it.copy(event = null) }
    }

    fun clearWriteErrors() {
        mutableState.update { it.copy(writeErrors = emptyMap()) }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun startWorking(action: Any): Boolean {
        if (mutableState.value.location == null || mutableState.value.isWorking) {
            return false
        }
        loadJob?.cancel()
        mutableState.update { it.copy(working = action, event = null, isLoading = false, isRefreshing = false, writeErrors = emptyMap()) }
        return true
    }

    private fun refuse(result: GoodsOutResult<*>) {
        if (result == GoodsOutResult.SessionExpired) {
            mutableState.update { it.copy(working = null, isSessionExpired = true) }
            return
        }
        val errors = (result as? GoodsOutResult.Invalid)?.errors.orEmpty()
        mutableState.update { it.copy(working = null, writeErrors = errors, event = result.stockRefusal()) }
    }

    private fun load(isPull: Boolean = false) {
        val token = token() ?: return
        loadJob?.cancel()
        mutableState.update { it.copy(isLoading = true, isRefreshing = isPull, problem = null) }

        loadJob = viewModelScope.launch {
            when (val result = api.location(token, locationId)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(location = result.value, isLoading = false, isRefreshing = false) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }

    private fun token(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableState.update { it.copy(isSessionExpired = true) }
    }
}

data class SkoSettingsDraft(
    val type: LocationStockType?,
    val minStock: String,
    val maxStock: String,
    val replenishmentStock: String,
    val notes: String,
    val makeDefaultFor: Set<PickingChannel> = emptySet(),
) {
    val invalidLevels: Set<StockLevel>
        get() = levelTexts().filterValues { text -> text.isNotBlank() && parseLevel(text) == null }.keys

    fun update(original: LocationOrgStock): LocationOrgStockUpdate? {
        if (invalidLevels.isNotEmpty()) {
            return null
        }
        val originalLevels = mapOf(StockLevel.Min to original.minStock, StockLevel.Max to original.maxStock, StockLevel.Replenishment to original.replenishmentStock)
        val levels = levelTexts()
            .mapValues { (_, text) -> parseLevel(text) }
            .filter { (level, value) -> value != originalLevels[level] }
        val alreadyDefault = setOfNotNull(
            PickingChannel.Wholesale.takeIf { original.isDefaultWholesale },
            PickingChannel.Dropshipping.takeIf { original.isDefaultDropshipping },
        )
        return LocationOrgStockUpdate(
            type = type?.takeIf { it != original.stockType },
            levels = levels,
            notes = notes.trim().takeIf { it != original.notes.orEmpty().trim() }?.let { Edited(it.ifEmpty { null }) },
            makeDefaultFor = makeDefaultFor - alreadyDefault,
        )
    }

    private fun levelTexts(): Map<StockLevel, String> =
        mapOf(StockLevel.Min to minStock, StockLevel.Max to maxStock, StockLevel.Replenishment to replenishmentStock)

    companion object {
        fun of(sko: LocationOrgStock): SkoSettingsDraft = SkoSettingsDraft(
            type = sko.stockType,
            minStock = sko.minStock.levelText(),
            maxStock = sko.maxStock.levelText(),
            replenishmentStock = sko.replenishmentStock.levelText(),
            notes = sko.notes.orEmpty(),
        )

        private fun Double?.levelText(): String = this?.let { if (it % 1.0 == 0.0) it.toLong().toString() else it.toString() }.orEmpty()

        internal fun parseLevel(text: String): Double? = text.trim().replace(',', '.').takeIf { it.isNotEmpty() }?.toDoubleOrNull()?.takeIf { it >= 0 }
    }
}
