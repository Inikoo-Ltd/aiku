import Foundation
import Testing
@testable import Aiku

struct GoodsOutDispatchTests {
    private let now = GoodsOutDateFormat.date(day: 10, hour: 12, minute: 0)

    private func sample(_ reference: String) -> GoodsOutDeliveryNote {
        GoodsOutSampleData.notes.first { $0.reference == reference }!
    }

    @Test func everyLaterSampleNoteHasParcelsAndShippedNotesHaveShipments() {
        for note in GoodsOutSampleData.notes where note.state.isLaterState {
            #expect(!note.parcels.isEmpty, "\(note.reference) has no parcels")
            if note.state == .finalised || note.state == .dispatched, note.needsShipment {
                #expect(!note.shipments.isEmpty, "\(note.reference) has no shipment")
            }
        }
        #expect(GoodsOutSampleData.notes.contains { $0.state == .packed && $0.isCollection })
        #expect(GoodsOutSampleData.notes.contains { $0.state == .packed && $0.shipsViaPlatform })
        #expect(GoodsOutSampleData.notes.contains { $0.state == .packed && $0.needsShipment && !$0.shipsViaPlatform })
    }

    @Test func packingNoteShowsWhatIsStillToPack() {
        let note = sample("AR24821")
        #expect(note.packedProgress == "6 of 12 packed")
        #expect(note.nextLineToPack?.sko == "SKO-00733")
        #expect(note.line("SKO-00412")?.parcelText == "Parcel 1")
        #expect(note.line("SKO-01187")?.parcelText == "no parcel yet")
        #expect(note.packingBlocker == "2 lines still to pack")
        #expect(note.canSetAsPacked == false)
    }

    @Test func packingNeverPacksMoreThanWasPicked() {
        var note = sample("AR24821")
        let packed = note.pack("SKO-00733", quantity: 99, into: 1)
        #expect(packed)
        #expect(note.line("SKO-00733")?.packed == 6)
        let packedAgain = note.pack("SKO-00733", quantity: 1, into: 1)
        #expect(packedAgain == false)
        let intoMissingParcel = note.pack("SKO-01187", quantity: 1, into: 9)
        #expect(intoMissingParcel == false)
    }

    @Test func setAsPackedNeedsEveryLinePackedAndEveryDimension() {
        var note = sample("AR24821")
        note.packAll("SKO-00733", into: 1)
        note.packAll("SKO-01187", into: 2)
        #expect(note.allLinesPacked)
        #expect(note.packingBlocker == "Enter the dimensions of Parcel 2")
        let packedEarly = note.setAsPacked(at: now)
        #expect(packedEarly == false)

        note.updateParcel(2) { parcel in
            parcel.length = 30
            parcel.width = 20
            parcel.height = 15
        }
        let packed = note.setAsPacked(at: now)
        #expect(packed)
        #expect(note.state == .packed)
        #expect(note.undoStep == .unpack)
    }

    @Test func everyPackScanCountsOneAndOpensAParcelWhenNoneExists() {
        var note = sample("AR24813")
        note.startPacking(at: now)
        #expect(note.parcels.isEmpty)

        let first = note.registerPackScan()
        #expect(note.parcels.count == 1)
        #expect(first == .packed(sko: "SKO-00412", left: 3, parcel: 1))
        #expect(note.packedUnits == 1)

        let wrong = note.registerPackScan(code: "SKO-01402")
        #expect(wrong == .notOnNote(code: "SKO-01402"))
        #expect(note.packedUnits == 1)
    }

    @Test func notPickedLinesAreNotPacked() {
        var note = sample("AR24813")
        note.startPacking(at: now)
        #expect(note.packableLines.map(\.sko).contains("SKO-00953") == false)
        #expect(note.packableUnits == 12)
    }

    @Test func boxSuggestionFollowsTheWeight() {
        #expect(GoodsOutBox.suggestion(forWeight: 0.3).title == "Envelope")
        #expect(GoodsOutBox.suggestion(forWeight: 1.2).title == "Box S")
        #expect(GoodsOutBox.suggestion(forWeight: 6.2).title == "Box M")
        #expect(GoodsOutBox.suggestion(forWeight: 12.4).title == "2 × Box L")
    }

