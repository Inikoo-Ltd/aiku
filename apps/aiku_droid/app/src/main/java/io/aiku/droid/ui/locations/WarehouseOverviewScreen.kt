package io.aiku.droid.ui.locations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
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
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun WarehouseOverviewScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val warehouse = LocalWarehouse.current
    var removesEmptyAutomatically by remember { mutableStateOf(LocationsSamples.AUTO_REMOVE_ENABLED) }
    val toggleAutoRemove: (Boolean) -> Unit = { removesEmptyAutomatically = it }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ModuleHeader(WarehouseSection.WarehouseOverview, title = warehouse.name) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
                LocationsStatRow {
                    LocationsStatTile(
                        value = LocationsSamples.AREA_COUNT,
                        label = stringResource(R.string.locations_stat_areas),
                        modifier = Modifier.weight(1f),
                        onClick = { navigator.switchSection(AreasRoute) },
                    )
                    LocationsStatTile(
                        value = LocationsSamples.LOCATION_COUNT,
                        label = stringResource(R.string.locations_stat_locations),
                        modifier = Modifier.weight(1f),
                        onClick = { navigator.switchSection(LocationsRoute) },
                    )
                }
                LocationsStatRow {
                    LocationsStatTile(
                        value = LocationsSamples.EMPTY_LOCATION_COUNT,
                        label = stringResource(R.string.locations_stat_empty_locations),
                        modifier = Modifier.weight(1f),
                        valueColor = colors.warn,
                        onClick = { navigator.switchSection(LocationsRoute) },
                    )
                    LocationsStatTile(
                        value = LocationsSamples.UTILISATION,
                        label = stringResource(R.string.locations_stat_utilisation),
                        modifier = Modifier.weight(1f),
                    )
                }
            }
            LocationsHeading(text = stringResource(R.string.locations_heading_empty_locations))
            AikuCard(modifier = Modifier.fakeData(), padding = 16.dp, spacing = 12.dp) {
                TrailingCheckboxRow(
                    label = stringResource(R.string.locations_auto_remove),
                    checked = removesEmptyAutomatically,
                    onCheckedChange = toggleAutoRemove.takeIf { warehouse.can.moveStock },
                    caption = stringResource(
                        R.string.locations_auto_remove_changed,
                        LocationsSamples.AUTO_REMOVE_CHANGED_BY,
                        LocationsSamples.AUTO_REMOVE_CHANGED_ON,
                    ),
                )
                LocationsStatRow {
                    LocationsStatTile(
                        value = LocationsSamples.TO_REMOVE_NOW,
                        label = stringResource(R.string.locations_to_remove_now),
                        modifier = Modifier.weight(1f),
                    )
                    LocationsStatTile(
                        value = LocationsSamples.REMOVED_LAST_7_DAYS,
                        label = stringResource(R.string.locations_removed_last_week),
                        modifier = Modifier.weight(1f),
                    )
                }
            }
            LocationsFootnote(text = stringResource(R.string.locations_auto_remove_help))
        }
    }
}
