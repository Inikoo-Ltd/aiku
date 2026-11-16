package io.aiku.droid.data.api

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

private const val HTTP_OK = 200
private const val HTTP_CREATED = 201

@Serializable
private data class ChatData<T>(val data: T)

object StaffChatResponses {
    private val json = ClockingResponses.json

    fun openPath(kind: ChatContextKind, contextId: Long): String = "${kind.path}/$contextId/chat"

    fun messagesParameters(query: ChatPageQuery): List<Pair<String, String>> = when (query) {
        ChatPageQuery.Latest -> emptyList()
        is ChatPageQuery.Before -> listOf("before_id" to query.messageId.toString())
        is ChatPageQuery.After -> listOf("after_id" to query.messageId.toString())
    }

    fun sendBody(body: String): JsonObject = buildJsonObject { put("body", body.trim()) }

    fun parseConversation(statusCode: Int, body: String): GoodsOutResult<ChatConversation> = parseData(statusCode, body)

    fun parseMessages(statusCode: Int, body: String): GoodsOutResult<ChatMessagePage> = when (statusCode) {
        HTTP_OK -> decode<ChatMessagePage>(body)?.let { GoodsOutResult.Loaded(it) } ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseSent(statusCode: Int, body: String): GoodsOutResult<ChatMessage> = parseData(statusCode, body)

    private inline fun <reified T> parseData(statusCode: Int, body: String): GoodsOutResult<T> = when (statusCode) {
        HTTP_OK, HTTP_CREATED -> decode<ChatData<T>>(body)?.let { GoodsOutResult.Loaded(it.data) } ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()
}
