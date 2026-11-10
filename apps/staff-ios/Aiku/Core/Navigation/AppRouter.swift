import Observation
import SwiftUI

enum MainTab: Hashable, CaseIterable, Sendable {
    case home
    case work
    case scan
    case inbox
    case menu
}

@MainActor
@Observable
final class AppRouter {
    var selectedTab: MainTab = .home
    var path = NavigationPath()

    func select(_ tab: MainTab) {
        path = NavigationPath()
        selectedTab = tab
    }

    func open<R: Hashable>(_ route: R) {
        path.append(route)
    }

    func replace<R: Hashable>(with route: R) {
        var transaction = Transaction()
        transaction.disablesAnimations = true
        withTransaction(transaction) {
            if !path.isEmpty {
                path.removeLast()
            }
            path.append(route)
        }
    }

    func back() {
        guard !path.isEmpty else { return }
        path.removeLast()
    }

    func backToRoot() {
        path = NavigationPath()
    }
}
