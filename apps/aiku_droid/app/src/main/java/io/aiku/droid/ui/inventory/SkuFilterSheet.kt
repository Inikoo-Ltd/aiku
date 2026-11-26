package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.OrgStockFamily
import io.aiku.droid.data.api.SkoFilterChoice
import io.aiku.droid.data.api.SkoFilterOptions
import io.aiku.droid.data.api.SkoSort
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection

private val sortOptions = SkoSort.entries

private enum class SkoFilterPart { Family, StockStatus, LocationType, Sort }

private fun parts(families: List<OrgStockFamily>, options: SkoFilterOptions): List<SkoFilterPart> = listOfNotNull(
    SkoFilterPart.StockStatus.takeIf { options.stockStatus.isNotEmpty() },
    SkoFilterPart.LocationType.takeIf { options.locationType.isNotEmpty() },
    SkoFilterPart.Family.takeIf { families.isNotEmpty() },
    SkoFilterPart.Sort,
)

private fun List<SkoFilterChoice>.indexOfKey(key: String?): Set<Int> = setOfNotNull(indexOfFirst { it.key == key }.takeIf { it >= 0 })

private fun SkoFilter.selection(families: List<OrgStockFamily>, options: SkoFilterOptions): List<Set<Int>> = parts(families, options).map { part ->
    when (part) {
        SkoFilterPart.Family -> setOfNotNull(families.indexOfFirst { it.id == familyId }.takeIf { it >= 0 })
        SkoFilterPart.StockStatus -> options.stockStatus.indexOfKey(stockStatus)
        SkoFilterPart.LocationType -> options.locationType.indexOfKey(locationType)
        SkoFilterPart.Sort -> setOf(sortOptions.indexOf(sort ?: SkoSort.Code))
    }
}

private fun SkoFilter.withSelection(selection: List<Set<Int>>, families: List<OrgStockFamily>, options: SkoFilterOptions): SkoFilter =
    parts(families, options).foldIndexed(this) { index, filter, part ->
        val chosen = selection.getOrNull(index)?.firstOrNull()
        when (part) {
            SkoFilterPart.Family -> filter.copy(familyId = chosen?.let { families[it].id })
            SkoFilterPart.StockStatus -> filter.copy(stockStatus = chosen?.let { options.stockStatus[it].key })
            SkoFilterPart.LocationType -> filter.copy(locationType = chosen?.let { options.locationType[it].key })
            SkoFilterPart.Sort -> filter.copy(sort = chosen?.let { sortOptions[it] }?.takeIf { it != SkoSort.Code })
        }
    }

@Composable
fun SkuFilterSheet(
    filter: SkoFilter,
    families: List<OrgStockFamily>?,
    options: SkoFilterOptions?,
    totalFor: (SkoFilter) -> Int?,
    onDraftChange: (SkoFilter) -> Unit,
    onApply: (SkoFilter) -> Unit,
    onDismiss: () -> Unit,
) {
    var draft by remember { mutableStateOf(filter) }
    val familyOptions = families.orEmpty()
    val filterOptions = options ?: SkoFilterOptions()
    val total = totalFor(draft)
    val sortLabels = mapOf(
        SkoSort.Code to stringResource(R.string.inventory_sort_reference),
        SkoSort.Name to stringResource(R.string.inventory_sort_name),
        SkoSort.StockLowFirst to stringResource(R.string.inventory_sort_stock),
        SkoSort.StockValue to stringResource(R.string.inventory_sort_stock_value),
        SkoSort.Family to stringResource(R.string.inventory_sort_family),
    )

    LaunchedEffect(draft) { onDraftChange(draft) }

    FilterSheet(
        title = stringResource(R.string.inventory_filter_title),
        sections = parts(familyOptions, filterOptions).map { part ->
            when (part) {
                SkoFilterPart.Family -> FilterSheetSection(
                    label = stringResource(R.string.inventory_filter_family),
                    options = familyOptions.map { FilterSheetOption(label = stringResource(R.string.inventory_pair, it.code, it.name)) },
                    isAtMostOne = true,
                    isSearchable = true,
                )
                SkoFilterPart.StockStatus -> FilterSheetSection(
                    label = stringResource(R.string.inventory_filter_stock_status),
                    options = filterOptions.stockStatus.map { FilterSheetOption(label = it.label) },
                    isAtMostOne = true,
                )
                SkoFilterPart.LocationType -> FilterSheetSection(
                    label = stringResource(R.string.inventory_filter_location_type),
                    options = filterOptions.locationType.map { FilterSheetOption(label = it.label) },
                    isAtMostOne = true,
                )
                SkoFilterPart.Sort -> FilterSheetSection(
                    label = stringResource(R.string.inventory_filter_sort),
                    options = sortOptions.map { FilterSheetOption(label = sortLabels.getValue(it)) },
                    isSingleChoice = true,
                    isSort = true,
                )
            }
        },
        selection = draft.selection(familyOptions, filterOptions),
        onSelectionChange = { selection -> draft = draft.withSelection(selection, familyOptions, filterOptions) },
        activeFilterCount = draft.activeFilterCount,
        showText = total?.let { pluralStringResource(R.plurals.inventory_filter_show, it, it) } ?: stringResource(R.string.inventory_filter_show_unknown),
        onShow = { onApply(draft) },
        onReset = { draft = draft.cleared() },
        onDismiss = onDismiss,
    )
}
