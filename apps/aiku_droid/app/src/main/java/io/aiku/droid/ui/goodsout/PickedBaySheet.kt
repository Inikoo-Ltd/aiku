package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.SkeletonListCard

private sealed interface BayChoice {
    data object Unchosen : BayChoice
    data object NoBay : BayChoice
    data class Bay(val id: Long) : BayChoice
}

@Composable
internal fun PickedBaySheet(
    note: DeliveryNote,
    action: DeliveryNoteAction,
    bays: Choices<PickedBay>,
    isWorking: Boolean,
    problem: String?,
    onLoad: () -> Unit,
    onConfirm: (Long?) -> Unit,
    onDismiss: () -> Unit,
) {
    val isSetAsPicked = action.key == NoteActionKey.SET_AS_PICKED
    val currentBays = note.pickedBayLabels
    var choice by remember { mutableStateOf<BayChoice>(BayChoice.Unchosen) }
    val defaultBay = bays.items.firstOrNull { it.code in currentBays } ?: bays.items.firstOrNull().takeIf { isSetAsPicked || currentBays.isEmpty() }
    val selectedBay = when (val chosen = choice) {
        BayChoice.Unchosen -> defaultBay
        BayChoice.NoBay -> null
        is BayChoice.Bay -> bays.items.firstOrNull { it.id == chosen.id }
    }

    LaunchedEffect(Unit) { onLoad() }

    AikuBottomSheet(
        title = stringResource(if (isSetAsPicked) R.string.goodsout_bay_title else R.string.goodsout_bay_change_title),
        subtitle = stringResource(R.string.goodsout_bay_subtitle, note.reference),
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = when {
                    isSetAsPicked && selectedBay != null -> stringResource(R.string.goodsout_bay_set_as_picked, selectedBay.code)
                    isSetAsPicked -> action.label
                    selectedBay != null -> stringResource(R.string.goodsout_bay_use, selectedBay.code)
                    else -> stringResource(R.string.goodsout_bay_use_none)
                },
                onClick = { onConfirm(selectedBay?.id) },
                icon = if (isSetAsPicked) R.drawable.ic_ui_check else null,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        when {
            bays.isLoading && bays.items.isEmpty() -> SkeletonListCard(rows = 2)
            bays.problem != null -> GoodsOutLoadProblem(problem = bays.problem, title = stringResource(R.string.goodsout_bay_failed), onRetry = onLoad)
            else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                bays.items.forEach { bay ->
                    ChoiceRow(
                        title = bay.code,
                        subtitle = if (bay.numberDeliveryNotes > 0) {
                            pluralStringResource(R.plurals.goodsout_bay_notes, bay.numberDeliveryNotes, bay.numberDeliveryNotes)
                        } else {
                            stringResource(R.string.goodsout_bay_free)
                        },
                        selected = bay.id == selectedBay?.id,
                        onClick = { choice = BayChoice.Bay(bay.id) },
                    )
                }
                ChoiceRow(
                    title = stringResource(R.string.goodsout_bay_none),
                    subtitle = stringResource(R.string.goodsout_bay_none_hint),
                    selected = selectedBay == null,
                    onClick = { choice = BayChoice.NoBay },
                )
            }
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_bay_note))
    }
}
