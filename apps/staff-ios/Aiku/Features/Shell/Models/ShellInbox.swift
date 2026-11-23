import Foundation
import Observation

struct InboxService: Sendable {
    var page: @Sendable (_ page: Int) async throws -> MobileInboxPage
    var unreadCount: @Sendable () async throws -> Int
    var markRead: @Sendable (_ id: String) async throws -> MobileInboxNotification
    var markUnread: @Sendable (_ id: String) async throws -> MobileInboxNotification
    var markAllRead: @Sendable () async throws -> Int

    static func live(_ client: APIClient) -> InboxService {
        InboxService(
            page: { try await client.fetchInbox(page: $0) },
            unreadCount: { try await client.fetchInboxUnreadCount() },
            markRead: { try await client.markInboxRead(id: $0) },
            markUnread: { try await client.markInboxUnread(id: $0) },
            markAllRead: { try await client.markAllInboxRead() }
        )
    }
}

@MainActor
@Observable
final class ShellInboxStore {
    static let shared = ShellInboxStore(service: .live(.shared))

    private(set) var notifications: [MobileInboxNotification] = []
    private(set) var page = 0
    private(set) var lastPage = 0
    private(set) var unread: Int?
    private(set) var loadingPage: Int?
    private(set) var loadError: String?
    private(set) var loadMoreError: String?

    @ObservationIgnored private let service: InboxService
    @ObservationIgnored private let clock: @Sendable () -> Date
    @ObservationIgnored private var writesInFlight = 0
    @ObservationIgnored private var generation = 0

    init(service: InboxService, clock: @escaping @Sendable () -> Date = { .now }) {
        self.service = service
        self.clock = clock
    }

    var hasLoaded: Bool { page > 0 }
    var isEmpty: Bool { hasLoaded && notifications.isEmpty && loadError == nil }
    var canLoadMore: Bool { hasLoaded && page < lastPage && loadingPage == nil && loadMoreError == nil }
    var isLoadingMore: Bool { (loadingPage ?? 0) > 1 }
    var unreadCount: Int { max(unread ?? notifications.filter(\.isUnread).count, 0) }

    func load() async {
        await loadPage(1)
    }

    func loadMore() async {
        guard canLoadMore else { return }
        await loadPage(page + 1)
    }

    func retryMore() async {
        loadMoreError = nil
        await loadPage(page + 1)
    }

    func refreshUnread() async {
        let generation = generation
        guard let count = try? await service.unreadCount(), generation == self.generation else { return }
        applyUnread(count)
    }

    func applyUnread(_ count: Int?) {
        guard let count, writesInFlight == 0 else { return }
        unread = max(count, 0)
    }

    @discardableResult
    func markRead(_ id: String) async -> Bool {
        guard let index = notifications.firstIndex(where: { $0.id == id }), notifications[index].isUnread else { return true }
        let stamp = Self.timestamp(clock())
        notifications[index].readAt = stamp
        unread = unread.map { max($0 - 1, 0) }
        return await write(id: id, request: service.markRead) { store in
            if store.undo(id: id, when: { $0.readAt == stamp }, change: { $0.readAt = nil }) {
                store.unread = store.unread.map { $0 + 1 }
            }
        }
    }

    @discardableResult
    func markUnread(_ id: String) async -> Bool {
        guard let index = notifications.firstIndex(where: { $0.id == id }), !notifications[index].isUnread else { return true }
        let previous = notifications[index].readAt
        notifications[index].readAt = nil
        unread = unread.map { $0 + 1 }
        return await write(id: id, request: service.markUnread) { store in
            if store.undo(id: id, when: { $0.readAt == nil }, change: { $0.readAt = previous }) {
                store.unread = store.unread.map { max($0 - 1, 0) }
            }
        }
    }

    @discardableResult
    func markAllRead() async -> Bool {
        let generation = generation
        let stamp = Self.timestamp(clock())
        let unreadIDs = Set(notifications.filter(\.isUnread).map(\.id))
        let unreadBefore = unread
        for index in notifications.indices where notifications[index].isUnread {
            notifications[index].readAt = stamp
        }
        unread = 0
        writesInFlight += 1
        defer { writesInFlight -= 1 }
        do {
            let remaining = try await service.markAllRead()
            guard generation == self.generation else { return true }
            unread = max(remaining, 0)
            return true
        } catch {
            guard generation == self.generation else { return false }
            for index in notifications.indices where unreadIDs.contains(notifications[index].id) && notifications[index].readAt == stamp {
                notifications[index].readAt = nil
            }
            unread = unreadBefore
            return false
        }
    }

    func reset() {
        generation += 1
        notifications = []
        page = 0
        lastPage = 0
        unread = nil
        loadingPage = nil
        loadError = nil
        loadMoreError = nil
    }

