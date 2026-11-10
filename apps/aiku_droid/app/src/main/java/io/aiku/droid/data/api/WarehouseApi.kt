package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class OrganisationSummary(
    val id: Long,
    val slug: String,
    val name: String,
)

@Serializable
data class WarehouseAbilities(
    @SerialName("view_locations") val viewLocations: Boolean = false,
    @SerialName("edit_locations") val editLocations: Boolean = false,
    @SerialName("view_inventory") val viewInventory: Boolean = false,
    @SerialName("move_stock") val moveStock: Boolean = false,
    @SerialName("view_goods_in") val viewGoodsIn: Boolean = false,
    @SerialName("book_in") val bookIn: Boolean = false,
    @SerialName("supervise_goods_in") val superviseGoodsIn: Boolean = false,
    @SerialName("edit_procurement") val editProcurement: Boolean = false,
    @SerialName("place_stock_deliveries") val placeStockDeliveries: Boolean = false,
    @SerialName("view_goods_out") val viewGoodsOut: Boolean = false,
    @SerialName("pick_and_pack") val pickAndPack: Boolean = false,
    @SerialName("supervise_goods_out") val superviseGoodsOut: Boolean = false,
    @SerialName("view_fulfilment") val viewFulfilment: Boolean = false,
    @SerialName("edit_fulfilment") val editFulfilment: Boolean = false,
)

enum class Ability(val permission: String, private val grantedBy: (WarehouseAbilities) -> Boolean) {
    ViewLocations("locations.view", { it.viewLocations }),
    EditLocations("locations.edit", { it.editLocations }),
    ViewInventory("inventory.view", { it.viewInventory }),
    MoveStock("stocks.edit", { it.moveStock }),
    ViewGoodsIn("incoming.view", { it.viewGoodsIn }),
    BookIn("incoming.edit", { it.bookIn }),
    SuperviseGoodsIn("supervisor-incoming", { it.superviseGoodsIn }),
    EditProcurement("procurement.edit", { it.editProcurement }),
    PlaceStockDeliveries("procurement.edit / accounting.edit", { it.placeStockDeliveries }),
    ViewGoodsOut("dispatching.view", { it.viewGoodsOut }),
    PickAndPack("dispatching.edit", { it.pickAndPack }),
    SuperviseGoodsOut("supervisor-dispatching", { it.superviseGoodsOut }),
    ViewFulfilment("fulfilment.view", { it.viewFulfilment }),
    EditFulfilment("fulfilment.edit", { it.editFulfilment }),
    ;

    fun isGrantedBy(abilities: WarehouseAbilities): Boolean = grantedBy(abilities)
}

fun WarehouseAbilities.allows(ability: Ability): Boolean = ability.isGrantedBy(this)

@Serializable
data class WarehouseAccess(
    val id: Long,
    val slug: String,
    val code: String,
    val name: String,
    val organisation: OrganisationSummary,
    val can: WarehouseAbilities,
)

sealed interface WarehousesResult {
    data class Loaded(val warehouses: List<WarehouseAccess>) : WarehousesResult
    data object SessionExpired : WarehousesResult
    data object AppKeyRejected : WarehousesResult
    data object Unreachable : WarehousesResult
    data class ServerError(val statusCode: Int) : WarehousesResult
}

interface WarehouseApi {
    suspend fun warehouses(token: String): WarehousesResult
}
