package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.PlaceRef
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.sync.doubleField
import io.aiku.droid.ui.sync.longField

fun LocationDetail.knownTargets(): List<PlaceRef> = orgStocks
    .flatMap { listOfNotNull(it.pickingLocation, it.pickingDropshippingLocation) }
    .filter { it.id != id && it.code.isNotBlank() }
    .distinctBy { it.id }

@Composable
fun locationQueuedText(action: QueuedAction, detail: LocationDetail, targetCodes: Map<Long, String>): String {
    if (action.operation != OfflineOperation.MOVE_STOCK) {
        return countedText(action) { linkId -> detail.orgStock(linkId)?.code }
    }
    val sko = action.payload.longField(StockOfflinePayloads.LOCATION_ORG_STOCK_ID)?.let(detail::orgStock)?.code.orEmpty()
    val quantity = stockText(action.payload.doubleField(StockOfflinePayloads.QUANTITY) ?: 0.0)
    val target = action.payload.longField(StockOfflinePayloads.LOCATION_ID)?.let { targetCodes[it] }
        ?: stringResource(R.string.offline_stock_another_location)
    return stringResource(R.string.offline_stock_moved_sko, quantity, sko, target)
}

@Composable
fun skoQueuedText(action: QueuedAction, detail: OrgStockDetail): String {
    val codeOf: (Long) -> String? = { linkId -> detail.location(linkId)?.location?.code }
    if (action.operation != OfflineOperation.MOVE_STOCK) {
        return countedText(action, codeOf)
    }
    val from = action.payload.longField(StockOfflinePayloads.LOCATION_ORG_STOCK_ID)?.let(codeOf).orEmpty()
    val to = action.moveTargets().mapNotNull { codeOf(it.locationOrgStockId) }.joinToString(LIST_SEPARATOR)
    val quantity = stockText(action.payload.doubleField(StockOfflinePayloads.QUANTITY) ?: 0.0)
    return stringResource(R.string.offline_stock_moved_between, quantity, from, to)
}

@Composable
private fun countedText(action: QueuedAction, codeOf: (Long) -> String?): String {
    val counts = action.countedQuantities().entries.joinToString(LIST_SEPARATOR) { (linkId, quantity) ->
        listOfNotNull(codeOf(linkId), stockText(quantity)).joinToString(" ")
    }
    return stringResource(R.string.offline_stock_counted, counts)
}
