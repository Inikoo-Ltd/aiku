package io.aiku.droid.ui.locations

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.LocationOccupancy
import io.aiku.droid.data.api.LocationScope
import io.aiku.droid.data.api.LocationSort
import io.aiku.droid.data.api.LocationStatusFilter
import io.aiku.droid.data.api.WarehouseArea
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection
import io.aiku.droid.ui.theme.LocalAikuColors

private val statusOptions = LocationStatusFilter.entries
private val scopeOptions = LocationScope.entries
private val occupancyOptions = LocationOccupancy.entries
private val sortOptions = LocationSort.entries

private fun LocationFilter.selection(areas: List<WarehouseArea>): List<Set<Int>> = listOfNotNull(
    areas.takeIf { it.isNotEmpty() }?.let { options -> options.indices.filter { options[it].id in areaIds }.toSet() },
    statuses.map { statusOptions.indexOf(it) }.toSet(),
    scopes.map { scopeOptions.indexOf(it) }.toSet(),
    occupancies.map { occupancyOptions.indexOf(it) }.toSet(),
    setOf(sortOptions.indexOf(sort ?: LocationSort.Code)),
)

private fun LocationFilter.withSelection(selection: List<Set<Int>>, areas: List<WarehouseArea>): LocationFilter {
    val offset = if (areas.isNotEmpty()) 1 else 0
    val chosenSort = selection[offset + 3].firstOrNull()?.let { sortOptions[it] }
    return copy(
        areaIds = if (offset == 1) selection[0].map { areas[it].id }.toSet() else areaIds,
        statuses = selection[offset].map { statusOptions[it] }.toSet(),
        scopes = selection[offset + 1].map { scopeOptions[it] }.toSet(),
        occupancies = selection[offset + 2].map { occupancyOptions[it] }.toSet(),
        sort = chosenSort?.takeIf { it != LocationSort.Code },
    )
}

@Composable
fun LocationFilterSheet(
    filter: LocationFilter,
    areas: List<WarehouseArea>?,
    totalFor: (LocationFilter) -> Int?,
    onDraftChange: (LocationFilter) -> Unit,
    onApply: (LocationFilter) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var draft by remember { mutableStateOf(filter) }
    val areaOptions = areas.orEmpty()
    val total = totalFor(draft)
    val statusLabels = mapOf(
        LocationStatusFilter.Operational to FilterSheetOption(label = stringResource(R.string.locations_status_operational), dot = colors.success),
        LocationStatusFilter.Broken to FilterSheetOption(label = stringResource(R.string.locations_status_broken), dot = colors.dangerDot),
    )
    val scopeLabels = mapOf(
        LocationScope.Stocks to stringResource(R.string.locations_kind_stocks),
        LocationScope.Fulfilment to stringResource(R.string.locations_kind_fulfilment),
        LocationScope.Dropshipping to stringResource(R.string.locations_scope_dropshipping),
    )
    val occupancyLabels = mapOf(
        LocationOccupancy.Empty to stringResource(R.string.locations_stat_empty),
        LocationOccupancy.PartiallyEmpty to stringResource(R.string.locations_occupancy_partially_empty),
        LocationOccupancy.GoodsOut to stringResource(R.string.locations_occupancy_gathering),
        LocationOccupancy.KeepEmptyOrgStocks to stringResource(R.string.locations_occupancy_never_remove),
    )
    val sortLabels = mapOf(
        LocationSort.Code to stringResource(R.string.locations_sort_code),
        LocationSort.StockValue to stringResource(R.string.locations_sort_stock_value),
        LocationSort.Weight to stringResource(R.string.locations_sort_weight),
        LocationSort.Volume to stringResource(R.string.locations_sort_cbm),
    )

    LaunchedEffect(draft) { onDraftChange(draft) }

    FilterSheet(
        title = stringResource(R.string.locations_filter_title),
        sections = listOfNotNull(
            areaOptions.takeIf { it.isNotEmpty() }?.let { options ->
                FilterSheetSection(label = stringResource(R.string.locations_filter_area), options = options.map { FilterSheetOption(label = it.label) })
            },
            FilterSheetSection(label = stringResource(R.string.locations_filter_status), options = statusOptions.map { statusLabels.getValue(it) }),
            FilterSheetSection(label = stringResource(R.string.locations_filter_scope), options = scopeOptions.map { FilterSheetOption(label = scopeLabels.getValue(it)) }),
            FilterSheetSection(
                label = stringResource(R.string.locations_filter_occupancy),
                options = occupancyOptions.map { FilterSheetOption(label = occupancyLabels.getValue(it)) },
            ),
            FilterSheetSection(
                label = stringResource(R.string.locations_filter_sort),
                options = sortOptions.map { FilterSheetOption(label = sortLabels.getValue(it)) },
                isSingleChoice = true,
                isSort = true,
            ),
        ),
        selection = draft.selection(areaOptions),
        onSelectionChange = { selection -> draft = draft.withSelection(selection, areaOptions) },
        activeFilterCount = draft.activeFilterCount,
        showText = total?.let { pluralStringResource(R.plurals.locations_filter_show, it, it) } ?: stringResource(R.string.locations_filter_show_unknown),
        onShow = { onApply(draft) },
        onReset = { draft = draft.cleared() },
        onDismiss = onDismiss,
    )
}
