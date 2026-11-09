package io.aiku.droid.ui.goodsin

import androidx.annotation.StringRes
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderSearchRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.StockDeliveryDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class StockDeliveryState(
    @StringRes val label: Int,
    val tone: Tone,
    @StringRes val actionLabel: Int?,
    val actionStyle: ButtonStyle,
) {
    Dispatched(R.string.goodsin_state_dispatched, Tone.Neutral, null, ButtonStyle.Outline),
    Received(R.string.goodsin_state_received, Tone.Success, R.string.goodsin_check_items, ButtonStyle.Primary),
    Checked(R.string.goodsin_state_checked, Tone.Primary, R.string.goodsin_place_in_locations, ButtonStyle.Soft),
}

private data class StockDeliverySample(
    val reference: String,
    val state: StockDeliveryState,
    val summary: String,
    val isNextUp: Boolean = false,
)

private val stockDeliveriesSample = listOf(
    StockDeliverySample("SD-1093", StockDeliveryState.Received, "Supplier [name] · 6 items · arrived today 08:40", isNextUp = true),
    StockDeliverySample("SD-1091", StockDeliveryState.Checked, "Supplier [name] · 14 items · ready to place", isNextUp = true),
    StockDeliverySample("SD-1096", StockDeliveryState.Dispatched, "Supplier [name] · 22 items · expected 11 Nov"),
    StockDeliverySample("SD-1097", StockDeliveryState.Dispatched, "Supplier [name] · 8 items · expected 12 Nov"),
    StockDeliverySample("SD-1088", StockDeliveryState.Received, "Supplier [name] · 3 items · arrived yesterday"),
    StockDeliverySample("SD-1099", StockDeliveryState.Dispatched, "Supplier [name] · 40 items · expected 14 Nov"),
)

private val stockDeliveryFilters = listOf(null, StockDeliveryState.Dispatched, StockDeliveryState.Received, StockDeliveryState.Checked)

@Composable
fun StockDeliveriesScreen() {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val title = stringResource(R.string.goodsin_title)
    var query by remember { mutableStateOf("") }
    var selectedFilter by remember { mutableIntStateOf(0) }

    RequireAbility(isAllowed = can.viewGoodsIn, title = title, activeTab = AppTab.GoodsIn) {
        val filterOptions = stockDeliveryFilters.map { state ->
            if (state == null) {
                FilterOption(label = stringResource(R.string.goodsin_filter_open))
            } else {
                FilterOption(
                    label = stringResource(
                        R.string.goodsin_filter_with_count,
                        stringResource(state.label),
                        stockDeliveriesSample.count { it.state == state },
                    ),
                )
            }
        }
        val selectedState = stockDeliveryFilters[selectedFilter]
        val deliveries = stockDeliveriesSample.filter { delivery ->
            (selectedState == null || delivery.state == selectedState) && delivery.reference.contains(query.trim(), ignoreCase = true)
        }

        AppScreen(
            header = {
                AppHeader(
                    title = title,
                    below = {
                        GoodsInSectionTabs(active = GoodsInSection.StockDeliveries)
                        HeaderSearchRow(
                            placeholder = stringResource(R.string.goodsin_search_placeholder),
                            value = query,
                            onValueChange = { query = it },
                            onScan = { navigator.open(ScanCameraRoute) },
                        )
                    },
                )
            },
            activeTab = AppTab.GoodsIn,
            contentPadding = PaddingValues(start = 16.dp, top = 14.dp, end = 16.dp, bottom = 16.dp),
            spacing = 10.dp,
        ) {
            FilterChips(
                options = filterOptions,
                selectedIndex = selectedFilter,
                onSelect = { selectedFilter = it },
                modifier = Modifier.fakeData(cornerRadius = 8.dp),
            )
            GoodsCaptionRow(
                start = stringResource(R.string.goodsin_sorted_by_reference),
                end = stringResource(R.string.goodsin_flow_hint),
            )
            if (deliveries.isEmpty()) {
                Text(
                    text = stringResource(R.string.goodsin_no_results),
                    fontSize = 14.sp,
                    color = colors.textTertiary,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 24.dp),
                )
            } else {
                ListGroup(modifier = Modifier.fakeData()) {
                    deliveries.forEachIndexed { index, delivery ->
                        if (index > 0) {
                            RowDivider()
                        }
                        StockDeliveryRow(
                            delivery = delivery,
                            showAction = can.bookIn && delivery.isNextUp,
                            onOpen = { navigator.open(StockDeliveryDetailRoute(delivery.reference)) },
                        )
                    }
                }
            }
            Text(
                text = stringResource(R.string.goodsin_list_footer),
                fontSize = 13.sp,
                color = colors.textTertiary,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .align(Alignment.CenterHorizontally)
                    .padding(6.dp),
            )
        }
    }
}

@Composable
private fun StockDeliveryRow(delivery: StockDeliverySample, showAction: Boolean, onOpen: () -> Unit) {
    val colors = LocalAikuColors.current
    val actionLabel = delivery.state.actionLabel?.takeIf { showAction }

    Column(
        verticalArrangement = Arrangement.spacedBy(if (actionLabel != null) 10.dp else 6.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable(role = Role.Button, onClick = onOpen)
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(
                text = delivery.reference,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            StatusBadge(text = stringResource(delivery.state.label), tone = delivery.state.tone)
        }
        Text(text = delivery.summary, fontSize = 13.sp, color = colors.textTertiary)
        if (actionLabel != null) {
            AikuButton(
                text = stringResource(actionLabel),
                onClick = onOpen,
                style = delivery.state.actionStyle,
                height = 44.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Preview(widthDp = 390, heightDp = 1100)
@Composable
private fun StockDeliveriesScreenPreview() {
    AikuDroidTheme {
        StockDeliveriesScreen()
    }
}
