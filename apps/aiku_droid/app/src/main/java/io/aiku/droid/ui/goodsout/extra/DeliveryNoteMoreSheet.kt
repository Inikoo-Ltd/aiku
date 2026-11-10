package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.DrawableRes
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.PickerSheet
import io.aiku.droid.ui.goodsout.PrintDeliveryNoteSheet
import io.aiku.droid.ui.navigation.AskCrmRoute
import io.aiku.droid.ui.navigation.CustomsFileRoute
import io.aiku.droid.ui.navigation.DeliveryNoteFinalisedRoute
import io.aiku.droid.ui.navigation.DispatchedEmailsRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.TariffCodesRoute

private sealed interface MoreNextSheet {
    data object Picker : MoreNextSheet
    data object Print : MoreNextSheet
    data object Cancel : MoreNextSheet
    data class Undo(val step: UndoStep) : MoreNextSheet
}

@Composable
fun DeliveryNoteMoreSheet(reference: String, onDismiss: () -> Unit) {
    var next by remember { mutableStateOf<MoreNextSheet?>(null) }

    when (val shown = next) {
        MoreNextSheet.Picker -> PickerSheet(reference = reference, onDismiss = onDismiss)
        MoreNextSheet.Print -> PrintDeliveryNoteSheet(reference = reference, onDismiss = onDismiss)
        MoreNextSheet.Cancel -> CancelDeliveryNoteConfirmSheet(reference = reference, onBack = { next = null }, onDone = onDismiss)
        is MoreNextSheet.Undo -> UndoStepConfirmSheet(reference = reference, step = shown.step, onBack = { next = null }, onDone = onDismiss)
        null -> MoreActionsSheet(reference = reference, onNext = { next = it }, onDismiss = onDismiss)
    }
}

@Composable
private fun MoreActionsSheet(reference: String, onNext: (MoreNextSheet) -> Unit, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val openScreen: (Any) -> Unit = { route ->
        onDismiss()
        navigator.open(route)
    }

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_more_title, reference),
        subtitle = stringResource(R.string.goodsout_extra_more_subtitle),
        onDismiss = onDismiss,
    ) {
        if (can.pickAndPack) {
            MoreGroup(label = stringResource(R.string.goodsout_extra_more_group_picker)) {
                MoreAction(
                    icon = R.drawable.ic_ui_person,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_change_picker),
                    caption = stringResource(R.string.goodsout_extra_change_picker_caption),
                    onClick = { onNext(MoreNextSheet.Picker) },
                )
                RowDivider()
                MoreAction(
                    icon = R.drawable.ic_ui_person,
                    tone = Tone.Neutral,
                    title = stringResource(R.string.goodsout_extra_remove_picker),
                    caption = stringResource(R.string.goodsout_extra_remove_picker_caption),
                    onClick = { onNext(MoreNextSheet.Picker) },
                )
            }
            MoreGroup(label = stringResource(R.string.goodsout_extra_more_group_undo)) {
                UndoStep.entries.forEachIndexed { index, step ->
                    if (index > 0) {
                        RowDivider()
                    }
                    MoreAction(
                        icon = R.drawable.ic_ui_undo,
                        tone = Tone.Neutral,
                        title = stringResource(step.label),
                        caption = stringResource(step.caption),
                        onClick = { onNext(MoreNextSheet.Undo(step)) },
                    )
                }
            }
            MoreGroup(label = stringResource(R.string.goodsout_extra_more_group_talk)) {
                MoreAction(
                    icon = R.drawable.ic_ui_chat,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_ask_crm),
                    caption = stringResource(R.string.goodsout_extra_ask_crm_caption),
                    onClick = { openScreen(AskCrmRoute(reference)) },
                )
                RowDivider()
                MoreAction(
                    icon = R.drawable.ic_feedback_check,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_add_task),
                    caption = stringResource(R.string.goodsout_extra_add_task_caption),
                    onClick = { openScreen(AskCrmRoute(reference)) },
                )
                RowDivider()
                MoreAction(
                    icon = R.drawable.ic_ui_mail,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_dispatched_emails),
                    caption = stringResource(R.string.goodsout_extra_dispatched_emails_caption),
                    onClick = { openScreen(DispatchedEmailsRoute(reference)) },
                )
            }
            MoreGroup(label = stringResource(R.string.goodsout_extra_more_group_documents)) {
                MoreAction(
                    icon = R.drawable.ic_ui_print,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_print_delivery_note),
                    caption = stringResource(R.string.goodsout_extra_print_delivery_note_caption),
                    onClick = { onNext(MoreNextSheet.Print) },
                )
                RowDivider()
                MoreAction(
                    icon = R.drawable.ic_ui_language,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_tariff_title),
                    caption = stringResource(R.string.goodsout_extra_tariff_caption),
                    onClick = { openScreen(TariffCodesRoute(reference)) },
                )
                RowDivider()
                MoreAction(
                    icon = R.drawable.ic_ui_document,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_customs_title),
                    caption = stringResource(R.string.goodsout_extra_customs_caption),
                    onClick = { openScreen(CustomsFileRoute(reference)) },
                )
                RowDivider()
                MoreAction(
                    icon = R.drawable.ic_ui_document,
                    tone = Tone.Primary,
                    title = stringResource(R.string.goodsout_extra_generate_invoice),
                    caption = stringResource(R.string.goodsout_extra_generate_invoice_caption),
                    onClick = { openScreen(DeliveryNoteFinalisedRoute(reference)) },
                )
            }
        }
        if (can.superviseGoodsOut) {
            MoreGroup(label = stringResource(R.string.goodsout_extra_more_group_supervisor)) {
                MoreAction(
                    icon = R.drawable.ic_feedback_close,
                    tone = Tone.Danger,
                    title = stringResource(R.string.goodsout_extra_cancel_delivery_note),
                    caption = stringResource(R.string.goodsout_extra_cancel_delivery_note_caption),
                    onClick = { onNext(MoreNextSheet.Cancel) },
                )
            }
        }
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
private fun MoreGroup(label: String, content: @Composable ColumnScope.() -> Unit) {
    SheetGroupLabel(text = label)
    ListGroup(content = content)
}

@Composable
private fun MoreAction(
    @DrawableRes icon: Int,
    tone: Tone,
    title: String,
    caption: String,
    onClick: () -> Unit,
) {
    ExtraRow(
        title = title,
        subtitle = caption,
        leading = { ExtraIconTile(icon = icon, tone = tone) },
        onClick = onClick,
    )
}
