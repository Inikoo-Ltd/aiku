package io.aiku.droid.data.cache

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxResponses
import io.aiku.droid.data.api.WarehouseResponses
import io.aiku.droid.data.api.WarehousesResult
import okhttp3.HttpUrl
import okhttp3.Interceptor
import okhttp3.Response

class ScreenCacheInterceptor(private val cache: () -> ScreenCache) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val response = chain.proceed(request)
        val route = mobileRouteOf(request.url) ?: return response
        val token = request.header(AUTHORIZATION)?.removePrefix(BEARER_PREFIX)?.takeIf { it.isNotBlank() } ?: return response
        val screenCache = cache()
        val userId = screenCache.userIdFor(token) ?: return response

        when {
            response.code == HTTP_UNAUTHORISED -> screenCache.clear()
            request.method == GET -> ScreenReads(screenCache, userId, request.url, route).record(response)
            response.isSuccessful -> ScreenWrites(screenCache, userId, route).record(response)
        }
        return response
    }

    private fun mobileRouteOf(url: HttpUrl): List<String>? {
        val segments = url.pathSegments.filter { it.isNotEmpty() }
        val start = segments.windowed(MOBILE_PREFIX.size).indexOf(MOBILE_PREFIX)
        return if (start < 0) null else segments.drop(start + MOBILE_PREFIX.size)
    }

    private class ScreenReads(
        private val cache: ScreenCache,
        private val userId: Long,
        private val url: HttpUrl,
        private val route: List<String>,
    ) {
        fun record(response: Response) {
            if (route == listOf(WAREHOUSES)) {
                keepListedWarehouses(response)
                return
            }
            val (warehouseId, screen) = cachedScreen() ?: return
            val key = ScreenCacheKey(userId = userId, warehouseId = warehouseId, screen = screen.key)
            when (response.code) {
                HTTP_OK -> {
                    val body = response.peekBody(MAX_CACHED_BYTES).string()
                    if (screen.parse(body) != null) cache.replace(key, body) else cache.forget(key)
                }
                HTTP_FORBIDDEN, HTTP_NOT_FOUND -> cache.forget(key)
            }
        }

        private fun keepListedWarehouses(response: Response) {
            if (response.code != HTTP_OK) {
                return
            }
            val listed = WarehouseResponses.parse(HTTP_OK, response.peekBody(MAX_CACHED_BYTES).string()) as? WarehousesResult.Loaded ?: return
            cache.keepWarehouses(userId, listed.warehouses.map { it.id }.toSet())
        }

        private fun cachedScreen(): Pair<Long, CachedScreen<*>>? = when {
            route.size == 3 && route[0] == WAREHOUSES -> route[1].toLongOrNull()?.let { warehouseId ->
                when (route[2]) {
                    "my-work" -> warehouseId to CachedScreens.myWork
                    "badges" -> warehouseId to CachedScreens.workBadges
                    "dashboard" -> warehouseId to CachedScreens.warehouseDashboard
                    else -> null
                }
            }
            route == listOf(INBOX) && (url.queryParameter("page") ?: FIRST_PAGE) == FIRST_PAGE ->
                CachedScreens.USER_SCOPED to CachedScreens.inbox(url.queryParameter("filter") ?: InboxResponses.DEFAULT_FILTER)
            route == listOf(CLOCKING) -> CachedScreens.USER_SCOPED to CachedScreens.clockingStatus
            route == listOf(CLOCKING, "timesheets") && url.queryParameter("from") == null && url.queryParameter("to") == null ->
                CachedScreens.USER_SCOPED to CachedScreens.clockingThisWeek
            else -> null
        }
    }

    private class ScreenWrites(
        private val cache: ScreenCache,
        private val userId: Long,
        private val route: List<String>,
    ) {
        fun record(response: Response) {
            when (route.firstOrNull()) {
                INBOX -> recordInbox(response)
                CLOCKING -> cache.forget(userId) { it in CachedScreens.clockingKeys }
                in ACCOUNT_ROUTES -> Unit
                else -> cache.forget(userId) { it in CachedScreens.warehouseWorkKeys }
            }
        }

        private fun recordInbox(response: Response) {
            val readAt = cache.now().toString()
            when {
                route == listOf(INBOX, "read-all") -> {
                    val unread = (InboxResponses.parseUnread(HTTP_OK, response.peekBody(MAX_CACHED_BYTES).string()) as? GoodsOutResult.Loaded)?.value
                    cache.edit(userId, CachedScreens::isInbox) { body -> unread?.let { InboxResponses.allMarkedRead(body, readAt, it) } }
                }
                route.size == 3 && route[2] == "read" -> {
                    val read = (InboxResponses.parseNotification(HTTP_OK, response.peekBody(MAX_CACHED_BYTES).string()) as? GoodsOutResult.Loaded)?.value
                    cache.edit(userId, CachedScreens::isInbox) { body -> InboxResponses.markedRead(body, route[1], read?.readAt ?: readAt) }
                }
                else -> cache.forget(userId, CachedScreens::isInbox)
            }
            cache.forget(userId) { it == CachedScreens.workBadges.key }
        }
    }

    private companion object {
        val MOBILE_PREFIX = listOf("app", "api", "mobile")
        val ACCOUNT_ROUTES = setOf("login", "logout", "password", "profile", "push-token", "chat", "app-version")
        const val WAREHOUSES = "warehouses"
        const val INBOX = "inbox"
        const val CLOCKING = "clocking"
        const val FIRST_PAGE = "1"
        const val GET = "GET"
        const val AUTHORIZATION = "Authorization"
        const val BEARER_PREFIX = "Bearer "
        const val HTTP_OK = 200
        const val HTTP_UNAUTHORISED = 401
        const val HTTP_FORBIDDEN = 403
        const val HTTP_NOT_FOUND = 404
        const val MAX_CACHED_BYTES = 4L * 1024 * 1024
    }
}
