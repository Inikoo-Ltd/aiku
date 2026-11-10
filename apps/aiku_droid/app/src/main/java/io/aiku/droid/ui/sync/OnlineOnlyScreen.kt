package io.aiku.droid.ui.sync

import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun OnlineOnlyScreen(reference: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    AppScreen(
        header = { AppHeader(title = reference, kicker = SyncSamples.ONLINE_ONLY_KICKER, isSample = true) },
        activeTab = MainTab.Menu,
    ) {
        OfflineStatusBar(text = SyncSamples.ONLINE_ONLY_STATUS, onView = { navigator.open(SyncQueueRoute) })
        StateStepper(steps = deliveryNoteSteps(DeliveryNoteStage.Packed), modifier = Modifier.fakeData(cornerRadius = 14.dp))
        InlineAlert(
            title = stringResource(R.string.sync_online_only_title),
            body = stringResource(R.string.sync_online_only_body),
            tone = AlertTone.Warn,
        )
        ListGroup(modifier = Modifier.fakeData()) {
            ListRow(
                title = SyncSamples.PARCEL_TITLE,
                subtitle = SyncSamples.PARCEL_SUBTITLE,
                leading = { IconTile(icon = R.drawable.ic_ui_sko, tone = Tone.Primary) },
            )
        }
        Text(
            text = stringResource(R.string.sync_online_only_note),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}
