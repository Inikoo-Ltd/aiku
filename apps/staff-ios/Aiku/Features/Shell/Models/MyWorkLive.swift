import Foundation
import Observation

@MainActor
@Observable
final class WorkBadgesStore {
    static let shared = WorkBadgesStore(inbox: ShellInboxStore.shared, cache: .shared)

    private(set) var badges = MobileWorkBadges()
    private(set) var warehouseID: Int?

    @ObservationIgnored private let inbox: ShellInboxStore?
    @ObservationIgnored private let cache: ScreenCache?

    init(inbox: ShellInboxStore? = nil, cache: ScreenCache? = nil) {
        self.inbox = inbox
        self.cache = cache
    }

    var myWorkCount: Int { Self.visible(badges.myWork) }
    var goodsOutUnassignedCount: Int { Self.visible(badges.goodsOutUnassigned) }
    var goodsInReceivedCount: Int { Self.visible(badges.goodsInReceived) }

    static func visible(_ count: Int?) -> Int { max(count ?? 0, 0) }

    func refresh(warehouseID: Int?, apiClient: APIClient) async {
        if warehouseID != self.warehouseID {
            badges = MobileWorkBadges()
            self.warehouseID = warehouseID
            if let warehouseID {
                showSavedCopy(warehouseID: warehouseID)
            }
        }
        guard let warehouseID else { return }
        do {
            let fetched = try await apiClient.fetchWorkBadges(warehouseID: warehouseID)
            guard warehouseID == self.warehouseID else { return }
            badges = fetched
            inbox?.applyUnread(fetched.inboxUnread)
            cache?.save(fetched, key: ScreenCacheKey.workBadges(warehouseID: warehouseID))
        } catch where error.isCancellation {
            return
        } catch let error as APIError {
            if Self.keepsBadges(after: error) { return }
            badges = MobileWorkBadges()
            cache?.remove(ScreenCacheKey.workBadges(warehouseID: warehouseID))
        } catch {
            return
        }
    }

    private func showSavedCopy(warehouseID: Int) {
        guard let saved = cache?.read(MobileWorkBadges.self, key: ScreenCacheKey.workBadges(warehouseID: warehouseID)) else { return }
        badges = saved.value
        inbox?.applyUnread(saved.value.inboxUnread)
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

@MainActor
enum MyWorkText {
    static func kind(_ kind: MobileMyWorkKind) -> String {
        switch kind {
        case .deliveryNote: L10n.text("Delivery note")
        case .pickingSession: L10n.text("Picking session")
        case .customerReturn: L10n.text("Customer return")
        case .palletReturn: L10n.text("Fulfilment return")
        }
    }

    static func size(_ row: MobileMyWorkRow) -> String? {
        guard let count = row.items else { return nil }
        return switch row.kind {
        case .deliveryNote: L10n.format("%lld lines", count)
        case .pickingSession: L10n.format("%lld delivery notes", count)
        case .customerReturn: L10n.format("%lld items", count)
        case .palletReturn: row.typeGroup == "items" ? L10n.format("%lld stored items", count) : L10n.format("%lld pallets", count)
        }
    }

    static func trolleys(_ names: [String]) -> String? {
        guard !names.isEmpty else { return nil }
        let joined = names.joined(separator: ", ")
        return names.count == 1 ? L10n.format("Trolley %@", joined) : L10n.format("Trolleys %@", joined)
    }

    static func since(_ text: String?, now: Date) -> String? {
        guard let text, let start = date(text) else { return nil }
        let minutes = Int(now.timeIntervalSince(start) / 60)
        if minutes < 1 { return L10n.text("just now") }
        if minutes < 60 { return L10n.format("%lld min ago", minutes) }
        if minutes < 1440 { return L10n.format("%lld hours ago", minutes / 60) }
        return L10n.format("%lld days ago", minutes / 1440)
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

    private static func date(_ text: String) -> Date? {
        let fractional = ISO8601DateFormatter()
        fractional.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return fractional.date(from: text) ?? ISO8601DateFormatter().date(from: text)
    }
}
