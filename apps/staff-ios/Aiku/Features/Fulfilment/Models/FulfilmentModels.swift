import Foundation

enum FulfilmentItemType: String, Hashable, Sendable {
    case pallet
    case box
    case oversize

    var title: String {
        switch self {
        case .pallet: "Pallet"
        case .box: "Box"
        case .oversize: "Oversize"
        }
    }

    func counted(_ count: Int) -> String {
        switch self {
        case .pallet: count == 1 ? "1 pallet" : "\(count) pallets"
        case .box: count == 1 ? "1 box" : "\(count) boxes"
        case .oversize: count == 1 ? "1 oversize item" : "\(count) oversize items"
        }
    }
}

enum FulfilmentPalletState: String, Hashable, Sendable {
    case confirmed
    case received
    case bookingIn
    case bookedIn
    case notReceived
    case storing
    case returning
    case damaged
    case lost
    case other
    case returned

    var title: String {
        switch self {
        case .confirmed: "Confirmed"
        case .received: "Received"
        case .bookingIn: "Booking in"
        case .bookedIn: "Booked in"
        case .notReceived: "Not received"
        case .storing: "Storing"
        case .returning: "Returning"
        case .damaged: "Damaged"
        case .lost: "Lost"
        case .other: "Other"
        case .returned: "Returned"
        }
    }

    var tone: Tone {
        switch self {
        case .confirmed, .returned: .neutral
        case .received, .bookingIn, .returning: .info
        case .bookedIn, .storing: .success
        case .notReceived, .damaged, .lost: .danger
        case .other: .warn
        }
    }

    var isIncident: Bool {
        self == .damaged || self == .lost || self == .other
    }

    var holdsStock: Bool {
        self == .storing || self == .returning || isIncident
    }
}

enum FulfilmentPalletFilter: String, CaseIterable, Hashable, Sendable {
    case storing
    case receiving
    case returning
    case incidents
    case returned

    var title: String {
        switch self {
        case .storing: "Storing"
        case .receiving: "Receiving"
        case .returning: "Returning"
        case .incidents: "Incidents"
        case .returned: "Returned"
        }
    }

    func matches(_ state: FulfilmentPalletState) -> Bool {
        switch self {
        case .storing: state == .storing
        case .receiving: state == .received || state == .bookingIn || state == .bookedIn
        case .returning: state == .returning
        case .incidents: state.isIncident
        case .returned: state == .returned
        }
    }
}

struct FulfilmentStoredQuantity: Hashable, Sendable {
    let itemCode: String
    var quantity: Int
}

struct FulfilmentPallet: Identifiable, Hashable, Sendable {
    let reference: String
    let customer: String
    let type: FulfilmentItemType
    var customerReference: String?
    var state: FulfilmentPalletState
    var location: String?
    var items: [FulfilmentStoredQuantity] = []

    var id: String { reference }

    var locationLabel: String { location ?? "no location" }

    var customerReferenceLabel: String {
        customerReference.map { "“\($0)”" } ?? "—"
    }

    var listSubtitle: String {
        var parts = [customer, type.title, locationLabel]
        let stocked = items.filter { $0.quantity > 0 }.count
        if stocked > 0 {
            parts.append(stocked == 1 ? "1 stored item" : "\(stocked) stored items")
        }
        return parts.joined(separator: " · ")
    }

    func quantity(of itemCode: String) -> Int {
        items.first { $0.itemCode == itemCode }?.quantity ?? 0
    }
}

enum FulfilmentStoredItemStatus: String, Hashable, Sendable {
    case active
    case discontinuing

    var chip: Chip {
        switch self {
        case .active: Chip("Active", .success)
        case .discontinuing: Chip("Discontinuing", .warn)
        }
    }
}

struct FulfilmentStoredItem: Identifiable, Hashable, Sendable {
    let code: String
    let name: String
    let customer: String
    var status: FulfilmentStoredItemStatus = .active
    var ean: String?

    var id: String { code }

    var detail: String {
        ean.map { "\(name) · EAN \($0)" } ?? name
    }
}

struct FulfilmentStoredItemSummary: Identifiable, Hashable, Sendable {
    let item: FulfilmentStoredItem
    let quantity: Int
    let palletReferences: [String]

