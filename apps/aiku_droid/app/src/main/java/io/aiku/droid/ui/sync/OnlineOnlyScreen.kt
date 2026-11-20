package io.aiku.droid.ui.sync

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.offline.NoOfflineWork
import io.aiku.droid.data.offline.OfflineWork
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun OnlineOnlyScreen(reference: String, offlineWork: OfflineWork = NoOfflineWork) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val isOnline by offlineWork.isOnline.collectAsStateWithLifecycle()
    val waiting by offlineWork.waitingCount.collectAsStateWithLifecycle()

    AppScreen(
        header = { AppHeader(title = reference, kicker = stringResource(R.string.goodsout_offline_online_only_kicker)) },
        activeTab = MainTab.Menu,
    ) {
        if (isOnline) {
            InlineAlert(title = stringResource(R.string.goodsout_offline_online_only_back_online), tone = AlertTone.Success)
        } else {
            InlineAlert(
                title = stringResource(R.string.goodsout_offline_online_only_title),
                body = stringResource(R.string.goodsout_offline_online_only_body),
                tone = AlertTone.Warn,
            )
        }
        Text(
            text = if (waiting > 0) {
                stringResource(R.string.goodsout_offline_online_only_waiting, waiting)
            } else {
                stringResource(R.string.goodsout_offline_online_only_nothing_waiting)
            },
            fontSize = 14.sp,
            lineHeight = 20.sp,
            color = colors.textSecondary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
        if (waiting > 0) {
            AikuButton(
                text = stringResource(R.string.goodsout_offline_online_only_view),
                onClick = { navigator.open(SyncQueueRoute) },
                style = ButtonStyle.Soft,
                icon = R.drawable.ic_ui_clock,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        AikuButton(
            text = stringResource(R.string.goodsout_back),
            onClick = navigator::back,
            style = ButtonStyle.Outline,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
fun OnlineOnlyGate(reference: String, offlineWork: OfflineWork, content: @Composable () -> Unit) {
    val isOnline by offlineWork.isOnline.collectAsStateWithLifecycle()

    if (isOnline) {
        content()
    } else {
        OnlineOnlyScreen(reference = reference, offlineWork = offlineWork)
    }
}
