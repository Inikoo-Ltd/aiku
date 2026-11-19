package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.decodeFromJsonElement
import kotlinx.serialization.json.intOrNull

private const val HTTP_OK = 200
private const val INBOX_PAGE_SIZE = 30
private const val DATA = "data"
private const val UNREAD = "unread"
private const val ID = "id"
private const val READ_AT = "read_at"

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
    const val DEFAULT_FILTER = "all"

    private val json = ClockingResponses.json

    fun listParameters(page: Int): List<Pair<String, String>> = listOf(
        "filter" to DEFAULT_FILTER,
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

    fun markedRead(listBody: String, notificationId: String, readAt: String): String? {
        val list = jsonObjectOf(listBody) ?: return null
        val rows = list[DATA] as? JsonArray ?: return null
        val index = rows.indexOfFirst { it.notificationId() == notificationId }
        val row = rows.getOrNull(index) as? JsonObject ?: return null
        val unread = (list[UNREAD] as? JsonPrimitive)?.intOrNull
        val patchedRows = JsonArray(rows.mapIndexed { position, element -> if (position == index) row.readAt(readAt) else element })
        return list.withRows(patchedRows, unread = if (row.isUnread() && unread != null) (unread - 1).coerceAtLeast(0) else unread).toString()
    }

    fun allMarkedRead(listBody: String, readAt: String, unread: Int): String? {
        val list = jsonObjectOf(listBody) ?: return null
        val rows = list[DATA] as? JsonArray ?: return null
        val patchedRows = JsonArray(rows.map { element -> (element as? JsonObject)?.takeIf { it.isUnread() }?.readAt(readAt) ?: element })
        return list.withRows(patchedRows, unread).toString()
    }

    private fun jsonObjectOf(body: String): JsonObject? = runCatching { json.parseToJsonElement(body) }.getOrNull() as? JsonObject

    private fun JsonElement.notificationId(): String? = ((this as? JsonObject)?.get(ID) as? JsonPrimitive)?.contentOrNull.trimmedOrNull()

    private fun JsonObject.isUnread(): Boolean = (this[READ_AT] as? JsonPrimitive)?.contentOrNull.trimmedOrNull() == null

    private fun JsonObject.readAt(readAt: String): JsonObject = JsonObject(this + (READ_AT to JsonPrimitive(readAt)))

    private fun JsonObject.withRows(rows: JsonArray, unread: Int?): JsonObject =
        JsonObject(this + (DATA to rows) + listOfNotNull(unread?.let { UNREAD to JsonPrimitive(it) }))

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
