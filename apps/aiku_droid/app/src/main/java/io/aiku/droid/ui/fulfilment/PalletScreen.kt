package io.aiku.droid.ui.fulfilment

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.size
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentInboundErrorKey
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletWrite
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.fulfilment.inbound.InboundLoading
import io.aiku.droid.ui.fulfilment.inbound.MovePalletSheet
import io.aiku.droid.ui.fulfilment.inbound.PalletUiState
import io.aiku.droid.ui.fulfilment.inbound.PalletViewModel
import io.aiku.droid.ui.fulfilment.inbound.StartAudit
import io.aiku.droid.ui.fulfilment.inbound.StoredItemList
import io.aiku.droid.ui.fulfilment.inbound.incidentStates
import io.aiku.droid.ui.fulfilment.inbound.incidentTone
import io.aiku.droid.ui.fulfilment.inbound.palletDoneText
import io.aiku.droid.ui.fulfilment.inbound.palletLocationText
import io.aiku.droid.ui.fulfilment.inbound.palletTone
import io.aiku.droid.ui.fulfilment.inbound.palletTypeText
import io.aiku.droid.ui.fulfilment.inbound.quotedReference
import io.aiku.droid.ui.fulfilment.inbound.rememberPallet
import io.aiku.droid.ui.goodsin.GoodsInEvents
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsin.goodsInDate
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StoredItemAuditRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class PalletSheet { Move, Incident }

private data class PalletAction(
    @StringRes val title: Int,
    @StringRes val subtitle: Int,
    @DrawableRes val icon: Int,
    val tone: Tone,
    val onClick: () -> Unit,
    val isWorking: Boolean = false,
)

@Composable
fun PalletScreen(container: AppContainer, warehouseId: Long, id: Long, reference: String) {
    val pallet = rememberPallet(container, warehouseId, id)
    val state by pallet.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { pallet.onStart() }

    RequireFulfilment(section = R.string.section_stored_pallets) {
        val loaded = state.pallet
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = pallet::signInAgain)
            loaded != null -> PalletContent(viewModel = pallet, state = state, pallet = loaded)
            else -> InboundLoading(
                title = reference,
                kicker = stringResource(R.string.fulfilment_type_pallet),
                problem = state.problem,
                failedTitle = stringResource(R.string.fulfilment_inbound_pallet_failed, reference),
                onRetry = pallet::retry,
            )
        }
    }
}

@Composable
private fun PalletContent(viewModel: PalletViewModel, state: PalletUiState, pallet: Pallet) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    var sheet by remember { mutableStateOf<PalletSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val incidentStates = pallet.incidentStates()
    val move = pallet.action(FulfilmentInboundActionKey.MOVE)
    val audit = pallet.action(FulfilmentInboundActionKey.AUDIT)
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
        viewModel.clearWriteErrors()
    }
    val openSheet: (PalletSheet) -> Unit = {
        sheetProblem = null
        viewModel.clearWriteErrors()
        sheet = it
    }
    val startedAudit = state.startedAudit
    val actions = listOfNotNull(
        PalletAction(
            title = R.string.fulfilment_pallet_move,
            subtitle = R.string.fulfilment_pallet_move_hint,
            icon = R.drawable.ic_ui_location_label,
            tone = Tone.Primary,
            onClick = { openSheet(PalletSheet.Move) },
        ).takeIf { move != null },
        PalletAction(
            title = if (pallet.openAuditId != null) R.string.fulfilment_inbound_continue_audit else R.string.fulfilment_pallet_audit,
            subtitle = R.string.fulfilment_pallet_audit_hint,
            icon = R.drawable.ic_ui_check,
            tone = Tone.Primary,
            isWorking = state.working == StartAudit,
            onClick = viewModel::startAudit,
        ).takeIf { audit != null },
        PalletAction(
            title = R.string.fulfilment_pallet_incident,
            subtitle = R.string.fulfilment_pallet_incident_hint,
            icon = R.drawable.ic_ui_warning,
            tone = Tone.Danger,
            onClick = { openSheet(PalletSheet.Incident) },
        ).takeIf { incidentStates.isNotEmpty() },
    )

    GoodsInEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        doneText = { done -> palletDoneText(done, state.pallet ?: pallet) { id, args -> resources.getString(id, *args) } },
        onDone = { done ->
            if (done.action is PalletWrite) {
                sheet = null
                sheetProblem = null
            }
        },
    )
    LaunchedEffect(startedAudit) {
        if (startedAudit != null) {
            navigator.open(StoredItemAuditRoute(id = startedAudit.auditId, reference = pallet.reference))
            viewModel.auditOpened()
        }
    }

    AppScreen(
        header = {
            AppHeader(
                title = pallet.reference,
                kicker = dotJoined(palletTypeText(pallet) ?: stringResource(R.string.fulfilment_type_pallet), pallet.customer?.name),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.fulfilment_inbound_pallet_refresh_failed), onRetry = viewModel::retry) }
        PalletFactsCard(pallet = pallet)
        IncidentNotice(pallet = pallet)
        PalletDetailCard(pallet = pallet)
        if (pallet.itemsStorage) {
            SectionHeading(title = stringResource(R.string.fulfilment_stored_items), caption = countText(pallet.storedItems.size))
            if (pallet.storedItems.isEmpty()) {
                FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_no_stored_items))
            }
            StoredItemList(items = pallet.storedItems)
        }
        if (actions.isNotEmpty()) {
            SectionHeading(title = stringResource(R.string.fulfilment_pallet_actions))
            ListGroup {
                actions.forEachIndexed { index, action ->
                    if (index > 0) {
                        RowDivider()
                    }
                    FulfilmentRow(
                        title = stringResource(action.title),
                        subtitle = stringResource(action.subtitle),
                        leading = { IconTile(icon = action.icon, tone = action.tone) },
                        trailing = if (action.isWorking) ({ CircularProgressIndicator(color = colors.link, strokeWidth = 2.dp, modifier = Modifier.size(20.dp)) }) else null,
                        onClick = action.onClick,
                    )
                }
            }
        }
    }

    when (sheet) {
        PalletSheet.Move -> MovePalletSheet(
            pallet = pallet,
            finder = viewModel.finder,
            isWorking = state.working is PalletWrite.Move,
            problem = sheetProblem ?: listOf(FulfilmentInboundErrorKey.LOCATION_ID, FulfilmentInboundErrorKey.LOCATION_CODE)
                .firstNotNullOfOrNull { state.writeErrors[it]?.firstOrNull() },
            onMove = viewModel::move,
            onDismiss = closeSheet,
        )
        PalletSheet.Incident -> PalletIncidentSheet(
            pallet = pallet,
            states = incidentStates,
            isWorking = state.working is PalletWrite.Incident,
            problem = sheetProblem ?: state.writeErrors[FulfilmentInboundErrorKey.MESSAGE]?.firstOrNull(),
            onSave = viewModel::reportIncident,
            onDismiss = closeSheet,
        )
        null -> Unit
    }
}

