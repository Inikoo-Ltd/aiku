package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

enum class ChatContextKind(val key: String, val path: String) {
    DeliveryNote("delivery_note", "delivery-notes"),
    PickingSession("picking_session", "picking-sessions"),
    ;

    companion object {
        fun ofKey(key: String?): ChatContextKind? = entries.firstOrNull { it.key == key }
    }
}

@Serializable
data class ChatConversation(
    val ulid: String,
    val name: String = "",
    @SerialName("context_label") val contextLabel: String = "",
    val participants: List<StaffRef> = emptyList(),
    @SerialName("unread_count") val unreadCount: Int = 0,
)

@Serializable
data class ChatMessage(
    val id: Long,
    val user: StaffRef? = null,
    @SerialName("is_mine") val isMine: Boolean = false,
    val body: String? = null,
    @SerialName("image_url") val imageUrl: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
)

@Serializable
data class ChatMessagePage(
    val data: List<ChatMessage> = emptyList(),
    @SerialName("has_more") val hasMore: Boolean = false,
)

sealed interface ChatPageQuery {
    data object Latest : ChatPageQuery
    data class Before(val messageId: Long) : ChatPageQuery
    data class After(val messageId: Long) : ChatPageQuery
}

interface StaffChatApi {
    suspend fun openChat(token: String, kind: ChatContextKind, contextId: Long): GoodsOutResult<ChatConversation>

    suspend fun chatMessages(token: String, ulid: String, query: ChatPageQuery): GoodsOutResult<ChatMessagePage>

    suspend fun sendChatMessage(token: String, ulid: String, body: String): GoodsOutResult<ChatMessage>
}
