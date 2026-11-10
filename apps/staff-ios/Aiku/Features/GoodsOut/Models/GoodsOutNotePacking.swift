import Foundation

extension GoodsOutDeliveryNote {
    var isCollection: Bool { shipper == .collection }
    var shipsViaPlatform: Bool { channel == .marketplaces && platform != nil }
    var needsShipment: Bool { !isCollection }

    var packableLines: [GoodsOutLine] { linesByLocation.filter(\.isPackable) }
    var packableUnits: Int { packableLines.reduce(0) { $0 + $1.picked } }
    var packedUnits: Int { packableLines.reduce(0) { $0 + $1.packed } }
    var packedProgress: String { "\(packedUnits) of \(packableUnits) packed" }
    var linesStillToPack: Int { packableLines.filter { !$0.isFullyPacked }.count }
    var allLinesPacked: Bool { !packableLines.isEmpty && linesStillToPack == 0 }
    var nextLineToPack: GoodsOutLine? { packableLines.first { !$0.isFullyPacked } }
    var currentParcel: GoodsOutParcel? { parcels.last }
    var parcelsMissingDimensions: [GoodsOutParcel] { parcels.filter { !$0.hasDimensions } }
    var parcelWeightKg: Double { GoodsOutWeight.rounded(parcels.reduce(0) { $0 + $1.weightKg }) }
    var boxSuggestion: GoodsOutBoxSuggestion { GoodsOutBox.suggestion(forWeight: weightKg) }
    var unprintedLeaflets: [GoodsOutLeaflet] { leaflets.filter { !$0.isPrinted } }
    var openTasks: Int { tasks.filter(\.isOpen).count }

    var shipperTitle: String {
        if let shipment = shipments.first { return shipment.shipper }
        if shipsViaPlatform, let platform { return platform }
        return shipper?.title ?? "No shipper"
    }

    var parcelCountText: String {
        let count = max(parcels.count, 1)
        if isCollection { return "\(count) \(count == 1 ? "parcel" : "parcels")" }
        return "\(count) \(count == 1 ? "box" : "boxes")"
    }

    var shippedWeightText: String { GoodsOutWeight.text(parcels.isEmpty ? weightKg : parcelWeightKg) }

    var dispatchSummary: String {
        var parts = [shipperTitle, parcelCountText, shippedWeightText]
        if shipments.allSatisfy(\.labelPrinted), unprintedLeaflets.isEmpty {
            parts.append(leaflets.isEmpty ? "labels printed" : "labels and leaflets printed")
        }
        return parts.joined(separator: " · ")
    }

    var dispatchedUnits: Int { packedUnits > 0 ? packedUnits : pickedUnits }

    var notPickedSummary: String? {
        var units = 0
        var reasons: [String] = []
        for line in lines {
            guard case .notPicked(let reason) = line.outcome else { continue }
            units += line.required - line.picked
            let title = reason.title.lowercased()
            if !reasons.contains(title) {
                reasons.append(title)
            }
        }
        guard units > 0 else { return nil }
        return "\(units) not picked · \(reasons.joined(separator: ", "))"
    }

    var packingBlocker: String? {
        guard state == .packing else { return "Only a delivery note being packed can be set as packed." }
        let left = linesStillToPack
        if left > 0 { return "\(left) \(left == 1 ? "line" : "lines") still to pack" }
        if parcels.isEmpty { return "Add a parcel" }
        if let parcel = parcelsMissingDimensions.first { return "Enter the dimensions of \(parcel.title)" }
        return nil
    }

    var canSetAsPacked: Bool { packingBlocker == nil }

    var finaliseBlocker: String? {
        guard state == .packed else { return "Only a packed delivery note can be finalised." }
        if needsShipment, shipments.isEmpty {
            if shipsViaPlatform, let platform { return "Get the shipment from \(platform) first" }
            return "Add a shipment before Finalise"
        }
        if !unprintedLeaflets.isEmpty { return "Print every insert before continuing" }
        return nil
    }

