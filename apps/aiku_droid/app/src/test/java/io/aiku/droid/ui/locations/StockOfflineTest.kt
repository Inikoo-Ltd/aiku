package io.aiku.droid.ui.locations

import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationOrgStock
import io.aiku.droid.data.api.LocationOrgStockUpdate
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.MoveSomeItem
import io.aiku.droid.data.api.MoveTarget
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockLocation
import io.aiku.droid.data.api.OrgStockLocationPlace
import io.aiku.droid.data.api.OrgStockWrite
import io.aiku.droid.data.api.PlaceRef
import io.aiku.droid.data.api.StockMovementReasons
import io.aiku.droid.data.api.StockRights
import io.aiku.droid.data.offline.DownloadedRecord
import io.aiku.droid.data.offline.OfflineOperation
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType
import io.aiku.droid.ui.inventory.FakeInventoryApi
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.inventory.MovementReasonsMemory
import io.aiku.droid.ui.inventory.SkoViewModel
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.knownTargets
import io.aiku.droid.ui.sync.FakeOfflineWork
import io.aiku.droid.ui.sync.QueueEvent
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.addJsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonArray
import kotlinx.serialization.json.putJsonObject
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.time.Instant

@OptIn(ExperimentalCoroutinesApi::class)
class StockOfflineTest {

    private val api = FakeInventoryApi()
    private val offline = FakeOfflineWork()
    private val locationKey = OfflineRecordKey(OfflineRecordType.LOCATION, 220)
    private val skoKey = OfflineRecordKey(OfflineRecordType.ORG_STOCK, 77)

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private val skos = listOf(
        LocationOrgStock(id = 77, code = "AWO-12", quantity = 30.0, locationOrgStockId = 5501, version = 7, pickingLocation = PlaceRef(300, "P-01")),
        LocationOrgStock(id = 78, code = "AWO-13", quantity = 4.0, locationOrgStockId = 5502, version = 2),
        LocationOrgStock(id = 79, code = "AWO-14", quantity = 0.0, locationOrgStockId = 5503, version = 1),
    )

    private val location = LocationDetail(summary = LocationSummary(id = 220, code = "A-03-02"), orgStocks = skos, can = StockRights(editStocks = true, editLocations = true))

    private fun openLocation(): LocationViewModel {
        api.location = GoodsOutResult.Loaded(location)
        return LocationViewModel(api, FakeSessions(), warehouseId = 1, locationId = 220, offlineWork = offline).also { it.onStart() }
    }

    @Test
    fun `offline, move all from a location goes out as one explicit move per SKO that holds stock`() {
        val viewModel = openLocation()
        offline.online.value = false

        viewModel.write(LocationWrite.MoveAll(targetLocationId = 300, removeAfterMove = false))

        assertTrue(api.locationWrites.isEmpty())
        assertEquals(listOf(OfflineOperation.MOVE_STOCK, OfflineOperation.MOVE_STOCK), offline.queued.map { it.operation })
        assertEquals(
            buildJsonObject {
                put("location_org_stock_id", 5501)
                put("location_id", 300)
                put("quantity", 30)
                put("client_request_id", "req-1")
            },
            offline.queued[0].payload,
        )
        assertEquals(JsonPrimitive(5502), offline.queued[1].payload["location_org_stock_id"])
        assertEquals(JsonPrimitive(4), offline.queued[1].payload["quantity"])
        assertEquals(listOf("req-1", "req-2"), offline.queued.map { it.clientRequestId })
        assertTrue(offline.queued.none { it.payload.containsKey("remove_after_move") })
        assertTrue(offline.queued.all { it.record == locationKey })
        assertEquals(0.0, viewModel.state.value.location?.orgStock(5501)?.stock ?: -1.0, 0.0)
        assertEquals(0.0, viewModel.state.value.location?.orgStock(5502)?.stock ?: -1.0, 0.0)
        assertEquals(QueueEvent.Queued(OfflineOperation.MOVE_STOCK), viewModel.state.value.queueEvent)
    }

