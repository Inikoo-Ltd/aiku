package io.aiku.droid.ui.locations

import io.aiku.droid.data.api.EmptyLocationsRemoval
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.LocationsOverview
import io.aiku.droid.data.api.WarehouseArea
import io.aiku.droid.ui.inventory.FakeInventoryApi
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class LocationsRefreshTest {

    private val api = FakeInventoryApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `pulling the warehouse overview loads it again`() {
        api.overview = GoodsOutResult.Loaded(LocationsOverview(numberLocations = 10))
        val overview = WarehouseOverviewViewModel(api, FakeSessions(), warehouseId = 1)
        overview.onStart()
        api.overview = GoodsOutResult.Loaded(LocationsOverview(numberLocations = 11))

        overview.refresh()

        assertEquals(11, overview.state.value.overview?.numberLocations)
        assertFalse(overview.state.value.isRefreshing)
    }

    @Test
    fun `pulling the overview does nothing while the auto remove switch is being saved`() {
        api.overview = GoodsOutResult.Loaded(LocationsOverview(numberLocations = 10, emptyLocationsRemoval = EmptyLocationsRemoval(enabled = false, canEdit = true)))
        api.removal = GoodsOutResult.Loaded(EmptyLocationsRemoval(enabled = true, canEdit = true))
        val overview = WarehouseOverviewViewModel(api, FakeSessions(), warehouseId = 1)
        overview.onStart()
        val gate = CompletableDeferred<Unit>()
        api.removalGate = gate

        overview.setAutoRemove(true)
        overview.refresh()

        assertEquals(1, api.overviewRequests)
        assertFalse(overview.state.value.isRefreshing)

        gate.complete(Unit)

        assertTrue(overview.state.value.overview?.emptyLocationsRemoval?.enabled == true)
    }

    @Test
    fun `pulling the areas loads them again with the current search`() {
        api.areas = GoodsOutResult.Loaded(listOf(WarehouseArea(id = 1, code = "A")))
        val areas = AreasViewModel(api, FakeSessions(), warehouseId = 1)
        areas.onStart()
        api.areas = GoodsOutResult.Loaded(listOf(WarehouseArea(id = 1, code = "A"), WarehouseArea(id = 2, code = "B")))

        areas.refresh()

        assertEquals(listOf(1L, 2L), areas.state.value.areas?.map { it.id })
        assertFalse(areas.state.value.isRefreshing)
        assertEquals(2, api.areaSearches.size)
    }
}
