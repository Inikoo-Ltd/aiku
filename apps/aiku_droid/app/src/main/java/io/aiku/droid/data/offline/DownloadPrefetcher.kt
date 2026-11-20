package io.aiku.droid.data.offline

import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import java.time.Duration

class DownloadPrefetcher(
    private val api: OfflineSyncApi,
    private val downloads: OfflineDownloads,
    private val scope: CoroutineScope,
    private val ioDispatcher: CoroutineDispatcher = Dispatchers.IO,
) {

    @Volatile
    private var running: Job? = null

    @Synchronized
    fun prefetch(request: DownloadPrefetch) {
        if (running?.isActive == true) {
            return
        }
        running = scope.launch(ioDispatcher) {
            val freshFrom = downloads.now() - FRESH_FOR
            for ((record, warehouseId) in request.records) {
                if (downloads.userIdFor(request.token) != request.userId) {
                    break
                }
                val downloadedAt = downloads.downloadedAt(request.userId, record)
                if (downloadedAt != null && downloadedAt > freshFrom) {
                    continue
                }
                if (!api.refetch(request.token, OfflineRoutes.detailPath(record, warehouseId))) {
                    break
                }
            }
        }
    }

    private companion object {
        val FRESH_FOR: Duration = Duration.ofMinutes(1)
    }
}
