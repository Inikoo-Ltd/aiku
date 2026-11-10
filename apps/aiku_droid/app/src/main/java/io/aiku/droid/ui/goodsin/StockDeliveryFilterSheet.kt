package io.aiku.droid.ui.goodsin

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.theme.LocalAikuColors

private const val SAMPLE_STOCK_DELIVERY_TOTAL = 58

@Composable
fun StockDeliveryFilterSheet(onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val sections = listOf(
        FilterSheetSection(
            label = stringResource(R.string.goodsin_filter_state),
            options = listOf(
                FilterSheetOption(stringResource(R.string.goodsin_state_arriving), dot = colors.faint),
                FilterSheetOption(stringResource(R.string.sd_state_received), dot = colors.primary),
                FilterSheetOption(stringResource(R.string.sd_state_checked), dot = colors.tone(Tone.Primary).content),
                FilterSheetOption(stringResource(R.string.sd_state_booking_in), dot = colors.tone(Tone.Lime).content),
                FilterSheetOption(stringResource(R.string.sd_state_booked_in), dot = colors.tone(Tone.Success).content),
                FilterSheetOption(stringResource(R.string.goodsin_not_received), dot = colors.tone(Tone.Danger).content),
            ),
        ),
        FilterSheetSection(
            label = stringResource(R.string.goodsin_filter_source),
            options = listOf(
                FilterSheetOption(stringResource(R.string.goodsin_source_suppliers)),
                FilterSheetOption(stringResource(R.string.goodsin_source_agents)),
                FilterSheetOption(stringResource(R.string.goodsin_source_partners)),
                FilterSheetOption(stringResource(R.string.goodsin_source_production)),
            ),
        ),
        FilterSheetSection(
            label = stringResource(R.string.goodsin_filter_dispatched),
            options = listOf(
                FilterSheetOption(stringResource(R.string.goodsin_filter_today)),
                FilterSheetOption(stringResource(R.string.goodsin_filter_this_week)),
                FilterSheetOption(stringResource(R.string.goodsin_filter_older)),
            ),
        ),
        FilterSheetSection(
            label = stringResource(R.string.goodsin_filter_sort),
            options = listOf(
                FilterSheetOption(stringResource(R.string.goodsin_sort_dispatched_date)),
                FilterSheetOption(stringResource(R.string.goodsin_sort_reference)),
                FilterSheetOption(stringResource(R.string.goodsin_sort_supplier)),
                FilterSheetOption(stringResource(R.string.goodsin_sort_items)),
            ),
            isSingleChoice = true,
            isSort = true,
            initiallySelected = setOf(0),
        ),
    )

    FilterSheet(
        title = stringResource(R.string.goodsin_filter_title),
        sections = sections,
        sampleTotal = SAMPLE_STOCK_DELIVERY_TOTAL,
        showLabel = R.plurals.goodsin_filter_show,
        onDismiss = onDismiss,
    )
}
