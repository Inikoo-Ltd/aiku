import Foundation
import Observation

@MainActor
@Observable
final class WarehouseStore {
    static let shared = WarehouseStore()

    private(set) var warehouses: [MobileWarehouse] = []
    private(set) var selectedID: Int?
    private(set) var loadError: String?

    @ObservationIgnored private let defaults: UserDefaults
    @ObservationIgnored private var userID: Int?

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    var isLive: Bool { !warehouses.isEmpty }

    var selected: MobileWarehouse? {
        warehouses.first { $0.id == selectedID }
    }

    func load(for userID: Int, apiClient: APIClient) async {
        guard self.userID != userID || warehouses.isEmpty else { return }
        do {
            let fetched = try await apiClient.fetchWarehouses()
            self.userID = userID
            warehouses = fetched
            loadError = nil
            let stored = defaults.object(forKey: Self.key(for: userID)) as? Int
            let chosen = fetched.first { $0.id == stored } ?? fetched.first(where: \.can.viewLocations) ?? fetched.first
            selectedID = chosen?.id
            persist()
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func select(_ warehouseID: Int) {
        guard warehouses.contains(where: { $0.id == warehouseID }) else { return }
        selectedID = warehouseID
        persist()
    }

    func reset() {
        warehouses = []
        selectedID = nil
        loadError = nil
        userID = nil
    }

    private func persist() {
        guard let userID else { return }
        if let selectedID {
            defaults.set(selectedID, forKey: Self.key(for: userID))
        } else {
            defaults.removeObject(forKey: Self.key(for: userID))
        }
    }

    static func key(for userID: Int) -> String {
        "warehouse.selected.\(userID)"
    }
}
