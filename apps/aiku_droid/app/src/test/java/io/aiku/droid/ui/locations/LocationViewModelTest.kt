package io.aiku.droid.ui.locations

import io.aiku.droid.data.api.AuditCount
import io.aiku.droid.data.api.Edited
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.LocationDetail
import io.aiku.droid.data.api.LocationOrgStock
import io.aiku.droid.data.api.LocationOrgStockUpdate
import io.aiku.droid.data.api.LocationPage
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.LocationWriteOutcome
import io.aiku.droid.data.api.LocationsOverview
import io.aiku.droid.data.api.EmptyLocationsRemoval
import io.aiku.droid.data.api.PickingChannel
import io.aiku.droid.data.api.ScanLookup
import io.aiku.droid.data.api.ScanMatch
import io.aiku.droid.data.api.ScannedLocation
import io.aiku.droid.data.api.StockLevel
import io.aiku.droid.data.api.StockRights
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeInventoryApi
import io.aiku.droid.ui.inventory.FakeSessions
import io.aiku.droid.ui.inventory.PickedLocation
import io.aiku.droid.ui.inventory.StockEvent
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class LocationViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeInventoryApi()

    private val sko = LocationOrgStock(
        id = 77,
        code = "AWO-12",
        type = "picking",
        quantity = 30.0,
        locationOrgStockId = 5501,
        isDefaultWholesale = true,
        minStock = 12.0,
        maxStock = 48.0,
        notes = "Top shelf",
    )

    private fun detail(can: StockRights? = StockRights(editStocks = true, editLocations = true), skos: List<LocationOrgStock> = listOf(sko), code: String = "A-03-02") =
        LocationDetail(summary = LocationSummary(id = 220, code = code), orgStocks = skos, can = can)

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun loaded(): LocationViewModel {
        api.location = GoodsOutResult.Loaded(detail())
        return LocationViewModel(api, FakeSessions(), warehouseId = 1, locationId = 220).also { it.onStart() }
    }

    @Test
    fun `pulling to refresh loads the location again`() {
        val location = loaded()
        api.location = GoodsOutResult.Loaded(detail(code = "A-03-03"))

        location.refresh()

        assertEquals("A-03-03", location.state.value.location?.code)
        assertFalse(location.state.value.isRefreshing)
    }

    @Test
    fun `moving and removing follow location edit, auditing follows stock edit`() {
        val none = WarehouseAbilities()

        assertEquals(listOf(LocationAction.Audit), detail(StockRights(editStocks = true, editLocations = false)).allowedActions(none))
        assertEquals(
            listOf(LocationAction.MoveAll, LocationAction.MoveSome, LocationAction.Remove),
            detail(StockRights(editStocks = false, editLocations = true)).allowedActions(WarehouseAbilities(moveStock = true)),
        )
        assertEquals(LocationAction.entries.toList(), detail().allowedActions(none))
        assertTrue(detail(skos = emptyList()).allowedActions(WarehouseAbilities(moveStock = true, editLocations = true)).isEmpty())
    }

    @Test
    fun `without a rights block the warehouse abilities decide`() {
        assertEquals(listOf(LocationAction.Audit), detail(can = null).allowedActions(WarehouseAbilities(moveStock = true)))
        assertEquals(
            listOf(LocationAction.MoveAll, LocationAction.MoveSome, LocationAction.Remove),
            detail(can = null).allowedActions(WarehouseAbilities(editLocations = true)),
        )
    }

    @Test
    fun `a write replaces the location with the answer`() {
        val location = loaded()
        val write = LocationWrite.Audit(listOf(AuditCount(locationOrgStockId = 5501, quantity = 28.0, reason = "damaged", note = null)))
        api.locationWriteResult = GoodsOutResult.Loaded(LocationWriteOutcome(location = detail(skos = listOf(sko.copy(quantity = 28.0)))))

        location.write(write)

        val state = location.state.value
        assertEquals(28.0, state.location?.orgStock(5501)?.stock ?: 0.0, 0.0)
        assertEquals(StockEvent.Done(action = write), state.event)
        assertFalse(state.isWorking)
        assertEquals(220L to write, api.locationWrites.single())
    }

    @Test
    fun `removing reports how many were removed`() {
        val location = loaded()
        val write = LocationWrite.RemoveOrgStocks(listOf(77))
        api.locationWriteResult = GoodsOutResult.Loaded(LocationWriteOutcome(location = detail(skos = emptyList()), removed = 1))

        location.write(write)

        assertEquals(StockEvent.Done(action = write, removed = 1), location.state.value.event)
        assertTrue(location.state.value.location?.orgStocks.orEmpty().isEmpty())
    }

    @Test
    fun `a refused move keeps the location and the error keys`() {
        val location = loaded()
        api.locationWriteResult = GoodsOutResult.Invalid("Only 30 in stock", mapOf("quantity" to listOf("Only 30 in stock")))

        location.write(LocationWrite.MoveAll(targetLocationId = 300, removeAfterMove = false))

        val state = location.state.value
        assertEquals(30.0, state.location?.orgStock(5501)?.stock ?: 0.0, 0.0)
        assertEquals(StockEvent.Refused("Only 30 in stock", GoodsOutProblem.ServerError), state.event)
        assertEquals(listOf("Only 30 in stock"), state.writeErrors["quantity"])
    }

    @Test
    fun `saving the settings replaces the location`() {
        val location = loaded()
        val update = LocationOrgStockUpdate(levels = mapOf(StockLevel.Min to 6.0))
        api.linkUpdateResult = GoodsOutResult.Loaded(detail(skos = listOf(sko.copy(minStock = 6.0))))

        location.update(5501, update)

        assertEquals(5501L to update, api.linkUpdates.single())
        assertEquals(6.0, location.state.value.location?.orgStock(5501)?.minStock ?: 0.0, 0.0)
        assertEquals(StockEvent.Done(action = SettingsSaved(5501)), location.state.value.event)
    }

    @Test
    fun `nothing changed sends nothing`() {
        val location = loaded()

        location.update(5501, LocationOrgStockUpdate())

        assertTrue(api.linkUpdates.isEmpty())
        assertNull(location.state.value.event)
    }

    @Test
    fun `the settings draft sends only what changed`() {
        val unchanged = SkoSettingsDraft.of(sko)
        val changed = unchanged.copy(
            type = LocationStockType.Storing,
            minStock = "6",
            maxStock = "",
            replenishmentStock = "24,5",
            notes = " ",
            makeDefaultFor = setOf(PickingChannel.Wholesale, PickingChannel.Dropshipping),
        )

        assertTrue(unchanged.update(sko)!!.isEmpty)
        assertEquals(
            LocationOrgStockUpdate(
                type = LocationStockType.Storing,
                levels = mapOf(StockLevel.Min to 6.0, StockLevel.Max to null, StockLevel.Replenishment to 24.5),
                notes = Edited(null),
                makeDefaultFor = setOf(PickingChannel.Dropshipping),
            ),
            changed.update(sko),
        )
    }

    @Test
    fun `a level that is not a number is refused before sending`() {
        val draft = SkoSettingsDraft.of(sko).copy(minStock = "twelve", maxStock = "-1")

        assertNull(draft.update(sko))
        assertEquals(setOf(StockLevel.Min, StockLevel.Max), draft.invalidLevels)
    }

    @Test
    fun `move targets are searched in this warehouse without the source, and a scan picks one`() {
        val location = loaded()
        api.locationPages = { GoodsOutResult.Loaded(LocationPage(listOf(LocationSummary(id = 220, code = "A-03-02"), LocationSummary(id = 300, code = "A-09-04")), ListMeta())) }

        location.finder.search("A-0")
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(listOf(300L), location.finder.state.value.results.map { it.id })
        assertEquals("A-0", api.locationQueries.last().search)

        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "a-09-04", type = ScanMatch.LOCATION, location = ScannedLocation(id = 300, code = "A-09-04")))
        location.finder.scan("a-09-04")

        assertEquals(PickedLocation(id = 300, code = "A-09-04"), location.finder.state.value.picked)
    }

    @Test
    fun `scanning the source location is not a target`() {
        val location = loaded()
        api.scanResult = GoodsOutResult.Loaded(ScanLookup(code = "a-03-02", type = ScanMatch.LOCATION, location = ScannedLocation(id = 220, code = "A-03-02")))

        location.finder.scan("a-03-02")

        assertNull(location.finder.state.value.picked)
        assertEquals("a-03-02", location.finder.state.value.notALocation)
    }

    @Test
    fun `the overview switch replaces the auto remove block`() {
        api.overview = GoodsOutResult.Loaded(LocationsOverview(numberLocations = 10, emptyLocationsRemoval = EmptyLocationsRemoval(enabled = false, canEdit = true)))
        api.removal = GoodsOutResult.Loaded(EmptyLocationsRemoval(enabled = true, canEdit = true, toRemove = 3))
        val overview = WarehouseOverviewViewModel(api, FakeSessions(), warehouseId = 1)
        overview.onStart()

        overview.setAutoRemove(true)

        val state = overview.state.value
        assertEquals(listOf(true), api.removalRequests)
        assertTrue(state.overview?.emptyLocationsRemoval?.enabled == true)
        assertEquals(3, state.overview?.emptyLocationsRemoval?.toRemove)
        assertEquals(StockEvent.Done(AutoRemoveChange(enabled = true)), state.event)
    }
}
