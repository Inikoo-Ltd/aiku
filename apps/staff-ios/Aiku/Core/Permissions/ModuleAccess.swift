import Foundation

struct ModuleAccess: Equatable, Sendable {
    var inventory: Bool
    var locations: Bool
    var goodsIn: Bool
    var goodsOut: Bool
    var fulfilment: Bool

    static let everything = ModuleAccess(inventory: true, locations: true, goodsIn: true, goodsOut: true, fulfilment: true)
    static let nothing = ModuleAccess(inventory: false, locations: false, goodsIn: false, goodsOut: false, fulfilment: false)

    var hasWarehouseModules: Bool { inventory || locations || goodsIn || goodsOut }
    var canScan: Bool { inventory || locations || goodsOut || fulfilment }

    init(inventory: Bool, locations: Bool, goodsIn: Bool, goodsOut: Bool, fulfilment: Bool) {
        self.inventory = inventory
        self.locations = locations
        self.goodsIn = goodsIn
        self.goodsOut = goodsOut
        self.fulfilment = fulfilment
    }

    init(abilities: MobileWarehouse.Abilities) {
        self.init(
            inventory: abilities.viewInventory,
            locations: abilities.viewLocations,
            goodsIn: abilities.viewGoodsIn,
            goodsOut: abilities.viewGoodsOut,
            fulfilment: abilities.viewFulfilment
        )
    }

    init(permissions: UserPermissions) {
        func any(_ prefixes: String...) -> Bool {
            prefixes.contains { permissions.allowsAny(withPrefix: $0) }
        }
        self.init(
            inventory: any("inventory.", "accounting.", "stocks.", "supervisor-stocks."),
            locations: any("locations.", "supervisor-locations."),
            goodsIn: any("incoming.", "supervisor-incoming."),
            goodsOut: any("dispatching.", "supervisor-dispatching.", "fulfilment."),
            fulfilment: any("fulfilment.", "supervisor-fulfilment.")
        )
    }

    static func resolve(warehouse: MobileWarehouse?, permissions: UserPermissions?) -> ModuleAccess? {
        if let warehouse {
            return ModuleAccess(abilities: warehouse.can)
        }
        return permissions.map(ModuleAccess.init(permissions:))
    }
}
