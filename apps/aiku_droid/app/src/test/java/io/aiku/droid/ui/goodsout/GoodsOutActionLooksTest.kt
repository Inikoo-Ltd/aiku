package io.aiku.droid.ui.goodsout

import io.aiku.droid.R
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.data.api.SessionActionKey
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import java.lang.reflect.Modifier

class GoodsOutActionLooksTest {

    private val main = ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next)
    private val finish = ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check)
    private val stepBack = ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo)
    private val neutral = ButtonLook(ButtonStyle.Outline)

    @Test
    fun `each goods out action key has the look of its group`() {
        val expected = mapOf(
            NoteActionKey.START_PICKING to main,
            NoteActionKey.RESUME_PICKING to main,
            NoteActionKey.START_PACKING to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_box),
            NoteActionKey.ADD_SHIPMENT to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_truck),
            NoteActionKey.GET_EXTERNAL_SHIPMENT to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_truck),
            NoteActionKey.PUT_BACK_INCOMPLETE_SETS to main,
            NoteActionKey.SET_AS_PICKED to finish,
            NoteActionKey.SET_AS_PACKED to finish,
            NoteActionKey.AUTO_FINISH_WAITING to finish,
            NoteActionKey.FINALISE_AND_DISPATCH to finish,
            NoteActionKey.FINALISE to finish,
            NoteActionKey.DISPATCH to finish,
            NoteActionKey.TAKE_OVER to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_unlock),
            NoteActionKey.BACK_TO_PICKING to stepBack,
            NoteActionKey.UNDO_SET_AS_PICKED to stepBack,
            NoteActionKey.UNDO_PACKING to stepBack,
            NoteActionKey.UNPACK to stepBack,
            NoteActionKey.REMOVE_PICKER to stepBack,
            NoteActionKey.CANCEL to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_x_circle),
            NoteActionKey.ASSIGN_PICKER to neutral,
            NoteActionKey.CHANGE_PICKER to neutral,
            NoteActionKey.CHANGE_PACKER to neutral,
            NoteActionKey.CHANGE_TROLLEY to neutral,
            NoteActionKey.CHANGE_PICKED_BAY to neutral,
            NoteActionKey.EDIT_DELIVERY_ADDRESS to neutral,
            SessionActionKey.SCAN_PICK to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_scan),
            SessionActionKey.SCAN_PACK to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_scan),
            SessionActionKey.ADD_DELIVERY_NOTES to ButtonLook(ButtonStyle.Soft, R.drawable.ic_ui_plus),
            SessionActionKey.REMOVE_DELIVERY_NOTES to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_trash),
        )

        assertEquals(expected, goodsOutActionLooks)
    }

    @Test
    fun `every delivery note and picking session key has a look`() {
        val keys = keysOf(NoteActionKey::class.java) + keysOf(SessionActionKey::class.java)
        val missing = keys.filterNot { it in goodsOutActionLooks }

        assertTrue(missing.joinToString(), missing.isEmpty())
    }

    @Test
    fun `an unknown key falls back to the neutral look`() {
        assertEquals(neutral, goodsOutActionLook("something_new"))
    }

    private fun keysOf(type: Class<*>): List<String> = type.declaredFields
        .filter { Modifier.isStatic(it.modifiers) && it.type == String::class.java }
        .map { it.get(null) as String }
}
