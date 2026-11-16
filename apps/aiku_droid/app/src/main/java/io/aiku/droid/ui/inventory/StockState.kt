package io.aiku.droid.ui.inventory

import androidx.compose.runtime.staticCompositionLocalOf
import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.LocationQuery
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.data.api.MovementReason
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.data.api.StockRights
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.scan.ScanProblem
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

internal const val SEARCH_DEBOUNCE_MILLIS = 300L
private const val FINDER_PER_PAGE = 20

class InventoryServices(val api: InventoryApi, val sessions: SessionRepository)

val LocalInventoryServices = staticCompositionLocalOf<InventoryServices> { error("Inventory services are not provided") }

data class PagedList<T>(
    val rows: List<T> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 0,
    val total: Int? = null,
    val loadingPage: Int? = null,
    val problem: GoodsOutProblem? = null,
    val failedPage: Int? = null,
) {
    val hasLoaded: Boolean get() = page > 0
    val hasMore: Boolean get() = hasLoaded && page < lastPage
    val canLoadMore: Boolean get() = hasMore && loadingPage == null
    val canLoadMoreOnScroll: Boolean get() = canLoadMore && problem == null
    val isLoadingMore: Boolean get() = (loadingPage ?: 0) > 1
    val isEmpty: Boolean get() = hasLoaded && loadingPage == null && rows.isEmpty()
    val firstPageProblem: GoodsOutProblem? get() = problem?.takeIf { failedPage == 1 }
    val nextPageProblem: GoodsOutProblem? get() = problem?.takeIf { (failedPage ?: 0) > 1 }

    fun loading(page: Int): PagedList<T> = copy(loadingPage = page, problem = null, failedPage = null)

    fun loaded(pageRows: List<T>, meta: ListMeta, key: (T) -> Any): PagedList<T> {
        val loadedPage = meta.currentPage
        val merged = if (loadedPage <= 1) pageRows else (rows + pageRows).distinctBy(key)
        return PagedList(rows = merged, page = loadedPage, lastPage = meta.lastPage, total = meta.total)
    }

    fun failed(page: Int, problem: GoodsOutProblem): PagedList<T> = copy(loadingPage = null, problem = problem, failedPage = page)
}

sealed interface StockEvent {
    data class Done(val action: Any, val message: String? = null, val removed: Int? = null) : StockEvent
    data class Refused(val message: String, val problem: GoodsOutProblem) : StockEvent
}

internal fun GoodsOutResult<*>.stockRefusal(): StockEvent.Refused {
    val message = when (this) {
        is GoodsOutResult.Forbidden -> message
        is GoodsOutResult.NotFound -> message
        is GoodsOutResult.Invalid -> message
        else -> ""
    }
    return StockEvent.Refused(message = message, problem = problem())
}

sealed interface StockScanOutcome {
    data class Open(val route: Any) : StockScanOutcome
    data class NoMatch(val code: String) : StockScanOutcome
    data class Failed(val code: String, val problem: ScanProblem) : StockScanOutcome
}

fun StockRights?.canEditStocks(warehouse: WarehouseAbilities): Boolean = this?.editStocks ?: warehouse.moveStock

fun StockRights?.canEditLocations(warehouse: WarehouseAbilities): Boolean = this?.editLocations ?: warehouse.editLocations

enum class AuditDirection { Increase, Decrease }

data class AuditLine(
    val locationOrgStockId: Long,
    val title: String,
    val subtitle: String? = null,
    val current: Double,
    val currentLabel: String? = null,
)

data class ChosenReason(val direction: AuditDirection, val key: String)

data class AuditDraft(
    val lines: List<AuditLine>,
    val counted: Map<Long, Double> = lines.associate { it.locationOrgStockId to it.current },
    val reasons: Map<Long, ChosenReason> = emptyMap(),
    val note: String = "",
) {
    fun countedOf(line: AuditLine): Double = counted[line.locationOrgStockId] ?: line.current

    fun direction(line: AuditLine): AuditDirection? {
        val delta = countedOf(line) - line.current
        return when {
            delta > 0 -> AuditDirection.Increase
            delta < 0 -> AuditDirection.Decrease
            else -> null
        }
    }

    fun reasonOf(line: AuditLine): String? {
        val direction = direction(line) ?: return null
        return reasons[line.locationOrgStockId]?.takeIf { it.direction == direction }?.key
    }

    val linesNeedingReason: List<AuditLine> get() = lines.filter { direction(it) != null && reasonOf(it) == null }

    fun withCount(locationOrgStockId: Long, value: Double): AuditDraft = copy(counted = counted + (locationOrgStockId to value.coerceAtLeast(0.0)))

    fun withReason(locationOrgStockId: Long, direction: AuditDirection, key: String): AuditDraft =
        copy(reasons = reasons + (locationOrgStockId to ChosenReason(direction, key)))

    fun counts(): List<AuditCount>? {
        if (linesNeedingReason.isNotEmpty()) {
            return null
        }
        val sharedNote = note.trim().takeIf { it.isNotEmpty() }
        return lines.map { line ->
            AuditCount(locationOrgStockId = line.locationOrgStockId, quantity = countedOf(line), reason = reasonOf(line), note = sharedNote)
        }
    }
}

