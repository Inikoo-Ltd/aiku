package io.aiku.droid.ui.fulfilment.outbound

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.FulfilmentOutboundErrorKey
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnActionKey
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.data.api.Shipper
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsin.HelperNote
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.IconTile40
import io.aiku.droid.ui.goodsout.MAX_PARCELS
import io.aiku.droid.ui.goodsout.ParcelCheck
import io.aiku.droid.ui.goodsout.ParcelField
import io.aiku.droid.ui.goodsout.ParcelFieldKey
import io.aiku.droid.ui.goodsout.ParcelInput
import io.aiku.droid.ui.goodsout.ParcelInputField
import io.aiku.droid.ui.goodsout.ParcelListProblem
import io.aiku.droid.ui.goodsout.ParcelProblem
import io.aiku.droid.ui.goodsout.checkParcels
import io.aiku.droid.ui.goodsout.editableNumber
import io.aiku.droid.ui.goodsout.extra.FieldLabel
import io.aiku.droid.ui.goodsout.extra.PlainTextField
import io.aiku.droid.ui.goodsout.parcelServerErrors
import io.aiku.droid.ui.goodsout.shipperChoices
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

const val SHIPMENT_PURPOSE = "shipment"

private const val SHIPPER_SEARCH_FROM = 7
private const val MAX_WEIGHT_KG = 1000.0
private const val MAX_DIMENSION_CM = 1000.0
private val parcelFields = listOf(ParcelField.Weight, ParcelField.Length, ParcelField.Width, ParcelField.Height)

