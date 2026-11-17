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
    @ObservationIgnored private let cache: WarehouseCaching
    @ObservationIgnored private var userID: Int?

    init(defaults: UserDefaults = .standard, cache: WarehouseCaching = FileWarehouseCache.standard) {
        self.defaults = defaults
        self.cache = cache
    }

    var isLive: Bool { !warehouses.isEmpty }

    var selected: MobileWarehouse? {
        warehouses.first { $0.id == selectedID }
    }

    func load(for userID: Int, apiClient: APIClient) async {
        guard self.userID != userID || warehouses.isEmpty else { return }
        if let cached = cache.load(for: userID) {
            apply(cached, for: userID)
        }
        await refresh(for: userID, apiClient: apiClient)
    }

    func refresh(for userID: Int, apiClient: APIClient) async {
        do {
            let fetched = try await apiClient.fetchWarehouses()
            apply(fetched, for: userID)
            loadError = nil
            try? cache.save(fetched, for: userID)
        } catch where error.isCancellation {
            return
        } catch {
            if self.userID != userID || warehouses.isEmpty {
                loadError = error.localizedDescription
            }
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
        cache.clear()
    }

    private func apply(_ fetched: [MobileWarehouse], for userID: Int) {
        self.userID = userID
        if warehouses != fetched {
            warehouses = fetched
        }
        let stored = defaults.object(forKey: Self.key(for: userID)) as? Int
        let chosen = fetched.first { $0.id == stored } ?? fetched.first(where: \.can.viewLocations) ?? fetched.first
        if selectedID != chosen?.id {
            selectedID = chosen?.id
        }
        persist()
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
