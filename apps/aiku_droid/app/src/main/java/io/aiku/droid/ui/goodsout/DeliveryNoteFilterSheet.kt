package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteSort
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.LocalAikuColors

private const val STATE_SECTION = 0
private const val PICKER_SECTION = 1
private const val CHANNEL_SECTION = 2
private const val DATE_SECTION = 3
private const val SORT_SECTION = 4

private val pickerOptions = PickerFilter.entries
private val dateOptions = DateFilter.entries
private val sortOptions = DeliveryNoteSort.entries

@StringRes
internal fun pickerLabel(picker: PickerFilter): Int = when (picker) {
    PickerFilter.Me -> R.string.goodsout_filter_picker_me
    PickerFilter.Nobody -> R.string.goodsout_filter_picker_none
    PickerFilter.Others -> R.string.goodsout_filter_picker_others
}

@StringRes
internal fun dateLabel(date: DateFilter): Int = when (date) {
    DateFilter.Today -> R.string.goodsout_filter_date_today
    DateFilter.Yesterday -> R.string.goodsout_filter_date_yesterday
    DateFilter.LastSevenDays -> R.string.goodsout_filter_date_last_7_days
}

@StringRes
internal fun sortLabel(sort: DeliveryNoteSort): Int = when (sort) {
    DeliveryNoteSort.DateOldest -> R.string.goodsout_filter_sort_oldest
    DeliveryNoteSort.DateNewest -> R.string.goodsout_filter_sort_newest
    DeliveryNoteSort.MostItems -> R.string.goodsout_filter_sort_items
    DeliveryNoteSort.Heaviest -> R.string.goodsout_filter_sort_weight
    DeliveryNoteSort.Customer -> R.string.goodsout_filter_sort_customer
}

private fun stageDot(stage: DeliveryNoteStage, colors: AikuColors): Color = when (stage) {
    DeliveryNoteStage.ToDo -> colors.faint
    DeliveryNoteStage.Waiting -> colors.tone(Tone.Warn).content
    DeliveryNoteStage.Packed -> colors.tone(Tone.Lime).content
    DeliveryNoteStage.Finalised, DeliveryNoteStage.Dispatched -> colors.tone(Tone.Success).content
    DeliveryNoteStage.Picking, DeliveryNoteStage.Picked, DeliveryNoteStage.Packing -> colors.tone(Tone.Primary).content
}

private fun DeliveryNoteFilter.selection(): List<Set<Int>> = listOf(
    setOfNotNull(stageBuckets.indexOfFirst { it.second == bucket }.takeIf { it >= 0 }),
    setOfNotNull(picker?.let { pickerOptions.indexOf(it) }),
    setOf(filterChannels.indexOf(channel).coerceAtLeast(0)),
    setOfNotNull(date?.let { dateOptions.indexOf(it) }),
    setOf(sortOptions.indexOf(shownSort)),
)

private fun DeliveryNoteFilter.withSelection(selection: List<Set<Int>>): DeliveryNoteFilter {
    val selectedSort = selection[SORT_SECTION].firstOrNull()?.let { sortOptions[it] }
    val updated = copy(
        bucket = selection[STATE_SECTION].firstOrNull()?.let { stageBuckets[it].second } ?: bucket,
        picker = selection[PICKER_SECTION].firstOrNull()?.let { pickerOptions[it] },
        channel = selection[CHANNEL_SECTION].firstOrNull()?.let { filterChannels[it] } ?: DispatchChannel.All,
        date = selection[DATE_SECTION].firstOrNull()?.let { dateOptions[it] },
    )
    return when {
        selectedSort != null && selectedSort != shownSort -> updated.withSort(selectedSort)
        else -> updated.copy(sort = sort?.takeIf { it != defaultSortOf(updated.bucket) })
    }
}

@Composable
fun DeliveryNoteFilterSheet(
    filter: DeliveryNoteFilter,
    totalFor: (DeliveryNoteFilter) -> Int?,
    onDraftChange: (DeliveryNoteFilter) -> Unit,
    onApply: (DeliveryNoteFilter) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var draft by remember { mutableStateOf(filter) }
    val total = totalFor(draft)

    LaunchedEffect(draft) { onDraftChange(draft) }

    FilterSheet(
        title = stringResource(R.string.goodsout_filter_title),
        sections = listOf(
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_state),
                options = stageBuckets.map { (stage, _) -> FilterSheetOption(stringResource(stage.label), dot = stageDot(stage, colors)) },
                isSingleChoice = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_picker),
                options = pickerOptions.map { FilterSheetOption(stringResource(pickerLabel(it))) },
                isAtMostOne = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_channel),
                options = filterChannels.map { FilterSheetOption(stringResource(channelLabel(it))) },
                isSingleChoice = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_date),
                options = dateOptions.map { FilterSheetOption(stringResource(dateLabel(it))) },
                isAtMostOne = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_sort),
                options = sortOptions.map { FilterSheetOption(stringResource(sortLabel(it))) },
                isSingleChoice = true,
                isSort = true,
            ),
        ),
        selection = draft.selection(),
        onSelectionChange = { selection -> draft = draft.withSelection(selection) },
        activeFilterCount = draft.activeFilterCount,
        showText = total?.let { pluralStringResource(R.plurals.goodsout_show_delivery_notes, it, it) }
            ?: stringResource(R.string.goodsout_show_delivery_notes_unknown),
        onShow = { onApply(draft) },
        onReset = { draft = draft.cleared() },
        onDismiss = onDismiss,
        header = {
            SearchField(
                value = draft.search,
                onValueChange = { draft = draft.copy(search = it) },
                placeholder = stringResource(R.string.goodsout_filter_search),
                modifier = Modifier.fillMaxWidth(),
            )
        },
    )
}