    @Test func quickPackFromTheQueuePacksIntoTheSuggestedBox() {
        var note = sample("AR24790")
        let packed = note.quickPack(at: now)
        #expect(packed)
        #expect(note.state == .packed)
        #expect(note.allLinesPacked)
        #expect(note.parcels.count == 1)
        #expect(note.parcels.first?.dimensionsText == "25 × 20 × 10 cm")

        var blocked = sample("AR24842")
        let blockedOnce = blocked.blockHandling()
        let blockedTwice = blocked.blockHandling()
        #expect(blockedOnce)
        #expect(blocked.handlingBlocked)
        #expect(blockedTwice == false)
    }

    @Test func finaliseNeedsAShipmentAndEveryLeafletPrinted() {
        var note = sample("AR24835")
        #expect(note.finaliseBlocker == "Add a shipment before Finalise")
        let finalisedEarly = note.finalise(at: now)
        #expect(finalisedEarly == false)

        let manualWithoutTracking = note.addShipment(shipper: "[Local courier]", tracking: "  ", source: .manual)
        #expect(manualWithoutTracking == false)
        let api = note.addShipment(shipper: "DPD (GB)", tracking: nil, source: .api, cost: "[£ 6.20]")
        #expect(api)
        #expect(note.shipments.first?.tracking == "TRK-4835-5678")
        #expect(note.shipments.first?.parcelCount == 2)

        #expect(note.finaliseBlocker == "Print every insert before continuing")
        note.printAllLeaflets()
        #expect(note.canFinalise)
        let finalised = note.finalise(at: now)
        #expect(finalised)
        #expect(note.state == .finalised)
        #expect(note.shipments.allSatisfy { $0.labelPrinted })
        #expect(note.dispatchSummary == "DPD (GB) · 2 boxes · 5.5 kg · labels and leaflets printed")
        #expect(note.notPickedSummary == "2 not picked · out of stock")
    }

    @Test func dispatchAddsTheDispatchEmailAndCanBeUndone() {
        var note = sample("AR24831")
        #expect(note.canGenerateInvoice)
        let dispatched = note.dispatch(at: now)
        #expect(dispatched)
        #expect(note.state == .dispatched)
        #expect(note.emails.first?.title == "Dispatch confirmation")
        #expect(note.canCancel == false)

        #expect(note.undoStep == .dispatch)
        let undone = note.undo(.dispatch)
        #expect(undone)
        #expect(note.state == .finalised)
        #expect(note.stateDates[.dispatched] == nil)
    }

    @Test func undoPackingAndUnpackClearTheParcels() {
        var packing = sample("AR24821")
        let undidPacking = packing.undo(.packing)
        #expect(undidPacking)
        #expect(packing.state == .picked)
        #expect(packing.parcels.isEmpty)
        #expect(packing.packedUnits == 0)

        var packed = sample("AR24835")
        packed.addShipment(shipper: "DPD (GB)", tracking: nil, source: .api)
        let unpacked = packed.undo(.unpack)
        #expect(unpacked)
        #expect(packed.state == .packing)
        #expect(packed.shipments.isEmpty)
        #expect(packed.parcels.isEmpty)
    }

    @Test func collectionSkipsTheShipment() {
        var note = sample("AR24840")
        #expect(note.isCollection)
        #expect(note.needsShipment == false)
        let shipment = note.addShipment(shipper: "DPD (GB)", tracking: nil, source: .api)
        #expect(shipment == false)
        #expect(note.canFinalise)
        let finalised = note.finalise(at: now)
        #expect(finalised)
        let collected = note.dispatch(at: now)
        #expect(collected)
        #expect(note.state == .dispatched)
        #expect(note.emails.first?.title == "Collection confirmation")
    }

