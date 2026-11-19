package io.aiku.droid.ui.inbox

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxApi
import io.aiku.droid.data.api.InboxNotification
import io.aiku.droid.data.api.InboxPage
import io.aiku.droid.data.api.ListMeta
import kotlinx.coroutines.CompletableDeferred

class FakeInboxApi : InboxApi {
    val pageRequests = mutableListOf<Int>()
    var pages: (Int) -> GoodsOutResult<InboxPage> = { GoodsOutResult.Loaded(InboxPage(emptyList(), ListMeta(currentPage = it, lastPage = 1), unread = 0)) }
    var pageGate: CompletableDeferred<Unit>? = null
    val unreadRequests = mutableListOf<String>()
    var unread: GoodsOutResult<Int> = GoodsOutResult.Loaded(0)
    val readRequests = mutableListOf<String>()
    var readResult: (String) -> GoodsOutResult<InboxNotification> = { GoodsOutResult.Loaded(InboxNotification(id = it, readAt = "2026-11-18T09:05:00Z")) }
    var readAllRequests = 0
    var readAllResult: GoodsOutResult<Int> = GoodsOutResult.Loaded(0)

    override suspend fun notifications(token: String, page: Int): GoodsOutResult<InboxPage> {
        pageRequests += page
        pageGate?.await()
        return pages(page)
    }

    override suspend fun unreadCount(token: String): GoodsOutResult<Int> {
        unreadRequests += token
        return unread
    }

    override suspend fun markRead(token: String, notificationId: String): GoodsOutResult<InboxNotification> {
        readRequests += notificationId
        return readResult(notificationId)
    }

    override suspend fun markAllRead(token: String): GoodsOutResult<Int> {
        readAllRequests++
        return readAllResult
    }
}
