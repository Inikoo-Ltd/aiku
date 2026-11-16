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
import io.aiku.droid.data.api.SkoSort
import io.aiku.droid.ui.components.FilterSheet
import io.aiku.droid.ui.components.FilterSheetOption
import io.aiku.droid.ui.components.FilterSheetSection

private val sortOptions = SkoSort.entries

private fun SkoFilter.selection(families: List<OrgStockFamily>): List<Set<Int>> = listOfNotNull(
    families.takeIf { it.isNotEmpty() }?.let { options -> setOfNotNull(options.indexOfFirst { it.id == familyId }.takeIf { it >= 0 }) },
    setOf(sortOptions.indexOf(sort ?: SkoSort.Code)),
)

private fun SkoFilter.withSelection(selection: List<Set<Int>>, families: List<OrgStockFamily>): SkoFilter {
    val hasFamilies = families.isNotEmpty()
    val chosenSort = selection[if (hasFamilies) 1 else 0].firstOrNull()?.let { sortOptions[it] }
    return copy(
        familyId = if (hasFamilies) selection[0].firstOrNull()?.let { families[it].id } else familyId,
        sort = chosenSort?.takeIf { it != SkoSort.Code },
    )
}

@Composable
fun SkuFilterSheet(
    filter: SkoFilter,
    families: List<OrgStockFamily>?,
    totalFor: (SkoFilter) -> Int?,
    onDraftChange: (SkoFilter) -> Unit,
    onApply: (SkoFilter) -> Unit,
    onDismiss: () -> Unit,
) {
    var draft by remember { mutableStateOf(filter) }
    val familyOptions = families.orEmpty()
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
        sections = listOfNotNull(
            familyOptions.takeIf { it.isNotEmpty() }?.let { options ->
                FilterSheetSection(
                    label = stringResource(R.string.inventory_filter_family),
                    options = options.map { FilterSheetOption(label = stringResource(R.string.inventory_pair, it.code, it.name)) },
                    isAtMostOne = true,
                )
            },
            FilterSheetSection(
                label = stringResource(R.string.inventory_filter_sort),
                options = sortOptions.map { FilterSheetOption(label = sortLabels.getValue(it)) },
                isSingleChoice = true,
                isSort = true,
            ),
        ),
        selection = draft.selection(familyOptions),
        onSelectionChange = { selection -> draft = draft.withSelection(selection, familyOptions) },
        activeFilterCount = draft.activeFilterCount,
        showText = total?.let { pluralStringResource(R.plurals.inventory_filter_show, it, it) } ?: stringResource(R.string.inventory_filter_show_unknown),
        onShow = { onApply(draft) },
        onReset = { draft = draft.cleared() },
        onDismiss = onDismiss,
    )
}
