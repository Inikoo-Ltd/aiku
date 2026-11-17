package io.aiku.droid.ui.components

import io.aiku.droid.R
import org.junit.Assert.assertEquals
import org.junit.Test

class ButtonLookTest {

    @Test
    fun `a red action confirms with the filled red and keeps its icon`() {
        assertEquals(ButtonLook(ButtonStyle.DangerStrong, R.drawable.ic_ui_trash), ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_trash).forConfirm())
        assertEquals(ButtonLook(ButtonStyle.DangerStrong, R.drawable.ic_ui_x_circle), ButtonLook(ButtonStyle.Danger).forConfirm())
    }

    @Test
    fun `a helper confirms as the main step and the other groups keep their look`() {
        assertEquals(ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_check_double), ButtonLook(ButtonStyle.Soft, R.drawable.ic_ui_check_double).forConfirm())
        listOf(ButtonStyle.Primary, ButtonStyle.Finish, ButtonStyle.Caution, ButtonStyle.Outline, ButtonStyle.Scan).forEach { style ->
            assertEquals(ButtonLook(style, R.drawable.ic_ui_undo), ButtonLook(style, R.drawable.ic_ui_undo).forConfirm())
        }
    }

    @Test
    fun `only the first footer action is filled, later main and finish steps turn into helpers`() {
        assertEquals(ButtonStyle.Finish, ButtonLook(ButtonStyle.Finish).inFooter(isFirst = true).style)
        assertEquals(ButtonStyle.Primary, ButtonLook(ButtonStyle.Primary).inFooter(isFirst = true).style)
        assertEquals(ButtonLook(ButtonStyle.Soft, R.drawable.ic_ui_check), ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check).inFooter(isFirst = false))
        assertEquals(ButtonStyle.Soft, ButtonLook(ButtonStyle.Primary).inFooter(isFirst = false).style)
        assertEquals(ButtonStyle.Caution, ButtonLook(ButtonStyle.Caution).inFooter(isFirst = true).style)
        assertEquals(ButtonStyle.Caution, ButtonLook(ButtonStyle.Caution).inFooter(isFirst = false).style)
        assertEquals(ButtonStyle.Danger, ButtonLook(ButtonStyle.Danger).inFooter(isFirst = false).style)
    }

    @Test
    fun `icon tiles take the tone of their group`() {
        assertEquals(Tone.Success, ButtonStyle.Finish.tileTone())
        assertEquals(Tone.Warn, ButtonStyle.Caution.tileTone())
        assertEquals(Tone.Danger, ButtonStyle.Danger.tileTone())
        assertEquals(Tone.Danger, ButtonStyle.DangerStrong.tileTone())
        assertEquals(Tone.Primary, ButtonStyle.Outline.tileTone())
    }
}
