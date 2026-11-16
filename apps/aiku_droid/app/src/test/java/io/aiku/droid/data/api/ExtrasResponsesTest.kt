package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ExtrasResponsesTest {

    @Test
    fun `a note carries the cancel, packer and address keys, the contact right and its return note`() {
        val note = (
            GoodsOutResponses.parseDeliveryNote(
                200,
                """{"data":{"id":9,"reference":"DN-9-CANCELLED","state":"cancelled","can_edit_contact":true,
                |"return_delivery_note":{"id":31,"reference":"DN-9-cancel-pick","state_label":"Received"},
                |"available_actions":[{"key":"cancel","label":"Cancel delivery note"},{"key":"change_packer","label":"Change packer"},
                |{"key":"edit_delivery_address","label":"Edit delivery address"}]}}""".trimMargin(),
            ) as GoodsOutResult.Loaded
            ).value
        val plain = (GoodsOutResponses.parseDeliveryNote(200, """{"data":{"id":10}}""") as GoodsOutResult.Loaded).value

        assertTrue(note.canEditContact)
        assertEquals(ReturnDeliveryNote(id = 31, reference = "DN-9-cancel-pick", stateLabel = "Received"), note.returnDeliveryNote)
        assertEquals("Cancel delivery note", note.action(NoteActionKey.CANCEL)?.label)
        assertEquals("Change packer", note.action(NoteActionKey.CHANGE_PACKER)?.label)
        assertEquals("Edit delivery address", note.action(NoteActionKey.EDIT_DELIVERY_ADDRESS)?.label)
        assertFalse(plain.canEditContact)
        assertNull(plain.returnDeliveryNote)
    }

    @Test
    fun `list rows say whether they can join a picking session`() {
        val page = (
            GoodsOutResponses.parseDeliveryNotes(
                200,
                """{"data":{"delivery_notes":[{"id":9,"reference":"DN-9","can_add_to_picking_session":true},{"id":10,"reference":"DN-10"}],"can_work_in_warehouse":true}}""",
            ) as GoodsOutResult.Loaded
            ).value

        assertEquals(listOf(true, false), page.notes.map { it.canAddToPickingSession })
    }

    @Test
    fun `picked bays list their notes and how many wait`() {
        val bays = (
            GoodsOutResponses.parsePickedBays(
                200,
                """{"data":[{"id":1,"code":"Bay 1","number_delivery_notes":0},{"id":2,"code":"Bay 2","number_delivery_notes":2,"number_waiting":1,
                |"delivery_notes":[{"id":9,"reference":"DN-9","state":"picked","state_label":"Picked"},{"id":10,"reference":"DN-10","state":"handling_blocked","state_label":"Waiting"}]}]}""".trimMargin(),
            ) as GoodsOutResult.Loaded
            ).value

        assertTrue(bays.first().isFree)
        assertFalse(bays.last().isFree)
        assertEquals(1, bays.last().numberWaiting)
        assertEquals(DeliveryNoteRef(id = 10, reference = "DN-10", state = "handling_blocked", stateLabel = "Waiting"), bays.last().deliveryNotes.last())
    }

    @Test
    fun `cancel, notes and packer writes send their bodies`() {
        assertWrite(DeliveryNoteWrite.Cancel(createReturn = true), "cancel", """{"create_return":true}""")
        assertWrite(DeliveryNoteWrite.Cancel(createReturn = false), "cancel", """{"create_return":false}""")
        assertWrite(DeliveryNoteWrite.SetNote(field = NoteField.Warehouse, text = " Fragile \n"), "notes", """{"field":"warehouse","text":"Fragile"}""")
        assertWrite(DeliveryNoteWrite.SetNote(field = NoteField.Shipping, text = ""), "notes", """{"field":"shipping","text":""}""")
        assertWrite(DeliveryNoteWrite.SetPacker(userId = 5), "packer", """{"user_id":5}""")
    }

    @Test
    fun `the address edit sends contact fields only when the user may change them`() {
        val fields = DeliveryAddressFields(contactName = " Jane ", companyName = "", email = "jane@example.com", addressLine1 = "12 High St", postalCode = "S1 2AB", locality = "Sheffield", countryCode = "gb")
        val address = """"address":{"address_line_1":"12 High St","address_line_2":null,"sorting_code":null,"postal_code":"S1 2AB","locality":"Sheffield",""" +
            """"dependent_locality":null,"administrative_area":null,"country_code":"GB"}"""

        assertWrite(DeliveryNoteWrite.EditDeliveryAddress(address = fields, includesContact = false), "delivery-address", "{$address}")
        assertWrite(
            DeliveryNoteWrite.EditDeliveryAddress(address = fields, includesContact = true),
            "delivery-address",
            """{$address,"contact_name":"Jane","company_name":null,"email":"jane@example.com","phone":null}""",
        )
    }

    private fun assertWrite(write: DeliveryNoteWrite, path: String, body: String) {
        val request = GoodsOutResponses.writeRequest(write)

        assertEquals(WriteMethod.Post, request.method)
        assertEquals(path, request.path)
        assertEquals(body, request.body.toString())
    }
}
