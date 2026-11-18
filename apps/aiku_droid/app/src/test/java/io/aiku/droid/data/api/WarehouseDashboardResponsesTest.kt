package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class WarehouseDashboardResponsesTest {

    private fun loaded(body: String): WarehouseDashboard = (WarehouseDashboardResponses.parseDashboard(200, body) as GoodsOutResult.Loaded).value

    @Test
    fun `the dashboard reads every block`() {
        val body = """{"data":{
            "generated_at":"2026-11-18T09:00:00+00:00",
            "goods_out":{"unassigned":47,"handling":17,"handling_blocked":2,"picked":9,"packed":26,"in_warehouse":92,
                         "dispatched_today":{"count":142,"yesterday_by_now":130,"same_day_last_week":151}},
            "goods_in":{"arriving":3,"received":2,"checked":1,"booking_in":1,"returns_to_process":4},
            "inventory":{"number_current_org_stocks":4812,"below_minimum_stock":17,"negative_stocks":5,"number_abnormalities":3}
            }}"""

        val dashboard = loaded(body)

        assertEquals("2026-11-18T09:00:00+00:00", dashboard.generatedAt)
        assertEquals(
            DashboardGoodsOut(
                unassigned = 47,
                handling = 17,
                handlingBlocked = 2,
                picked = 9,
                packed = 26,
                inWarehouse = 92,
                dispatchedToday = DispatchedToday(count = 142, yesterdayByNow = 130, sameDayLastWeek = 151),
            ),
            dashboard.goodsOut,
        )
        assertEquals(DashboardGoodsIn(arriving = 3, received = 2, checked = 1, bookingIn = 1, returnsToProcess = 4), dashboard.goodsIn)
        assertEquals(DashboardInventory(numberCurrentOrgStocks = 4812, belowMinimumStock = 17, negativeStocks = 5, numberAbnormalities = 3), dashboard.inventory)
    }

    @Test
    fun `a block without the right is null and the others stay`() {
        val body = """{"data":{"generated_at":null,"goods_out":null,
            "goods_in":{"arriving":"3","received":null,"checked":1,"booking_in":1,"returns_to_process":null},
            "inventory":null}}"""

        val dashboard = loaded(body)

        assertNull(dashboard.generatedAt)
        assertNull(dashboard.goodsOut)
        assertNull(dashboard.inventory)
        assertEquals(DashboardGoodsIn(arriving = 3, received = 0, checked = 1, bookingIn = 1, returnsToProcess = null), dashboard.goodsIn)
    }

    @Test
    fun `missing figures read as zero and a missing comparison as null`() {
        val dashboard = loaded("""{"data":{"goods_out":{"unassigned":5,"dispatched_today":{"count":"12","yesterday_by_now":null}}}}""")

        assertEquals(DashboardGoodsOut(unassigned = 5, dispatchedToday = DispatchedToday(count = 12)), dashboard.goodsOut)
        assertNull(dashboard.goodsIn)
    }

    @Test
    fun `to pack is null when an older server leaves picked out or sends null`() {
        assertNull(loaded("""{"data":{"goods_out":{"unassigned":5,"packed":2}}}""").goodsOut?.picked)
        assertNull(loaded("""{"data":{"goods_out":{"unassigned":5,"picked":null}}}""").goodsOut?.picked)
        assertEquals(0, loaded("""{"data":{"goods_out":{"picked":"0"}}}""").goodsOut?.picked)
    }

    @Test
    fun `a block that is not an object is left out`() {
        val dashboard = loaded("""{"data":{"goods_out":[1,2],"goods_in":"soon","inventory":{"negative_stocks":5}}}""")

        assertNull(dashboard.goodsOut)
        assertNull(dashboard.goodsIn)
        assertEquals(DashboardInventory(negativeStocks = 5), dashboard.inventory)
    }

    @Test
    fun `the dashboard maps failures`() {
        assertEquals(GoodsOutResult.SessionExpired, WarehouseDashboardResponses.parseDashboard(401, """{"message":"Unauthenticated."}"""))
        assertTrue(WarehouseDashboardResponses.parseDashboard(403, """{"message":"This action is unauthorized."}""") is GoodsOutResult.Forbidden)
        assertTrue(WarehouseDashboardResponses.parseDashboard(404, """{"message":"Not found"}""") is GoodsOutResult.NotFound)
        assertEquals(GoodsOutResult.ServerError(200), WarehouseDashboardResponses.parseDashboard(200, "not json"))
        assertEquals(GoodsOutResult.ServerError(503), WarehouseDashboardResponses.parseDashboard(503, ""))
    }
}
