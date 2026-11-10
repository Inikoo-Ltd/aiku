import Foundation
import Testing
@testable import Aiku

@MainActor
struct FulfilmentTests {
    @Test("Receiving a confirmed delivery moves it and its pallets to Received")
    func receivingConfirmedDelivery() {
        let store = FulfilmentStore()

        store.receive("acme-012")

        #expect(store.delivery("acme-012")?.state == .received)
        #expect(store.pallet("acme-p0235")?.state == .received)
    }

    @Test("Starting booking in puts every received pallet into Booking in")
    func startingBookingIn() {
        let store = FulfilmentStore()

        store.startBookingIn("bravo-007")

        #expect(store.delivery("bravo-007")?.state == .bookingIn)
        #expect(store.currentBookingPallet(in: "bravo-007")?.reference == "bravo-p0118")
    }

    @Test("A delivery can only be set as booked in after the last pallet")
    func bookedInNeedsEveryPallet() {
        let store = FulfilmentStore()
        #expect(store.canSetBookedIn("acme-011") == false)

        store.setBookedIn("acme-011")
        #expect(store.delivery("acme-011")?.state == .bookingIn)

        store.bookIn("acme-p0234", at: "D-02-04")
        #expect(store.canSetBookedIn("acme-011"))

        store.setBookedIn("acme-011")
        #expect(store.delivery("acme-011")?.state == .bookedIn)
        #expect(store.pallet("acme-p0234")?.state == .storing)
        #expect(store.pallet("acme-p0234")?.location == "D-02-04")
        #expect(store.storedCount(of: "acme-011") == 4)
    }

    @Test("A booked-in pallet can be undone back to Booking in")
    func undoingABookedInPallet() {
        let store = FulfilmentStore()

        store.undoBooking("acme-p0231")

        #expect(store.pallet("acme-p0231")?.state == .bookingIn)
        #expect(store.pallet("acme-p0231")?.location == nil)
        #expect(store.deliverySubtitle(store.delivery("acme-011")!) == "[Customer A] · 2 of 4 pallets booked in")
    }

    @Test("The suggested location skips locations already holding a pallet")
    func suggestedLocationSkipsUsedOnes() {
        let store = FulfilmentStore()
        #expect(store.suggestedLocation() == "D-02-04")

        store.bookIn("acme-p0234", at: "D-02-04")
        #expect(store.suggestedLocation() == "D-02-05")
    }

    @Test("Boxes with the same customer reference are grouped into one row")
    func groupingBoxes() {
        let store = FulfilmentStore()
        let groups = FulfilmentPalletGroup.grouping(store.pallets(of: store.delivery("bravo-007")!))

        #expect(groups.count == 3)
        #expect(groups[2].title == "bravo-p0120 … p0127")
        #expect(groups[2].subtitle == "8 boxes · “Gift sets”")
        #expect(groups[0].subtitle == "Customer ref “Spring stock 1”")
    }

    @Test("Delivery subtitles count pallets and boxes")
    func deliverySubtitles() {
        let store = FulfilmentStore()

        #expect(store.deliverySubtitle(store.delivery("bravo-007")!) == "[Customer B] · 2 pallets, 8 boxes")
        #expect(store.deliverySubtitle(store.delivery("acme-012")!) == "[Customer A] · 6 pallets · arriving today")
        #expect(store.deliverySubtitle(store.delivery("acme-011")!) == "[Customer A] · 3 of 4 pallets booked in")
    }

    @Test("Reporting an incident clears the location and lists it first")
    func reportingAnIncident() {
        let store = FulfilmentStore()

        store.reportIncident("acme-p0140", kind: .damaged, note: " Forklift ", reportedBy: "Alex M.", date: "10 Nov")

        #expect(store.pallet("acme-p0140")?.state == .damaged)
        #expect(store.pallet("acme-p0140")?.location == nil)
        #expect(store.incidents.first?.palletReference == "acme-p0140")
        #expect(store.incidents.first?.subtitle == "“Forklift” · Alex M. · 10 Nov")
        #expect(store.palletCount(.incidents) == 4)
    }

