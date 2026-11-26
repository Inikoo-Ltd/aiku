package io.aiku.droid.ui.locations

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.Edited
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.InventoryResponses
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationOrgStock
import io.aiku.droid.data.api.LocationOrgStockUpdate
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.data.api.PickingChannel
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.StockLevel
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.offline.NoOfflineWork
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.inventory.LocationFinder
import io.aiku.droid.ui.inventory.MovementReasonsMemory
import io.aiku.droid.ui.inventory.QueuedStockWrite
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.StockOfflinePayloads
import io.aiku.droid.ui.inventory.canEditLocations
import io.aiku.droid.ui.inventory.canEditStocks
import io.aiku.droid.ui.inventory.stockOperations
import io.aiku.droid.ui.inventory.stockRefusal
import io.aiku.droid.ui.inventory.versions
import io.aiku.droid.ui.inventory.withQueued
import io.aiku.droid.ui.inventory.withRequestId
import io.aiku.droid.ui.sync.QueueEvent
import io.aiku.droid.ui.sync.RecordQueue
import io.aiku.droid.ui.sync.StatusGate
import java.time.Instant
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

private const val HTTP_OK = 200

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
    val pending: List<QueuedAction> = emptyList(),
    val copyAt: Instant? = null,
    val queueEvent: QueueEvent? = null,
    val findingSko: String? = null,
    val foundSko: FoundSko? = null,
) {
    val isWorking: Boolean get() = working != null
}

sealed interface FoundSko {
    data class Here(val locationOrgStockId: Long) : FoundSko
    data class NotSko(val code: String) : FoundSko
    data class Refused(val message: String, val problem: GoodsOutProblem) : FoundSko
}

