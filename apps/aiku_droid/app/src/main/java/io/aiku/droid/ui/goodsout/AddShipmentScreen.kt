package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
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
import io.aiku.droid.data.api.Shipper
import io.aiku.droid.data.api.ShipperDirective
import io.aiku.droid.data.api.WriteErrorKey
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.goodsout.extra.EditAddressSheet
import io.aiku.droid.ui.goodsout.extra.FieldLabel
import io.aiku.droid.ui.goodsout.extra.PlainTextField
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private const val SHIPPER_SEARCH_FROM = 7
private const val LOCKED_BY_CUSTOMER = "customer"
private val shownShipmentErrorKeys = listOf(WriteErrorKey.MESSAGE, WriteErrorKey.SHIPPER, WriteErrorKey.SENDER, WriteErrorKey.OTHERS)

internal val ShipperDirective.chosenShipperId: Long? get() = lockedShipperId ?: preferredShipperId

internal fun shipperChoices(shippers: List<Shipper>): Pair<List<Shipper>, List<Shipper>> =
    shippers.filterNot { it.isLockedOut }.partition { it.isApi }

@Composable
fun AddShipmentScreen(note: DeliveryNoteViewModel, reference: String) {
    val state by note.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { note.onStart() }

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        val loaded = state.note
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = note::signInAgain)
            loaded != null -> AddShipmentContent(viewModel = note, state = state, note = loaded)
            else -> AppScreen(
                header = { ShipmentHeader(reference = reference, title = stringResource(R.string.goodsout_add_shipment)) },
                activeTab = MainTab.Menu,
                showBar = true,
            ) {
                val problem = state.problem
                if (problem == null) {
                    SkeletonListCard(rows = 3)
                } else {
                    GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_note_failed_title, reference), onRetry = note::retry)
                }
            }
        }
    }
}

@Composable
internal fun ShipmentHeader(reference: String, title: String, stateLabel: String? = null) {
    AppHeader(
        title = title,
        kicker = stateLabel?.takeIf { it.isNotBlank() }?.let { stringResource(R.string.goodsout_kicker_reference_state, reference, it) }
            ?: stringResource(R.string.goodsout_delivery_note_reference, reference),
    )
}