    @Test func marketplaceNotesWaitForThePlatformShipment() {
        let note = sample("AR24822")
        #expect(note.shipsViaPlatform)
        #expect(note.finaliseBlocker == "Get the shipment from TikTok first")
    }

    @MainActor
    @Test func storeGetsThePlatformShipmentAndRestoresOnUndo() {
        let store = GoodsOutStore(now: { GoodsOutDateFormat.date(day: 10, hour: 12, minute: 0) })
        let previous = store.getPlatformShipment("AR24822")
        #expect(store.note("AR24822")?.shipments.first?.shipper == "TikTok")
        #expect(store.note("AR24822")?.canFinalise == true)
        store.restore(previous!)
        #expect(store.note("AR24822")?.shipments.isEmpty == true)
    }

    @MainActor
    @Test func onlyUsersWithRightsCanCancelAndNeverADispatchedNote() {
        let blocked = GoodsOutStore(rights: GoodsOutRights(isDispatchingSupervisor: false, canEditOrders: false))
        #expect(blocked.cancel("AR24811") == nil)
        #expect(blocked.note("AR24811")?.state == .picking)

        let store = GoodsOutStore()
        #expect(store.cancel("AR24801") == nil)
        #expect(store.cancel("AR24811") != nil)
        #expect(store.note("AR24811")?.state == .cancelled)
        #expect(store.note("AR24811")?.trolley == nil)
    }

    @MainActor
    @Test func packingQueueListsPickedNotesAndManualShipmentsKeepTheTypedTracking() {
        let store = GoodsOutStore()
        #expect(Set(store.packingQueue.map(\.reference)) == ["AR24813", "AR24790", "AR24842"])
        let courier = store.otherShipperOptions.first { !$0.hasAPI }!
        #expect(store.addShipment("AR24835", shipper: courier, tracking: "") == nil)
        #expect(store.addShipment("AR24835", shipper: courier, tracking: " TRK-0099-1234 ") != nil)
        #expect(store.note("AR24835")?.shipments.last?.tracking == "TRK-0099-1234")
        #expect(store.note("AR24835")?.shipments.last?.source == .manual)
    }

    @MainActor
    @Test func chatTasksAndAddressStayOnTheNote() {
        let store = GoodsOutStore()
        #expect(store.sendMessage("AR24815", text: "   ") == nil)
        store.sendMessage("AR24815", text: "Found the second pack")
        #expect(store.note("AR24815")?.messages.last?.isMine == true)
        store.addTask("AR24815", title: "check overstock")
        #expect(store.note("AR24815")?.tasks.last?.title == "Task: check overstock")

        var address = GoodsOutAddress.sample
        address.street = "1 High Street"
        store.updateAddress("AR24835", address: address, contactName: "Sam", phone: "", email: "")
        #expect(store.note("AR24835")?.address.hasPrefix("1 High Street") == true)
        #expect(store.updateAddress("AR24801", address: address, contactName: "Sam", phone: "", email: "") == nil)
    }

    @Test func customsRowsFlagMissingTradeUnits() {
        let rows = GoodsOutCustoms.rows(for: sample("AR24821"), tradeUnits: GoodsOutSampleData.tradeUnits)
        #expect(rows.map(\.sko) == ["SKO-00412", "SKO-00733", "SKO-01187"])
        #expect(GoodsOutCustoms.missingCodes(rows) == ["SKO-01187"])
        #expect(rows[0].tradeUnitText == "Tariff 3301 29 · Origin GB · Lavender essential oil 10ml")
        #expect(rows[2].tradeUnitText == "no tariff code · Origin CN")
    }

    @Test func incompleteSetAsksToPutThePickedPartsBack() {
        var note = sample("AR24826")
        #expect(note.incompleteSet?.instruction == "Put back SKO-00733 and SKO-00412 on the shelf, then press Parts put back.")
        #expect(note.incompleteSet?.actionTitle == "Parts put back")
        let putBack = note.partsPutBack()
        #expect(putBack)
        #expect(note.incompleteSet == nil)
        #expect(note.state == .waiting)
    }
}
