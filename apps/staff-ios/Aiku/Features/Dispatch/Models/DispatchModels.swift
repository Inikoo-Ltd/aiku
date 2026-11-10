import Foundation

struct DispatchShipper: Identifiable, Hashable, Sendable {
    let name: String
    let integration: String
    let shipmentsToday: Int

    var id: String { name }

    var subtitle: String {
        switch shipmentsToday {
        case 0: "No shipments today"
        case 1: "1 shipment today"
        default: "\(shipmentsToday) shipments today"
        }
    }

    var tone: Tone { shipmentsToday > 0 ? .info : .neutral }
}

enum DispatchSessionKind: String, CaseIterable, Hashable, Sendable {
    case dropshipping
    case fulfilment

    var title: String {
        switch self {
        case .dropshipping: "Dropshipping"
        case .fulfilment: "Fulfilment"
        }
    }
}

enum DispatchSessionState: String, Hashable, Sendable {
    case inProcess
    case picking
    case waiting
    case pickingFinished
    case packed

    var title: String {
        switch self {
        case .inProcess: "In process"
        case .picking: "Picking"
        case .waiting: "Waiting"
        case .pickingFinished: "Picking finished"
        case .packed: "Packed"
        }
    }

    var tone: Tone {
        switch self {
        case .inProcess: .neutral
        case .picking: .info
        case .waiting: .warn
        case .pickingFinished, .packed: .success
        }
    }
}

enum DispatchSessionTab: String, CaseIterable, Hashable, Sendable {
    case items
    case byLocation
    case byDelivery

    var title: String {
        switch self {
        case .items: "Items"
        case .byLocation: "Pick by location"
        case .byDelivery: "Group by delivery"
        }
    }
}

struct DispatchPickNote: Identifiable, Hashable, Sendable {
    let deliveryNote: String
    let trolley: String
    let required: Int
    var picked = 0
    var isWaiting = false

    var id: String { deliveryNote }

    var isComplete: Bool { picked >= required }

    var isOpen: Bool { !isComplete && !isWaiting }

    var tone: Tone {
        if isComplete { return .success }
        if isWaiting { return .warn }
        return picked > 0 ? .info : .neutral
    }
}

struct DispatchPickLocation: Identifiable, Hashable, Sendable {
    let location: String
    let sko: String
    var skoName: String?
    var notes: [DispatchPickNote]

    var id: String { location }

    var required: Int { notes.reduce(0) { $0 + $1.required } }

    var picked: Int { notes.reduce(0) { $0 + $1.picked } }

    var remaining: Int { notes.filter(\.isOpen).reduce(0) { $0 + ($1.required - $1.picked) } }

    var isOpen: Bool { notes.contains(where: \.isOpen) }

    var summary: String {
        let count = notes.count == 1 ? "1 delivery note" : "\(notes.count) delivery notes"
        return "\(sko) × \(required) · \(count)"
    }

    var skoLine: String {
        [sko, skoName].compactMap { $0 }.joined(separator: " · ")
    }
}

struct DispatchPackNote: Identifiable, Hashable, Sendable {
    let deliveryNote: String
    let trolley: String
    let items: Int
    var packed = 0
    var box = 1

    var id: String { deliveryNote }

    var isComplete: Bool { packed >= items }

    var remaining: Int { max(0, items - packed) }

    var chip: Chip? {
        if isComplete { return Chip("Packed", .success) }
        return packed > 0 ? Chip("Packing", .info) : nil
    }

    var tone: Tone {
        if isComplete { return .success }
        return packed > 0 ? .info : .neutral
    }

    var subtitle: String {
        "\(trolley) · \(items == 1 ? "1 item" : "\(items) items")"
    }
}

struct DispatchSkoLine: Identifiable, Hashable, Sendable {
    let sko: String
    let skoName: String?
    let required: Int
    let picked: Int

    var id: String { sko }
}

struct DispatchPickingSession: Identifiable, Hashable, Sendable {
    let reference: String
    let kind: DispatchSessionKind
    let picker: String
    var isYou = false
    let deliveryNoteCount: Int
    var trolleys: [String]
    var locations: [DispatchPickLocation]
    var packNotes: [DispatchPackNote] = []
    var isStarted = false
    var bay: String?

    var id: String { reference }

    var itemCount: Int { locations.reduce(0) { $0 + $1.required } }

    var pickedCount: Int { locations.reduce(0) { $0 + $1.picked } }

    var allNotes: [DispatchPickNote] { locations.flatMap(\.notes) }

    var isPickingDone: Bool { allNotes.allSatisfy(\.isComplete) }

    var state: DispatchSessionState {
        if !packNotes.isEmpty, packNotes.allSatisfy(\.isComplete) { return .packed }
        guard isStarted else { return .inProcess }
        if isPickingDone { return .pickingFinished }
        if !allNotes.contains(where: \.isOpen) { return .waiting }
        return .picking
    }

    var pickedPercent: Int {
        guard itemCount > 0 else { return 0 }
        return Int((Double(pickedCount) / Double(itemCount) * 100).rounded())
    }

