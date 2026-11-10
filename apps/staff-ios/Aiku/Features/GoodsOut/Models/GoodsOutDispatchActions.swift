import Foundation

extension GoodsOutStore {
    var packingQueue: [GoodsOutDeliveryNote] { notes.filter { $0.state == .picked } }
    var shipperOptions: [GoodsOutShipperOption] { GoodsOutSampleData.apiShippers }
    var otherShipperOptions: [GoodsOutShipperOption] { GoodsOutSampleData.otherShippers }
    var printers: [GoodsOutPrinter] { GoodsOutSampleData.printers }

    func nextNoteToDispatch(after reference: String) -> GoodsOutDeliveryNote? {
        let order: [GoodsOutDeliveryNoteState] = [.finalised, .packed, .packing, .picked]
        for state in order {
            if let next = notes.first(where: { $0.state == state && $0.reference != reference }) {
                return next
            }
        }
        return nil
    }

    @discardableResult
    func addParcel(_ reference: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.addParcel() }
    }

    @discardableResult
    func updateParcel(_ reference: String, parcel: Int, _ change: @escaping (inout GoodsOutParcel) -> Void) -> GoodsOutDeliveryNote? {
        update(reference) { $0.updateParcel(parcel, change) }
    }

    @discardableResult
    func pack(_ reference: String, line: GoodsOutLine.ID, quantity: Int, into parcel: Int) -> GoodsOutDeliveryNote? {
        update(reference) { $0.pack(line, quantity: quantity, into: parcel) }
    }

    @discardableResult
    func packIntoNewParcel(_ reference: String, line: GoodsOutLine.ID, quantity: Int) -> GoodsOutDeliveryNote? {
        update(reference) { note in
            guard note.addParcel(), let parcel = note.currentParcel?.number else { return false }
            return note.pack(line, quantity: quantity, into: parcel)
        }
    }

    func scanToPack(_ reference: String, code: String? = nil) -> GoodsOutPackScanOutcome {
        var outcome = GoodsOutPackScanOutcome.notPacking
        update(reference) { note in
            outcome = note.registerPackScan(code: code)
            if case .packed = outcome { return true }
            return false
        }
        return outcome
    }

    @discardableResult
    func setAsPacked(_ reference: String) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.setAsPacked(at: date) }
    }

    @discardableResult
    func quickPack(_ reference: String) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.quickPack(at: date) }
    }

    @discardableResult
    func blockHandling(_ reference: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.blockHandling() }
    }

    @discardableResult
    func addShipment(_ reference: String, shipper: GoodsOutShipperOption, tracking: String? = nil) -> GoodsOutDeliveryNote? {
        let source: GoodsOutShipmentSource = shipper.hasAPI ? .api : .manual
        let cost = shipper.hasAPI ? "[£ 6.20]" : nil
        return update(reference) { $0.addShipment(shipper: shipper.title, tracking: shipper.hasAPI ? nil : tracking, source: source, cost: cost) }
    }

    @discardableResult
    func getPlatformShipment(_ reference: String) -> GoodsOutDeliveryNote? {
        update(reference) { note in
            guard note.shipsViaPlatform, let platform = note.platform else { return false }
            return note.addShipment(shipper: platform, tracking: nil, source: .platform)
        }
    }

    @discardableResult
    func deleteShipment(_ reference: String, shipment: Int) -> GoodsOutDeliveryNote? {
        update(reference) { $0.deleteShipment(shipment) }
    }

    @discardableResult
    func printLeaflet(_ reference: String, leaflet: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.printLeaflet(leaflet) }
    }

    @discardableResult
    func printAllLeaflets(_ reference: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.printAllLeaflets() }
    }

    @discardableResult
    func finalise(_ reference: String) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.finalise(at: date) }
    }

    @discardableResult
    func dispatch(_ reference: String) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.dispatch(at: date) }
    }

    @discardableResult
    func finaliseAndDispatch(_ reference: String) -> GoodsOutDeliveryNote? {
        let date = now()
        return update(reference) { $0.finaliseAndDispatch(at: date) }
    }

    @discardableResult
    func generateInvoice(_ reference: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.generateInvoice() }
    }

    @discardableResult
    func cancel(_ reference: String) -> GoodsOutDeliveryNote? {
        guard rights.canCancelNotes else { return nil }
        let date = now()
        return update(reference) { $0.cancel(at: date) }
    }

    @discardableResult
    func updateAddress(_ reference: String, address: GoodsOutAddress, contactName: String, phone: String, email: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.updateAddress(address, contactName: contactName, phone: phone, email: email) }
    }

    @discardableResult
    func sendMessage(_ reference: String, text: String) -> GoodsOutDeliveryNote? {
        let byline = "\(currentUser) (you)", date = now()
        return update(reference) { $0.sendMessage(text, by: byline, at: date) }
    }

    @discardableResult
    func addTask(_ reference: String, title: String) -> GoodsOutDeliveryNote? {
        let user = currentUser
        return update(reference) { $0.addTask(title: title, assignee: user) }
    }

    @discardableResult
    func toggleTask(_ reference: String, task: Int) -> GoodsOutDeliveryNote? {
        update(reference) { $0.toggleTask(task) }
    }

    @discardableResult
    func updateTradeUnit(_ sko: String, tariff: String, origin: String) -> Bool {
        let tariffCode = tariff.trimmingCharacters(in: .whitespacesAndNewlines)
        let country = origin.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
        guard !tariffCode.isEmpty, country.count == 2 else { return false }
        var unit = tradeUnits[sko] ?? GoodsOutTradeUnit()
        unit.tariff = tariffCode
        unit.origin = country
        tradeUnits[sko] = unit
        return true
    }

    @discardableResult
    func partsPutBack(_ reference: String) -> GoodsOutDeliveryNote? {
        update(reference) { $0.partsPutBack() }
    }
}