class LocationViewModel(
    private val api: InventoryApi,
    private val sessions: SessionRepository,
    private val warehouseId: Long,
    private val locationId: Long,
    offlineWork: OfflineWork = NoOfflineWork,
    private val reasonsMemory: MovementReasonsMemory = MovementReasonsMemory(),
) : ViewModel() {

    private val mutableState = MutableStateFlow(LocationUiState())
    val state: StateFlow<LocationUiState> = mutableState.asStateFlow()

    private val queue = RecordQueue(offlineWork, warehouseId, OfflineRecordKey(OfflineRecordType.LOCATION, locationId))

    val isOnline: StateFlow<Boolean> = queue.online

    private var base: LocationDetail? = null

    val finder = LocationFinder(
        api = api,
        token = ::token,
        warehouseId = warehouseId,
        scope = viewModelScope,
        onSessionExpired = ::expireSession,
        excluded = { setOf(locationId) },
    )

    private var loadJob: Job? = null

    init {
        viewModelScope.launch {
            queue.pending.collect { actions ->
                mutableState.update { current ->
                    current.copy(
                        pending = actions.filter { it.operation in stockOperations && it.state != QueuedActionState.VOID },
                        location = base?.withQueued(actions) ?: current.location,
                    )
                }
            }
        }
        queue.watchSettled(viewModelScope) {
            if (!mutableState.value.isWorking) {
                load()
            }
        }
    }

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
        reasonsMemory.reasons?.let { remembered ->
            mutableState.update { it.copy(reasons = remembered, reasonsProblem = null) }
            return
        }
        mutableState.update { it.copy(reasonsProblem = null) }
        viewModelScope.launch {
            when (val result = api.stockMovementReasons(token)) {
                is GoodsOutResult.Loaded -> {
                    reasonsMemory.reasons = result.value
                    mutableState.update { it.copy(reasons = result.value) }
                }
                GoodsOutResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(reasonsProblem = result.problem()) }
            }
        }
    }

    fun queueEventHandled() {
        mutableState.update { it.copy(queueEvent = null) }
    }

    fun undoQueued(localId: Long) = queue.undo(localId)

    fun write(requested: LocationWrite) {
        val token = token() ?: return
        val write = requested.withRequestId(queue::newClientRequestId)
        val shown = mutableState.value.location ?: return
        if (!startWorking(write)) {
            return
        }
        viewModelScope.launch {
            val queued = queuedWrites(write, shown)
            if (queued.isEmpty()) {
                if (!passesStatusGate()) {
                    return@launch
                }
            } else if (queue.shouldQueue() && enqueue(queued)) {
                return@launch
            }
            when (val result = api.writeLocation(token, locationId, write)) {
                is GoodsOutResult.Loaded -> {
                    base = result.value.location
                    mutableState.update {
                        it.copy(
                            location = result.value.location.withQueued(queue.pending.value),
                            copyAt = null,
                            working = null,
                            problem = null,
                            event = StockEvent.Done(action = write, removed = result.value.removed),
                        )
                    }
                }
                GoodsOutResult.Unreachable -> if (queued.size != 1 || !enqueue(queued)) refuse(result)
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
            if (!passesStatusGate()) {
                return@launch
            }
            when (val result = api.updateLocationOrgStock(token, locationOrgStockId, update)) {
                is GoodsOutResult.Loaded -> {
                    base = result.value
                    mutableState.update {
                        it.copy(
                            location = result.value.withQueued(queue.pending.value),
                            copyAt = null,
                            working = null,
                            problem = null,
                            event = StockEvent.Done(action = SettingsSaved(locationOrgStockId)),
                        )
                    }
                }
                else -> refuse(result)
            }
        }
    }

    private suspend fun passesStatusGate(): Boolean {
        val event = when (queue.statusGate()) {
            StatusGate.Go -> return true
            StatusGate.OnlineOnly -> QueueEvent.OnlineOnly
            StatusGate.NeedsYou -> QueueEvent.NeedsYou
        }
        mutableState.update { it.copy(working = null, queueEvent = event) }
        return false
    }

    private fun queuedWrites(write: LocationWrite, shown: LocationDetail): List<QueuedStockWrite> = when (write) {
        is LocationWrite.Audit -> listOf(
            StockOfflinePayloads.auditLocation(locationId, write.counts, (base ?: shown).versions(), write.clientRequestId ?: queue.newClientRequestId()),
        )
        is LocationWrite.MoveAll -> shown.orgStocks.filter { it.stock > 0.0 }.mapIndexed { index, sko ->
            StockOfflinePayloads.moveToLocation(sko.locationOrgStockId, write.targetLocationId, sko.stock, write.requestIdFor(index))
        }
        is LocationWrite.MoveSome -> write.items.filter { it.quantity > 0.0 }.mapNotNull { item ->
            shown.orgStocks.firstOrNull { it.id == item.orgStockId }?.let { sko -> sko.locationOrgStockId to item.quantity }
        }.mapIndexed { index, (linkId, quantity) ->
            StockOfflinePayloads.moveToLocation(linkId, write.targetLocationId, quantity, write.requestIdFor(index))
        }
        is LocationWrite.RemoveOrgStocks -> emptyList()
    }

    private fun LocationWrite.requestIdFor(index: Int): String {
        val own = when (this) {
            is LocationWrite.MoveAll -> clientRequestId
            is LocationWrite.MoveSome -> clientRequestId
            else -> null
        }
        return own?.takeIf { index == 0 } ?: queue.newClientRequestId()
    }

    private suspend fun enqueue(writes: List<QueuedStockWrite>): Boolean {
        val queued = writes.map { queue.enqueue(it.operation, it.payload) ?: return false }
        mutableState.update { it.copy(working = null, queueEvent = QueueEvent.Queued(queued.first().operation)) }
        return true
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
            val loaded = queue.load(
                live = { api.location(token, locationId) },
                parse = { body -> InventoryResponses.parseLocation(HTTP_OK, body) },
            )
            when (val result = loaded.result) {
                is GoodsOutResult.Loaded -> {
                    base = result.value
                    mutableState.update {
                        it.copy(location = result.value.withQueued(queue.pending.value), copyAt = loaded.copyAt, isLoading = false, isRefreshing = false)
                    }
                    if (loaded.copyAt == null && result.value.orgStocks.isNotEmpty()) {
                        loadReasons()
                    }
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoading = false, isRefreshing = false, problem = result.problem()) }
            }
        }
    }

    fun findSko(code: String) {
        val token = token() ?: return
        val detail = mutableState.value.location ?: return
        val scanned = code.trim()
        if (scanned.isEmpty() || mutableState.value.findingSko != null) {
            return
        }
        mutableState.update { it.copy(findingSko = scanned, foundSko = null) }
        viewModelScope.launch {
            val outcome = when (val lookup = api.scan(token, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> {
                    val skoId = lookup.value.sko?.id?.takeIf { lookup.value.type == ScanMatch.SKO }
                    when {
                        skoId == null -> FoundSko.NotSko(scanned)
                        else -> detail.orgStocks.firstOrNull { it.id == skoId }?.let { FoundSko.Here(it.locationOrgStockId) } ?: linkHere(token, skoId)
                    }
                }
                is GoodsOutResult.NotFound -> FoundSko.NotSko(scanned)
                GoodsOutResult.SessionExpired -> null.also { expireSession() }
                else -> FoundSko.Refused("", lookup.problem())
            }
            mutableState.update { it.copy(findingSko = null, foundSko = outcome) }
        }
    }

    fun foundSkoHandled() {
        mutableState.update { it.copy(foundSko = null) }
    }

    private suspend fun linkHere(token: String, orgStockId: Long): FoundSko? =
        when (val added = api.writeOrgStock(token, warehouseId, orgStockId, OrgStockWrite.AddLocation(locationId = locationId, type = LocationStockType.Storing))) {
            is GoodsOutResult.Loaded -> when (val reloaded = api.location(token, locationId)) {
                is GoodsOutResult.Loaded -> {
                    base = reloaded.value
                    mutableState.update { it.copy(location = reloaded.value.withQueued(queue.pending.value)) }
                    reloaded.value.orgStocks.firstOrNull { it.id == orgStockId }?.let { FoundSko.Here(it.locationOrgStockId) }
                        ?: FoundSko.Refused("", GoodsOutProblem.ServerError)
                }
                GoodsOutResult.SessionExpired -> null.also { expireSession() }
                else -> FoundSko.Refused("", reloaded.problem())
            }
            GoodsOutResult.SessionExpired -> null.also { expireSession() }
            is GoodsOutResult.Forbidden -> FoundSko.Refused(added.message, added.problem())
            is GoodsOutResult.Invalid -> FoundSko.Refused(added.message, added.problem())
            is GoodsOutResult.NotFound -> FoundSko.Refused(added.message, added.problem())
            else -> FoundSko.Refused("", added.problem())
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
