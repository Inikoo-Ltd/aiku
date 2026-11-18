package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ListMeta(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    @SerialName("per_page") val perPage: Int = 0,
    val total: Int = 0,
)

@Serializable
data class AreaRef(val id: Long, val code: String = "", val name: String = "") {
    val label: String get() = name.ifBlank { code }
}

@Serializable
data class PlaceRef(val id: Long, val code: String = "")

@Serializable
data class WarehouseRef(val id: Long, val code: String = "", val name: String = "")

@Serializable
data class StockRights(
    @SerialName("edit_stocks") val editStocks: Boolean = false,
    @SerialName("edit_locations") val editLocations: Boolean = false,
)

enum class LocationStockType(val key: String) {
    Picking("picking"),
    Storing("storing"),
    ;

    companion object {
        fun ofKey(key: String?): LocationStockType? = entries.firstOrNull { it.key == key }
    }
}

enum class PickingChannel(val field: String) {
    Wholesale("set_as_priority_wholesale"),
    Dropshipping("set_as_priority_dropshipping"),
}

object LocationStatus {
    const val OPERATIONAL = "operational"
    const val BROKEN = "broken"
}

object MovementReasonKey {
    const val RECOUNT = "recount"
    const val TRANSFER = "transfer"
}

@Serializable
data class RemovalChange(val by: String? = null, val at: String? = null)

@Serializable
data class EmptyLocationsRemoval(
    val enabled: Boolean = false,
    @SerialName("can_edit") val canEdit: Boolean = false,
    @SerialName("last_change") val lastChange: RemovalChange? = null,
    @Serializable(with = LenientInt::class) @SerialName("to_remove") val toRemove: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("removed_last_7_days") val removedLastSevenDays: Int = 0,
)

@Serializable
data class LocationsOverview(
    val warehouse: WarehouseRef? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_areas") val numberAreas: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_locations") val numberLocations: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_locations_empty") val numberLocationsEmpty: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_locations_partially_empty") val numberLocationsPartiallyEmpty: Int = 0,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    @SerialName("empty_locations_removal") val emptyLocationsRemoval: EmptyLocationsRemoval = EmptyLocationsRemoval(),
    @Serializable(with = LenientDoubleOrNull::class) val utilisation: Double? = null,
    @SerialName("currency_code") val currencyCode: String? = null,
)

@Serializable
data class WarehouseArea(
    val id: Long,
    val slug: String = "",
    val code: String = "",
    val name: String = "",
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("picking_position") val pickingPosition: Double? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_locations") val numberLocations: Int = 0,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    @Serializable(with = LenientInt::class) @SerialName("number_empty_locations") val numberEmptyLocations: Int = 0,
) {
    val label: String get() = name.ifBlank { code }
}

@Serializable
data class LocationSummary(
    val id: Long,
    val slug: String = "",
    val code: String = "",
    val status: String = "",
    val area: AreaRef? = null,
    @SerialName("allow_stocks") val allowStocks: Boolean = false,
    @SerialName("allow_fulfilment") val allowFulfilment: Boolean = false,
    @SerialName("allow_dropshipping") val allowDropshipping: Boolean = false,
    @SerialName("is_goods_out") val isGoodsOut: Boolean = false,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("max_weight") val maxWeight: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("max_volume") val maxVolume: Double? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_org_stock_slots") val numberOrgStockSlots: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_empty_stock_slots") val numberEmptyStockSlots: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_pallets") val numberPallets: Int = 0,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    val barcode: String? = null,
    @SerialName("last_audited_at") val lastAuditedAt: String? = null,
) {
    val isBroken: Boolean get() = status == LocationStatus.BROKEN
}

enum class LocationTab(val key: String?) {
    All(null),
    Empty("empty"),
    PartiallyEmpty("partially_empty"),
    ;

    companion object {
        fun ofKey(key: String?): LocationTab = entries.firstOrNull { it.key == key && key != null } ?: All
    }
}

enum class LocationStatusFilter(val key: String) {
    Operational(LocationStatus.OPERATIONAL),
    Broken(LocationStatus.BROKEN),
}

enum class LocationScope(val key: String) {
    Stocks("stocks"),
    Fulfilment("fulfilment"),
    Dropshipping("dropshipping"),
}

