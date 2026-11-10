import Foundation

enum ShellAccessRequirement: Hashable, Sendable {
    case allStaff
    case warehouse
    case fulfilment

    func isMet(hasWarehouseAccess: Bool, hasFulfilmentAccess: Bool) -> Bool {
        switch self {
        case .allStaff: true
        case .warehouse: hasWarehouseAccess
        case .fulfilment: hasFulfilmentAccess
        }
    }
}

enum ShellMenuBadge: Hashable, Sendable {
    case pill(Chip)
    case fulfilmentTag
}

struct ShellMenuItem: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    var subtitle: String?
    var subtitleTone: Tone?
    let icon: AppIcon
    var iconTone: Tone = .info
    var badge: ShellMenuBadge?
    let requirement: ShellAccessRequirement
    let link: ShellLink
}

struct ShellMenuGroup: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let icon: AppIcon
    var note: String?
    let items: [ShellMenuItem]
}

enum ShellMenuCatalog {
    static let allGroups: [ShellMenuGroup] = [
        ShellMenuGroup(id: "inventory", title: "Inventory", icon: .inventory, items: [
            ShellMenuItem(id: "inventory.dashboard", title: "Dashboard", icon: .dashboard, requirement: .warehouse, link: .inventory(.dashboard)),
            ShellMenuItem(id: "inventory.families", title: "SKO families", icon: .skoFamilies, requirement: .warehouse, link: .inventory(.families)),
            ShellMenuItem(id: "inventory.skos", title: "SKOs", icon: .sko, badge: .pill(Chip("17 low", .warn)), requirement: .warehouse, link: .inventory(.skos)),
            ShellMenuItem(id: "inventory.pallets", title: "Stored pallets", icon: .sko, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.pallets)),
            ShellMenuItem(id: "inventory.storedItems", title: "Stored items", icon: .sko, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.storedItems)),
            ShellMenuItem(id: "inventory.history", title: "Stock history", icon: .history, requirement: .warehouse, link: .inventory(.stockHistory))
        ]),
        ShellMenuGroup(id: "locations", title: "Locations", icon: .locations, items: [
            ShellMenuItem(id: "locations.warehouse", title: "Warehouse", icon: .warehouse, requirement: .warehouse, link: .locations(.warehouse)),
            ShellMenuItem(id: "locations.areas", title: "Areas", icon: .areas, requirement: .warehouse, link: .locations(.areas)),
            ShellMenuItem(id: "locations.locations", title: "Locations", icon: .locations, requirement: .warehouse, link: .locations(.locations))
        ]),
        ShellMenuGroup(id: "goodsIn", title: "Goods in", icon: .goodsIn, items: [
            ShellMenuItem(id: "goodsIn.backlog", title: "Backlog", icon: .backlog, requirement: .warehouse, link: .goodsIn(.backlog)),
            ShellMenuItem(id: "goodsIn.stockDeliveries", title: "Stock deliveries", icon: .stockDelivery, badge: .pill(Chip("2 received", .success)), requirement: .warehouse, link: .goodsIn(.stockDeliveries)),
            ShellMenuItem(id: "goodsIn.fulfilmentDeliveries", title: "Fulfilment deliveries", icon: .stockDelivery, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.deliveries)),
            ShellMenuItem(id: "goodsIn.returns", title: "Returns", icon: .stockDelivery, requirement: .warehouse, link: .goodsIn(.returns))
        ]),
        ShellMenuGroup(id: "goodsOut", title: "Goods out", icon: .goodsOut, items: [
            ShellMenuItem(id: "goodsOut.backlog", title: "Backlog", icon: .backlog, badge: .pill(Chip("12 unassigned", .neutral)), requirement: .warehouse, link: .goodsOut(.backlog)),
            ShellMenuItem(id: "goodsOut.deliveryNotes", title: "Delivery notes", icon: .deliveryNote, requirement: .warehouse, link: .goodsOut(.deliveryNotes)),
            ShellMenuItem(id: "goodsOut.fulfilmentReturns", title: "Fulfilment returns", icon: .deliveryNote, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.returns)),
            ShellMenuItem(id: "goodsOut.pickingSessions", title: "Picking sessions", icon: .deliveryNote, requirement: .warehouse, link: .dispatch(.pickingSessions)),
            ShellMenuItem(id: "goodsOut.shippers", title: "Shippers", icon: .shipment, requirement: .warehouse, link: .dispatch(.shippers)),
            ShellMenuItem(id: "goodsOut.trolleys", title: "Trolleys", icon: .shipment, requirement: .warehouse, link: .dispatch(.trolleys)),
            ShellMenuItem(id: "goodsOut.pickedBays", title: "Picked bays", icon: .shipment, requirement: .warehouse, link: .dispatch(.pickedBays))
        ]),
        ShellMenuGroup(id: "clocking", title: "Clocking & HR", icon: .clock, note: "All staff", items: [
            ShellMenuItem(id: "clocking.clocking", title: "Clocking", subtitle: "Clocked in since 08:02", subtitleTone: .success, icon: .clock, iconTone: .success, requirement: .allStaff, link: .clocking(.clocking)),
            ShellMenuItem(id: "clocking.timesheets", title: "Timesheets", icon: .backlog, requirement: .allStaff, link: .clocking(.timesheets))
        ])
    ]

    static func groups(hasWarehouseAccess: Bool, hasFulfilmentAccess: Bool) -> [ShellMenuGroup] {
        allGroups.compactMap { group in
            let visible = group.items.filter {
                $0.requirement.isMet(hasWarehouseAccess: hasWarehouseAccess, hasFulfilmentAccess: hasFulfilmentAccess)
            }
            guard !visible.isEmpty else { return nil }
            return ShellMenuGroup(id: group.id, title: group.title, icon: group.icon, note: group.note, items: visible)
        }
    }
}
