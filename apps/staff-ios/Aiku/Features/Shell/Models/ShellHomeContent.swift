import Foundation

struct ShellShortcut: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let icon: AppIcon
    var badge: Int?
    let requirement: ShellAccessRequirement
    var link: ShellLink

    func opening(for modules: ModuleAccess) -> ShellShortcut {
        guard id == "goodsIn", !modules.goodsIn, modules.returns else { return self }
        var shortcut = self
        shortcut.link = .goodsIn(.returns)
        return shortcut
    }
}

struct ShellHomeModule: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let subtitle: String
    let icon: AppIcon
    let link: ShellLink
}

struct ShellClockStatus: Hashable, Sendable {
    let title: String
    let detail: String
    let isClockedIn: Bool
}

enum ShellHomeContent {
    static let staffWorkplace = "Customer services"

    static let shortcuts: [ShellShortcut] = [
        ShellShortcut(id: "goodsOut", title: "Goods out", icon: .faArrowFromLeft, requirement: .goodsOut, link: .goodsOut(.backlog)),
        ShellShortcut(id: "goodsIn", title: "Goods in", icon: .faArrowToBottom, requirement: .goodsInModule, link: .goodsIn(.backlog)),
        ShellShortcut(id: "inventory", title: "Inventory", icon: .faPalletAlt, requirement: .inventory, link: .inventory(.dashboard)),
        ShellShortcut(id: "locations", title: "Locations", icon: .faInventory, requirement: .locations, link: .locations(.warehouse))
    ]

    static func shortcuts(for modules: ModuleAccess, goodsOutUnassigned: Int = 0) -> [ShellShortcut] {
        shortcuts.filter { $0.requirement.isMet(by: modules) }.map { shortcut in
            var shortcut = shortcut.opening(for: modules)
            if shortcut.id == "goodsOut", goodsOutUnassigned > 0 {
                shortcut.badge = goodsOutUnassigned
            }
            return shortcut
        }
    }

    @MainActor
    static func greeting(hour: Int, firstName: String) -> String {
        switch hour {
        case 5..<12: L10n.format("Good morning, %@", firstName)
        case 12..<18: L10n.format("Good afternoon, %@", firstName)
        default: L10n.format("Good evening, %@", firstName)
        }
    }

    static func modules(hasWarehouseAccess: Bool, warehouseLabel: String) -> [ShellHomeModule] {
        guard hasWarehouseAccess else { return [] }
        return [ShellHomeModule(id: "warehouse", title: "Warehouse dashboard", subtitle: warehouseLabel, icon: .faWarehouse, link: .shell(.warehouseDashboard))]
    }

    @MainActor
    static func conflictsText(needsYou: Int) -> String {
        needsYou > 0 ? L10n.format("%lld need you", needsYou) : L10n.text("Nothing needs you")
    }

    @MainActor
    static func clockStatus(_ clocking: ClockingStore) -> ShellClockStatus? {
        guard clocking.hasStatus else {
            return clocking.loadError == nil ? nil : ShellClockStatus(title: L10n.text("Clocking"), detail: L10n.text("Clocking status unavailable"), isClockedIn: false)
        }
        let today = clocking.today
        if today.isClockedIn {
            let since = today.trackers.last.map { ClockingFormat.shortTime($0.inAt) } ?? "—"
            let organisation = clocking.organisationName.flatMap { $0.isEmpty ? nil : $0 }
            return ShellClockStatus(
                title: L10n.format("Clocked in · %@", ClockingFormat.duration(today.working(now: clocking.now))),
                detail: organisation.map { L10n.format("Since %@ · %@", since, $0) } ?? L10n.format("Since %@", since),
                isClockedIn: true
            )
        }
        return ShellClockStatus(
            title: L10n.text("Clocked out"),
            detail: today.lastOut.map { L10n.format("Last clock out %@", ClockingFormat.shortTime($0)) } ?? L10n.text("No clocking today"),
            isClockedIn: false
        )
    }

    @MainActor
    static func weekTotal(_ clocking: ClockingStore) -> String? {
        guard clocking.hasStatus, let week = clocking.weeks.first, week.label == "This week" else { return nil }
        return ClockingFormat.duration(week.working(now: clocking.now))
    }

    @MainActor
    static func context(hasWarehouseAccess: Bool, organisation: String, warehouseName: String) -> String? {
        guard !organisation.isEmpty else { return nil }
        if hasWarehouseAccess {
            return warehouseName.isEmpty ? nil : "\(organisation) · \(warehouseName)"
        }
        return "\(organisation) · \(L10n.text(staffWorkplace))"
    }
}
