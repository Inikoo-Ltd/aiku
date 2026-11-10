package io.aiku.droid.ui.clocking

internal val ClockingEnabledMethodsSample = listOf(ClockingMethod.QrCode, ClockingMethod.Pin, ClockingMethod.Barcode, ClockingMethod.CameraQr)

internal data class ClockingSessionSample(val clockIn: String, val clockOut: String?, val duration: String?)

internal data class ClockingStatusSample(
    val isClockedIn: Boolean,
    val statusTime: String,
    val date: String,
    val firstClockIn: String,
    val lastClockOut: String?,
    val working: String,
    val breaks: String,
    val sessions: List<ClockingSessionSample>,
)

internal val ClockingTodaySample = ClockingStatusSample(
    isClockedIn = true,
    statusTime = "13:01:40",
    date = "Mon 09 Nov 2026",
    firstClockIn = "08:02:14",
    lastClockOut = null,
    working = "5h 41m",
    breaks = "31m",
    sessions = listOf(
        ClockingSessionSample(clockIn = "08:02:14", clockOut = "12:30:05", duration = "4h 27m"),
        ClockingSessionSample(clockIn = "13:01:40", clockOut = null, duration = null),
    ),
)

internal const val CLOCKING_LOCATION_SAMPLE = "51.5072, −0.1276"
internal const val CLOCKING_PIN_SAMPLE = "4829"
internal const val CLOCKING_EMPLOYEE_CODE_SAMPLE = "[EMPLOYEE-CODE]"

internal val ClockingOrganisationHasShiftsSample: Boolean = true
internal const val CLOCKING_DEFAULT_HOURS_SAMPLE = "08:00 - 17:00"

internal data class ClockingShiftSample(val name: String, val hours: String)

internal val ClockingShiftsSample = listOf(
    ClockingShiftSample(name = "[Early shift]", hours = "06:00 - 14:00"),
    ClockingShiftSample(name = "[Late shift]", hours = "14:00 - 22:00"),
)

internal const val CLOCKING_SCAN_TIME_SAMPLE = "08:02:14"
internal val ClockingScanIsClockOutSample: Boolean = false
internal val ClockingScanIsVisitingSample: Boolean = false
internal const val CLOCKING_SCAN_WORKING_HOURS_SAMPLE = "08:00 - 17:00"

internal enum class ClockingDayState { Complete, Open, Error }

internal data class ClockingWeekDaySample(
    val date: String,
    val range: String,
    val summary: String,
    val worked: String,
    val state: ClockingDayState,
    val hours: Float,
)

internal data class ClockingWeekSample(
    val label: String,
    val range: String,
    val total: String,
    val breaks: String,
    val days: String,
    val entries: List<ClockingWeekDaySample>,
)

internal const val CLOCKING_WEEK_TARGET_SAMPLE = "37h 30m"

internal val ClockingWeeksSample = listOf(
    ClockingWeekSample(
        label = "This week",
        range = "09 – 15 Nov 2026",
        total = "6h 13m",
        breaks = "31m",
        days = "1 of 5",
        entries = listOf(
            ClockingWeekDaySample("2026-11-09", "08:02 → now", "Open · 3 clockings", "6h 13m", ClockingDayState.Open, 6.2f),
        ),
    ),
    ClockingWeekSample(
        label = "Last week",
        range = "02 – 08 Nov 2026",
        total = "31h 40m",
        breaks = "2h 35m",
        days = "5 of 5",
        entries = listOf(
            ClockingWeekDaySample("2026-11-06", "07:58 → 16:31", "4 clockings · break 32m", "8h 01m", ClockingDayState.Complete, 8f),
            ClockingWeekDaySample("2026-11-05", "08:05 → —", "Error · no clock out", "4h 25m", ClockingDayState.Error, 4.4f),
            ClockingWeekDaySample("2026-11-04", "08:00 → 16:30", "4 clockings · break 30m", "8h 00m", ClockingDayState.Complete, 8f),
            ClockingWeekDaySample("2026-11-03", "08:10 → 15:05", "2 clockings · break —", "6h 55m", ClockingDayState.Complete, 6.9f),
            ClockingWeekDaySample("2026-11-02", "07:55 → 12:15", "2 clockings · half day", "4h 20m", ClockingDayState.Complete, 4.3f),
        ),
    ),
    ClockingWeekSample(
        label = "2 weeks ago",
        range = "26 Oct – 01 Nov 2026",
        total = "37h 52m",
        breaks = "2h 40m",
        days = "5 of 5",
        entries = listOf(
            ClockingWeekDaySample("2026-10-30", "08:00 → 16:35", "4 clockings", "8h 05m", ClockingDayState.Complete, 8.1f),
            ClockingWeekDaySample("2026-10-29", "08:02 → 16:30", "4 clockings", "7h 58m", ClockingDayState.Complete, 8f),
            ClockingWeekDaySample("2026-10-28", "07:59 → 16:28", "4 clockings", "7h 59m", ClockingDayState.Complete, 8f),
            ClockingWeekDaySample("2026-10-27", "08:01 → 14:00", "2 clockings · manual fix", "5h 59m", ClockingDayState.Complete, 6f),
            ClockingWeekDaySample("2026-10-26", "08:00 → 16:31", "4 clockings", "7h 51m", ClockingDayState.Complete, 7.9f),
        ),
    ),
)

