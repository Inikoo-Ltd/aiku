package io.aiku.droid.ui.mywork

import androidx.compose.runtime.staticCompositionLocalOf
import io.aiku.droid.data.api.WorkBadges
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

private const val MAX_SHOWN_BADGE_COUNT = 99

interface WorkBadgesController {
    val badges: StateFlow<WorkBadges>

    fun refresh()

    fun showInboxUnread(count: Int)
}

private object NoWorkBadges : WorkBadgesController {
    override val badges: StateFlow<WorkBadges> = MutableStateFlow(WorkBadges()).asStateFlow()
    override fun refresh() = Unit
    override fun showInboxUnread(count: Int) = Unit
}

val LocalWorkBadges = staticCompositionLocalOf<WorkBadgesController> { NoWorkBadges }

fun visibleBadgeCount(count: Int?): Int? = count?.takeIf { it > 0 }

fun badgeText(count: Int): String = if (count > MAX_SHOWN_BADGE_COUNT) "$MAX_SHOWN_BADGE_COUNT+" else count.toString()

val WorkBadges.myWorkBadge: Int? get() = visibleBadgeCount(myWork)

val WorkBadges.goodsOutUnassignedBadge: Int? get() = visibleBadgeCount(goodsOutUnassigned)

val WorkBadges.inboxBadge: Int? get() = visibleBadgeCount(inboxUnread)

val WorkBadges.goodsInReceivedBadge: Int? get() = visibleBadgeCount(goodsInReceived)
