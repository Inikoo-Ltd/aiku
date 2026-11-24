import Foundation

enum InventoryStockError: Error, Equatable, Sendable {
    case quantityMustBePositive
    case notEnoughStock(available: Int)
    case sameLocation
    case unknownLocation(String)
    case missingReason(String)
    case locationAlreadyAssigned(String)
    case nothingSelected
    case waitingForOfflineWork(String)

    @MainActor
    var message: String {
        switch self {
        case .quantityMustBePositive: L10n.text("Enter a quantity above 0.")
        case .notEnoughStock(let available): L10n.format("Only %@ in location.", String(available))
        case .sameLocation: L10n.text("Choose two different locations.")
        case .unknownLocation(let code): L10n.format("%@ is not a location of this SKO.", code)
        case .missingReason(let code): L10n.format("Choose a reason for %@.", code)
        case .locationAlreadyAssigned(let code): L10n.format("%@ is already a location of this SKO.", code)
        case .nothingSelected: L10n.text("Choose at least one location.")
        case .waitingForOfflineWork(let reason): reason
        }
    }
}

struct InventoryAuditDraft: Equatable, Sendable {
    var counts: [String: Int]
    var reasons: [String: InventoryAuditReason] = [:]
    var countedLocations: Set<String>?

    init(sko: InventorySko, only codes: Set<String>? = nil) {
        counts = Dictionary(uniqueKeysWithValues: sko.locations.map { ($0.code, max(0, $0.quantity)) })
        countedLocations = codes
    }

    func isCounted(_ location: InventorySkoLocation) -> Bool {
        countedLocations?.contains(location.code) ?? true
    }

    func counted(_ location: InventorySkoLocation) -> Int {
        counts[location.code] ?? location.quantity
    }

    func change(for location: InventorySkoLocation) -> Int {
        counted(location) - location.quantity
    }

    func changedLocations(in sko: InventorySko) -> [InventorySkoLocation] {
        sko.locations.filter { isCounted($0) && change(for: $0) != 0 }
    }

    func validReason(for location: InventorySkoLocation) -> InventoryAuditReason? {
        guard let reason = reasons[location.code] else { return nil }
        let change = change(for: location)
        guard change != 0, reason.isIncrease == (change > 0) else { return nil }
        return reason
    }

    func missingReasons(in sko: InventorySko) -> [String] {
        changedLocations(in: sko).filter { validReason(for: $0) == nil }.map(\.code)
    }

    func canSave(_ sko: InventorySko) -> Bool {
        !sko.locations.isEmpty && missingReasons(in: sko).isEmpty
    }
}

@MainActor
enum StockConfirmText {
    static func quantityLabel(_ quantity: Int, packedIn: Double?) -> String {
        FractionGlyphs.apply(StockFraction.label(Double(quantity), packedIn: packedIn))
    }

    static func countedText(_ counted: Int, was quantity: Int, wasText: String, packedIn: Double?) -> String {
        counted == quantity ? wasText : quantityLabel(counted, packedIn: packedIn)
    }

    static func auditLine(_ title: String, wasText: String, counted: Int, was quantity: Int, packedIn: Double?) -> String {
        guard counted != quantity else { return L10n.format("%@: %@, no change", title, wasText) }
        return L10n.format("%@: %@ → %@", title, wasText, quantityLabel(counted, packedIn: packedIn))
    }

    static func auditSummary(_ sko: InventorySko, counts: [String: Int]) -> String {
        sko.locations.compactMap { location in
            guard let counted = counts[location.code] else { return nil }
            return auditLine(location.code, wasText: location.quantityText, counted: counted, was: location.quantity, packedIn: sko.packedIn)
        }
        .joined(separator: "\n")
    }

    static func moveSummary(quantity: Int, from source: String, to destination: String, packedIn: Double?) -> String {
        L10n.format("%@ from %@ to %@.", quantityLabel(quantity, packedIn: packedIn), source, destination)
    }

    static func removeTitle(_ location: InventorySkoLocation) -> String {
        L10n.format("Remove %@?", location.code)
    }

    static func removeMessage(_ location: InventorySkoLocation, skoReference: String) -> String {
        if location.exactQuantity > 0 {
            return L10n.format("The %@ still in %@ will be written off.", location.quantityText, location.code)
        }
        return L10n.format("%@ will no longer be a location of %@.", location.code, skoReference)
    }
}

struct InventoryOperationResult: Sendable {
    let sko: InventorySko
    let movements: [InventoryStockMovement]
    let message: String
}

@MainActor
enum InventoryStockOperations {
    static func audit(_ sko: InventorySko, draft: InventoryAuditDraft, auditedOn: String, time: String) throws -> InventoryOperationResult {
        guard !sko.locations.isEmpty else { throw InventoryStockError.nothingSelected }
        if let missing = draft.missingReasons(in: sko).first {
            throw InventoryStockError.missingReason(missing)
        }
        var updated = sko
        var movements: [InventoryStockMovement] = []
        for index in updated.locations.indices where draft.isCounted(updated.locations[index]) {
            let location = updated.locations[index]
            let change = draft.change(for: location)
            updated.locations[index].quantity = draft.counted(location)
            updated.locations[index].lastAudited = auditedOn
            if change != 0, let reason = draft.validReason(for: location) {
                movements.append(InventoryStockMovement(
                    skoReference: sko.reference,
                    kind: .audit,
                    quantity: change,
                    summary: "\(location.code) · \(reason.title) · you · \(time)",
                    todaySummary: "\(reason.title) · \(location.code) · you · just now"
                ))
            }
        }
        let running = updated.stock
        movements = movements.map {
            InventoryStockMovement(
                id: $0.id, skoReference: $0.skoReference, kind: $0.kind, quantity: $0.quantity,
                summary: "\($0.summary) · running \(running)", todaySummary: $0.todaySummary
            )
        }
        let net = movements.reduce(0) { $0 + $1.quantity }
        let message = movements.isEmpty ? L10n.text("Audit saved · no change") : L10n.format("Audit saved · %@", InventoryStockMovement.signed(net))
        return InventoryOperationResult(sko: updated, movements: movements.reversed(), message: message)
    }

