package io.aiku.droid.ui.goodsout.extra

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.ChatContextKind
import io.aiku.droid.data.api.ChatConversation
import io.aiku.droid.data.api.ChatMessage
import io.aiku.droid.data.api.ChatPageQuery
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.StaffChatApi
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

const val CHAT_POLL_MILLIS = 10_000L
const val CHAT_MESSAGE_MAX_LENGTH = 5000

data class ChatSendRefusal(val message: String, val problem: GoodsOutProblem)

data class StaffChatUiState(
    val conversation: ChatConversation? = null,
    val messages: List<ChatMessage> = emptyList(),
    val hasOlder: Boolean = false,
    val isOpening: Boolean = false,
    val isLoadingOlder: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val draft: String = "",
    val isSending: Boolean = false,
    val sendRefusal: ChatSendRefusal? = null,
    val isSessionExpired: Boolean = false,
) {
    val canSend: Boolean get() = conversation != null && draft.isNotBlank() && !isSending
}

class StaffChatViewModel(
    private val api: StaffChatApi,
    private val sessions: SessionRepository,
    private val kind: ChatContextKind,
    private val contextId: Long,
    private val pollMillis: Long = CHAT_POLL_MILLIS,
) : ViewModel() {

    private val mutableState = MutableStateFlow(StaffChatUiState())
    val state: StateFlow<StaffChatUiState> = mutableState.asStateFlow()

    private var visibleJob: Job? = null

    fun onVisible() {
        if (visibleJob?.isActive == true) {
            return
        }
        visibleJob = viewModelScope.launch {
            val isOpened = mutableState.value.conversation != null
            if (isOpened) {
                fetchNewer()
            } else if (!open()) {
                return@launch
            }
            while (isActive) {
                delay(pollMillis)
                fetchNewer()
            }
        }
    }

    fun onHidden() {
        visibleJob?.cancel()
        visibleJob = null
    }

    fun retry() {
        onHidden()
        onVisible()
    }

    fun signInAgain() {
        sessions.end()
    }

    fun updateDraft(text: String) {
        mutableState.update { it.copy(draft = text.take(CHAT_MESSAGE_MAX_LENGTH), sendRefusal = null) }
    }

    fun loadOlder() {
        val current = mutableState.value
        val ulid = current.conversation?.ulid ?: return
        val oldest = current.messages.firstOrNull()?.id ?: return
        val token = sessions.session.value?.token ?: return
        if (!current.hasOlder || current.isLoadingOlder) {
            return
        }
        mutableState.update { it.copy(isLoadingOlder = true) }

        viewModelScope.launch {
            when (val result = api.chatMessages(token, ulid, ChatPageQuery.Before(oldest))) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(messages = merged(result.value.data, it.messages), hasOlder = result.value.hasMore, isLoadingOlder = false)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoadingOlder = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoadingOlder = false) }
            }
        }
    }

    fun send() {
        val current = mutableState.value
        val ulid = current.conversation?.ulid ?: return
        val token = sessions.session.value?.token ?: return
        val body = current.draft.trim()
        if (body.isEmpty() || current.isSending) {
            return
        }
        mutableState.update { it.copy(isSending = true, sendRefusal = null) }

        viewModelScope.launch {
            when (val result = api.sendChatMessage(token, ulid, body)) {
                is GoodsOutResult.Loaded -> mutableState.update {
                    it.copy(messages = merged(it.messages, listOf(result.value)), draft = "", isSending = false)
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSending = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isSending = false, sendRefusal = ChatSendRefusal(message = result.messageOrEmpty(), problem = result.problem())) }
            }
        }
    }

    private suspend fun open(): Boolean {
        val token = sessions.session.value?.token ?: return false
        mutableState.update { it.copy(isOpening = true, problem = null) }
        val conversation = when (val result = api.openChat(token, kind, contextId)) {
            is GoodsOutResult.Loaded -> result.value
            else -> return fail(result)
        }
        return when (val result = api.chatMessages(token, conversation.ulid, ChatPageQuery.Latest)) {
            is GoodsOutResult.Loaded -> {
                mutableState.update {
                    it.copy(conversation = conversation, messages = result.value.data.sortedBy(ChatMessage::id), hasOlder = result.value.hasMore, isOpening = false)
                }
                true
            }
            else -> fail(result)
        }
    }

    private suspend fun fetchNewer() {
        val current = mutableState.value
        val ulid = current.conversation?.ulid ?: return
        val token = sessions.session.value?.token ?: return
        val query = current.messages.lastOrNull()?.id?.let { ChatPageQuery.After(it) } ?: ChatPageQuery.Latest
        when (val result = api.chatMessages(token, ulid, query)) {
            is GoodsOutResult.Loaded -> mutableState.update { it.copy(messages = merged(it.messages, result.value.data), problem = null) }
            GoodsOutResult.SessionExpired -> {
                mutableState.update { it.copy(isSessionExpired = true) }
                onHidden()
            }
            else -> Unit
        }
    }

    private fun fail(result: GoodsOutResult<*>): Boolean {
        if (result == GoodsOutResult.SessionExpired) {
            mutableState.update { it.copy(isOpening = false, isSessionExpired = true) }
        } else {
            mutableState.update { it.copy(isOpening = false, problem = result.problem()) }
        }
        return false
    }

    private fun merged(first: List<ChatMessage>, second: List<ChatMessage>): List<ChatMessage> =
        (first + second).distinctBy { it.id }.sortedBy { it.id }
}

private fun GoodsOutResult<*>.messageOrEmpty(): String = when (this) {
    is GoodsOutResult.Forbidden -> message
    is GoodsOutResult.NotFound -> message
    is GoodsOutResult.Invalid -> message
    else -> ""
}
