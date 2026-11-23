package io.aiku.droid.ui.goodsin

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.DispatchedBucket
import io.aiku.droid.data.api.StockDeliverySort
import io.aiku.droid.data.api.StockDeliverySource
import io.aiku.droid.ui.components.DateRangeFields
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection

private val sources = StockDeliverySource.entries
private val buckets = DispatchedBucket.entries
private val sorts = StockDeliverySort.entries

private const val SOURCE_SECTION = 0
private const val DISPATCHED_SECTION = 1
private const val SORT_SECTION = 3

private fun StockDeliveryFilter.selection(): List<Set<Int>> = listOf(
    setOfNotNull(source?.let(sources::indexOf)),
    setOfNotNull(dispatched?.let(buckets::indexOf)),
    emptySet(),
    setOf(sorts.indexOf(sort ?: StockDeliverySort.Newest)),
)

private fun StockDeliveryFilter.withSelection(selection: List<Set<Int>>): StockDeliveryFilter = copy(
    source = selection[SOURCE_SECTION].firstOrNull()?.let(sources::get),
    dispatched = selection[DISPATCHED_SECTION].firstOrNull()?.let(buckets::get),
    sort = selection[SORT_SECTION].firstOrNull()?.let(sorts::get)?.takeIf { it != StockDeliverySort.Newest },
)

@Composable
internal fun sourceLabel(source: StockDeliverySource): String = stringResource(
    when (source) {
        StockDeliverySource.Suppliers -> R.string.goodsin_source_suppliers
        StockDeliverySource.Agents -> R.string.goodsin_source_agents
        StockDeliverySource.Partners -> R.string.goodsin_source_partners
    },
)

@Composable
internal fun dispatchedLabel(bucket: DispatchedBucket): String = stringResource(
    when (bucket) {
        DispatchedBucket.Today -> R.string.goodsin_filter_today
        DispatchedBucket.Week -> R.string.goodsin_filter_this_week
        DispatchedBucket.Older -> R.string.goodsin_filter_older
    },
)

@Composable
internal fun stockDeliverySortLabel(sort: StockDeliverySort): String = stringResource(
    when (sort) {
        StockDeliverySort.Newest -> R.string.goodsin_sort_newest
        StockDeliverySort.Oldest -> R.string.filter_sort_oldest
        StockDeliverySort.Reference -> R.string.goodsin_sort_reference
        StockDeliverySort.Supplier -> R.string.goodsin_sort_supplier
        StockDeliverySort.ExpectedDate -> R.string.goodsin_sort_expected_date
        StockDeliverySort.DispatchedAt -> R.string.goodsin_sort_dispatched_date
        StockDeliverySort.Items -> R.string.goodsin_sort_items
    },
)

@Composable
fun StockDeliveryFilterSheet(
    filter: StockDeliveryFilter,
    totalFor: (StockDeliveryFilter) -> Int?,
    onDraftChange: (StockDeliveryFilter) -> Unit,
    onApply: (StockDeliveryFilter) -> Unit,
    onDismiss: () -> Unit,
) {
    var draft by remember { mutableStateOf(filter) }
    val total = totalFor(draft)

    LaunchedEffect(draft) { onDraftChange(draft) }

    FilterSheet(
        title = stringResource(R.string.goodsin_filter_title),
        sections = listOf(
            FilterSheetSection(
                label = stringResource(R.string.goodsin_filter_source),
                options = sources.map { FilterSheetOption(label = sourceLabel(it)) },
                isAtMostOne = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsin_filter_dispatched),
                options = buckets.map { FilterSheetOption(label = dispatchedLabel(it)) },
                isAtMostOne = true,
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsout_filter_date),
                options = emptyList(),
                extraCount = if (draft.dates.isSet) 1 else 0,
                extra = { DateRangeFields(range = draft.dates, onChange = { draft = draft.copy(dates = it) }) },
            ),
            FilterSheetSection(
                label = stringResource(R.string.goodsin_filter_sort),
                options = sorts.map { FilterSheetOption(label = stockDeliverySortLabel(it)) },
                isSingleChoice = true,
                isSort = true,
            ),
        ),
        selection = draft.selection(),
        onSelectionChange = { selection -> draft = draft.withSelection(selection) },
        activeFilterCount = draft.activeFilterCount,
        showText = total?.let { pluralStringResource(R.plurals.goodsin_filter_show, it, it) } ?: stringResource(R.string.goodsin_filter_show_unknown),
        onShow = { onApply(draft) },
        onReset = { draft = draft.cleared() },
        onDismiss = onDismiss,
    )
}
