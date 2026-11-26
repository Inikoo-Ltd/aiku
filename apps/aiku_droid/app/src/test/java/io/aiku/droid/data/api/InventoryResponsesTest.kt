package io.aiku.droid.data.api

import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class InventoryResponsesTest {

    private val summaryJson = """
        "id":220,"slug":"a-03-02","code":"A-03-02","status":"operational","area":{"id":3,"code":"A","name":"Area A"},
        "allow_stocks":true,"allow_fulfilment":false,"allow_dropshipping":true,"is_goods_out":false,"keep_empty_org_stocks":false,
        "max_weight":"120.5","max_volume":1.2,"number_org_stock_slots":4,"number_empty_stock_slots":"1","number_pallets":0,
        "stock_value":"812.40","audited_at":null,"barcode":"a-03-02","last_audited_at":"2026-10-28T09:12:00+00:00"
    """.trimIndent()

    private val locationJson = """
        {$summaryJson,
        "pallets":[{"id":9,"slug":"p-9","reference":"PAL-9","customer_reference":"C-1","state":"storing","state_label":"Storing","status":"storing","status_label":"Storing","type":"pallet","customer_name":"Jane Gifts"}],
        "org_stocks":[
          {"id":77,"code":"AWO-12","name":"Lavender oil","slug":"awo-12","sku_value":null,"type":"picking","picking_priority":1,"value":null,"dropshipping_pipe":null,
           "quantity":"30","notes":"Top shelf","location_org_stock_id":5501,"packed_at":null,"packed_by":null,"packed_quantity":null,"un_numbers":[],"is_packed":false,
           "default_wholesale_picking_location":true,"default_dropshipping_picking_location":false,"min_stock":"12","max_stock":48,"replenishment_stock":null,
           "audited_at":"2026-10-28T09:12:00+00:00","image_url":"https://media.aiku.io/77.jpg","picking_location":{"id":220,"code":"A-03-02"},
           "picking_dropshipping_location":{"id":221,"code":"B-02-01"},"quantity_value":30},
          {"id":78,"code":"AWO-13","name":"Rose oil","type":"storing","quantity":"1.5","location_org_stock_id":5502,"min_stock":null,"max_stock":null,"replenishment_stock":"24.5"},
          {"id":79,"code":"AWO-14","name":"Lost","type":"storing","quantity":"","location_org_stock_id":5503}
        ],
        "can":{"edit_stocks":true,"edit_locations":false}}
    """.trimIndent()

    private val detailJson = """
        {"id":77,"code":"AWO-12","name":"Lavender oil 10ml","state":"active","state_label":"Active",
         "family":{"id":5,"code":"AWO","name":"Aromatherapy oils"},"image_url":null,"barcode":"5060","unit_barcode":"5061","packed_in":6,
         "is_on_demand":false,"note_to_pickers":"Fragile","note_to_packers":null,"currency_code":"GBP","stock_value":120.5,
         "quantity_in_locations":30.5,"quantity_in_locations_label":"30 1/2","quantity_reserved":4,"quantity_reserved_label":"4",
         "quantity_to_be_picked":"2","quantity_to_be_picked_label":"2","cover":{"days":41,"out_at":"2026-12-27T00:00:00+00:00","daily_usage":0.7},
         "picking_location":{"id":220,"code":"A-01-03"},"picking_dropshipping_location":null,
         "locations":[{"location_org_stock_id":5501,"type":"picking","quantity":30,"quantity_label":"30","picking_priority":1,"notes":null,
            "min_stock":12,"max_stock":48,"replenishment_stock":null,"is_default_wholesale":true,"is_default_dropshipping":false,"audited_at":null,
            "location":{"id":220,"code":"A-01-03","status":"operational","allow_stocks":true,"area":{"id":3,"code":"A","name":"Area A"}}}],
         "can":{"edit_stocks":true,"edit_locations":false}}
    """.trimIndent()

    @Test
    fun `the overview reads its tiles, the auto remove block, utilisation and currency`() {
        val body = """{"data":{"warehouse":{"id":1,"code":"WH1","name":"Main"},"number_areas":6,"number_locations":"1284","number_locations_operational":1200,
            "number_locations_broken":4,"number_locations_empty":180,"number_locations_partially_empty":96,"stock_value":"412345.6",
            "empty_locations_removal":{"enabled":true,"can_edit":false,"last_change":{"by":"Ana","at":"2026-11-02T10:00:00+00:00"},"to_remove":37,"removed_last_7_days":"214","max_per_run":500},
            "utilisation":0.86,"currency_code":"GBP"}}"""

        val overview = (InventoryResponses.parseOverview(200, body) as GoodsOutResult.Loaded).value

        assertEquals(1284, overview.numberLocations)
        assertEquals(180, overview.numberLocationsEmpty)
        assertEquals(412345.6, overview.stockValue, 0.0)
        assertEquals(0.86, overview.utilisation!!, 0.0)
        assertEquals("GBP", overview.currencyCode)
        assertTrue(overview.emptyLocationsRemoval.enabled)
        assertFalse(overview.emptyLocationsRemoval.canEdit)
        assertEquals("Ana", overview.emptyLocationsRemoval.lastChange?.by)
        assertEquals(214, overview.emptyLocationsRemoval.removedLastSevenDays)
    }

    @Test
    fun `an overview without the new fields still loads`() {
        val overview = (InventoryResponses.parseOverview(200, """{"data":{"number_areas":1,"empty_locations_removal":{"enabled":false}}}""") as GoodsOutResult.Loaded).value

        assertNull(overview.utilisation)
        assertNull(overview.currencyCode)
        assertNull(overview.emptyLocationsRemoval.lastChange)
    }

    @Test
    fun `the auto remove switch answers with the block`() {
        val body = """{"data":{"enabled":false,"can_edit":true,"last_change":null,"to_remove":0,"removed_last_7_days":3,"max_per_run":500}}"""

        val removal = (InventoryResponses.parseEmptyLocationsRemoval(200, body) as GoodsOutResult.Loaded).value

        assertFalse(removal.enabled)
        assertTrue(removal.canEdit)
        assertEquals(3, removal.removedLastSevenDays)
    }

    @Test
    fun `areas are a plain list with a nullable picking order`() {
        val body = """{"data":[{"id":3,"slug":"a","code":"A","name":"Area A","picking_position":null,"number_locations":"412","stock_value":"96000.5","number_empty_locations":12},
            {"id":4,"slug":"b","code":"B","name":"","picking_position":2.5,"number_locations":280,"stock_value":61000,"number_empty_locations":"44"}]}"""

        val areas = (InventoryResponses.parseAreas(200, body) as GoodsOutResult.Loaded).value

        assertNull(areas[0].pickingPosition)
        assertEquals(412, areas[0].numberLocations)
        assertEquals(96000.5, areas[0].stockValue, 0.0)
        assertEquals(2.5, areas[1].pickingPosition!!, 0.0)
        assertEquals(44, areas[1].numberEmptyLocations)
        assertEquals("B", areas[1].label)
    }

    @Test
    fun `the location list uses meta and reads the summary leniently`() {
        val body = """{"data":[{$summaryJson}],"meta":{"current_page":2,"last_page":5,"per_page":50,"total":212}}"""

        val page = (InventoryResponses.parseLocations(200, body) as GoodsOutResult.Loaded).value
        val location = page.locations.single()

        assertEquals(ListMeta(currentPage = 2, lastPage = 5, perPage = 50, total = 212), page.meta)
        assertEquals(220L, location.id)
        assertEquals(120.5, location.maxWeight!!, 0.0)
        assertEquals(1, location.numberEmptyStockSlots)
        assertEquals(812.4, location.stockValue, 0.0)
        assertEquals("a-03-02", location.barcode)
        assertEquals("2026-10-28T09:12:00+00:00", location.lastAuditedAt)
        assertEquals("Area A", location.area?.label)
        assertFalse(location.isBroken)
    }

    @Test
    fun `the location detail reads string quantities, settings, pick locations and rights`() {
        val detail = (InventoryResponses.parseLocation(200, """{"data":$locationJson}""") as GoodsOutResult.Loaded).value
        val (first, second, third) = detail.orgStocks

        assertEquals("A-03-02", detail.code)
        assertEquals(StockRights(editStocks = true, editLocations = false), detail.can)
        assertEquals("Jane Gifts", detail.pallets.single().customerName)
        assertEquals(30.0, first.stock, 0.0)
        assertEquals(12.0, first.minStock!!, 0.0)
        assertEquals(48.0, first.maxStock!!, 0.0)
        assertTrue(first.isDefaultWholesale)
        assertEquals(LocationStockType.Picking, first.stockType)
        assertEquals(PlaceRef(id = 221, code = "B-02-01"), first.pickingDropshippingLocation)
        assertEquals("https://media.aiku.io/77.jpg", first.imageUrl)
        assertEquals(1.5, second.stock, 0.0)
        assertEquals(24.5, second.replenishmentStock!!, 0.0)
        assertEquals(0.0, third.stock, 0.0)
        assertEquals(5503L, detail.orgStock(5503)?.locationOrgStockId)
    }

    @Test
    fun `a location without a rights block leaves the rights to the warehouse`() {
        val detail = (InventoryResponses.parseLocation(200, """{"data":{"id":1,"code":"X"}}""") as GoodsOutResult.Loaded).value

        assertNull(detail.can)
        assertTrue(detail.orgStocks.isEmpty())
    }

    @Test
    fun `location writes answer with the location, and removal with the count next to it`() {
        val audited = (InventoryResponses.parseLocationWrite(200, """{"data":$locationJson}""") as GoodsOutResult.Loaded).value
        val removed = (InventoryResponses.parseLocationWrite(200, """{"removed":2,"data":$locationJson}""") as GoodsOutResult.Loaded).value

        assertNull(audited.removed)
        assertEquals(220L, audited.location.id)
        assertEquals(2, removed.removed)
    }

    @Test
    fun `the location movements read string quantities and the user name`() {
        val body = """{"data":[{"id":91,"date":"2026-11-12T10:22:00.000000Z","type":"audit","type_label":"Audit","flow":"audit","quantity":"-2",
            "audited_quantity":"28","running_quantity":"28","reason":"damaged","reason_label":"Damaged Stock","note":null,"org_stock_name":"Lavender oil",
            "org_stock_slug":"awo-12","parent_type":null,"parent_reference":null,"org_stock_code":"AWO-12","user_name":"Jo K."}],
            "meta":{"current_page":1,"last_page":1,"per_page":50,"total":1}}"""

        val page = (InventoryResponses.parseMovements(200, body) as GoodsOutResult.Loaded).value
        val movement = page.movements.single()

        assertEquals(-2.0, movement.quantity, 0.0)
        assertEquals(28.0, movement.running!!, 0.0)
        assertEquals("AWO-12", movement.orgStockCode)
        assertEquals("Jo K.", movement.who)
        assertEquals(1, page.meta.total)
    }

    @Test
    fun `the SKO movements read numbers, labels, the location and the user`() {
        val body = """{"data":[{"id":92,"date":"2026-11-12T10:08:00+00:00","type":"picked","type_label":"Picked","flow":"out","quantity":-6,"quantity_label":"6",
            "running_quantity_org_stock":36,"location":{"id":220,"code":"A-03-02"},"reason":null,"reason_label":null,"note":null,
            "parent_type":"DeliveryNote","parent_reference":"AR24790","user":{"id":4,"name":"Sam P."}}],"meta":{"current_page":1,"last_page":3,"per_page":50,"total":130}}"""

        val movement = (InventoryResponses.parseMovements(200, body) as GoodsOutResult.Loaded).value.movements.single()

        assertEquals("A-03-02", movement.location?.code)
        assertEquals("Sam P.", movement.who)
        assertEquals(36.0, movement.running!!, 0.0)
        assertEquals("AR24790", movement.parentReference)
    }

    @Test
    fun `the reasons keep their order and survive an empty list`() {
        val body = """{"data":{"increase":{"count_gain":"Count Gain (Stock Found)","recount":"Recount Correction"},"decrease":{"count_short":"Count Shortage"},"transfer":[]}}"""

        val reasons = (InventoryResponses.parseReasons(200, body) as GoodsOutResult.Loaded).value
        val empty = (InventoryResponses.parseReasons(200, """{"data":{"increase":[],"decrease":[]}}""") as GoodsOutResult.Loaded).value

        assertEquals(listOf("count_gain", "recount"), reasons.increase.map { it.key })
        assertEquals("Count Shortage", reasons.decrease.single().label)
        assertTrue(empty.increase.isEmpty())
    }

    @Test
    fun `the dashboard reads every figure`() {
        val body = """{"data":{"currency_code":"GBP","stock_value":120345.5,"number_locations":812,"number_empty_locations":97,"utilisation":0.88,
            "number_current_org_stocks":2345,"number_abnormalities":3,"low_stock_audits":12,"replenishments_wholesale":9,"replenishments_dropshipping":4,
            "below_minimum_stock":7,"number_with_minimum_stock":410,"negative_stocks":2,"wrong_weights":5,"without_products":11}}"""

        val dashboard = (InventoryResponses.parseDashboard(200, body) as GoodsOutResult.Loaded).value

        assertEquals(715, dashboard.usedLocations)
        assertEquals(4, dashboard.replenishmentsDropshipping)
        assertEquals(410, dashboard.numberWithMinimumStock)
        assertEquals(11, dashboard.withoutProducts)
        assertEquals(0.88, dashboard.utilisation!!, 0.0)
    }

    @Test
    fun `the SKO list reads rows, meta, counts and currency`() {
        val body = """{"data":[{"id":77,"slug":"awo-12","code":"AWO-12","name":"Lavender","state":"abnormality","state_label":"Abnormality",
            "family":{"id":5,"code":"AWO"},"image_url":null,"quantity_available":"12.5","quantity_available_label":"12 1/2","quantity_in_locations":30,
            "quantity_in_locations_label":"30","stock_value":120.5,"number_locations":2,"last_seen_at":"2026-11-01T00:00:00+00:00"}],
            "meta":{"current_page":1,"last_page":3,"per_page":50,"total":120},"counts":{"current":2345,"discontinued":120,"abnormality":3,"all":2468},"currency_code":"GBP"}"""

        val page = (InventoryResponses.parseOrgStocks(200, body) as GoodsOutResult.Loaded).value
        val row = page.rows.single()

        assertEquals(12.5, row.quantityAvailable, 0.0)
        assertEquals("12 1/2", row.quantityAvailableLabel)
        assertEquals("2026-11-01T00:00:00+00:00", row.lastSeenAt)
        assertEquals(3, page.counts?.count(SkoBucket.Abnormality))
        assertEquals(2468, page.counts?.count(SkoBucket.All))
        assertNull(page.counts?.count(SkoBucket.BelowMinimum))
        assertNull(page.counts?.count(SkoBucket.Negative))
        assertEquals(120, page.meta.total)
        assertEquals("GBP", page.currencyCode)
    }

    @Test
    fun `a negative stock row reads its location and that location's quantity`() {
        val body = """{"data":[
            {"id":77,"code":"AWO-12","name":"Lavender","state":"active","quantity_available":10,"number_locations":2,
             "location_org_stock_id":5501,"location":{"id":220,"code":"A-03-02"},"location_quantity":"-4"},
            {"id":77,"code":"AWO-12","name":"Lavender","state":"active","quantity_available":10,"number_locations":2,
             "location_org_stock_id":5502,"location":{"id":221,"code":"B-01-01"},"location_quantity":-0.5}],
            "meta":{"current_page":1,"last_page":1,"per_page":50,"total":2},"counts":{"current":2345,"discontinued":120,"abnormality":3,"all":2468,"negative":2}}"""

        val page = (InventoryResponses.parseOrgStocks(200, body) as GoodsOutResult.Loaded).value
        val (first, second) = page.rows

        assertEquals(5501L, first.locationOrgStockId)
        assertEquals(PlaceRef(id = 220, code = "A-03-02"), first.location)
        assertEquals(-4.0, first.locationQuantity!!, 0.0)
        assertEquals(5502L, second.locationOrgStockId)
        assertEquals(PlaceRef(id = 221, code = "B-01-01"), second.location)
        assertEquals(-0.5, second.locationQuantity!!, 0.0)
        assertEquals(2, page.meta.total)
        assertEquals(2, page.counts?.count(SkoBucket.Negative))
    }

    @Test
    fun `an SKO row without a location, or with null location keys, reads none`() {
        val body = """{"data":[
            {"id":77,"code":"AWO-12","name":"Lavender","quantity_available":10},
            {"id":78,"code":"AWO-13","name":"Rose","quantity_available":3,"location_org_stock_id":null,"location":null,"location_quantity":null}],
            "meta":{"current_page":1,"last_page":1,"per_page":50,"total":2}}"""

        val rows = (InventoryResponses.parseOrgStocks(200, body) as GoodsOutResult.Loaded).value.rows

        rows.forEach { row ->
            assertNull(row.locationOrgStockId)
            assertNull(row.location)
            assertNull(row.locationQuantity)
        }
        assertEquals(listOf(77L, 78L), rows.map { it.id })
    }

    @Test
    fun `the SKO list reads the below minimum and negative counts`() {
        val body = """{"data":[],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":17},
            "counts":{"current":2345,"discontinued":120,"abnormality":3,"below_minimum":17,"negative":"5","all":2468}}"""

        val counts = (InventoryResponses.parseOrgStocks(200, body) as GoodsOutResult.Loaded).value.counts

        assertEquals(17, counts?.count(SkoBucket.BelowMinimum))
        assertEquals(5, counts?.count(SkoBucket.Negative))
    }

    @Test
    fun `the SKO detail reads the summary, cover, pick locations, locations and rights`() {
        val detail = (InventoryResponses.parseOrgStock(200, """{"data":$detailJson}""") as GoodsOutResult.Loaded).value
        val location = detail.locations.single()

        assertEquals(6.0, detail.packedIn!!, 0.0)
        assertEquals(2.0, detail.quantityToBePicked, 0.0)
        assertEquals("30 1/2", detail.quantityInLocationsLabel)
        assertEquals(41.0, detail.cover.days!!, 0.0)
        assertEquals("A-01-03", detail.pickingLocation?.code)
        assertNull(detail.pickingDropshippingLocation)
        assertEquals(LocationStockType.Picking, location.stockType)
        assertEquals("Area A", location.location.area?.label)
        assertTrue(location.isDefaultWholesale)
        assertEquals(StockRights(editStocks = true, editLocations = false), detail.can)
    }

    @Test
    fun `an SKO write carries the re-read SKO and the result`() {
        val body = """{"data":$detailJson,"result":{"status":"done","message":"Audit saved for AWO-12"}}"""

        val outcome = (InventoryResponses.parseOrgStockWrite(200, body) as GoodsOutResult.Loaded).value

        assertEquals(77L, outcome.orgStock.id)
        assertEquals("done", outcome.status)
        assertEquals("Audit saved for AWO-12", outcome.message)
    }

    @Test
    fun `families read counts, meta and the cover in months`() {
        val body = """{"data":[{"id":5,"state":"active","slug":"bb","code":"BB","name":"Bath bombs","number_current_org_stocks":"42","number_out_of_stock_org_stocks":1,
            "currency_code":"GBP","stock_value":"3900.4","potential_sales":null,"on_the_way_po_value":0,"on_the_way_po_count":0,"stock_turn":null,"stock_cover":"6.5"}],
            "meta":{"current_page":1,"last_page":1,"per_page":50,"total":1},"counts":{"in_process":3,"active":214,"discontinuing":6,"discontinued":0}}"""

        val page = (InventoryResponses.parseFamilies(200, body) as GoodsOutResult.Loaded).value
        val family = page.families.single()

        assertEquals(42, family.numberCurrentOrgStocks)
        assertEquals(6.5, family.stockCover!!, 0.0)
        assertEquals(3, page.counts?.count(FamilyState.InProcess))
        assertEquals(214, page.counts?.count(FamilyState.Active))
    }

    @Test
    fun `a family's SKOs come with the family block`() {
        val body = """{"family":{"id":5,"code":"BB","name":"Bath bombs","state":"active","number_current_org_stocks":42,"stock_value":3900.5,"currency_code":"GBP"},
            "data":[{"id":77,"slug":"bb-1","code":"BB-1","name":"Rose","quantity_in_locations":"36","quantity_available":"30","stock_value":12,"number_locations":null,"stock_cover":null,"woc":null,"state":"active"}],
            "meta":{"current_page":1,"last_page":1,"per_page":50,"total":1}}"""

        val page = (InventoryResponses.parseFamilySkos(200, body) as GoodsOutResult.Loaded).value

        assertEquals("BB", page.family?.code)
        assertEquals(30.0, page.skos.single().quantityAvailable, 0.0)
        assertEquals(1, page.meta.total)
    }

    @Test
    fun `refusals map to their kinds and keep the error keys`() {
        val forbidden = InventoryResponses.parseOrgStockWrite(403, """{"message":"You can not edit stock"}""")
        val invalid = InventoryResponses.parseOrgStockWrite(422, """{"message":"The reason is wrong","errors":{"counts.0.reason":["Choose a decrease reason"]}}""")
        val missing = InventoryResponses.parseLocation(404, """{"message":"Not found"}""")

        assertEquals(GoodsOutResult.Forbidden("You can not edit stock"), forbidden)
        assertEquals(mapOf("counts.0.reason" to listOf("Choose a decrease reason")), (invalid as GoodsOutResult.Invalid).errors)
        assertTrue(missing is GoodsOutResult.NotFound)
        assertEquals(GoodsOutResult.SessionExpired, InventoryResponses.parseLocations(401, ""))
        assertEquals(GoodsOutResult.ServerError(200), InventoryResponses.parseOrgStock(200, "not json"))
    }

    @Test
    fun `the location list sends tabs and filters the way the endpoint takes them`() {
        val query = LocationQuery(
            tab = LocationTab.Empty,
            search = " a-0 ",
            areaIds = setOf(4, 3),
            statuses = setOf(LocationStatusFilter.Broken),
            scopes = setOf(LocationScope.Dropshipping, LocationScope.Stocks),
            occupancies = setOf(LocationOccupancy.KeepEmptyOrgStocks),
            sort = LocationSort.StockValue,
            page = 2,
            perPage = 50,
        )

        assertEquals(
            listOf(
                "occupancy" to "empty",
                "q" to "a-0",
                "area_ids[]" to "3",
                "area_ids[]" to "4",
                "statuses[]" to "broken",
                "scopes[]" to "stocks",
                "scopes[]" to "dropshipping",
                "occupancies[]" to "keep_empty_org_stocks",
                "sort" to "-stock_value",
                "page" to "2",
                "per_page" to "50",
            ),
            InventoryResponses.locationsParameters(query),
        )
        assertEquals(listOf("area_id" to "3", "page" to "1", "per_page" to "50"), InventoryResponses.locationsParameters(LocationQuery(areaId = 3)))
    }

    @Test
    fun `the SKO list and movements send their query`() {
        assertEquals(
            listOf("bucket" to "abnormality", "family_id" to "5", "q" to "awo", "sort" to "quantity_available", "page" to "1", "per_page" to "50"),
            InventoryResponses.orgStocksParameters(SkoQuery(bucket = SkoBucket.Abnormality, familyId = 5, search = "awo", sort = SkoSort.StockLowFirst)),
        )
        assertEquals(listOf("bucket" to "below_minimum", "page" to "1", "per_page" to "50"), InventoryResponses.orgStocksParameters(SkoQuery(bucket = SkoBucket.BelowMinimum)))
        assertEquals(listOf("bucket" to "negative", "page" to "1", "per_page" to "50"), InventoryResponses.orgStocksParameters(SkoQuery(bucket = SkoBucket.Negative)))
        assertEquals(listOf("flow" to "audit", "page" to "2", "per_page" to "50"), InventoryResponses.movementParameters(MovementFlow.Audit, 2, 50))
        assertEquals(listOf("page" to "1", "per_page" to "50"), InventoryResponses.movementParameters(null, 1, 50))
        assertEquals(listOf("state" to "in_process", "page" to "1", "per_page" to "50"), InventoryResponses.familiesParameters(FamilyQuery(state = FamilyState.InProcess)))
    }

    @Test
    fun `a location audit sends recount for an unchanged count and the chosen reason otherwise`() {
        val body = InventoryResponses.locationWriteBody(
            LocationWrite.Audit(
                listOf(
                    AuditCount(locationOrgStockId = 5501, quantity = 30.0, reason = null, note = " "),
                    AuditCount(locationOrgStockId = 5502, quantity = 1.5, reason = "damaged", note = "Wet box"),
                ),
            ),
        )
        val counts = body["counts"]!!.jsonArray.map { it.jsonObject }

        assertEquals("audit", InventoryResponses.locationWritePath(LocationWrite.Audit(emptyList())))
        assertEquals(JsonPrimitive("recount"), counts[0]["reason"])
        assertEquals(JsonPrimitive(30L), counts[0]["quantity"])
        assertEquals(JsonNull, counts[0]["note"])
        assertEquals(JsonPrimitive("damaged"), counts[1]["reason"])
        assertEquals(JsonPrimitive(1.5), counts[1]["quantity"])
        assertEquals(JsonPrimitive("Wet box"), counts[1]["note"])
    }

    @Test
    fun `an SKO audit leaves the reason out of an unchanged count`() {
        val body = InventoryResponses.orgStockWriteBody(OrgStockWrite.Audit(listOf(AuditCount(locationOrgStockId = 5501, quantity = 30.0, reason = null, note = null))))

        assertEquals(JsonNull, body["counts"]!!.jsonArray.single().jsonObject["reason"])
    }

    @Test
    fun `location moves always send the transfer reason`() {
        val all = InventoryResponses.locationWriteBody(LocationWrite.MoveAll(targetLocationId = 300, removeAfterMove = true))
        val some = InventoryResponses.locationWriteBody(LocationWrite.MoveSome(targetLocationId = 300, items = listOf(MoveSomeItem(orgStockId = 77, quantity = 2.5, removeAfterMove = false))))
        val remove = InventoryResponses.locationWriteBody(LocationWrite.RemoveOrgStocks(listOf(77, 78)))

        assertEquals(JsonObject(mapOf("location_id" to JsonPrimitive(300L), "remove_after_move" to JsonPrimitive(true), "reason" to JsonPrimitive("transfer"))), all)
        assertEquals("move-all", InventoryResponses.locationWritePath(LocationWrite.MoveAll(1, false)))
        assertEquals("move-some", InventoryResponses.locationWritePath(LocationWrite.MoveSome(1, emptyList())))
        assertEquals(JsonPrimitive("transfer"), some["reason"])
        val item = some["org_stocks"]!!.jsonArray.single().jsonObject
        assertEquals(JsonPrimitive(77L), item["org_stock_id"])
        assertEquals(JsonPrimitive(2.5), item["quantity"])
        assertEquals(JsonPrimitive(false), item["remove_after_move"])
        assertEquals(JsonArray(listOf(JsonPrimitive(77L), JsonPrimitive(78L))), remove["org_stock_ids"])
        assertEquals("remove-org-stocks", InventoryResponses.locationWritePath(LocationWrite.RemoveOrgStocks(emptyList())))
    }

    @Test
    fun `a link update sends only what changed and a default only as true`() {
        val body = InventoryResponses.locationOrgStockUpdateBody(
            LocationOrgStockUpdate(
                type = LocationStockType.Storing,
                levels = mapOf(StockLevel.Max to null, StockLevel.Min to 6.0),
                notes = Edited(null),
                makeDefaultFor = setOf(PickingChannel.Dropshipping),
            ),
        )
        val untouched = InventoryResponses.locationOrgStockUpdateBody(LocationOrgStockUpdate(makeDefaultFor = setOf(PickingChannel.Wholesale)))

        assertEquals(listOf("type", "min_stock", "max_stock", "notes", "set_as_priority_dropshipping"), body.keys.toList())
        assertEquals("storing", body["type"]!!.jsonPrimitive.content)
        assertEquals(JsonPrimitive(6L), body["min_stock"])
        assertEquals(JsonNull, body["max_stock"])
        assertEquals(JsonNull, body["notes"])
        assertEquals(JsonPrimitive(true), body["set_as_priority_dropshipping"])
        assertEquals(JsonObject(mapOf("set_as_priority_wholesale" to JsonPrimitive(true))), untouched)
    }

    @Test
    fun `SKO writes go to their paths with their bodies`() {
        val move = OrgStockWrite.Move(fromLocationOrgStockId = 5501, targets = listOf(MoveTarget(locationOrgStockId = 5502, quantity = 12.0)))
        val consume = OrgStockWrite.Consume(locationOrgStockId = 5501, quantity = 0.5, note = " Showroom ")
        val add = OrgStockWrite.AddLocation(locationId = 300, type = LocationStockType.Picking)
        val remove = OrgStockWrite.RemoveLocations(listOf(5502))

        assertEquals("warehouses/1/org-stocks/77/audit", InventoryResponses.orgStockWritePath(1, 77, OrgStockWrite.Audit(emptyList())))
        assertEquals("location-org-stocks/5501/move", InventoryResponses.orgStockWritePath(1, 77, move))
        assertEquals("location-org-stocks/5501/consume", InventoryResponses.orgStockWritePath(1, 77, consume))
        assertEquals("warehouses/1/org-stocks/77/locations", InventoryResponses.orgStockWritePath(1, 77, add))
        assertEquals("warehouses/1/org-stocks/77/remove-locations", InventoryResponses.orgStockWritePath(1, 77, remove))
        val target = InventoryResponses.orgStockWriteBody(move)["targets"]!!.jsonArray.single().jsonObject
        assertEquals(JsonPrimitive(5502L), target["location_org_stock_id"])
        assertEquals(JsonPrimitive(12L), target["quantity"])
        assertEquals(JsonObject(mapOf("quantity" to JsonPrimitive(0.5), "note" to JsonPrimitive("Showroom"))), InventoryResponses.orgStockWriteBody(consume))
        assertEquals(JsonObject(mapOf("location_id" to JsonPrimitive(300L), "type" to JsonPrimitive("picking"))), InventoryResponses.orgStockWriteBody(add))
        assertEquals(JsonArray(listOf(JsonPrimitive(5502L))), InventoryResponses.orgStockWriteBody(remove)["location_org_stock_ids"])
    }

    @Test
    fun `the auto remove switch sends enabled`() {
        assertEquals(JsonObject(mapOf("enabled" to JsonPrimitive(true))), InventoryResponses.emptyLocationsRemovalBody(true))
    }

    @Test
    fun `the SKO list sends the stock status and location type and reads their choices`() {
        val parameters = InventoryResponses.orgStocksParameters(SkoQuery(stockStatus = "w2", locationType = "storing"))
        val page = (
            InventoryResponses.parseOrgStocks(
                200,
                """{"data":[],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":0},"filters":{"stock_status":[{"key":"out","label":"Out of stock"},{"key":"w2","label":"Critical"}],"location_type":[{"key":"picking","label":"Picking"}]}}""",
            ) as GoodsOutResult.Loaded
            ).value

        assertTrue(("stock_status" to "w2") in parameters)
        assertTrue(("location_type" to "storing") in parameters)
        assertTrue(InventoryResponses.orgStocksParameters(SkoQuery()).none { it.first == "stock_status" || it.first == "location_type" })
        assertEquals(listOf("out", "w2"), page.filterOptions?.stockStatus?.map { it.key })
        assertEquals("Critical", page.filterOptions?.stockStatus?.last()?.label)
        assertEquals(listOf("picking"), page.filterOptions?.locationType?.map { it.key })
    }
}
