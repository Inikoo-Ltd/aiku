import Foundation

struct ShellShortcut: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let icon: AppIcon
    var badge: Int?
    let link: ShellLink
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
        ShellShortcut(id: "goodsOut", title: "Goods out", icon: .goodsOut, badge: 12, link: .goodsOut(.deliveryNotes)),
        ShellShortcut(id: "goodsIn", title: "Goods in", icon: .goodsIn, link: .goodsIn(.stockDeliveries)),
        ShellShortcut(id: "inventory", title: "Inventory", icon: .inventory, link: .inventory(.skos)),
        ShellShortcut(id: "locations", title: "Locations", icon: .locations, link: .locations(.locations))
    ]

    static func greeting(hour: Int, firstName: String) -> String {
        let part = switch hour {
        case 5..<12: "Good morning"
        case 12..<18: "Good afternoon"
        default: "Good evening"
        }
        return "\(part), \(firstName)"
    }

    static func modules(hasWarehouseAccess: Bool, warehouseLabel: String) -> [ShellHomeModule] {
        var modules: [ShellHomeModule] = []
        if hasWarehouseAccess {
            modules.append(ShellHomeModule(id: "warehouse", title: "Warehouse dashboard", subtitle: warehouseLabel, icon: .warehouse, link: .shell(.warehouseDashboard)))
        }
        modules.append(ShellHomeModule(
            id: "hr",
            title: "Clocking & HR",
            subtitle: hasWarehouseAccess ? "This week 31h 40m" : "This week 29h 05m",
            icon: .clock,
            link: .clocking(.clocking)
        ))
        return modules
    }

    static func clockStatus(hasWarehouseAccess: Bool, warehouseName: String) -> ShellClockStatus {
        hasWarehouseAccess
            ? ShellClockStatus(title: "Clocked in · 6h 13m", detail: "Since 08:02 · \(warehouseName)", isClockedIn: true)
            : ShellClockStatus(title: "Clocked out", detail: "Last clocking 14:15 · Head office", isClockedIn: false)
    }

    static func context(hasWarehouseAccess: Bool, organisation: String, warehouseName: String) -> String {
        "\(organisation) · \(hasWarehouseAccess ? warehouseName : staffWorkplace)"
    }
}
