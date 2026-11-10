import Foundation

enum GoodsInDeliveryState: String, CaseIterable, Hashable, Sendable {
    case arriving
    case received
    case checked
    case bookingIn
    case bookedIn
    case placed
    case notReceived
    case cancelled
    case withdrawn

    var title: String {
        switch self {
        case .arriving: "Arriving"
        case .received: "Received"
        case .checked: "Checked"
        case .bookingIn: "Booking in"
        case .bookedIn: "Booked in"
        case .placed: "Placed"
        case .notReceived: "Not received"
        case .cancelled: "Cancelled"
        case .withdrawn: "Not dispatched"
        }
    }

    var tone: Tone {
        switch self {
        case .arriving, .withdrawn: .neutral
        case .received, .checked, .bookingIn: .info
        case .bookedIn, .placed: .success
        case .notReceived, .cancelled: .danger
        }
    }

    var chip: Chip { Chip(title, tone) }

    var isActiveWork: Bool {
        switch self {
        case .arriving, .received, .checked, .bookingIn, .notReceived: true
        case .bookedIn, .placed, .cancelled, .withdrawn: false
        }
    }

    var progressStep: Int? {
        switch self {
        case .arriving: 0
        case .received: 1
        case .checked, .notReceived: 2
        case .bookingIn: 3
        case .bookedIn: 4
        case .placed: 5
        case .cancelled, .withdrawn: nil
        }
    }

    static let progressSteps = ["Dispatched", "Received", "Checked", "Booking in", "Booked in"]
}

enum GoodsInDeliveryPhase: Hashable, Sendable {
    case dispatched
    case inGoodsIn
    case placed
    case cancelled
    case withdrawn
}

enum GoodsInSource: String, CaseIterable, Hashable, Sendable {
    case supplier
    case agent
    case partner
    case production

    var filterTitle: String {
        switch self {
        case .supplier: "Suppliers"
        case .agent: "Agents"
        case .partner: "Partners"
        case .production: "Production"
        }
    }
}

struct GoodsInFact: Hashable, Sendable, Identifiable {
    let label: String
    let value: String

    var id: String { label }

    init(_ label: String, _ value: String) {
        self.label = label
        self.value = value
    }
}

struct GoodsInPlacement: Hashable, Sendable {
    let location: String
    var quantity: Int
}

struct GoodsInLocationOption: Hashable, Sendable, Identifiable {
    let code: String
    let detail: String

    var id: String { code }
}

enum GoodsInCheckStatus: Hashable, Sendable {
    case pending
    case exact
    case under(Int)
    case over(Int)
    case notReceived(Int)

    var chip: Chip? {
        switch self {
        case .pending: nil
        case .exact: Chip("Checked", .success)
        case .under: Chip("Checked · under", .warn)
        case .over: Chip("Checked · over", .info)
        case .notReceived: Chip("Not received", .danger)
        }
    }

    var differenceChip: Chip? {
        switch self {
        case .pending, .exact: nil
        case .under(let quantity): Chip("Under −\(quantity.grouped)", .warn)
        case .over(let quantity): Chip("Over +\(quantity.grouped)", .info)
        case .notReceived(let quantity): Chip("Not received −\(quantity.grouped)", .danger)
        }
    }

    var tone: Tone {
        switch self {
        case .pending: .neutral
        case .exact: .success
        case .under: .warn
        case .over: .info
        case .notReceived: .danger
        }
    }

    var isDifference: Bool {
        switch self {
        case .under, .over, .notReceived: true
        case .pending, .exact: false
        }
    }

    static func classify(checked: Int?, delivered: Int) -> GoodsInCheckStatus {
        guard let checked else { return .pending }
        if checked == 0 { return .notReceived(delivered) }
        if checked < delivered { return .under(delivered - checked) }
        if checked > delivered { return .over(checked - delivered) }
        return .exact
    }
}

struct GoodsInItem: Identifiable, Hashable, Sendable {
    let sko: String
    let name: String
    let delivered: Int
    var checked: Int?
    var placements: [GoodsInPlacement] = []
    var pickingLocation: String?
    var pickingSummary: String?
    var locationOptions: [GoodsInLocationOption] = []
    var suggestedLocation: GoodsInLocationOption?
    var isNewSko = false

    var id: String { sko }

