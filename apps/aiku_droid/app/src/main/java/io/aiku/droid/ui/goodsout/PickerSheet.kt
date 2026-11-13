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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.Picker
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.navigation.LocalStaff

private enum class PickerChange { Assign, Remove }

internal enum class HandlerRole { Picker, Packer }

@Composable
internal fun PickerSheet(
    note: DeliveryNote,
    assign: DeliveryNoteAction?,
    remove: DeliveryNoteAction?,
    pickers: Choices<Picker>,
    isWorking: Boolean,
    problem: String?,
    onLoad: () -> Unit,
    onConfirm: (Long?) -> Unit,
    onDismiss: () -> Unit,
    role: HandlerRole = HandlerRole.Picker,
) {
    val staff = LocalStaff.current
    var search by remember { mutableStateOf("") }
    var selectedId by remember { mutableStateOf<Long?>(null) }
    var change by remember { mutableStateOf<PickerChange?>(null) }
    val isPacker = role == HandlerRole.Packer
    val currentPicker = if (isPacker) note.packer else note.picker
    val selected = pickers.items.firstOrNull { it.userId == selectedId }
    val shown = pickers.items.filter { picker ->
        search.isBlank() || picker.name.contains(search.trim(), ignoreCase = true) || picker.alias?.contains(search.trim(), ignoreCase = true) == true
    }

    LaunchedEffect(assign) {
        if (assign != null) {
            onLoad()
        }
    }

    AikuBottomSheet(
        title = assign?.label ?: remove?.label.orEmpty(),
        subtitle = currentPicker?.name?.takeIf { it.isNotBlank() }
            ?.let { stringResource(if (isPacker) R.string.goodsout_packer_subtitle else R.string.goodsout_picker_subtitle, note.reference, it) }
            ?: stringResource(if (isPacker) R.string.goodsout_packer_subtitle_none else R.string.goodsout_picker_subtitle_none, note.reference),
        onDismiss = onDismiss,
        pinnedTop = if (assign != null) {
            {
                SearchField(
                    value = search,
                    onValueChange = { search = it },
                    placeholder = stringResource(if (isPacker) R.string.goodsout_packer_search else R.string.goodsout_picker_search),
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        } else {
            null
        },
        footer = {
            ActionProblem(problem)
            if (assign != null && selected != null) {
                AikuButton(
                    text = stringResource(R.string.goodsout_picker_assign, selected.name),
                    onClick = {
                        change = PickerChange.Assign
                        onConfirm(selected.userId)
                    },
                    isLoading = isWorking && change == PickerChange.Assign,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            if (remove != null) {
                AikuButton(
                    text = remove.label,
                    onClick = {
                        change = PickerChange.Remove
                        onConfirm(null)
                    },
                    style = ButtonStyle.Danger,
                    isLoading = isWorking && change == PickerChange.Remove,
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
        if (assign != null) {
            when {
                pickers.isLoading && pickers.items.isEmpty() -> SkeletonListCard(rows = 3)
                pickers.problem != null -> GoodsOutLoadProblem(problem = pickers.problem, title = stringResource(R.string.goodsout_picker_failed), onRetry = onLoad)
                shown.isEmpty() -> EmptyChoices(text = stringResource(R.string.goodsout_picker_none_found))
                else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    shown.forEach { picker ->
                        ChoiceRow(
                            title = if (picker.userId == staff.userId) stringResource(R.string.goodsout_person_you, picker.name) else picker.name,
                            subtitle = listOfNotNull(
                                picker.alias?.takeIf { it.isNotBlank() && it != picker.name },
                                if (picker.userId == currentPicker?.id) stringResource(if (isPacker) R.string.goodsout_packer_current else R.string.goodsout_picker_current) else null,
                            ).joinToString(" · ").takeIf { it.isNotEmpty() },
                            selected = picker.userId == selected?.userId,
                            onClick = { selectedId = picker.userId },
                        )
                    }
                }
            }
        }
    }
}
