import Foundation
import Testing
@testable import Aiku

struct GoodsInTests {
    private func item(_ sko: String, delivered: Int, checked: Int? = nil, picking: String? = "A-01-01") -> GoodsInItem {
        GoodsInItem(sko: sko, name: "[Product name]", delivered: delivered, checked: checked, pickingLocation: picking)
    }

    private func delivery(phase: GoodsInDeliveryPhase = .inGoodsIn, items: [GoodsInItem], dispatchedAt: Date = .now, source: GoodsInSource = .supplier, reference: String = "SDaw-0001") -> GoodsInDelivery {
        GoodsInDelivery(reference: reference, source: source, sourceName: "[Supplier]", phase: phase, dispatchedAt: dispatchedAt, items: items)
    }

    @Test("Marking an arriving delivery as received moves it to Received")
    func markAsReceived() {
        var sample = delivery(phase: .dispatched, items: [item("SKO-1", delivered: 10)])
        #expect(sample.state == .arriving)

        sample.markAsReceived(label: "today 09:00")

        #expect(sample.state == .received)
        #expect(sample.receivedLabel == "today 09:00")
    }

    @Test("The delivery becomes Checked once every item has a checked quantity")
    func checkingAllItemsMovesToChecked() {
        var sample = delivery(items: [item("SKO-1", delivered: 10), item("SKO-2", delivered: 5)])

        sample.setChecked("SKO-1", quantity: 10)
        #expect(sample.state == .received)

        sample.setChecked("SKO-2", quantity: 4)
        #expect(sample.state == .checked)
    }

    @Test("Checking every item as 0 makes the delivery Not received")
    func checkingAllAsZeroIsNotReceived() {
        var sample = delivery(items: [item("SKO-1", delivered: 10)])

        sample.setChecked("SKO-1", quantity: 0)

        #expect(sample.state == .notReceived)
        #expect(sample.item("SKO-1")?.checkStatus == .notReceived(10))
    }

    @Test("Checked quantities are classified as exact, under, over or not received")
    func underOverClassification() {
        #expect(GoodsInCheckStatus.classify(checked: nil, delivered: 120) == .pending)
        #expect(GoodsInCheckStatus.classify(checked: 120, delivered: 120) == .exact)
        #expect(GoodsInCheckStatus.classify(checked: 108, delivered: 120) == .under(12))
        #expect(GoodsInCheckStatus.classify(checked: 125, delivered: 120) == .over(5))
        #expect(GoodsInCheckStatus.classify(checked: 0, delivered: 60) == .notReceived(60))
        #expect(GoodsInCheckStatus.under(12).differenceChip == Chip("Under −12", .warn))
        #expect(GoodsInCheckStatus.notReceived(60).differenceChip == Chip("Not received −60", .danger))
    }

    @Test("Under and over counts follow the items")
    func underOverCounts() {
        let sample = delivery(items: [
            item("SKO-1", delivered: 120, checked: 108),
            item("SKO-2", delivered: 10, checked: 12),
            item("SKO-3", delivered: 6, checked: 6),
            item("SKO-4", delivered: 60, checked: 0)
        ])

        #expect(sample.underCount == 1)
        #expect(sample.overCount == 1)
        #expect(sample.differenceItems.map(\.sko) == ["SKO-1", "SKO-2", "SKO-4"])
    }

    @Test("Every scan counts 1 and an unknown code is not counted")
    func scanningCountsOne() {
        var sample = delivery(items: [item("SKO-01187", delivered: 2)])

        #expect(sample.registerScan("sko-01187") == .counted(sko: "SKO-01187", checked: 1, delivered: 2))
        #expect(sample.currentScanItem?.sko == "SKO-01187")
        #expect(sample.registerScan("SKO-01187") == .counted(sko: "SKO-01187", checked: 2, delivered: 2))
        #expect(sample.currentScanItem == nil)
        #expect(sample.registerScan("SKO-02210") == .notOnDelivery(code: "SKO-02210"))
        #expect(sample.state == .checked)
    }