    var id: String { item.code }

    var subtitle: String {
        let pallets = palletReferences.count == 1 ? "1 pallet" : "\(palletReferences.count) pallets"
        return "\(item.customer) · \(item.name) · \(pallets)"
    }
}

enum FulfilmentDeliveryState: String, CaseIterable, Hashable, Sendable {
    case confirmed
    case received
    case bookingIn
    case bookedIn
    case notReceived

    static let steps: [FulfilmentDeliveryState] = [.confirmed, .received, .bookingIn, .bookedIn]

    var title: String {
        switch self {
        case .confirmed: "Confirmed"
        case .received: "Received"
        case .bookingIn: "Booking in"
        case .bookedIn: "Booked in"
        case .notReceived: "Not received"
        }
    }

    var tone: Tone {
        switch self {
        case .confirmed: .neutral
        case .received, .bookingIn: .info
        case .bookedIn: .success
        case .notReceived: .danger
        }
    }

    var stepIndex: Int? {
        Self.steps.firstIndex(of: self)
    }
}

struct FulfilmentDelivery: Identifiable, Hashable, Sendable {
    let reference: String
    let customer: String
    var state: FulfilmentDeliveryState
    var palletReferences: [String]
    var arrivalNote: String?

    var id: String { reference }
}

struct FulfilmentPalletGroup: Identifiable, Hashable, Sendable {
    let pallets: [FulfilmentPallet]

    var id: String { pallets.first?.reference ?? "" }

    var title: String {
        guard let first = pallets.first else { return "" }
        guard pallets.count > 1, let last = pallets.last else { return first.reference }
        let suffix = last.reference.split(separator: "-").last.map(String.init) ?? last.reference
        return "\(first.reference) … \(suffix)"
    }

    var typeTitle: String { pallets.first?.type.title ?? "" }

    var subtitle: String {
        guard let first = pallets.first else { return "" }
        if pallets.count > 1 {
            let reference = first.customerReference.map { " · “\($0)”" } ?? ""
            return first.type.counted(pallets.count) + reference
        }
        return first.customerReference.map { "Customer ref “\($0)”" } ?? first.type.title
    }

    var state: FulfilmentPalletState { pallets.first?.state ?? .received }

    static func grouping(_ pallets: [FulfilmentPallet]) -> [FulfilmentPalletGroup] {
        var groups: [[FulfilmentPallet]] = []
        for pallet in pallets {
            if pallet.type == .box,
               let last = groups.last?.last,
               last.type == .box,
               last.customerReference == pallet.customerReference,
               last.state == pallet.state {
                groups[groups.count - 1].append(pallet)
            } else {
                groups.append([pallet])
            }
        }
        return groups.map(FulfilmentPalletGroup.init)
    }
}

enum FulfilmentIncidentKind: String, CaseIterable, Hashable, Sendable {
    case damaged
    case lost
    case other

    var title: String {
        switch self {
        case .damaged: "Damaged"
        case .lost: "Lost"
        case .other: "Other"
        }
    }

    var explanation: String {
        switch self {
        case .damaged: "The pallet or its goods are damaged"
        case .lost: "The pallet is not where it should be"
        case .other: "Another problem, explain in the note"
        }
    }

    var palletState: FulfilmentPalletState {
        switch self {
        case .damaged: .damaged
        case .lost: .lost
        case .other: .other
        }
    }
}

struct FulfilmentIncident: Identifiable, Hashable, Sendable {
    let palletReference: String
    let kind: FulfilmentIncidentKind
    let note: String
    let reportedBy: String
    let date: String
    var returnReference: String?

    var id: String { palletReference }

    var subtitle: String {
        let quoted = note.isEmpty ? nil : "“\(note)”"
        if let returnReference {
            return (["Not picked in return \(returnReference)"] + [quoted].compactMap { $0 }).joined(separator: " · ")
        }
        return ([quoted].compactMap { $0 } + [reportedBy, date]).joined(separator: " · ")
    }

    static func reporterLabel(fullName: String) -> String {
        let parts = fullName.split(separator: " ")
        guard let first = parts.first else { return fullName }
        guard parts.count > 1, let initial = parts.last?.first else { return String(first) }
        return "\(first) \(initial)."
    }