@Composable
internal fun ShipmentContent(viewModel: PalletReturnViewModel, state: PalletReturnUiState, palletReturn: PalletReturn) {
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    val colors = LocalAikuColors.current
    val canAdd = palletReturn.can(PalletReturnActionKey.ADD_SHIPMENT)
    val (apiShippers, manualShippers) = shipperChoices(state.shippers.items)
    val shown = apiShippers + manualShippers
    var chosenId by rememberSaveable { mutableStateOf<Long?>(null) }
    var tracking by rememberSaveable { mutableStateOf("") }
    var search by rememberSaveable { mutableStateOf("") }
    var trackingMissing by remember { mutableStateOf(false) }
    var problem by remember { mutableStateOf<String?>(null) }
    val selected = shown.firstOrNull { it.id == chosenId }
    val saved = remember(palletReturn.parcels) { palletReturn.parcels.map { ParcelInput.of(it.asParcel()) } }
    var parcels by remember(palletReturn.parcels) { mutableStateOf(saved.ifEmpty { listOf(ParcelInput.added(estimatedWeightKg = null, parcelCount = 1)) }) }
    var parcelCheck by remember(palletReturn.parcels) { mutableStateOf<ParcelCheck.Invalid?>(null) }
    val serverParcels = remember(state.writeErrors) { parcelServerErrors(state.writeErrors) }
    val shipperErrors = state.writeErrors[FulfilmentOutboundErrorKey.SHIPPER_ID].orEmpty()
    val trackingError = state.writeErrors[FulfilmentOutboundErrorKey.TRACKING]?.firstOrNull()
        ?: if (trackingMissing) stringResource(R.string.goodsout_tracking_required) else null
    val isAdding = state.working is PalletReturnWrite.AddShipment
    val coveredByFields = shipperErrors.isNotEmpty() || trackingError != null || serverParcels.general.isNotEmpty() || serverParcels.rows.isNotEmpty()

    LaunchedEffect(Unit) { viewModel.loadShippers() }
    OutboundEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = { problem = it },
        doneText = { done -> returnDoneText(done.action, palletReturn, resources) },
        onDone = { done ->
            if (done.action is PalletReturnWrite.AddShipment) {
                navigator.back()
            }
        },
    )

    AppScreen(
        header = { ReturnHeader(palletReturn = palletReturn, title = stringResource(R.string.goodsout_add_shipment)) },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = selected?.takeIf { canAdd }?.let { shipper ->
            {
                AikuButton(
                    text = stringResource(if (shipper.isApi) R.string.goodsout_add_shipment_create_label else R.string.goodsout_add_shipment),
                    onClick = {
                        problem = null
                        if (shipper.isApi) {
                            when (val check = checkParcels(parcels, withBoxes = false)) {
                                is ParcelCheck.Valid -> viewModel.createShipment(shipper.id, tracking = null, parcels = check.parcels.takeIf { parcels != saved })
                                is ParcelCheck.Invalid -> parcelCheck = check
                            }
                        } else {
                            val manualTracking = tracking.trim().takeIf { it.isNotEmpty() }
                            if (shipper.needsTracking && manualTracking == null) {
                                trackingMissing = true
                            } else {
                                viewModel.createShipment(shipper.id, tracking = manualTracking, parcels = null)
                            }
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
        ReturnRefreshProblem(problem = state.problem, onRetry = viewModel::retry)
        if (!canAdd) {
            InlineAlert(title = stringResource(R.string.fulfilment_out_no_shipment_title), body = stringResource(R.string.fulfilment_out_no_shipment_body), tone = AlertTone.Info)
        } else {
            when {
                state.shippers.isLoading && shown.isEmpty() -> SkeletonListCard(rows = 3)
                state.shippers.problem != null -> GoodsOutLoadProblem(
                    problem = state.shippers.problem,
                    title = stringResource(R.string.goodsout_shippers_failed),
                    onRetry = viewModel::loadShippers,
                )
                shown.isEmpty() -> HelperNote(text = stringResource(R.string.fulfilment_out_no_shippers))
                else -> {
                    if (apiShippers.isNotEmpty()) {
                        SectionHeading(title = stringResource(R.string.goodsout_api_shippers))
                        ShipperRows(shippers = apiShippers, selectedId = selected?.id, onChoose = {
                            chosenId = it.id
                            trackingMissing = false
                        })
                    }
                    if (manualShippers.isNotEmpty()) {
                        SectionHeading(title = stringResource(R.string.goodsout_other_shippers))
                        if (manualShippers.size >= SHIPPER_SEARCH_FROM) {
                            SearchField(
                                value = search,
                                onValueChange = { search = it },
                                placeholder = stringResource(R.string.goodsout_select_shipping),
                                modifier = Modifier.fillMaxWidth(),
                            )
                        }
                        ShipperRows(
                            shippers = manualShippers.filter {
                                search.isBlank() || it.name.contains(search, ignoreCase = true) || it.code.contains(search, ignoreCase = true) || it.id == selected?.id
                            },
                            selectedId = selected?.id,
                            onChoose = {
                                chosenId = it.id
                                trackingMissing = false
                            },
                        )
                    }
                }
            }
            shipperErrors.takeIf { it.isNotEmpty() }?.let { InlineAlert(title = it.joinToString(" "), tone = AlertTone.Danger) }
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
                    trackingError?.let { Text(text = it, fontSize = 12.sp, color = colors.danger) }
                }
            }
            if (selected?.isApi == true) {
                ReturnParcelsEditor(
                    inputs = parcels,
                    onChange = {
                        parcels = it
                        parcelCheck = null
                    },
                    check = parcelCheck,
                    serverRows = serverParcels.rows,
                    serverFields = serverParcels.fields,
                    serverGeneral = serverParcels.general,
                )
            }
            problem?.takeIf { !coveredByFields }?.let { InlineAlert(title = it, tone = AlertTone.Danger) }
        }
        HelperNote(text = stringResource(R.string.fulfilment_return_shipment_footnote))
    }
}

@Composable
private fun ShipperRows(shippers: List<Shipper>, selectedId: Long?, onChoose: (Shipper) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        shippers.forEach { shipper ->
            ShipperRow(
                name = shipper.name.ifBlank { shipper.code },
                detail = stringResource(
                    when {
                        shipper.isApi -> R.string.goodsout_shipper_api
                        shipper.needsTracking -> R.string.goodsout_shipper_manual_tracking_required
                        else -> R.string.goodsout_shipper_manual
                    },
                ),
                selected = shipper.id == selectedId,
                onClick = { onChoose(shipper) },
            )
        }
    }
}

@Composable
private fun ShipperRow(name: String, detail: String, selected: Boolean, onClick: () -> Unit) {
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
            Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = detail, fontSize = 12.sp, color = colors.textTertiary)
        }
        if (selected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun ReturnParcelsEditor(
    inputs: List<ParcelInput>,
    onChange: (List<ParcelInput>) -> Unit,
    check: ParcelCheck.Invalid?,
    serverRows: Map<Int, List<String>>,
    serverFields: Set<ParcelFieldKey>,
    serverGeneral: List<String>,
) {
    SectionHeading(title = stringResource(R.string.goodsout_parcels_count, inputs.size), caption = stringResource(R.string.goodsout_parcel_units))
    ListGroup {
        inputs.forEachIndexed { index, input ->
            if (index > 0) {
                RowDivider()
            }
            ParcelRow(
                index = index,
                input = input,
                problems = check?.fields.orEmpty().filterKeys { it.index == index },
                serverMessages = serverRows[index].orEmpty(),
                serverFields = serverFields.filter { it.index == index }.toSet(),
                canRemove = inputs.size > 1,
                onChange = { changed -> onChange(inputs.mapIndexed { position, current -> if (position == index) changed else current }) },
                onRemove = { onChange(inputs.filterIndexed { position, _ -> position != index }) },
            )
        }
        if (inputs.size < MAX_PARCELS) {
            if (inputs.isNotEmpty()) {
                RowDivider()
            }
            Text(
                text = stringResource(if (inputs.isEmpty()) R.string.goodsout_add_first_parcel else R.string.goodsout_add_parcel),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = LocalAikuColors.current.link,
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 48.dp)
                    .clickable(role = Role.Button) { onChange(inputs + ParcelInput.added(estimatedWeightKg = null, parcelCount = inputs.size + 1)) }
                    .padding(horizontal = 14.dp, vertical = 14.dp),
            )
        }
    }
    check?.list?.let { listProblem ->
        InlineAlert(
            title = when (listProblem) {
                ParcelListProblem.NoParcels -> stringResource(R.string.goodsout_parcels_none)
                ParcelListProblem.TooManyParcels -> pluralStringResource(R.plurals.goodsout_parcels_too_many, MAX_PARCELS, MAX_PARCELS)
            },
            tone = AlertTone.Danger,
        )
    }
    serverGeneral.takeIf { it.isNotEmpty() }?.let { InlineAlert(title = it.joinToString(" "), tone = AlertTone.Danger) }
}

