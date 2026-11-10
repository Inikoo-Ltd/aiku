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
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute

private val returnFilters: List<FulfilmentReturnType?> = listOf<FulfilmentReturnType?>(null) + FulfilmentReturnType.entries

@Composable
fun FulfilmentReturnsScreen() {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        FulfilmentReturnsContent()
    }
}

@Composable
private fun FulfilmentReturnsContent() {
    val navigator = LocalAppNavigator.current
    var selectedFilter by remember { mutableIntStateOf(0) }
    val selectedType = returnFilters[selectedFilter]
    val visibleReturns = fulfilmentReturnsSample.filter { selectedType == null || it.type == selectedType }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.FulfilmentReturns) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        FilterChips(
            options = returnFilters.map { type ->
                FilterOption(
                    label = filterChipLabel(
                        label = stringResource(type?.label ?: R.string.fulfilment_filter_all),
                        count = fulfilmentReturnsSample.count { type == null || it.type == type },
                    ),
                )
            },
            selectedIndex = selectedFilter,
            onSelect = { selectedFilter = it },
            modifier = Modifier.fakeData(cornerRadius = 17.dp),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            visibleReturns.forEachIndexed { index, fulfilmentReturn ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = fulfilmentReturn.reference,
                    subtitle = fulfilmentReturn.summary,
                    chip = stringResource(fulfilmentReturn.state.label),
                    chipTone = fulfilmentReturn.state.tone,
                    showPhoto = false,
                    onClick = { navigator.open(fulfilmentReturn.route()) },
                )
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_returns_footnote))
    }
}

private fun FulfilmentReturnSample.route(): Any = when (state) {
    FulfilmentReturnState.Picked -> ReturnPickedRoute(reference)
    FulfilmentReturnState.Dispatched -> ReturnDispatchedRoute(reference)
    FulfilmentReturnState.Confirmed, FulfilmentReturnState.Picking ->
        if (type == FulfilmentReturnType.Pallets) ReturnPickPalletsRoute(reference) else ReturnPickItemsRoute(reference)
}
