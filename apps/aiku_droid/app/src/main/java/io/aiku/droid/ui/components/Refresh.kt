package io.aiku.droid.ui.components

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.material3.pulltorefresh.PullToRefreshDefaults
import androidx.compose.material3.pulltorefresh.pullToRefresh
import androidx.compose.material3.pulltorefresh.rememberPullToRefreshState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import io.aiku.droid.ui.theme.LocalAikuColors

class PullRefresh(
    val isRefreshing: Boolean,
    val onRefresh: () -> Unit,
    val isEnabled: Boolean = true,
)

@Composable
fun PullRefreshBox(refresh: PullRefresh?, modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    if (refresh == null) {
        Box(modifier = modifier, content = content)
        return
    }
    val colors = LocalAikuColors.current
    val state = rememberPullToRefreshState()

    Box(modifier = modifier.pullToRefresh(isRefreshing = refresh.isRefreshing, state = state, enabled = refresh.isEnabled, onRefresh = refresh.onRefresh)) {
        content()
        PullToRefreshDefaults.Indicator(
            state = state,
            isRefreshing = refresh.isRefreshing,
            containerColor = colors.surface,
            color = colors.primary,
            modifier = Modifier.align(Alignment.TopCenter),
        )
    }
}