    var canFinalise: Bool { finaliseBlocker == nil }
    var canGenerateInvoice: Bool { (state == .finalised || state == .dispatched) && !invoiceGenerated }
    var canCancel: Bool { state != .dispatched && state != .cancelled }
    var canEditAddress: Bool { state != .dispatched && state != .cancelled }

    static func generatedTracking(reference: String, index: Int) -> String {
        let digits = reference.filter(\.isNumber)
        let prefix = String(digits.suffix(4)).padding(toLength: 4, withPad: "0", startingAt: 0)
        return "TRK-\(prefix)-\(5678 + index * 1111)"
    }

    @discardableResult
    mutating func addParcel() -> Bool {
        guard state == .packing else { return false }
        let number = (parcels.map(\.number).max() ?? 0) + 1
        let remaining = GoodsOutWeight.rounded(max(0.1, weightKg - parcelWeightKg))
        parcels.append(GoodsOutParcel(number: number, weightKg: remaining))
        return true
    }

    @discardableResult
    mutating func updateParcel(_ number: Int, _ change: (inout GoodsOutParcel) -> Void) -> Bool {
        guard state == .packing, let index = parcels.firstIndex(where: { $0.number == number }) else { return false }
        change(&parcels[index])
        return true
    }

    @discardableResult
    mutating func pack(_ id: GoodsOutLine.ID, quantity: Int, into parcel: Int) -> Bool {
        guard state == .packing,
              parcels.contains(where: { $0.number == parcel }),
              let index = lines.firstIndex(where: { $0.id == id })
        else { return false }
        return lines[index].pack(quantity, into: parcel) > 0
    }

    @discardableResult
    mutating func packAll(_ id: GoodsOutLine.ID, into parcel: Int) -> Bool {
        guard let line = line(id) else { return false }
        return pack(id, quantity: line.packRemaining, into: parcel)
    }

    mutating func registerPackScan(code: String? = nil) -> GoodsOutPackScanOutcome {
        guard state == .packing else { return .notPacking }
        let target: GoodsOutLine?
        if let code {
            let normalised = code.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
            let matching = packableLines.filter { $0.sko.uppercased() == normalised }
            guard !matching.isEmpty else { return .notOnNote(code: normalised) }
            target = matching.first { !$0.isFullyPacked }
        } else {
            target = nextLineToPack
        }
        guard let target else { return .allPacked }
        if parcels.isEmpty {
            addParcel()
        }
        guard let parcel = currentParcel?.number, pack(target.id, quantity: 1, into: parcel) else { return .notPacking }
        return .packed(sko: target.sko, left: line(target.id)?.packRemaining ?? 0, parcel: parcel)
    }

    @discardableResult
    mutating func setAsPacked(at date: Date) -> Bool {
        guard canSetAsPacked else { return false }
        state = .packed
        stateDates[.packed] = date
        return true
    }

    @discardableResult
    mutating func quickPack(at date: Date) -> Bool {
        guard state == .picked, pickedUnits > 0 else { return false }
        let suggestion = boxSuggestion
        let size = suggestion.box.dimensions
        let weight = GoodsOutWeight.rounded(max(0.1, weightKg / Double(suggestion.count)))
        parcels = (1...suggestion.count).map {
            GoodsOutParcel(number: $0, weightKg: weight, length: size.length, width: size.width, height: size.height)
        }
        for (offset, index) in lines.indices.enumerated() {
            lines[index].packedInto = [:]
            lines[index].pack(lines[index].picked, into: offset % suggestion.count + 1)
        }
        handlingBlocked = false
        stateDates[.packing] = date
        stateDates[.packed] = date
        state = .packed
        return true
    }

    @discardableResult
    mutating func blockHandling() -> Bool {
        guard state == .picked, !handlingBlocked else { return false }
        handlingBlocked = true
        return true
    }

    @discardableResult
    mutating func addShipment(shipper: String, tracking: String?, source: GoodsOutShipmentSource, cost: String? = nil) -> Bool {
        guard state == .packed, needsShipment else { return false }
        let typed = tracking?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        if source == .manual, typed.isEmpty { return false }
        let id = (shipments.map(\.id).max() ?? 0) + 1
        let number = typed.isEmpty ? Self.generatedTracking(reference: reference, index: shipments.count) : typed
        shipments.append(GoodsOutShipment(id: id, shipper: shipper, tracking: number, parcelCount: max(parcels.count, 1), cost: cost, source: source))
        return true
    }

