package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.goodsout.stageOf
import io.aiku.droid.ui.goodsout.stageTone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
fun PickedBaysScreen(bays: EquipmentListViewModel<PickedBay>) {
    val state by bays.state.collectAsStateWithLifecycle()
    var openBayId by remember { mutableStateOf<Long?>(null) }

    LifecycleEventEffect(Lifecycle.Event.ON_START) { bays.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = bays::signInAgain)
            return@RequireAbility
        }
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.PickedBays) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            EquipmentList(
                state = state,
                failedTitle = stringResource(R.string.goodsout_extra_bays_failed),
                emptyIcon = R.drawable.ic_ui_area,
                emptyTitle = stringResource(R.string.goodsout_extra_bays_empty_title),
                emptyBody = stringResource(R.string.goodsout_extra_bays_hint),
                onRetry = bays::retry,
            ) {
                state.items.forEachIndexed { index, bay ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val tone = if (bay.isFree) Tone.Success else Tone.Primary
                    ExtraRow(
                        title = bay.code,
                        subtitle = if (bay.isFree) null else baySummary(bay),
                        chip = stringResource(if (bay.isFree) R.string.goodsout_extra_free else R.string.goodsout_extra_in_use),
                        chipTone = tone,
                        leading = { ExtraIconTile(icon = R.drawable.ic_ui_area, tone = tone) },
                        onClick = if (bay.deliveryNotes.isEmpty()) null else ({ openBayId = bay.id }),
                    )
                }
            }
            HintText(text = stringResource(R.string.goodsout_extra_bays_hint))
        }
        state.items.firstOrNull { it.id == openBayId }?.let { bay -> BayNotesSheet(bay = bay, onDismiss = { openBayId = null }) }
    }
}

@Composable
private fun baySummary(bay: PickedBay): String {
    val notes = maxOf(bay.numberDeliveryNotes, bay.deliveryNotes.size)
    return listOfNotNull(
        pluralStringResource(R.plurals.goodsout_extra_delivery_notes, notes, notes),
        if (bay.numberWaiting > 0) pluralStringResource(R.plurals.goodsout_extra_waiting_count, bay.numberWaiting, bay.numberWaiting) else null,
    ).joinToString(KICKER_SEPARATOR)
}

@Composable
private fun BayNotesSheet(bay: PickedBay, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current

    AikuBottomSheet(
        title = bay.code,
        subtitle = baySummary(bay),
        icon = R.drawable.ic_ui_area,
        onDismiss = onDismiss,
        footer = {
            AikuButton(
                text = stringResource(R.string.goodsout_extra_close),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        ListGroup {
            bay.deliveryNotes.forEachIndexed { index, note ->
                if (index > 0) {
                    RowDivider()
                }
                ExtraRow(
                    title = note.reference,
                    chip = note.stateLabel.ifBlank { note.state },
                    chipTone = stageTone(stageOf(note.state)),
                    leading = { ExtraIconTile(icon = R.drawable.ic_ui_delivery_note, tone = Tone.Primary) },
                    onClick = {
                        onDismiss()
                        navigator.open(deliveryNoteRoute(id = note.id, reference = note.reference, state = note.state))
                    },
                )
            }
        }
    }
}
