package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.decodeFromJsonElement

private const val HTTP_OK = 200
private const val INBOX_PAGE_SIZE = 30

@Serializable
private data class InboxListResponse(
    val data: List<JsonElement> = emptyList(),
    val meta: ListMeta = ListMeta(),
    @Serializable(with = LenientIntOrNull::class) val unread: Int? = null,
)

@Serializable
private data class InboxRowResponse(val data: JsonElement? = null)

@Serializable
private data class UnreadResponse(val data: UnreadCount? = null)

@Serializable
private data class UnreadCount(@Serializable(with = LenientIntOrNull::class) val unread: Int? = null)

@Serializable
private data class InboxWireRow(
    val id: String? = null,
    val kind: String? = null,
    val event: String? = null,
    val title: String? = null,
    val body: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("read_at") val readAt: String? = null,
    val target: InboxWireTarget? = null,
)

@Serializable
private data class InboxWireTarget(
    val screen: String? = null,
    val id: Long? = null,
    val reference: String? = null,
    val state: String? = null,
    @SerialName("warehouse_id") val warehouseId: Long? = null,
    @SerialName("type_group") val typeGroup: String? = null,
)

object InboxResponses {
    private val json = ClockingResponses.json

    fun listParameters(page: Int): List<Pair<String, String>> = listOf(
        "filter" to "all",
        "page" to page.toString(),
        "per_page" to INBOX_PAGE_SIZE.toString(),
    )

    fun parseList(statusCode: Int, body: String): GoodsOutResult<InboxPage> = when (statusCode) {
        HTTP_OK -> decode<InboxListResponse>(body)
            ?.let { GoodsOutResult.Loaded(InboxPage(notifications = it.data.mapNotNull(::notificationOf), meta = it.meta, unread = it.unread)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseUnread(statusCode: Int, body: String): GoodsOutResult<Int> = when (statusCode) {
        HTTP_OK -> decode<UnreadResponse>(body)?.data
            ?.let { GoodsOutResult.Loaded(it.unread ?: 0) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseNotification(statusCode: Int, body: String): GoodsOutResult<InboxNotification> = when (statusCode) {
        HTTP_OK -> decode<InboxRowResponse>(body)?.data
            ?.let(::notificationOf)
            ?.let { GoodsOutResult.Loaded(it) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private fun notificationOf(element: JsonElement): InboxNotification? {
        val wire = runCatching { json.decodeFromJsonElement<InboxWireRow>(element) }.getOrNull() ?: return null
        val id = wire.id.trimmedOrNull() ?: return null
        return InboxNotification(
            id = id,
            kind = wire.kind.orEmpty(),
            event = wire.event.orEmpty(),
            title = wire.title.orEmpty().trim(),
            body = wire.body.orEmpty().trim(),
            createdAt = wire.createdAt.trimmedOrNull(),
            readAt = wire.readAt.trimmedOrNull(),
            target = wire.target?.let(::recordOf),
        )
    }

    private fun recordOf(target: InboxWireTarget): WorkRecord? {
        val kind = MyWorkKind.ofKey(target.screen) ?: return null
        val id = target.id ?: return null
        return WorkRecord(
            kind = kind,
            id = id,
            reference = target.reference.orEmpty(),
            state = target.state.orEmpty(),
            warehouseId = target.warehouseId,
            typeGroup = target.typeGroup.trimmedOrNull(),
        )
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}
