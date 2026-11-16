package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone

private val pickerKeys = setOf(NoteActionKey.ASSIGN_PICKER, NoteActionKey.REMOVE_PICKER)
private val packerKeys = setOf(NoteActionKey.CHANGE_PACKER)
private val equipmentKeys = setOf(NoteActionKey.CHANGE_TROLLEY, NoteActionKey.CHANGE_PICKED_BAY)
private val undoKeys = setOf(NoteActionKey.UNDO_SET_AS_PICKED, NoteActionKey.UNDO_PACKING, NoteActionKey.UNPACK)
private val supervisorKeys = setOf(NoteActionKey.CANCEL)

@Composable
internal fun DeliveryNoteMoreSheet(
    reference: String,
    actions: List<DeliveryNoteAction>,
    onAction: (DeliveryNoteAction) -> Unit,
    onAskCrm: () -> Unit,
    onDocuments: () -> Unit,
    onDismiss: () -> Unit,
) {
    val run: (DeliveryNoteAction) -> Unit = { action ->
        onDismiss()
        onAction(action)
    }

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_more_title, reference),
        subtitle = stringResource(R.string.goodsout_extra_more_subtitle),
        onDismiss = onDismiss,
    ) {
        ActionGroup(label = stringResource(R.string.goodsout_extra_more_group_picker), actions = actions.filter { it.key in pickerKeys }, onAction = run)
        ActionGroup(label = stringResource(R.string.goodsout_more_group_packer), actions = actions.filter { it.key in packerKeys }, onAction = run)
        ActionGroup(label = stringResource(R.string.goodsout_more_group_equipment), actions = actions.filter { it.key in equipmentKeys }, onAction = run)
        ActionGroup(label = stringResource(R.string.goodsout_extra_more_group_undo), actions = actions.filter { it.key in undoKeys }, onAction = run)
        MoreGroup(label = stringResource(R.string.goodsout_extra_more_group_talk)) {
            MoreAction(
                icon = R.drawable.ic_ui_chat,
                tone = Tone.Primary,
                title = stringResource(R.string.goodsout_extra_ask_crm),
                caption = stringResource(R.string.goodsout_extra_ask_crm_caption),
                onClick = {
                    onDismiss()
                    onAskCrm()
                },
            )
        }
        MoreGroup(label = stringResource(R.string.goodsout_extra_more_group_documents)) {
            MoreAction(
                icon = R.drawable.ic_ui_document,
                tone = Tone.Primary,
                title = stringResource(R.string.goodsout_documents),
                caption = stringResource(R.string.goodsout_documents_caption),
                onClick = {
                    onDismiss()
                    onDocuments()
                },
            )
        }
        ActionGroup(label = stringResource(R.string.goodsout_extra_more_group_supervisor), actions = actions.filter { it.key in supervisorKeys }, onAction = run)
        SheetButtons {
            AikuButton(
                text = stringResource(R.string.goodsout_extra_close),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun ActionGroup(label: String, actions: List<DeliveryNoteAction>, onAction: (DeliveryNoteAction) -> Unit) {
    if (actions.isEmpty()) {
        return
    }
    MoreGroup(label = label) {
        actions.forEachIndexed { index, action ->
            if (index > 0) {
                RowDivider()
            }
            MoreAction(
                icon = action.menuIcon(),
                tone = action.menuTone(),
                title = action.label,
                caption = action.disabledReason?.takeIf { action.isDisabled } ?: stringResource(action.menuCaption()),
                onClick = if (action.isDisabled) null else ({ onAction(action) }),
            )
        }
    }
}

private fun DeliveryNoteAction.menuTone(): Tone = when {
    isDisabled || key == NoteActionKey.REMOVE_PICKER -> Tone.Neutral
    key == NoteActionKey.CANCEL -> Tone.Danger
    else -> Tone.Primary
}

@DrawableRes
private fun DeliveryNoteAction.menuIcon(): Int = when (key) {
    NoteActionKey.CHANGE_TROLLEY -> R.drawable.ic_ui_trolley
    NoteActionKey.CHANGE_PICKED_BAY -> R.drawable.ic_ui_area
    NoteActionKey.UNDO_SET_AS_PICKED, NoteActionKey.UNDO_PACKING, NoteActionKey.UNPACK -> R.drawable.ic_ui_undo
    NoteActionKey.CANCEL -> R.drawable.ic_feedback_close
    else -> R.drawable.ic_ui_person
}

@StringRes
private fun DeliveryNoteAction.menuCaption(): Int = when (key) {
    NoteActionKey.REMOVE_PICKER -> R.string.goodsout_extra_remove_picker_caption
    NoteActionKey.CHANGE_PACKER -> R.string.goodsout_more_change_packer_caption
    NoteActionKey.CHANGE_TROLLEY -> R.string.goodsout_more_change_trolley_caption
    NoteActionKey.CHANGE_PICKED_BAY -> R.string.goodsout_more_change_bay_caption
    NoteActionKey.UNDO_SET_AS_PICKED -> R.string.goodsout_extra_undo_set_as_picked_caption
    NoteActionKey.UNDO_PACKING -> R.string.goodsout_extra_undo_packing_caption
    NoteActionKey.UNPACK -> R.string.goodsout_extra_unpack_caption
    NoteActionKey.CANCEL -> R.string.goodsout_extra_cancel_delivery_note_caption
    else -> R.string.goodsout_extra_change_picker_caption
}

@Composable
private fun MoreGroup(label: String, modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    SheetGroupLabel(text = label)
    ListGroup(modifier = modifier, content = content)
}

@Composable
private fun MoreAction(
    @DrawableRes icon: Int,
    tone: Tone,
    title: String,
    caption: String,
    onClick: (() -> Unit)?,
) {
    ExtraRow(
        title = title,
        subtitle = caption,
        leading = { ExtraIconTile(icon = icon, tone = tone) },
        onClick = onClick,
    )
}
