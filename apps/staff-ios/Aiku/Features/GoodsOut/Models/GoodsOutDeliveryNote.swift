import Foundation

enum GoodsOutUndoStep: String, Hashable, Sendable, Identifiable {
    case setAsPicked
    case packing
    case unpack
    case dispatch

    var id: String { rawValue }

    var title: String {
        switch self {
        case .setAsPicked: "Undo set as picked"
        case .packing: "Undo packing"
        case .unpack: "Unpack"
        case .dispatch: "Undispatch"
        }
    }

    var detail: String {
        switch self {
        case .setAsPicked: "Go back to picking"
        case .packing: "Go back to picked"
        case .unpack: "Unpack the parcels"
        case .dispatch: "Back to finalised"
        }
    }

    var question: String { "\(title)?" }

    var confirmTitle: String {
        switch self {
        case .setAsPicked: "Go back to picking"
        case .packing: "Go back to picked"
        case .unpack: "Unpack"
        case .dispatch: "Back to finalised"
        }
    }

    var from: GoodsOutDeliveryNoteState {
        switch self {
        case .setAsPicked: .picked
        case .packing: .packing
        case .unpack: .packed
        case .dispatch: .dispatched
        }
    }

    var target: GoodsOutDeliveryNoteState {
        switch self {
        case .setAsPicked: .picking
        case .packing: .picked
        case .unpack: .packing
        case .dispatch: .finalised
        }
    }
}

enum GoodsOutScanOutcome: Hashable, Sendable {
    case picked(sko: String, left: Int)
    case lineComplete(sko: String)
    case notOnNote(code: String)
    case notPicking
}

struct GoodsOutDeliveryNote: Identifiable, Hashable, Sendable {
    let reference: String
    var channel: GoodsOutChannel
    var platform: String?
    var customer: String
    var customerNumber: String
    var contactName: String
    var email: String
    var phone: String
    var deliveryAddress: GoodsOutAddress
    var shipper: GoodsOutShipper?
    var state: GoodsOutDeliveryNoteState
    var picker: String?
    var packerSameAsPicker = true
    var trolley: String?
    var pickedBay: String?
    var lines: [GoodsOutLine]
    var shippingLabelNote: String?
    var customerInstructions: String?
    var warehouseNote: String?
    var createdAt: Date
    var stateDates: [GoodsOutDeliveryNoteState: Date] = [:]
    var age: GoodsOutNoteAge = .today
    var isOverdue = false
    var estimate: String
    var weightKg: Double
    var skoCount: Int
    var listDetail: String?
    var packingMaterials: [String] = []
    var parcels: [GoodsOutParcel] = []
    var shipments: [GoodsOutShipment] = []
    var leaflets: [GoodsOutLeaflet] = []
    var invoiceGenerated = false
    var handlingBlocked = false
    var emails: [GoodsOutEmail] = []
    var messages: [GoodsOutChatMessage] = []
    var tasks: [GoodsOutTask] = []
    var incompleteSet: GoodsOutIncompleteSet?

    var id: String { reference }
    var address: String { deliveryAddress.singleLine }

    var units: Int { lines.reduce(0) { $0 + $1.required } }
    var pickedUnits: Int { lines.reduce(0) { $0 + $1.picked } }
    var pickedLines: [GoodsOutLine] { lines.filter { $0.picked > 0 } }
    var waitingLines: [GoodsOutLine] { lines.filter(\.isWaiting) }
    var hasWaitingLines: Bool { lines.contains(where: \.isWaiting) }
    var linesStillToHandle: Int { lines.filter { !$0.isHandled }.count }
    var allLinesHandled: Bool { !lines.isEmpty && linesStillToHandle == 0 }
    var handledLineCount: Int { lines.count - linesStillToHandle }
    var linesByLocation: [GoodsOutLine] { lines.sorted { $0.pickLocation < $1.pickLocation } }
    var nextLineToPick: GoodsOutLine? { linesByLocation.first { !$0.isHandled } }

    var weightText: String {
        weightKg.formatted(.number.precision(.fractionLength(1)).locale(Locale(identifier: "en_GB"))) + " kg"
    }

    var itemsSummary: String { "\(lines.count) items (\(skoCount) SKOs, \(units) units)" }
    var pickedProgress: String { "\(pickedUnits) of \(units) picked" }

    var finishPickingTitle: String { channel.skipsPacking ? "Set as packed" : "Set as picked" }

    var undoStep: GoodsOutUndoStep? {
        switch state {
        case .picked: .setAsPicked
        case .packing: .packing
        case .packed: .unpack
        case .dispatched: .dispatch
        default: nil
        }
    }

    func needsTakeOver(by user: String) -> Bool {
        state == .picking && picker != nil && picker != user
    }

    func line(_ id: GoodsOutLine.ID) -> GoodsOutLine? {
        lines.first { $0.id == id }
    }

    func listSubtitle(currentUser: String) -> String {
        var parts = [customer, channel.title]
        if let platform {
            parts.append(platform)
        }
        if let listDetail {
            parts.append(listDetail)
        } else if state == .picking, let picker, picker != currentUser {
            parts.append("picked by \(picker)")
        } else {
            parts.append("\(units) \(units == 1 ? "item" : "items")")
            parts.append(weightText)
        }
        return parts.joined(separator: " · ")
    }

