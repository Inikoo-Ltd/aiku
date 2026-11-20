package io.aiku.droid.data.offline

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.ForegroundInfo
import androidx.work.WorkerParameters
import io.aiku.droid.AikuDroidApplication

class OfflineSyncWorker(context: Context, parameters: WorkerParameters) : CoroutineWorker(context, parameters) {

    private val offlineWork: DeviceOfflineWork?
        get() = (applicationContext as? AikuDroidApplication)?.container?.offlineWork as? DeviceOfflineWork

    override suspend fun doWork(): Result {
        val work = offlineWork ?: return Result.success()
        val outcome = work.runInBackground()
        return when (outcome.status) {
            SyncRunStatus.RETRY -> if (runAttemptCount + 1 >= OfflineSyncRequests.MAX_ATTEMPTS) Result.failure() else Result.retry()
            SyncRunStatus.STOPPED, SyncRunStatus.COMPLETE -> Result.success()
        }
    }

    override suspend fun getForegroundInfo(): ForegroundInfo {
        val notices = offlineWork?.notices ?: error("Offline work is not available")
        return ForegroundInfo(FOREGROUND_NOTIFICATION_ID, notices.sending())
    }

    private companion object {
        const val FOREGROUND_NOTIFICATION_ID = 4108
    }
}
