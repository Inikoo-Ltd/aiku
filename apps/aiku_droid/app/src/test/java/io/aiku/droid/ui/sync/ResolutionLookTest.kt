package io.aiku.droid.ui.sync

import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.ui.components.ButtonStyle
import org.junit.Assert.assertEquals
import org.junit.Test

class ResolutionLookTest {

    @Test
    fun `conflict choices are styled by what they do`() {
        assertEquals(ButtonStyle.Primary, resolutionLook(OfflineResolution.Retry).style)
        assertEquals(ButtonStyle.Primary, resolutionLook(OfflineResolution.Recount).style)
        assertEquals(ButtonStyle.Primary, resolutionLook(OfflineResolution.PutBack).style)
        assertEquals(ButtonStyle.Finish, resolutionLook(OfflineResolution.Done).style)
        assertEquals(ButtonStyle.Caution, resolutionLook(OfflineResolution.KeepCount).style)
        assertEquals(ButtonStyle.Danger, resolutionLook(OfflineResolution.Discard).style)
    }
}
