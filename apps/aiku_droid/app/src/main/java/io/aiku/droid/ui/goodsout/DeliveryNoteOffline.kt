package io.aiku.droid.ui.goodsout

import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DeliveryNoteLine
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.GoodsOutResponses
import io.aiku.droid.data.api.LineLocation
import io.aiku.droid.data.api.WaitingKind
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.data.offline.QueuedAction
import io.aiku.droid.data.offline.QueuedActionState
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.longOrNull
import kotlinx.serialization.json.put
import java.math.BigDecimal
import java.math.RoundingMode
import java.time.Instant

private const val OFFLINE_QUANTITY_DECIMALS = 6

data class OfflineCopy(val seenAt: Instant, val downloadedAt: Instant)

data class QueuedLineWork(
    val localId: Long,
    val lineId: Long,
    val operation: OfflineOperation,
    val quantity: Double,
    val locationOrgStockId: Long?,
    val state: QueuedActionState,
    val isUnsent: Boolean,
) {
    val canUndo: Boolean get() = state == QueuedActionState.WAITING

    val needsYou: Boolean get() = state == QueuedActionState.NEEDS_YOU
}

internal sealed interface OfflineLinePlan {
    data class Queue(val operation: OfflineOperation, val payload: JsonObject, val replaces: Long? = null) : OfflineLinePlan
    data class Undo(val localId: Long) : OfflineLinePlan
    data object NothingToDo : OfflineLinePlan
    data object OnlineOnly : OfflineLinePlan
}

internal fun deliveryNoteRecord(deliveryNoteId: Long): OfflineRecordKey = OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, deliveryNoteId)

internal object GoodsOutOfflinePayload {
    const val DELIVERY_NOTE_ID = "delivery_note_id"
    const val LINE_ID = "delivery_note_item_id"
    const val LOCATION_ORG_STOCK_ID = "location_org_stock_id"
    const val QUANTITY = "quantity"
    const val REASON = "reason"
    const val NOTE = "note"
    const val CLIENT_REQUEST_ID = "client_request_id"

    fun pick(noteId: Long, lineId: Long, locationOrgStockId: Long, quantity: Double, clientRequestId: String): JsonObject = buildJsonObject {
        put(DELIVERY_NOTE_ID, noteId)
        put(LINE_ID, lineId)
        put(LOCATION_ORG_STOCK_ID, locationOrgStockId)
        put(QUANTITY, GoodsOutResponses.quantityValue(quantity))
        put(CLIENT_REQUEST_ID, clientRequestId)
    }

    fun notPicked(noteId: Long, lineId: Long, quantity: Double, reason: String, note: String?, clientRequestId: String): JsonObject = buildJsonObject {
        put(DELIVERY_NOTE_ID, noteId)
        put(LINE_ID, lineId)
        put(QUANTITY, GoodsOutResponses.quantityValue(quantity))
        put(REASON, reason)
        put(NOTE, note.trimmedOrNull())
        put(CLIENT_REQUEST_ID, clientRequestId)
    }

    fun waiting(noteId: Long, lineId: Long, quantity: Double, note: String?, clientRequestId: String): JsonObject = buildJsonObject {
        put(DELIVERY_NOTE_ID, noteId)
        put(LINE_ID, lineId)
        put(QUANTITY, GoodsOutResponses.quantityValue(quantity))
        put(NOTE, note.trimmedOrNull())
        put(CLIENT_REQUEST_ID, clientRequestId)
    }

    fun pack(noteId: Long, lineId: Long, quantity: Double, clientRequestId: String): JsonObject = buildJsonObject {
        put(DELIVERY_NOTE_ID, noteId)
        put(LINE_ID, lineId)
        put(QUANTITY, GoodsOutResponses.quantityValue(quantity))
        put(CLIENT_REQUEST_ID, clientRequestId)
    }

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}

private val QueuedAction.lineId: Long? get() = (payload[GoodsOutOfflinePayload.LINE_ID] as? JsonPrimitive)?.longOrNull

