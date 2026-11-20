package io.aiku.droid.data.offline

import io.aiku.droid.data.api.GoodsInResponses
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.MyWorkResponses
import io.aiku.droid.data.api.ReturnState
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.longOrNull
import okhttp3.Interceptor
import okhttp3.Response
import java.time.Instant

data class DownloadPrefetch(val token: String, val userId: Long, val records: List<Pair<OfflineRecordKey, Long>>)

class OfflineDownloadInterceptor(
    private val downloads: () -> OfflineDownloads?,
    private val prefetcher: () -> DownloadPrefetcher?,
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val response = chain.proceed(request)
        val route = OfflineRoutes.mobileRouteOf(request.url) ?: return response
        val token = request.header(AUTHORIZATION)?.removePrefix(BEARER_PREFIX)?.takeIf { it.isNotBlank() } ?: return response
        val store = downloads() ?: return response
        val userId = store.userIdFor(token) ?: return response
        val seenAt = response.headers.getDate(DATE_HEADER)?.toInstant()

        when {
            response.code == HTTP_UNAUTHORISED -> store.clear()
            request.method == GET -> recordRead(store, userId, token, route, response, seenAt)
            response.isSuccessful -> recordWrite(store, userId, route, response, seenAt)
        }
        return response
    }

    private fun recordRead(store: OfflineDownloads, userId: Long, token: String, route: List<String>, response: Response, seenAt: Instant?) {
        val record = OfflineRoutes.detailRecordOf(route)
        if (record != null) {
            when (response.code) {
                HTTP_OK -> store.replace(userId, record, response.peekBody(MAX_DOWNLOAD_BYTES).string(), seenAt)
                HTTP_FORBIDDEN, HTTP_NOT_FOUND -> store.forget(userId, record)
            }
            return
        }
        if (response.code != HTTP_OK) {
            return
        }
        OfflineRoutes.warehouseListOf(route, MY_WORK)?.let { warehouseId ->
            val notes = assignedNotes(response.peekBody(MAX_DOWNLOAD_BYTES).string())
            if (notes.isNotEmpty()) {
                prefetcher()?.prefetch(DownloadPrefetch(token, userId, notes.map { OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, it) to warehouseId }))
            }
            return
        }
        OfflineRoutes.warehouseListOf(route, RETURNS)?.let { warehouseId ->
            val returns = handledReturns(response.peekBody(MAX_DOWNLOAD_BYTES).string(), userId)
            if (returns.isNotEmpty()) {
                prefetcher()?.prefetch(DownloadPrefetch(token, userId, returns.map { OfflineRecordKey(OfflineRecordType.RETURN, it) to warehouseId }))
            }
        }
    }

    private fun recordWrite(store: OfflineDownloads, userId: Long, route: List<String>, response: Response, seenAt: Instant?) {
        val record = OfflineRoutes.recordUnder(route) ?: return
        val body = response.peekBody(MAX_DOWNLOAD_BYTES).string()
        val answeredId = runCatching { ((Json.parseToJsonElement(body) as? JsonObject)?.get(DATA) as? JsonObject)?.get(ID)?.jsonPrimitive?.longOrNull }.getOrNull()
        if (answeredId == record.id) {
            store.replace(userId, record, body, seenAt)
        }
    }

    private fun assignedNotes(body: String): List<Long> {
        val page = (MyWorkResponses.parseMyWork(HTTP_OK, body) as? GoodsOutResult.Loaded)?.value ?: return emptyList()
        return page.rows
            .filter { it.kind == MyWorkKind.DeliveryNote && it.state in OFFLINE_NOTE_STATES }
            .map { it.id }
            .distinct()
            .take(MAX_PREFETCHED_NOTES)
    }

    private fun handledReturns(body: String, userId: Long): List<Long> {
        val page = (GoodsInResponses.parseReturns(HTTP_OK, body) as? GoodsOutResult.Loaded)?.value ?: return emptyList()
        return page.rows
            .filter { it.state == ReturnState.RETURNING && it.handler?.id == userId }
            .map { it.id }
            .distinct()
    }

    companion object {
        const val MAX_PREFETCHED_NOTES = 30
        val OFFLINE_NOTE_STATES = setOf("handling", "handling_blocked", "packing")
        private const val MY_WORK = "my-work"
        private const val RETURNS = "returns"
        private const val DATA = "data"
        private const val ID = "id"
        private const val GET = "GET"
        private const val AUTHORIZATION = "Authorization"
        private const val BEARER_PREFIX = "Bearer "
        private const val DATE_HEADER = "Date"
        private const val HTTP_OK = 200
        private const val HTTP_UNAUTHORISED = 401
        private const val HTTP_FORBIDDEN = 403
        private const val HTTP_NOT_FOUND = 404
        private const val MAX_DOWNLOAD_BYTES = 4L * 1024 * 1024
    }
}
