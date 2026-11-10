import Foundation
import Testing
@testable import Aiku

@MainActor
struct ClockingTests {
    private func store(at seconds: Int, pin: String? = nil, methods: [ClockingMethod] = ClockingMethod.allCases) -> ClockingStore {
        ClockingStore(enabledMethods: methods, pin: pin, clock: { seconds })
    }

    @Test("Durations read as hours and two-digit minutes")
    func durationsAreFormatted() {
        #expect(ClockingFormat.duration(ClockingFormat.seconds(4, 27, 51)) == "4h 27m")
        #expect(ClockingFormat.duration(ClockingFormat.seconds(8, 0)) == "8h 00m")
        #expect(ClockingFormat.duration(ClockingFormat.seconds(0, 31, 35)) == "31m")
        #expect(ClockingFormat.time(ClockingFormat.seconds(8, 2, 14)) == "08:02:14")
    }

    @Test("Adjustment times must be valid 24-hour times")
    func timesAreParsed() {
        #expect(ClockingFormat.parseTime("16:30") == ClockingFormat.seconds(16, 30))
        #expect(ClockingFormat.parseTime("24:00") == nil)
        #expect(ClockingFormat.parseTime("9:5") == nil)
        #expect(ClockingFormat.parseTime("late") == nil)
    }

    @Test("Today counts the open session up to now and the gap as a break")
    func todayWorkingTimeIncludesOpenSession() {
        let store = store(at: ClockingFormat.seconds(14, 15, 40))

        #expect(store.isClockedIn)
        #expect(ClockingFormat.duration(store.workingToday) == "5h 41m")
        #expect(ClockingFormat.duration(store.today.breaks) == "31m")
        #expect(store.statusSince == "Since: 13:01:40")
        #expect(store.today.rangeLabel == "08:02 → now")
        #expect(store.today.summary == "Open · 3 clockings")
    }

    @Test("A past day without a clock out is an error and only counts closed sessions")
    func missingClockOutIsAnError() throws {
        let store = store(at: ClockingFormat.seconds(14, 0))
        let day = store.day(for: "2026-11-05")

        #expect(day.status == .error)
        #expect(day.summary == "Error · no clock out")
        #expect(ClockingFormat.duration(day.working(now: store.now)) == "4h 25m")
        #expect(ClockingFormat.duration(day.breaks) == "28m")
        #expect(day.clockingCount == 3)
        #expect(day.rangeLabel == "08:05 → —")
    }

    @Test("Week totals add up every day")
    func weekTotalsAddUp() throws {
        let store = store(at: ClockingFormat.seconds(14, 0))
        let lastWeek = try #require(store.pastWeeks.first)

        #expect(ClockingFormat.duration(lastWeek.working(now: store.now)) == "31h 41m")
        #expect(lastWeek.daysLabel == "5 of 5")
        #expect(lastWeek.day(on: "Fri")?.summary == "4 clockings · break 32m")
        #expect(lastWeek.day(on: "Mon")?.summary == "2 clockings · half day")
        #expect(lastWeek.day(on: "Tue")?.summary == "2 clockings · break —")
    }

    @Test("A successful scan clocks out when clocked in and adds the clocking to today")
    func successfulScanClocksOut() {
        let store = store(at: ClockingFormat.seconds(17, 4, 22))

        guard case .success(let event) = store.simulateScan() else {
            Issue.record("First simulated scan should succeed")
            return
        }

        #expect(event.kind == .clockOut)
        #expect(event.time == ClockingFormat.seconds(17, 4, 22))
        #expect(store.isClockedIn == false)
        #expect(store.today.clockingCount == 4)
        #expect(store.statusSince == "Last clock out 17:04:22")
        #expect(ClockingFormat.duration(store.workingToday) == "8h 30m")
        #expect(store.thisWeek.days.first?.summary == "4 clockings · break 31m")
    }

    @Test("Simulated scans follow the script: success, rejected, success, no connection")
    func scanScriptCycles() {
        let store = store(at: ClockingFormat.seconds(17, 0))

        #expect(store.simulateScan() != .rejected)
        #expect(store.simulateScan() == .rejected)
        guard case .success(let event) = store.simulateScan() else {
            Issue.record("Third simulated scan should succeed")
            return
        }
        #expect(event.kind == .clockIn)
        #expect(store.isClockedIn)
        #expect(store.simulateScan() == .offline)
        #expect(store.today.clockingCount == 5)
    }

    @Test("A clocking is never recorded before the previous one")
    func clockingTimesStayInOrder() {
        let store = store(at: ClockingFormat.seconds(10, 0))

        let event = store.recordClocking()

        #expect(event.time == ClockingFormat.seconds(13, 1, 41))
    }

    @Test("The first location detection is blocked and the next one finds the location")
    func locationDetectionSequence() {
        let store = store(at: ClockingFormat.seconds(14, 0))

        #expect(store.finishDetectingLocation() == false)
        #expect(store.location == .needed)
        #expect(store.finishDetectingLocation())
        #expect(store.location == .found)
    }

    @Test("An adjustment needs a valid time and a reason")
    func adjustmentValidation() {
        let store = store(at: ClockingFormat.seconds(14, 0))

        #expect(store.requestAdjustment(date: "2026-11-05", kind: .clockOut, time: "4pm", reason: "Forgot") == .failure(.invalidTime))
        #expect(store.requestAdjustment(date: "2026-11-05", kind: .clockOut, time: "16:30", reason: "  ") == .failure(.missingReason))
        guard case .success(let request) = store.requestAdjustment(date: "2026-11-05", kind: .clockOut, time: "16:30", reason: "Forgot to clock out at the door") else {
            Issue.record("Valid adjustment should succeed")
            return
        }
        #expect(request.summary == "Clock out 16:30 · Forgot to clock out at the door")
        #expect(store.adjustments(for: "2026-11-05").count == 1)
    }

    @Test("The adjustment sheet opens on the day with a missing clocking")
    func adjustmentDayIsTheErrorDay() {
        let store = store(at: ClockingFormat.seconds(14, 0))

        #expect(store.adjustmentDay.date == "2026-11-05")
        #expect(store.day(for: "unknown").date == "2026-11-05")
    }

    @Test("Methods fall back to the first enabled one and PIN shows no code until one exists")
    func methodsAndCodes() {
        let none = store(at: 0, methods: [])
        #expect(none.activeMethod == nil)

        let pinOnly = store(at: 0, methods: [.qrCode, .pin])
        pinOnly.selectedMethod = .barcode
        #expect(pinOnly.activeMethod == .qrCode)
        #expect(pinOnly.code(for: .pin) == nil)

        let withPin = store(at: 0, pin: "4829")
        #expect(withPin.code(for: .pin) == "4829")
    }

    @Test("Notes are saved on the clocking they belong to")
    func notesAreSaved() {
        let store = store(at: ClockingFormat.seconds(17, 0))
        let event = store.recordClocking()

        store.saveNote("  Left via goods-in  ", for: event.id)

        #expect(store.today.events.last?.note == "Left via goods-in")
    }

    @Test("The clocking codes match the design pattern size")
    func codePatternSizes() {
        #expect(ClockingCodePattern.bars.count == 44)
        #expect(ClockingCodePattern.qrCells.count == 121)
        #expect(ClockingCodePattern.qrCells[0])
        #expect(ClockingCodePattern.qrCells[8])
    }
}
