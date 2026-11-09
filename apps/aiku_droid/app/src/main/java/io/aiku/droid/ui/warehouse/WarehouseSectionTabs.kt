package io.aiku.droid.ui.warehouse

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.HeaderTab
import io.aiku.droid.ui.components.HeaderTabs
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute

internal enum class WarehouseSection { Overview, Areas, Locations }

@Composable
internal fun WarehouseSectionTabs(active: WarehouseSection) {
    val navigator = LocalAppNavigator.current

    HeaderTabs(
        tabs = listOf(
            HeaderTab(
                label = stringResource(R.string.warehouse_tab_warehouse),
                icon = R.drawable.ic_warehouse,
                isActive = active == WarehouseSection.Overview,
                onClick = { navigator.switchSection(WarehouseOverviewRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.warehouse_tab_areas),
                icon = R.drawable.ic_warehouse_area,
                isActive = active == WarehouseSection.Areas,
                onClick = { navigator.switchSection(AreasRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.warehouse_tab_locations),
                icon = R.drawable.ic_section_locations,
                isActive = active == WarehouseSection.Locations,
                onClick = { navigator.switchSection(LocationsRoute) },
            ),
        ),
    )
}
