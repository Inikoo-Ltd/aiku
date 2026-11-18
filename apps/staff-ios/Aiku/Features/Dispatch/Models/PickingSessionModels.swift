import Foundation

enum PickingSessionState: String, CaseIterable, Hashable, Sendable {
    case inProcess = "in_process"
    case handling
    case handlingBlocked = "handling_blocked"
    case pickingFinished = "picking_finished"
    case packingFinished = "packing_finished"

    var title: String {
        switch self {
        case .inProcess: "In process"
        case .handling: "Picking"
        case .handlingBlocked: "Waiting"
        case .pickingFinished: "Picking finished"
        case .packingFinished: "Packing finished"
        }
    }

    var tone: Tone {
        switch self {
        case .inProcess: .neutral
        case .handling: .info
        case .handlingBlocked: .warn
        case .pickingFinished, .packingFinished: .success
        }
    }

    var isPacking: Bool { self == .pickingFinished || self == .packingFinished }
}

enum PickingSessionFilter: Hashable, Sendable, CaseIterable {
    case all
    case state(PickingSessionState)

    static var allCases: [PickingSessionFilter] {
        [.all] + PickingSessionState.allCases.map { .state($0) }
    }

    var title: String {
        switch self {
        case .all: "All"
        case .state(let state): state.title
        }
    }

    var stateKey: String? {
        switch self {
        case .all: nil
        case .state(let state): state.rawValue
        }
    }
}

enum PickingSessionActionKey {
    static let startPicking = "start_picking"
    static let backToPicking = "back_to_picking"
    static let addDeliveryNotes = "add_delivery_notes"
    static let removeDeliveryNotes = "remove_delivery_notes"
    static let scanPick = "scan_pick"
    static let scanPack = "scan_pack"

    static let footerOrder = [scanPick, scanPack, startPicking, backToPicking, addDeliveryNotes, removeDeliveryNotes]
}

enum PickingSessionScanStatus {
    static let picked = "picked"
    static let packed = "packed"
    static let notFound = "not_found"
    static let wrongState = "wrong_state"
    static let nothingChanged = "nothing_changed"
}

enum PickingSessionFormat {
    static let separator = " · "

    static func count(_ value: Int, _ singular: String, _ plural: String? = nil) -> String {
        value == 1 ? "1 \(singular)" : "\(value) \(plural ?? singular + "s")"
    }

    static func percent(_ value: Double) -> Int {
        min(100, max(0, Int(value.rounded())))
    }

    static func quantity(_ value: Double, label: String? = nil) -> String {
        if let label, !label.isEmpty { return label }
        if value == value.rounded() { return String(Int(value)) }
        return String(format: "%g", value)
    }

    static func stateLabel(_ state: String, label: String?) -> String {
        if let label, !label.isEmpty { return label }
        return PickingSessionState(rawValue: state)?.title ?? state
    }

    static func personName(_ person: MobilePickingSessionPerson?, currentUserID: Int?) -> String? {
        guard let person, let name = person.name, !name.isEmpty else { return nil }
        return person.id == currentUserID ? "\(name) (you)" : name
    }
}

extension MobilePickingSessionRow {
    var sessionState: PickingSessionState? { PickingSessionState(rawValue: state) }

    var stateTitle: String { PickingSessionFormat.stateLabel(state, label: stateLabel) }

    var tone: Tone { sessionState?.tone ?? .neutral }

    func subtitle(currentUserID: Int?) -> String {
        let progress = sessionState?.isPacking == true
            ? "packing \(PickingSessionFormat.percent(packingPercentage))%"
            : "\(PickingSessionFormat.percent(pickingPercentage))% picked"
        return [
            PickingSessionFormat.personName(picker, currentUserID: currentUserID),
            PickingSessionFormat.count(numberDeliveryNotes, "delivery note"),
            PickingSessionFormat.count(numberItems, "item"),
            PickingSessionFormat.count(numberTrolleys, "trolley"),
            progress
        ]
        .compactMap { $0 }
        .joined(separator: PickingSessionFormat.separator)
    }
}

extension MobilePickingSession {
    var sessionState: PickingSessionState? { PickingSessionState(rawValue: state) }

    var stateTitle: String { PickingSessionFormat.stateLabel(state, label: stateLabel) }

    var tone: Tone { sessionState?.tone ?? .neutral }

    var isPickingOver: Bool { sessionState?.isPacking == true }

    var footerActions: [MobilePickingSessionAction] {
        PickingSessionActionKey.footerOrder.compactMap { action($0) }
    }

    func canRun(_ key: String) -> Bool {
        action(key).map { !$0.isDisabled } ?? false
    }

    func pick(_ key: String?) -> MobilePickListEntry? {
        pickList.first { $0.key == key }
    }

    func pickContaining(deliveryNoteItemID: Int) -> MobilePickListEntry? {
        pickList.first { entry in entry.lines.contains { $0.deliveryNoteItemId == deliveryNoteItemID } }
    }

    func nextOpenPick(after key: String?) -> MobilePickListEntry? {
        let index = pickList.firstIndex { $0.key == key } ?? -1
        let rotated = Array(pickList.dropFirst(index + 1)) + Array(pickList.prefix(index + 1))
        return rotated.first { !$0.isDone && $0.key != key }
    }

    func openPickKey(current: String?) -> String? {
        if let entry = pick(current), !entry.isDone { return entry.key }
        return nextOpenPick(after: current)?.key
    }

    var waitingDeliveryNoteCount: Int {
        deliveryNotes.filter(\.hasWaitingItems).count
    }

    var nextNoteToPack: MobileSessionDeliveryNote? {
        deliveryNotes.first { !$0.isPacked }
    }
}

extension MobileSessionDeliveryNote {
    static let packedStates: Set<String> = ["packed", "finalised", "dispatched", "cancelled"]