    private func write(
        id: String,
        request: @Sendable (String) async throws -> MobileInboxNotification,
        rollback: (ShellInboxStore) -> Void
    ) async -> Bool {
        let generation = generation
        writesInFlight += 1
        defer { writesInFlight -= 1 }
        do {
            let updated = try await request(id)
            guard generation == self.generation else { return true }
            if let index = notifications.firstIndex(where: { $0.id == updated.id }) {
                notifications[index] = updated
            }
            return true
        } catch {
            guard generation == self.generation else { return false }
            rollback(self)
            return false
        }
    }

    private func undo(id: String, when isStillOptimistic: (MobileInboxNotification) -> Bool, change: (inout MobileInboxNotification) -> Void) -> Bool {
        guard let index = notifications.firstIndex(where: { $0.id == id }), isStillOptimistic(notifications[index]) else { return false }
        change(&notifications[index])
        return true
    }

    private func loadPage(_ requested: Int) async {
        let generation = generation
        loadingPage = requested
        if requested == 1 { loadError = nil }
        loadMoreError = nil
        do {
            let result = try await service.page(requested)
            guard generation == self.generation else { return }
            if result.currentPage <= 1 {
                notifications = result.notifications
            } else {
                let known = Set(notifications.map(\.id))
                notifications += result.notifications.filter { !known.contains($0.id) }
            }
            page = result.currentPage
            lastPage = result.lastPage
            loadingPage = nil
            applyUnread(result.unread)
        } catch where error.isCancellation {
            if generation == self.generation { loadingPage = nil }
        } catch {
            guard generation == self.generation else { return }
            loadingPage = nil
            if requested == 1 {
                loadError = PickingSessionErrorText.message(error, offline: "The inbox needs internet. Check Wi-Fi and try again.")
            } else {
                loadMoreError = PickingSessionErrorText.message(error)
            }
        }
    }

    private static func timestamp(_ date: Date) -> String {
        ISO8601DateFormatter().string(from: date)
    }
}

enum InboxSection: String, Hashable, Sendable, CaseIterable {
    case today = "Today"
    case earlier = "Earlier"
}

struct InboxLook: Equatable, Sendable {
    let icon: AppIcon
    let tone: Tone
}

enum InboxOpening: Equatable, Sendable {
    case textOnly
    case unavailable
    case record(MobileInboxTarget, switchTo: MobileWarehouse?)
}

@MainActor
enum InboxFormat {
    static func opening(for notification: MobileInboxNotification, currentWarehouseID: Int?, warehouses: [MobileWarehouse]) -> InboxOpening {
        guard let target = notification.target else { return .textOnly }
        guard let warehouseID = target.warehouseID else { return .unavailable }
        if warehouseID == currentWarehouseID, warehouses.contains(where: { $0.id == warehouseID }) {
            return .record(target, switchTo: nil)
        }
        guard let warehouse = warehouses.first(where: { $0.id == warehouseID }) else { return .unavailable }
        return .record(target, switchTo: warehouse)
    }

    static func switchedText(_ warehouse: MobileWarehouse) -> String {
        L10n.format("Switched to %@ · %@ to open this", warehouse.organisation.name, warehouse.name)
    }

    static func unreadText(_ count: Int) -> String {
        count > 99 ? "99+" : "\(max(count, 0))"
    }

    static func kicker(unread: Int) -> String {
        unread > 0 ? L10n.format("%@ unread", unreadText(unread)) : L10n.text("All read")
    }

    static func headline(_ notification: MobileInboxNotification) -> String {
        notification.title.isEmpty ? notification.body : notification.title
    }

    static func detail(_ notification: MobileInboxNotification) -> String? {
        notification.title.isEmpty || notification.body.isEmpty ? nil : notification.body
    }

    static func section(_ notification: MobileInboxNotification, now: Date, calendar: Calendar = .current) -> InboxSection {
        guard let text = notification.createdAt, let date = StaffChatFormat.parse(text), calendar.isDate(date, inSameDayAs: now) else {
            return .earlier
        }
        return .today
    }

    static func notifications(_ notifications: [MobileInboxNotification], in section: InboxSection, now: Date, calendar: Calendar = .current) -> [MobileInboxNotification] {
        notifications.filter { Self.section($0, now: now, calendar: calendar) == section }
    }

    static func look(kind: String, event: String) -> InboxLook {
        switch event {
        case "assigned": InboxLook(icon: kindIcon(kind), tone: .info)
        case "taken_over": InboxLook(icon: .person, tone: .warn)
        case "unassigned": InboxLook(icon: .xCircle, tone: .warn)
        case "cancelled": InboxLook(icon: .close, tone: .danger)
        case "ready_again": InboxLook(icon: .check, tone: .success)
        case "modified": InboxLook(icon: .edit, tone: .warn)
        default: InboxLook(icon: kindIcon(kind), tone: .neutral)
        }
    }

    static func iconBlendsIntoRow(tone: Tone, isUnread: Bool) -> Bool {
        isUnread && tone == .info
    }

    private static func kindIcon(_ kind: String) -> AppIcon {
        switch MobileMyWorkKind(rawValue: kind) {
        case .deliveryNote: .deliveryNote
        case .pickingSession: .trolley
        case .customerReturn: .goodsIn
        case .palletReturn: .pallet
        case nil: .bell
        }
    }
}
