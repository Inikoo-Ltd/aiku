package io.aiku.droid.ui.goodsout

import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.DeliveryNoteSort
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.ui.components.matchesSearch
import io.aiku.droid.ui.components.nameKey
import io.aiku.droid.ui.components.rowInstant
import io.aiku.droid.ui.components.sortedNullsLast
import io.aiku.droid.ui.components.withinDays
import java.time.ZoneId

private const val DISPATCHED_STATE = "dispatched"

internal fun offlineDeliveryNotes(rows: List<DeliveryNoteRow>, query: DeliveryNoteQuery, zone: ZoneId, userId: Long?): List<DeliveryNoteRow> = rows
    .filter { row ->
        row.isIn(query.bucket) &&
            (query.channel == DispatchChannel.All || row.channel == query.channel.key) &&
            row.matchesPicker(query.picker, userId) &&
            matchesSearch(query.search, row.reference) &&
            withinDays(row.date, query.from, query.to, zone)
    }
    .sortedBy(query.sort ?: defaultSortOf(query.bucket))

private fun DeliveryNoteRow.isIn(bucket: DeliveryNoteBucket): Boolean = when (bucket) {
    DeliveryNoteBucket.All -> true
    DeliveryNoteBucket.InWarehouse -> state != DISPATCHED_STATE && state != CANCELLED_STATE
    else -> state == bucket.key
}

private fun DeliveryNoteRow.matchesPicker(picker: PickerFilter?, userId: Long?): Boolean = when (picker) {
    null -> true
    PickerFilter.Nobody -> this.picker == null
    PickerFilter.Me -> userId == null || this.picker?.id == userId
    PickerFilter.Others -> this.picker != null && this.picker.id != userId
}

private fun List<DeliveryNoteRow>.sortedBy(sort: DeliveryNoteSort): List<DeliveryNoteRow> = when (sort) {
    DeliveryNoteSort.DateOldest -> sortedNullsLast(descending = false, id = { it.id }) { rowInstant(it.date) }
    DeliveryNoteSort.DateNewest -> sortedNullsLast(descending = true, id = { it.id }) { rowInstant(it.date) }
    DeliveryNoteSort.MostItems -> sortedNullsLast(descending = true, id = { it.id }) { it.numberItems }
    DeliveryNoteSort.Heaviest -> sortedNullsLast(descending = true, id = { it.id }) { it.estimatedWeightKg }
    DeliveryNoteSort.Customer -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.customerName) }
    DeliveryNoteSort.Reference -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.reference) }
}
