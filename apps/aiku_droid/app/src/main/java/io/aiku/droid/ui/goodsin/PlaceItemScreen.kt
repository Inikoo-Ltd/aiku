package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalResources
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
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.GoodsInErrorKey
import io.aiku.droid.data.api.ItemLocation
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.PlaceTarget
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryItem
import io.aiku.droid.data.api.StockDeliveryWrite
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.DisabledActionNotice
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.LocationPicker
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.sync.OfflineCopyNote
import io.aiku.droid.ui.sync.QueueEventEffect
import io.aiku.droid.ui.theme.LocalAikuColors

private sealed interface PlaceChoice {
    data class Link(val locationOrgStockId: Long) : PlaceChoice
    data object Another : PlaceChoice
}

private fun StockDeliveryItem.preferredChoice(): PlaceChoice {
    val preferred = locations.firstOrNull { it.isDefaultWholesale }
        ?: locations.firstOrNull { it.stockType == LocationStockType.Picking }
        ?: locations.firstOrNull()
    return preferred?.let { PlaceChoice.Link(it.locationOrgStockId) } ?: PlaceChoice.Another
}

@Composable
fun PlaceItemScreen(delivery: StockDeliveryViewModel, itemId: Long, skoCode: String) {
    val state by delivery.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { delivery.onStart() }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        val loaded = state.delivery
        val item = loaded?.item(itemId)
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = delivery::signInAgain)
            loaded != null && item != null -> PlaceItemContent(viewModel = delivery, state = state, delivery = loaded, item = item)
            loaded != null -> PlaceItemGone(itemId = itemId)
            else -> StockDeliveryLoading(reference = stringResource(R.string.goodsin_place_item, skoCode), problem = state.problem, onRetry = delivery::retry)
        }
    }
}

@Composable
private fun PlaceItemGone(itemId: Long) {
    val navigator = LocalAppNavigator.current
    LaunchedEffect(itemId) { navigator.back() }
}