    @Test("Setting a pallet back to storing removes its incident")
    func resolvingAnIncident() {
        let store = FulfilmentStore()

        store.setBackToStoring("bravo-p0097")

        #expect(store.pallet("bravo-p0097")?.state == .storing)
        #expect(store.incident(for: "bravo-p0097") == nil)
        #expect(store.incidents.count == 2)
    }

    @Test("Incident lines mention the return when a pallet was not picked")
    func returnIncidentSubtitle() {
        let store = FulfilmentStore()

        #expect(store.incident(for: "acme-p0122")?.subtitle == "Not picked in return acme-r003 · “Pallet wet”")
    }

    @Test("Reporter labels use the first name and last initial")
    func reporterLabel() {
        #expect(FulfilmentIncident.reporterLabel(fullName: "Alex Morgan") == "Alex M.")
        #expect(FulfilmentIncident.reporterLabel(fullName: "Cher") == "Cher")
    }

    @Test("Pallet filters split the sample pallets by state")
    func palletFilters() {
        let store = FulfilmentStore()

        #expect(store.palletCount(.storing) == 3)
        #expect(store.palletCount(.receiving) == 14)
        #expect(store.palletCount(.returning) == 4)
        #expect(store.palletCount(.incidents) == 3)
        #expect(store.palletCount(.returned) == 0)
    }

    @Test("Stored items add up quantities across the pallets holding them")
    func storedItemSummaries() {
        let store = FulfilmentStore()
        let summaries = Dictionary(uniqueKeysWithValues: store.storedItemSummaries.map { ($0.item.code, $0) })

        #expect(summaries["MUG-BLUE"]?.quantity == 64)
        #expect(summaries["MUG-BLUE"]?.palletReferences.count == 2)
        #expect(summaries["GIFT-L"]?.quantity == 210)
        #expect(summaries["GIFT-L"]?.subtitle == "[Customer B] · Gift box large · 3 pallets")
    }

    @Test("Audit lines show subtraction, no change and not counted")
    func auditLineChips() {
        let store = FulfilmentStore()
        let audit = store.audit(forPallet: "acme-p0140")

        #expect(audit.reference == "acme-sia014")
        #expect(audit.lines[0].chip == Chip("No change", .success))
        #expect(audit.lines[1].chip == Chip("Subtraction −2", .warn))
        #expect(audit.lines[2].chip == nil)
        #expect(audit.lines[2].subtitle == "Expected 18 · not counted yet")
    }

    @Test("Every audit scan counts 1 and completing applies the counts")
    func completingAnAudit() {
        let store = FulfilmentStore()

        #expect(store.scanNextAuditItem(palletReference: "acme-p0140") == "MUG-GREEN")
        store.countAll("TOWEL-SET", inAuditOf: "acme-p0140")
        store.completeAudit(ofPallet: "acme-p0140")

        #expect(store.pallet("acme-p0140")?.quantity(of: "MUG-GREEN") == 3)
        #expect(store.pallet("acme-p0140")?.quantity(of: "TOWEL-SET") == 18)
        #expect(store.audits.contains { $0.palletReference == "acme-p0140" } == false)
    }

    @Test("A new audit gets the next reference only once it is started")
    func newAuditReference() {
        let store = FulfilmentStore()

        #expect(store.audit(forPallet: "bravo-p0152").reference == "bravo-sia015")
        #expect(store.audits.count == 1)

        store.count("REED-100", by: 2, inAuditOf: "bravo-p0152")
        #expect(store.audits.count == 2)
        #expect(store.audit(forPallet: "bravo-p0152").lines.first { $0.itemCode == "REED-100" }?.counted == 2)
    }

    @Test("Return states follow the picked lines")
    func returnStates() {
        let store = FulfilmentStore()

        #expect(store.returnRecord("acme-r004")?.state == .confirmed)
        #expect(store.returnRecord("bravo-r011")?.state == .picking)
        #expect(store.returnRecord("acme-r003")?.state == .picked)
    }