    static func dateLabel(for date: Date) -> String {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_GB")
        formatter.dateFormat = "dd MMM"
        return formatter.string(from: date)
    }
}

struct FulfilmentAuditLine: Identifiable, Hashable, Sendable {
    let itemCode: String
    let expected: Int
    var counted: Int?

    var id: String { itemCode }

    var difference: Int { (counted ?? 0) - expected }

    var isComplete: Bool { (counted ?? 0) >= expected && counted != nil }

    var chip: Chip? {
        guard counted != nil else { return nil }
        if expected == 0 { return Chip("Addition +\(difference)", .info) }
        if difference == 0 { return Chip("No change", .success) }
        if difference < 0 { return Chip("Subtraction \(difference.signedChange)", .warn) }
        return Chip("Addition \(difference.signedChange)", .info)
    }

    var subtitle: String {
        if expected == 0 { return "Not on the list · added" }
        return counted == nil ? "Expected \(expected) · not counted yet" : "Expected \(expected)"
    }

    var countTone: Tone {
        guard counted != nil else { return .neutral }
        return difference == 0 ? .success : .warn
    }
}

struct FulfilmentAudit: Identifiable, Hashable, Sendable {
    let reference: String
    let palletReference: String
    var lines: [FulfilmentAuditLine]
    var currentItemCode: String?

    var id: String { reference }

    var nextItemCode: String? {
        (lines.first { !$0.isComplete } ?? lines.first)?.itemCode
    }

    var focusItemCode: String? { currentItemCode ?? nextItemCode }
}

enum FulfilmentReturnType: String, CaseIterable, Hashable, Sendable {
    case pallets
    case storedItems
    case dropshipping

    var title: String {
        switch self {
        case .pallets: "Pallets"
        case .storedItems: "Stored items"
        case .dropshipping: "Dropshipping"
        }
    }

    var listTitle: String {
        self == .pallets ? "Pallets (wholesale)" : title
    }
}

enum FulfilmentReturnFilter: Hashable, Sendable {
    case all
    case type(FulfilmentReturnType)

    static let allCases: [FulfilmentReturnFilter] = [.all] + FulfilmentReturnType.allCases.map { .type($0) }

    var title: String {
        switch self {
        case .all: "All"
        case .type(let type): type.title
        }
    }

    func matches(_ record: FulfilmentReturn) -> Bool {
        switch self {
        case .all: true
        case .type(let type): record.type == type
        }
    }
}

enum FulfilmentReturnState: String, CaseIterable, Hashable, Sendable {
    case confirmed
    case picking
    case picked
    case dispatched

    var title: String {
        switch self {
        case .confirmed: "Confirmed"
        case .picking: "Picking"
        case .picked: "Picked"
        case .dispatched: "Dispatched"
        }
    }

    var tone: Tone {
        switch self {
        case .confirmed: .neutral
        case .picking: .info
        case .picked, .dispatched: .success
        }
    }

    var stepIndex: Int {
        Self.allCases.firstIndex(of: self) ?? 0
    }
}

enum FulfilmentLineState: String, Hashable, Sendable {
    case toPick
    case picked
    case notPicked
}

struct FulfilmentReturnPalletLine: Identifiable, Hashable, Sendable {
    let palletReference: String
    let location: String?
    var state: FulfilmentLineState = .toPick
    var incident: FulfilmentIncidentKind?

    var id: String { palletReference }

    var chip: Chip {
        switch state {
        case .toPick: Chip("To pick")
        case .picked: Chip("Picked", .success)
        case .notPicked: Chip("Not picked · \(incident?.title.lowercased() ?? "other")", .danger)
        }
    }

    func subtitle(type: FulfilmentItemType) -> String {
        let place = location ?? "no location"
        return state == .picked ? "\(type.title) · was in \(place)" : "\(type.title) · \(place)"
    }
}

struct FulfilmentReturnItemLine: Identifiable, Hashable, Sendable {
    let itemCode: String
    let palletReference: String
    let location: String?
    var required: Int
    var available: Int
    var picked: Int = 0

    var id: String { "\(itemCode)@\(palletReference)" }

    var target: Int { min(required, available) }

    var isComplete: Bool { picked >= required }

