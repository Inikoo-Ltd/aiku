package io.aiku.droid.ui.live

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import io.aiku.droid.data.live.LiveChannels
import io.aiku.droid.data.live.LiveEventNames
import io.aiku.droid.data.live.NoLiveChannels
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.filter

private const val SETTLE_MILLIS = 600L

val LocalLiveChannels = staticCompositionLocalOf<LiveChannels> { NoLiveChannels }

/**
 * Listens to one channel while the screen is started and runs [onEvent] once a burst of the given events
 * settles: the screen's normal reload, never data from the event itself.
 */
@Composable
fun LiveEffect(channel: String?, events: Set<String>, live: LiveChannels = LocalLiveChannels.current, onEvent: () -> Unit) {
    val lifecycleOwner = LocalLifecycleOwner.current
    val latest by rememberUpdatedState(onEvent)

    LaunchedEffect(live, channel, events, lifecycleOwner) {
        if (channel == null) {
            return@LaunchedEffect
        }
        lifecycleOwner.repeatOnLifecycle(Lifecycle.State.STARTED) {
            live.events(channel)
                .filter { it.event in events }
                .collectLatest {
                    delay(SETTLE_MILLIS)
                    latest()
                }
        }
    }
}

const val LIVE_RECORD_DELIVERY_NOTE = "delivery_note"
const val LIVE_RECORD_PICKING_SESSION = "picking_session"
const val LIVE_RECORD_RETURN = "return"
const val LIVE_RECORD_PALLET_RETURN = "pallet_return"
const val LIVE_RECORD_STOCK_DELIVERY = "stock_delivery"

val LIVE_RECORD_EVENTS = setOf(LiveEventNames.RECORD_CHANGED)
val LIVE_BACKLOG_EVENTS = setOf(LiveEventNames.DISPATCHING_BACKLOG)
val LIVE_BADGE_EVENTS = setOf(LiveEventNames.PERSONAL_NOTIFICATION, LiveEventNames.TASK_BADGES, LiveEventNames.TICKET_BADGES)
val LIVE_PERMISSION_EVENTS = setOf(LiveEventNames.PERMISSIONS_CHANGED)
val LIVE_CHAT_EVENTS = setOf(LiveEventNames.STAFF_MESSAGE, "staff-message-reaction", "staff-message-translated")