    @discardableResult
    mutating func deleteShipment(_ id: Int) -> Bool {
        guard state == .packed, let index = shipments.firstIndex(where: { $0.id == id }) else { return false }
        shipments.remove(at: index)
        return true
    }

    @discardableResult
    mutating func printLeaflet(_ id: String) -> Bool {
        guard state == .packing || state == .packed,
              let index = leaflets.firstIndex(where: { $0.id == id }),
              !leaflets[index].isPrinted
        else { return false }
        leaflets[index].isPrinted = true
        return true
    }

    @discardableResult
    mutating func printAllLeaflets() -> Bool {
        guard state == .packing || state == .packed, !unprintedLeaflets.isEmpty else { return false }
        for index in leaflets.indices {
            leaflets[index].isPrinted = true
        }
        return true
    }

    @discardableResult
    mutating func finalise(at date: Date) -> Bool {
        guard canFinalise else { return false }
        for index in shipments.indices {
            shipments[index].labelPrinted = true
        }
        state = .finalised
        stateDates[.finalised] = date
        return true
    }

    @discardableResult
    mutating func dispatch(at date: Date) -> Bool {
        guard state == .finalised else { return false }
        state = .dispatched
        stateDates[.dispatched] = date
        let id = (emails.map(\.id).max() ?? 0) + 1
        emails.insert(GoodsOutEmail(id: id, title: isCollection ? "Collection confirmation" : "Dispatch confirmation", recipient: email, sentAt: date), at: 0)
        return true
    }

    @discardableResult
    mutating func finaliseAndDispatch(at date: Date) -> Bool {
        var updated = self
        guard updated.finalise(at: date), updated.dispatch(at: date) else { return false }
        self = updated
        return true
    }

    @discardableResult
    mutating func generateInvoice() -> Bool {
        guard canGenerateInvoice else { return false }
        invoiceGenerated = true
        return true
    }

    @discardableResult
    mutating func cancel(at date: Date) -> Bool {
        guard canCancel else { return false }
        state = .cancelled
        stateDates[.cancelled] = date
        trolley = nil
        return true
    }

    @discardableResult
    mutating func updateAddress(_ address: GoodsOutAddress, contactName name: String, phone newPhone: String, email newEmail: String) -> Bool {
        guard canEditAddress else { return false }
        let trimmed = name.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty, !address.street.trimmingCharacters(in: .whitespaces).isEmpty else { return false }
        deliveryAddress = address
        contactName = trimmed
        phone = newPhone.trimmingCharacters(in: .whitespacesAndNewlines)
        email = newEmail.trimmingCharacters(in: .whitespacesAndNewlines)
        return true
    }

    @discardableResult
    mutating func sendMessage(_ text: String, by byline: String, at date: Date) -> Bool {
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return false }
        let id = (messages.map(\.id).max() ?? 0) + 1
        messages.append(GoodsOutChatMessage(id: id, byline: byline, sentAt: date, text: trimmed, isMine: true))
        return true
    }

    @discardableResult
    mutating func addTask(title: String, assignee: String) -> Bool {
        let trimmed = title.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return false }
        let id = (tasks.map(\.id).max() ?? 0) + 1
        tasks.append(GoodsOutTask(id: id, title: "Task: \(trimmed)", assignee: assignee))
        return true
    }

    @discardableResult
    mutating func toggleTask(_ id: Int) -> Bool {
        guard let index = tasks.firstIndex(where: { $0.id == id }) else { return false }
        tasks[index].isOpen.toggle()
        return true
    }

    @discardableResult
    mutating func partsPutBack() -> Bool {
        guard state == .waiting, incompleteSet != nil else { return false }
        incompleteSet = nil
        return true
    }

    mutating func clearPacking() {
        for index in lines.indices {
            lines[index].packedInto = [:]
        }
        parcels = []
        shipments = []
    }
}
