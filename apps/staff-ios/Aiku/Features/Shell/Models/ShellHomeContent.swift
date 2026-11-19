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
        ShellShortcut(id: "goodsOut", title: "Goods out", icon: .goodsOut, requirement: .goodsOut, link: .goodsOut(.deliveryNotes)),
        ShellShortcut(id: "goodsIn", title: "Goods in", icon: .goodsIn, requirement: .goodsInModule, link: .goodsIn(.stockDeliveries)),
        ShellShortcut(id: "inventory", title: "Inventory", icon: .inventory, requirement: .inventory, link: .inventory(.skos)),
        ShellShortcut(id: "locations", title: "Locations", icon: .locations, requirement: .locations, link: .locations(.locations))
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

    static func greeting(hour: Int, firstName: String) -> String {
        let part = switch hour {
        case 5..<12: "Good morning"
        case 12..<18: "Good afternoon"
        default: "Good evening"
        }
        return "\(part), \(firstName)"
    }

    static func modules(hasWarehouseAccess: Bool, warehouseLabel: String, weekTotal: String?) -> [ShellHomeModule] {
        var modules: [ShellHomeModule] = []
        if hasWarehouseAccess {
            modules.append(ShellHomeModule(id: "warehouse", title: "Warehouse dashboard", subtitle: warehouseLabel, icon: .warehouse, link: .shell(.warehouseDashboard)))
        }
        modules.append(ShellHomeModule(
            id: "hr",
            title: "Clocking & HR",
            subtitle: weekTotal.map { "This week \($0)" } ?? "—",
            icon: .clock,
            link: .clocking(.clocking)
        ))
        return modules
    }

    @MainActor
    static func clockStatus(_ clocking: ClockingStore) -> ShellClockStatus? {
        guard clocking.hasLoaded else {
            return clocking.loadError == nil ? nil : ShellClockStatus(title: "Clocking", detail: "Clocking status unavailable", isClockedIn: false)
        }
        let today = clocking.today
        if today.isClockedIn {
            let since = today.trackers.last.map { ClockingFormat.shortTime($0.inAt) } ?? "—"
            let organisation = clocking.organisationName.flatMap { $0.isEmpty ? nil : " · \($0)" } ?? ""
            return ShellClockStatus(
                title: "Clocked in · \(ClockingFormat.duration(today.working(now: clocking.now)))",
                detail: "Since \(since)\(organisation)",
                isClockedIn: true
            )
        }
        return ShellClockStatus(
            title: "Clocked out",
            detail: today.lastOut.map { "Last clock out \(ClockingFormat.shortTime($0))" } ?? "No clocking today",
            isClockedIn: false
        )
    }

    @MainActor
    static func weekTotal(_ clocking: ClockingStore) -> String? {
        guard clocking.hasLoaded, let week = clocking.weeks.first, week.label == "This week" else { return nil }
        return ClockingFormat.duration(week.working(now: clocking.now))
    }

    static func context(hasWarehouseAccess: Bool, organisation: String, warehouseName: String) -> String {
        "\(organisation) · \(hasWarehouseAccess ? warehouseName : staffWorkplace)"
    }
}
