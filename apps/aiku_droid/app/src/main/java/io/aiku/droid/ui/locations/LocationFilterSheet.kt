package io.aiku.droid.ui.locations

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationFilterSheet(onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current

    FilterSheet(
        title = stringResource(R.string.locations_filter_title),
        sections = listOf(
            FilterSheetSection(
                label = stringResource(R.string.locations_filter_area),
                options = LocationsSamples.filterAreas.map { FilterSheetOption(label = it) },
                isSample = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.locations_filter_status),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.locations_status_operational), dot = colors.success),
                    FilterSheetOption(label = stringResource(R.string.locations_status_broken), dot = colors.dangerDot),
                ),
                initiallySelected = setOf(0),
            ),
            FilterSheetSection(
                label = stringResource(R.string.locations_filter_scope),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.locations_kind_stocks)),
                    FilterSheetOption(label = stringResource(R.string.locations_kind_fulfilment)),
                    FilterSheetOption(label = stringResource(R.string.locations_scope_dropshipping)),
                ),
            ),
            FilterSheetSection(
                label = stringResource(R.string.locations_filter_occupancy),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.locations_stat_empty)),
                    FilterSheetOption(label = stringResource(R.string.locations_occupancy_partially_empty)),
                    FilterSheetOption(label = stringResource(R.string.locations_occupancy_gathering)),
                    FilterSheetOption(label = stringResource(R.string.locations_occupancy_never_remove)),
                ),
            ),
            FilterSheetSection(
                label = stringResource(R.string.locations_filter_sort),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.locations_sort_code)),
                    FilterSheetOption(label = stringResource(R.string.locations_sort_stock_value)),
                    FilterSheetOption(label = stringResource(R.string.locations_sort_weight)),
                    FilterSheetOption(label = stringResource(R.string.locations_sort_cbm)),
                ),
                isSingleChoice = true,
                isSort = true,
                initiallySelected = setOf(0),
            ),
        ),
        sampleTotal = LocationsSamples.FILTER_TOTAL,
        showLabel = R.plurals.locations_filter_show,
        onDismiss = onDismiss,
    )
}
