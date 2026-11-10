package io.aiku.droid.ui.sync

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.navigation.ConflictRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private data class ConflictSummary(val title: String, val chip: String, val subtitle: String, val reference: String)

private val sampleConflicts = listOf(
    ConflictSummary("Delivery note AR24811", "2 lines differ", "Sam P. also picked this note while you were offline", "AR24811"),
    ConflictSummary("SKO-00733 in A-03-02", "1 line differs", "Jo K. audited the location at 10:25", "SKO-00733"),
)

@Composable
fun ResolveConflictsScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val keptMine = stringResource(R.string.sync_kept_all_mine)
    val keptAiku = stringResource(R.string.sync_kept_all_aiku)

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.sync_conflicts_title),
                kicker = SyncSamples.CONFLICTS_KICKER,
                isSample = true,
            )
        },
        activeTab = MainTab.Menu,
    ) {
        OfflineStatusBar(text = SyncSamples.CONFLICTS_STATUS, onView = { navigator.open(SyncQueueRoute) })
        ListGroup(modifier = Modifier.fakeData()) {
            sampleConflicts.forEachIndexed { index, conflict ->
                if (index > 0) {
                    RowDivider()
                }
                ListRow(
                    title = conflict.title,
                    subtitle = conflict.subtitle,
                    badges = { StatusBadge(text = conflict.chip, tone = Tone.Warn) },
                    onClick = { navigator.open(ConflictRoute(conflict.reference)) },
                )
            }
        }
        GroupLabel(text = stringResource(R.string.sync_resolve_all))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            AikuButton(
                text = stringResource(R.string.sync_keep_all_mine),
                onClick = {
                    toasts.show(keptMine)
                    navigator.open(SyncQueueRoute)
                },
                style = ButtonStyle.Outline,
                modifier = Modifier.weight(1f),
            )
            AikuButton(
                text = stringResource(R.string.sync_keep_all_aiku),
                onClick = {
                    toasts.show(keptAiku)
                    navigator.open(SyncQueueRoute)
                },
                style = ButtonStyle.Outline,
                modifier = Modifier.weight(1f),
            )
        }
        Text(
            text = stringResource(R.string.sync_conflicts_explanation),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}