    static func move(
        _ sko: InventorySko,
        from source: String,
        to destination: String,
        quantity: Int,
        reason: InventoryMoveReason,
        time: String
    ) throws -> InventoryOperationResult {
        guard source != destination else { throw InventoryStockError.sameLocation }
        guard let fromIndex = sko.locations.firstIndex(where: { $0.code == source }) else {
            throw InventoryStockError.unknownLocation(source)
        }
        guard let toIndex = sko.locations.firstIndex(where: { $0.code == destination }) else {
            throw InventoryStockError.unknownLocation(destination)
        }
        guard quantity > 0 else { throw InventoryStockError.quantityMustBePositive }
        let available = sko.locations[fromIndex].quantity
        guard quantity <= available else { throw InventoryStockError.notEnoughStock(available: max(0, available)) }
        var updated = sko
        updated.locations[fromIndex].quantity -= quantity
        updated.locations[toIndex].quantity += quantity
        let movement = InventoryStockMovement(
            skoReference: sko.reference,
            kind: .locationTransfer,
            quantity: quantity,
            summary: "\(source) → \(destination) · \(reason.title) · you · \(time)",
            todaySummary: "\(source) → \(destination) · you · just now"
        )
        return InventoryOperationResult(sko: updated, movements: [movement], message: L10n.format("%lld units moved to %@", quantity, destination))
    }

    static func consume(_ sko: InventorySko, location code: String, quantity: Int, note: String, time: String) throws -> InventoryOperationResult {
        guard let index = sko.locations.firstIndex(where: { $0.code == code }) else {
            throw InventoryStockError.unknownLocation(code)
        }
        guard quantity > 0 else { throw InventoryStockError.quantityMustBePositive }
        let available = sko.locations[index].quantity
        guard quantity <= available else { throw InventoryStockError.notEnoughStock(available: max(0, available)) }
        var updated = sko
        updated.locations[index].quantity -= quantity
        let trimmedNote = note.trimmingCharacters(in: .whitespacesAndNewlines)
        let summary = [code, "Internal consumption", trimmedNote.isEmpty ? nil : trimmedNote, "you", time, "running \(updated.stock)"]
            .compactMap { $0 }
            .joined(separator: " · ")
        let movement = InventoryStockMovement(
            skoReference: sko.reference,
            kind: .consumption,
            quantity: -quantity,
            summary: summary,
            todaySummary: "Internal consumption · \(code) · you · just now"
        )
        return InventoryOperationResult(sko: updated, movements: [movement], message: L10n.format("%lld units consumed from %@", quantity, code))
    }

    static func addLocation(
        _ sko: InventorySko,
        location code: String,
        type: InventoryLocationType,
        channel: InventoryChannel = .wholesale,
        time: String
    ) throws -> InventoryOperationResult {
        let trimmed = code.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
        guard !trimmed.isEmpty else { throw InventoryStockError.nothingSelected }
        guard sko.location(trimmed) == nil else { throw InventoryStockError.locationAlreadyAssigned(trimmed) }
        var updated = sko
        if type == .picking {
            for index in updated.locations.indices where updated.locations[index].type == .picking && updated.locations[index].channel == channel {
                updated.locations[index].type = .storing
                updated.locations[index].channel = nil
            }
        }
        updated.locations.append(InventorySkoLocation(
            code: trimmed,
            type: type,
            channel: type == .picking ? channel : nil,
            quantity: 0
        ))
        let label = type == .picking ? "Picking · \(channel.title)" : type.title
        let movement = InventoryStockMovement(
            skoReference: sko.reference,
            kind: .associate,
            quantity: 0,
            summary: "\(trimmed) · \(label) · you · \(time)",
            todaySummary: "\(trimmed) · \(label) · you · just now"
        )
        return InventoryOperationResult(sko: updated, movements: [movement], message: L10n.format("%@ added", trimmed))
    }

    static func removeLocations(_ sko: InventorySko, codes: Set<String>, time: String) throws -> InventoryOperationResult {
        guard !codes.isEmpty else { throw InventoryStockError.nothingSelected }
        if let unknown = codes.sorted().first(where: { sko.location($0) == nil }) {
            throw InventoryStockError.unknownLocation(unknown)
        }
        var updated = sko
        let removed = sko.locations.filter { codes.contains($0.code) }
        updated.locations.removeAll { codes.contains($0.code) }
        let running = updated.stock
        var movements: [InventoryStockMovement] = []
        for location in removed {
            if location.quantity != 0 {
                movements.append(InventoryStockMovement(
                    skoReference: sko.reference,
                    kind: .audit,
                    quantity: -location.quantity,
                    summary: "\(location.code) · \(InventoryAuditReason.lostMissing.title) · you · \(time) · running \(running)",
                    todaySummary: "\(InventoryAuditReason.lostMissing.title) · \(location.code) · you · just now"
                ))
            }
            movements.append(InventoryStockMovement(
                skoReference: sko.reference,
                kind: .disassociate,
                quantity: 0,
                summary: "\(location.code) · \(location.typeLabel) · you · \(time)",
                todaySummary: "\(location.code) · you · just now"
            ))
        }
        let message = removed.count == 1 ? L10n.format("%@ removed", removed[0].code) : L10n.format("%lld locations removed", removed.count)
        return InventoryOperationResult(sko: updated, movements: movements.reversed(), message: message)
    }
}