private val QueuedAction.quantity: Double get() = (payload[GoodsOutOfflinePayload.QUANTITY] as? JsonPrimitive)?.doubleOrNull ?: 0.0

private val QueuedAction.locationOrgStockId: Long? get() = (payload[GoodsOutOfflinePayload.LOCATION_ORG_STOCK_ID] as? JsonPrimitive)?.longOrNull

private val QueuedAction.isUnsent: Boolean get() = state == QueuedActionState.WAITING && sequence == null

private val goodsOutOperations = setOf(
    OfflineOperation.PICK,
    OfflineOperation.NOT_PICKED,
    OfflineOperation.SET_WAITING_WAREHOUSE,
    OfflineOperation.SET_WAITING_CRM,
    OfflineOperation.PACK,
)

internal fun offlineQuantity(value: Double): Double = BigDecimal.valueOf(value).setScale(OFFLINE_QUANTITY_DECIMALS, RoundingMode.HALF_UP).toDouble()

internal fun queuedLineWork(actions: List<QueuedAction>): List<QueuedLineWork> = actions
    .filter { it.operation in goodsOutOperations && it.state != QueuedActionState.VOID && it.state != QueuedActionState.APPLIED }
    .mapNotNull { action ->
        action.lineId?.let { lineId ->
            QueuedLineWork(
                localId = action.localId,
                lineId = lineId,
                operation = action.operation,
                quantity = action.quantity,
                locationOrgStockId = action.locationOrgStockId,
                state = action.state,
                isUnsent = action.isUnsent,
            )
        }
    }

internal fun DeliveryNote.pickLocation(line: DeliveryNoteLine, locationOrgStockId: Long?): Long? =
    locationOrgStockId ?: line.locations.firstOrNull { (it.stock ?: 0.0) > 0.0 && it.locationOrgStockId != null }?.locationOrgStockId
        ?: line.defaultLocation()?.locationOrgStockId

internal fun offlinePlan(note: DeliveryNote, write: DeliveryNoteWrite, clientRequestId: String, queued: List<QueuedAction>): OfflineLinePlan {
    val line = when (write) {
        is DeliveryNoteWrite.Pick -> note.line(write.lineId)
        is DeliveryNoteWrite.PickAll -> note.line(write.lineId)
        is DeliveryNoteWrite.NotPicked -> note.line(write.lineId)
        is DeliveryNoteWrite.Waiting -> note.line(write.lineId)
        is DeliveryNoteWrite.Pack -> note.line(write.lineId)
        else -> null
    } ?: return OfflineLinePlan.OnlineOnly
    if (line.inPickingSession) {
        return OfflineLinePlan.OnlineOnly
    }
    val isPacking = write is DeliveryNoteWrite.Pack
    val canWork = if (isPacking) note.state == DeliveryNoteBucket.Packing.key && note.allowActions else note.canWorkLines
    if (!canWork) {
        return OfflineLinePlan.OnlineOnly
    }

    return when (write) {
        is DeliveryNoteWrite.Pick -> pickAtLocationPlan(note, line, write, clientRequestId, queued)
        is DeliveryNoteWrite.PickAll -> {
            val locationOrgStockId = note.pickLocation(line, write.locationOrgStockId) ?: return OfflineLinePlan.OnlineOnly
            line.quantityToPick.positive()
                ?.let { OfflineLinePlan.Queue(OfflineOperation.PICK, GoodsOutOfflinePayload.pick(note.id, line.id, locationOrgStockId, it, clientRequestId)) }
                ?: OfflineLinePlan.NothingToDo
        }
        is DeliveryNoteWrite.NotPicked -> if (!note.settings.allowPickerSetNotPicked) {
            OfflineLinePlan.NothingToDo
        } else {
            (write.quantity ?: line.quantityToPick).positive()
                ?.let {
                    OfflineLinePlan.Queue(
                        OfflineOperation.NOT_PICKED,
                        GoodsOutOfflinePayload.notPicked(note.id, line.id, it, write.reason.key, write.note, clientRequestId),
                    )
                }
                ?: OfflineLinePlan.NothingToDo
        }
        is DeliveryNoteWrite.Waiting -> if (!note.settings.allowWaiting) {
            OfflineLinePlan.NothingToDo
        } else {
            write.quantity.positive()
                ?.let {
                    OfflineLinePlan.Queue(
                        if (write.kind == WaitingKind.Warehouse) OfflineOperation.SET_WAITING_WAREHOUSE else OfflineOperation.SET_WAITING_CRM,
                        GoodsOutOfflinePayload.waiting(note.id, line.id, it, write.note, clientRequestId),
                    )
                }
                ?: OfflineLinePlan.NothingToDo
        }
        is DeliveryNoteWrite.Pack -> (write.quantity ?: line.quantityToPack).positive()
            ?.let { OfflineLinePlan.Queue(OfflineOperation.PACK, GoodsOutOfflinePayload.pack(note.id, line.id, it, clientRequestId)) }
            ?: OfflineLinePlan.NothingToDo
        else -> OfflineLinePlan.OnlineOnly
    }
}

