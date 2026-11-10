package io.aiku.droid.ui.sync

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SyncQueueScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    AppScreen(
        header = { AppHeader(title = stringResource(R.string.sync_title), kicker = stringResource(R.string.sync_kicker)) },
        activeTab = MainTab.Menu,
        footer = {
            AikuButton(
                text = SyncSamples.BACK_ONLINE,
                onClick = { navigator.open(ResolveConflictsRoute) },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
            )
        },
    ) {
        OfflineStatusBar(text = SyncSamples.OFFLINE_STATUS, onView = { navigator.switchSection(SyncQueueRoute) })
        Row(verticalAlignment = Alignment.Bottom, modifier = Modifier.fillMaxWidth()) {
            GroupLabel(text = stringResource(R.string.sync_waiting), modifier = Modifier.weight(1f))
            Text(text = stringResource(R.string.sync_oldest_first), fontSize = 12.sp, color = colors.textTertiary, modifier = Modifier.padding(end = 4.dp))
        }
        ListGroup(modifier = Modifier.fakeData()) {
            SyncSamples.waiting.forEachIndexed { index, action ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(code = action.title, subtitle = action.subtitle, chip = action.chip, chipTone = Tone.Neutral, showPhoto = false)
            }
        }
        GroupLabel(text = stringResource(R.string.sync_last_sync))
        ListGroup(modifier = Modifier.fakeData()) {
            ItemRow(code = SyncSamples.LAST_SYNC_TITLE, subtitle = SyncSamples.LAST_SYNC_SUBTITLE, chip = stringResource(R.string.sync_synced), chipTone = Tone.Success, showPhoto = false)
        }
        Text(
            text = stringResource(R.string.sync_explanation),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}

internal data class QueuedAction(val title: String, val chip: String, val subtitle: String)

internal object SyncSamples {
    const val OFFLINE_STATUS = "Offline since 10:09 · 4 actions waiting"
    const val BACK_ONLINE = "Back online · 2 conflicts to resolve"
    const val LAST_SYNC_TITLE = "12 actions uploaded"
    const val LAST_SYNC_SUBTITLE = "Version check passed · 09:58"
    const val CONFLICTS_STATUS = "2 uploaded automatically · 2 need you"
    const val ONLINE_ONLY_STATUS = "Offline · 4 actions waiting"
    const val CONFLICTS_KICKER = "Back online · 2 of 4 uploaded"
    const val CONFLICT_KICKER = "Conflict 1 of 2 · delivery note"
    const val LINES_DIFFER = "2 of 4 lines"
    const val ONLINE_ONLY_KICKER = "Delivery note · Wholesale"
    const val PARCEL_TITLE = "Parcel 1"
    const val PARCEL_SUBTITLE = "3.4 kg · 40 × 30 × 20 cm · saved offline"

    val waiting = listOf(
        QueuedAction("AR24811 · Picked 6 × SKO-00733", "Waiting", "A-03-02 · 10:12 · based on version of 10:05"),
        QueuedAction("AR24811 · Not picked 2 × SKO-00953", "Waiting", "Out of stock · 10:14"),
        QueuedAction("SKO-00733 · Audit A-03-02", "Waiting", "30 → 28 · Damaged stock · 10:20"),
        QueuedAction("SDaw-0093 · Checked 108 × SKO-00412", "Waiting", "10:31"),
    )
}

