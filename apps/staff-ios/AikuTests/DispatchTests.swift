import Foundation
import Testing
@testable import Aiku

@MainActor
struct DispatchTests {
    @Test("Session states and list lines come from the sample data")
    func sampleSessions() {
        let store = DispatchStore()

        #expect(store.session("PSaw-0142")?.state == .picking)
        #expect(store.session("PSaw-0142")?.listSubtitle == "Sam P. · 12 delivery notes · 2 trolleys · 60% picked")
        #expect(store.session("PSaw-0143")?.listSubtitle == "Alex Morgan · 8 delivery notes · not started")
        #expect(store.session("PSaw-0139")?.listSubtitle == "Jo K. · 1 delivery note waiting")
        #expect(store.session("PSaw-0137")?.state == .pickingFinished)
        #expect(store.session("PSaw-0137")?.listSubtitle == "Jo K. · packing 50%")
    }

    @Test("Session PSaw-0143 holds 8 delivery notes and 23 items")
    func sessionTotals() {
        let session = DispatchStore().session("PSaw-0143")

        #expect(session?.itemCount == 23)
        #expect(session?.deliveryNoteLines.count == 8)
        #expect(session?.noteCount(onTrolley: "T-03") == 4)
        #expect(session?.noteCount(onTrolley: "T-05") == 4)
    }

    @Test("Starting a session puts its trolleys in use")
    func startingTakesTrolleys() {
        let store = DispatchStore()
        #expect(store.trolleys.first { $0.code == "T-03" }?.holder == .free)

        store.startPicking("PSaw-0143")

        #expect(store.session("PSaw-0143")?.state == .picking)
        #expect(store.trolleys.first { $0.code == "T-03" }?.holder == .session("PSaw-0143"))
        #expect(store.trolleys.first { $0.code == "T-01" }?.holder == .deliveryNote("AR24790"))
    }

    @Test("Each scan picks 1 for the delivery note on top of the list")
    func scanPicksTopNote() {
        let store = DispatchStore()
        store.startPicking("PSaw-0143")

        let note = store.scanPick("PSaw-0143")

        #expect(note?.deliveryNote == "AR24830")
        #expect(store.session("PSaw-0143")?.pickedCount == 1)
        #expect(store.session("PSaw-0143")?.currentLocation?.location == "A-01-01")
    }

    @Test("Picking an amount fills notes in order and moves to the next location")
    func pickingAmounts() {
        let store = DispatchStore()
        store.startPicking("PSaw-0143")

        store.pick("PSaw-0143", at: "A-01-01", amount: 4)
        #expect(store.session("PSaw-0143")?.locations[0].notes.map(\.picked) == [3, 1, 0])

        store.pickAll("PSaw-0143", at: "A-01-01")
        #expect(store.session("PSaw-0143")?.currentLocation?.location == "A-03-02")
        #expect(store.session("PSaw-0143")?.nextLocation?.location == "B-01-04")
    }

    @Test("Picking every location finishes picking and prepares packing")
    func finishingPicking() {
        let store = DispatchStore()
        store.startPicking("PSaw-0143")

        for location in ["A-01-01", "A-03-02", "B-01-04", "A-07-01"] {
            store.pickAll("PSaw-0143", at: location)
        }

        let session = store.session("PSaw-0143")
        #expect(session?.state == .pickingFinished)
        #expect(session?.pickedPercent == 100)
        #expect(session?.packNotes.count == 8)
        #expect(session?.packNotes.first { $0.deliveryNote == "AR24830" }?.items == 5)
    }

    @Test("A waiting line moves the session to Waiting and into a free bay")
    func waitingGoesToBay() {
        let store = DispatchStore()
        store.startPicking("PSaw-0143")
        store.pickAll("PSaw-0143", at: "A-01-01")
        store.pickAll("PSaw-0143", at: "A-03-02")
        store.pickAll("PSaw-0143", at: "B-01-04")
        store.pick("PSaw-0143", at: "A-07-01", amount: 3)

        store.setWaiting("PSaw-0143", at: "A-07-01")

        let session = store.session("PSaw-0143")
        #expect(session?.state == .waiting)
        #expect(session?.bay == "Bay 1")
        #expect(store.bays.first?.subtitle == "8 delivery notes · 1 waiting")
    }

    @Test("Packing every delivery note packs the session and frees its trolleys")
    func packingFreesTrolleys() {
        let store = DispatchStore()
        #expect(store.trolleys.first { $0.code == "T-06" }?.holder == .session("PSaw-0137"))

        #expect(store.scanPack("PSaw-0137")?.deliveryNote == "AR24802")
        #expect(store.session("PSaw-0137")?.packNotes[1].packed == 3)
        store.packAll("PSaw-0137", deliveryNote: "AR24802")
        store.pack("PSaw-0137", deliveryNote: "AR24805", amount: 9)

        #expect(store.session("PSaw-0137")?.state == .packed)
        #expect(store.trolleys.first { $0.code == "T-06" }?.holder == .free)
    }

    @Test("Pack notes show their chip by progress")
    func packNoteChips() {
        #expect(DispatchPackNote(deliveryNote: "AR1", trolley: "T-02", items: 3, packed: 3).chip == Chip("Packed", .success))
        #expect(DispatchPackNote(deliveryNote: "AR2", trolley: "T-02", items: 5, packed: 2).chip == Chip("Packing", .info))
        #expect(DispatchPackNote(deliveryNote: "AR3", trolley: "T-04", items: 2).chip == nil)
    }

    @Test("Sessions filter by kind and shippers describe today's shipments")
    func filtersAndShippers() {
        let store = DispatchStore()

        #expect(store.sessions(of: .dropshipping).count == 4)
        #expect(store.sessions(of: .fulfilment).isEmpty)
        #expect(store.shippers.map(\.subtitle) == ["48 shipments today", "21 shipments today", "No shipments today"])
    }

    @Test("Picked bays describe what they hold")
    func bays() {
        let store = DispatchStore()

        #expect(store.bays.map(\.subtitle) == [nil, "2 delivery notes · 1 waiting", "1 delivery note"])
        #expect(store.bays[0].chip == Chip("Free", .success))
    }
}
