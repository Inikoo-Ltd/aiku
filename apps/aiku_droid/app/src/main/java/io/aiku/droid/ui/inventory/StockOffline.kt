package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.GoodsOutResponses
import io.aiku.droid.data.api.InventoryResponses
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationOrgStock
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.MoveTarget
import io.aiku.droid.data.api.MovementReasonKey
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockLocation
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.ui.sync.CLIENT_REQUEST_ID
import io.aiku.droid.ui.sync.doubleField
import io.aiku.droid.ui.sync.isUnsent
import io.aiku.droid.ui.sync.longField
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.addJsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonArray
import kotlinx.serialization.json.putJsonObject

data class QueuedStockWrite(val operation: OfflineOperation, val payload: JsonObject)

object StockOfflinePayloads {
    const val LOCATION_ORG_STOCK_ID = "location_org_stock_id"
    const val LOCATION_ID = "location_id"
    const val ORG_STOCK_ID = "org_stock_id"
    const val QUANTITY = "quantity"
    const val TARGETS = "targets"
    const val COUNTS = "counts"
    const val VERSIONS = "versions"

    fun moveToLocation(sourceLocationOrgStockId: Long, targetLocationId: Long, quantity: Double, clientRequestId: String): QueuedStockWrite =
        QueuedStockWrite(
            OfflineOperation.MOVE_STOCK,
            buildJsonObject {
                put(LOCATION_ORG_STOCK_ID, sourceLocationOrgStockId)
                put(LOCATION_ID, targetLocationId)
                put(QUANTITY, GoodsOutResponses.quantityValue(quantity))
                put(CLIENT_REQUEST_ID, clientRequestId)
            },
        )

    fun moveToRows(sourceLocationOrgStockId: Long, targets: List<MoveTarget>, clientRequestId: String): QueuedStockWrite =
        QueuedStockWrite(
            OfflineOperation.MOVE_STOCK,
            buildJsonObject {
                put(LOCATION_ORG_STOCK_ID, sourceLocationOrgStockId)
                putJsonArray(TARGETS) {
                    targets.forEach { target ->
                        addJsonObject {
                            put(LOCATION_ORG_STOCK_ID, target.locationOrgStockId)
                            put(QUANTITY, GoodsOutResponses.quantityValue(target.quantity))
                        }
                    }
                }
                put(QUANTITY, GoodsOutResponses.quantityValue(targets.sumOf { it.quantity }))
                put(CLIENT_REQUEST_ID, clientRequestId)
            },
        )

    fun auditLocation(locationId: Long, counts: List<AuditCount>, versions: Map<Long, Int>, clientRequestId: String): QueuedStockWrite =
        QueuedStockWrite(
            OfflineOperation.AUDIT_LOCATION,
            buildJsonObject {
                put(LOCATION_ID, locationId)
                put(COUNTS, InventoryResponses.countsArray(counts, unchangedReason = MovementReasonKey.RECOUNT))
                putVersions(counts, versions)
                put(CLIENT_REQUEST_ID, clientRequestId)
            },
        )

    fun auditOrgStock(orgStockId: Long, counts: List<AuditCount>, versions: Map<Long, Int>, clientRequestId: String): QueuedStockWrite =
        QueuedStockWrite(
            OfflineOperation.AUDIT_ORG_STOCK,
            buildJsonObject {
                put(ORG_STOCK_ID, orgStockId)
                put(COUNTS, InventoryResponses.countsArray(counts, unchangedReason = null))
                putVersions(counts, versions)
                put(CLIENT_REQUEST_ID, clientRequestId)
            },
        )

    private fun JsonObjectBuilder.putVersions(counts: List<AuditCount>, versions: Map<Long, Int>) {
        putJsonObject(VERSIONS) {
            counts.forEach { count -> versions[count.locationOrgStockId]?.let { put(count.locationOrgStockId.toString(), it) } }
        }
    }
}

fun LocationDetail.versions(): Map<Long, Int> = orgStocks.mapNotNull { sko -> sko.version?.let { sko.locationOrgStockId to it } }.toMap()

fun OrgStockDetail.versions(): Map<Long, Int> = locations.mapNotNull { location -> location.version?.let { location.locationOrgStockId to it } }.toMap()