    @Test("Placing never exceeds the checked quantity and adds up per location")
    func placementArithmetic() {
        var sample = delivery(items: [item("SKO-00953", delivered: 200, checked: 200)])

        #expect(sample.place("SKO-00953", quantity: 150, in: "b-01-04") == 150)
        #expect(sample.item("SKO-00953")?.leftToPlace == 50)
        #expect(sample.state == .bookingIn)
        #expect(sample.place("SKO-00953", quantity: 80, in: "C-04-02") == 50)
        #expect(sample.item("SKO-00953")?.placementSummary == "150 in B-01-04 · 50 in C-04-02")
        #expect(sample.place("SKO-00953", quantity: 1, in: "C-04-02") == 0)
    }

    @Test("The delivery becomes Booked in when every checked item is placed")
    func allPlacedIsBookedIn() {
        let now = Date(timeIntervalSince1970: 1_800_000_000)
        var sample = delivery(items: [item("SKO-1", delivered: 10, checked: 10), item("SKO-2", delivered: 5, checked: 0)])
        sample.startBookingIn()
        #expect(sample.state == .bookingIn)

        sample.place("SKO-1", quantity: 10, in: "A-07-01", now: now)

        #expect(sample.state == .bookedIn)
        #expect(sample.bookedInAt == now)
        sample.setAsPlaced()
        #expect(sample.state == .placed)
    }

    @Test("Placing a new SKO can set its picking location")
    func placingSetsPickingLocation() {
        var sample = delivery(items: [item("SKO-02210", delivered: 6, checked: 6, picking: nil)])

        sample.place("SKO-02210", quantity: 6, in: "A-09-04", setAsPicking: true)

        #expect(sample.item("SKO-02210")?.pickingLocation == "A-09-04")
    }

    @Test("A placed item cannot be checked below what is already placed")
    func checkedNeverBelowPlaced() {
        var sample = delivery(items: [item("SKO-1", delivered: 10, checked: 10), item("SKO-2", delivered: 3)])
        sample.place("SKO-1", quantity: 6, in: "A-01-01")

        sample.setChecked("SKO-1", quantity: 2)

        #expect(sample.item("SKO-1")?.checked == 6)
    }

    @Test("Actions follow the delivery state and the user's rights")
    func actionsFollowStateAndRights() {
        let arriving = delivery(phase: .dispatched, items: [item("SKO-1", delivered: 10)])
        #expect(GoodsInDeliveryAction.available(for: arriving, rights: .warehouseStaff) == [.markAsReceived])
        #expect(GoodsInDeliveryAction.available(for: arriving, rights: .full) == [.markAsReceived, .unmarkAsDispatched])
        #expect(GoodsInDeliveryAction.available(for: arriving, rights: .viewOnly).isEmpty)

        let received = delivery(items: [item("SKO-1", delivered: 10, checked: 4), item("SKO-2", delivered: 3)])
        #expect(GoodsInDeliveryAction.available(for: received, rights: .full) == [.checkItems, .placeItems, .unmarkAsReceived, .cancel])

        var bookedIn = delivery(items: [item("SKO-1", delivered: 10, checked: 10)])
        bookedIn.place("SKO-1", quantity: 10, in: "A-01-01")
        #expect(GoodsInDeliveryAction.available(for: bookedIn, rights: .warehouseStaff).isEmpty)
        #expect(GoodsInDeliveryAction.available(for: bookedIn, rights: .full) == [.setAsPlaced])
    }

    @Test("Cancelling is refused once something is placed")
    func cancelOnlyWhileNothingPlaced() {
        var sample = delivery(items: [item("SKO-1", delivered: 10, checked: 10)])
        sample.place("SKO-1", quantity: 1, in: "A-01-01")

        sample.cancel()

        #expect(sample.state == .bookingIn)
    }

    @Test("Dispatch dates fall into today, this week or older")
    func dispatchWindows() {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone(identifier: "UTC") ?? .gmt
        let now = Date(timeIntervalSince1970: 1_800_000_000)

        #expect(GoodsInDispatchWindow.classify(now.addingTimeInterval(-3600), now: now, calendar: calendar) == .today)
        #expect(GoodsInDispatchWindow.classify(now.addingTimeInterval(-3 * 86_400), now: now, calendar: calendar) == .thisWeek)
        #expect(GoodsInDispatchWindow.classify(now.addingTimeInterval(-10 * 86_400), now: now, calendar: calendar) == .older)
    }

