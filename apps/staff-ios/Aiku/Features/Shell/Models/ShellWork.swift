import Foundation

enum ShellWorkModule: String, Hashable, Sendable {
    case warehouse = "Warehouse"
    case tasks = "Tasks"
}

enum ShellWorkFilter: Hashable, Sendable, CaseIterable {
    case all
    case warehouse
    case tasks

    var title: String {
        switch self {
        case .all: "All"
        case .warehouse: "Warehouse"
        case .tasks: "Tasks"
        }
    }

    var module: ShellWorkModule? {
        switch self {
        case .all: nil
        case .warehouse: .warehouse
        case .tasks: .tasks
        }
    }
}

struct ShellWorkItem: Identifiable, Hashable, Sendable {
    var id: String { reference }
    let reference: String
    let state: String
    let detail: String
    let module: ShellWorkModule
    let tone: Tone
    let link: ShellLink?
}

enum ShellWork {
    static let items: [ShellWorkItem] = [
        ShellWorkItem(reference: "AR24811", state: "To pick", detail: "Delivery note · 14 items · aisle A–C", module: .warehouse, tone: .warn, link: .goodsOut(.deliveryNote(reference: "AR24811"))),
        ShellWorkItem(reference: "AR24790", state: "To pack", detail: "Delivery note · 6 items · picked 10 min ago", module: .warehouse, tone: .info, link: .goodsOut(.deliveryNote(reference: "AR24790"))),
        ShellWorkItem(reference: "SDaw-0093", state: "To check", detail: "Stock delivery · 6 items received", module: .warehouse, tone: .info, link: .goodsIn(.stockDelivery(reference: "SDaw-0093"))),
        ShellWorkItem(reference: "A-12-03", state: "Audit", detail: "Stock audit · location · 4 SKOs", module: .warehouse, tone: .info, link: .locations(.location(code: "A-12-03"))),
        ShellWorkItem(reference: "acme-011", state: "Booking in", detail: "Fulfilment delivery · [Customer A] · 3 of 4 pallets", module: .warehouse, tone: .info, link: .fulfilment(.delivery(reference: "acme-011"))),
        ShellWorkItem(reference: "[Task]", state: "Due today", detail: "From Aiku Tasks · assigned by [Supervisor]", module: .tasks, tone: .neutral, link: nil)
    ]

    static func visibleItems(_ items: [ShellWorkItem], hasWarehouseAccess: Bool) -> [ShellWorkItem] {
        hasWarehouseAccess ? items : items.filter { $0.module != .warehouse }
    }

    static func filtered(_ items: [ShellWorkItem], by filter: ShellWorkFilter) -> [ShellWorkItem] {
        guard let module = filter.module else { return items }
        return items.filter { $0.module == module }
    }

    static func count(_ items: [ShellWorkItem], for filter: ShellWorkFilter) -> Int {
        filtered(items, by: filter).count
    }

    static func filters(for items: [ShellWorkItem]) -> [ShellWorkFilter] {
        ShellWorkFilter.allCases.filter { $0 == .all || count(items, for: $0) > 0 }
    }
}
