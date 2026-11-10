package io.aiku.droid.ui.inbox

import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.navigation.StockDeliveryCheckingRoute

internal object InboxSamples {
    val today = listOf(
        InboxNotification("", "AR24812", " was cancelled while you were picking · stop picking", "Just now", R.drawable.ic_ui_close, Tone.Danger, DeliveryNoteCancelledRoute("AR24812"), isUnread = true),
        InboxNotification("", "AR24820", " was assigned to you", "5 min ago", R.drawable.ic_ui_delivery_note, Tone.Primary, DeliveryNotePickingRoute("AR24820"), isUnread = true),
        InboxNotification("", "SDaw-0093", " arrived at Goods-in bay 2", "1 h ago", R.drawable.ic_ui_goods_in, Tone.Success, StockDeliveryCheckingRoute("SDaw-0093"), isUnread = true),
        InboxNotification("Stock audit requested for ", "A-12-03", "", "2 h ago", R.drawable.ic_ui_clipboard, Tone.Warn, LocationRoute("A-12-03"), isUnread = true),
    )
    val earlier = listOf(
        InboxNotification("", "SKO-01402", " is out of stock", "Yesterday", R.drawable.ic_ui_warning, Tone.Danger, SkuRoute("SKO-01402"), isUnread = false),
        InboxNotification("", "AR24790", " dispatched", "Yesterday", R.drawable.ic_ui_check_double, Tone.Success, DeliveryNoteDispatchedRoute("AR24790"), isUnread = false),
    )
}
