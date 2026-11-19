package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.FulfilmentOutboundApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.OutboundPalletRef
import io.aiku.droid.data.api.OutboundStoredItemRef
import io.aiku.droid.data.api.OutboundWriteStatus
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnOutcome
import io.aiku.droid.data.api.PalletReturnPage
import io.aiku.droid.data.api.PalletReturnQuery
import io.aiku.droid.data.api.PalletReturnScanOutcome
import io.aiku.droid.data.api.PalletReturnScanRequest
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.data.api.PlaceRef
import io.aiku.droid.data.api.ReturnItemLine
import io.aiku.droid.data.api.ReturnOtherPallet
import io.aiku.droid.data.api.ReturnPalletLine
import io.aiku.droid.data.api.ReturnStoredItem
import io.aiku.droid.data.api.Shipper
import io.aiku.droid.data.api.StoredItemAudit
import io.aiku.droid.data.api.StoredItemAuditOutcome
import io.aiku.droid.data.api.StoredItemAuditWrite
import io.aiku.droid.data.api.StoredItemDetail
import io.aiku.droid.data.api.StoredItemPage
import io.aiku.droid.data.api.StoredItemQuery
import java.io.File
import kotlinx.coroutines.CompletableDeferred

class FakeFulfilmentOutboundApi : FulfilmentOutboundApi {
    val returnQueries = mutableListOf<PalletReturnQuery>()
    var returnPages: (PalletReturnQuery) -> GoodsOutResult<PalletReturnPage> = { GoodsOutResult.Loaded(PalletReturnPage(emptyList(), ListMeta())) }
    var palletReturn: GoodsOutResult<PalletReturn> = GoodsOutResult.Unreachable
    val writes = mutableListOf<PalletReturnWrite>()
    var writeResults: (PalletReturnWrite) -> GoodsOutResult<PalletReturnOutcome> = { GoodsOutResult.Unreachable }
    var writeGate: CompletableDeferred<Unit>? = null
    val scans = mutableListOf<PalletReturnScanRequest>()
    var scanResults: (PalletReturnScanRequest) -> GoodsOutResult<PalletReturnScanOutcome> = { GoodsOutResult.Unreachable }
    var shippers: GoodsOutResult<List<Shipper>> = GoodsOutResult.Loaded(emptyList())
    val storedItemQueries = mutableListOf<StoredItemQuery>()
    var storedItemPages: (StoredItemQuery) -> GoodsOutResult<StoredItemPage> = { GoodsOutResult.Loaded(StoredItemPage(emptyList(), ListMeta())) }
    val openedStoredItems = mutableListOf<Long>()
    var storedItem: GoodsOutResult<StoredItemDetail> = GoodsOutResult.Unreachable
    var audit: GoodsOutResult<StoredItemAudit> = GoodsOutResult.Unreachable
    val auditWrites = mutableListOf<StoredItemAuditWrite>()
    var auditWriteResults: (StoredItemAuditWrite) -> GoodsOutResult<StoredItemAuditOutcome> = { GoodsOutResult.Unreachable }
    val choiceSearches = mutableListOf<String?>()
    var choices: GoodsOutResult<List<OutboundStoredItemRef>> = GoodsOutResult.Loaded(emptyList())

    override suspend fun palletReturns(token: String, warehouseId: Long, query: PalletReturnQuery): GoodsOutResult<PalletReturnPage> {
        returnQueries += query
        return returnPages(query)
    }

