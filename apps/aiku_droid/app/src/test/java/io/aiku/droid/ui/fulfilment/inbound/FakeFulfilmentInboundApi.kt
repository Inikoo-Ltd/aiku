package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.DeliveryNoteTimelineEntry
import io.aiku.droid.data.api.FulfilmentInboundApi
import io.aiku.droid.data.api.FulfilmentLocation
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletAuditStart
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryOutcome
import io.aiku.droid.data.api.PalletDeliveryPage
import io.aiku.droid.data.api.PalletDeliveryQuery
import io.aiku.droid.data.api.PalletDeliveryWrite
import io.aiku.droid.data.api.PalletOutcome
import io.aiku.droid.data.api.PalletPage
import io.aiku.droid.data.api.PalletQuery
import io.aiku.droid.data.api.PalletWrite
import io.aiku.droid.data.api.PlaceRef
import io.aiku.droid.data.api.ScanLookup

class FakeFulfilmentInboundApi : FulfilmentInboundApi {
    val deliveryQueries = mutableListOf<PalletDeliveryQuery>()
    var deliveryPages: (PalletDeliveryQuery) -> GoodsOutResult<PalletDeliveryPage> = { GoodsOutResult.Loaded(PalletDeliveryPage(emptyList(), ListMeta())) }
    var delivery: GoodsOutResult<PalletDelivery> = GoodsOutResult.Unreachable
    val deliveryWrites = mutableListOf<PalletDeliveryWrite>()
    var deliveryWriteResults: (PalletDeliveryWrite) -> GoodsOutResult<PalletDeliveryOutcome> = { GoodsOutResult.Unreachable }
    val palletQueries = mutableListOf<PalletQuery>()
    var palletPages: (PalletQuery) -> GoodsOutResult<PalletPage> = { GoodsOutResult.Loaded(PalletPage(emptyList(), ListMeta())) }
    var pallet: GoodsOutResult<Pallet> = GoodsOutResult.Unreachable
    val palletWrites = mutableListOf<PalletWrite>()
    var palletWriteResults: (PalletWrite) -> GoodsOutResult<PalletOutcome> = { GoodsOutResult.Unreachable }
    var auditStarts = 0
    var auditStart: GoodsOutResult<PalletAuditStart> = GoodsOutResult.Unreachable
    val locationSearches = mutableListOf<String>()
    var locations: GoodsOutResult<List<FulfilmentLocation>> = GoodsOutResult.Loaded(emptyList())
    val scans = mutableListOf<String>()
    var scanResult: GoodsOutResult<ScanLookup> = GoodsOutResult.Unreachable

    override suspend fun palletDeliveries(token: String, warehouseId: Long, query: PalletDeliveryQuery): GoodsOutResult<PalletDeliveryPage> {
        deliveryQueries += query
        return deliveryPages(query)
    }

    override suspend fun palletDelivery(token: String, warehouseId: Long, deliveryId: Long): GoodsOutResult<PalletDelivery> = delivery

    override suspend fun writePalletDelivery(token: String, warehouseId: Long, deliveryId: Long, write: PalletDeliveryWrite): GoodsOutResult<PalletDeliveryOutcome> {
        deliveryWrites += write
        return deliveryWriteResults(write)
    }

    override suspend fun pallets(token: String, warehouseId: Long, query: PalletQuery): GoodsOutResult<PalletPage> {
        palletQueries += query
        return palletPages(query)
    }

    override suspend fun pallet(token: String, warehouseId: Long, palletId: Long): GoodsOutResult<Pallet> = pallet

    override suspend fun writePallet(token: String, warehouseId: Long, palletId: Long, write: PalletWrite): GoodsOutResult<PalletOutcome> {
        palletWrites += write
        return palletWriteResults(write)
    }

    override suspend fun startPalletAudit(token: String, warehouseId: Long, palletId: Long): GoodsOutResult<PalletAuditStart> {
        auditStarts += 1
        return auditStart
    }

    override suspend fun fulfilmentLocations(token: String, warehouseId: Long, search: String): GoodsOutResult<List<FulfilmentLocation>> {
        locationSearches += search
        return locations
    }

    override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> {
        scans += code
        return scanResult
    }
}

fun actions(vararg keys: String): List<DeliveryNoteAction> = keys.map { DeliveryNoteAction(key = it, label = it) }

fun testPallet(
    id: Long,
    state: String = "booking_in",
    status: String = "receiving",
    location: String? = null,
    customerReference: String? = null,
    actions: List<DeliveryNoteAction> = emptyList(),
): Pallet = Pallet(
    id = id,
    reference = "acme-p$id",
    customerReference = customerReference,
    state = state,
    stateLabel = state,
    status = status,
    location = location?.let { PlaceRef(id = id * 100, code = it) },
    availableActions = actions,
)

fun testDelivery(state: String, pallets: List<Pallet>, actions: List<DeliveryNoteAction> = emptyList(), finishBlocked: String? = null): PalletDelivery = PalletDelivery(
    id = 4,
    reference = "acme-012",
    state = state,
    stateLabel = state,
    timeline = listOf("confirmed", "received", "booking_in", "booked_in").map { DeliveryNoteTimelineEntry(state = it, label = it) },
    pallets = pallets,
    finishBookingBlocked = finishBlocked,
    availableActions = actions,
)