    @Test
    fun `offline, move some turns each SKO line into a move of its location row`() {
        val viewModel = openLocation()
        offline.online.value = false

        viewModel.write(LocationWrite.MoveSome(targetLocationId = 300, items = listOf(MoveSomeItem(orgStockId = 78, quantity = 3.0, removeAfterMove = true))))

        val move = offline.queued.single()
        assertEquals(JsonPrimitive(5502), move.payload["location_org_stock_id"])
        assertEquals(JsonPrimitive(3), move.payload["quantity"])
        assertEquals(1.0, viewModel.state.value.location?.orgStock(5502)?.stock ?: 0.0, 0.0)
    }

    @Test
    fun `an audit carries the version of every counted row as downloaded`() {
        val viewModel = openLocation()
        offline.online.value = false
        val counts = listOf(
            AuditCount(locationOrgStockId = 5501, quantity = 28.0, reason = "damaged", note = "Two broken"),
            AuditCount(locationOrgStockId = 5502, quantity = 4.0, reason = null, note = "Two broken"),
        )

        viewModel.write(LocationWrite.Audit(counts))

        val audit = offline.queued.single()
        assertEquals(OfflineOperation.AUDIT_LOCATION, audit.operation)
        assertEquals(
            buildJsonObject {
                put("location_id", 220)
                putJsonArray("counts") {
                    addJsonObject {
                        put("location_org_stock_id", 5501)
                        put("quantity", 28)
                        put("reason", "damaged")
                        put("note", "Two broken")
                    }
                    addJsonObject {
                        put("location_org_stock_id", 5502)
                        put("quantity", 4)
                        put("reason", "recount")
                        put("note", "Two broken")
                    }
                }
                putJsonObject("versions") {
                    put("5501", 7)
                    put("5502", 2)
                }
                put("client_request_id", "req-1")
            },
            audit.payload,
        )
        assertEquals(28.0, viewModel.state.value.location?.orgStock(5501)?.stock ?: 0.0, 0.0)
    }

    @Test
    fun `online, an audit that cannot reach Aiku is queued with the same request id`() {
        val viewModel = openLocation()
        api.locationWriteResult = GoodsOutResult.Unreachable

        viewModel.write(LocationWrite.Audit(listOf(AuditCount(locationOrgStockId = 5501, quantity = 29.0, reason = "damaged", note = null))))

        val sent = api.locationWrites.single().second as LocationWrite.Audit
        assertEquals("req-1", sent.clientRequestId)
        assertEquals("req-1", offline.queued.single().clientRequestId)
    }

    @Test
    fun `online, a move of several SKOs that cannot reach Aiku is not queued twice over and shows the problem`() {
        val viewModel = openLocation()
        api.locationWriteResult = GoodsOutResult.Unreachable

        viewModel.write(LocationWrite.MoveAll(targetLocationId = 300, removeAfterMove = false))

        assertTrue(offline.queued.isEmpty())
        assertTrue(viewModel.state.value.event is StockEvent.Refused)
    }

    @Test
    fun `remove and SKO settings are online only, and online they flush first`() {
        val viewModel = openLocation()
        offline.online.value = false

        viewModel.write(LocationWrite.RemoveOrgStocks(listOf(79)))
        assertEquals(QueueEvent.OnlineOnly, viewModel.state.value.queueEvent)
        viewModel.queueEventHandled()
        viewModel.update(5501, LocationOrgStockUpdate(type = LocationStockType.Storing))
        assertEquals(QueueEvent.OnlineOnly, viewModel.state.value.queueEvent)
        assertTrue(api.locationWrites.isEmpty() && api.linkUpdates.isEmpty())

        offline.online.value = true
        offline.flushResult = false
        viewModel.write(LocationWrite.RemoveOrgStocks(listOf(79)))
        assertEquals(QueueEvent.NeedsYou, viewModel.state.value.queueEvent)
        assertEquals(listOf(locationKey), offline.flushed)
        assertTrue(api.locationWrites.isEmpty())
    }

