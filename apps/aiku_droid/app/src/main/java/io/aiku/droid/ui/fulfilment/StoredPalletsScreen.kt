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
import io.aiku.droid.ui.navigation.PalletIncidentsRoute
import io.aiku.droid.ui.navigation.PalletRoute

@Composable
fun StoredPalletsScreen() {
    RequireFulfilment(section = R.string.section_stored_pallets) {
        StoredPalletsContent()
    }
}

@Composable
private fun StoredPalletsContent() {
    val navigator = LocalAppNavigator.current
    var selectedFilter by remember { mutableIntStateOf(StoredPalletFilter.Storing.ordinal) }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.StoredPallets) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        FilterChips(
            options = StoredPalletFilter.entries.map { filter ->
                FilterOption(label = filterChipLabel(label = stringResource(filter.label), count = storedPalletCountsSample[filter]))
            },
            selectedIndex = selectedFilter,
            onSelect = { selectedFilter = it },
            modifier = Modifier.fakeData(cornerRadius = 17.dp),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            storedPalletsSample.forEachIndexed { index, pallet ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = pallet.reference,
                    subtitle = pallet.summary,
                    chip = stringResource(pallet.state.label),
                    chipTone = pallet.state.tone,
                    showPhoto = false,
                    onClick = { navigator.open(if (pallet.state.hasIncident) PalletIncidentsRoute else PalletRoute(pallet.reference)) },
                )
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_pallets_footnote))
    }
}