enum class LocationOccupancy(val key: String) {
    Empty("empty"),
    PartiallyEmpty("partially_empty"),
    GoodsOut("goods_out"),
    KeepEmptyOrgStocks("keep_empty_org_stocks"),
}

enum class LocationSort(val key: String) {
    Code("code"),
    StockValue("-stock_value"),
    Weight("-max_weight"),
    Volume("-max_volume"),
}

data class LocationQuery(
    val tab: LocationTab = LocationTab.All,
    val search: String? = null,
    val areaId: Long? = null,
    val areaIds: Set<Long> = emptySet(),
    val statuses: Set<LocationStatusFilter> = emptySet(),
    val scopes: Set<LocationScope> = emptySet(),
    val occupancies: Set<LocationOccupancy> = emptySet(),
    val sort: LocationSort? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

data class LocationPage(val locations: List<LocationSummary>, val meta: ListMeta)

@Serializable
data class LocationPallet(
    val id: Long,
    val reference: String = "",
    @SerialName("customer_reference") val customerReference: String? = null,
    @SerialName("state_label") val stateLabel: String? = null,
    @SerialName("customer_name") val customerName: String? = null,
)

@Serializable
data class LocationOrgStock(
    val id: Long,
    val code: String = "",
    val name: String = "",
    val type: String = "",
    @Serializable(with = LenientDouble::class) val quantity: Double = 0.0,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("quantity_value") val quantityValue: Double? = null,
    val notes: String? = null,
    @SerialName("location_org_stock_id") val locationOrgStockId: Long,
    @SerialName("default_wholesale_picking_location") val isDefaultWholesale: Boolean = false,
    @SerialName("default_dropshipping_picking_location") val isDefaultDropshipping: Boolean = false,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("min_stock") val minStock: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("max_stock") val maxStock: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("replenishment_stock") val replenishmentStock: Double? = null,
    @SerialName("audited_at") val auditedAt: String? = null,
    @SerialName("image_url") val imageUrl: String? = null,
    @SerialName("picking_location") val pickingLocation: PlaceRef? = null,
    @SerialName("picking_dropshipping_location") val pickingDropshippingLocation: PlaceRef? = null,
) {
    val stock: Double get() = quantityValue ?: quantity

    val stockType: LocationStockType? get() = LocationStockType.ofKey(type)
}

data class LocationDetail(
    val summary: LocationSummary,
    val pallets: List<LocationPallet> = emptyList(),
    val orgStocks: List<LocationOrgStock> = emptyList(),
    val can: StockRights? = null,
) {
    val id: Long get() = summary.id

    val code: String get() = summary.code

    fun orgStock(locationOrgStockId: Long): LocationOrgStock? = orgStocks.firstOrNull { it.locationOrgStockId == locationOrgStockId }
}

data class AuditCount(val locationOrgStockId: Long, val quantity: Double, val reason: String?, val note: String?)

data class MoveSomeItem(val orgStockId: Long, val quantity: Double, val removeAfterMove: Boolean)

sealed interface LocationWrite {
    data class Audit(val counts: List<AuditCount>) : LocationWrite
    data class MoveAll(val targetLocationId: Long, val removeAfterMove: Boolean) : LocationWrite
    data class MoveSome(val targetLocationId: Long, val items: List<MoveSomeItem>) : LocationWrite
    data class RemoveOrgStocks(val orgStockIds: List<Long>) : LocationWrite
}

data class LocationWriteOutcome(val location: LocationDetail, val removed: Int? = null)

enum class StockLevel(val field: String) {
    Min("min_stock"),
    Max("max_stock"),
    Replenishment("replenishment_stock"),
}

data class Edited<T>(val value: T)

data class LocationOrgStockUpdate(
    val type: LocationStockType? = null,
    val levels: Map<StockLevel, Double?> = emptyMap(),
    val notes: Edited<String?>? = null,
    val makeDefaultFor: Set<PickingChannel> = emptySet(),
) {
    val isEmpty: Boolean get() = type == null && levels.isEmpty() && notes == null && makeDefaultFor.isEmpty()
}

data class MovementReason(val key: String, val label: String)

data class StockMovementReasons(
    val increase: List<MovementReason> = emptyList(),
    val decrease: List<MovementReason> = emptyList(),
)

enum class MovementFlow(val key: String) {
    In("in"),
    Out("out"),
    Audit("audit"),
}

@Serializable
data class StockMovement(
    val id: Long,
    val date: String? = null,
    val type: String = "",
    @SerialName("type_label") val typeLabel: String = "",
    val flow: String = "",
    @Serializable(with = LenientDouble::class) val quantity: Double = 0.0,
    @SerialName("quantity_label") val quantityLabel: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("running_quantity") val runningQuantity: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("running_quantity_org_stock") val runningQuantityOrgStock: Double? = null,
    val location: PlaceRef? = null,
    val reason: String? = null,
    @SerialName("reason_label") val reasonLabel: String? = null,
    val note: String? = null,
    @SerialName("parent_type") val parentType: String? = null,
    @SerialName("parent_reference") val parentReference: String? = null,
    val user: StaffRef? = null,
    @SerialName("user_name") val userName: String? = null,
    @SerialName("org_stock_code") val orgStockCode: String? = null,
    @SerialName("org_stock_name") val orgStockName: String? = null,
) {
    val who: String? get() = user?.name?.takeIf { it.isNotBlank() } ?: userName?.takeIf { it.isNotBlank() }

    val running: Double? get() = runningQuantityOrgStock ?: runningQuantity
}

data class MovementPage(val movements: List<StockMovement>, val meta: ListMeta)

@Serializable
data class InventoryDashboard(
    @SerialName("currency_code") val currencyCode: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    @Serializable(with = LenientInt::class) @SerialName("number_locations") val numberLocations: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_empty_locations") val numberEmptyLocations: Int = 0,
    @Serializable(with = LenientDoubleOrNull::class) val utilisation: Double? = null,
    @Serializable(with = LenientInt::class) @SerialName("number_current_org_stocks") val numberCurrentOrgStocks: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_abnormalities") val numberAbnormalities: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("low_stock_audits") val lowStockAudits: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("replenishments_wholesale") val replenishmentsWholesale: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("replenishments_dropshipping") val replenishmentsDropshipping: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("below_minimum_stock") val belowMinimumStock: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_with_minimum_stock") val numberWithMinimumStock: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("negative_stocks") val negativeStocks: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("wrong_weights") val wrongWeights: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("without_products") val withoutProducts: Int = 0,
) {
    val usedLocations: Int get() = (numberLocations - numberEmptyLocations).coerceAtLeast(0)
}

