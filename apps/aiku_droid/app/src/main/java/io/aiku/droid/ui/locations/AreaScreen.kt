package io.aiku.droid.ui.locations

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun AreaScreen(code: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val area = LocationsSamples.area(code)

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        AppScreen(
            header = { AppHeader(isSample = true, title = area.name, kicker = stringResource(R.string.locations_area_kicker, area.code)) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            LocationsStatRow(modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
                LocationsStatTile(
                    value = area.locationCount.toString(),
                    label = stringResource(R.string.locations_stat_locations),
                    modifier = Modifier.weight(1f),
                )
                LocationsStatTile(
                    value = area.emptyCount.toString(),
                    label = stringResource(R.string.locations_stat_empty),
                    modifier = Modifier.weight(1f),
                    valueColor = colors.warn,
                )
                LocationsStatTile(
                    value = area.pickingOrder.toString(),
                    label = stringResource(R.string.locations_stat_picking_order),
                    modifier = Modifier.weight(1f),
                )
            }
            LocationsHeading(text = stringResource(R.string.locations_heading_locations))
            LocationsList(
                locations = LocationsSamples.locations,
                onOpen = { location -> navigator.open(LocationRoute(location.code)) },
                modifier = Modifier.fakeData(),
            )
        }
    }
}
