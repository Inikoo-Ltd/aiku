package io.aiku.droid.ui.goodsout.extra

import io.aiku.droid.data.api.PickingSessionQuery
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.PickingSessionSort
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.api.StaffRef
import org.junit.Assert.assertEquals
import org.junit.Test

class PickingSessionsOfflineQueryTest {

    private val rows = listOf(
        PickingSessionRow(id = 1, reference = "PS-001", state = "packing_finished", picker = StaffRef(5, "Zed"), startAt = "2026-11-20T08:00:00+00:00", endAt = "2026-11-20T12:00:00+00:00"),
        PickingSessionRow(id = 2, reference = "PS-002", state = "handling", picker = StaffRef(6, "amy"), startAt = "2026-11-21T08:00:00+00:00"),
        PickingSessionRow(id = 3, reference = "PS-003", state = "in_process"),
        PickingSessionRow(id = 4, reference = "PS-004", state = "handling", picker = StaffRef(7, "Bea"), startAt = "2026-11-19T08:00:00+00:00", endAt = "2026-11-21T12:00:00+00:00"),
    )

    @Test
    fun `the default order puts finished packing last and the newest first`() {
        assertEquals(listOf(4L, 3L, 2L, 1L), ids(PickingSessionQuery()))
        assertEquals(listOf(4L, 2L), ids(PickingSessionQuery(state = PickingSessionState.Handling)))
    }

    @Test
    fun `search looks inside the reference only, as Aiku does`() {
        assertEquals(listOf(3L), ids(PickingSessionQuery(search = "s-003")))
        assertEquals(emptyList<Long>(), ids(PickingSessionQuery(search = "AMY")))
    }

    @Test
    fun `each sort mirrors the server`() {
        assertEquals(listOf(1L, 2L, 3L, 4L), ids(PickingSessionQuery(sort = PickingSessionSort.Reference)))
        assertEquals(listOf(2L, 4L, 1L, 3L), ids(PickingSessionQuery(sort = PickingSessionSort.Picker)))
        assertEquals(listOf(2L, 1L, 4L, 3L), ids(PickingSessionQuery(sort = PickingSessionSort.Started)))
        assertEquals(listOf(4L, 1L, 3L, 2L), ids(PickingSessionQuery(sort = PickingSessionSort.Finished)))
    }

    private fun ids(query: PickingSessionQuery): List<Long> = offlinePickingSessions(rows, query).map { it.id }
}
