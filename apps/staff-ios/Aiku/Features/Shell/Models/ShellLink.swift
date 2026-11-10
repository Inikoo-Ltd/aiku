import Foundation

enum ShellLink: Hashable, Sendable {
    case shell(ShellRoute)
    case system(SystemRoute)
    case inventory(InventoryRoute)
    case locations(LocationsRoute)
    case goodsIn(GoodsInRoute)
    case goodsOut(GoodsOutRoute)
    case dispatch(DispatchRoute)
    case fulfilment(FulfilmentRoute)
    case clocking(ClockingRoute)
    case tab(MainTab)
}
