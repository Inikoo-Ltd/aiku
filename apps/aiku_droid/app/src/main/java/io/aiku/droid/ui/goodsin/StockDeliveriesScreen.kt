package io.aiku.droid.ui.goodsin

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.BookedInRoute
import io.aiku.droid.ui.navigation.BookingInRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StockDeliveryArrivingRoute
import io.aiku.droid.ui.navigation.StockDeliveryCheckingRoute

private const val RECEIVED_CHIP_INDEX = 1

private fun SampleDelivery.route(): Any = when (state) {
    DeliveryState.Arriving -> StockDeliveryArrivingRoute(reference)
    DeliveryState.Received -> StockDeliveryCheckingRoute(reference)
    DeliveryState.Checked, DeliveryState.BookingIn -> BookingInRoute(reference)
    DeliveryState.BookedIn -> BookedInRoute(reference)
}

@Composable
fun StockDeliveriesScreen() {
    var showFilters by remember { mutableStateOf(false) }
    var selectedChip by remember { mutableIntStateOf(RECEIVED_CHIP_INDEX) }
    val states = DeliveryState.entries
    val chipOptions = states.map { state ->
        val label = stringResource(state.label)
        val count = GoodsInSamples.stateCounts[state]
        FilterOption(label = if (count != null) stringResource(R.string.goodsin_chip_with_count, label, count) else label)
    } + FilterOption(label = stringResource(R.string.goodsin_filter_all))
    val selectedState = states.getOrNull(selectedChip)
    val matching = GoodsInSamples.deliveries.filter { selectedState == null || it.state == selectedState }
    val others = GoodsInSamples.deliveries - matching.toSet()

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                ModuleHeader(
                    section = WarehouseSection.StockDeliveries,
                    actions = {
                        HeaderIconButton(
                            icon = R.drawable.ic_ui_search,
                            description = stringResource(R.string.goodsin_search_filter),
                            onClick = { showFilters = true },
                            filled = true,
                        )
                    },
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            FilterChips(
                options = chipOptions,
                selectedIndex = selectedChip,
                onSelect = { selectedChip = it },
                modifier = Modifier.fakeData(cornerRadius = 17.dp),
            )
            if (matching.isNotEmpty()) {
                DeliveryGroup(deliveries = matching)
            }
            if (others.isNotEmpty()) {
                SectionHeading(title = stringResource(R.string.goodsin_other_states))
                DeliveryGroup(deliveries = others)
            }
        }
    }

    if (showFilters) {
        StockDeliveryFilterSheet(onDismiss = { showFilters = false })
    }
}

@Composable
private fun DeliveryGroup(deliveries: List<SampleDelivery>) {
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