    @Test("Picking every pallet sets the return as picked")
    func pickingPallets() {
        let store = FulfilmentStore()

        store.pickPallet("acme-p0118", inReturn: "acme-r004")
        #expect(store.returnRecord("acme-r004")?.state == .picking)
        #expect(store.returnRecord("acme-r004")?.currentPalletLine?.palletReference == "acme-p0121")

        store.pickPallet("acme-p0121", inReturn: "acme-r004")
        #expect(store.returnRecord("acme-r004")?.state == .picked)
        #expect(store.returnRecord("acme-r004")?.pickedSummary == "2 pallets: acme-p0118, acme-p0121")
    }

    @Test("A pallet not picked becomes an incident linked to the return")
    func notPickedPallet() {
        let store = FulfilmentStore()

        store.setNotPicked("acme-p0121", inReturn: "acme-r004", kind: .other, note: "Pallet wet", reportedBy: "Alex M.", date: "10 Nov")

        #expect(store.pallet("acme-p0121")?.state == .other)
        #expect(store.incident(for: "acme-p0121")?.returnReference == "acme-r004")
        #expect(store.returnRecord("acme-r004")?.palletLines.last?.state == .notPicked)
    }

    @Test("A short item line is completed from another pallet")
    func pickingFromAnotherPallet() {
        let store = FulfilmentStore()
        let reed = "REED-100@bravo-p0140"

        #expect(store.alternativePallet(for: reed, inReturn: "bravo-r011")?.reference == "bravo-p0152")
        store.pickFromAnotherPallet(reed, inReturn: "bravo-r011")
        store.pickItems("REED-100@bravo-p0152", amount: 1, inReturn: "bravo-r011")
        store.pickItems("CANDLE-AMB@bravo-p0152", amount: 5, inReturn: "bravo-r011")

        let record = store.returnRecord("bravo-r011")
        #expect(record?.itemLines.first { $0.id == reed }?.required == 1)
        #expect(record?.itemLines.first { $0.id == "CANDLE-AMB@bravo-p0152" }?.picked == 1)
        #expect(record?.state == .picked)
    }

    @Test("Dispatch needs a shipment unless the customer collects")
    func dispatchingAReturn() {
        let store = FulfilmentStore()
        #expect(store.canDispatch("acme-r003") == false)

        store.createShipment(forReturn: "acme-r003")
        #expect(store.returnRecord("acme-r003")?.shipment == FulfilmentSampleData.carrierShipment)
        #expect(store.canDispatch("acme-r003"))

        store.dispatch("acme-r003")
        #expect(store.returnRecord("acme-r003")?.state == .dispatched)
        #expect(store.pallet("acme-p0119")?.state == .returned)
        #expect(store.returnRecord("acme-r003")?.returnedMessage == "2 pallets are now Returned.")
    }

    @Test("A collected stored-item return takes the items off their pallets")
    func collectingStoredItems() {
        let store = FulfilmentStore()
        store.pickItems("MUG-BLUE@acme-p0140", amount: 1, inReturn: "acme-r009")
        store.setDeparture(.collection, forReturn: "acme-r009")

        store.dispatch("acme-r009")

        #expect(store.returnRecord("acme-r009")?.state == .dispatched)
        #expect(store.pallet("acme-p0140")?.quantity(of: "MUG-BLUE") == 38)
    }

    @Test("Return filters count each type")
    func returnFilters() {
        let store = FulfilmentStore()

        #expect(store.returnCount(.all) == 4)
        #expect(store.returnCount(.type(.pallets)) == 2)
        #expect(store.returnCount(.type(.storedItems)) == 1)
        #expect(store.returnCount(.type(.dropshipping)) == 1)
        #expect(store.returnRecord("acme-r004")?.listSubtitle == "[Customer A] · Pallets (wholesale) · 2 pallets")
        #expect(store.returnRecord("acme-r009")?.listSubtitle == "[Customer A] · Dropshipping · from Shopify")
    }
}
