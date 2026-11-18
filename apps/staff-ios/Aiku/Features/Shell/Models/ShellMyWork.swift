import Foundation
import Observation

enum MyWorkFilter: String, CaseIterable, Hashable, Sendable {
    case all
    case deliveryNotes
    case pickingSessions
    case returns
    case palletReturns

    var title: String {
        switch self {
        case .all: "All"
        case .deliveryNotes: "Delivery notes"
        case .pickingSessions: "Picking sessions"
        case .returns: "Returns"
        case .palletReturns: "Pallet returns"
        }
    }

    var kind: MobileMyWorkKind? {
        switch self {
        case .all: nil
        case .deliveryNotes: .deliveryNote
        case .pickingSessions: .pickingSession
        case .returns: .customerReturn
        case .palletReturns: .palletReturn
        }
    }

    func count(in counts: MobileMyWorkCounts) -> Int {
        switch self {
        case .all: counts.total
        case .deliveryNotes: counts.deliveryNotes
        case .pickingSessions: counts.pickingSessions
        case .returns: counts.returns
        case .palletReturns: counts.palletReturns
        }
    }
}

struct MyWorkService: Sendable {
    var fetch: @Sendable (Int) async throws -> MobileMyWorkPage

    static func live(_ client: APIClient) -> MyWorkService {
        MyWorkService(fetch: { try await client.fetchMyWork(warehouseID: $0) })
    }
}

@MainActor
@Observable
final class MyWorkStore {
    static let shared = MyWorkStore()

    private(set) var rows: [MobileMyWorkRow] = []
    private(set) var counts = MobileMyWorkCounts()
    private(set) var hasLoaded = false
    private(set) var isLoading = false
    private(set) var loadError: String?
    private(set) var warehouseID: Int?

    func load(warehouseID: Int, service: MyWorkService) async {
        if self.warehouseID != warehouseID {
            rows = []
            counts = MobileMyWorkCounts()
            hasLoaded = false
        }
        self.warehouseID = warehouseID
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await service.fetch(warehouseID)
            guard self.warehouseID == warehouseID else { return }
            rows = page.data
            counts = page.counts ?? MyWorkStore.counts(for: page.data)
            hasLoaded = true
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = InventoryLive.message(for: error)
        }
    }

    func reset() {
        rows = []
        counts = MobileMyWorkCounts()
        hasLoaded = false
        loadError = nil
        warehouseID = nil
    }

    func filters() -> [MyWorkFilter] {
        MyWorkFilter.allCases.filter { $0 == .all || $0.count(in: counts) > 0 }
    }

    func rows(for filter: MyWorkFilter) -> [MobileMyWorkRow] {
        guard let kind = filter.kind else { return rows }
        return rows.filter { $0.kind == kind }
    }

    static func counts(for rows: [MobileMyWorkRow]) -> MobileMyWorkCounts {
        MobileMyWorkCounts(
            total: rows.count,
            deliveryNotes: rows.filter { $0.kind == .deliveryNote }.count,
            pickingSessions: rows.filter { $0.kind == .pickingSession }.count,
            returns: rows.filter { $0.kind == .customerReturn }.count,
            palletReturns: rows.filter { $0.kind == .palletReturn }.count
        )
    }
}

enum MyWorkFormat {
    static func link(for row: MobileMyWorkRow) -> ShellLink? {
        switch row.kind {
        case .deliveryNote: .goodsOut(.deliveryNoteID(id: row.id))
        case .pickingSession: .dispatch(.pickingSessionID(id: row.id))
        case .customerReturn: .goodsIn(.returnID(id: row.id, reference: row.reference))
        case .palletReturn: nil
        }
    }

    static func tone(for step: String) -> Tone {
        switch step {
        case "waiting": .warn
        case "to_pick", "to_pack", "to_start", "to_dispatch": .info
        default: .neutral
        }
    }

    static func stepTitle(_ row: MobileMyWorkRow) -> String {
        [row.stepLabel, row.stateLabel, row.step].compactMap { $0 }.first { !$0.isEmpty } ?? ""
    }

    static func subtitle(_ row: MobileMyWorkRow, now: Date) -> String {
        [
            kindTitle(row.kind),
            row.items.map { size(row, $0) },
            row.customer.flatMap { $0.isEmpty ? nil : $0 },
            row.trolleys.isEmpty ? nil : (row.trolleys.count == 1 ? "Trolley " : "Trolleys ") + row.trolleys.joined(separator: ", "),
            elapsed(since: row.since, now: now)
        ]
        .compactMap { $0 }
        .joined(separator: " · ")
    }

    static func kindTitle(_ kind: MobileMyWorkKind) -> String {
        switch kind {
        case .deliveryNote: "Delivery note"
        case .pickingSession: "Picking session"
        case .customerReturn: "Return"
        case .palletReturn: "Pallet return"
        }
    }

    static func size(_ row: MobileMyWorkRow, _ count: Int) -> String {
        let noun = switch row.kind {
        case .deliveryNote: count == 1 ? "item" : "items"
        case .pickingSession: count == 1 ? "delivery note" : "delivery notes"
        case .customerReturn: count == 1 ? "item" : "items"
        case .palletReturn where row.typeGroup == "items": count == 1 ? "stored item" : "stored items"
        case .palletReturn: count == 1 ? "pallet" : "pallets"
        }
        return "\(count) \(noun)"
    }

    static func elapsed(since text: String?, now: Date) -> String? {
        guard let text, let start = parse(text) else { return nil }
        let minutes = Int(now.timeIntervalSince(start) / 60)
        switch minutes {
        case ..<1: return "just now"
        case ..<60: return "\(minutes) min ago"
        case ..<(60 * 24): return "\(minutes / 60) h ago"
        default:
            let days = minutes / (60 * 24)
            return days == 1 ? "1 day ago" : "\(days) days ago"
        }
    }

    private static func parse(_ text: String) -> Date? {
        let withFraction = ISO8601DateFormatter()
        withFraction.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return ISO8601DateFormatter().date(from: text) ?? withFraction.date(from: text)
    }
}
