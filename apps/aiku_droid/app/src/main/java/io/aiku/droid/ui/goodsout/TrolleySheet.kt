package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun TrolleySheet(
    note: DeliveryNote,
    action: DeliveryNoteAction,
    trolleys: Choices<Trolley>,
    isWorking: Boolean,
    problem: String?,
    onLoad: () -> Unit,
    onConfirm: (Long?) -> Unit,
    onDismiss: () -> Unit,
) {
    val isStart = action.key == NoteActionKey.START_PICKING
    val current = note.trolleys.joinToString(", ") { it.name }.takeIf { it.isNotBlank() }

    TrolleyChoiceSheet(
        title = stringResource(if (isStart) R.string.goodsout_trolley_title else R.string.goodsout_trolley_change_title),
        subtitle = if (!isStart && current != null) {
            stringResource(R.string.goodsout_trolley_change_subtitle, note.reference, current)
        } else {
            stringResource(R.string.goodsout_delivery_note_reference, note.reference)
        },
        trolleys = trolleys,
        isWorking = isWorking,
        problem = problem,
        onLoad = onLoad,
        confirmLabel = { trolley -> stringResource(if (isStart) R.string.goodsout_trolley_start else R.string.goodsout_trolley_use, trolley.name) },
        onConfirm = onConfirm,
        noTrolleyLabel = if (isStart || current != null) stringResource(if (isStart) R.string.goodsout_trolley_skip else R.string.goodsout_trolley_remove) else null,
        onDismiss = onDismiss,
    )
}

@Composable
internal fun TrolleyChoiceSheet(
    title: String,
    subtitle: String,
    trolleys: Choices<Trolley>,
    isWorking: Boolean,
    problem: String?,
    onLoad: () -> Unit,
    confirmLabel: @Composable (Trolley) -> String,
    onConfirm: (Long?) -> Unit,
    noTrolleyLabel: String?,
    onDismiss: () -> Unit,
) {
    var selectedId by remember { mutableStateOf<Long?>(null) }
    var confirmedId by remember { mutableStateOf<Long?>(null) }
    val selected = trolleys.items.firstOrNull { it.id == selectedId } ?: trolleys.items.firstOrNull()

    LaunchedEffect(Unit) { onLoad() }

    AikuBottomSheet(
        title = title,
        subtitle = subtitle,
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            selected?.let { trolley ->
                AikuButton(
                    text = confirmLabel(trolley),
                    onClick = {
                        confirmedId = trolley.id
                        onConfirm(trolley.id)
                    },
                    isLoading = isWorking && confirmedId != null,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            if (noTrolleyLabel != null) {
                AikuButton(
                    text = noTrolleyLabel,
                    onClick = {
                        confirmedId = null
                        onConfirm(null)
                    },
                    style = if (selected == null) ButtonStyle.Primary else ButtonStyle.Outline,
                    isLoading = isWorking && confirmedId == null,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        GroupLabel(text = stringResource(R.string.goodsout_trolley_available))
        when {
            trolleys.isLoading && trolleys.items.isEmpty() -> SkeletonListCard(rows = 2)
            trolleys.problem != null -> GoodsOutLoadProblem(problem = trolleys.problem, title = stringResource(R.string.goodsout_trolley_failed), onRetry = onLoad)
            trolleys.items.isEmpty() -> EmptyChoices(text = stringResource(R.string.goodsout_trolley_none_free))
            else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                trolleys.items.forEach { trolley ->
                    ChoiceRow(
                        title = trolley.name,
                        subtitle = stringResource(R.string.goodsout_trolley_free),
                        selected = trolley.id == selected?.id,
                        onClick = { selectedId = trolley.id },
                    )
                }
            }
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_trolley_note))
    }
}

@Composable
internal fun EmptyChoices(text: String) {
    Text(text = text, fontSize = 14.sp, color = LocalAikuColors.current.textTertiary)
}
