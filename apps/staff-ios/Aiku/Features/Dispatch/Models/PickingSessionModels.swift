import Foundation

enum PickingSessionState: String, CaseIterable, Hashable, Sendable {
    case inProcess = "in_process"
    case handling
    case handlingBlocked = "handling_blocked"
    case pickingFinished = "picking_finished"
    case packingFinished = "packing_finished"

    @MainActor
    var title: String {
        switch self {
        case .inProcess: L10n.text("In process")
        case .handling: L10n.text("Picking")
        case .handlingBlocked: L10n.text("Waiting")
        case .pickingFinished: L10n.text("Picking finished")
        case .packingFinished: L10n.text("Packing finished")
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

    @MainActor
    var title: String {
        switch self {
        case .all: L10n.text("All")
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

extension PickingSessionSort {
    static let serverOrderLabel = "Newest first"

    static var options: [ListSortOption<PickingSessionSort>] {
        let sorts = allCases.map { ListSortOption<PickingSessionSort>(sort: $0, label: $0.label) }
        return [ListSortOption<PickingSessionSort>(sort: nil, label: serverOrderLabel)] + sorts
    }

    var label: String {
        switch self {
        case .reference: "Reference"
        case .picker: "Picker"
        case .started: "Started"
        case .finished: "Finished"
        }
    }
}

enum PickingSessionOfflineList {
    static func rows(_ saved: [MobilePickingSessionRow], query: MobilePickingSessionsQuery) -> [MobilePickingSessionRow] {
        let matching = saved.filter { row in
            (query.state == nil || row.state == query.state) && SavedListFilter.matches(query.search, row.reference)
        }
        return sorted(matching, by: query.sort)
    }

    static func sorted(_ rows: [MobilePickingSessionRow], by sort: PickingSessionSort?) -> [MobilePickingSessionRow] {
        switch sort {
        case nil:
            return rows.sorted { lhs, rhs in
                let lhsPacked = lhs.state == PickingSessionState.packingFinished.rawValue
                let rhsPacked = rhs.state == PickingSessionState.packingFinished.rawValue
                if lhsPacked != rhsPacked { return rhsPacked }
                return lhs.id > rhs.id
            }
        case .reference?:
            return SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { Self.nameKey($0.reference) })
        case .picker?:
            return SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { Self.nameKey($0.picker?.name) })
        case .started?:
            return SavedListFilter.sorted(rows, descending: true, id: { $0.id }, key: { Self.instant($0.startAt) })
        case .finished?:
            return SavedListFilter.sorted(rows, descending: true, id: { $0.id }, key: { Self.instant($0.endAt) })
        }
    }

    private static func nameKey(_ value: String?) -> String? {
        guard let trimmed = value?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return trimmed.lowercased()
    }

    private static func instant(_ value: String?) -> Date? {
        guard let text = value?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty else { return nil }
        return ListDates.instant(text)
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

    static func percent(_ value: Double) -> Int {
        min(100, max(0, Int(value.rounded())))
    }

    @MainActor
    static func pickedProgress(_ percentage: Double) -> String {
        L10n.format("%lld%% picked", percent(percentage))
    }

    @MainActor
    static func packingProgress(_ percentage: Double) -> String {
        L10n.format("packing %lld%%", percent(percentage))
    }

    static func quantity(_ value: Double, label: String? = nil) -> String {
        if let label, !label.isEmpty { return FractionGlyphs.apply(label) }
        if value == value.rounded() { return String(Int(value)) }
        return String(format: "%g", value)
    }

    @MainActor
    static func stateLabel(_ state: String, label: String?) -> String {
        if let label, !label.isEmpty { return label }
        return PickingSessionState(rawValue: state)?.title ?? state
    }

    @MainActor
    static func personName(_ person: MobilePickingSessionPerson?, currentUserID: Int?) -> String? {
        guard let person, let name = person.name, !name.isEmpty else { return nil }
        return person.id == currentUserID ? L10n.format("%@ (you)", name) : name
    }
}

extension MobilePickingSessionRow {
    var sessionState: PickingSessionState? { PickingSessionState(rawValue: state) }

    @MainActor
    var stateTitle: String { PickingSessionFormat.stateLabel(state, label: stateLabel) }

    var tone: Tone { sessionState?.tone ?? .neutral }

    @MainActor
    var details: [String] {
        [
            L10n.format("%lld delivery notes", numberDeliveryNotes),
            L10n.format("%lld items", numberItems),
            L10n.format("%lld trolleys", numberTrolleys),
            sessionState?.isPacking == true
                ? PickingSessionFormat.packingProgress(packingPercentage)
                : PickingSessionFormat.pickedProgress(pickingPercentage)
        ]
    }

    func recordDate(in timeZone: TimeZone = .current) -> String? {
        ListDates.recordDate(startAt, in: timeZone) ?? ListDates.recordDate(createdAt, in: timeZone)
    }

    @MainActor
    func recordDateCaption(in timeZone: TimeZone = .current) -> String? {
        guard recordDate(in: timeZone) != nil else { return nil }
        let caption = ListDates.recordDate(startAt, in: timeZone) == nil ? RecordDateCaption.created : RecordDateCaption.started
        return RecordDateCaption.unlessRepeating(caption, status: stateTitle)
    }
}

extension MobilePickingSession {
    var sessionState: PickingSessionState? { PickingSessionState(rawValue: state) }

    @MainActor
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

    @MainActor
    func subtitle(isPacking: Bool) -> String {
        [
            L10n.format("%lld items", numberItems),
            isPacking
                ? PickingSessionFormat.packingProgress(packingPercentage)
                : PickingSessionFormat.pickedProgress(pickingPercentage),
            isForCollection ? L10n.text("Collection") : nil,
            hasWaitingItems ? L10n.text("waiting") : nil
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

    @MainActor
    var toPickText: String {
        L10n.format("%@ to pick", PickingSessionFormat.quantity(quantityToPick, label: quantityToPickLabel))
    }

    @MainActor
    var detail: String {
        let notes = Set(lines.map(\.deliveryNoteId)).count
        let stock = "\(orgStock?.code ?? "") × \(PickingSessionFormat.quantity(quantityToPick, label: quantityToPickLabel))"
        return [stock, L10n.format("%lld delivery notes", notes)].joined(separator: PickingSessionFormat.separator)
    }
}

extension MobilePickListLine {
    var tone: Tone {
        if quantityToPick <= 0 { return .success }
        return quantityPicked > 0 ? .info : .neutral
    }

    @MainActor
    var subtitle: String {
        quantityToPick > 0 ? L10n.format("%@ to pick", PickingSessionFormat.quantity(quantityToPick)) : L10n.text("Picked")
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

    @MainActor
    var place: String {
        [locationCode, deliveryNoteReference.map { L10n.format("Delivery note %@", $0) }]
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

    @MainActor
    var toast: (text: String, tone: Tone) {
        switch self {
        case .feedback(let text, let tone): (text, tone)
        case .lineDone(let code): (L10n.format("%@ · line done", code), .success)
        case .locationDone(let location): (L10n.format("Everything for %@ is picked", location), .success)
        case .allPicked: (L10n.text("Everything in this session is picked"), .success)
        case .nowWaiting: (L10n.text("Picking stopped: the session is waiting for some lines"), .warn)
        case .allPacked: (L10n.text("Every delivery note is packed"), .success)
        }
    }
}

extension MobileSessionTrolley {
    var holderTitle: String { isFree ? "Free" : "In use" }

    var isFree: Bool { currentDeliveryNote == nil && currentPickingSession == nil }

    var holderTone: Tone { isFree ? .success : .info }

    @MainActor
    var holderSubtitle: String? {
        if let note = currentDeliveryNote { return L10n.format("Delivery note %@", note.reference ?? "#\(note.id)") }
        if let session = currentPickingSession { return L10n.format("Picking session %@", session.reference ?? "#\(session.id)") }
        return nil
    }
}

extension MobileSessionPickedBay {
    var isFree: Bool { numberDeliveryNotes <= 0 && deliveryNotes.isEmpty }

    @MainActor
    var subtitle: String? {
        guard !isFree else { return nil }
        let notes = L10n.format("%lld delivery notes", max(numberDeliveryNotes, deliveryNotes.count))
        return numberWaiting > 0 ? [notes, L10n.format("%lld waiting", numberWaiting)].joined(separator: PickingSessionFormat.separator) : notes
    }

    var chip: Chip { isFree ? Chip("Free", .success) : Chip("In use", .info) }
}

extension MobileSessionPickedBay.DeliveryNote {
    var chip: Chip {
        let tone = state.flatMap(GoodsOutDeliveryNoteState.init(apiState:))?.tone ?? .neutral
        return Chip(stateLabel ?? state ?? "", tone)
    }
}

@MainActor
enum PickingSessionErrorText {
    static func message(_ error: Error, offline: String = "This needs internet. Check Wi-Fi and try again.") -> String {
        if let apiError = error as? APIError {
            switch apiError {
            case .offline: return L10n.text(offline)
            case .unauthorized: return L10n.text("Your session has ended. Sign in again.")
            default: return apiError.errorDescription ?? L10n.text("Something went wrong.")
            }
        }
        return error.localizedDescription
    }
}
