package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.CustomerReturn
import io.aiku.droid.data.api.CustomerReturnItem
import io.aiku.droid.data.api.GoodsOutResponses
import io.aiku.droid.data.api.PlaceTarget
import io.aiku.droid.data.api.ReturnWrite
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryItem
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.ui.goodsout.skoLabel
import io.aiku.droid.ui.sync.CLIENT_REQUEST_ID
import io.aiku.droid.ui.sync.doubleField
import io.aiku.droid.ui.sync.longField
import io.aiku.droid.ui.sync.isUnsent
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

data class QueuedReturnWrite(val operation: OfflineOperation, val payload: JsonObject)

private data class ReturnPutFields(
    val operation: OfflineOperation,
    val itemId: Long,
    val quantity: Double?,
    val locationOrgStockId: Long?,
    val clientRequestId: String?,
)

object GoodsInOfflinePayloads {
    const val RETURN_ID = "return_delivery_note_id"
    const val RETURN_ITEM_ID = "return_delivery_note_item_id"
    const val STOCK_DELIVERY_ID = "stock_delivery_id"
    const val STOCK_DELIVERY_ITEM_ID = "stock_delivery_item_id"
    const val QUANTITY = "quantity"
    const val LOCATION_ORG_STOCK_ID = "location_org_stock_id"
    const val LOCATION_ID = "location_id"
    const val SET_AS_PICKING_LOCATION = "set_as_picking_location"

    fun returnPut(returnId: Long, write: ReturnWrite, remainingOf: (Long) -> Double?): QueuedReturnWrite? {
        val fields = when (write) {
            is ReturnWrite.Returned -> ReturnPutFields(OfflineOperation.RETURN_PUT_BACK, write.itemId, write.quantity, write.locationOrgStockId, write.clientRequestId)
            is ReturnWrite.Damaged -> ReturnPutFields(OfflineOperation.RETURN_DAMAGED, write.itemId, write.quantity, null, write.clientRequestId)
            is ReturnWrite.NotReturned -> ReturnPutFields(OfflineOperation.RETURN_NOT_RETURNED, write.itemId, write.quantity, null, write.clientRequestId)
            else -> return null
        }
        val clientRequestId = fields.clientRequestId ?: return null
        val quantity = fields.quantity ?: remainingOf(fields.itemId) ?: return null
        val payload = buildJsonObject {
            put(RETURN_ID, returnId)
            put(RETURN_ITEM_ID, fields.itemId)
            put(QUANTITY, GoodsOutResponses.quantityValue(quantity))
            fields.locationOrgStockId?.let { put(LOCATION_ORG_STOCK_ID, it) }
            put(CLIENT_REQUEST_ID, clientRequestId)
        }
        return QueuedReturnWrite(fields.operation, payload)
    }

    fun place(
        stockDeliveryId: Long,
        itemId: Long,
        quantity: Double,
        target: PlaceTarget,
        setAsPickingLocation: Boolean,
        clientRequestId: String,
    ): JsonObject = buildJsonObject {
        put(STOCK_DELIVERY_ID, stockDeliveryId)
        put(STOCK_DELIVERY_ITEM_ID, itemId)
        put(QUANTITY, GoodsOutResponses.quantityValue(quantity))
        when (target) {
            is PlaceTarget.Link -> put(LOCATION_ORG_STOCK_ID, target.locationOrgStockId)
            is PlaceTarget.Location -> put(LOCATION_ID, target.locationId)
        }
        put(SET_AS_PICKING_LOCATION, setAsPickingLocation)
        put(CLIENT_REQUEST_ID, clientRequestId)
    }
}

internal val returnOperations = setOf(OfflineOperation.RETURN_PUT_BACK, OfflineOperation.RETURN_DAMAGED, OfflineOperation.RETURN_NOT_RETURNED)

fun CustomerReturn.withQueued(actions: List<QueuedAction>): CustomerReturn {
    val queued = actions.filter { it.isUnsent && it.operation in returnOperations }
    if (queued.isEmpty()) {
        return this
    }
    return copy(
        items = items.map { item ->
            queued.filter { it.payload.longField(GoodsInOfflinePayloads.RETURN_ITEM_ID) == item.id }.fold(item) { current, action -> current.withQueued(action) }
        },
    )
}

private fun CustomerReturnItem.withQueued(action: QueuedAction): CustomerReturnItem {
    val quantity = action.payload.doubleField(GoodsInOfflinePayloads.QUANTITY) ?: return this
    val left = (remaining - quantity).coerceAtLeast(0.0)
    val packedIn = sko?.packedIn
    val changed = when (action.operation) {
        OfflineOperation.RETURN_PUT_BACK -> {
            val linkId = action.payload.longField(GoodsInOfflinePayloads.LOCATION_ORG_STOCK_ID)
            copy(
                returned = returned + quantity,
                returnedLabel = skoLabel(returned + quantity, packedIn),
                locations = locations.map { location ->
                    if (location.locationOrgStockId == linkId) location.copy(quantity = location.quantity + quantity, quantityLabel = skoLabel(location.quantity + quantity, packedIn)) else location
                },
            )
        }
        OfflineOperation.RETURN_DAMAGED -> copy(damaged = damaged + quantity, damagedLabel = skoLabel(damaged + quantity, packedIn))
        else -> copy(notReturned = notReturned + quantity, notReturnedLabel = skoLabel(notReturned + quantity, packedIn))
    }
    return changed.copy(remaining = left, remainingLabel = skoLabel(left, packedIn), isHandled = isHandled || left <= 0.0)
}

fun StockDelivery.withQueued(actions: List<QueuedAction>): StockDelivery {
    val queued = actions.filter { it.isUnsent && it.operation == OfflineOperation.PLACE_STOCK_DELIVERY_ITEM }
    if (queued.isEmpty()) {
        return this
    }
    return copy(
        items = items.map { item ->
            queued.filter { it.payload.longField(GoodsInOfflinePayloads.STOCK_DELIVERY_ITEM_ID) == item.id }.fold(item) { current, action -> current.withPlacement(action) }
        },
    )
}

private fun StockDeliveryItem.withPlacement(action: QueuedAction): StockDeliveryItem {
    val quantity = action.payload.doubleField(GoodsInOfflinePayloads.QUANTITY) ?: return this
    val linkId = action.payload.longField(GoodsInOfflinePayloads.LOCATION_ORG_STOCK_ID)
    val packedIn = sko?.packedIn
    val toPlace = (skoToPlace - quantity).coerceAtLeast(0.0)
    return copy(
        skoPlaced = skoPlaced + quantity,
        skoPlacedLabel = skoLabel(skoPlaced + quantity, packedIn),
        skoToPlace = toPlace,
        skoToPlaceLabel = skoLabel(toPlace, packedIn),
        locations = locations.map { location ->
            if (location.locationOrgStockId == linkId) location.copy(quantity = location.quantity + quantity, quantityLabel = skoLabel(location.quantity + quantity, packedIn)) else location
        },
    )
}
