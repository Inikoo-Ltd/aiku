package io.aiku.droid.ui.locations

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AreaRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun AreasScreen() {
    val navigator = LocalAppNavigator.current
    var query by remember { mutableStateOf("") }
    val search = query.trim()
    val areas = LocationsSamples.areas.filter { area ->
        area.code.startsWith(search, ignoreCase = true) || area.name.contains(search, ignoreCase = true)
    }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ModuleHeader(WarehouseSection.Areas) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            SearchScanField(
                placeholder = stringResource(R.string.locations_areas_search),
                value = query,
                onValueChange = { query = it },
            )
            if (areas.isEmpty()) {
                LocationsFootnote(text = stringResource(R.string.locations_areas_no_match, search))
            } else {
                ListGroup(modifier = Modifier.fakeData()) {
                    areas.forEachIndexed { index, area ->
                        if (index > 0) {
                            RowDivider()
                        }
                        AreaRow(area = area, onClick = { navigator.open(AreaRoute(area.code)) })
                    }
                }
            }
        }
    }
}

@Composable
private fun AreaRow(area: SampleArea, onClick: () -> Unit) {
    LocationsTileRow(
        icon = R.drawable.ic_ui_area,
        title = area.name,
        subtitle = pluralStringResource(R.plurals.locations_area_subtitle, area.emptyCount, area.pickingOrder, pluralStringResource(R.plurals.locations_location_count, area.locationCount, area.locationCount), area.emptyCount, area.stockValue),
        onClick = onClick,
    )
}
