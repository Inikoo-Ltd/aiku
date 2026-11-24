import Foundation

extension GoodsOutDeliveryNote {
    var isCollection: Bool { shipper == .collection }
    var shipsViaPlatform: Bool { channel == .marketplaces && platform != nil }
    var needsShipment: Bool { !isCollection }

    var packableLines: [GoodsOutLine] { linesByLocation.filter(\.isPackable) }
    var packableUnits: Int { packableLines.reduce(0) { $0 + $1.picked } }
    var packedUnits: Int { packableLines.reduce(0) { $0 + $1.packed } }
    @MainActor
    var packedProgress: String { L10n.format("%lld of %lld packed", packedUnits, packableUnits) }
    var linesStillToPack: Int { packableLines.filter { !$0.isFullyPacked }.count }
    var allLinesPacked: Bool { !packableLines.isEmpty && linesStillToPack == 0 }
    var nextLineToPack: GoodsOutLine? { packableLines.first { !$0.isFullyPacked } }
    var currentParcel: GoodsOutParcel? { parcels.last }
    var parcelsMissingDimensions: [GoodsOutParcel] { parcels.filter { !$0.hasDimensions } }
    var parcelWeightKg: Double { GoodsOutWeight.rounded(parcels.reduce(0) { $0 + $1.weightKg }) }
    var boxSuggestion: GoodsOutBoxSuggestion { GoodsOutBox.suggestion(forWeight: weightKg) }
    var unprintedLeaflets: [GoodsOutLeaflet] { leaflets.filter { !$0.isPrinted } }
    var openTasks: Int { tasks.filter(\.isOpen).count }

    @MainActor
    var shipperTitle: String {
        if let shipment = shipments.first { return shipment.shipper }
        if shipsViaPlatform, let platform { return platform }
        return L10n.text(shipper?.title ?? "No shipper")
    }

    @MainActor
    var parcelCountText: String {
        let count = max(parcels.count, 1)
        return isCollection ? L10n.format("%lld parcels", count) : L10n.format("%lld boxes", count)
    }

    var shippedWeightText: String { GoodsOutWeight.text(parcels.isEmpty ? weightKg : parcelWeightKg) }

    @MainActor
    var dispatchSummary: String {
        var parts = [shipperTitle, parcelCountText, shippedWeightText]
        if shipments.allSatisfy(\.labelPrinted), unprintedLeaflets.isEmpty {
            parts.append(leaflets.isEmpty ? L10n.text("labels printed") : L10n.text("labels and leaflets printed"))
        }
        return parts.joined(separator: " · ")
    }

    var dispatchedUnits: Int { packedUnits > 0 ? packedUnits : pickedUnits }

    @MainActor
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
        return L10n.format("%lld not picked · %@", units, reasons.joined(separator: ", "))
    }

    @MainActor
    var packingBlocker: String? {
        guard state == .packing else { return L10n.text("Only a delivery note being packed can be set as packed.") }
        let left = linesStillToPack
        if left > 0 { return L10n.format("%lld lines still to pack", left) }
        if parcels.isEmpty { return L10n.text("Add a parcel") }
        if let parcel = parcelsMissingDimensions.first { return L10n.format("Enter the dimensions of %@", parcel.title) }
        return nil
    }

    var canSetAsPacked: Bool {
        state == .packing && linesStillToPack == 0 && !parcels.isEmpty && parcelsMissingDimensions.isEmpty
    }

    @MainActor
    var finaliseBlocker: String? {
        guard state == .packed else { return L10n.text("Only a packed delivery note can be finalised.") }
        if needsShipment, shipments.isEmpty {
            if shipsViaPlatform, let platform { return L10n.format("Get the shipment from %@ first", platform) }
            return L10n.text("Add a shipment before Finalise")
        }
        if !unprintedLeaflets.isEmpty { return L10n.text("Print every insert before continuing") }
        return nil
    }

    var canFinalise: Bool {
        state == .packed && !(needsShipment && shipments.isEmpty) && unprintedLeaflets.isEmpty
    }
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

    @MainActor
    @discardableResult
    mutating func addTask(title: String, assignee: String) -> Bool {
        let trimmed = title.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return false }
        let id = (tasks.map(\.id).max() ?? 0) + 1
        tasks.append(GoodsOutTask(id: id, title: L10n.format("Task: %@", trimmed), assignee: assignee))
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

enum GoodsOutFooterItem: Hashable, Sendable {
    case button(GoodsOutAvailableAction, isPrimary: Bool)
    case notice(GoodsOutAvailableAction)
}

extension GoodsOutDeliveryNote {
    static let packingFooterKeys: [GoodsOutActionKey] = [
        .setAsPacked, .startPacking, .addShipment, .getExternalShipment, .finaliseAndDispatch, .finalise, .dispatch, .takeOver
    ]

    var packer: String? { packingDetails?.packerName }