    @Test("The filter matches state and source and sorts by reference")
    func filterAndSort() {
        let now = Date.now
        let deliveries = [
            delivery(phase: .dispatched, items: [item("SKO-1", delivered: 1)], source: .agent, reference: "SDaw-0003"),
            delivery(items: [item("SKO-1", delivered: 1)], source: .supplier, reference: "SDaw-0002"),
            delivery(phase: .dispatched, items: [item("SKO-1", delivered: 1)], source: .supplier, reference: "SDaw-0001")
        ]
        var filter = GoodsInDeliveryFilter(states: [.arriving], sort: .reference)

        #expect(filter.apply(to: deliveries, now: now).map(\.reference) == ["SDaw-0001", "SDaw-0003"])

        filter.sources = [.agent]
        #expect(filter.apply(to: deliveries, now: now).map(\.reference) == ["SDaw-0003"])
        #expect(filter.activeCount == 2)
        #expect(filter.summary == "2 filters · sorted by reference")
        #expect(GoodsInDeliveryFilter().summary == "No filters · sorted by dispatched date")
    }

    @Test("Backlog counts group deliveries by state")
    func backlogCounts() {
        let now = Date.now
        var booked = delivery(items: [item("SKO-1", delivered: 1, checked: 1)])
        booked.place("SKO-1", quantity: 1, in: "A-01-01", now: now)
        let counts = GoodsInBacklogCounts.count([
            delivery(phase: .dispatched, items: [item("SKO-1", delivered: 1)]),
            delivery(items: [item("SKO-1", delivered: 1)]),
            booked
        ], now: now)

        #expect(counts == GoodsInBacklogCounts(arriving: 1, received: 1, checked: 0, bookingIn: 0, bookedInLastWeek: 1))
    }

    @Test("Sample data matches the design list counts")
    @MainActor
    func sampleDataCounts() {
        let store = GoodsInStore()

        #expect(store.count(in: .state(.arriving)) == 3)
        #expect(store.count(in: .state(.received)) == 2)
        #expect(store.count(in: .state(.checked)) == 1)
        #expect(store.count(in: .state(.bookingIn)) == 1)
        #expect(store.delivery("SDaw-0091").placedItemCount == 2)
        #expect(store.delivery("unknown").reference == "SDaw-0093")
    }

    @Test("The store moves a delivery from Arriving to Booked in")
    @MainActor
    func storeFlow() {
        let store = GoodsInStore(deliveries: [delivery(phase: .dispatched, items: [item("SKO-1", delivered: 4)])], returns: [])

        store.markAsReceived("SDaw-0001")
        store.setChecked("SDaw-0001", sko: "SKO-1", quantity: 4)
        #expect(store.delivery("SDaw-0001").state == .checked)

        store.place("SDaw-0001", sko: "SKO-1", quantity: 4, in: "A-07-01")
        #expect(store.delivery("SDaw-0001").state == .bookedIn)
    }

    @Test("A return moves from Received to Returning to Returned as items are processed")
    func returnFlow() {
        var sample = GoodsInReturn(reference: "[Return ref] 0409", customer: "[Customer]", items: [
            GoodsInReturnItem(sko: "SKO-00733", name: "Bath bomb – rose 180g", quantity: 2, homeLocation: "A-03-02"),
            GoodsInReturnItem(sko: "SKO-01187", name: "Soy wax candle – vanilla", quantity: 1, homeLocation: "A-07-01")
        ])
        #expect(sample.state == .received)

        sample.process("SKO-00733", outcome: .toLocation("A-03-02"))
        #expect(sample.state == .returning)
        #expect(sample.listSubtitle == "[Customer] · 1 of 2 items processed")

        sample.setAsReturned()
        #expect(sample.state == .returning)

        sample.process("SKO-01187", outcome: .damaged)
        sample.setAsReturned()
        #expect(sample.state == .returned)
    }
}
