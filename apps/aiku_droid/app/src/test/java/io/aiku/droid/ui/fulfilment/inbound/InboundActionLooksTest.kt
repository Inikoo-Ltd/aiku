package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.R
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import org.junit.Assert.assertEquals
import org.junit.Test

class InboundActionLooksTest {

    @Test
    fun `each fulfilment inbound action key has the look of its group`() {
        val expected = mapOf(
            FulfilmentInboundActionKey.RECEIVE to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
            FulfilmentInboundActionKey.START_BOOKING to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_pallet),
            FulfilmentInboundActionKey.BOOK_IN to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_location),
            FulfilmentInboundActionKey.FINISH_BOOKING to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
            FulfilmentInboundActionKey.UNDO_NOT_RECEIVED to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
            FulfilmentInboundActionKey.NOT_RECEIVED to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_x_circle),
            FulfilmentInboundActionKey.DAMAGED to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_warning),
            FulfilmentInboundActionKey.LOST to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_warning),
        )

        assertEquals(expected, fulfilmentInboundActionLooks)
    }

    @Test
    fun `an unknown key falls back to the neutral look`() {
        assertEquals(ButtonLook(ButtonStyle.Outline), fulfilmentInboundActionLook("something_new"))
    }
}