    var isPacked: Bool { Self.packedStates.contains(state) }

    var stateTitle: String { stateLabel.flatMap { $0.isEmpty ? nil : $0 } ?? state }

    var stateTone: Tone {
        switch state {
        case "handling_blocked": .warn
        case "packed", "finalised", "dispatched": .success
        case "cancelled": .danger
        case "unassigned", "queued": .neutral
        default: .info
        }
    }

    var packingTone: Tone {
        if isPacked { return .success }
        return packingPercentage > 0 ? .info : .neutral
    }

    func subtitle(isPacking: Bool) -> String {
        [
            PickingSessionFormat.count(numberItems, "item"),
            isPacking
                ? "packing \(PickingSessionFormat.percent(packingPercentage))%"
                : "\(PickingSessionFormat.percent(pickingPercentage))% picked",
            isForCollection ? "Collection" : nil,
            hasWaitingItems ? "waiting" : nil
        ]
        .compactMap { $0 }
        .joined(separator: PickingSessionFormat.separator)
    }
}

extension MobilePickListEntry {
    var title: String {
        if let code = location?.code, !code.isEmpty { return code }
        return orgStock?.code ?? ""
    }

    var stockLine: String {
        [orgStock?.code, orgStock?.name]
            .compactMap { $0 }
            .filter { !$0.isEmpty }
            .joined(separator: PickingSessionFormat.separator)
    }

    var toPickText: String {
        "\(PickingSessionFormat.quantity(quantityToPick, label: quantityToPickLabel)) to pick"
    }

    var detail: String {
        let notes = Set(lines.map(\.deliveryNoteId)).count
        return "\(orgStock?.code ?? "") × \(PickingSessionFormat.quantity(quantityToPick, label: quantityToPickLabel))\(PickingSessionFormat.separator)\(PickingSessionFormat.count(notes, "delivery note"))"
    }
}

extension MobilePickListLine {
    var tone: Tone {
        if quantityToPick <= 0 { return .success }
        return quantityPicked > 0 ? .info : .neutral
    }

    var subtitle: String {
        quantityToPick > 0 ? "\(PickingSessionFormat.quantity(quantityToPick)) to pick" : "Picked"
    }
}

struct PickingSessionScanTarget: Equatable, Sendable {
    let lineID: Int
    let barcode: String
    let code: String
    let name: String
    let deliveryNoteReference: String?
    let locationCode: String?
    let locationOrgStockID: Int?
    let done: Double
    let remaining: Double
    let remainingLabel: String?

    var remainingText: String { PickingSessionFormat.quantity(remaining, label: remainingLabel) }

    var maximumAmount: Int { max(1, Int(remaining.rounded(.down))) }

    var place: String {
        [locationCode, deliveryNoteReference.map { "Delivery note \($0)" }]
            .compactMap { $0 }
            .filter { !$0.isEmpty }
            .joined(separator: PickingSessionFormat.separator)
    }
}

enum PickingSessionScanResult: Equatable, Sendable {
    case done
    case refused
    case failed
}

struct PickingSessionScanLogEntry: Identifiable, Equatable, Sendable {
    let id: Int
    let barcode: String
    let result: PickingSessionScanResult
    let message: String?
}

enum PickingSessionEvent: Equatable, Sendable {
    case feedback(String, Tone)
    case lineDone(String)
    case locationDone(String)
    case allPicked
    case nowWaiting
    case allPacked

    var isFinishing: Bool {
        switch self {
        case .allPicked, .nowWaiting, .allPacked: true
        default: false
        }
    }

    var toast: (text: String, tone: Tone) {
        switch self {
        case .feedback(let text, let tone): (text, tone)
        case .lineDone(let code): ("\(code) · line done", .success)
        case .locationDone(let location): ("Everything for \(location) is picked", .success)
        case .allPicked: ("Everything in this session is picked", .success)
        case .nowWaiting: ("Picking stopped: the session is waiting for some lines", .warn)
        case .allPacked: ("Every delivery note is packed", .success)
        }
    }
}

extension MobileSessionTrolley {
    var holderTitle: String { isFree ? "Free" : "In use" }

    var isFree: Bool { currentDeliveryNote == nil && currentPickingSession == nil }

    var holderTone: Tone { isFree ? .success : .info }

    var holderSubtitle: String? {
        if let note = currentDeliveryNote { return "Delivery note \(note.reference ?? "#\(note.id)")" }
        if let session = currentPickingSession { return "Picking session \(session.reference ?? "#\(session.id)")" }
        return nil
    }
}

extension MobileSessionPickedBay {
    var isFree: Bool { numberDeliveryNotes <= 0 && deliveryNotes.isEmpty }

    var subtitle: String? {
        guard !isFree else { return nil }
        let notes = PickingSessionFormat.count(max(numberDeliveryNotes, deliveryNotes.count), "delivery note")
        return numberWaiting > 0 ? "\(notes) · \(numberWaiting) waiting" : notes
    }

    var chip: Chip { isFree ? Chip("Free", .success) : Chip("In use", .info) }
}

extension MobileSessionPickedBay.DeliveryNote {
    var chip: Chip {
        let tone = state.flatMap(GoodsOutDeliveryNoteState.init(apiState:))?.tone ?? .neutral
        return Chip(stateLabel ?? state ?? "", tone)
    }
}

enum PickingSessionErrorText {
    static func message(_ error: Error, offline: String = "This needs internet. Check Wi-Fi and try again.") -> String {
        if let apiError = error as? APIError {
            switch apiError {
            case .offline: return offline
            case .unauthorized: return "Your session has ended. Sign in again."
            default: return apiError.errorDescription ?? "Something went wrong."
            }
        }
        return error.localizedDescription
    }
}