@Composable
private fun AddShipmentContent(viewModel: DeliveryNoteViewModel, state: DeliveryNoteUiState, note: DeliveryNote) {
    val navigator = LocalAppNavigator.current
    val directive = state.shipperDirective ?: note.shipping.let {
        ShipperDirective(lockedShipperId = it.lockedShipperId, preferredShipperId = it.preferredShipperId, lockedBy = it.lockedBy, canOverrideLock = it.canOverrideLock, trackingChannel = it.trackingChannel)
    }
    val (apiShippers, manualShippers) = shipperChoices(state.shippers.items)
    val shown = apiShippers + manualShippers
    var chosenId by rememberSaveable { mutableStateOf<Long?>(null) }
    var tracking by rememberSaveable { mutableStateOf("") }
    var search by rememberSaveable { mutableStateOf("") }
    var trackingMissing by remember { mutableStateOf(false) }
    var problem by remember { mutableStateOf<String?>(null) }
    val selected = shown.firstOrNull { it.id == chosenId } ?: shown.firstOrNull { it.id == directive.chosenShipperId }
    val shownErrors = shownShipmentErrorKeys.flatMap { state.writeErrors[it].orEmpty() }
    val trackingError = state.writeErrors[WriteErrorKey.TRACKING]?.firstOrNull()
        ?: if (trackingMissing) stringResource(R.string.goodsout_tracking_required) else null
    val isAdding = state.working is DeliveryNoteWrite.AddShipment
    val choose: (Shipper) -> Unit = {
        chosenId = it.id
        trackingMissing = false
    }

    LaunchedEffect(Unit) { viewModel.loadShippers() }
    DeliveryNoteEvents(
        event = state.event,
        onSheetProblem = { problem = it },
        onActed = { navigator.back() },
        onHandled = viewModel::eventHandled,
        followsRoute = false,
    )

    AppScreen(
        header = { ShipmentHeader(reference = note.reference, title = stringResource(R.string.goodsout_add_shipment), stateLabel = note.stateLabel) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = selected?.let { shipper ->
            {
                AikuButton(
                    text = stringResource(if (shipper.isApi) R.string.goodsout_add_shipment_create_label else R.string.goodsout_add_shipment),
                    onClick = {
                        val manualTracking = tracking.trim().takeIf { !shipper.isApi && it.isNotEmpty() }
                        if (!shipper.isApi && shipper.needsTracking && manualTracking == null) {
                            trackingMissing = true
                        } else {
                            problem = null
                            viewModel.act(DeliveryNoteWrite.AddShipment(shipperId = shipper.id, tracking = manualTracking))
                        }
                    },
                    icon = R.drawable.ic_ui_truck,
                    isLoading = isAdding,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
    ) {
        StateStepper(steps = deliveryNoteSteps(current = note.stage ?: DeliveryNoteStage.Packed))
        ListGroup {
            IconInfoRow(
                icon = R.drawable.ic_ui_location,
                title = stringResource(R.string.goodsout_delivery_address),
                subtitle = note.deliveryAddress?.lines?.filter { it.isNotBlank() }?.joinToString(", ")?.takeIf { it.isNotEmpty() }
                    ?: stringResource(R.string.goodsout_no_address),
            )
        }
        ShipperLockNotice(directive = directive, shippers = state.shippers.items)
        when {
            state.shippers.isLoading && shown.isEmpty() -> SkeletonListCard(rows = 3)
            state.shippers.problem != null -> GoodsOutLoadProblem(
                problem = state.shippers.problem,
                title = stringResource(R.string.goodsout_shippers_failed),
                onRetry = viewModel::loadShippers,
            )
            shown.isEmpty() -> EmptyChoices(text = stringResource(R.string.goodsout_shippers_none))
            else -> {
                if (apiShippers.isNotEmpty()) {
                    GoodsOutHeading(title = stringResource(R.string.goodsout_api_shippers))
                    ShipperList(shippers = apiShippers, selectedId = selected?.id, directive = directive, onChoose = choose)
                }
                if (manualShippers.isNotEmpty()) {
                    GoodsOutHeading(title = stringResource(R.string.goodsout_other_shippers))
                    if (manualShippers.size >= SHIPPER_SEARCH_FROM) {
                        SearchField(
                            value = search,
                            onValueChange = { search = it },
                            placeholder = stringResource(R.string.goodsout_select_shipping),
                            modifier = Modifier.fillMaxWidth(),
                        )
                    }
                    ShipperList(
                        shippers = manualShippers.filter { search.isBlank() || it.name.contains(search, ignoreCase = true) || it.code.contains(search, ignoreCase = true) || it.id == selected?.id },
                        selectedId = selected?.id,
                        directive = directive,
                        onChoose = choose,
                    )
                }
            }
        }
        if (selected != null && !selected.isApi) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                FieldLabel(text = stringResource(if (selected.needsTracking) R.string.goodsout_tracking_number else R.string.goodsout_tracking_number_optional))
                PlainTextField(
                    value = tracking,
                    onValueChange = {
                        tracking = it
                        trackingMissing = false
                    },
                )
                trackingError?.let { Text(text = it, fontSize = 12.sp, color = LocalAikuColors.current.danger) }
            }
        }
        shownErrors.takeIf { it.isNotEmpty() }?.let { InlineAlert(title = it.joinToString(" "), tone = AlertTone.Danger) }
        problem?.takeIf { shownErrors.isEmpty() && state.writeErrors[WriteErrorKey.TRACKING] == null }?.let { InlineAlert(title = it, tone = AlertTone.Danger) }
        GoodsOutFootNote(text = stringResource(if (selected?.isApi == false) R.string.goodsout_manual_shipment_note else R.string.goodsout_add_shipment_note))
    }
    ShipmentFollowUpSheet(note = note, state = state, viewModel = viewModel)
}

@Composable
private fun ShipmentFollowUpSheet(note: DeliveryNote, state: DeliveryNoteUiState, viewModel: DeliveryNoteViewModel) {
    when (val followUp = state.shipmentFollowUp) {
        is ShipmentFollowUp.ConfirmOverride -> {
            val shipper = state.shippers.items.firstOrNull { it.id == followUp.shipperId }?.name ?: stringResource(R.string.goodsout_shipment)
            ActionConfirmSheet(
                title = stringResource(R.string.goodsout_override_lock_title),
                subtitle = null,
                confirmLabel = stringResource(R.string.goodsout_override_lock_confirm, shipper),
                icon = R.drawable.ic_ui_lock,
                hint = followUp.message,
                isWorking = state.isWorking,
                problem = null,
                onConfirm = viewModel::confirmOverrideLock,
                onDismiss = viewModel::dismissShipmentFollowUp,
            )
        }
        is ShipmentFollowUp.FixAddress -> EditAddressSheet(
            reference = note.reference,
            initial = note.deliveryAddress?.fields,
            problem = followUp.message,
            isWorking = state.isWorking,
            onSave = viewModel::retryShipmentWithAddress,
            onDismiss = viewModel::dismissShipmentFollowUp,
        )
        null -> Unit
    }
}

@Composable
private fun ShipperLockNotice(directive: ShipperDirective, shippers: List<Shipper>) {
    val lockedId = directive.lockedShipperId ?: return
    val name = shippers.firstOrNull { it.id == lockedId }?.name ?: return
    val title = stringResource(if (directive.lockedBy == LOCKED_BY_CUSTOMER) R.string.goodsout_shipper_locked_customer else R.string.goodsout_shipper_locked_rule, name)
    val body = if (directive.canOverrideLock) stringResource(R.string.goodsout_shipper_locked_override) else null

    InlineAlert(title = title, body = body, tone = AlertTone.Info)
}

@Composable
private fun ShipperList(shippers: List<Shipper>, selectedId: Long?, directive: ShipperDirective, onChoose: (Shipper) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        shippers.forEach { shipper ->
            val badge = when (shipper.id) {
                directive.lockedShipperId -> stringResource(R.string.goodsout_shipper_locked)
                directive.preferredShipperId -> stringResource(R.string.goodsout_shipper_preferred)
                else -> null
            }
            ShipperChoiceRow(
                name = shipper.name.ifBlank { shipper.code },
                detail = stringResource(
                    when {
                        shipper.isApi -> R.string.goodsout_shipper_api
                        shipper.needsTracking -> R.string.goodsout_shipper_manual_tracking_required
                        else -> R.string.goodsout_shipper_manual
                    },
                ),
                badge = badge,
                selected = shipper.id == selectedId,
                onClick = { onChoose(shipper) },
            )
        }
    }
}

@Composable
private fun ShipperChoiceRow(name: String, detail: String, badge: String?, selected: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 64.dp)
            .clip(shape)
            .background(if (selected) colors.soft else colors.surface)
            .border(if (selected) 2.dp else 1.dp, if (selected) colors.primary else colors.border, shape)
            .selectable(selected = selected, role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        IconTile40(icon = R.drawable.ic_ui_truck, tone = if (selected) Tone.Primary else Tone.Neutral)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f, fill = false))
                badge?.let { StatusBadge(text = it, tone = Tone.Primary) }
            }
            Text(text = detail, fontSize = 12.sp, color = colors.textTertiary)
        }
        if (selected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        }
    }
}
