package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SkuFilterSheet(onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current

    FilterSheet(
        title = stringResource(R.string.inventory_filter_title),
        sections = listOf(
            FilterSheetSection(
                label = stringResource(R.string.inventory_filter_sko_state),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.inventory_state_active), dot = colors.tone(Tone.Success).content),
                    FilterSheetOption(label = stringResource(R.string.inventory_state_discontinuing), dot = colors.tone(Tone.Warn).content),
                    FilterSheetOption(label = stringResource(R.string.inventory_state_discontinued), dot = colors.dangerDot),
                    FilterSheetOption(label = stringResource(R.string.inventory_state_suspended), dot = colors.faint),
                    FilterSheetOption(label = stringResource(R.string.inventory_state_abnormality), dot = colors.danger),
                ),
            ),
            FilterSheetSection(
                label = stringResource(R.string.inventory_filter_stock_status),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.inventory_status_excess), dot = colors.tone(Tone.Primary).content),
                    FilterSheetOption(label = stringResource(R.string.inventory_status_ideal), dot = colors.tone(Tone.Success).content),
                    FilterSheetOption(label = stringResource(R.string.inventory_status_low), dot = colors.tone(Tone.Warn).content),
                    FilterSheetOption(label = stringResource(R.string.inventory_status_critical), dot = colors.tone(Tone.Orange).content),
                    FilterSheetOption(label = stringResource(R.string.inventory_status_out_of_stock), dot = colors.dangerDot),
                    FilterSheetOption(label = stringResource(R.string.inventory_status_error), dot = colors.dangerInk),
                ),
            ),
            FilterSheetSection(
                label = stringResource(R.string.inventory_filter_family),
                options = sampleFilterFamilies.map { FilterSheetOption(label = it) },
                isSample = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.inventory_filter_area),
                options = sampleFilterAreas.map { FilterSheetOption(label = it) },
                isSample = true,
                initiallySelected = setOf(0),
            ),
            FilterSheetSection(
                label = stringResource(R.string.inventory_filter_location_type),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.inventory_location_picking)),
                    FilterSheetOption(label = stringResource(R.string.inventory_location_storing)),
                ),
                initiallySelected = setOf(0),
            ),
            FilterSheetSection(
                label = stringResource(R.string.inventory_filter_sort),
                options = listOf(
                    FilterSheetOption(label = stringResource(R.string.inventory_sort_reference)),
                    FilterSheetOption(label = stringResource(R.string.inventory_sort_name)),
                    FilterSheetOption(label = stringResource(R.string.inventory_sort_stock)),
                    FilterSheetOption(label = stringResource(R.string.inventory_sort_stock_value)),
                    FilterSheetOption(label = stringResource(R.string.inventory_sort_family)),
                ),
                isSingleChoice = true,
                isSort = true,
                initiallySelected = setOf(0),
            ),
        ),
        sampleTotal = SAMPLE_CURRENT_SKOS,
        showLabel = R.plurals.inventory_filter_show,
        onDismiss = onDismiss,
    )
}
