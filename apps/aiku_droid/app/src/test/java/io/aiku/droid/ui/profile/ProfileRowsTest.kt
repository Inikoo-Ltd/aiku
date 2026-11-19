package io.aiku.droid.ui.profile

import androidx.core.app.NotificationManagerCompat
import io.aiku.droid.R
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ProfileRowsTest {

    @Test
    fun `notifications are on only when the app may post and the work channel is not blocked`() {
        assertTrue(notificationsAllowed(isAppAllowed = true, workChannelImportance = NotificationManagerCompat.IMPORTANCE_HIGH))
        assertTrue(notificationsAllowed(isAppAllowed = true, workChannelImportance = null))
        assertFalse(notificationsAllowed(isAppAllowed = true, workChannelImportance = NotificationManagerCompat.IMPORTANCE_NONE))
        assertFalse(notificationsAllowed(isAppAllowed = false, workChannelImportance = NotificationManagerCompat.IMPORTANCE_HIGH))
        assertFalse(notificationsAllowed(isAppAllowed = false, workChannelImportance = null))
    }

    @Test
    fun `the notifications row says on or off`() {
        assertEquals(R.string.app_notifications_on, notificationsValue(isAllowed = true))
        assertEquals(R.string.app_notifications_off, notificationsValue(isAllowed = false))
    }
}
