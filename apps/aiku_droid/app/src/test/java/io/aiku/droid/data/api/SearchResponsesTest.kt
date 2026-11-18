package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class SearchResponsesTest {

    private fun loaded(body: String): SearchResults = (SearchResponses.parseSearch(200, body) as GoodsOutResult.Loaded).value

    @Test
    fun `search reads the types, the exact match and the groups in the order received`() {
        val body = """{"data":{
            "query":"lav",
            "types":["skos","sko_families","locations","delivery_notes"],
            "exact":{"type":"delivery_note","id":991,"code":"AR24817","title":"AR24817","subtitle":"Wholesale · 6 items","state":"handling","state_label":"Picking","quantity":null,"image_url":null,"type_group":null},
            "groups":[
              {"type":"skos","label":"SKOs","has_more":true,"rows":[
                {"type":"sko","id":412,"code":"SKO-00412","title":"SKO-00412","subtitle":"Lavender essential oil 10ml","state":"active","state_label":"Active","quantity":1240,"image_url":"https://app.aiku.test/image/412.jpg","type_group":null}
              ]},
              {"type":"pallet_returns","label":"Fulfilment returns","has_more":false,"rows":[
                {"type":"pallet_return","id":8,"code":"acme-r1","title":"acme-r1","subtitle":"Acme Ltd · Picking","state":"picking","state_label":"Picking","quantity":null,"image_url":null,"type_group":"items"}
              ]}
            ]}}"""

        val results = loaded(body)

        assertEquals("lav", results.query)
        assertEquals(listOf(SearchType.Skos, SearchType.SkoFamilies, SearchType.Locations, SearchType.DeliveryNotes), results.types)
        assertEquals(
            SearchRow(type = SearchType.DeliveryNotes, id = 991, code = "AR24817", title = "AR24817", subtitle = "Wholesale · 6 items", state = "handling", stateLabel = "Picking"),
            results.exact,
        )
        assertEquals(listOf(SearchType.Skos, SearchType.PalletReturns), results.groups.map { it.type })
        assertEquals(
            SearchGroup(
                type = SearchType.Skos,
                label = "SKOs",
                hasMore = true,
                rows = listOf(
                    SearchRow(
                        type = SearchType.Skos,
                        id = 412,
                        code = "SKO-00412",
                        title = "SKO-00412",
                        subtitle = "Lavender essential oil 10ml",
                        state = "active",
                        stateLabel = "Active",
                        quantity = 1240.0,
                        imageUrl = "https://app.aiku.test/image/412.jpg",
                    ),
                ),
            ),
            results.groups.first(),
        )
        assertEquals("items", results.groups.last().rows.single().typeGroup)
    }

    @Test
    fun `a query under 2 characters reads as no results with the types`() {
        val results = loaded("""{"data":{"query":"l","types":["locations"],"exact":null,"groups":[]}}""")

        assertEquals(listOf(SearchType.Locations), results.types)
        assertNull(results.exact)
        assertTrue(results.groups.isEmpty())
    }

    @Test
    fun `unknown types, groups and rows are skipped and nulls become empty`() {
        val body = """{"data":{
            "query":null,
            "types":["skos","trolleys",null],
            "exact":{"type":"trolley","id":3,"code":"T-03"},
            "groups":[
              {"type":"trolleys","label":"Trolleys","has_more":false,"rows":[{"type":"trolley","id":3,"code":"T-03"}]},
              {"type":"skos","label":null,"has_more":null,"rows":[
                {"type":"sko","id":null,"code":"SKO-1"},
                {"type":"sko","id":2,"code":" SKO-2 ","title":null,"subtitle":null,"state":null,"state_label":null,"quantity":"36.5","image_url":" ","type_group":null},
                {"type":"location","id":5,"code":"A-01"},
                "not a row"
              ]},
              {"type":"locations","label":"Locations","has_more":false,"rows":[{"type":"wall","id":5}]}
            ]}}"""

        val results = loaded(body)

        assertEquals("", results.query)
        assertEquals(listOf(SearchType.Skos), results.types)
        assertNull(results.exact)
        val group = results.groups.single()
        assertEquals(SearchType.Skos, group.type)
        assertEquals("", group.label)
        assertEquals(false, group.hasMore)
        assertEquals(
            listOf(
                SearchRow(type = SearchType.Skos, id = 2, code = "SKO-2", title = "SKO-2", quantity = 36.5),
                SearchRow(type = SearchType.Locations, id = 5, code = "A-01", title = "A-01"),
            ),
            group.rows,
        )
    }

    @Test
    fun `search maps failures`() {
        assertEquals(GoodsOutResult.SessionExpired, SearchResponses.parseSearch(401, """{"message":"Unauthenticated."}"""))
        assertTrue(SearchResponses.parseSearch(403, """{"message":"This action is unauthorized."}""") is GoodsOutResult.Forbidden)
        assertTrue(SearchResponses.parseSearch(404, """{"message":"Not found"}""") is GoodsOutResult.NotFound)
        assertTrue(SearchResponses.parseSearch(422, """{"message":"The q field must not be greater than 64 characters."}""") is GoodsOutResult.Invalid)
        assertEquals(GoodsOutResult.ServerError(200), SearchResponses.parseSearch(200, "not json"))
        assertEquals(GoodsOutResult.ServerError(200), SearchResponses.parseSearch(200, """{"data":null}"""))
        assertEquals(GoodsOutResult.ServerError(500), SearchResponses.parseSearch(500, ""))
    }
}
