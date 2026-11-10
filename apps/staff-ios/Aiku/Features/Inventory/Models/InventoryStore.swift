import Foundation
import Observation

@MainActor
@Observable
final class InventoryStore {
    static let shared = InventoryStore()

    private(set) var skos: [InventorySko]
    private(set) var families: [InventoryFamily]
    private(set) var movements: [InventoryStockMovement]
    let locationOptions: [InventoryLocationOption]
    let dashboard: InventoryDashboardSummary
    var filter = InventorySkoFilter()

    @ObservationIgnored private var undoSnapshot: (skos: [InventorySko], movements: [InventoryStockMovement])?
    @ObservationIgnored private let clock: @MainActor () -> Date

    init(
        skos: [InventorySko] = InventorySampleData.skos,
        families: [InventoryFamily] = InventorySampleData.families,
        movements: [InventoryStockMovement] = InventorySampleData.movements,
        locationOptions: [InventoryLocationOption] = InventorySampleData.locationOptions,
        dashboard: InventoryDashboardSummary = InventorySampleData.dashboard,
        clock: @escaping @MainActor () -> Date = { Date() }
    ) {
        self.skos = skos
        self.families = families
        self.movements = movements
        self.locationOptions = locationOptions
        self.dashboard = dashboard
        self.clock = clock
    }

    var canUndo: Bool { undoSnapshot != nil }

    var familyNames: [String: String] {
        Dictionary(uniqueKeysWithValues: families.map { ($0.code, $0.name) })
    }

    func sko(reference: String) -> InventorySko {
        skos.first { $0.reference.caseInsensitiveCompare(reference) == .orderedSame } ?? skos[0]
    }

    func family(code: String) -> InventoryFamily {
        families.first { $0.code.caseInsensitiveCompare(code) == .orderedSame } ?? families[0]
    }

    func family(of sko: InventorySko) -> InventoryFamily? {
        sko.familyCode.flatMap { code in families.first { $0.code == code } }
    }

    func skos(inFamily code: String) -> [InventorySko] {
        skos.filter { $0.familyCode == code }
    }

    func results(scope: InventorySkoScope, search: String) -> [InventorySko] {
        InventorySkoQuery.results(skos, scope: scope, filter: filter, search: search, familyNames: familyNames)
    }

    func count(scope: InventorySkoScope) -> Int {
        skos.filter(scope.includes).count
    }

    func families(in state: InventoryFamilyState, search: String) -> [InventoryFamily] {
        families.filter { $0.state == state && $0.matches(search: search) }
    }

    func familyCount(_ state: InventoryFamilyState) -> Int {
        families.filter { $0.state == state }.count
    }

    func movements(filter: InventoryHistoryFilter, skoReference: String? = nil) -> [InventoryStockMovement] {
        movements.filter { movement in
            filter.includes(movement) && (skoReference.map { $0 == movement.skoReference } ?? true)
        }
    }

    var recentMovements: [InventoryStockMovement] { Array(movements.prefix(3)) }

    func availableLocations(for sko: InventorySko, search: String) -> [InventoryLocationOption] {
        let query = search.trimmingCharacters(in: .whitespacesAndNewlines)
        return locationOptions.filter { option in
            sko.location(option.code) == nil && (query.isEmpty || option.code.localizedCaseInsensitiveContains(query))
        }
    }

    @discardableResult
    func audit(_ reference: String, draft: InventoryAuditDraft) throws -> String {
        try apply(reference) { try InventoryStockOperations.audit($0, draft: draft, auditedOn: auditDate, time: timeLabel) }
    }

    @discardableResult
    func move(_ reference: String, from source: String, to destination: String, quantity: Int, reason: InventoryMoveReason) throws -> String {
        try apply(reference) {
            try InventoryStockOperations.move($0, from: source, to: destination, quantity: quantity, reason: reason, time: timeLabel)
        }
    }

    @discardableResult
    func consume(_ reference: String, location: String, quantity: Int, note: String) throws -> String {
        try apply(reference) { try InventoryStockOperations.consume($0, location: location, quantity: quantity, note: note, time: timeLabel) }
    }

    @discardableResult
    func addLocation(_ reference: String, location: String, type: InventoryLocationType) throws -> String {
        try apply(reference) { try InventoryStockOperations.addLocation($0, location: location, type: type, time: timeLabel) }
    }

    @discardableResult
    func removeLocations(_ reference: String, codes: Set<String>) throws -> String {
        try apply(reference) { try InventoryStockOperations.removeLocations($0, codes: codes, time: timeLabel) }
    }

    func undo() {
        guard let snapshot = undoSnapshot else { return }
        skos = snapshot.skos
        movements = snapshot.movements
        undoSnapshot = nil
    }

    private func apply(_ reference: String, _ operation: (InventorySko) throws -> InventoryOperationResult) throws -> String {
        guard let index = skos.firstIndex(where: { $0.reference == reference }) else {
            throw InventoryStockError.unknownLocation(reference)
        }
        let result = try operation(skos[index])
        undoSnapshot = (skos, movements)
        skos[index] = result.sko
        movements.insert(contentsOf: result.movements, at: 0)
        return result.message
    }

    private var timeLabel: String {
        clock().formatted(Date.FormatStyle(date: .omitted, time: .shortened).locale(Locale(identifier: "en_GB")))
    }

    private var auditDate: String {
        clock().formatted(.dateTime.day(.twoDigits).month(.abbreviated).locale(Locale(identifier: "en_GB")))
    }
}
