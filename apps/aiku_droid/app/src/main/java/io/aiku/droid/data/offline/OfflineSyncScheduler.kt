package io.aiku.droid.data.offline

import android.content.Context
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequest
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.OutOfQuotaPolicy
import androidx.work.PeriodicWorkRequest
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.workDataOf
import java.util.concurrent.TimeUnit

interface SyncScheduler {
    fun schedule(reason: SyncReason)

    fun scheduleFollowUp()

    fun keepPeriodic(isNeeded: Boolean)
}

object OfflineSyncRequests {
    const val UNIQUE_SYNC = "offline_sync"
    const val UNIQUE_PERIODIC = "offline_sync_periodic"
    const val REASON = "reason"
    const val MAX_ATTEMPTS = 5
    const val BATCHES_PER_RUN = 4
    const val BACKOFF_MINUTES = 1L
    const val PERIODIC_HOURS = 6L

    fun oneTime(reason: SyncReason): OneTimeWorkRequest = OneTimeWorkRequestBuilder<OfflineSyncWorker>()
        .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
        .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, BACKOFF_MINUTES, TimeUnit.MINUTES)
        .setInputData(workDataOf(REASON to reason.name))
        .apply {
            if (reason == SyncReason.CLOCK_OUT) {
                setExpedited(OutOfQuotaPolicy.RUN_AS_NON_EXPEDITED_WORK_REQUEST)
            }
        }
        .build()

    fun policyFor(reason: SyncReason): ExistingWorkPolicy = when (reason) {
        SyncReason.CLOCK_OUT -> ExistingWorkPolicy.REPLACE
        else -> ExistingWorkPolicy.KEEP
    }

    fun periodic(): PeriodicWorkRequest = PeriodicWorkRequestBuilder<OfflineSyncWorker>(PERIODIC_HOURS, TimeUnit.HOURS)
        .setConstraints(
            Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .setRequiresBatteryNotLow(true)
                .build(),
        )
        .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, BACKOFF_MINUTES, TimeUnit.MINUTES)
        .setInputData(workDataOf(REASON to SyncReason.PERIODIC.name))
        .build()
}

class WorkManagerSyncScheduler(private val context: Context) : SyncScheduler {

    private val workManager: WorkManager get() = WorkManager.getInstance(context)

    @Volatile
    private var periodicNeeded: Boolean? = null

    override fun schedule(reason: SyncReason) {
        workManager.enqueueUniqueWork(OfflineSyncRequests.UNIQUE_SYNC, OfflineSyncRequests.policyFor(reason), OfflineSyncRequests.oneTime(reason))
    }

    override fun scheduleFollowUp() {
        workManager.enqueueUniqueWork(OfflineSyncRequests.UNIQUE_SYNC, ExistingWorkPolicy.APPEND_OR_REPLACE, OfflineSyncRequests.oneTime(SyncReason.QUEUED))
    }

    override fun keepPeriodic(isNeeded: Boolean) {
        if (periodicNeeded == isNeeded) {
            return
        }
        periodicNeeded = isNeeded
        if (isNeeded) {
            workManager.enqueueUniquePeriodicWork(OfflineSyncRequests.UNIQUE_PERIODIC, ExistingPeriodicWorkPolicy.KEEP, OfflineSyncRequests.periodic())
        } else {
            workManager.cancelUniqueWork(OfflineSyncRequests.UNIQUE_PERIODIC)
        }
    }
}
