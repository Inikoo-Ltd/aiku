package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.offline.NoOfflineWork
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.theme.LocalAikuColors

val LocalOfflineIndicator = staticCompositionLocalOf<OfflineWork> { NoOfflineWork }

enum class OfflineIndicatorKind { Offline, NeedYou, Syncing }

data class OfflineIndicatorState(val kind: OfflineIndicatorKind, val count: Int)

fun offlineIndicatorState(isOnline: Boolean, waiting: Int, needYou: Int): OfflineIndicatorState? = when {
    !isOnline -> OfflineIndicatorState(OfflineIndicatorKind.Offline, waiting)
    needYou > 0 -> OfflineIndicatorState(OfflineIndicatorKind.NeedYou, needYou)
    waiting > 0 -> OfflineIndicatorState(OfflineIndicatorKind.Syncing, waiting)
    else -> null
}

@Composable
fun OfflineIndicator(modifier: Modifier = Modifier) {
    val offlineWork = LocalOfflineIndicator.current
    val isOnline by offlineWork.isOnline.collectAsStateWithLifecycle()
    val waiting by offlineWork.waitingCount.collectAsStateWithLifecycle()
    val needYou by offlineWork.needsYouCount.collectAsStateWithLifecycle()
    val state = offlineIndicatorState(isOnline = isOnline, waiting = waiting, needYou = needYou) ?: return
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current

    when (state.kind) {
        OfflineIndicatorKind.Offline -> IndicatorBar(
            text = if (state.count > 0) {
                pluralStringResource(R.plurals.offline_indicator_offline_waiting, state.count, state.count)
            } else {
                stringResource(R.string.offline_indicator_offline)
            },
            icon = R.drawable.ic_feedback_offline,
            background = colors.toast,
            content = colors.onToast,
            modifier = modifier,
        )
        OfflineIndicatorKind.NeedYou -> IndicatorBar(
            text = pluralStringResource(R.plurals.offline_indicator_need_you, state.count, state.count),
            icon = R.drawable.ic_ui_warning,
            background = colors.warnSoft,
            content = colors.warnInk,
            modifier = modifier,
            onClick = { navigator.open(ResolveConflictsRoute) },
        )
        OfflineIndicatorKind.Syncing -> IndicatorBar(
            text = pluralStringResource(R.plurals.offline_indicator_syncing, state.count, state.count),
            icon = R.drawable.ic_states_sync,
            background = colors.surfaceMuted,
            content = colors.textSecondary,
            modifier = modifier,
        )
    }
}

@Composable
private fun IndicatorBar(
    text: String,
    @DrawableRes icon: Int,
    background: Color,
    content: Color,
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = modifier
            .fillMaxWidth()
            .padding(start = 16.dp, end = 16.dp, top = 8.dp)
            .clip(ControlShape)
            .background(background)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .semantics { liveRegion = LiveRegionMode.Polite }
            .padding(horizontal = 12.dp, vertical = 7.dp),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(16.dp))
        Text(text = text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = content, modifier = Modifier.weight(1f))
        if (onClick != null) {
            Icon(painter = painterResource(R.drawable.ic_ui_next), contentDescription = null, tint = content, modifier = Modifier.size(14.dp))
        }
    }
}
