package io.aiku.droid.ui.fulfilment

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveryRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab

private val deliveryFilters = listOf(
    FulfilmentDeliveryState.Confirmed,
    FulfilmentDeliveryState.Received,
    FulfilmentDeliveryState.BookingIn,
    FulfilmentDeliveryState.BookedIn,
    FulfilmentDeliveryState.NotReceived,
)

@Composable
fun FulfilmentDeliveriesScreen() {
    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        FulfilmentDeliveriesContent()
    }
}

@Composable
private fun FulfilmentDeliveriesContent() {
    var selectedFilter by remember { mutableIntStateOf(deliveryFilters.indexOf(FulfilmentDeliveryState.Received)) }
    val selectedState = deliveryFilters[selectedFilter]
    val (inSelectedState, inOtherStates) = fulfilmentDeliveriesSample.partition { it.state == selectedState }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.FulfilmentDeliveries) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        FilterChips(
            options = deliveryFilters.map { state ->
                FilterOption(label = filterChipLabel(label = stringResource(state.label), count = fulfilmentDeliveryCountsSample[state]))
            },
            selectedIndex = selectedFilter,
            onSelect = { selectedFilter = it },
            modifier = Modifier.fakeData(cornerRadius = 17.dp),
        )
        if (inSelectedState.isNotEmpty()) {
            DeliveryGroup(deliveries = inSelectedState)
            if (inOtherStates.isNotEmpty()) {
                FulfilmentSectionHeading(text = stringResource(R.string.fulfilment_deliveries_other_states))
            }
        }
        if (inOtherStates.isNotEmpty()) {
            DeliveryGroup(deliveries = inOtherStates)
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_deliveries_footnote))
    }
}

@Composable
private fun DeliveryGroup(deliveries: List<FulfilmentDeliverySample>) {
    val navigator = LocalAppNavigator.current

    ListGroup(modifier = Modifier.fakeData()) {
        deliveries.forEachIndexed { index, delivery ->
            if (index > 0) {
                RowDivider()
            }
            ItemRow(
                code = delivery.reference,
                subtitle = delivery.summary,
                chip = stringResource(delivery.state.label),
                chipTone = delivery.state.tone,
                showPhoto = false,
                onClick = { navigator.open(delivery.route()) },
            )
        }
    }
}

private fun FulfilmentDeliverySample.route(): Any = when (state) {
    FulfilmentDeliveryState.BookingIn -> FulfilmentBookInRoute(reference)
    FulfilmentDeliveryState.BookedIn -> FulfilmentBookedInRoute(reference)
    else -> FulfilmentDeliveryRoute(reference)
}
