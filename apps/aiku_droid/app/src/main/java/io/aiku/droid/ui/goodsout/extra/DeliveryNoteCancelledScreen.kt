package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.PickingType
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.DeliveryNoteViewModel
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.pluralQuantity
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun DeliveryNoteCancelledScreen(note: DeliveryNoteViewModel, reference: String) {
    val state by note.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { note.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.note
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = note::signInAgain)
        } else {
            CancelledContent(reference = loaded?.reference ?: reference, note = loaded, problem = state.problem, onRetry = note::retry)
        }
    }
}

@Composable
private fun CancelledContent(reference: String, note: DeliveryNote?, problem: GoodsOutProblem?, onRetry: () -> Unit) {
    val navigator = LocalAppNavigator.current

    AppScreen(
        header = { AppHeader(title = reference, kicker = stringResource(R.string.goodsout_extra_cancelled_kicker)) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            AikuButton(
                text = stringResource(R.string.goodsout_extra_back_to_delivery_notes),
                onClick = { navigator.switchSection(DeliveryNotesRoute()) },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsout_note_failed_title, reference), onRetry = onRetry) }
        if (note == null) {
            if (problem == null) {
                SkeletonListCard(rows = 3)
            }
            return@AppScreen
        }
        val picked = note.lines.filter { it.quantityPicked > 0.0 }
        val units = picked.sumOf { it.quantityPicked }
        CancelledAlert(
            body = if (picked.isEmpty()) {
                stringResource(R.string.goodsout_extra_cancelled_nothing_picked)
            } else {
                pluralStringResource(
                    R.plurals.goodsout_extra_cancelled_units_picked,
                    pluralQuantity(units),
                    quantityText(units),
                    pluralStringResource(R.plurals.goodsout_extra_sko_count, picked.size, picked.size),
                )
            },
            returnNote = note.returnDeliveryNote?.let { returned ->
                stringResource(R.string.goodsout_extra_cancelled_return, listOf(returned.reference, returned.stateLabel).filter { it.isNotBlank() }.joinToString(KICKER_SEPARATOR))
            },
        )
        if (picked.isNotEmpty()) {
            ListGroup {
                picked.forEachIndexed { index, line ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val locations = line.pickings.filter { it.type == PickingType.PICK }.mapNotNull { it.locationCode?.takeIf(String::isNotBlank) }.distinct()
                    ItemRow(
                        code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsout_line_unknown_sko),
                        subtitle = if (locations.isEmpty()) line.orgStock?.name.orEmpty() else stringResource(R.string.goodsout_extra_picked_from, locations.joinToString(", ")),
                        done = quantityText(line.quantityPicked, line.quantityPickedLabel),
                        required = quantityText(line.quantityRequired, line.quantityRequiredLabel),
                        quantityTone = Tone.Success,
                        imageUrl = line.orgStock?.imageUrl,
                    )
                }
            }
            HintText(text = stringResource(R.string.goodsout_extra_cancelled_hint))
        }
    }
}

@Composable
private fun CancelledAlert(body: String, returnNote: String?) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.dangerSoft)
            .semantics { liveRegion = LiveRegionMode.Assertive }
            .padding(horizontal = 16.dp, vertical = 24.dp),
    ) {
        Text(
            text = stringResource(R.string.goodsout_extra_cancelled_title),
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = colors.dangerInk,
            textAlign = TextAlign.Center,
        )
        Text(text = body, fontSize = 14.sp, lineHeight = 20.sp, color = colors.textSecondary, textAlign = TextAlign.Center)
        if (returnNote != null) {
            Text(text = returnNote, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, lineHeight = 20.sp, color = colors.text, textAlign = TextAlign.Center)
        }
    }
}
