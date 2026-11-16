package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.EmptyLocationsRemoval
import io.aiku.droid.data.api.FamilyPage
import io.aiku.droid.data.api.FamilyQuery
import io.aiku.droid.data.api.FamilySkoPage
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.InventoryDashboard
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationOrgStockUpdate
import io.aiku.droid.data.api.LocationPage
import io.aiku.droid.data.api.LocationQuery
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.LocationWriteOutcome
import io.aiku.droid.data.api.LocationsOverview
import io.aiku.droid.data.api.MovementFlow
import io.aiku.droid.data.api.MovementPage
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockPage
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.data.api.OrgStockWriteOutcome
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.SkoQuery
import io.aiku.droid.data.api.StockMovement
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.data.api.WarehouseArea
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class FakeSessions(session: StaffSession? = StaffSession(token = "7|abc", userId = 42, username = "stock", displayName = "Ana")) : SessionRepository {
    private val current = MutableStateFlow(session)
    override val session: StateFlow<StaffSession?> = current.asStateFlow()

    override fun start(session: StaffSession, rememberOnDevice: Boolean) {
        current.value = session
    }

    override fun end() {
        current.value = null
    }
}

data class MovementRequest(val orgStockId: Long?, val locationId: Long?, val flow: MovementFlow?, val page: Int)

class FakeInventoryApi : InventoryApi {
    var overview: GoodsOutResult<LocationsOverview> = GoodsOutResult.Unreachable
    var removal: GoodsOutResult<EmptyLocationsRemoval> = GoodsOutResult.Unreachable
    val removalRequests = mutableListOf<Boolean>()
    var areas: GoodsOutResult<List<WarehouseArea>> = GoodsOutResult.Loaded(emptyList())
    val areaSearches = mutableListOf<String?>()
    val locationQueries = mutableListOf<LocationQuery>()
    var locationPages: (LocationQuery) -> GoodsOutResult<LocationPage> = { GoodsOutResult.Loaded(LocationPage(emptyList(), ListMeta())) }
    var location: GoodsOutResult<LocationDetail> = GoodsOutResult.Unreachable
    val locationWrites = mutableListOf<Pair<Long, LocationWrite>>()
    var locationWriteResult: GoodsOutResult<LocationWriteOutcome> = GoodsOutResult.Unreachable
    val linkUpdates = mutableListOf<Pair<Long, LocationOrgStockUpdate>>()
    var linkUpdateResult: GoodsOutResult<LocationDetail> = GoodsOutResult.Unreachable
    var reasons: GoodsOutResult<StockMovementReasons> = GoodsOutResult.Loaded(StockMovementReasons())
    val scans = mutableListOf<String>()
    var scanResult: GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable
    var dashboard: GoodsOutResult<InventoryDashboard> = GoodsOutResult.Unreachable
    val skoQueries = mutableListOf<SkoQuery>()
    var skoPages: (SkoQuery) -> GoodsOutResult<OrgStockPage> = { GoodsOutResult.Loaded(OrgStockPage(emptyList(), ListMeta())) }
    var orgStock: GoodsOutResult<OrgStockDetail> = GoodsOutResult.Unreachable
    val orgStockRequests = mutableListOf<Long>()
    val movementRequests = mutableListOf<MovementRequest>()
    var movementPages: (Int) -> GoodsOutResult<MovementPage> = { GoodsOutResult.Loaded(MovementPage(emptyList(), ListMeta())) }
    val orgStockWrites = mutableListOf<OrgStockWrite>()
    var orgStockWriteResult: GoodsOutResult<OrgStockWriteOutcome> = GoodsOutResult.Unreachable
    val familyQueries = mutableListOf<FamilyQuery>()
    var familyPages: (FamilyQuery) -> GoodsOutResult<FamilyPage> = { GoodsOutResult.Loaded(FamilyPage(emptyList(), ListMeta())) }
    var familySkos: GoodsOutResult<FamilySkoPage> = GoodsOutResult.Unreachable

    override suspend fun locationsOverview(token: String, warehouseId: Long): GoodsOutResult<LocationsOverview> = overview

    override suspend fun setEmptyLocationsRemoval(token: String, warehouseId: Long, enabled: Boolean): GoodsOutResult<EmptyLocationsRemoval> {
        removalRequests += enabled
        return removal
    }

    override suspend fun areas(token: String, warehouseId: Long, search: String?): GoodsOutResult<List<WarehouseArea>> {
        areaSearches += search
        return areas
    }

    override suspend fun locations(token: String, warehouseId: Long, query: LocationQuery): GoodsOutResult<LocationPage> {
        locationQueries += query
        return locationPages(query)
    }

    override suspend fun location(token: String, locationId: Long): GoodsOutResult<LocationDetail> = location

    override suspend fun locationMovements(token: String, locationId: Long, page: Int, perPage: Int): GoodsOutResult<MovementPage> {
        movementRequests += MovementRequest(orgStockId = null, locationId = locationId, flow = null, page = page)
        return movementPages(page)
    }

    override suspend fun writeLocation(token: String, locationId: Long, write: LocationWrite): GoodsOutResult<LocationWriteOutcome> {
        locationWrites += locationId to write
        return locationWriteResult
    }

    override suspend fun updateLocationOrgStock(token: String, locationOrgStockId: Long, update: LocationOrgStockUpdate): GoodsOutResult<LocationDetail> {
        linkUpdates += locationOrgStockId to update
        return linkUpdateResult
    }

    override suspend fun stockMovementReasons(token: String): GoodsOutResult<StockMovementReasons> = reasons

    override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> {
        scans += code
        return scanResult
    }

    override suspend fun inventoryDashboard(token: String, warehouseId: Long): GoodsOutResult<InventoryDashboard> = dashboard

    override suspend fun orgStocks(token: String, warehouseId: Long, query: SkoQuery): GoodsOutResult<OrgStockPage> {
        skoQueries += query
        return skoPages(query)
    }

    override suspend fun orgStock(token: String, warehouseId: Long, orgStockId: Long): GoodsOutResult<OrgStockDetail> {
        orgStockRequests += orgStockId
        return orgStock
    }

    override suspend fun orgStockMovements(token: String, warehouseId: Long, orgStockId: Long, flow: MovementFlow?, page: Int, perPage: Int): GoodsOutResult<MovementPage> {
        movementRequests += MovementRequest(orgStockId = orgStockId, locationId = null, flow = flow, page = page)
        return movementPages(page)
    }

    override suspend fun writeOrgStock(token: String, warehouseId: Long, orgStockId: Long, write: OrgStockWrite): GoodsOutResult<OrgStockWriteOutcome> {
        orgStockWrites += write
        return orgStockWriteResult
    }

    override suspend fun families(token: String, warehouseId: Long, query: FamilyQuery): GoodsOutResult<FamilyPage> {
        familyQueries += query
        return familyPages(query)
    }

    override suspend fun familySkos(token: String, warehouseId: Long, familyId: Long, page: Int, perPage: Int): GoodsOutResult<FamilySkoPage> = familySkos
}

fun locationSummaries(ids: LongRange): List<LocationSummary> = ids.map { LocationSummary(id = it, code = "L-$it") }

fun movements(ids: LongRange): List<StockMovement> = ids.map { StockMovement(id = it, quantity = 1.0) }

fun meta(page: Int, lastPage: Int, total: Int): ListMeta = ListMeta(currentPage = page, lastPage = lastPage, perPage = 50, total = total)
