import Foundation
import Observation

@MainActor
@Observable
final class LocationsStore {
    static let shared = LocationsStore()

    var warehouse: LocationsWarehouse
    var listTab: LocationsListTab = .all
    var listQuery = ""
    var listFilter = LocationsFilter.none
    var areaQuery = ""

    init(warehouse: LocationsWarehouse = LocationsSampleData.warehouse) {
        self.warehouse = warehouse
    }

    var listedLocations: [LocationsLocation] {
        warehouse.list(tab: listTab, query: listQuery, filter: listFilter)
    }

    func count(for tab: LocationsListTab) -> Int {
        warehouse.count(tab: tab, query: listQuery, filter: listFilter)
    }

    func resultCount(for filter: LocationsFilter) -> Int {
        warehouse.list(tab: listTab, query: listQuery, filter: filter).count
    }

    var firstScannableCode: String {
        warehouse.locations.first?.code ?? ""
    }

    func restore(_ snapshot: LocationsWarehouse) {
        warehouse = snapshot
    }

    @discardableResult
    func audit(location code: String, counts: [LocationsAuditCount], on date: Date = Date()) -> Int {
        warehouse.audit(location: code, counts: counts, on: date)
    }

    func move(from source: String, to target: String, quantities: [String: Int], removeFromSource: Bool) -> (moved: Int, snapshot: LocationsWarehouse) {
        let snapshot = warehouse
        let moved = warehouse.move(from: source, to: target, quantities: quantities, removeFromSource: removeFromSource)
        return (moved, snapshot)
    }

    func remove(skos references: Set<String>, from code: String) -> (removed: Int, snapshot: LocationsWarehouse) {
        let snapshot = warehouse
        let removed = warehouse.remove(skos: references, from: code)
        return (removed, snapshot)
    }

    func update(sko reference: String, in code: String, settings: LocationsSlotSettings) {
        warehouse.update(sko: reference, in: code, settings: settings)
    }

    func setAutoRemoval(_ isOn: Bool, by name: String, on date: Date = Date()) {
        warehouse.autoRemoval = LocationsAutoRemoval(isOn: isOn, changedBy: name, changedOn: date)
    }
}