internal data class ClockingTrackerSample(val range: String, val kind: String, val duration: String)

internal data class ClockingEventSample(val time: String, val summary: String, val hasPhoto: Boolean)

internal data class ClockingDaySample(
    val working: String,
    val breaks: String,
    val clockings: String,
    val trackers: List<ClockingTrackerSample>,
    val events: List<ClockingEventSample>,
    val workplace: String,
    val shift: String,
)

internal val ClockingDayDetailSample = ClockingDaySample(
    working = "4h 25m",
    breaks = "28m",
    clockings = "3",
    trackers = listOf(
        ClockingTrackerSample(range = "08:05 → 12:30", kind = "Clock in → clock out", duration = "4h 25m"),
        ClockingTrackerSample(range = "12:58 → 17:04", kind = "Clock in → clock out", duration = "4h 06m"),
    ),
    events = listOf(
        ClockingEventSample(time = "08:05", summary = "In · Mobile app · Main Warehouse", hasPhoto = true),
        ClockingEventSample(time = "12:30", summary = "Out · Clocking machine · Goods-in door", hasPhoto = false),
        ClockingEventSample(time = "12:58", summary = "In · Mobile app · Main Warehouse", hasPhoto = true),
    ),
    workplace = "Main Warehouse · WH1",
    shift = "08:00 – 16:30",
)

internal const val CLOCKING_ADJUSTMENT_DATE_SAMPLE = "2026-11-05"

internal data class ClockingAdjustmentSample(val missing: String, val time: String, val shiftEnd: String, val reason: String)

internal val ClockingAdjustmentRequestSample = ClockingAdjustmentSample(
    missing = "Clock out",
    time = "16:30",
    shiftEnd = "16:30",
    reason = "Forgot to clock out at the door",
)

internal const val CLOCKING_ERROR_MESSAGE_SAMPLE = "[Server message, e.g. not a clocking machine]"
internal const val CLOCKING_ERROR_SCANNED_SAMPLE = "[LOC-A-03-02]"

internal data class ClockingBarcodeBar(val widthDp: Int, val isDark: Boolean)

internal data class ClockingPersonalCodes(val barcode: List<ClockingBarcodeBar>, val qrCells: List<Boolean>)

internal const val CLOCKING_QR_MODULES = 11

private const val SAMPLE_BARCODE_BARS = 44
private const val SAMPLE_CODE_SEED = 7L
private const val SAMPLE_CODE_MULTIPLIER = 9301L
private const val SAMPLE_CODE_INCREMENT = 49297L
private const val SAMPLE_CODE_MODULUS = 233280L
private const val SAMPLE_WIDE_BAR_THRESHOLD = 0.55
private const val SAMPLE_DARK_CELL_THRESHOLD = 0.52
private const val QR_FINDER_SIZE = 3
private const val QR_FINDER_FAR_EDGE = 8

private class SampleCodeRandom(private var seed: Long) {
    fun next(): Double {
        seed = (seed * SAMPLE_CODE_MULTIPLIER + SAMPLE_CODE_INCREMENT) % SAMPLE_CODE_MODULUS
        return seed.toDouble() / SAMPLE_CODE_MODULUS
    }
}

private fun isQrFinderCell(row: Int, column: Int): Boolean {
    fun inFinder(top: Int, left: Int) = row in top until top + QR_FINDER_SIZE && column in left until left + QR_FINDER_SIZE
    return inFinder(0, 0) || inFinder(0, QR_FINDER_FAR_EDGE) || inFinder(QR_FINDER_FAR_EDGE, 0)
}

internal val ClockingPersonalCodesSample: ClockingPersonalCodes = run {
    val random = SampleCodeRandom(SAMPLE_CODE_SEED)
    val barcode = List(SAMPLE_BARCODE_BARS) { index ->
        ClockingBarcodeBar(widthDp = if (random.next() > SAMPLE_WIDE_BAR_THRESHOLD) 4 else 2, isDark = index % 2 == 0)
    }
    val qrCells = List(CLOCKING_QR_MODULES * CLOCKING_QR_MODULES) { index ->
        isQrFinderCell(index / CLOCKING_QR_MODULES, index % CLOCKING_QR_MODULES) || random.next() > SAMPLE_DARK_CELL_THRESHOLD
    }
    ClockingPersonalCodes(barcode = barcode, qrCells = qrCells)
}