@Composable
private fun ParcelRow(
    index: Int,
    input: ParcelInput,
    problems: Map<ParcelFieldKey, ParcelProblem>,
    serverMessages: List<String>,
    serverFields: Set<ParcelFieldKey>,
    canRemove: Boolean,
    onChange: (ParcelInput) -> Unit,
    onRemove: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val name = stringResource(R.string.goodsout_parcel_name, index + 1)
    val messages = problems.values.distinct().map { parcelProblemText(it) } + serverMessages

    Column(modifier = Modifier.fillMaxWidth().padding(start = 14.dp, end = 4.dp, top = 6.dp, bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().heightIn(min = 44.dp)) {
            Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
            if (canRemove) {
                RemoveButton(description = stringResource(R.string.goodsout_parcel_remove, name), onClick = onRemove)
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth().padding(end = 10.dp)) {
            parcelFields.forEach { field ->
                val key = ParcelFieldKey(index, field)
                ParcelInputField(
                    label = stringResource(parcelFieldLabel(field)),
                    value = input.value(field),
                    isWholeNumber = false,
                    isError = key in problems || key in serverFields,
                    onValueChange = { onChange(input.with(field, it)) },
                    modifier = Modifier.weight(1f),
                )
            }
        }
        messages.forEach { message -> Text(text = message, fontSize = 12.sp, color = colors.danger, modifier = Modifier.padding(end = 10.dp)) }
    }
}

@StringRes
private fun parcelFieldLabel(field: ParcelField): Int = when (field) {
    ParcelField.Weight -> R.string.goodsout_parcel_kg
    ParcelField.Length -> R.string.goodsout_parcel_length
    ParcelField.Width -> R.string.goodsout_parcel_width
    ParcelField.Height -> R.string.goodsout_parcel_height
    ParcelField.Boxes -> R.string.goodsout_parcel_boxes_short
}

@Composable
private fun parcelProblemText(problem: ParcelProblem): String = when (problem) {
    ParcelProblem.WeightMissing -> stringResource(R.string.goodsout_parcel_weight_missing)
    ParcelProblem.WeightOutOfRange -> stringResource(R.string.goodsout_parcel_weight_range, editableNumber(MAX_WEIGHT_KG))
    ParcelProblem.DimensionMissing -> stringResource(R.string.goodsout_parcel_dimension_missing)
    ParcelProblem.DimensionOutOfRange -> stringResource(R.string.goodsout_parcel_dimension_range, editableNumber(MAX_DIMENSION_CM))
    ParcelProblem.BoxesInvalid -> stringResource(R.string.goodsout_parcel_boxes_invalid)
}

@Composable
private fun RemoveButton(description: String, onClick: () -> Unit) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_close), contentDescription = null, tint = LocalAikuColors.current.textTertiary, modifier = Modifier.size(18.dp))
    }
}
