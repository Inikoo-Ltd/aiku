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

struct GoodsOutWriteResult: Equatable, Sendable {
    var outcome: GoodsOutActionOutcome
    var fieldErrors: [String: String] = [:]
    var warning: String?
    var status: String?
    var item: GoodsOutActionItemDTO?

    var isDone: Bool { outcome.isDone }

    var failureMessage: String? {
        if case .failed(let message) = outcome, !message.isEmpty { return message }
        return nil
    }
}

enum GoodsOutPackScanResult: Equatable, Sendable {
    case packed(code: String, message: String?, lineDone: Bool)
    case refused(message: String)
}

extension GoodsOutStore {
    func write(_ action: GoodsOutNoteAction, on reference: String) async -> GoodsOutWriteResult {
        guard isRemote else { return GoodsOutWriteResult(outcome: .failed(message: "Sign in and choose a warehouse to work on delivery notes.")) }
        guard let id = noteID(for: reference) else { return GoodsOutWriteResult(outcome: .failed(message: "This delivery note is not in the selected warehouse.")) }
        guard !isWorking(on: reference) else { return GoodsOutWriteResult(outcome: .failed(message: "Still working on the last action.")) }
        setWorking(reference, true)
        defer { setWorking(reference, false) }
        do {
            let response = try await (service.write ?? service.perform)(id, action)
            store(response.data)
            let message = response.result?.message.flatMap(GoodsOutMapper.nonEmpty)
            return GoodsOutWriteResult(
                outcome: response.isNothingChanged ? .nothingChanged(message: message) : .done(message: message),
                warning: response.result?.warning.flatMap(GoodsOutMapper.nonEmpty),
                status: response.result?.status,
                item: response.result?.item
            )
        } catch let formError as FormValidationError {
            return GoodsOutWriteResult(outcome: .failed(message: formError.message), fieldErrors: formError.fields)
        } catch {
            if error.isCancellation { return GoodsOutWriteResult(outcome: .failed(message: "")) }
            await reloadIfPlatformUnanswered(action, error: error, noteID: id)
            return GoodsOutWriteResult(outcome: .failed(message: GoodsOutMapper.message(for: error)))
        }
    }

    func scanToPack(_ reference: String, barcode: String, quantity: Double? = 1, lineID: Int? = nil) async -> GoodsOutPackScanResult {
        let code = barcode.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !code.isEmpty else { return .refused(message: "Scan a barcode first.") }
        let result = await write(.scanPack(barcode: code, quantity: quantity, lineID: lineID), on: reference)
        if let message = result.failureMessage { return .refused(message: message) }
        let message = {
            if case .done(let text) = result.outcome { return text }
            if case .nothingChanged(let text) = result.outcome { return text }
            return nil
        }()
        let sko = result.item?.code.flatMap(GoodsOutMapper.nonEmpty) ?? code
        switch result.status {
        case "packed":
            return .packed(code: sko, message: message, lineDone: (result.item?.quantityToPack ?? 1) <= 0)
        case "already_packed":
            return .refused(message: message ?? "\(sko) is already packed")
        case "nothing_to_pack":
            return .refused(message: message ?? "Nothing is left to pack on this delivery note")
        case "wrong_state":
            return .refused(message: message ?? "\(reference) is not being packed")
        default:
            return .refused(message: message ?? "\(code) is not on this delivery note")
        }
    }

    func shipperList(for reference: String) async -> Result<GoodsOutShipperList, GoodsOutProblem> {
        guard isRemote, let id = noteID(for: reference) else { return .failure(GoodsOutProblem(message: "This delivery note is not in the selected warehouse.")) }
        guard let shippers = service.shippers else { return .failure(GoodsOutProblem(message: "Shippers can’t be loaded here.")) }
        do {
            return .success(GoodsOutMapper.shipperList(try await shippers(id)))
        } catch where error.isCancellation {
            return .failure(GoodsOutProblem(message: ""))
        } catch {
            return .failure(GoodsOutProblem(message: GoodsOutMapper.message(for: error)))
        }
    }

    func download(_ document: GoodsOutDocument, of reference: String, into folder: URL = FileManager.default.temporaryDirectory.appending(path: "aiku-documents")) async -> Result<URL, GoodsOutProblem> {
        guard isRemote, let id = noteID(for: reference) else { return .failure(GoodsOutProblem(message: "This delivery note is not in the selected warehouse.")) }
        guard let fetch = service.document else { return .failure(GoodsOutProblem(message: "Documents can’t be opened here.")) }
        do {
            let data = try await fetch(id, document)
            guard data.starts(with: Data("%PDF".utf8)) else {
                return .failure(GoodsOutProblem(message: "Aiku sent something that is not a PDF."))
            }
            try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
            let file = folder.appending(path: document.fileName(reference: reference))
            try data.write(to: file, options: .atomic)
            return .success(file)
        } catch where error.isCancellation {
            return .failure(GoodsOutProblem(message: ""))
        } catch {
            return .failure(GoodsOutProblem(message: GoodsOutMapper.message(for: error)))
        }
    }
}
