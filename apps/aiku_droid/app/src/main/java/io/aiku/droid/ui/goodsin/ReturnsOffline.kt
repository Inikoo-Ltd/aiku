package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.ReturnQuery
import io.aiku.droid.data.api.ReturnSort
import io.aiku.droid.data.api.ReturnState
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.ui.components.matchesSearch
import io.aiku.droid.ui.components.nameKey
import io.aiku.droid.ui.components.rowInstant
import io.aiku.droid.ui.components.sortedNullsLast
import io.aiku.droid.ui.components.withinDays
import java.time.ZoneId

private val toProcessReturnStates = setOf(ReturnState.RECEIVED, ReturnState.RETURNING)

internal fun offlineReturns(rows: List<CustomerReturnRow>, query: ReturnQuery, zone: ZoneId): List<CustomerReturnRow> = rows
    .filter { row ->
        row.isIn(query.state) &&
            matchesSearch(query.search, row.reference) &&
            withinDays(row.createdAt, query.from, query.to, zone)
    }
    .sortedBy(query.sort)

private fun CustomerReturnRow.isIn(filter: ReturnStateFilter): Boolean = when (filter) {
    ReturnStateFilter.All -> true
    ReturnStateFilter.ToProcess -> state in toProcessReturnStates
    else -> state == filter.key
}

private fun List<CustomerReturnRow>.sortedBy(sort: ReturnSort?): List<CustomerReturnRow> = when (sort) {
    null -> sortedNullsLast(descending = true, id = { it.id }) { rowInstant(it.createdAt) }
    ReturnSort.OldestReceived -> sortedNullsLast(descending = false, id = { it.id }) { rowInstant(it.createdAt) }
    ReturnSort.Reference -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.reference) }
    ReturnSort.Customer -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.customer?.name) }
}