    var remoteFooterActions: [GoodsOutAvailableAction] {
        Self.packingFooterKeys.compactMap { key in
            guard let action = action(key) else { return nil }
            if key == .addShipment, !shipments.isEmpty { return nil }
            if key == .takeOver, state == .packed { return nil }
            return action
        }
    }

    var remoteFooterItems: [GoodsOutFooterItem] {
        let actions = remoteFooterActions
        let primary = actions.first { $0.disabledReason == nil }
        return actions.map { action in
            action.disabledReason == nil ? .button(action, isPrimary: action == primary) : .notice(action)
        }
    }

    var addAnotherShipment: GoodsOutAvailableAction? {
        shipments.isEmpty ? nil : action(.addShipment)
    }

    var canPackLines: Bool {
        guard let remote else { return state == .packing }
        return (state == .packing || state == .packed) && remote.allowActions
    }

    var canScanToPack: Bool {
        guard let remote else { return state == .packing }
        return state == .packing && remote.allowActions && remote.settings.allowScanToPack && !remote.isDropshipping
    }

    var isShippingByExternal: Bool {
        if let packingDetails { return packingDetails.isShippingByExternal }
        return shipsViaPlatform
    }

    var externalEngine: String? {
        packingDetails?.externalEngine ?? platform
    }

    var documents: [GoodsOutDocument] {
        var list: [GoodsOutDocument] = [.deliveryNote]
        if packingDetails?.hasBoxPackingList == true { list.append(.packingList) }
        if packingDetails?.hasGiftMessage == true { list.append(.giftMessage) }
        return list
    }

    @MainActor
    var remoteFooterStatus: (title: String, detail: String) {
        let stateTitle = remote?.stateLabel.isEmpty == false ? remote?.stateLabel ?? state.title : state.title
        if let notice = remote?.readOnlyNotice { return (stateTitle, notice) }
        if state == .packed, parcels.isEmpty { return (stateTitle, L10n.text("Add at least one parcel before this delivery note can be finalised.")) }
        return (stateTitle, L10n.text("There is nothing to do on this delivery note in the app right now."))
    }

    var packingFootNote: String? {
        switch state {
        case .packing where canPackLines:
            return canScanToPack
                ? "Scan to pack, or tap a line to pack part of it or unpack it. Every parcel needs its length, width and height before Set as packed."
                : "Tap a line to pack it, pack part of it or unpack it. Every parcel needs its length, width and height before Set as packed."
        case .packed, .finalised:
            if action(.finalise) != nil {
                return "Collection is set on the order. On a replacement delivery note it can be switched on the delivery note itself. Finalise for collection leaves it ready for the customer."
            }
            if action(.dispatch) != nil {
                return isCollection
                    ? "When the customer picks it up, set it as collected. That is the collection version of Dispatch."
                    : "Dispatch when the parcel leaves the warehouse."
            }
            return nil
        default:
            return nil
        }
    }
}

struct GoodsOutParcelDraft: Identifiable, Hashable, Sendable {
    let id: Int
    var weight: String
    var length: String
    var width: String
    var height: String
    var boxes: String = ""

    init(id: Int, weight: String = "", length: String = "", width: String = "", height: String = "", boxes: String = "") {
        self.id = id
        self.weight = weight
        self.length = length
        self.width = width
        self.height = height
        self.boxes = boxes
    }

    init(id: Int, parcel: GoodsOutParcel) {
        self.init(
            id: id,
            weight: parcel.weightKg > 0 ? GoodsOutLinePacking.text(parcel.weightKg) : "",
            length: parcel.length.map(String.init) ?? "",
            width: parcel.width.map(String.init) ?? "",
            height: parcel.height.map(String.init) ?? "",
            boxes: parcel.boxes.map(String.init) ?? ""
        )
    }

    static let maxWeightKg = 1000.0
    static let maxSizeCm = 1000.0
    static let maxParcels = 50

    static func number(_ text: String) -> Double? {
        Double(text.trimmingCharacters(in: .whitespaces).replacingOccurrences(of: ",", with: "."))
    }

    @MainActor
    var weightProblem: String? {
        guard let value = Self.number(weight) else { return L10n.text("Enter the weight.") }
        guard value > 0, value <= Self.maxWeightKg else { return L10n.text("The weight must be more than 0 and at most 1000 kg.") }
        return nil
    }

    @MainActor
    var sizeProblem: String? {
        let values = [length, width, height].map(Self.number)
        if values.contains(where: { $0 == nil }) { return L10n.text("Enter the length, width and height.") }
        if values.contains(where: { ($0 ?? 0) <= 0 || ($0 ?? 0) > Self.maxSizeCm }) { return L10n.text("Each size must be more than 0 and at most 1000 cm.") }
        return nil
    }

    @MainActor
    var boxesProblem: String? {
        let text = boxes.trimmingCharacters(in: .whitespaces)
        guard !text.isEmpty else { return nil }
        guard let value = Int(text), value >= 1 else { return L10n.text("Boxes must be a whole number of 1 or more.") }
        return nil
    }

