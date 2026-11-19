package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.CustomerReturn
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsInHubRow
import io.aiku.droid.data.api.GoodsInSko
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ItemLocation
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.PlaceRef
import io.aiku.droid.data.api.ReturnOutcome
import io.aiku.droid.data.api.ReturnPage
import io.aiku.droid.data.api.ReturnQuery
import io.aiku.droid.data.api.ReturnWrite
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryItem
import io.aiku.droid.data.api.StockDeliveryOutcome
import io.aiku.droid.data.api.StockDeliveryPage
import io.aiku.droid.data.api.StockDeliveryQuery
import io.aiku.droid.data.api.StockDeliveryWrite
import kotlinx.coroutines.CompletableDeferred

class FakeGoodsInApi : GoodsInApi {
    var hub: GoodsOutResult<List<GoodsInHubRow>> = GoodsOutResult.Unreachable
    val deliveryQueries = mutableListOf<StockDeliveryQuery>()
    var deliveryPages: (StockDeliveryQuery) -> GoodsOutResult<StockDeliveryPage> = { GoodsOutResult.Loaded(StockDeliveryPage(emptyList(), ListMeta())) }
    var delivery: GoodsOutResult<StockDelivery> = GoodsOutResult.Unreachable
    val deliveryWrites = mutableListOf<StockDeliveryWrite>()
    var deliveryWriteResults: (StockDeliveryWrite) -> GoodsOutResult<StockDeliveryOutcome> = { GoodsOutResult.Unreachable }
    var deliveryWriteGate: CompletableDeferred<Unit>? = null
    val returnQueries = mutableListOf<ReturnQuery>()
    var returnPages: (ReturnQuery) -> GoodsOutResult<ReturnPage> = { GoodsOutResult.Loaded(ReturnPage(emptyList(), ListMeta())) }
    var customerReturn: GoodsOutResult<CustomerReturn> = GoodsOutResult.Unreachable
    val returnWrites = mutableListOf<ReturnWrite>()
    var returnWriteResults: (ReturnWrite) -> GoodsOutResult<ReturnOutcome> = { GoodsOutResult.Unreachable }
    val scans = mutableListOf<String>()
    var scanResult: GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable

    override suspend fun goodsInHub(token: String, warehouseId: Long): GoodsOutResult<List<GoodsInHubRow>> = hub

    override suspend fun stockDeliveries(token: String, warehouseId: Long, query: StockDeliveryQuery): GoodsOutResult<StockDeliveryPage> {
        deliveryQueries += query
        return deliveryPages(query)
    }

    override suspend fun stockDelivery(token: String, warehouseId: Long, stockDeliveryId: Long): GoodsOutResult<StockDelivery> = delivery

    override suspend fun writeStockDelivery(token: String, warehouseId: Long, stockDeliveryId: Long, write: StockDeliveryWrite): GoodsOutResult<StockDeliveryOutcome> {
        deliveryWrites += write
        deliveryWriteGate?.await()
        return deliveryWriteResults(write)
    }

    override suspend fun returns(token: String, warehouseId: Long, query: ReturnQuery): GoodsOutResult<ReturnPage> {
        returnQueries += query
        return returnPages(query)
    }

    override suspend fun customerReturn(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<CustomerReturn> = customerReturn

    override suspend fun writeReturn(token: String, warehouseId: Long, returnId: Long, write: ReturnWrite): GoodsOutResult<ReturnOutcome> {
        returnWrites += write
        return returnWriteResults(write)
    }

    override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> {
        scans += code
        return scanResult
    }
}

fun deliveryItem(
    id: Long,
    state: String = "received",
    expected: Double = 10.0,
    checked: Double? = null,
    placed: Double = 0.0,
    toPlace: Double = 0.0,
    skoId: Long = id * 10,
    barcode: String? = null,
    locations: List<ItemLocation> = emptyList(),
    hasPickingLocation: Boolean = false,
): StockDeliveryItem = StockDeliveryItem(
    id = id,
    state = state,
    sko = GoodsInSko(id = skoId, code = "SKO-$id", name = "Item $id", barcode = barcode),
    skoExpected = expected,
    skoChecked = checked,
    skoPlaced = placed,
    skoToPlace = toPlace,
    hasPickingLocation = hasPickingLocation,
    locations = locations,
)

fun itemLocation(linkId: Long, locationId: Long, code: String): ItemLocation =
    ItemLocation(locationOrgStockId = linkId, location = PlaceRef(id = locationId, code = code), type = "picking")

fun stockDelivery(state: String, items: List<StockDeliveryItem>, actions: List<String> = emptyList()): StockDelivery = StockDelivery(
    id = 1,
    reference = "SD-1",
    state = state,
    items = items,
    availableActions = actions.map { DeliveryNoteAction(key = it, label = it) },
)
