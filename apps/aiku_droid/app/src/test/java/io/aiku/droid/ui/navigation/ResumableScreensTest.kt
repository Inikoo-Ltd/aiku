package io.aiku.droid.ui.navigation

import io.aiku.droid.R
import io.aiku.droid.data.settings.SavedScreen
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class ResumableScreensTest {

    private val deliveryNotes = DeliveryNotesRoute::class.qualifiedName.orEmpty()

    @Test
    fun `a saved list screen comes back with its filters and label`() {
        val target = ResumableScreens.restore(SavedScreen(type = deliveryNotes, payload = """{"bucket":"picking","channel":"web"}"""))

        assertEquals(DeliveryNotesRoute(bucket = "picking", channel = "web"), target?.route)
        assertEquals(R.string.section_delivery_notes, target?.label)
    }

    @Test
    fun `object routes come back as the same screen`() {
        val target = ResumableScreens.restore(SavedScreen(type = GoodsOutBacklogRoute::class.qualifiedName.orEmpty(), payload = "{}"))

        assertEquals(GoodsOutBacklogRoute, target?.route)
    }

    @Test
    fun `a picking session's notes, an unknown screen or a broken payload are not resumed`() {
        assertNull(ResumableScreens.restore(SavedScreen(type = deliveryNotes, payload = """{"sessionId":4}""")))
        assertNull(ResumableScreens.restore(SavedScreen(type = ConflictRoute::class.qualifiedName.orEmpty(), payload = """{"id":1,"title":"x"}""")))
        assertNull(ResumableScreens.restore(SavedScreen(type = deliveryNotes, payload = "not json")))
    }

    @Test
    fun `every resumable screen is listed once`() {
        val types = ResumableScreens.all.map { it.type }

        assertEquals(types.distinct(), types)
    }
}