    var checkStatus: GoodsInCheckStatus { .classify(checked: checked, delivered: delivered) }
    var placedQuantity: Int { placements.reduce(0) { $0 + $1.quantity } }
    var leftToPlace: Int { max((checked ?? 0) - placedQuantity, 0) }
    var leftToCheck: Int { max(delivered - (checked ?? 0), 0) }
    var isChecked: Bool { checked != nil }
    var isDone: Bool { checked != nil && placedQuantity >= (checked ?? 0) }
    var isPlaced: Bool { (checked ?? 0) > 0 && isDone }
    var hasPlacements: Bool { placedQuantity > 0 }

    var hasPossibleUnitMismatch: Bool {
        guard let checked, checked > 0, checked != delivered else { return false }
        return checked % delivered == 0 || delivered % checked == 0
    }

    var placementSummary: String {
        placements.map { "\($0.quantity.grouped) in \($0.location)" }.joined(separator: " · ")
    }

    mutating func addPlacement(_ quantity: Int, in location: String) {
        if let index = placements.firstIndex(where: { $0.location == location }) {
            placements[index].quantity += quantity
        } else {
            placements.append(GoodsInPlacement(location: location, quantity: quantity))
        }
    }
}

enum GoodsInCheckResult: Hashable, Sendable {
    case toDo
    case passed
    case failed

    var chip: Chip {
        switch self {
        case .toDo: Chip("To do", .neutral)
        case .passed: Chip("Passed", .success)
        case .failed: Chip("Failed", .danger)
        }
    }
}

struct GoodsInDeliveryCheck: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    var result: GoodsInCheckResult = .toDo
    var detail: String = "Pass or fail"
}

enum GoodsInScanResult: Hashable, Sendable {
    case counted(sko: String, checked: Int, delivered: Int)
    case notOnDelivery(code: String)
    case alreadyPlaced(sko: String)
}

struct GoodsInDelivery: Identifiable, Hashable, Sendable {
    let reference: String
    let source: GoodsInSource
    let sourceName: String
    var phase: GoodsInDeliveryPhase
    var isBookingIn = false
    let dispatchedAt: Date
    var receivedLabel: String?
    var bookedInAt: Date?
    var orderer = "[Supplier name] · [SUP-CODE]"
    var purchaseOrders = "Purchase orders POaw-0211, POaw-0214"
    var estimatedReceiving = "Estimated receiving 11 Nov 2026"
    var deliveryFacts: [GoodsInFact] = []
    var weight = "—"
    var volume: String?
    var items: [GoodsInItem]
    var checks: [GoodsInDeliveryCheck] = []
    var scanFocusSKO: String?

    var id: String { reference }

    var state: GoodsInDeliveryState {
        switch phase {
        case .dispatched: return .arriving
        case .placed: return .placed
        case .cancelled: return .cancelled
        case .withdrawn: return .withdrawn
        case .inGoodsIn: break
        }
        if isBookingIn || items.contains(where: \.hasPlacements) {
            return items.allSatisfy(\.isDone) ? .bookedIn : .bookingIn
        }
        if !items.isEmpty, items.allSatisfy(\.isChecked) {
            return items.allSatisfy { $0.checked == 0 } ? .notReceived : .checked
        }
        return .received
    }

    var checkedCount: Int { items.filter(\.isChecked).count }
    var placeableItems: [GoodsInItem] { items.filter { ($0.checked ?? 0) > 0 } }
    var placedItemCount: Int { placeableItems.filter(\.isPlaced).count }
    var hasAnyPlacement: Bool { items.contains(where: \.hasPlacements) }
    var hasItemsToPlace: Bool { items.contains { $0.leftToPlace > 0 } }
    var newSkoCount: Int { items.filter(\.isNewSko).count }
    var underCount: Int { items.filter { if case .under = $0.checkStatus { true } else { false } }.count }
    var overCount: Int { items.filter { if case .over = $0.checkStatus { true } else { false } }.count }
    var unitMismatchCount: Int { items.filter(\.hasPossibleUnitMismatch).count }
    var differenceItems: [GoodsInItem] { items.filter(\.checkStatus.isDifference) }
    var nextItemToPlace: GoodsInItem? { items.first { $0.leftToPlace > 0 } }
    var nextCheckToDo: GoodsInDeliveryCheck? { checks.first { $0.result == .toDo } }

    var currentScanItem: GoodsInItem? {
        if let scanFocusSKO, let focused = item(scanFocusSKO), !focused.hasPlacements, (focused.checked ?? 0) < focused.delivered {
            return focused
        }
        return items.first { $0.checked == nil }
    }

    func item(_ sko: String) -> GoodsInItem? {
        items.first { $0.sko == sko }
    }

    mutating func markAsReceived(label: String) {
        guard phase == .dispatched else { return }
        phase = .inGoodsIn
        receivedLabel = label
    }

