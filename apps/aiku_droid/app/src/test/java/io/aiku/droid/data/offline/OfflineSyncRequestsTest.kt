package io.aiku.droid.data.offline

import androidx.work.BackoffPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.concurrent.TimeUnit

class OfflineSyncRequestsTest {

    @Test
    fun `a one-time sync waits for a network and backs off exponentially from one minute`() {
        val request = OfflineSyncRequests.oneTime(SyncReason.QUEUED)

        assertEquals(NetworkType.CONNECTED, request.workSpec.constraints.requiredNetworkType)
        assertFalse(request.workSpec.constraints.requiresBatteryNotLow())
        assertEquals(BackoffPolicy.EXPONENTIAL, request.workSpec.backoffPolicy)
        assertEquals(TimeUnit.MINUTES.toMillis(1), request.workSpec.backoffDelayDuration)
        assertFalse(request.workSpec.expedited)
        assertEquals(ExistingWorkPolicy.KEEP, OfflineSyncRequests.policyFor(SyncReason.QUEUED))
        assertEquals(ExistingWorkPolicy.KEEP, OfflineSyncRequests.policyFor(SyncReason.RECONNECTED))
    }

    @Test
    fun `clock-out asks for expedited work that replaces a waiting run`() {
        val request = OfflineSyncRequests.oneTime(SyncReason.CLOCK_OUT)

        assertTrue(request.workSpec.expedited)
        assertEquals(NetworkType.CONNECTED, request.workSpec.constraints.requiredNetworkType)
        assertEquals(ExistingWorkPolicy.REPLACE, OfflineSyncRequests.policyFor(SyncReason.CLOCK_OUT))
    }

    @Test
    fun `the safety net runs every six hours on a network with the battery not low`() {
        val request = OfflineSyncRequests.periodic()

        assertEquals(TimeUnit.HOURS.toMillis(6), request.workSpec.intervalDuration)
        assertEquals(NetworkType.CONNECTED, request.workSpec.constraints.requiredNetworkType)
        assertTrue(request.workSpec.constraints.requiresBatteryNotLow())
    }

    @Test
    fun `a background run sends at most four batches and gives up after five attempts`() {
        assertEquals(4, OfflineSyncRequests.BATCHES_PER_RUN)
        assertEquals(5, OfflineSyncRequests.MAX_ATTEMPTS)
        assertEquals(50, OfflineSyncEngine.MAX_BATCH_SIZE)
    }
}
