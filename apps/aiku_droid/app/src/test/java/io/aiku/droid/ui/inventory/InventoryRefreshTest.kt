package io.aiku.droid.ui.inventory

import io.aiku.droid.data.api.FamilyPage
import io.aiku.droid.data.api.FamilySko
import io.aiku.droid.data.api.FamilySkoPage
import io.aiku.droid.data.api.FamilySummary
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InventoryDashboard
import io.aiku.droid.data.api.OrgStockFamily
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class InventoryRefreshTest {

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
    fun `pulling the inventory dashboard loads it again`() {
        api.dashboard = GoodsOutResult.Loaded(InventoryDashboard(numberLocations = 10))
        val dashboard = InventoryDashboardViewModel(api, FakeSessions(), warehouseId = 1)
        dashboard.onStart()
        api.dashboard = GoodsOutResult.Loaded(InventoryDashboard(numberLocations = 12))

        dashboard.refresh()

        assertEquals(12, dashboard.state.value.dashboard?.numberLocations)
        assertFalse(dashboard.state.value.isRefreshing)
    }

    @Test
    fun `pulling the families reloads the first page`() {
        api.familyPages = { query -> GoodsOutResult.Loaded(FamilyPage(listOf(OrgStockFamily(id = 1, code = "F1")), meta(query.page, 2, 2))) }
        val families = SkoFamiliesViewModel(api, FakeSessions(), warehouseId = 1)
        families.onStart()
        families.loadMore()
        api.familyPages = { query -> GoodsOutResult.Loaded(FamilyPage(listOf(OrgStockFamily(id = 9, code = "F9")), meta(query.page, 1, 1))) }

        families.refresh()

        assertEquals(listOf(9L), families.state.value.list.rows.map { it.id })
        assertEquals(1, api.familyQueries.last().page)
        assertFalse(families.state.value.list.isRefreshing)
    }

    @Test
    fun `pulling a family reloads its SKOs`() {
        api.familySkos = GoodsOutResult.Loaded(FamilySkoPage(FamilySummary(id = 4, code = "F4"), listOf(FamilySko(id = 1)), meta(1, 1, 1)))
        val family = SkoFamilyViewModel(api, FakeSessions(), warehouseId = 1, familyId = 4)
        family.onStart()
        api.familySkos = GoodsOutResult.Loaded(FamilySkoPage(FamilySummary(id = 4, code = "F4"), listOf(FamilySko(id = 1), FamilySko(id = 2)), meta(1, 1, 2)))

        family.refresh()

        assertEquals(listOf(1L, 2L), family.state.value.list.rows.map { it.id })
        assertFalse(family.state.value.list.isRefreshing)
    }
}