@Composable
private fun PlaceItemContent(viewModel: StockDeliveryViewModel, state: StockDeliveryUiState, delivery: StockDelivery, item: StockDeliveryItem) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    val finderState by viewModel.finder.state.collectAsStateWithLifecycle()
    val isOnline by viewModel.isOnline.collectAsStateWithLifecycle()
    val scanFeedback = LocalScanController.current.feedback
    var choice by remember(item.id) { mutableStateOf(item.preferredChoice()) }
    var quantity by remember(item.id) { mutableDoubleStateOf(item.skoToPlace) }
    var setAsPicking by remember(item.id) { mutableStateOf(item.locations.isEmpty()) }
    var isLocationMissing by remember { mutableStateOf(false) }
    val remaining = item.skoToPlace
    val chosenLink = (choice as? PlaceChoice.Link)?.let { link -> item.locations.firstOrNull { it.locationOrgStockId == link.locationOrgStockId } }
    val picked = finderState.picked
    val target: PlaceTarget? = when {
        chosenLink != null -> PlaceTarget.Link(chosenLink.locationOrgStockId, chosenLink.location.code)
        choice == PlaceChoice.Another && picked != null && isOnline ->
            item.locations.firstOrNull { it.location.id == picked.id }?.let { PlaceTarget.Link(it.locationOrgStockId, it.location.code) }
                ?: PlaceTarget.Location(picked.id, picked.code)
        else -> null
    }
    val place = delivery.action(GoodsInActionKey.PLACE)
    val shownQuantity = quantity.coerceIn(0.0, remaining.coerceAtLeast(0.0))
    val errors = state.writeErrors
    val fieldError = listOf(GoodsInErrorKey.QUANTITY, GoodsInErrorKey.LOCATION_ORG_STOCK_ID, GoodsInErrorKey.LOCATION_ID)
        .firstNotNullOfOrNull { errors[it]?.firstOrNull() }
    val run: (Boolean) -> Unit = { isAll ->
        val chosen = target
        if (chosen == null) {
            isLocationMissing = true
        } else if (isAll) {
            viewModel.placeAll(item.id, chosen, setAsPicking && !item.hasPickingLocation)
        } else {
            viewModel.place(item.id, shownQuantity, chosen, setAsPicking && !item.hasPickingLocation)
        }
    }

    LaunchedEffect(item.id) { viewModel.finder.reset() }
    ScanEffect(enabled = choice != PlaceChoice.Another) { scan ->
        isLocationMissing = false
        val linked = item.locations.firstOrNull { it.location.code.equals(scan.code.trim(), ignoreCase = true) }
        if (linked != null) {
            choice = PlaceChoice.Link(linked.locationOrgStockId)
        } else if (isOnline) {
            choice = PlaceChoice.Another
            viewModel.finder.scan(scan.code)
        } else {
            scanFeedback.rejected()
        }
    }
    GoodsInEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        doneText = { done -> placedText(done.action, { id, args -> resources.getString(id, *args) }, delivery = state.delivery ?: delivery) },
        onDone = { done ->
            val after = state.delivery?.item(item.id)
            if (done.action is StockDeliveryWrite.Place || done.action is StockDeliveryWrite.PlaceAll) {
                if (after == null || !after.canBePlaced) {
                    navigator.back()
                } else {
                    quantity = after.skoToPlace
                }
            }
        },
    )
    QueueEventEffect(event = state.queueEvent, reference = delivery.reference, onHandled = viewModel::queueEventHandled, onQueued = navigator::back)

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.goodsin_place_item, item.code),
                kicker = stringResource(R.string.goodsin_kicker_booking_in, delivery.reference),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = when {
            place == null -> ({ FooterStatus(title = delivery.stateLabel, body = stringResource(R.string.goodsin_place_not_allowed)) })
            place.isDisabled -> ({ DisabledActionNotice(action = place) })
            !item.canBePlaced -> ({ FooterStatus(title = stringResource(R.string.goodsin_chip_placed)) })
            else -> ({
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    AikuButton(
                        text = stringResource(R.string.goodsin_place_all),
                        onClick = { run(true) },
                        style = ButtonStyle.Soft,
                        icon = R.drawable.ic_ui_check_double,
                        isLoading = state.working is StockDeliveryWrite.PlaceAll,
                        height = 52.dp,
                    )
                    AikuButton(
                        text = target?.let { stringResource(R.string.goodsin_place_in, quantityText(shownQuantity), it.code) }
                            ?: stringResource(R.string.goodsin_place_quantity, quantityText(shownQuantity)),
                        onClick = { run(false) },
                        icon = goodsInActionLook(GoodsInActionKey.PLACE).icon,
                        isLoading = state.working is StockDeliveryWrite.Place,
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
            })
        },
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsin_delivery_refresh_failed), onRetry = viewModel::retry) }
        state.copyAt?.let { OfflineCopyNote(copyAt = it) }
        if (state.unsurePlacement != null) {
            InlineAlert(
                title = stringResource(R.string.goodsin_place_unsure_title),
                body = stringResource(R.string.goodsin_place_unsure_body),
                tone = AlertTone.Warn,
                actionLabel = stringResource(R.string.goodsout_try_again),
                onAction = viewModel::retryPlacement,
            )
        }
        PlaceItemCard(
            code = item.code,
            subtitle = stringResource(R.string.goodsin_item_to_place, item.name, quantityText(remaining, item.skoToPlaceLabel)),
            imageUrl = item.sko?.imageUrl,
            chip = stringResource(R.string.goodsin_no_picking_location).takeIf { !item.hasPickingLocation },
            chipTone = Tone.Warn,
        )
        SectionHeading(title = stringResource(R.string.goodsin_where_does_it_go))
        item.locations.forEach { location ->
            ChoiceRow(
                title = location.location.code,
                subtitle = locationDetail(location),
                selected = choice == PlaceChoice.Link(location.locationOrgStockId),
                onClick = {
                    isLocationMissing = false
                    choice = PlaceChoice.Link(location.locationOrgStockId)
                },
            )
        }
        if (isOnline) {
            ChoiceRow(
                title = stringResource(R.string.goodsin_another_location),
                subtitle = picked?.takeIf { choice == PlaceChoice.Another }?.code ?: stringResource(R.string.goodsin_another_location_body),
                selected = choice == PlaceChoice.Another,
                onClick = {
                    isLocationMissing = false
                    choice = PlaceChoice.Another
                },
            )
        } else {
            HelperNote(text = stringResource(R.string.offline_stock_another_location_offline))
        }
        if (choice == PlaceChoice.Another && isOnline) {
            LocationPicker(
                state = finderState,
                placeholder = stringResource(R.string.goodsin_search_location),
                onQueryChange = viewModel.finder::search,
                onPick = { id -> finderState.results.firstOrNull { it.id == id }?.let(viewModel.finder::pick) },
                onScanned = { code ->
                    val linked = item.locations.firstOrNull { it.location.code.equals(code.trim(), ignoreCase = true) }
                    if (linked != null) {
                        choice = PlaceChoice.Link(linked.locationOrgStockId)
                    } else {
                        viewModel.finder.scan(code)
                    }
                },
            )
        }
        if (isLocationMissing) {
            Text(text = stringResource(R.string.goodsin_choose_location_first), fontSize = 13.sp, color = colors.danger)
        }
        if (remaining > 0.0) {
            SectionHeading(title = stringResource(R.string.goodsin_quantity), caption = stringResource(R.string.goodsin_up_to, quantityText(remaining, item.skoToPlaceLabel)))
            QuantityStepper(
                value = shownQuantity,
                valueText = quantityText(shownQuantity),
                onValueChange = { quantity = it },
                min = minOf(1.0, remaining),
                max = remaining,
            )
        }
        fieldError?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        if (!item.hasPickingLocation) {
            SetAsPickingRow(checked = setAsPicking, onCheckedChange = { setAsPicking = it })
            HelperNote(text = stringResource(R.string.goodsin_set_as_picking_note))
        } else {
            PickingDefaults(item = item)
        }
        HelperNote(text = stringResource(R.string.goodsin_place_note))
    }
}