    override suspend fun palletReturn(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<PalletReturn> = palletReturn

    override suspend fun writePalletReturn(token: String, warehouseId: Long, returnId: Long, write: PalletReturnWrite): GoodsOutResult<PalletReturnOutcome> {
        writes += write
        writeGate?.await()
        return writeResults(write)
    }

    override suspend fun scanPalletReturn(token: String, warehouseId: Long, returnId: Long, request: PalletReturnScanRequest): GoodsOutResult<PalletReturnScanOutcome> {
        scans += request
        return scanResults(request)
    }

    override suspend fun palletReturnShippers(token: String, warehouseId: Long, returnId: Long): GoodsOutResult<List<Shipper>> = shippers

    override suspend fun downloadShipmentLabel(token: String, warehouseId: Long, returnId: Long, shipmentId: Long, target: File): AttachmentDownload = AttachmentDownload.Unreachable

    override suspend fun storedItems(token: String, warehouseId: Long, query: StoredItemQuery): GoodsOutResult<StoredItemPage> {
        storedItemQueries += query
        return storedItemPages(query)
    }

    override suspend fun storedItem(token: String, warehouseId: Long, storedItemId: Long): GoodsOutResult<StoredItemDetail> {
        openedStoredItems += storedItemId
        return storedItem
    }

    override suspend fun storedItemAudit(token: String, warehouseId: Long, auditId: Long): GoodsOutResult<StoredItemAudit> = audit

    override suspend fun writeStoredItemAudit(token: String, warehouseId: Long, auditId: Long, write: StoredItemAuditWrite): GoodsOutResult<StoredItemAuditOutcome> {
        auditWrites += write
        return auditWriteResults(write)
    }

    override suspend fun auditStoredItemChoices(token: String, warehouseId: Long, auditId: Long, search: String?): GoodsOutResult<List<OutboundStoredItemRef>> {
        choiceSearches += search
        return choices
    }
}

fun actions(vararg keys: String): List<DeliveryNoteAction> = keys.map { DeliveryNoteAction(key = it, label = it) }

fun palletLine(lineId: Long, palletId: Long, reference: String, state: String = "picking", vararg keys: String, customerReference: String? = null): ReturnPalletLine =
    ReturnPalletLine(
        lineId = lineId,
        lineState = state,
        pallet = OutboundPalletRef(id = palletId, reference = reference, customerReference = customerReference),
        location = PlaceRef(id = palletId * 10, code = "D-0$palletId"),
        availableActions = actions(*keys),
    )

fun itemLine(lineId: Long, palletId: Long, palletReference: String, ordered: Int, picked: Int, toPick: Int, onPallet: Int, vararg keys: String, location: String? = null): ReturnItemLine =
    ReturnItemLine(
        lineId = lineId,
        pallet = OutboundPalletRef(id = palletId, reference = palletReference),
        location = location?.let { PlaceRef(id = palletId * 10, code = it) },
        quantityOrdered = ordered,
        quantityPicked = picked,
        quantityToPick = toPick,
        quantityOnPallet = onPallet,
        availableActions = actions(*keys),
    )

fun storedItem(id: Long, reference: String, toPick: Int, lines: List<ReturnItemLine>, others: List<ReturnOtherPallet> = emptyList(), barcode: String? = null): ReturnStoredItem =
    ReturnStoredItem(
        storedItem = OutboundStoredItemRef(id = id, reference = reference, name = "Item $id", barcode = barcode),
        quantityOrdered = lines.sumOf { it.quantityOrdered },
        quantityPicked = lines.sumOf { it.quantityPicked },
        quantityToPick = toPick,
        lines = lines,
        otherPallets = others,
    )

fun palletReturn(
    state: String = "picking",
    typeGroup: String = PalletReturnTypeGroup.PALLETS,
    pallets: List<ReturnPalletLine> = emptyList(),
    storedItems: List<ReturnStoredItem> = emptyList(),
    canScan: Boolean = false,
    keys: List<String> = emptyList(),
): PalletReturn = PalletReturn(
    id = 7,
    reference = "acme-r004",
    typeGroup = typeGroup,
    state = state,
    stateLabel = state,
    pallets = pallets,
    storedItems = storedItems,
    canScan = canScan,
    availableActions = actions(*keys.toTypedArray()),
)

fun answer(after: PalletReturn, message: String? = null, status: String = OutboundWriteStatus.DONE): (PalletReturnWrite) -> GoodsOutResult<PalletReturnOutcome> =
    { GoodsOutResult.Loaded(PalletReturnOutcome(palletReturn = after, status = status, message = message)) }
