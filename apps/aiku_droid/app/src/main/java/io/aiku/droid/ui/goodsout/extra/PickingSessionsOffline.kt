package io.aiku.droid.ui.goodsout.extra

import io.aiku.droid.data.api.PickingSessionQuery
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.PickingSessionSort
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.ui.components.matchesSearch
import io.aiku.droid.ui.components.nameKey
import io.aiku.droid.ui.components.rowInstant
import io.aiku.droid.ui.components.sortedNullsLast

internal fun offlinePickingSessions(rows: List<PickingSessionRow>, query: PickingSessionQuery): List<PickingSessionRow> = rows
    .filter { row -> (query.state == null || row.state == query.state.key) && matchesSearch(query.search, row.reference) }
    .sortedBy(query.sort)

private fun List<PickingSessionRow>.sortedBy(sort: PickingSessionSort?): List<PickingSessionRow> = when (sort) {
    null -> sortedWith(compareBy<PickingSessionRow> { it.state == PickingSessionState.PackingFinished.key }.thenByDescending { it.id })
    PickingSessionSort.Reference -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.reference) }
    PickingSessionSort.Picker -> sortedNullsLast(descending = false, id = { it.id }) { nameKey(it.picker?.name) }
    PickingSessionSort.Started -> sortedNullsLast(descending = true, id = { it.id }) { rowInstant(it.startAt) }
    PickingSessionSort.Finished -> sortedNullsLast(descending = true, id = { it.id }) { rowInstant(it.endAt) }
}
