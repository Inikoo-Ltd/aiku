package io.aiku.droid.data.cache

import io.aiku.droid.data.session.ByteCipher
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.time.Duration
import java.time.Instant

data class CachedCopy<out T>(val value: T, val fetchedAt: Instant)

class ScreenCache(
    private val store: ScreenCacheStore,
    private val cipher: ByteCipher,
    private val sessions: SessionRepository,
    private val clock: () -> Instant = Instant::now,
    private val ioDispatcher: CoroutineDispatcher = Dispatchers.IO,
) {

    @Volatile
    private var lastCleanUp: Instant? = null

    fun start(scope: CoroutineScope) {
        scope.launch(ioDispatcher) {
            cleanUpIfDue()
            sessions.session
                .map { it?.userId }
                .distinctUntilChanged()
                .collect { userId ->
                    if (userId == null) {
                        store.deleteAll()
                    } else {
                        store.deleteOtherUsers(userId)
                    }
                }
        }
    }

    suspend fun <T> read(warehouseId: Long, screen: CachedScreen<T>): CachedCopy<T>? = withContext(ioDispatcher) {
        val userId = sessions.session.value?.userId ?: return@withContext null
        cleanUpIfDue()
        val key = ScreenCacheKey(userId = userId, warehouseId = warehouseId, screen = screen.key)
        val stored = store.read(key) ?: return@withContext null
        val value = bodyOf(stored)?.let { body -> runCatching { screen.parse(body) }.getOrNull() }
        if (value == null) {
            store.delete(key)
            return@withContext null
        }
        store.markOpened(key, clock().toEpochMilli())
        CachedCopy(value = value, fetchedAt = Instant.ofEpochMilli(stored.fetchedAt))
    }

    internal fun userIdFor(token: String): Long? = sessions.session.value?.takeIf { it.token == token }?.userId

    internal fun replace(key: ScreenCacheKey, body: String) {
        val payload = encrypted(body)
        if (payload == null) {
            store.delete(key)
            return
        }
        val now = clock().toEpochMilli()
        store.write(StoredScreen(key = key, payload = payload, fetchedAt = now, lastOpenedAt = now))
    }

    internal fun edit(userId: Long, screens: (String) -> Boolean, change: (String) -> String?) {
        store.keys(userId).filter { screens(it.screen) }.forEach { key ->
            val stored = store.read(key) ?: return@forEach
            val payload = bodyOf(stored)?.let { body -> runCatching { change(body) }.getOrNull() }?.let(::encrypted)
            if (payload == null) {
                store.delete(key)
            } else {
                store.write(StoredScreen(key = key, payload = payload, fetchedAt = stored.fetchedAt, lastOpenedAt = stored.lastOpenedAt))
            }
        }
    }

    internal fun forget(key: ScreenCacheKey) {
        store.delete(key)
    }

    internal fun forget(userId: Long, screens: (String) -> Boolean) {
        store.keys(userId).filter { screens(it.screen) }.forEach(store::delete)
    }

    internal fun keepWarehouses(userId: Long, warehouseIds: Set<Long>) {
        store.deleteWarehousesOutside(userId, warehouseIds)
    }

    internal fun clear() {
        store.deleteAll()
    }

    internal fun now(): Instant = clock()

    private fun cleanUpIfDue() {
        val now = clock()
        val previous = lastCleanUp
        if (previous != null && now < previous + CLEAN_UP_INTERVAL) {
            return
        }
        lastCleanUp = now
        store.deleteOpenedBefore((now - KEEP_UNOPENED_FOR).toEpochMilli())
        sessions.session.value?.userId?.let(store::deleteOtherUsers)
    }

    private fun bodyOf(stored: StoredScreen): String? = runCatching { cipher.decrypt(stored.payload).toString(Charsets.UTF_8) }.getOrNull()

    private fun encrypted(body: String): ByteArray? = runCatching { cipher.encrypt(body.toByteArray(Charsets.UTF_8)) }.getOrNull()

    private companion object {
        val KEEP_UNOPENED_FOR: Duration = Duration.ofDays(7)
        val CLEAN_UP_INTERVAL: Duration = Duration.ofDays(1)
    }
}