private fun placedText(action: Any, text: (Int, Array<out Any>) -> String, delivery: StockDelivery): String? = when (action) {
    is StockDeliveryWrite.Place -> text(R.string.goodsin_toast_placed, arrayOf(quantityText(action.quantity), delivery.item(action.itemId)?.code.orEmpty(), action.target.code))
    is StockDeliveryWrite.PlaceAll -> text(R.string.goodsin_toast_placed_all, arrayOf(delivery.item(action.itemId)?.code.orEmpty(), action.target.code))
    else -> null
}

@Composable
private fun locationDetail(location: ItemLocation): String = listOfNotNull(
    when (location.stockType) {
        LocationStockType.Picking -> stringResource(R.string.goodsin_location_picking)
        LocationStockType.Storing -> stringResource(R.string.goodsin_location_storing)
        null -> null
    },
    stringResource(R.string.goodsin_location_wholesale).takeIf { location.isDefaultWholesale },
    stringResource(R.string.goodsin_location_dropshipping).takeIf { location.isDefaultDropshipping },
    stringResource(R.string.goodsin_in_location, quantityText(location.quantity, location.quantityLabel)),
).joinToString(LIST_SEPARATOR)

@Composable
private fun PickingDefaults(item: StockDeliveryItem) {
    val wholesale = item.locations.firstOrNull { it.isDefaultWholesale }?.location?.code
    val dropshipping = item.locations.firstOrNull { it.isDefaultDropshipping }?.location?.code
    val lines = listOfNotNull(
        wholesale?.let { stringResource(R.string.goodsin_picking_for_wholesale, it) },
        dropshipping?.let { stringResource(R.string.goodsin_picking_for_dropshipping, it) },
    )
    if (lines.isEmpty()) {
        return
    }
    ListGroup {
        ActionLinkRow(
            icon = R.drawable.ic_ui_location,
            title = stringResource(R.string.goodsin_picking_location),
            subtitle = lines.joinToString(LIST_SEPARATOR),
            tone = ActionTone.Primary,
        )
    }
}

@Composable
private fun SetAsPickingRow(checked: Boolean, onCheckedChange: (Boolean) -> Unit) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clip(shape)
            .background(colors.warnSoft)
            .border(width = 1.dp, color = colors.warnBorder, shape = shape)
            .toggleable(value = checked, role = Role.Checkbox, onValueChange = onCheckedChange)
            .padding(start = 14.dp, end = 4.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = stringResource(R.string.goodsin_set_as_picking), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            Text(text = stringResource(R.string.goodsin_set_as_picking_body), fontSize = 12.sp, color = colors.warnInk)
        }
        Checkbox(
            checked = checked,
            onCheckedChange = null,
            colors = CheckboxDefaults.colors(checkedColor = colors.primary, checkmarkColor = colors.onPrimary, uncheckedColor = colors.borderStrong),
        )
    }
}
