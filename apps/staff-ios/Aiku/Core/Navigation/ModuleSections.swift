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
    case stockHistory
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
    case shippers
    case trolleys
    case pickedBays
}

struct ModuleSection: Identifiable {
    let id: SectionID
    let title: String
    let requiresFulfilment: Bool
    let show: @MainActor (AppRouter, Bool) -> Void

    @MainActor
    init<R: Hashable>(_ id: SectionID, _ title: String, _ route: R, fulfilment: Bool = false) {
        self.id = id
        self.title = title
        requiresFulfilment = fulfilment
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
    func sections(includingFulfilment: Bool) -> [ModuleSection] {
        let all: [ModuleSection] = switch self {
        case .inventory: [
            ModuleSection(.inventoryDashboard, "Dashboard", InventoryRoute.dashboard),
            ModuleSection(.skoFamilies, "SKO families", InventoryRoute.families),
            ModuleSection(.skos, "SKOs", InventoryRoute.skos),
            ModuleSection(.storedPallets, "Stored pallets", FulfilmentRoute.pallets, fulfilment: true),
            ModuleSection(.storedItems, "Stored items", FulfilmentRoute.storedItems, fulfilment: true),
            ModuleSection(.stockHistory, "Stock history", InventoryRoute.stockHistory)
        ]
        case .locations: [
            ModuleSection(.warehouse, "Warehouse", LocationsRoute.warehouse),
            ModuleSection(.areas, "Areas", LocationsRoute.areas),
            ModuleSection(.locations, "Locations", LocationsRoute.locations)
        ]
        case .goodsIn: [
            ModuleSection(.goodsInBacklog, "Backlog", GoodsInRoute.backlog),
            ModuleSection(.stockDeliveries, "Stock deliveries", GoodsInRoute.stockDeliveries),
            ModuleSection(.fulfilmentDeliveries, "Fulfilment deliveries", FulfilmentRoute.deliveries, fulfilment: true),
            ModuleSection(.goodsInReturns, "Returns", GoodsInRoute.returns)
        ]
        case .goodsOut: [
            ModuleSection(.goodsOutBacklog, "Backlog", GoodsOutRoute.backlog),
            ModuleSection(.deliveryNotes, "Delivery notes", GoodsOutRoute.deliveryNotes),
            ModuleSection(.pickingSessions, "Picking sessions", DispatchRoute.pickingSessions),
            ModuleSection(.fulfilmentReturns, "Fulfilment returns", FulfilmentRoute.returns, fulfilment: true),
            ModuleSection(.shippers, "Shippers", DispatchRoute.shippers),
            ModuleSection(.trolleys, "Trolleys", DispatchRoute.trolleys),
            ModuleSection(.pickedBays, "Picked bays", DispatchRoute.pickedBays)
        ]
        }
        return all.filter { includingFulfilment || !$0.requiresFulfilment }
    }
}