    mutating func unmarkAsReceived() {
        guard phase == .inGoodsIn, !hasAnyPlacement else { return }
        phase = .dispatched
        isBookingIn = false
        receivedLabel = nil
        scanFocusSKO = nil
        for index in items.indices {
            items[index].checked = nil
        }
    }

    mutating func setChecked(_ sko: String, quantity: Int) {
        guard phase == .inGoodsIn, let index = items.firstIndex(where: { $0.sko == sko }) else { return }
        let floor = items[index].placedQuantity
        items[index].checked = max(quantity, floor, 0)
        if scanFocusSKO == sko {
            scanFocusSKO = nil
        }
    }

    mutating func registerScan(_ code: String) -> GoodsInScanResult {
        let normalised = code.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
        guard phase == .inGoodsIn, let index = items.firstIndex(where: { $0.sko.uppercased() == normalised }) else {
            return .notOnDelivery(code: normalised)
        }
        guard !items[index].hasPlacements else { return .alreadyPlaced(sko: items[index].sko) }
        let checked = (items[index].checked ?? 0) + 1
        items[index].checked = checked
        scanFocusSKO = checked < items[index].delivered ? items[index].sko : nil
        return .counted(sko: items[index].sko, checked: checked, delivered: items[index].delivered)
    }

    mutating func startBookingIn() {
        guard phase == .inGoodsIn, items.contains(where: { $0.leftToPlace > 0 }) else { return }
        isBookingIn = true
    }

    @discardableResult
    mutating func place(_ sko: String, quantity: Int, in location: String, setAsPicking: Bool = false, now: Date = .now) -> Int {
        guard phase == .inGoodsIn, let index = items.firstIndex(where: { $0.sko == sko }) else { return 0 }
        let code = location.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
        let amount = min(max(quantity, 0), items[index].leftToPlace)
        guard amount > 0, !code.isEmpty else { return 0 }
        items[index].addPlacement(amount, in: code)
        if setAsPicking {
            items[index].pickingLocation = code
            items[index].pickingSummary = "\(code) for Wholesale"
        }
        isBookingIn = true
        if state == .bookedIn, bookedInAt == nil {
            bookedInAt = now
        }
        return amount
    }

    mutating func setAsPlaced() {
        guard state == .bookedIn else { return }
        phase = .placed
    }

    mutating func cancel() {
        guard !hasAnyPlacement, phase == .dispatched || phase == .inGoodsIn else { return }
        phase = .cancelled
    }

    mutating func unmarkAsDispatched() {
        guard phase == .dispatched else { return }
        phase = .withdrawn
    }

    mutating func recordCheck(_ id: String, passed: Bool, note: String, by name: String, at time: String) {
        guard let index = checks.firstIndex(where: { $0.id == id }) else { return }
        checks[index].result = passed ? .passed : .failed
        let trimmed = note.trimmingCharacters(in: .whitespacesAndNewlines)
        checks[index].detail = passed || trimmed.isEmpty ? "\(name) · \(time)" : "“\(trimmed)” · \(name) · \(time)"
    }

    func listSubtitle(now: Date = .now) -> String {
        let count = items.count == 1 ? "1 item" : "\(items.count) items"
        switch state {
        case .arriving:
            return "\(sourceName) · \(count) · dispatched \(GoodsInDates.dayMonth(dispatchedAt))"
        case .received:
            return "\(sourceName) · \(count) · received \(receivedLabel ?? "today")"
        case .checked:
            return "\(sourceName) · \(count) checked"
        case .notReceived:
            return "\(sourceName) · \(count) · nothing received"
        case .bookingIn:
            return "\(sourceName) · \(placedItemCount) of \(placeableItems.count) items placed"
        case .bookedIn:
            return "\(sourceName) · \(count) · booked in \(GoodsInDates.dayMonth(bookedInAt ?? now))"
        case .placed:
            return "\(sourceName) · \(count) · placed"
        case .cancelled:
            return "\(sourceName) · \(count) · cancelled"
        case .withdrawn:
            return "\(sourceName) · \(count) · not dispatched"
        }
    }
}

enum GoodsInDates {
    static func dayMonth(_ date: Date) -> String {
        date.formatted(.dateTime.day(.twoDigits).month(.abbreviated).locale(Locale(identifier: "en_GB")))
    }

    static func time(_ date: Date) -> String {
        date.formatted(.dateTime.hour(.twoDigits(amPM: .omitted)).minute(.twoDigits).locale(Locale(identifier: "en_GB")))
    }
}