    @MainActor
    var problem: String? { weightProblem ?? sizeProblem ?? boxesProblem }

    @MainActor
    var input: GoodsOutParcelInput? {
        guard problem == nil, let weightKg = Self.number(weight) else { return nil }
        let sizes = [length, width, height].compactMap(Self.number)
        return GoodsOutParcelInput(weightKg: weightKg, dimensionsCm: sizes, boxes: Int(boxes.trimmingCharacters(in: .whitespaces)))
    }

    @MainActor
    static func inputs(_ drafts: [GoodsOutParcelDraft]) -> Result<[GoodsOutParcelInput], GoodsOutProblem> {
        if drafts.isEmpty { return .failure(GoodsOutProblem(message: L10n.text("Add at least one parcel."))) }
        if drafts.count > maxParcels { return .failure(GoodsOutProblem(message: L10n.text("A delivery note can have at most 50 parcels."))) }
        if let index = drafts.firstIndex(where: { $0.problem != nil }), let problem = drafts[index].problem {
            return .failure(GoodsOutProblem(message: L10n.format("Parcel %lld: %@", index + 1, problem)))
        }
        return .success(drafts.compactMap(\.input))
    }
}

struct GoodsOutProblem: Error, Hashable, Sendable {
    let message: String
}

struct GoodsOutBoxDraft: Identifiable, Hashable, Sendable {
    let id: Int
    var box: String
    var quantity: String

    static let maxBox = 999

    @MainActor
    static func inputs(_ drafts: [GoodsOutBoxDraft], picked: Double) -> Result<[GoodsOutLineBoxInput], GoodsOutProblem> {
        var boxes: [GoodsOutLineBoxInput] = []
        for draft in drafts {
            guard let box = Int(draft.box.trimmingCharacters(in: .whitespaces)), (1...maxBox).contains(box) else {
                return .failure(GoodsOutProblem(message: L10n.format("Each box number must be from 1 to %lld.", maxBox)))
            }
            guard let quantity = GoodsOutParcelDraft.number(draft.quantity), quantity >= 0 else {
                return .failure(GoodsOutProblem(message: L10n.text("Enter a quantity of 0 or more for each box.")))
            }
            boxes.append(GoodsOutLineBoxInput(box: box, quantity: quantity))
        }
        if boxes.reduce(0, { $0 + $1.quantity }) > picked + 0.0005 {
            return .failure(GoodsOutProblem(message: L10n.format("The boxes hold more than the %@ picked.", GoodsOutLinePacking.text(picked))))
        }
        return .success(boxes)
    }
}

enum GoodsOutShipmentRequest: Hashable, Sendable {
    case add(shipperID: Int, tracking: String?, overrideLock: Bool)
    case retry(shipperID: Int, address: GoodsOutAddressForm, overrideLock: Bool)

    var action: GoodsOutNoteAction {
        switch self {
        case .add(let shipperID, let tracking, let overrideLock):
            .addShipment(shipperID: shipperID, tracking: tracking, overrideLock: overrideLock)
        case .retry(let shipperID, let address, let overrideLock):
            .retryShipment(shipperID: shipperID, address: address, overrideLock: overrideLock)
        }
    }

    var shipperID: Int {
        switch self {
        case .add(let shipperID, _, _), .retry(let shipperID, _, _): shipperID
        }
    }

    var overrideLock: Bool {
        switch self {
        case .add(_, _, let overrideLock), .retry(_, _, let overrideLock): overrideLock
        }
    }

    var overridingLock: GoodsOutShipmentRequest {
        switch self {
        case .add(let shipperID, let tracking, _): .add(shipperID: shipperID, tracking: tracking, overrideLock: true)
        case .retry(let shipperID, let address, _): .retry(shipperID: shipperID, address: address, overrideLock: true)
        }
    }
}

enum GoodsOutShipmentFollowUp: Hashable, Sendable, Identifiable {
    case confirmOverride(message: String, request: GoodsOutShipmentRequest)
    case fixAddress(message: String, shipperID: Int, overrideLock: Bool)

    var id: String {
        switch self {
        case .confirmOverride: "override"
        case .fixAddress: "address"
        }
    }

    static func after(_ request: GoodsOutShipmentRequest, errors: [String: String]) -> GoodsOutShipmentFollowUp? {
        let override = errors["override_lock"]
        let address = errors["address"] ?? errors.first { $0.key.hasPrefix("address.") }?.value
        switch request {
        case .add:
            if let override { return .confirmOverride(message: override, request: request) }
            if let address { return .fixAddress(message: address, shipperID: request.shipperID, overrideLock: request.overrideLock) }
            return nil
        case .retry:
            if let override { return .confirmOverride(message: override, request: request) }
            guard let message = address ?? errors.sorted(by: { $0.key < $1.key }).first?.value else { return nil }
            return .fixAddress(message: message, shipperID: request.shipperID, overrideLock: request.overrideLock)
        }
    }
}