@Composable
private fun PalletFactsCard(pallet: Pallet) {
    AikuCard {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            PalletFact(
                label = stringResource(R.string.fulfilment_pallet_state),
                value = pallet.stateLabel.ifBlank { pallet.state },
                valueTone = palletTone(pallet),
                modifier = Modifier.weight(1f),
            )
            PalletFact(
                label = stringResource(R.string.fulfilment_inbound_status),
                value = pallet.statusLabel.ifBlank { pallet.status },
                modifier = Modifier.weight(1f),
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            PalletFact(
                label = stringResource(R.string.fulfilment_pallet_type),
                value = palletTypeText(pallet).orEmpty(),
                modifier = Modifier.weight(1f),
            )
            PalletFact(
                label = stringResource(R.string.fulfilment_pallet_location),
                value = palletLocationText(pallet),
                modifier = Modifier.weight(1f),
            )
        }
        quotedReference(pallet.customerReference)?.let {
            PalletFact(label = stringResource(R.string.fulfilment_pallet_customer_reference), value = it, modifier = Modifier.fillMaxWidth())
        }
        pallet.notes?.takeIf { it.isNotBlank() }?.let {
            PalletFact(label = stringResource(R.string.fulfilment_inbound_notes), value = it, modifier = Modifier.fillMaxWidth())
        }
    }
}

@Composable
private fun IncidentNotice(pallet: Pallet) {
    val incident = pallet.incident ?: return
    val zone = organisationZone()
    val returnReference = incident.palletReturn?.reference?.takeIf { it.isNotBlank() }

    InlineAlert(
        title = incident.stateLabel.ifBlank { pallet.stateLabel },
        body = dotJoined(
            returnReference?.let { stringResource(R.string.fulfilment_inbound_incident_in_return, it) },
            quotedReference(incident.message),
            incident.reporter?.name,
            goodsInDate(incident.date, zone),
        ).takeIf { it.isNotBlank() },
        tone = if (incidentTone(incident.state) == Tone.Warn) AlertTone.Warn else AlertTone.Danger,
    )
}

@Composable
private fun PalletDetailCard(pallet: Pallet) {
    val zone = organisationZone()
    val lines = listOfNotNull(
        pallet.customer?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        pallet.delivery?.reference?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_goods_in, text = stringResource(R.string.fulfilment_inbound_from_delivery, it)) },
        pallet.palletReturn?.reference?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_goods_out, text = stringResource(R.string.fulfilment_inbound_on_return, it)) },
        goodsInDate(pallet.receivedAt, zone)?.let { DetailLine(icon = R.drawable.ic_ui_check, text = stringResource(R.string.fulfilment_inbound_received_on, it)) },
        goodsInDate(pallet.bookedInAt, zone)?.let { DetailLine(icon = R.drawable.ic_ui_pallet, text = stringResource(R.string.fulfilment_inbound_booked_in_on, it)) },
        goodsInDate(pallet.storingAt, zone)?.let { DetailLine(icon = R.drawable.ic_ui_location, text = stringResource(R.string.fulfilment_inbound_storing_since, it)) },
    )
    if (lines.isNotEmpty()) {
        DetailCard(title = stringResource(R.string.fulfilment_inbound_details), lines = lines)
    }
}

@Composable
private fun PalletFact(label: String, value: String, modifier: Modifier = Modifier, valueTone: Tone? = null) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary)
        Text(
            text = value,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = valueTone?.let { colors.tone(it).content } ?: colors.text,
        )
    }
}