private fun pickAtLocationPlan(
    note: DeliveryNote,
    line: DeliveryNoteLine,
    write: DeliveryNoteWrite.Pick,
    clientRequestId: String,
    queued: List<QueuedAction>,
): OfflineLinePlan {
    val current = line.locations.firstOrNull { it.locationOrgStockId == write.locationOrgStockId }?.quantityPicked ?: 0.0
    val change = offlineQuantity(write.quantity - current)
    val unsent = queued.lastOrNull {
        it.operation == OfflineOperation.PICK && it.isUnsent && it.lineId == line.id && it.locationOrgStockId == write.locationOrgStockId
    }
    val total = offlineQuantity((unsent?.quantity ?: 0.0) + change)

    return when {
        change == 0.0 -> OfflineLinePlan.NothingToDo
        unsent != null && total > 0.0 -> OfflineLinePlan.Queue(
            OfflineOperation.PICK,
            GoodsOutOfflinePayload.pick(note.id, line.id, write.locationOrgStockId, total, clientRequestId),
            replaces = unsent.localId,
        )
        unsent != null && total == 0.0 -> OfflineLinePlan.Undo(unsent.localId)
        change > 0.0 -> OfflineLinePlan.Queue(OfflineOperation.PICK, GoodsOutOfflinePayload.pick(note.id, line.id, write.locationOrgStockId, change, clientRequestId))
        else -> OfflineLinePlan.OnlineOnly
    }
}

private fun Double.positive(): Double? = offlineQuantity(this).takeIf { it > 0.0 }

internal fun QueuedAction.showsOn(copySeenAt: Instant?): Boolean = when (state) {
    QueuedActionState.WAITING, QueuedActionState.SENDING -> true
    QueuedActionState.APPLIED -> copySeenAt != null && performedAt.isAfter(copySeenAt)
    QueuedActionState.NEEDS_YOU, QueuedActionState.VOID -> false
}

internal fun DeliveryNote.withQueued(actions: List<QueuedAction>, copySeenAt: Instant?): DeliveryNote {
    val shown = actions.filter { it.operation in goodsOutOperations && it.showsOn(copySeenAt) && it.lineId != null }
    if (shown.isEmpty()) {
        return this
    }
    val byLine = shown.groupBy { it.lineId }
    val updated = lines.map { line -> byLine[line.id]?.fold(line) { current, action -> current.withAction(action) } ?: line }
    val change = updated.count { it.isDone(state) } - lines.count { it.isDone(state) }

    return copy(
        lines = updated,
        lineCounts = lineCounts.copy(done = (lineCounts.done + change).coerceAtLeast(0), todo = (lineCounts.todo - change).coerceAtLeast(0)),
    )
}

private fun DeliveryNoteLine.isDone(noteState: String): Boolean =
    if (noteState == DeliveryNoteBucket.Packing.key || noteState == DeliveryNoteBucket.Packed.key) quantityToPack <= 0.0 else quantityToPick <= 0.0

