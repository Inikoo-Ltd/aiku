import Foundation
import Observation

@MainActor
@Observable
final class WorkBadgesStore {
    static let shared = WorkBadgesStore()

    private(set) var badges = MobileWorkBadges()
    private(set) var warehouseID: Int?

    var myWorkCount: Int { Self.visible(badges.myWork) }
    var goodsOutUnassignedCount: Int { Self.visible(badges.goodsOutUnassigned) }

    static func visible(_ count: Int?) -> Int { max(count ?? 0, 0) }

    func refresh(warehouseID: Int?, apiClient: APIClient) async {
        if warehouseID != self.warehouseID {
            badges = MobileWorkBadges()
            self.warehouseID = warehouseID
        }
        guard let warehouseID else { return }
        do {
            let fetched = try await apiClient.fetchWorkBadges(warehouseID: warehouseID)
            guard warehouseID == self.warehouseID else { return }
            badges = fetched
        } catch where error.isCancellation {
            return
        } catch let error as APIError {
            if Self.keepsBadges(after: error) { return }
            badges = MobileWorkBadges()
        } catch {
            return
        }
    }

    static func keepsBadges(after error: APIError) -> Bool {
        switch error {
        case .offline, .invalidResponse, .unexpectedBody: true
        case .http(let statusCode): statusCode >= 500
        default: false
        }
    }

    func reset() {
        badges = MobileWorkBadges()
        warehouseID = nil
    }
}

@MainActor
@Observable
final class MyWorkFeed {
    private(set) var rows: [MobileMyWorkRow] = []
    private(set) var hasLoaded = false
    private(set) var isLoading = false
    private(set) var loadError: String?
    private(set) var loadedWarehouseID: Int?

    var isEmpty: Bool { hasLoaded && rows.isEmpty && loadError == nil }

    func reload(warehouseID: Int, apiClient: APIClient) async {
        if loadedWarehouseID != warehouseID {
            rows = []
            hasLoaded = false
        }
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await apiClient.fetchMyWork(warehouseID: warehouseID)
            rows = page.rows
            loadedWarehouseID = warehouseID
            hasLoaded = true
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = PickingSessionErrorText.message(error)
        }
    }
}

enum MyWorkText {
    static func kind(_ kind: MobileMyWorkKind) -> String {
        switch kind {
        case .deliveryNote: "Delivery note"
        case .pickingSession: "Picking session"
        case .customerReturn: "Customer return"
        case .palletReturn: "Fulfilment return"
        }
    }

    static func size(_ row: MobileMyWorkRow) -> String? {
        guard let count = row.items else { return nil }
        return switch row.kind {
        case .deliveryNote: plural(count, "line", "lines")
        case .pickingSession: plural(count, "delivery note", "delivery notes")
        case .customerReturn: plural(count, "item", "items")
        case .palletReturn: row.typeGroup == "items" ? plural(count, "stored item", "stored items") : plural(count, "pallet", "pallets")
        }
    }

    static func trolleys(_ names: [String]) -> String? {
        guard !names.isEmpty else { return nil }
        return "\(names.count == 1 ? "Trolley" : "Trolleys") \(names.joined(separator: ", "))"
    }

    static func since(_ text: String?, now: Date) -> String? {
        guard let text, let start = date(text) else { return nil }
        let minutes = Int(now.timeIntervalSince(start) / 60)
        if minutes < 1 { return "just now" }
        if minutes < 60 { return "\(plural(minutes, "min", "min")) ago" }
        if minutes < 1440 { return "\(plural(minutes / 60, "hour", "hours")) ago" }
        return "\(plural(minutes / 1440, "day", "days")) ago"
    }

    static func subtitle(_ row: MobileMyWorkRow, now: Date) -> String {
        [kind(row.kind), size(row), row.customer, trolleys(row.trolleys), since(row.since, now: now)]
            .compactMap { $0 }
            .joined(separator: " · ")
    }

    static func chip(_ row: MobileMyWorkRow) -> String {
        [row.stepLabel, row.stateLabel, row.step].first { !$0.isEmpty } ?? ""
    }

    static func tone(_ step: String) -> Tone {
        switch step {
        case "waiting": .warn
        case "to_pick", "to_pack", "to_start", "to_dispatch": .info
        default: .neutral
        }
    }

    private static func plural(_ count: Int, _ one: String, _ other: String) -> String {
        "\(count) \(count == 1 ? one : other)"
    }

    private static func date(_ text: String) -> Date? {
        let fractional = ISO8601DateFormatter()
        fractional.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return fractional.date(from: text) ?? ISO8601DateFormatter().date(from: text)
    }
}