fun StockMovementReasons.forDirection(direction: AuditDirection): List<MovementReason> = when (direction) {
    AuditDirection.Increase -> increase
    AuditDirection.Decrease -> decrease
}

data class PickedLocation(val id: Long, val code: String, val area: String? = null)

data class LocationFinderState(
    val query: String = "",
    val results: List<LocationSummary> = emptyList(),
    val isSearching: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val picked: PickedLocation? = null,
    val lookingUp: String? = null,
    val notALocation: String? = null,
)

class LocationFinder(
    private val api: InventoryApi,
    private val token: () -> String?,
    private val warehouseId: Long,
    private val scope: CoroutineScope,
    private val onSessionExpired: () -> Unit,
    private val excluded: () -> Set<Long> = { emptySet() },
) {
    private val mutableState = MutableStateFlow(LocationFinderState())
    val state: StateFlow<LocationFinderState> = mutableState.asStateFlow()

    private var searchJob: Job? = null

    fun reset() {
        searchJob?.cancel()
        mutableState.value = LocationFinderState()
    }

    fun search(query: String) {
        mutableState.update { it.copy(query = query, notALocation = null) }
        searchJob?.cancel()
        val needle = query.trim()
        if (needle.isEmpty()) {
            mutableState.update { it.copy(results = emptyList(), isSearching = false, problem = null) }
            return
        }
        searchJob = scope.launch {
            delay(SEARCH_DEBOUNCE_MILLIS)
            runSearch(needle)
        }
    }

    fun pick(location: LocationSummary) {
        mutableState.update { it.copy(picked = PickedLocation(id = location.id, code = location.code, area = location.area?.label)) }
    }

    fun scan(code: String) {
        val scanned = code.trim()
        val currentToken = token() ?: return
        if (scanned.isEmpty() || mutableState.value.lookingUp != null) {
            return
        }
        mutableState.update { it.copy(lookingUp = scanned, notALocation = null) }
        searchJob?.cancel()
        searchJob = scope.launch {
            when (val result = api.scan(currentToken, warehouseId, scanned)) {
                is GoodsOutResult.Loaded -> {
                    val location = result.value.location?.takeIf { result.value.type == ScanMatch.LOCATION }
                    if (location == null || location.id in excluded()) {
                        mutableState.update { it.copy(lookingUp = null, notALocation = scanned) }
                    } else {
                        mutableState.update {
                            it.copy(lookingUp = null, query = location.code, picked = PickedLocation(id = location.id, code = location.code))
                        }
                        runSearch(location.code)
                    }
                }
                GoodsOutResult.SessionExpired -> {
                    mutableState.update { it.copy(lookingUp = null) }
                    onSessionExpired()
                }
                is GoodsOutResult.NotFound -> mutableState.update { it.copy(lookingUp = null, notALocation = scanned) }
                else -> mutableState.update { it.copy(lookingUp = null, problem = result.problem()) }
            }
        }
    }

    private suspend fun runSearch(needle: String) {
        val currentToken = token() ?: return
        mutableState.update { it.copy(isSearching = true, problem = null) }
        when (val result = api.locations(currentToken, warehouseId, LocationQuery(search = needle, perPage = FINDER_PER_PAGE))) {
            is GoodsOutResult.Loaded -> {
                val hidden = excluded()
                val found = result.value.locations.filterNot { it.id in hidden }
                mutableState.update { current ->
                    val picked = current.picked
                    val refreshed = picked?.let { chosen -> found.firstOrNull { it.id == chosen.id } }
                    current.copy(
                        results = found,
                        isSearching = false,
                        picked = refreshed?.let { PickedLocation(id = it.id, code = it.code, area = it.area?.label) } ?: picked,
                    )
                }
            }
            GoodsOutResult.SessionExpired -> {
                mutableState.update { it.copy(isSearching = false) }
                onSessionExpired()
            }
            else -> mutableState.update { it.copy(isSearching = false, problem = result.problem()) }
        }
    }
}