    func matches(search: String) -> Bool {
        let query = search.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !query.isEmpty else { return true }
        let haystack = [reference, customer, customerNumber, contactName, channel.title, platform ?? ""] + lines.map(\.sko)
        return haystack.contains { $0.localizedCaseInsensitiveContains(query) }
    }

    @discardableResult
    mutating func startPicking(picker name: String, trolley code: String?, at date: Date) -> Bool {
        guard state == .todo else { return false }
        picker = name
        trolley = code
        state = .picking
        stateDates[.picking] = date
        return true
    }

    @discardableResult
    mutating func assignPicker(_ name: String, packerSameAsPicker sameAsPicker: Bool = true, at date: Date) -> Bool {
        switch state {
        case .todo:
            picker = name
            packerSameAsPicker = sameAsPicker
            state = .picking
            stateDates[.picking] = date
            return true
        case .picking:
            picker = name
            packerSameAsPicker = sameAsPicker
            return true
        default:
            return false
        }
    }

    @discardableResult
    mutating func removePicker() -> Bool {
        guard state == .picking || state == .todo else { return false }
        picker = nil
        trolley = nil
        state = .todo
        stateDates[.picking] = nil
        return true
    }

    @discardableResult
    mutating func takeOver(by user: String) -> Bool {
        guard state == .picking else { return false }
        picker = user
        return true
    }

    @discardableResult
    mutating func changeTrolley(_ code: String?) -> Bool {
        guard state == .picking || state == .todo else { return false }
        trolley = code
        return true
    }

    @discardableResult
    mutating func updateLine(_ id: GoodsOutLine.ID, _ change: (inout GoodsOutLine) -> Void) -> Bool {
        guard state == .picking, let index = lines.firstIndex(where: { $0.id == id }) else { return false }
        change(&lines[index])
        return true
    }

    @discardableResult
    mutating func pickAll(_ id: GoodsOutLine.ID) -> Bool {
        updateLine(id) { $0.pick($0.remaining) }
    }

    @discardableResult
    mutating func pick(_ id: GoodsOutLine.ID, quantity: Int, from location: String? = nil) -> Bool {
        updateLine(id) { $0.pick(quantity, from: location) }
    }

    @discardableResult
    mutating func markNotPicked(_ id: GoodsOutLine.ID, reason: GoodsOutNotPickedReason) -> Bool {
        updateLine(id) { $0.markNotPicked(reason) }
    }

    @discardableResult
    mutating func setWaiting(_ id: GoodsOutLine.ID, reason: GoodsOutWaitingReason, note: String) -> Bool {
        updateLine(id) { $0.setWaiting(reason, note: note) }
    }

    @discardableResult
    mutating func resetLine(_ id: GoodsOutLine.ID) -> Bool {
        updateLine(id) { $0.reset() }
    }

    mutating func registerScan(code: String) -> GoodsOutScanOutcome {
        guard state == .picking else { return .notPicking }
        let normalised = code.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
        let matching = linesByLocation.filter { $0.sko.uppercased() == normalised }
        guard !matching.isEmpty else { return .notOnNote(code: normalised) }
        guard let line = matching.first(where: { !$0.isComplete }) else {
            return .lineComplete(sko: matching[0].sko)
        }
        pick(line.id, quantity: 1)
        return .picked(sko: line.sko, left: self.line(line.id)?.remaining ?? 0)
    }

    @discardableResult
    mutating func setAsPicked(bay: String?, at date: Date) -> Bool {
        guard state == .picking, allLinesHandled else { return false }
        pickedBay = bay
        if hasWaitingLines {
            state = .waiting
            stateDates[.waiting] = date
        } else if channel.skipsPacking {
            state = .packed
            stateDates[.picked] = date
            stateDates[.packed] = date
        } else {
            state = .picked
            stateDates[.picked] = date
        }
        return true
    }

    @discardableResult
    mutating func changePickedBay(_ bay: String) -> Bool {
        guard state == .picked || state == .waiting || state == .packing else { return false }
        pickedBay = bay
        return true
    }

    @discardableResult
    mutating func backToPicking() -> Bool {
        guard state == .waiting else { return false }
        for index in lines.indices where lines[index].isWaiting {
            lines[index].outcome = nil
        }
        state = .picking
        stateDates[.waiting] = nil
        return true
    }

    @discardableResult
    mutating func autoFinishWaiting(at date: Date) -> Bool {
        guard state == .waiting, !hasWaitingLines else { return false }
        state = .picked
        stateDates[.waiting] = nil
        stateDates[.picked] = date
        return true
    }

    @discardableResult
    mutating func startPacking(at date: Date) -> Bool {
        guard state == .picked else { return false }
        handlingBlocked = false
        state = .packing
        stateDates[.packing] = date
        return true
    }

    @discardableResult
    mutating func undo(_ step: GoodsOutUndoStep) -> Bool {
        guard state == step.from else { return false }
        stateDates[step.from] = nil
        state = step.target
        switch step {
        case .packing, .unpack:
            clearPacking()
        case .setAsPicked, .dispatch:
            break
        }
        return true
    }
}
