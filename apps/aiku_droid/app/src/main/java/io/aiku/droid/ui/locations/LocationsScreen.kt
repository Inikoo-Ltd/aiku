package io.aiku.droid.ui.locations

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
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
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.MainTab

private enum class LocationTab { All, Empty, PartiallyEmpty }

@Composable
fun LocationsScreen() {
    val navigator = LocalAppNavigator.current
    var selectedTab by remember { mutableStateOf(LocationTab.All) }
    var query by remember { mutableStateOf("") }
    var showFilter by remember { mutableStateOf(false) }
    val search = query.trim()
    val locations = LocationsSamples.locations.filter { location ->
        val isInTab = when (selectedTab) {
            LocationTab.All -> true
            LocationTab.Empty -> location.isEmpty
            LocationTab.PartiallyEmpty -> location.isPartiallyEmpty
        }
        isInTab && location.code.startsWith(search, ignoreCase = true)
    }
    val tabs = listOf(
        FilterOption(label = stringResource(R.string.locations_tab_all, LocationsSamples.LOCATION_COUNT)),
        FilterOption(label = stringResource(R.string.locations_tab_empty, LocationsSamples.EMPTY_LOCATION_COUNT)),
        FilterOption(label = stringResource(R.string.locations_tab_partially_empty, LocationsSamples.PARTIALLY_EMPTY_LOCATION_COUNT)),
    )

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                ModuleHeader(
                    WarehouseSection.Locations,
                    actions = {
                        HeaderIconButton(
                            icon = R.drawable.ic_ui_search,
                            description = stringResource(R.string.filter_open),
                            onClick = { showFilter = true },
                            filled = true,
                        )
                    },
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            FilterChips(
                options = tabs,
                selectedIndex = selectedTab.ordinal,
                onSelect = { index -> selectedTab = LocationTab.entries[index] },
                modifier = Modifier.fakeData(cornerRadius = 17.dp),
            )
            SearchScanField(
                placeholder = stringResource(R.string.locations_search),
                value = query,
                onValueChange = { query = it },
            )
            if (locations.isEmpty()) {
                LocationsFootnote(text = stringResource(R.string.locations_no_match, search))
            } else {
                LocationsList(
                    locations = locations,
                    onOpen = { location -> navigator.open(LocationRoute(location.code)) },
                    modifier = Modifier.fakeData(),
                )
            }
            LocationsFootnote(text = stringResource(R.string.locations_scan_hint))
        }
        if (showFilter) {
            LocationFilterSheet(onDismiss = { showFilter = false })
        }
    }
}