    var isShort: Bool { picked >= available && picked < required }

    var remaining: Int { max(0, target - picked) }

    var countTone: Tone {
        if isComplete { return .success }
        if isShort || picked > 0 { return .warn }
        return .neutral
    }
}

enum FulfilmentDeparture: String, Hashable, Sendable {
    case shipment
    case collection
}

struct FulfilmentShipment: Hashable, Sendable {
    let shipper: String
    let tracking: String
}

struct FulfilmentReturn: Identifiable, Hashable, Sendable {
    let reference: String
    let customer: String
    let type: FulfilmentReturnType
    var source: String?
    var palletLines: [FulfilmentReturnPalletLine] = []
    var itemLines: [FulfilmentReturnItemLine] = []
    var departure: FulfilmentDeparture = .shipment
    var shipment: FulfilmentShipment?
    var isDispatched = false

    var id: String { reference }

    var state: FulfilmentReturnState {
        if isDispatched { return .dispatched }
        if type == .pallets {
            guard !palletLines.isEmpty else { return .confirmed }
            if palletLines.allSatisfy({ $0.state != .toPick }) { return .picked }
            return palletLines.contains { $0.state != .toPick } ? .picking : .confirmed
        }
        guard !itemLines.isEmpty else { return .confirmed }
        if itemLines.allSatisfy(\.isComplete) { return .picked }
        return itemLines.contains { $0.picked > 0 } ? .picking : .confirmed
    }

    var pickedPalletReferences: [String] {
        palletLines.filter { $0.state == .picked }.map(\.palletReference)
    }

    var requiredUnits: Int { itemLines.reduce(0) { $0 + $1.required } }

    var pickedUnits: Int { itemLines.reduce(0) { $0 + $1.picked } }

    var pickedSummary: String {
        if type == .pallets {
            let references = pickedPalletReferences
            let count = references.count == 1 ? "1 pallet" : "\(references.count) pallets"
            return "\(count): \(references.joined(separator: ", "))"
        }
        var codes: [String] = []
        for line in itemLines where !codes.contains(line.itemCode) {
            codes.append(line.itemCode)
        }
        let count = pickedUnits == 1 ? "1 item" : "\(pickedUnits) items"
        return "\(count): \(codes.joined(separator: ", "))"
    }

    var returnedMessage: String {
        if type == .pallets {
            let count = pickedPalletReferences.count
            return count == 1 ? "1 pallet is now Returned." : "\(count) pallets are now Returned."
        }
        return pickedUnits == 1 ? "1 item is now Returned." : "\(pickedUnits) items are now Returned."
    }

    var listSubtitle: String {
        var parts = [customer, type.listTitle]
        switch state {
        case .confirmed:
            if let source {
                parts.append(source)
            } else if type == .pallets {
                parts.append(palletLines.count == 1 ? "1 pallet" : "\(palletLines.count) pallets")
            } else {
                parts.append(requiredUnits == 1 ? "1 item" : "\(requiredUnits) items")
            }
        case .picking:
            if let source {
                parts.append(source)
            } else if type == .pallets {
                parts.append("\(palletLines.filter { $0.state != .toPick }.count) of \(palletLines.count) picked")
            } else {
                parts.append("\(pickedUnits) of \(requiredUnits) picked")
            }
        case .picked:
            parts.append("ready to dispatch")
        case .dispatched:
            parts.append("dispatched")
        }
        return parts.joined(separator: " · ")
    }

    var currentPalletLine: FulfilmentReturnPalletLine? {
        palletLines.first { $0.state == .toPick }
    }

    var currentItemLine: FulfilmentReturnItemLine? {
        itemLines.first { !$0.isComplete && !$0.isShort } ?? itemLines.first { !$0.isComplete }
    }

    var itemLineGroups: [(palletReference: String, location: String?, lines: [FulfilmentReturnItemLine])] {
        var groups: [(palletReference: String, location: String?, lines: [FulfilmentReturnItemLine])] = []
        for line in itemLines {
            if let index = groups.firstIndex(where: { $0.palletReference == line.palletReference }) {
                groups[index].lines.append(line)
            } else {
                groups.append((line.palletReference, line.location, [line]))
            }
        }
        return groups
    }
}
