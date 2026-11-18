package io.aiku.droid.data.api

import okhttp3.HttpUrl.Companion.toHttpUrl

object WorkEvent {
    const val ASSIGNED = "assigned"
    const val TAKEN_OVER = "taken_over"
    const val UNASSIGNED = "unassigned"
    const val CANCELLED = "cancelled"
    const val READY_AGAIN = "ready_again"
    const val MODIFIED = "modified"
}

data class WorkRecord(
    val kind: MyWorkKind,
    val id: Long,
    val reference: String = "",
    val state: String = "",
    val warehouseId: Long? = null,
    val typeGroup: String? = null,
)

data class InboxNotification(
    val id: String,
    val kind: String = "",
    val event: String = "",
    val title: String = "",
    val body: String = "",
    val createdAt: String? = null,
    val readAt: String? = null,
    val target: WorkRecord? = null,
) {
    val isUnread: Boolean get() = readAt == null
}

data class InboxPage(val notifications: List<InboxNotification>, val meta: ListMeta, val unread: Int? = null)

interface InboxApi {
    suspend fun notifications(token: String, page: Int): GoodsOutResult<InboxPage>

    suspend fun unreadCount(token: String): GoodsOutResult<Int>

    suspend fun markRead(token: String, notificationId: String): GoodsOutResult<InboxNotification>

    suspend fun markAllRead(token: String): GoodsOutResult<Int>
}

class OkHttpInboxApi(private val http: OkHttpMobileApi) : InboxApi {

    private val inboxUrl = "${http.mobileApiUrl}/inbox"

    override suspend fun notifications(token: String, page: Int): GoodsOutResult<InboxPage> =
        http.execute(http.get(http.urlWith(inboxUrl, InboxResponses.listParameters(page)), token))
            ?.let { (statusCode, body) -> InboxResponses.parseList(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun unreadCount(token: String): GoodsOutResult<Int> =
        http.execute(http.get("$inboxUrl/unread-count", token))
            ?.let { (statusCode, body) -> InboxResponses.parseUnread(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun markRead(token: String, notificationId: String): GoodsOutResult<InboxNotification> {
        val url = inboxUrl.toHttpUrl().newBuilder().addPathSegment(notificationId).addPathSegment("read").build().toString()
        return http.execute(http.post(url, body = "{}", bearerToken = token))
            ?.let { (statusCode, body) -> InboxResponses.parseNotification(statusCode, body) }
            ?: GoodsOutResult.Unreachable
    }

    override suspend fun markAllRead(token: String): GoodsOutResult<Int> =
        http.execute(http.post("$inboxUrl/read-all", body = "{}", bearerToken = token))
            ?.let { (statusCode, body) -> InboxResponses.parseUnread(statusCode, body) }
            ?: GoodsOutResult.Unreachable
}
