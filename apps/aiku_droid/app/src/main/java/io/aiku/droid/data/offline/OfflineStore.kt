package io.aiku.droid.data.offline

data class DownloadKey(val userId: Long, val type: OfflineRecordType, val id: Long)

class StoredDownload(
    val key: DownloadKey,
    val payload: ByteArray,
    val seenAt: Long,
    val downloadedAt: Long,
    val lastOpenedAt: Long,
)

class StoredAction(
    val localId: Long,
    val userId: Long,
    val warehouseId: Long,
    val operation: String?,
    val recordType: String?,
    val recordId: Long?,
    val payload: ByteArray?,
    val clientRequestId: String,
    val performedAt: Long,
    val timeSourceIsServer: Boolean,
    val seenAt: Long?,
    val sequence: Long?,
    val state: String,
    val result: ByteArray?,
    val syncedAt: Long?,
    val createdAt: Long,
)

interface OfflineDownloadStore {
    fun readDownload(key: DownloadKey): StoredDownload?

    fun writeDownload(download: StoredDownload)

    fun markDownloadOpened(key: DownloadKey, openedAt: Long)

    fun deleteDownload(key: DownloadKey)

    fun deleteDownloadsOpenedBefore(openedAt: Long)

    fun deleteDownloadsOfOtherUsers(userId: Long)

    fun deleteAllDownloads()
}

interface OfflineQueueStore {
    fun actions(): List<StoredAction>

    fun insertAction(action: StoredAction): Long

    fun updateAction(action: StoredAction)

    fun deleteAction(localId: Long)
}

interface OfflineStore : OfflineDownloadStore, OfflineQueueStore
