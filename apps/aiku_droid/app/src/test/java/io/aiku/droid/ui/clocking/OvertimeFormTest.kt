package io.aiku.droid.ui.clocking

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.LocalDate
import java.time.LocalTime
import java.time.ZoneId

class OvertimeFormTest {

    private val london = ZoneId.of("Europe/London")

    @Test
    fun `the start and end carry the organisation's offset`() {
        val input = overtimeRequestInput(
            typeId = 4,
            date = LocalDate.of(2026, 7, 10),
            startTime = LocalTime.of(18, 0),
            durationMinutes = 90,
            reason = "  Stock take  ",
            zone = london,
        )

        assertEquals(4L, input.overtimeTypeId)
        assertEquals("2026-07-10", input.requestedDate)
        assertEquals("2026-07-10T18:00:00+01:00", input.requestedStartAt)
        assertEquals("2026-07-10T19:30:00+01:00", input.requestedEndAt)
        assertEquals(90, input.requestedDurationMinutes)
        assertEquals("Stock take", input.reason)
        assertEquals(0, input.lieuRequestedMinutes)
    }

    @Test
    fun `a winter shift ends after midnight with a zero offset written out`() {
        val input = overtimeRequestInput(
            typeId = 5,
            date = LocalDate.of(2026, 11, 10),
            startTime = LocalTime.of(23, 30),
            durationMinutes = 45,
            reason = "",
            zone = london,
            lieuRequestedMinutes = 15,
        )

        assertEquals("2026-11-10T23:30:00+00:00", input.requestedStartAt)
        assertEquals("2026-11-11T00:15:00+00:00", input.requestedEndAt)
        assertNull(input.reason)
        assertEquals(15, input.lieuRequestedMinutes)
    }

    @Test
    fun `without a start time or a duration no times are sent`() {
        val withoutStart = overtimeRequestInput(typeId = 4, date = LocalDate.of(2026, 11, 10), startTime = null, durationMinutes = 60, reason = "", zone = london)
        val withoutDuration = overtimeRequestInput(typeId = 4, date = LocalDate.of(2026, 11, 10), startTime = LocalTime.NOON, durationMinutes = 0, reason = "", zone = london)

        assertNull(withoutStart.requestedStartAt)
        assertNull(withoutStart.requestedEndAt)
        assertEquals("2026-11-10T12:00:00+00:00", withoutDuration.requestedStartAt)
        assertNull(withoutDuration.requestedEndAt)
        assertEquals(0, withoutDuration.requestedDurationMinutes)
    }

    @Test
    fun `the duration adds hours and minutes, and stays empty when both are blank`() {
        assertEquals(90, overtimeDurationMinutes("1", "30"))
        assertEquals(120, overtimeDurationMinutes("2", ""))
        assertEquals(45, overtimeDurationMinutes("", "45"))
        assertNull(overtimeDurationMinutes("", " "))
    }

    @Test
    fun `an edited request starts at its local time`() {
        assertEquals(LocalTime.of(18, 0), overtimeStartTime("2026-11-10T18:00:00+00:00", london))
        assertEquals(LocalTime.of(18, 0), overtimeStartTime("2026-07-10T17:00:00Z", london))
        assertNull(overtimeStartTime(null, london))
    }
}
