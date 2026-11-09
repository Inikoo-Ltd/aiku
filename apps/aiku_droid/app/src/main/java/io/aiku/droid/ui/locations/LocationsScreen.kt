package io.aiku.droid.ui.locations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderSearchRow
import io.aiku.droid.ui.components.HeaderTab
import io.aiku.droid.ui.components.HeaderTabs
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.inventory.ListFooter
import io.aiku.droid.ui.inventory.ListMetaRow
import io.aiku.droid.ui.inventory.MetaText
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationDetailRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationsScreen() {
    val can = LocalWarehouse.current.can
    val title = stringResource(R.string.locations_title)

    RequireAbility(isAllowed = can.viewLocations, title = title) {
        LocationsContent(title = title)
    }
}

@Composable
private fun LocationsContent(title: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    var query by remember { mutableStateOf("") }
    var selectedFilter by remember { mutableIntStateOf(0) }

    AppScreen(
        header = {
            AppHeader(
                title = title,
                kicker = warehouseKicker(),
                below = {
                    LocationsSectionTabs()
                    HeaderSearchRow(
                        placeholder = stringResource(R.string.locations_search),
                        value = query,
                        onValueChange = { query = it },
                    )
                },
            )
        },
        activeTab = null,
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 16.dp),
        spacing = 10.dp,
    ) {
        FilterChips(
            options = listOf(FilterOption(label = stringResource(R.string.locations_filter_all))) +
                LocationsAreaSamples.map { area -> FilterOption(label = area) } +
                FilterOption(label = stringResource(R.string.locations_filter_broken), dotColor = colors.dangerDot),
            selectedIndex = selectedFilter,
            onSelect = { selectedFilter = it },
            modifier = Modifier.fakeData(8.dp),
        )
        ListMetaRow(start = stringResource(R.string.locations_sorted)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                UsageIcon(usage = LocationsUsage.Stocks, size = 14.dp)
                MetaText(text = stringResource(R.string.locations_legend_stocks))
            }
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                UsageIcon(usage = LocationsUsage.Fulfilment, size = 14.dp)
                MetaText(text = stringResource(R.string.locations_legend_fulfilment))
            }
        }
        ListGroup(modifier = Modifier.fakeData()) {
            LocationsListSamples.forEachIndexed { index, location ->
                if (index > 0) {
                    RowDivider()
                }
                ListRow(
                    title = location.code,
                    subtitle = location.summary,
                    titleSize = 16,
                    badges = {
                        location.usage?.let { UsageIcon(usage = it, size = 16.dp, describe = true) }
                        location.flag?.let { StatusBadge(text = stringResource(it.label), tone = it.tone) }
                    },
                    trailingValue = location.stockValue,
                    trailingCaption = stringResource(R.string.locations_stock_value_caption),
                    onClick = { navigator.open(LocationDetailRoute(location.code)) },
                )
            }
        }
        ListFooter(
            text = stringResource(R.string.locations_showing, "6", "1,284"),
            modifier = Modifier.align(Alignment.CenterHorizontally).fakeData(8.dp),
        )
    }
}

@Composable
private fun LocationsSectionTabs() {
    val navigator = LocalAppNavigator.current

    HeaderTabs(
        tabs = listOf(
            HeaderTab(
                label = stringResource(R.string.locations_section_warehouse),
                icon = R.drawable.ic_warehouse,
                isActive = false,
                onClick = { navigator.switchSection(WarehouseOverviewRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.locations_section_areas),
                icon = R.drawable.ic_locations_areas,
                isActive = false,
                onClick = { navigator.switchSection(AreasRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.locations_section_locations),
                icon = R.drawable.ic_section_locations,
                isActive = true,
                onClick = {},
            ),
        ),
    )
}

@Composable
private fun UsageIcon(usage: LocationsUsage, size: Dp, describe: Boolean = false) {
    val colors = LocalAikuColors.current
    val tint = when (usage) {
        LocationsUsage.Stocks -> colors.link
        LocationsUsage.Fulfilment -> colors.warn
    }

    Icon(
        painter = painterResource(usage.icon),
        contentDescription = if (describe) stringResource(usage.description) else null,
        tint = tint,
        modifier = Modifier.size(size),
    )
}

@Preview(showSystemUi = true)
@Composable
private fun LocationsScreenPreview() {
    AikuDroidTheme {
        LocationsScreen()
    }
}
