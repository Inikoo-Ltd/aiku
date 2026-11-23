import Foundation

enum ShellAccessRequirement: Hashable, Sendable {
    case allStaff
    case inventory
    case locations
    case goodsIn
    case returns
    case goodsInModule
    case goodsOut
    case fulfilment

    func isMet(by modules: ModuleAccess) -> Bool {
        switch self {
        case .allStaff: true
        case .inventory: modules.inventory
        case .locations: modules.locations
        case .goodsIn: modules.goodsIn
        case .returns: modules.returns
        case .goodsInModule: modules.hasGoodsInModule
        case .goodsOut: modules.goodsOut
        case .fulfilment: modules.fulfilment
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

struct ShellMenuClocking: Hashable, Sendable {
    let subtitle: String
    let isClockedIn: Bool

    @MainActor
    init?(_ clocking: ClockingStore) {
        guard clocking.hasStatus else { return nil }
        let today = clocking.today
        isClockedIn = today.isClockedIn
        if today.isClockedIn {
            subtitle = today.trackers.last.map { "Clocked in since \(ClockingFormat.shortTime($0.inAt))" } ?? "Clocked in"
        } else {
            subtitle = "Clocked out"
        }
    }
}

enum ShellClockingText {
    static let module = "Clocking & Attendance"

    static func kicker(_ organisation: String) -> String {
        organisation.isEmpty ? module : "\(module) · \(organisation)"
    }
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
        ShellMenuGroup(id: "inventory", title: "Inventory", icon: .faPalletAlt, items: [
            ShellMenuItem(id: "inventory.dashboard", title: "Dashboard", icon: .faChartNetwork, requirement: .inventory, link: .inventory(.dashboard)),
            ShellMenuItem(id: "inventory.families", title: "SKO families", icon: .faBoxesAlt, requirement: .inventory, link: .inventory(.families)),
            ShellMenuItem(id: "inventory.skos", title: "SKOs", icon: .faBox, requirement: .inventory, link: .inventory(.skos)),
            ShellMenuItem(id: "inventory.pallets", title: "Stored pallets", icon: .faPallet, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.pallets)),
            ShellMenuItem(id: "inventory.storedItems", title: "Stored items", icon: .faNarwhal, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.storedItems))
        ]),
        ShellMenuGroup(id: "locations", title: "Locations", icon: .faInventory, items: [
            ShellMenuItem(id: "locations.warehouse", title: "Warehouse", icon: .faWarehouseAlt, requirement: .locations, link: .locations(.warehouse)),
            ShellMenuItem(id: "locations.areas", title: "Areas", icon: .faMapSigns, requirement: .locations, link: .locations(.areas)),
            ShellMenuItem(id: "locations.locations", title: "Locations", icon: .faInventory, requirement: .locations, link: .locations(.locations))
        ]),
        ShellMenuGroup(id: "goodsIn", title: "Goods in", icon: .faArrowToBottom, items: [
            ShellMenuItem(id: "goodsIn.backlog", title: "Backlog", icon: .faTasksAlt, requirement: .goodsIn, link: .goodsIn(.backlog)),
            ShellMenuItem(id: "goodsIn.stockDeliveries", title: "Stock deliveries", icon: .faTruckContainer, requirement: .goodsIn, link: .goodsIn(.stockDeliveries)),
            ShellMenuItem(id: "goodsIn.fulfilmentDeliveries", title: "Fulfilment deliveries", icon: .faTruckCouch, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.deliveries)),
            ShellMenuItem(id: "goodsIn.returns", title: "Returns", icon: .faExchange, requirement: .returns, link: .goodsIn(.returns))
        ]),
        ShellMenuGroup(id: "goodsOut", title: "Goods out", icon: .faArrowFromLeft, items: [
            ShellMenuItem(id: "goodsOut.backlog", title: "Backlog", icon: .faTasksAlt, requirement: .goodsOut, link: .goodsOut(.backlog)),
            ShellMenuItem(id: "goodsOut.deliveryNotes", title: "Delivery notes", icon: .faTruck, requirement: .goodsOut, link: .goodsOut(.deliveryNotes)),
            ShellMenuItem(id: "goodsOut.fulfilmentReturns", title: "Fulfilment returns", icon: .faSignOut, badge: .fulfilmentTag, requirement: .fulfilment, link: .fulfilment(.returns)),
            ShellMenuItem(id: "goodsOut.pickingSessions", title: "Picking sessions", icon: .faStackOverflow, requirement: .goodsOut, link: .dispatch(.pickingSessions)),
            ShellMenuItem(id: "goodsOut.trolleys", title: "Trolleys", icon: .faDollyFlatbedAlt, requirement: .goodsOut, link: .dispatch(.trolleys)),
            ShellMenuItem(id: "goodsOut.pickedBays", title: "Picked bays", icon: .faMonument, requirement: .goodsOut, link: .dispatch(.pickedBays))
        ]),
        ShellMenuGroup(id: "clocking", title: ShellClockingText.module, icon: .faUserClock, items: [
            ShellMenuItem(id: "clocking.clocking", title: "Clocking", icon: .faUserClock, requirement: .allStaff, link: .clocking(.clocking)),
            ShellMenuItem(id: "clocking.timesheets", title: "Timesheets", icon: .faStopwatch, requirement: .allStaff, link: .clocking(.timesheets)),
            ShellMenuItem(id: "clocking.leave", title: "Leave", icon: .faCalendarMinus, requirement: .allStaff, link: .clocking(.leave)),
            ShellMenuItem(id: "clocking.adjustments", title: "Clocking adjustment", icon: .faClockPencil, requirement: .allStaff, link: .clocking(.adjustments)),
            ShellMenuItem(id: "clocking.overtime", title: "Overtime", icon: .faClock, requirement: .allStaff, link: .clocking(.overtime)),
            ShellMenuItem(id: "clocking.calendar", title: "Calendar", icon: .faCalendar, requirement: .allStaff, link: .clocking(.calendar))
        ])
    ]

    static func groups(for modules: ModuleAccess, goodsOutUnassigned: Int = 0, goodsInReceived: Int = 0, clocking: ShellMenuClocking? = nil) -> [ShellMenuGroup] {
        allGroups.compactMap { group in
            let visible = group.items.filter { $0.requirement.isMet(by: modules) }.map { item in
                var item = item
                if item.id == "goodsOut.backlog", goodsOutUnassigned > 0 {
                    item.badge = .pill(Chip("\(goodsOutUnassigned) unassigned", .warn))
                }
                if item.id == "goodsIn.stockDeliveries", goodsInReceived > 0 {
                    item.badge = .pill(Chip("\(goodsInReceived) received", .success))
                }
                if item.id == "clocking.clocking", let clocking {
                    item.subtitle = clocking.subtitle
                    if clocking.isClockedIn {
                        item.subtitleTone = .success
                        item.iconTone = .success
                    }
                }
                return item
            }
            guard !visible.isEmpty else { return nil }
            return ShellMenuGroup(id: group.id, title: group.title, icon: group.icon, note: group.note, items: visible)
        }
    }
}
