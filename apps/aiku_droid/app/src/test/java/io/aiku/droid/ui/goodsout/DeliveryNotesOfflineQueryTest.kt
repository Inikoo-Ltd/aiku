package io.aiku.droid.ui.goodsout

import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DeliveryNoteQuery
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.DeliveryNoteSort
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.data.api.PickerFilter
import io.aiku.droid.data.api.StaffRef
import org.junit.Assert.assertEquals
import org.junit.Test
import java.time.LocalDate
import java.time.ZoneId

class DeliveryNotesOfflineQueryTest {

    private val madrid = ZoneId.of("Europe/Madrid")
    private val rows = listOf(
        DeliveryNoteRow(id = 1, reference = "AR1", state = "unassigned", channel = "b2b", customerName = "Zoe", date = "2026-11-20T23:30:00+00:00", numberItems = 3, estimatedWeightKg = 2.0),
        DeliveryNoteRow(
            id = 2, reference = "AR2", state = "handling", channel = "dropshipping", customerName = "adam", picker = StaffRef(42, "Ana"),
            date = "2026-11-21T08:00:00+00:00", numberItems = 10,
        ),
        DeliveryNoteRow(
            id = 3, reference = "AR3", state = "dispatched", channel = "b2b", customerName = "Mia", picker = StaffRef(7, "Bo"),
            date = "2026-11-19T08:00:00+00:00", numberItems = 1, estimatedWeightKg = 9.5,
        ),
        DeliveryNoteRow(id = 4, reference = "AR4", state = "cancelled", channel = "b2c"),
    )

    @Test
    fun `buckets map to the row state as on the server, with each bucket's default order`() {
        assertEquals(listOf(1L), ids(DeliveryNoteQuery(bucket = DeliveryNoteBucket.Unassigned)))
        assertEquals(listOf(1L, 2L), ids(DeliveryNoteQuery(bucket = DeliveryNoteBucket.InWarehouse)))
        assertEquals(listOf(2L, 1L, 3L, 4L), ids(DeliveryNoteQuery(bucket = DeliveryNoteBucket.All)))
        assertEquals(listOf(3L), ids(DeliveryNoteQuery(bucket = DeliveryNoteBucket.Dispatched)))
    }

    @Test
    fun `channel, picker and search use the row's own data, searching the reference only`() {
        assertEquals(listOf(1L, 3L), ids(all().copy(channel = DispatchChannel.B2b)))
        assertEquals(listOf(2L), ids(all().copy(picker = PickerFilter.Me)))
        assertEquals(listOf(1L, 4L), ids(all().copy(picker = PickerFilter.Nobody)))
        assertEquals(listOf(3L), ids(all().copy(picker = PickerFilter.Others)))
        assertEquals(emptyList<Long>(), ids(all().copy(search = "ana")))
        assertEquals(emptyList<Long>(), ids(all().copy(search = "ZO")))
        assertEquals(listOf(3L), ids(all().copy(search = "r3")))
    }

    @Test
    fun `the date range is read in the organisation zone`() {
        val day = LocalDate.of(2026, 11, 21)

        assertEquals(listOf(2L, 1L), ids(all().copy(from = day, to = day)))
        assertEquals(listOf(2L), ids(all().copy(from = day, to = day), ZoneId.of("UTC")))
        assertEquals(listOf(1L, 3L), ids(all().copy(to = LocalDate.of(2026, 11, 20)), ZoneId.of("UTC")))
    }

    @Test
    fun `each sort mirrors the server`() {
        assertEquals(listOf(3L, 1L, 2L, 4L), ids(all().copy(sort = DeliveryNoteSort.DateOldest)))
        assertEquals(listOf(2L, 1L, 3L, 4L), ids(all().copy(sort = DeliveryNoteSort.MostItems)))
        assertEquals(listOf(3L, 1L, 4L, 2L), ids(all().copy(sort = DeliveryNoteSort.Heaviest)))
        assertEquals(listOf(2L, 3L, 1L, 4L), ids(all().copy(sort = DeliveryNoteSort.Customer)))
        assertEquals(listOf(1L, 2L, 3L, 4L), ids(all().copy(sort = DeliveryNoteSort.Reference)))
    }

    private fun all() = DeliveryNoteQuery(bucket = DeliveryNoteBucket.All)

    private fun ids(query: DeliveryNoteQuery, zone: ZoneId = madrid): List<Long> = offlineDeliveryNotes(rows, query, zone, userId = 42).map { it.id }
}
