package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class WarehouseResponsesTest {

    @Test
    fun `warehouses come with what the user may do in each`() {
        val body = """
            {"data":[{"id":7,"slug":"wh1","code":"WH1","name":"Main Warehouse","organisation":{"id":3,"code":"AW","slug":"aw","name":"AW"},
            "can":{"view_locations":true,"edit_locations":false,"view_inventory":false,"move_stock":false,"view_goods_in":false,
            "book_in":false,"supervise_goods_in":true,"edit_procurement":false,"place_stock_deliveries":true,"view_goods_out":true,"pick_and_pack":true,"supervise_goods_out":false,"view_fulfilment":false,"edit_fulfilment":true}}]}
        """.trimIndent()

        val warehouse = (WarehouseResponses.parse(200, body) as WarehousesResult.Loaded).warehouses.single()

        assertEquals("Main Warehouse", warehouse.name)
        assertEquals("AW", warehouse.organisation.name)
        assertEquals("AW", warehouse.organisation.code)
        assertTrue(warehouse.can.viewGoodsOut)
        assertTrue(warehouse.can.pickAndPack)
        assertFalse(warehouse.can.viewInventory)
        assertFalse(warehouse.can.superviseGoodsOut)
        assertTrue(warehouse.can.editFulfilment)
        assertTrue(warehouse.can.superviseGoodsIn)
        assertFalse(warehouse.can.editProcurement)
        assertTrue(warehouse.can.placeStockDeliveries)
    }

    @Test
    fun `abilities the server does not send yet default to not allowed`() {
        val body = """{"data":[{"id":7,"slug":"wh1","code":"WH1","name":"Main","organisation":{"id":3,"slug":"aw","name":"AW"},"can":{"view_goods_out":true}}]}"""

        val can = (WarehouseResponses.parse(200, body) as WarehousesResult.Loaded).warehouses.single().can

        assertTrue(can.viewGoodsOut)
        assertFalse(can.pickAndPack)
        assertFalse(can.viewFulfilment)
        assertFalse(can.editFulfilment)
        assertFalse(can.superviseGoodsIn)
        assertFalse(can.editProcurement)
        assertFalse(can.placeStockDeliveries)
    }

    @Test
    fun `an expired or refused token ends the session and a missing key is reported`() {
        assertEquals(WarehousesResult.SessionExpired, WarehouseResponses.parse(401, ""))
        assertEquals(WarehousesResult.SessionExpired, WarehouseResponses.parse(403, ""))
        assertEquals(WarehousesResult.AppKeyRejected, WarehouseResponses.parse(404, ""))
        assertEquals(WarehousesResult.ServerError(500), WarehouseResponses.parse(500, "<html></html>"))
    }
}
