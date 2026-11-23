package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.PalletDeliveryQuery
import io.aiku.droid.data.api.PalletDeliveryRow
import io.aiku.droid.data.api.PalletDeliverySort
import io.aiku.droid.data.api.PalletDeliveryState
import io.aiku.droid.data.api.PalletDeliveryStateFilter
import io.aiku.droid.ui.components.matchesSearch
import io.aiku.droid.ui.components.nameKey
import io.aiku.droid.ui.components.rowDay
import io.aiku.droid.ui.components.rowInstant
import io.aiku.droid.ui.components.sortedNullsLast
import io.aiku.droid.ui.components.withinDays
import java.time.ZoneId

private val activePalletDeliveryStates = setOf(PalletDeliveryState.CONFIRMED, PalletDeliveryState.RECEIVED, PalletDeliveryState.BOOKING_IN)

internal fun offlinePalletDeliveries(rows: List<PalletDeliveryRow>, query: PalletDeliveryQuery, zone: ZoneId): List<PalletDeliveryRow> = rows
    .filter { row ->
        row.isIn(query.state) &&
            matchesSearch(query.search, row.reference, row.customerReference, row.customer?.name) &&
            withinDays(row.bookedInAt, query.from, query.to, zone)
    }
    .sortedBy(query.sort, zone)

private fun PalletDeliveryRow.isIn(filter: PalletDeliveryStateFilter): Boolean = when (filter) {
    PalletDeliveryStateFilter.Active -> state in activePalletDeliveryStates
    else -> state == filter.key
}

private fun List<PalletDeliveryRow>.sortedBy(sort: PalletDeliverySort?, zone: ZoneId): List<PalletDeliveryRow> = when (sort) {
    null -> sortedNullsLast(descending = false, id = { it.id }, idDescending = true) { rowDay(it.estimatedDeliveryDate, zone) }
    PalletDeliverySort.Reference -> sortedNullsLast(descending = false, id = { it.id }, idDescending = true) { nameKey(it.reference) }
    PalletDeliverySort.BookedIn -> sortedNullsLast(descending = true, id = { it.id }) { rowInstant(it.bookedInAt) }
}