enum class SkoBucket(val key: String) {
    Current("current"),
    Discontinued("discontinued"),
    Abnormality("abnormality"),
    All("all"),
    BelowMinimum("below_minimum"),
    Negative("negative"),
    ;

    companion object {
        fun ofKey(key: String?): SkoBucket? = entries.firstOrNull { it.key == key }
    }
}

enum class SkoSort(val key: String) {
    Code("code"),
    Name("name"),
    StockLowFirst("quantity_available"),
    StockValue("-stock_value"),
    Family("family_code"),
}

data class SkoQuery(
    val bucket: SkoBucket = SkoBucket.Current,
    val familyId: Long? = null,
    val search: String? = null,
    val sort: SkoSort? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class FamilyRef(val id: Long, val code: String = "", val name: String = "")

@Serializable
data class OrgStockRow(
    val id: Long,
    val slug: String = "",
    val code: String = "",
    val name: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val family: FamilyRef? = null,
    @SerialName("image_url") val imageUrl: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("quantity_available") val quantityAvailable: Double = 0.0,
    @SerialName("quantity_available_label") val quantityAvailableLabel: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    @Serializable(with = LenientInt::class) @SerialName("number_locations") val numberLocations: Int = 0,
    @SerialName("last_seen_at") val lastSeenAt: String? = null,
)

@Serializable
data class SkoCounts(
    @Serializable(with = LenientInt::class) val current: Int = 0,
    @Serializable(with = LenientInt::class) val discontinued: Int = 0,
    @Serializable(with = LenientInt::class) val abnormality: Int = 0,
    @Serializable(with = LenientInt::class) val all: Int = 0,
    @Serializable(with = LenientIntOrNull::class) @SerialName("below_minimum") val belowMinimum: Int? = null,
    @Serializable(with = LenientIntOrNull::class) val negative: Int? = null,
) {
    fun count(bucket: SkoBucket): Int? = when (bucket) {
        SkoBucket.Current -> current
        SkoBucket.Discontinued -> discontinued
        SkoBucket.Abnormality -> abnormality
        SkoBucket.All -> all
        SkoBucket.BelowMinimum -> belowMinimum
        SkoBucket.Negative -> negative
    }
}

data class OrgStockPage(
    val rows: List<OrgStockRow>,
    val meta: ListMeta,
    val counts: SkoCounts? = null,
    val currencyCode: String? = null,
)

@Serializable
data class StockCover(
    @Serializable(with = LenientDoubleOrNull::class) val days: Double? = null,
    @SerialName("out_at") val outAt: String? = null,
)

@Serializable
data class OrgStockLocationPlace(
    val id: Long,
    val code: String = "",
    val status: String = "",
    @SerialName("allow_stocks") val allowStocks: Boolean = false,
    val area: AreaRef? = null,
)

@Serializable
data class OrgStockLocation(
    @SerialName("location_org_stock_id") val locationOrgStockId: Long,
    val type: String = "",
    @Serializable(with = LenientDouble::class) val quantity: Double = 0.0,
    @SerialName("quantity_label") val quantityLabel: String? = null,
    val notes: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("min_stock") val minStock: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("max_stock") val maxStock: Double? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("replenishment_stock") val replenishmentStock: Double? = null,
    @SerialName("is_default_wholesale") val isDefaultWholesale: Boolean = false,
    @SerialName("is_default_dropshipping") val isDefaultDropshipping: Boolean = false,
    @SerialName("audited_at") val auditedAt: String? = null,
    val location: OrgStockLocationPlace,
) {
    val stockType: LocationStockType? get() = LocationStockType.ofKey(type)
}

@Serializable
data class OrgStockDetail(
    val id: Long,
    val code: String = "",
    val name: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val family: FamilyRef? = null,
    @SerialName("image_url") val imageUrl: String? = null,
    val barcode: String? = null,
    @SerialName("unit_barcode") val unitBarcode: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("packed_in") val packedIn: Double? = null,
    @SerialName("is_on_demand") val isOnDemand: Boolean = false,
    @SerialName("note_to_pickers") val noteToPickers: String? = null,
    @SerialName("note_to_packers") val noteToPackers: String? = null,
    @SerialName("currency_code") val currencyCode: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    @Serializable(with = LenientDouble::class) @SerialName("quantity_in_locations") val quantityInLocations: Double = 0.0,
    @SerialName("quantity_in_locations_label") val quantityInLocationsLabel: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("quantity_reserved") val quantityReserved: Double = 0.0,
    @SerialName("quantity_reserved_label") val quantityReservedLabel: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("quantity_to_be_picked") val quantityToBePicked: Double = 0.0,
    @SerialName("quantity_to_be_picked_label") val quantityToBePickedLabel: String? = null,
    val cover: StockCover = StockCover(),
    @SerialName("picking_location") val pickingLocation: PlaceRef? = null,
    @SerialName("picking_dropshipping_location") val pickingDropshippingLocation: PlaceRef? = null,
    val locations: List<OrgStockLocation> = emptyList(),
    val can: StockRights? = null,
) {
    fun location(locationOrgStockId: Long): OrgStockLocation? = locations.firstOrNull { it.locationOrgStockId == locationOrgStockId }
}

data class MoveTarget(val locationOrgStockId: Long, val quantity: Double)

sealed interface OrgStockWrite {
    data class Audit(val counts: List<AuditCount>) : OrgStockWrite
    data class Move(val fromLocationOrgStockId: Long, val targets: List<MoveTarget>) : OrgStockWrite
    data class Consume(val locationOrgStockId: Long, val quantity: Double, val note: String?) : OrgStockWrite
    data class AddLocation(val locationId: Long, val type: LocationStockType) : OrgStockWrite
    data class RemoveLocations(val locationOrgStockIds: List<Long>) : OrgStockWrite
}

data class OrgStockWriteOutcome(val orgStock: OrgStockDetail, val status: String, val message: String?)

enum class FamilyState(val key: String) {
    Active("active"),
    InProcess("in_process"),
    Discontinuing("discontinuing"),
    Discontinued("discontinued"),
}

data class FamilyQuery(
    val state: FamilyState = FamilyState.Active,
    val search: String? = null,
    val page: Int = 1,
    val perPage: Int = DEFAULT_PER_PAGE,
) {
    companion object {
        const val DEFAULT_PER_PAGE = 50
    }
}

@Serializable
data class OrgStockFamily(
    val id: Long,
    val state: String = "",
    val code: String = "",
    val name: String = "",
    @Serializable(with = LenientInt::class) @SerialName("number_current_org_stocks") val numberCurrentOrgStocks: Int = 0,
    @SerialName("currency_code") val currencyCode: String? = null,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    @Serializable(with = LenientDoubleOrNull::class) @SerialName("stock_cover") val stockCover: Double? = null,
)

@Serializable
data class FamilyCounts(
    @Serializable(with = LenientInt::class) @SerialName("in_process") val inProcess: Int = 0,
    @Serializable(with = LenientInt::class) val active: Int = 0,
    @Serializable(with = LenientInt::class) val discontinuing: Int = 0,
    @Serializable(with = LenientInt::class) val discontinued: Int = 0,
) {
    fun count(state: FamilyState): Int = when (state) {
        FamilyState.Active -> active
        FamilyState.InProcess -> inProcess
        FamilyState.Discontinuing -> discontinuing
        FamilyState.Discontinued -> discontinued
    }
}

data class FamilyPage(val families: List<OrgStockFamily>, val meta: ListMeta, val counts: FamilyCounts? = null)

@Serializable
data class FamilySummary(
    val id: Long,
    val code: String = "",
    val name: String = "",
    val state: String = "",
    @Serializable(with = LenientInt::class) @SerialName("number_current_org_stocks") val numberCurrentOrgStocks: Int = 0,
    @Serializable(with = LenientDouble::class) @SerialName("stock_value") val stockValue: Double = 0.0,
    @SerialName("currency_code") val currencyCode: String? = null,
)

@Serializable
data class FamilySko(
    val id: Long,
    val code: String = "",
    val name: String = "",
    val state: String = "",
    @Serializable(with = LenientDouble::class) @SerialName("quantity_available") val quantityAvailable: Double = 0.0,
    @Serializable(with = LenientDouble::class) @SerialName("quantity_in_locations") val quantityInLocations: Double = 0.0,
)

data class FamilySkoPage(val family: FamilySummary?, val skos: List<FamilySko>, val meta: ListMeta)

interface InventoryApi {
    suspend fun locationsOverview(token: String, warehouseId: Long): GoodsOutResult<LocationsOverview>

    suspend fun setEmptyLocationsRemoval(token: String, warehouseId: Long, enabled: Boolean): GoodsOutResult<EmptyLocationsRemoval>

    suspend fun areas(token: String, warehouseId: Long, search: String?): GoodsOutResult<List<WarehouseArea>>

    suspend fun locations(token: String, warehouseId: Long, query: LocationQuery): GoodsOutResult<LocationPage>

    suspend fun location(token: String, locationId: Long): GoodsOutResult<LocationDetail>

    suspend fun locationMovements(token: String, locationId: Long, page: Int, perPage: Int): GoodsOutResult<MovementPage>

    suspend fun writeLocation(token: String, locationId: Long, write: LocationWrite): GoodsOutResult<LocationWriteOutcome>

    suspend fun updateLocationOrgStock(token: String, locationOrgStockId: Long, update: LocationOrgStockUpdate): GoodsOutResult<LocationDetail>

    suspend fun stockMovementReasons(token: String): GoodsOutResult<StockMovementReasons>

    suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup>

    suspend fun inventoryDashboard(token: String, warehouseId: Long): GoodsOutResult<InventoryDashboard>

    suspend fun orgStocks(token: String, warehouseId: Long, query: SkoQuery): GoodsOutResult<OrgStockPage>

    suspend fun orgStock(token: String, warehouseId: Long, orgStockId: Long): GoodsOutResult<OrgStockDetail>

    suspend fun orgStockMovements(token: String, warehouseId: Long, orgStockId: Long, flow: MovementFlow?, page: Int, perPage: Int): GoodsOutResult<MovementPage>

    suspend fun writeOrgStock(token: String, warehouseId: Long, orgStockId: Long, write: OrgStockWrite): GoodsOutResult<OrgStockWriteOutcome>

    suspend fun families(token: String, warehouseId: Long, query: FamilyQuery): GoodsOutResult<FamilyPage>

    suspend fun familySkos(token: String, warehouseId: Long, familyId: Long, page: Int, perPage: Int): GoodsOutResult<FamilySkoPage>
}
