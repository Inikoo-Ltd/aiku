package io.aiku.droid.ui.goodsout

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.theme.LocalAikuColors

private const val SAMPLE_DELIVERY_NOTE_TOTAL = 236
private val sampleShippers = listOf("DPD (GB)", "APC (GB)")

@Composable
fun DeliveryNoteFilterSheet(onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current

    FilterSheet(
        title = stringResource(R.string.goodsout_filter_title),
        sections = listOf(
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_state),
                options = listOf(
                    FilterSheetOption(stringResource(R.string.dn_state_to_do), dot = colors.faint),
                    FilterSheetOption(stringResource(R.string.dn_state_picking), dot = colors.tone(Tone.Primary).content),
                    FilterSheetOption(stringResource(R.string.dn_state_waiting), dot = colors.tone(Tone.Warn).content),
                    FilterSheetOption(stringResource(R.string.dn_state_picked), dot = colors.tone(Tone.Primary).content),
                    FilterSheetOption(stringResource(R.string.dn_state_packing), dot = colors.tone(Tone.Primary).content),
                    FilterSheetOption(stringResource(R.string.dn_state_packed), dot = colors.tone(Tone.Lime).content),
                    FilterSheetOption(stringResource(R.string.dn_state_finalised), dot = colors.tone(Tone.Success).content),
                    FilterSheetOption(stringResource(R.string.dn_state_dispatched), dot = colors.tone(Tone.Success).content),
                ),
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_picker),
                options = listOf(
                    FilterSheetOption(stringResource(R.string.goodsout_filter_picker_me)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_picker_none)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_picker_others)),
                ),
                initiallySelected = setOf(0),
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_channel),
                options = listOf(
                    FilterSheetOption(stringResource(R.string.goodsout_channel_wholesale)),
                    FilterSheetOption(stringResource(R.string.goodsout_channel_dropshipping)),
                    FilterSheetOption(stringResource(R.string.goodsout_channel_b2c)),
                    FilterSheetOption(stringResource(R.string.goodsout_channel_marketplaces)),
                    FilterSheetOption(stringResource(R.string.goodsout_channel_partners)),
                ),
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_shipper),
                options = sampleShippers.map { FilterSheetOption(it) } + FilterSheetOption(stringResource(R.string.goodsout_filter_shipper_collection)),
                isSample = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_date),
                options = listOf(
                    FilterSheetOption(stringResource(R.string.goodsout_filter_date_today)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_date_yesterday)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_date_last_7_days)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_date_overdue)),
                ),
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_sort),
                options = listOf(
                    FilterSheetOption(stringResource(R.string.goodsout_filter_sort_oldest)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_sort_newest)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_sort_items)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_sort_weight)),
                    FilterSheetOption(stringResource(R.string.goodsout_filter_sort_customer)),
                ),
                isSingleChoice = true,
                isSort = true,
                initiallySelected = setOf(0),
            ),
        ),
        sampleTotal = SAMPLE_DELIVERY_NOTE_TOTAL,
        showLabel = R.plurals.goodsout_show_delivery_notes,
        onDismiss = onDismiss,
    )
}
