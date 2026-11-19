import Foundation

struct ModuleAccess: Equatable, Sendable {
    var inventory: Bool
    var locations: Bool
    var goodsIn: Bool
    var returns: Bool
    var goodsOut: Bool
    var fulfilment: Bool

    static let everything = ModuleAccess(inventory: true, locations: true, goodsIn: true, returns: true, goodsOut: true, fulfilment: true)
    static let nothing = ModuleAccess(inventory: false, locations: false, goodsIn: false, returns: false, goodsOut: false, fulfilment: false)

    var hasGoodsInModule: Bool { goodsIn || returns }
    var hasWarehouseModules: Bool { inventory || locations || hasGoodsInModule || goodsOut }
    var canScan: Bool { inventory || locations || goodsOut || fulfilment }

    init(inventory: Bool, locations: Bool, goodsIn: Bool, returns: Bool = false, goodsOut: Bool, fulfilment: Bool) {
        self.inventory = inventory
        self.locations = locations
        self.goodsIn = goodsIn
        self.returns = returns
        self.goodsOut = goodsOut
        self.fulfilment = fulfilment
    }

    init(abilities: MobileWarehouse.Abilities) {
        self.init(
            inventory: abilities.viewInventory,
            locations: abilities.viewLocations,
            goodsIn: abilities.viewGoodsIn,
            returns: abilities.viewReturns,
            goodsOut: abilities.viewGoodsOut,
            fulfilment: abilities.viewFulfilment
        )
    }

    static func resolve(warehouse: MobileWarehouse?, isSignedIn: Bool) -> ModuleAccess? {
        if let warehouse {
            return ModuleAccess(abilities: warehouse.can)
        }
        return isSignedIn ? .nothing : nil
    }
}
