package io.aiku.droid.ui.mywork

import androidx.compose.runtime.staticCompositionLocalOf
import io.aiku.droid.data.api.WorkBadges
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

interface WorkBadgesController {
    val badges: StateFlow<WorkBadges>

    fun refresh()
}

private object NoWorkBadges : WorkBadgesController {
    override val badges: StateFlow<WorkBadges> = MutableStateFlow(WorkBadges()).asStateFlow()
    override fun refresh() = Unit
}

val LocalWorkBadges = staticCompositionLocalOf<WorkBadgesController> { NoWorkBadges }

fun visibleBadgeCount(count: Int?): Int? = count?.takeIf { it > 0 }

val WorkBadges.myWorkBadge: Int? get() = visibleBadgeCount(myWork)

val WorkBadges.goodsOutUnassignedBadge: Int? get() = visibleBadgeCount(goodsOutUnassigned)
