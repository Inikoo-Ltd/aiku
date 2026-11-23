import Foundation

enum AppModule: String, CaseIterable, Sendable {
    case inventory
    case locations
    case goodsIn
    case goodsOut

    var title: String {
        switch self {
        case .inventory: "Inventory"
        case .locations: "Locations"
        case .goodsIn: "Goods in"
        case .goodsOut: "Goods out"
        }
    }
}

enum SectionID: String, Sendable {
    case inventoryDashboard
    case skoFamilies
    case skos
    case storedPallets
    case storedItems
    case warehouse
    case areas
    case locations
    case goodsInBacklog
    case stockDeliveries
    case fulfilmentDeliveries
    case goodsInReturns
    case goodsOutBacklog
    case deliveryNotes
    case pickingSessions
    case fulfilmentReturns
    case trolleys
    case pickedBays
}

struct ModuleSection: Identifiable {
    let id: SectionID
    let title: String
    let requirement: ShellAccessRequirement?
    let show: @MainActor (AppRouter, Bool) -> Void

    @MainActor
    init<R: Hashable>(_ id: SectionID, _ title: String, _ route: R, requires requirement: ShellAccessRequirement? = nil) {
        self.id = id
        self.title = title
        self.requirement = requirement
        show = { router, replacing in
            if replacing {
                router.replace(with: route)
            } else {
                router.open(route)
            }
        }
    }
}

extension AppModule {
    @MainActor
    func sections(for modules: ModuleAccess) -> [ModuleSection] {
        let all: [ModuleSection] = switch self {
        case .inventory: [
            ModuleSection(.inventoryDashboard, "Dashboard", InventoryRoute.dashboard, requires: .inventory),
            ModuleSection(.skoFamilies, "SKO families", InventoryRoute.families, requires: .inventory),
            ModuleSection(.skos, "SKOs", InventoryRoute.skos, requires: .inventory),
            ModuleSection(.storedPallets, "Stored pallets", FulfilmentRoute.pallets, requires: .fulfilment),
            ModuleSection(.storedItems, "Stored items", FulfilmentRoute.storedItems, requires: .fulfilment)
        ]
        case .locations: [
            ModuleSection(.warehouse, "Warehouse", LocationsRoute.warehouse),
            ModuleSection(.areas, "Areas", LocationsRoute.areas),
            ModuleSection(.locations, "Locations", LocationsRoute.locations)
        ]
        case .goodsIn: [
            ModuleSection(.goodsInBacklog, "Backlog", GoodsInRoute.backlog, requires: .goodsIn),
            ModuleSection(.stockDeliveries, "Stock deliveries", GoodsInRoute.stockDeliveries, requires: .goodsIn),
            ModuleSection(.fulfilmentDeliveries, "Fulfilment deliveries", FulfilmentRoute.deliveries, requires: .fulfilment),
            ModuleSection(.goodsInReturns, "Returns", GoodsInRoute.returns, requires: .returns)
        ]
        case .goodsOut: [
            ModuleSection(.goodsOutBacklog, "Backlog", GoodsOutRoute.backlog, requires: .goodsOut),
            ModuleSection(.deliveryNotes, "Delivery notes", GoodsOutRoute.deliveryNotes, requires: .goodsOut),
            ModuleSection(.pickingSessions, "Picking sessions", DispatchRoute.pickingSessions, requires: .goodsOut),
            ModuleSection(.fulfilmentReturns, "Fulfilment returns", FulfilmentRoute.returns, requires: .fulfilment),
            ModuleSection(.trolleys, "Trolleys", DispatchRoute.trolleys, requires: .goodsOut),
            ModuleSection(.pickedBays, "Picked bays", DispatchRoute.pickedBays, requires: .goodsOut)
        ]
        }
        return all.filter { $0.requirement?.isMet(by: modules) ?? true }
    }
}