fun QueuedAction.moveTargets(): List<MoveTarget> = (payload[StockOfflinePayloads.TARGETS] as? JsonArray).orEmpty().mapNotNull { element ->
    val target = element as? JsonObject ?: return@mapNotNull null
    val linkId = target.longField(StockOfflinePayloads.LOCATION_ORG_STOCK_ID) ?: return@mapNotNull null
    MoveTarget(locationOrgStockId = linkId, quantity = target.doubleField(StockOfflinePayloads.QUANTITY) ?: 0.0)
}

fun QueuedAction.countedQuantities(): Map<Long, Double> = (payload[StockOfflinePayloads.COUNTS] as? JsonArray).orEmpty().mapNotNull { element ->
    val count = element as? JsonObject ?: return@mapNotNull null
    val linkId = count.longField(StockOfflinePayloads.LOCATION_ORG_STOCK_ID) ?: return@mapNotNull null
    val quantity = count.doubleField(StockOfflinePayloads.QUANTITY) ?: return@mapNotNull null
    linkId to quantity
}.toMap()

internal val stockOperations = setOf(OfflineOperation.MOVE_STOCK, OfflineOperation.AUDIT_LOCATION, OfflineOperation.AUDIT_ORG_STOCK)

fun LocationDetail.withQueued(actions: List<QueuedAction>): LocationDetail {
    val queued = actions.filter { it.isUnsent && it.operation in stockOperations }
    if (queued.isEmpty()) {
        return this
    }
    val stocks = queued.fold(orgStocks.associate { it.locationOrgStockId to it.stock }) { levels, action -> levels.applied(action) }
    return copy(orgStocks = orgStocks.map { sko -> stocks[sko.locationOrgStockId]?.takeIf { it != sko.stock }?.let(sko::withStock) ?: sko })
}

fun OrgStockDetail.withQueued(actions: List<QueuedAction>): OrgStockDetail {
    val queued = actions.filter { it.isUnsent && it.operation in stockOperations }
    if (queued.isEmpty()) {
        return this
    }
    val levels = queued.fold(locations.associate { it.locationOrgStockId to it.quantity }) { current, action -> current.applied(action) }
    val changed = locations.map { location -> levels[location.locationOrgStockId]?.takeIf { it != location.quantity }?.let(location::withQuantity) ?: location }
    val delta = changed.sumOf { it.quantity } - locations.sumOf { it.quantity }
    return if (delta == 0.0) {
        copy(locations = changed)
    } else {
        copy(locations = changed, quantityInLocations = quantityInLocations + delta, quantityInLocationsLabel = null)
    }
}

private fun Map<Long, Double>.applied(action: QueuedAction): Map<Long, Double> = when (action.operation) {
    OfflineOperation.MOVE_STOCK -> {
        val source = action.payload.longField(StockOfflinePayloads.LOCATION_ORG_STOCK_ID)
        val quantity = action.payload.doubleField(StockOfflinePayloads.QUANTITY) ?: 0.0
        val moved = toMutableMap()
        if (source != null && source in moved) {
            moved[source] = (moved.getValue(source) - quantity).coerceAtLeast(0.0)
        }
        action.moveTargets().forEach { target ->
            moved[target.locationOrgStockId]?.let { moved[target.locationOrgStockId] = it + target.quantity }
        }
        moved
    }
    else -> this + action.countedQuantities().filterKeys { it in this }
}

private fun LocationOrgStock.withStock(stock: Double): LocationOrgStock = if (quantityValue != null) copy(quantityValue = stock) else copy(quantity = stock)

private fun OrgStockLocation.withQuantity(quantity: Double): OrgStockLocation = copy(quantity = quantity, quantityLabel = null)

class MovementReasonsMemory {
    @Volatile
    var reasons: StockMovementReasons? = null
}

fun LocationWrite.withRequestId(newId: () -> String): LocationWrite = when (this) {
    is LocationWrite.Audit -> copy(clientRequestId = clientRequestId ?: newId())
    is LocationWrite.MoveAll -> copy(clientRequestId = clientRequestId ?: newId())
    is LocationWrite.MoveSome -> copy(clientRequestId = clientRequestId ?: newId())
    is LocationWrite.RemoveOrgStocks -> this
}

fun OrgStockWrite.withRequestId(newId: () -> String): OrgStockWrite = when (this) {
    is OrgStockWrite.Audit -> copy(clientRequestId = clientRequestId ?: newId())
    is OrgStockWrite.Move -> copy(clientRequestId = clientRequestId ?: newId())
    else -> this
}
