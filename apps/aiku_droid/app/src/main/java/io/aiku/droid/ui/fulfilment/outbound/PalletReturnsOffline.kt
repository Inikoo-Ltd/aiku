package io.aiku.droid.ui.fulfilment.outbound

import io.aiku.droid.data.api.PalletReturnQuery
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PalletReturnSort
import io.aiku.droid.data.api.PalletReturnState
import io.aiku.droid.data.api.PalletReturnStateFilter
import io.aiku.droid.ui.components.matchesSearch
import io.aiku.droid.ui.components.nameKey
import io.aiku.droid.ui.components.rowInstant
import io.aiku.droid.ui.components.sortedNullsLast

internal val onlineOnlyPalletReturnSorts: Set<PalletReturnSort> = setOf(PalletReturnSort.Dispatched)

private val activePalletReturnStates = setOf(PalletReturnState.CONFIRMED, PalletReturnState.PICKING, PalletReturnState.PICKED)

internal fun offlinePalletReturns(rows: List<PalletReturnRow>, query: PalletReturnQuery): List<PalletReturnRow> = rows
    .filter { row ->
        row.isIn(query.state) &&
            (query.type == null || row.typeGroup == query.type.key) &&
            matchesSearch(query.search, row.reference, row.customerReference, row.customer?.name)
    }
    .sortedBy(query.sort?.takeUnless { it in onlineOnlyPalletReturnSorts })

private fun PalletReturnRow.isIn(filter: PalletReturnStateFilter): Boolean = when (filter) {
    PalletReturnStateFilter.Active -> state in activePalletReturnStates
    else -> state == filter.key
}

private fun List<PalletReturnRow>.sortedBy(sort: PalletReturnSort?): List<PalletReturnRow> = when (sort) {
    null, PalletReturnSort.Dispatched -> sortedNullsLast(descending = true, id = { it.id }) { rowInstant(it.date) }
    PalletReturnSort.Oldest -> sortedNullsLast(descending = false, id = { it.id }) { rowInstant(it.date) }
    PalletReturnSort.Reference -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.reference) }
    PalletReturnSort.Customer -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.customer?.name) }
}
