package io.aiku.droid.ui.goodsout.extra

import io.aiku.droid.data.api.ChatContextKind
import io.aiku.droid.data.api.ChatConversation
import io.aiku.droid.data.api.ChatMessage
import io.aiku.droid.data.api.ChatMessagePage
import io.aiku.droid.data.api.ChatPageQuery
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.StaffChatApi
import io.aiku.droid.data.api.StaffRef
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
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
class StaffChatViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 5, username = "picker", displayName = "Ana"))
    private val api = FakeChatApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `opening loads the latest messages, polling appends only new ones and stops when hidden`() {
        api.pages[ChatPageQuery.Latest] = ChatMessagePage(data = listOf(message(2), message(1)), hasMore = true)
        api.pages[ChatPageQuery.After(2)] = ChatMessagePage(data = listOf(message(2), message(3)))
        val viewModel = StaffChatViewModel(api, sessions, ChatContextKind.DeliveryNote, contextId = 9)

        viewModel.onVisible()

        assertEquals(listOf(ChatContextKind.DeliveryNote to 9L), api.opened)
        assertEquals(listOf(1L, 2L), viewModel.state.value.messages.map { it.id })
        assertTrue(viewModel.state.value.hasOlder)

        dispatcher.scheduler.advanceTimeBy(CHAT_POLL_MILLIS + 1)

        assertEquals(listOf<ChatPageQuery>(ChatPageQuery.Latest, ChatPageQuery.After(2)), api.queries)
        assertEquals(listOf(1L, 2L, 3L), viewModel.state.value.messages.map { it.id })

        viewModel.onHidden()
        dispatcher.scheduler.advanceTimeBy(CHAT_POLL_MILLIS * 3)

        assertEquals(2, api.queries.size)

        viewModel.onVisible()

        assertEquals(listOf(ChatContextKind.DeliveryNote to 9L), api.opened)
        assertEquals(ChatPageQuery.After(3), api.queries.last())
    }

    @Test
    fun `older messages load before the oldest one and sending appends the stored message`() {
        api.pages[ChatPageQuery.Latest] = ChatMessagePage(data = listOf(message(5), message(6)), hasMore = true)
        api.pages[ChatPageQuery.Before(5)] = ChatMessagePage(data = listOf(message(3), message(4)), hasMore = false)
        api.sent = GoodsOutResult.Loaded(message(7, isMine = true))
        val viewModel = StaffChatViewModel(api, sessions, ChatContextKind.PickingSession, contextId = 12)
        viewModel.onVisible()

        viewModel.loadOlder()
        viewModel.updateDraft("  Is DN-9 still needed? ")
        viewModel.send()

        val state = viewModel.state.value
        assertEquals(listOf(3L, 4L, 5L, 6L, 7L), state.messages.map { it.id })
        assertFalse(state.hasOlder)
        assertEquals(listOf("Is DN-9 still needed?"), api.bodies)
        assertEquals("", state.draft)
        viewModel.onHidden()
    }

    @Test
    fun `a refused message keeps the draft and says why`() {
        api.pages[ChatPageQuery.Latest] = ChatMessagePage()
        api.sent = GoodsOutResult.Forbidden("You are not in this conversation.")
        val viewModel = StaffChatViewModel(api, sessions, ChatContextKind.DeliveryNote, contextId = 9)
        viewModel.onVisible()

        viewModel.updateDraft("Hello")
        viewModel.send()

        assertEquals("Hello", viewModel.state.value.draft)
        assertEquals(ChatSendRefusal(message = "You are not in this conversation.", problem = GoodsOutProblem.Forbidden), viewModel.state.value.sendRefusal)
        viewModel.onHidden()
    }

    private fun message(id: Long, isMine: Boolean = false): ChatMessage =
        ChatMessage(id = id, user = StaffRef(id = if (isMine) 5 else 7, name = if (isMine) "Ana" else "Bea"), isMine = isMine, body = "Message $id")

    private class FakeChatApi : StaffChatApi {
        val pages = mutableMapOf<ChatPageQuery, ChatMessagePage>()
        var sent: GoodsOutResult<ChatMessage> = GoodsOutResult.Unreachable
        val opened = mutableListOf<Pair<ChatContextKind, Long>>()
        val queries = mutableListOf<ChatPageQuery>()
        val bodies = mutableListOf<String>()

        override suspend fun openChat(token: String, kind: ChatContextKind, contextId: Long): GoodsOutResult<ChatConversation> {
            opened += kind to contextId
            return GoodsOutResult.Loaded(ChatConversation(ulid = "01JD", participants = listOf(StaffRef(id = 7, name = "Bea"))))
        }

        override suspend fun chatMessages(token: String, ulid: String, query: ChatPageQuery): GoodsOutResult<ChatMessagePage> {
            queries += query
            return GoodsOutResult.Loaded(pages[query] ?: ChatMessagePage())
        }

        override suspend fun sendChatMessage(token: String, ulid: String, body: String): GoodsOutResult<ChatMessage> {
            bodies += body
            return sent
        }
    }

    private class InMemorySessions(initial: StaffSession) : SessionRepository {
        private val current = MutableStateFlow<StaffSession?>(initial)
        override val session: StateFlow<StaffSession?> = current.asStateFlow()
        override fun start(session: StaffSession, rememberOnDevice: Boolean) {
            current.value = session
        }
        override fun end() {
            current.value = null
        }
    }
}
