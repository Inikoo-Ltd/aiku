package io.aiku.droid.ui.goodsin

import io.aiku.droid.data.api.DispatchedBucket
import io.aiku.droid.data.api.StockDeliveryQuery
import io.aiku.droid.data.api.StockDeliveryRow
import io.aiku.droid.data.api.StockDeliverySort
import io.aiku.droid.data.api.StockDeliveryState
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.ui.components.matchesSearch
import io.aiku.droid.ui.components.nameKey
import io.aiku.droid.ui.components.rowDay
import io.aiku.droid.ui.components.rowInstant
import io.aiku.droid.ui.components.sortedNullsLast
import io.aiku.droid.ui.components.withinDays
import java.time.LocalDate
import java.time.ZoneId

private const val LAST_WEEK_DAYS = 6L

private val activeStockDeliveryStates = setOf(
    StockDeliveryState.DISPATCHED,
    StockDeliveryState.RECEIVED,
    StockDeliveryState.CHECKED,
    StockDeliveryState.BOOKING_IN,
    StockDeliveryState.BOOKED_IN,
)

internal fun offlineStockDeliveries(rows: List<StockDeliveryRow>, query: StockDeliveryQuery, zone: ZoneId, today: LocalDate): List<StockDeliveryRow> = rows
    .filter { row ->
        row.isIn(query.state) &&
            (query.source == null || row.source == query.source.key) &&
            row.wasDispatched(query.dispatched, zone, today) &&
            matchesSearch(query.search, row.reference, row.agentReference, row.supplier?.name) &&
            withinDays(row.dispatchedAt, query.from, query.to, zone)
    }
    .sortedBy(query.sort ?: StockDeliverySort.Newest, zone)

private fun StockDeliveryRow.isIn(filter: StockDeliveryStateFilter): Boolean = when (filter) {
    StockDeliveryStateFilter.Active -> state in activeStockDeliveryStates
    else -> state == filter.key
}

private fun StockDeliveryRow.wasDispatched(bucket: DispatchedBucket?, zone: ZoneId, today: LocalDate): Boolean {
    if (bucket == null) {
        return true
    }
    val day = rowDay(dispatchedAt, zone) ?: return false
    val weekStart = today.minusDays(LAST_WEEK_DAYS)
    return when (bucket) {
        DispatchedBucket.Today -> !day.isBefore(today)
        DispatchedBucket.Week -> !day.isBefore(weekStart)
        DispatchedBucket.Older -> day.isBefore(weekStart)
    }
}

private fun List<StockDeliveryRow>.sortedBy(sort: StockDeliverySort, zone: ZoneId): List<StockDeliveryRow> = when (sort) {
    StockDeliverySort.Newest, StockDeliverySort.DispatchedAt -> sortedNullsLast(descending = true, id = { it.id }) { rowInstant(it.dispatchedAt) }
    StockDeliverySort.Oldest -> sortedNullsLast(descending = false, id = { it.id }) { rowInstant(it.dispatchedAt) }
    StockDeliverySort.Reference -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.reference) }
    StockDeliverySort.Supplier -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.supplier?.label) }
    StockDeliverySort.ExpectedDate -> sortedNullsLast(descending = false, id = { it.id }) { rowDay(it.estimatedReceivingDate, zone) }
    StockDeliverySort.Items -> sortedNullsLast(descending = true, id = { it.id }) { it.numberItems }
}
