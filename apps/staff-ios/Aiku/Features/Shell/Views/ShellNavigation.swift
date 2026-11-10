import SwiftUI

extension AppRouter {
    func shellFollow(_ link: ShellLink) {
        switch link {
        case .shell(let route): open(route)
        case .system(let route): open(route)
        case .inventory(let route): open(route)
        case .locations(let route): open(route)
        case .goodsIn(let route): open(route)
        case .goodsOut(let route): open(route)
        case .dispatch(let route): open(route)
        case .fulfilment(let route): open(route)
        case .clocking(let route): open(route)
        case .tab(let tab): select(tab)
        }
    }
}