    var packedPercent: Int {
        let total = packNotes.reduce(0) { $0 + $1.items }
        guard total > 0 else { return 0 }
        let packed = packNotes.reduce(0) { $0 + $1.packed }
        return Int((Double(packed) / Double(total) * 100).rounded())
    }

    var waitingNoteCount: Int {
        Set(allNotes.filter(\.isWaiting).map(\.deliveryNote)).count
    }

    var pickerLabel: String { isYou ? "\(picker) (you)" : picker }

    var listSubtitle: String {
        let notes = deliveryNoteCount == 1 ? "1 delivery note" : "\(deliveryNoteCount) delivery notes"
        switch state {
        case .inProcess:
            return "\(picker) · \(notes) · not started"
        case .picking:
            let trolleyCount = trolleys.count == 1 ? "1 trolley" : "\(trolleys.count) trolleys"
            return "\(picker) · \(notes) · \(trolleyCount) · \(pickedPercent)% picked"
        case .waiting:
            let waiting = waitingNoteCount == 1 ? "1 delivery note" : "\(waitingNoteCount) delivery notes"
            return "\(picker) · \(waiting) waiting"
        case .pickingFinished:
            return "\(picker) · packing \(packedPercent)%"
        case .packed:
            return "\(picker) · packed"
        }
    }

    var currentLocation: DispatchPickLocation? {
        locations.first(where: \.isOpen)
    }

    var nextLocation: DispatchPickLocation? {
        guard let current = currentLocation, let index = locations.firstIndex(of: current) else { return nil }
        return locations[(index + 1)...].first(where: \.isOpen)
    }

    var currentPackNote: DispatchPackNote? {
        packNotes.first { !$0.isComplete }
    }

    var skoLines: [DispatchSkoLine] {
        var order: [String] = []
        var totals: [String: (name: String?, required: Int, picked: Int)] = [:]
        for location in locations {
            if totals[location.sko] == nil {
                order.append(location.sko)
                totals[location.sko] = (location.skoName, 0, 0)
            }
            totals[location.sko]?.required += location.required
            totals[location.sko]?.picked += location.picked
        }
        return order.compactMap { sko in
            totals[sko].map { DispatchSkoLine(sko: sko, skoName: $0.name, required: $0.required, picked: $0.picked) }
        }
    }

    var deliveryNoteLines: [DispatchPackNote] {
        if !packNotes.isEmpty { return packNotes }
        var order: [String] = []
        var lines: [String: DispatchPackNote] = [:]
        for note in allNotes {
            if let existing = lines[note.deliveryNote] {
                lines[note.deliveryNote] = DispatchPackNote(deliveryNote: note.deliveryNote, trolley: existing.trolley, items: existing.items + note.required)
            } else {
                order.append(note.deliveryNote)
                lines[note.deliveryNote] = DispatchPackNote(deliveryNote: note.deliveryNote, trolley: note.trolley, items: note.required)
            }
        }
        return order.compactMap { lines[$0] }
    }

    func noteCount(onTrolley trolley: String) -> Int {
        let picking = allNotes.filter { $0.trolley == trolley }.map(\.deliveryNote)
        let packing = packNotes.filter { $0.trolley == trolley }.map(\.deliveryNote)
        return Set(picking + packing).count
    }
}

enum DispatchTrolleyHolder: Hashable, Sendable {
    case free
    case deliveryNote(String)
    case session(String)

    var title: String {
        switch self {
        case .free: "Free"
        case .deliveryNote, .session: "In use"
        }
    }

    var tone: Tone {
        self == .free ? .success : .info
    }

    var subtitle: String? {
        switch self {
        case .free: nil
        case .deliveryNote(let reference): "Delivery note \(reference)"
        case .session(let reference): "Picking session \(reference)"
        }
    }
}

struct DispatchTrolley: Identifiable, Hashable, Sendable {
    let code: String
    let holder: DispatchTrolleyHolder

    var id: String { code }
}

struct DispatchBayNote: Hashable, Sendable {
    let deliveryNote: String
    var isWaiting = false
}

struct DispatchPickedBay: Identifiable, Hashable, Sendable {
    let name: String
    var notes: [DispatchBayNote] = []

    var id: String { name }

    var isFree: Bool { notes.isEmpty }

    var subtitle: String? {
        guard !notes.isEmpty else { return nil }
        let count = notes.count == 1 ? "1 delivery note" : "\(notes.count) delivery notes"
        let waiting = notes.filter(\.isWaiting).count
        return waiting > 0 ? "\(count) · \(waiting) waiting" : count
    }

    var chip: Chip { isFree ? Chip("Free", .success) : Chip("In use", .info) }
}

enum DispatchProblemKind: String, CaseIterable, Hashable, Sendable {
    case unknownBarcode
    case wrongStock
    case dataProblem
    case appError
    case other

    var title: String {
        switch self {
        case .unknownBarcode: "Unknown barcode"
        case .wrongStock: "Wrong stock"
        case .dataProblem: "Data problem"
        case .appError: "App error"
        case .other: "Other"
        }
    }
}

struct DispatchProblemReport: Hashable, Sendable {
    let kind: DispatchProblemKind
    let note: String
    let hasPhoto: Bool
    let context: String
}
