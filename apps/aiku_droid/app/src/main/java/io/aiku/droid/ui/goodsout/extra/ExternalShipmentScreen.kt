package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteWrite
import io.aiku.droid.data.api.NoteActionKey
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.goodsout.DeliveryNoteEvents
import io.aiku.droid.ui.goodsout.DeliveryNoteUiState
import io.aiku.droid.ui.goodsout.DeliveryNoteViewModel
import io.aiku.droid.ui.goodsout.DisabledActionNotice
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.ReadOnlyParcels
import io.aiku.droid.ui.goodsout.channelName
import io.aiku.droid.ui.goodsout.stage
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

internal val DeliveryNote.externalEngine: String
    get() = shipping.external?.engineLabel?.takeIf { it.isNotBlank() } ?: platform?.name.orEmpty()

@Composable
fun ExternalShipmentScreen(note: DeliveryNoteViewModel, reference: String) {
    val state by note.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { note.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.note
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = note::signInAgain)
            loaded != null -> ExternalShipmentContent(viewModel = note, state = state, note = loaded)
            else -> AppScreen(
                header = { AppHeader(title = reference, kicker = stringResource(R.string.goodsout_extra_delivery_note)) },
                activeTab = MainTab.Menu,
                showBar = true,
            ) {
                val problem = state.problem
                if (problem == null) {
                    SkeletonListCard(rows = 2)
                } else {
                    GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_note_failed_title, reference), onRetry = note::retry)
                }
            }
        }
    }
}

@Composable
private fun ExternalShipmentContent(viewModel: DeliveryNoteViewModel, state: DeliveryNoteUiState, note: DeliveryNote) {
    val navigator = LocalAppNavigator.current
    val engine = note.externalEngine
    val action = note.action(NoteActionKey.GET_EXTERNAL_SHIPMENT)
    var problem by remember { mutableStateOf<String?>(null) }
    val kicker = listOfNotNull(channelName(note.channel, note.shop?.name), engine.takeIf { it.isNotBlank() })

    LaunchedEffect(note.shipments.isNotEmpty()) {
        if (note.shipments.isNotEmpty()) {
            navigator.back()
        }
    }
    DeliveryNoteEvents(
        event = state.event,
        onSheetProblem = { problem = it },
        onActed = { problem = null },
        onHandled = viewModel::eventHandled,
        followsRoute = false,
    )

    AppScreen(
        header = { AppHeader(title = note.reference, kicker = deliveryNoteKicker(*kicker.toTypedArray())) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            when {
                action == null -> FooterStatus(
                    title = stringResource(R.string.goodsout_extra_shipped_by_platform),
                    body = stringResource(R.string.goodsout_extra_label_from_platform, engine),
                )
                action.isDisabled -> DisabledActionNotice(action = action)
                else -> AikuButton(
                    text = action.label.ifBlank { stringResource(R.string.goodsout_extra_get_shipment_from, engine) },
                    onClick = {
                        problem = null
                        viewModel.act(DeliveryNoteWrite.GetExternalShipment)
                    },
                    icon = R.drawable.ic_ui_truck,
                    isLoading = state.isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
    ) {
        StateStepper(steps = deliveryNoteSteps(note.stage ?: DeliveryNoteStage.Packed))
        PlatformShipmentCard(platform = engine)
        problem?.let { InlineAlert(title = it, tone = AlertTone.Danger) }
        ReadOnlyParcels(parcels = note.parcels, withBoxes = note.boxPackingList != null)
        HintText(text = stringResource(R.string.goodsout_external_shipment_hint, engine))
    }
}

@Composable
private fun PlatformShipmentCard(platform: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(16.dp),
    ) {
        ExtraIconTile(icon = R.drawable.ic_ui_truck, tone = Tone.Primary)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = stringResource(R.string.goodsout_extra_shipped_by_platform),
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
            )
            Text(text = stringResource(R.string.goodsout_extra_label_from_platform, platform), fontSize = 13.sp, color = colors.textTertiary)
        }
    }
}
