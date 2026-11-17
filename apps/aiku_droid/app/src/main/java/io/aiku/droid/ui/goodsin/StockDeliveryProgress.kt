package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryItem
import io.aiku.droid.data.api.StockDeliveryState
import io.aiku.droid.ui.components.StockDeliveryStage

private const val CLAMP_TOLERANCE = 0.00005

enum class DeliveryView { Items, Check, Place, Done, Checklist }

internal fun StockDelivery.views(): List<DeliveryView> = buildList {
    when (state) {
        in StockDeliveryState.beforeReceiving -> add(DeliveryView.Items)
        in StockDeliveryState.inGoodsIn -> {
            add(DeliveryView.Check)
            if (state in StockDeliveryState.placing) {
                add(DeliveryView.Place)
            }
            add(DeliveryView.Done)
        }
        else -> add(DeliveryView.Done)
    }
    if (checklist != null) {
        add(DeliveryView.Checklist)
    }
}

internal fun StockDelivery.defaultView(): DeliveryView = when {
    state in StockDeliveryState.beforeReceiving -> DeliveryView.Items
    state in StockDeliveryState.inGoodsIn && items.any { it.needsCheck } -> DeliveryView.Check
    state in StockDeliveryState.placing && items.any { it.canBePlaced } -> DeliveryView.Place
    else -> DeliveryView.Done
}

internal fun StockDelivery.itemsFor(view: DeliveryView): List<StockDeliveryItem> = when (view) {
    DeliveryView.Items -> items
    DeliveryView.Check -> items.filterNot { it.isDone }
    DeliveryView.Place -> items.filter { it.state != StockDeliveryState.CANCELLED && it.hasBeenChecked }
    DeliveryView.Done -> items.filter { it.isDone }
    DeliveryView.Checklist -> emptyList()
}

internal val StockDelivery.canCheck: Boolean get() = can(GoodsInActionKey.CHECK)

internal val StockDelivery.canPlace: Boolean get() = can(GoodsInActionKey.PLACE)

internal val StockDelivery.canRecordChecks: Boolean get() = can(GoodsInActionKey.CHECKLIST)

internal fun StockDelivery.scansLines(view: DeliveryView?): Boolean = when (view) {
    DeliveryView.Check -> canCheck
    DeliveryView.Place -> canPlace
    else -> false
}

internal fun StockDelivery.nextToPlace(): StockDeliveryItem? = itemsFor(DeliveryView.Place).firstOrNull { it.canBePlaced }

internal fun StockDelivery.scanCandidates(view: DeliveryView?): List<StockDeliveryItem> = when (view) {
    DeliveryView.Place -> items.filter { it.canBePlaced }
    else -> itemsFor(DeliveryView.Check)
}

internal fun List<StockDeliveryItem>.matchingCode(code: String): List<StockDeliveryItem> {
    val needle = code.trim()
    if (needle.isEmpty()) {
        return emptyList()
    }
    return filter { item ->
        val sko = item.sko
        sko != null && (sko.barcode?.trim() == needle || sko.code.equals(needle, ignoreCase = true))
    }
}

internal fun StockDeliveryItem.wasRaisedAbove(sent: Double): Boolean = (skoChecked ?: 0.0) > sent + CLAMP_TOLERANCE

internal fun StockDelivery.stage(): StockDeliveryStage? = when (state) {
    in StockDeliveryState.beforeReceiving -> StockDeliveryStage.Dispatched
    StockDeliveryState.RECEIVED -> StockDeliveryStage.Received
    StockDeliveryState.CHECKED -> StockDeliveryStage.Checked
    StockDeliveryState.BOOKING_IN -> StockDeliveryStage.BookingIn
    StockDeliveryState.BOOKED_IN, StockDeliveryState.PLACED -> StockDeliveryStage.BookedIn
    else -> null
}
