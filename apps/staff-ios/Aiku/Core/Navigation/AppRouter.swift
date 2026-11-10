import Observation
import SwiftUI

enum MainTab: Hashable, CaseIterable {
    case dashboard
    case inventory
    case goodsIn
    case goodsOut
}

enum Route: Hashable {
    case warehouseMenu
    case locations
    case profile
    case sku(String)
    case location(String)
    case stockDelivery(String)
    case deliveryNote(String)
    case scannerDevice(deliveryNote: String)
    case placeholder(String)
    case componentGallery
    case stateDemo(StateDemo)
}

struct ScanContext: Identifiable, Hashable {
    let title: String
    let reference: String?

    var id: String { "\(title)-\(reference ?? "")" }

    static let general = ScanContext(title: "Scan", reference: nil)
}

@MainActor
@Observable
final class AppRouter {
    var selectedTab: MainTab = .dashboard
    var path: [Route] = []
    var scanContext: ScanContext?

    func select(_ tab: MainTab) {
        path = []
        selectedTab = tab
    }

    func open(_ route: Route) {
        path.append(route)
    }

    func back() {
        guard !path.isEmpty else { return }
        path.removeLast()
    }

    func scan(_ context: ScanContext = .general) {
        scanContext = context
    }
}