private fun DeliveryNoteLine.withAction(action: QueuedAction): DeliveryNoteLine {
    val quantity = action.quantity
    val packedIn = orgStock?.packedIn
    return when (action.operation) {
        OfflineOperation.PICK -> {
            val picked = offlineQuantity(quantityPicked + quantity)
            copy(
                quantityPicked = picked,
                quantityPickedLabel = skoLabel(picked, packedIn, roundToUnits = true),
                locations = locations.map { location -> if (location.locationOrgStockId == action.locationOrgStockId) location.withPicked(quantity, packedIn) else location },
            ).withPickRecomputed()
        }
        OfflineOperation.NOT_PICKED -> {
            val notPicked = offlineQuantity(quantityNotPicked + quantity)
            copy(quantityNotPicked = notPicked, quantityNotPickedLabel = skoLabel(notPicked, packedIn, roundToUnits = true)).withPickRecomputed()
        }
        OfflineOperation.SET_WAITING_WAREHOUSE -> {
            val waiting = offlineQuantity(minOf(quantity, outstandingForWaiting))
            copy(quantityWaitingWarehouse = waiting, quantityWaitingWarehouseLabel = skoLabel(waiting, packedIn, roundToUnits = true)).withPickRecomputed()
        }
        OfflineOperation.SET_WAITING_CRM -> {
            val moved = minOf(quantity, outstandingForWaiting)
            val waitingWarehouse = offlineQuantity((quantityWaitingWarehouse - moved).coerceAtLeast(0.0))
            val waitingCrm = offlineQuantity(quantityWaitingCrm + moved)
            copy(
                quantityWaitingWarehouse = waitingWarehouse,
                quantityWaitingWarehouseLabel = skoLabel(waitingWarehouse, packedIn, roundToUnits = true),
                quantityWaitingCrm = waitingCrm,
                quantityWaitingCrmLabel = skoLabel(waitingCrm, packedIn, roundToUnits = true),
            ).withPickRecomputed()
        }
        OfflineOperation.PACK -> {
            val toPack = offlineQuantity((quantityToPack - quantity).coerceAtLeast(0.0))
            val packed = offlineQuantity(quantityPacked + quantity)
            copy(
                quantityPacked = packed,
                quantityPackedLabel = skoLabel(packed, packedIn, roundToUnits = true),
                quantityToPack = toPack,
                quantityToPackLabel = skoLabel(toPack, packedIn, roundToUnits = true),
                isDonePacking = isDonePacking || toPack <= 0.0,
            )
        }
        else -> this
    }
}

private val DeliveryNoteLine.outstandingForWaiting: Double get() = (quantityRequired - quantityPicked - quantityWaitingCrm).coerceAtLeast(0.0)

private fun DeliveryNoteLine.withPickRecomputed(): DeliveryNoteLine {
    val toPick = offlineQuantity((quantityRequired - quantityPicked - quantityNotPicked - quantityWaitingWarehouse - quantityWaitingCrm).coerceAtLeast(0.0))
    return copy(quantityToPick = toPick, quantityToPickLabel = skoLabel(toPick, orgStock?.packedIn, roundToUnits = true), isPicked = toPick <= 0.0)
}

private fun LineLocation.withPicked(quantity: Double, packedIn: Double?): LineLocation {
    val left = stock?.let { offlineQuantity(it - quantity) }
    return copy(
        quantityPicked = offlineQuantity((quantityPicked ?: 0.0) + quantity),
        stock = left,
        stockLabel = left?.let { skoLabel(it, packedIn, roundToUnits = true) },
    )
}

internal fun DeliveryNoteLine.matchesScan(code: String): Boolean =
    (barcodes + listOfNotNull(orgStock?.barcode)).any { it.trim().equals(code.trim(), ignoreCase = true) }

internal fun List<QueuedLineWork>.unsentPickedAt(lineId: Long, locationOrgStockId: Long?): Double =
    filter { it.lineId == lineId && it.operation == OfflineOperation.PICK && it.isUnsent && it.locationOrgStockId == locationOrgStockId }.sumOf { it.quantity }
