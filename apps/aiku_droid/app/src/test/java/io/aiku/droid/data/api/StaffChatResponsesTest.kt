package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class StaffChatResponsesTest {

    @Test
    fun `opening a chat returns the conversation with its people as id and name only`() {
        val conversation = (
            StaffChatResponses.parseConversation(
                200,
                """{"data":{"ulid":"01JD","name":"DN-9 · CRM","context_label":"Delivery note DN-9","participants":[{"id":7,"name":"Bea","handle":"bea"}],"unread_count":2}}""",
            ) as GoodsOutResult.Loaded
            ).value

        assertEquals(ChatConversation(ulid = "01JD", name = "DN-9 · CRM", contextLabel = "Delivery note DN-9", participants = listOf(StaffRef(id = 7, name = "Bea")), unreadCount = 2), conversation)
        assertEquals("delivery-notes/9/chat", StaffChatResponses.openPath(ChatContextKind.DeliveryNote, 9))
        assertEquals("picking-sessions/12/chat", StaffChatResponses.openPath(ChatContextKind.PickingSession, 12))
        assertEquals(ChatContextKind.PickingSession, ChatContextKind.ofKey("picking_session"))
    }

    @Test
    fun `messages page newest last, with mine flagged and images kept`() {
        val page = (
            StaffChatResponses.parseMessages(
                200,
                """{"data":[{"id":40,"user":{"id":7,"name":"Bea"},"is_mine":false,"body":null,"image_url":"https://app.aiku.test/c/40.jpg","created_at":"2026-11-13T09:00:00Z"},
                |{"id":41,"user":{"id":5,"name":"Ana"},"is_mine":true,"body":"Thanks","image_url":null,"created_at":"2026-11-13T09:01:00Z"}],"has_more":true}""".trimMargin(),
            ) as GoodsOutResult.Loaded
            ).value

        assertTrue(page.hasMore)
        assertEquals(listOf(40L, 41L), page.data.map { it.id })
        assertFalse(page.data.first().isMine)
        assertEquals("https://app.aiku.test/c/40.jpg", page.data.first().imageUrl)
        assertTrue(page.data.last().isMine)
        assertEquals("Thanks", page.data.last().body)
    }

    @Test
    fun `polling asks after the newest message and paging asks before the oldest`() {
        assertEquals(emptyList<Pair<String, String>>(), StaffChatResponses.messagesParameters(ChatPageQuery.Latest))
        assertEquals(listOf("after_id" to "41"), StaffChatResponses.messagesParameters(ChatPageQuery.After(41)))
        assertEquals(listOf("before_id" to "40"), StaffChatResponses.messagesParameters(ChatPageQuery.Before(40)))
    }

    @Test
    fun `sending posts the trimmed text and returns the stored message`() {
        val sent = StaffChatResponses.parseSent(201, """{"data":{"id":42,"user":{"id":5,"name":"Ana"},"is_mine":true,"body":"On my way","created_at":"2026-11-13T09:02:00Z"}}""")
        val refused = StaffChatResponses.parseSent(403, """{"message":"You are not in this conversation."}""")

        assertEquals("""{"body":"On my way"}""", StaffChatResponses.sendBody("  On my way ").toString())
        assertEquals(42L, (sent as GoodsOutResult.Loaded).value.id)
        assertEquals(GoodsOutResult.Forbidden("You are not in this conversation."), refused)
    }
}
