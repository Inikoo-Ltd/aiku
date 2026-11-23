package io.aiku.droid.ui.components

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class ListFilterTest {

    private val nov18 = LocalDate.of(2026, 11, 18)
    private val nov23 = LocalDate.of(2026, 11, 23)

    @Test
    fun `either end of a date range may be set alone`() {
        assertFalse(DateRange().isSet)
        assertTrue(DateRange().withFrom(nov18).isSet)
        assertTrue(DateRange().withTo(nov23).isSet)
        assertEquals(DateRange(nov18, nov23), DateRange().withFrom(nov18).withTo(nov23))
    }

    @Test
    fun `moving one end past the other drops the other end, so to is never before from`() {
        assertNull(DateRange(nov18, nov23).withFrom(nov23.plusDays(1)).to)
        assertNull(DateRange(nov18, nov23).withTo(nov18.minusDays(1)).from)
        assertEquals(DateRange(nov23, nov23), DateRange(nov18, nov23).withFrom(nov23))
    }

    @Test
    fun `the active count adds one for a date range and one for a non-default sort`() {
        assertEquals(0, ListFilter<String>().activeCount)
        assertEquals(1, ListFilter(sort = "reference").activeCount)
        assertEquals(2, ListFilter(dates = DateRange(from = nov18), sort = "reference").activeCount)
        assertTrue(ListFilter<String>(dates = DateRange(to = nov23)).isFiltered)
    }
}