    @Test
    fun `offline, the location opens from the downloaded copy and remembered reasons are offered`() {
        val memory = MovementReasonsMemory().apply { reasons = StockMovementReasons(increase = emptyList(), decrease = emptyList()) }
        offline.online.value = false
        offline.downloads[locationKey] = DownloadedRecord(
            body = """{"data":{"id":220,"code":"A-03-02","org_stocks":[{"id":77,"code":"AWO-12","quantity":30,"location_org_stock_id":5501,"version":7}]}}""",
            seenAt = Instant.parse("2026-11-20T09:00:00Z"),
            downloadedAt = Instant.parse("2026-11-20T09:01:00Z"),
        )

        val viewModel = LocationViewModel(api, FakeSessions(), warehouseId = 1, locationId = 220, offlineWork = offline, reasonsMemory = memory).also { it.onStart() }
        viewModel.loadReasons()

        assertEquals(7, viewModel.state.value.location?.orgStock(5501)?.version)
        assertEquals(Instant.parse("2026-11-20T09:01:00Z"), viewModel.state.value.copyAt)
        assertEquals(memory.reasons, viewModel.state.value.reasons)
    }

    @Test
    fun `offline move targets are the picking locations of the SKOs here`() {
        assertEquals(listOf(PlaceRef(300, "P-01")), location.knownTargets())
        assertTrue(location.copy(orgStocks = listOf(skos[0].copy(pickingLocation = PlaceRef(220, "A-03-02")))).knownTargets().isEmpty())
    }

    private val orgStock = OrgStockDetail(
        id = 77,
        code = "AWO-12",
        quantityInLocations = 36.0,
        locations = listOf(
            OrgStockLocation(locationOrgStockId = 5501, quantity = 30.0, version = 7, location = OrgStockLocationPlace(id = 220, code = "A-03-02")),
            OrgStockLocation(locationOrgStockId = 5502, quantity = 6.0, version = 3, location = OrgStockLocationPlace(id = 221, code = "B-01")),
        ),
        can = StockRights(editStocks = true),
    )

    private fun openSko(): SkoViewModel {
        api.orgStock = GoodsOutResult.Loaded(orgStock)
        return SkoViewModel(api, FakeSessions(), warehouseId = 1, orgStockId = 77, offlineWork = offline).also { it.onStart() }
    }

    @Test
    fun `offline, an SKO move and audit queue with targets and versions and show on the locations`() {
        val sko = openSko()
        offline.online.value = false

        sko.write(OrgStockWrite.Move(fromLocationOrgStockId = 5501, targets = listOf(MoveTarget(locationOrgStockId = 5502, quantity = 2.0))))
        sko.write(OrgStockWrite.Audit(listOf(AuditCount(locationOrgStockId = 5502, quantity = 9.0, reason = "found", note = null))))

        val (move, audit) = offline.queued
        assertEquals(
            buildJsonObject {
                put("location_org_stock_id", 5501)
                putJsonArray("targets") {
                    addJsonObject {
                        put("location_org_stock_id", 5502)
                        put("quantity", 2)
                    }
                }
                put("quantity", 2)
                put("client_request_id", "req-1")
            },
            move.payload,
        )
        assertEquals(OfflineOperation.AUDIT_ORG_STOCK, audit.operation)
        assertEquals(JsonPrimitive(77), audit.payload["org_stock_id"])
        assertEquals(buildJsonObject { put("5502", 3) }, audit.payload["versions"])
        assertEquals(JsonNull, ((audit.payload["counts"] as JsonArray).single() as JsonObject)["note"])
        assertTrue(offline.queued.all { it.record == skoKey })
        val shown = sko.state.value.orgStock
        assertEquals(28.0, shown?.location(5501)?.quantity ?: 0.0, 0.0)
        assertEquals(9.0, shown?.location(5502)?.quantity ?: 0.0, 0.0)
        assertEquals(37.0, shown?.quantityInLocations ?: 0.0, 0.0)
    }

    @Test
    fun `an SKO move that cannot reach Aiku is queued with the same id, consume stays online only`() {
        val sko = openSko()
        api.orgStockWriteResult = GoodsOutResult.Unreachable

        sko.write(OrgStockWrite.Move(fromLocationOrgStockId = 5501, targets = listOf(MoveTarget(locationOrgStockId = 5502, quantity = 1.0))))

        assertEquals((api.orgStockWrites.single() as OrgStockWrite.Move).clientRequestId, offline.queued.single().clientRequestId)

        offline.online.value = false
        sko.write(OrgStockWrite.Consume(locationOrgStockId = 5501, quantity = 1.0, note = null))
        assertEquals(QueueEvent.OnlineOnly, sko.state.value.queueEvent)
        assertEquals(1, api.orgStockWrites.size)
    }
}
