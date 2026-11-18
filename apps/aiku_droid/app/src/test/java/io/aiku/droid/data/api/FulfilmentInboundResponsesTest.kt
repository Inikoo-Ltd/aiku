package io.aiku.droid.data.api

import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class FulfilmentInboundResponsesTest {

    private val palletJson = """
        {"id":10,"reference":"acme-p0231","customer_reference":"Spring 1","type":"pallet","type_label":"Pallet",
         "state":"booked_in","state_label":"Booked In","status":"receiving","status_label":"Receiving",
         "customer":{"id":3,"name":"Acme Ltd"},"location":{"id":220,"code":"D-02-01"},"notes":null,"number_stored_items":"3","incident":null,
         "has_rental":true,"stored_items":[{"id":1,"reference":"MUG-BLUE","name":"Mug","quantity":"40"}],
         "available_actions":[{"key":"book_in","label":"Change location"}]}
    """.trimIndent()

    private val deliveryJson = """
        {"id":4,"reference":"acme-012","customer_reference":"PO 55","state":"booking_in","state_label":"Booking In",
         "customer":{"id":3,"name":"Acme Ltd"},"estimated_delivery_date":"2026-11-17","handing_type":"shipping",
         "customer_notes":"Fragile","public_notes":null,"internal_notes":"Check pallet 2",
         "timeline":[{"state":"confirmed","label":"Confirmed","at":"2026-11-10T08:00:00+00:00"},{"state":"received","label":"Received","at":"2026-11-16T09:00:00+00:00"},
                     {"state":"booking_in","label":"Booking In","at":null},{"state":"booked_in","label":"Booked In","at":null}],
         "counts":{"pallets":3,"boxes":"1","oversizes":0,"total":4,"received":0,"booking_in":1,"booked_in":2.0,"not_received":1},
         "pallets":[$palletJson,
           {"id":11,"reference":"acme-p0232","customer_reference":null,"type":"box","type_label":"Box","state":"booking_in","state_label":"Booking In",
            "status":"receiving","status_label":"Receiving","location":null,"number_stored_items":0,"has_rental":false,"stored_items":[],
            "available_actions":[{"key":"book_in","label":"Book in"},{"key":"not_received","label":"Not received"}]}],
         "services":[{"code":"S-1","name":"Unloading","quantity":"2","unit_label":"pallet"}],
         "physical_goods":[{"code":"G-1","name":"Shrink wrap","quantity":1.5,"unit_label":null}],
         "finish_booking_blocked":"1 pallet still to book in",
         "available_actions":[]}
    """.trimIndent()

    @Test
    fun `the delivery list reads rows with lenient counts, meta and the state counts`() {
        val body = """{"data":[{"id":4,"reference":"acme-012","customer_reference":null,"state":"confirmed","state_label":"Confirmed",
            "customer":{"id":3,"name":"Acme Ltd"},"estimated_delivery_date":"2026-11-17","received_at":null,"booked_in_at":null,
            "counts":{"pallets":"6","boxes":8,"oversizes":null,"total":14.0,"booked_in":0,"not_received":0}}],
            "meta":{"current_page":1,"last_page":2,"per_page":25,"total":"30"},
            "counts":{"confirmed":2,"received":"1","booking_in":1,"booked_in":12,"not_received":null,"active":4}}"""

        val page = (FulfilmentInboundResponses.parsePalletDeliveries(200, body) as GoodsOutResult.Loaded).value
        val row = page.rows.single()

        assertEquals("acme-012", row.reference)
        assertEquals("Acme Ltd", row.customer?.name)
        assertEquals(6, row.counts.pallets)
        assertEquals(8, row.counts.boxes)
        assertEquals(0, row.counts.oversizes)
        assertEquals(14, row.counts.total)
        assertNull(row.customerReference)
        assertEquals(2, page.meta.lastPage)
        assertEquals(30, page.meta.total)
        assertEquals(4, page.counts?.count(PalletDeliveryStateFilter.Active.key))
        assertEquals(1, page.counts?.count(PalletDeliveryStateFilter.Received.key))
        assertNull(page.counts?.count(PalletDeliveryStateFilter.NotReceived.key))
    }

    @Test
    fun `the delivery detail reads pallets, timeline, notes, services and the finish hint`() {
        val delivery = (FulfilmentInboundResponses.parsePalletDelivery(200, """{"data":$deliveryJson}""") as GoodsOutResult.Loaded).value

        assertEquals(PalletDeliveryState.BOOKING_IN, delivery.state)
        assertEquals(4, delivery.timeline.size)
        assertEquals("2026-11-16T09:00:00+00:00", delivery.at(PalletDeliveryState.RECEIVED))
        assertNull(delivery.at(PalletDeliveryState.BOOKED_IN))
        assertEquals(1, delivery.counts.boxes)
        assertEquals(2, delivery.counts.bookedIn)
        assertEquals("Fragile", delivery.customerNotes)
        assertNull(delivery.publicNotes)
        assertEquals("1 pallet still to book in", delivery.finishBookingBlocked)
        assertEquals(2.0, delivery.services.single().quantity, 0.0)
        assertNull(delivery.physicalGoods.single().unitLabel)
        assertTrue(delivery.availableActions.isEmpty())

        val booked = delivery.pallet(10)!!
        assertEquals("D-02-01", booked.locationCode)
        assertEquals(3, booked.numberStoredItems)
        assertEquals(40, booked.storedItems.single().quantity)
        assertEquals(true, booked.hasRental)
        assertTrue(booked.can(FulfilmentInboundActionKey.BOOK_IN))
        assertFalse(booked.can(FulfilmentInboundActionKey.NOT_RECEIVED))

        val waiting = delivery.pallet(11)!!
        assertNull(waiting.locationCode)
        assertNull(waiting.customerReference)
        assertTrue(waiting.can(FulfilmentInboundActionKey.NOT_RECEIVED))
    }

    @Test
    fun `a pallet is found by its label reference, or by a customer reference that only one pallet has`() {
        val delivery = (FulfilmentInboundResponses.parsePalletDelivery(200, """{"data":$deliveryJson}""") as GoodsOutResult.Loaded).value

        assertEquals(10L, delivery.palletLabelled("ACME-P0231")?.id)
        assertEquals(10L, delivery.palletLabelled(" spring 1 ")?.id)
        assertNull(delivery.palletLabelled("D-02-04"))
    }

    @Test
    fun `the pallet list reads rows, incidents and both count groups`() {
        val body = """{"data":[{"id":12,"reference":"bravo-p0097","customer_reference":null,"type":"pallet","type_label":"Pallet",
            "state":"damaged","state_label":"Damaged","status":"incident","status_label":"Incident","customer":{"id":4,"name":"Bravo"},
            "location":null,"notes":null,"number_stored_items":0,
            "incident":{"state":"damaged","state_label":"Damaged","message":"Forklift hit the corner","reporter":{"id":2,"name":"Jo K."},"date":"2026-11-06T10:00:00+00:00","return":null}},
            {"id":13,"reference":"acme-p0122","state":"other_incident","state_label":"Other incident","status":"incident",
            "incident":{"state":"other_incident","state_label":"Other incident","message":null,"reporter":null,"date":null,"return":{"id":7,"reference":"acme-r003"}}}],
            "meta":{"current_page":"1","last_page":1,"per_page":50,"total":2},
            "counts":{"storing":"642","receiving":10,"returning":4,"incidents":3,"returned":0,"incident_states":{"damaged":1,"lost":"1","other_incident":1}}}"""

        val page = (FulfilmentInboundResponses.parsePallets(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf(12L, 13L), page.rows.map { it.id })
        assertEquals("Jo K.", page.rows[0].incident?.reporter?.name)
        assertNull(page.rows[0].locationCode)
        assertEquals("acme-r003", page.rows[1].incident?.palletReturn?.reference)
        assertNull(page.rows[1].incident?.reporter)
        assertEquals(642, page.counts?.count(PalletFilter.Storing.key))
        assertEquals(3, page.counts?.count(PalletFilter.Incidents.key))
        assertNull(page.counts?.count("incident_states"))
        assertEquals(1, page.incidentCounts?.count(PalletIncidentState.Lost.key))
        assertEquals(1, page.incidentCounts?.count(PalletIncidentState.OtherIncident.key))
    }

    @Test
    fun `the pallet detail reads references, dates, stored items, the open audit and actions`() {
        val body = """{"data":{"id":11,"reference":"acme-p0140","customer_reference":"Spring range","type":"pallet","type_label":"Pallet",
            "state":"storing","state_label":"Storing","status":"storing","status_label":"Storing","customer":{"id":3,"name":"Acme Ltd"},
            "location":{"id":9,"code":"D-03-01"},"notes":"Top shelf","number_stored_items":2,"incident":null,
            "delivery":{"id":4,"reference":"acme-012"},"return":null,"received_at":"2026-11-01T08:00:00+00:00","booked_in_at":null,"storing_at":"2026-11-02T08:00:00+00:00",
            "items_storage":true,
            "stored_items":[{"id":1,"reference":"MUG-BLUE","name":"Mug","barcode":"5060","quantity":40,"state":"active","state_label":"Active"},
                            {"id":2,"reference":"TOWEL","name":"Towel","barcode":null,"quantity":"18.0","state":"discontinuing","state_label":"Discontinuing"}],
            "open_audit_id":12,
            "available_actions":[{"key":"move","label":"Move"},{"key":"damaged","label":"Damaged"},{"key":"audit","label":"Continue audit"}]}}"""

        val pallet = (FulfilmentInboundResponses.parsePallet(200, body) as GoodsOutResult.Loaded).value

        assertEquals("acme-012", pallet.delivery?.reference)
        assertNull(pallet.palletReturn)
        assertTrue(pallet.itemsStorage)
        assertEquals(12L, pallet.openAuditId)
        assertEquals(18, pallet.storedItems[1].quantity)
        assertNull(pallet.storedItems[1].barcode)
        assertNull(pallet.bookedInAt)
        assertTrue(pallet.can(FulfilmentInboundActionKey.MOVE))
        assertTrue(pallet.can(FulfilmentInboundActionKey.AUDIT))
        assertFalse(pallet.can(FulfilmentInboundActionKey.LOST))
    }

    @Test
    fun `writes answer the re-read detail with the result message`() {
        val deliveryBody = """{"data":$deliveryJson,"result":{"status":"nothing_changed","message":"Already at D-02-01."}}"""
        val palletBody = """{"data":$palletJson,"result":{"status":"done","message":" Pallet set as damaged. "}}"""

        val deliveryOutcome = (FulfilmentInboundResponses.parsePalletDeliveryWrite(200, deliveryBody) as GoodsOutResult.Loaded).value
        val palletOutcome = (FulfilmentInboundResponses.parsePalletWrite(200, palletBody) as GoodsOutResult.Loaded).value

        assertEquals(GoodsInWriteStatus.NOTHING_CHANGED, deliveryOutcome.status)
        assertEquals("Already at D-02-01.", deliveryOutcome.message)
        assertEquals("acme-012", deliveryOutcome.delivery.reference)
        assertEquals("Pallet set as damaged.", palletOutcome.message)
        assertEquals(10L, palletOutcome.pallet.id)
    }

    @Test
    fun `starting an audit gives the audit id and its message`() {
        val body = """{"data":{"id":"12","reference":"acme-sia014","state":"in_process","lines":[]},"result":{"status":"done","message":"Audit resumed."}}"""

        val started = (FulfilmentInboundResponses.parseAuditStart(200, body) as GoodsOutResult.Loaded).value

        assertEquals(12L, started.auditId)
        assertEquals("Audit resumed.", started.message)
    }

    @Test
    fun `refusals keep the server message and field errors`() {
        val invalid = FulfilmentInboundResponses.parsePalletDeliveryWrite(
            422,
            """{"message":"That location is not in this warehouse.","errors":{"location_code":["That location is not in this warehouse."]}}""",
        ) as GoodsOutResult.Invalid
        val forbidden = FulfilmentInboundResponses.parsePallet(403, """{"message":"You can not view fulfilment here."}""") as GoodsOutResult.Forbidden

        assertEquals("That location is not in this warehouse.", invalid.message)
        assertEquals(listOf("That location is not in this warehouse."), invalid.errors[FulfilmentInboundErrorKey.LOCATION_CODE])
        assertEquals("You can not view fulfilment here.", forbidden.message)
        assertTrue(FulfilmentInboundResponses.parsePalletDelivery(404, "{}") is GoodsOutResult.NotFound)
        assertTrue(FulfilmentInboundResponses.parsePallets(200, "not json") is GoodsOutResult.ServerError)
    }

    @Test
    fun `fulfilment locations read the live pallet count`() {
        val body = """{"data":[{"id":220,"code":"D-02-01","number_pallets":"2"},{"id":221,"code":"D-02-02","number_pallets":null}]}"""

        val locations = (FulfilmentInboundResponses.parseLocations(200, body) as GoodsOutResult.Loaded).value

        assertEquals(listOf("D-02-01", "D-02-02"), locations.map { it.code })
        assertEquals(2, locations[0].numberPallets)
        assertEquals(0, locations[1].numberPallets)
    }

    @Test
    fun `the scan can find a pallet or a pallet delivery`() {
        val pallet = (GoodsOutResponses.parseScan(
            200,
            """{"data":{"code":"acme-p0231","type":"pallet","pallet":{"id":10,"reference":"acme-p0231","customer_reference":null,"state":"storing","state_label":"Storing","status":"storing","location_code":"D-02-01"}}}""",
        ) as GoodsOutResult.Loaded).value
        val delivery = (GoodsOutResponses.parseScan(
            200,
            """{"data":{"code":"pad-acme-012","type":"pallet_delivery","pallet_delivery":{"id":4,"reference":"acme-012","state":"booking_in","state_label":"Booking In"}}}""",
        ) as GoodsOutResult.Loaded).value

        assertEquals(ScanMatch.PALLET, pallet.type)
        assertEquals(10L, pallet.pallet?.id)
        assertEquals("D-02-01", pallet.pallet?.locationCode)
        assertEquals(ScanMatch.PALLET_DELIVERY, delivery.type)
        assertEquals(PalletDeliveryState.BOOKING_IN, delivery.palletDelivery?.state)
        assertNull(delivery.pallet)
    }

    @Test
    fun `list parameters carry the state or filter, the search and paging, and the incident state only with incidents`() {
        assertEquals(
            listOf("state" to "booking_in", "q" to "acme", "page" to "2", "per_page" to "50"),
            FulfilmentInboundResponses.palletDeliveriesParameters(PalletDeliveryQuery(state = PalletDeliveryStateFilter.BookingIn, search = " acme ", page = 2)),
        )
        assertEquals(
            listOf("state" to "active", "page" to "1", "per_page" to "25"),
            FulfilmentInboundResponses.palletDeliveriesParameters(PalletDeliveryQuery(search = "  ", perPage = 25)),
        )
        assertEquals(
            listOf("filter" to "incidents", "incident_state" to "other_incident", "page" to "1", "per_page" to "50"),
            FulfilmentInboundResponses.palletsParameters(PalletQuery(filter = PalletFilter.Incidents, incidentState = PalletIncidentState.OtherIncident)),
        )
        assertEquals(
            listOf("filter" to "storing", "q" to "D-02", "page" to "1", "per_page" to "50"),
            FulfilmentInboundResponses.palletsParameters(PalletQuery(incidentState = PalletIncidentState.Lost, search = "D-02")),
        )
        assertEquals(listOf("q" to "d-02"), FulfilmentInboundResponses.locationParameters(" d-02 "))
    }

    @Test
    fun `writes go to their paths with exactly one location field`() {
        val receive = FulfilmentInboundResponses.palletDeliveryWriteRequest(4, PalletDeliveryWrite.Receive)
        val start = FulfilmentInboundResponses.palletDeliveryWriteRequest(4, PalletDeliveryWrite.StartBooking)
        val finish = FulfilmentInboundResponses.palletDeliveryWriteRequest(4, PalletDeliveryWrite.FinishBooking)
        val picked = FulfilmentInboundResponses.palletDeliveryWriteRequest(4, PalletDeliveryWrite.BookIn(10, PalletLocationTarget.Picked(220, "D-02-01")))
        val scanned = FulfilmentInboundResponses.palletDeliveryWriteRequest(4, PalletDeliveryWrite.BookIn(10, PalletLocationTarget.Scanned(" d-02-04 ")))
        val notReceived = FulfilmentInboundResponses.palletDeliveryWriteRequest(4, PalletDeliveryWrite.NotReceived(11))
        val undo = FulfilmentInboundResponses.palletDeliveryWriteRequest(4, PalletDeliveryWrite.UndoNotReceived(11))

        assertEquals("pallet-deliveries/4/receive", receive.path)
        assertEquals("pallet-deliveries/4/start-booking", start.path)
        assertEquals("pallet-deliveries/4/finish-booking", finish.path)
        assertEquals("pallets/10/book-in", picked.path)
        assertEquals(JsonPrimitive(220), picked.body?.get("location_id"))
        assertFalse(picked.body?.containsKey("location_code") == true)
        assertEquals(JsonPrimitive("d-02-04"), scanned.body?.get("location_code"))
        assertFalse(scanned.body?.containsKey("location_id") == true)
        assertEquals("pallets/11/not-received", notReceived.path)
        assertEquals("pallets/11/undo-not-received", undo.path)
        assertTrue(listOf(receive, start, finish, picked, scanned, notReceived, undo).all { it.method == WriteMethod.Post })

        val move = FulfilmentInboundResponses.palletWriteRequest(PalletWrite.Move(PalletLocationTarget.Scanned("D-03-02")))
        val incident = FulfilmentInboundResponses.palletWriteRequest(PalletWrite.Incident(PalletIncidentState.Lost, " Not at D-04-06 "))
        val silent = FulfilmentInboundResponses.palletWriteRequest(PalletWrite.Incident(PalletIncidentState.Damaged, null))

        assertEquals("move", move.path)
        assertEquals(JsonPrimitive("D-03-02"), move.body?.get("location_code"))
        assertEquals("incident", incident.path)
        assertEquals(JsonPrimitive("lost"), incident.body?.get("state"))
        assertEquals(JsonPrimitive("Not at D-04-06"), incident.body?.get("message"))
        assertEquals(JsonNull, silent.body?.get("message"))
        assertEquals("audit", FulfilmentInboundResponses.auditStartRequest().path)
    }
}
