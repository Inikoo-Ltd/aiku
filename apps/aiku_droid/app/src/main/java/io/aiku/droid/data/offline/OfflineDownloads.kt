package io.aiku.droid.data.offline

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

class OfflineDownloads(
    private val store: OfflineDownloadStore,
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
                        store.deleteAllDownloads()
                    } else {
                        store.deleteDownloadsOfOtherUsers(userId)
                    }
                }
        }
    }

    suspend fun read(record: OfflineRecordKey): DownloadedRecord? = withContext(ioDispatcher) {
        val userId = sessions.session.value?.userId ?: return@withContext null
        cleanUpIfDue()
        val key = DownloadKey(userId = userId, type = record.type, id = record.id)
        val stored = store.readDownload(key) ?: return@withContext null
        val body = runCatching { cipher.decrypt(stored.payload).toString(Charsets.UTF_8) }.getOrNull()
        if (body == null) {
            store.deleteDownload(key)
            return@withContext null
        }
        store.markDownloadOpened(key, clock().toEpochMilli())
        DownloadedRecord(body = body, seenAt = Instant.ofEpochMilli(stored.seenAt), downloadedAt = Instant.ofEpochMilli(stored.downloadedAt))
    }

    fun downloadedAt(userId: Long, record: OfflineRecordKey): Instant? =
        store.readDownload(DownloadKey(userId = userId, type = record.type, id = record.id))?.let { Instant.ofEpochMilli(it.downloadedAt) }

    internal fun userIdFor(token: String): Long? = sessions.session.value?.takeIf { it.token == token }?.userId

    internal fun replace(userId: Long, record: OfflineRecordKey, body: String, seenAt: Instant?) {
        val key = DownloadKey(userId = userId, type = record.type, id = record.id)
        val payload = runCatching { cipher.encrypt(body.toByteArray(Charsets.UTF_8)) }.getOrNull()
        if (payload == null) {
            store.deleteDownload(key)
            return
        }
        val now = clock()
        val opened = store.readDownload(key)?.lastOpenedAt ?: now.toEpochMilli()
        store.writeDownload(
            StoredDownload(key = key, payload = payload, seenAt = (seenAt ?: now).toEpochMilli(), downloadedAt = now.toEpochMilli(), lastOpenedAt = opened),
        )
    }

    internal fun forget(userId: Long, record: OfflineRecordKey) {
        store.deleteDownload(DownloadKey(userId = userId, type = record.type, id = record.id))
    }

    internal fun clear() {
        store.deleteAllDownloads()
    }

    internal fun now(): Instant = clock()

    private fun cleanUpIfDue() {
        val now = clock()
        val previous = lastCleanUp
        if (previous != null && now < previous + CLEAN_UP_INTERVAL) {
            return
        }
        lastCleanUp = now
        store.deleteDownloadsOpenedBefore((now - KEEP_UNOPENED_FOR).toEpochMilli())
        sessions.session.value?.userId?.let(store::deleteDownloadsOfOtherUsers)
    }

    private companion object {
        val KEEP_UNOPENED_FOR: Duration = Duration.ofDays(7)
        val CLEAN_UP_INTERVAL: Duration = Duration.ofDays(1)
    }
}
