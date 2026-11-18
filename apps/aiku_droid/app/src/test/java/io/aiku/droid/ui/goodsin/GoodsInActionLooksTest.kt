package io.aiku.droid.ui.goodsin

import io.aiku.droid.R
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import org.junit.Assert.assertEquals
import org.junit.Test

class GoodsInActionLooksTest {

    @Test
    fun `each goods in action key has the look of its group`() {
        val expected = mapOf(
            GoodsInActionKey.RECEIVE to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
            GoodsInActionKey.START to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
            GoodsInActionKey.PUT_BACK to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
            GoodsInActionKey.PLACE to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_location),
            GoodsInActionKey.CHECK to ButtonLook(ButtonStyle.Soft, R.drawable.ic_ui_check_double),
            GoodsInActionKey.SET_AS_RETURNED to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
            GoodsInActionKey.UNRECEIVE to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
            GoodsInActionKey.UNDO to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
        )

        assertEquals(expected, goodsInActionLooks)
    }

    @Test
    fun `an unknown key falls back to the neutral look`() {
        assertEquals(ButtonLook(ButtonStyle.Outline), goodsInActionLook(GoodsInActionKey.CHECKLIST))
    }
}
