package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.R
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.StoredItemAuditActionKey
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import org.junit.Assert.assertEquals
import org.junit.Test

class OutboundActionLooksTest {

    @Test
    fun `each fulfilment outbound action key has the look of its group`() {
        val expected = mapOf(
            PalletReturnActionKey.START_PICKING to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
            PalletReturnActionKey.PICK to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
            PalletReturnActionKey.ADD_SHIPMENT to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_truck),
            PalletReturnActionKey.FINISH_PICKING to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
            PalletReturnActionKey.DISPATCH to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
            PalletReturnActionKey.PRINT_INSERTS to ButtonLook(ButtonStyle.Soft, R.drawable.ic_ui_print),
            PalletReturnActionKey.UNDO to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
            PalletReturnActionKey.NOT_PICKED to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_x_circle),
            StoredItemAuditActionKey.COUNT to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_check),
            StoredItemAuditActionKey.COMPLETE to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
        )

        assertEquals(expected, fulfilmentOutboundActionLooks)
    }

    @Test
    fun `an unknown key falls back to the neutral look`() {
        assertEquals(ButtonLook(ButtonStyle.Outline), fulfilmentOutboundActionLook(StoredItemAuditActionKey.ADD_ITEM))
    }
}
