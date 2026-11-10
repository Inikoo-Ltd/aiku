import Foundation

enum InventoryStockError: Error, Equatable, Sendable {
    case quantityMustBePositive
    case notEnoughStock(available: Int)
    case sameLocation
    case unknownLocation(String)
    case missingReason(String)
    case locationAlreadyAssigned(String)
    case nothingSelected

    var message: String {
        switch self {
        case .quantityMustBePositive: "Enter a quantity above 0."
        case .notEnoughStock(let available): "Only \(available) in location."
        case .sameLocation: "Choose two different locations."
        case .unknownLocation(let code): "\(code) is not a location of this SKO."
        case .missingReason(let code): "Choose a reason for \(code)."
        case .locationAlreadyAssigned(let code): "\(code) is already a location of this SKO."
        case .nothingSelected: "Choose at least one location."
        }
    }
}

struct InventoryAuditDraft: Equatable, Sendable {
    var counts: [String: Int]
    var reasons: [String: InventoryAuditReason] = [:]

    init(sko: InventorySko) {
        counts = Dictionary(uniqueKeysWithValues: sko.locations.map { ($0.code, max(0, $0.quantity)) })
    }

    func counted(_ location: InventorySkoLocation) -> Int {
        counts[location.code] ?? location.quantity
    }

    func change(for location: InventorySkoLocation) -> Int {
        counted(location) - location.quantity
    }

    func changedLocations(in sko: InventorySko) -> [InventorySkoLocation] {
        sko.locations.filter { change(for: $0) != 0 }
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

struct InventoryOperationResult: Sendable {
    let sko: InventorySko
    let movements: [InventoryStockMovement]
    let message: String
}

enum InventoryStockOperations {
    static func audit(_ sko: InventorySko, draft: InventoryAuditDraft, auditedOn: String, time: String) throws -> InventoryOperationResult {
        guard !sko.locations.isEmpty else { throw InventoryStockError.nothingSelected }
        if let missing = draft.missingReasons(in: sko).first {
            throw InventoryStockError.missingReason(missing)
        }
        var updated = sko
        var movements: [InventoryStockMovement] = []
        for index in updated.locations.indices {
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
        let message = movements.isEmpty ? "Audit saved · no change" : "Audit saved · \(InventoryStockMovement.signed(net))"
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
        return InventoryOperationResult(sko: updated, movements: [movement], message: "\(unitsLabel(quantity)) moved to \(destination)")
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
        return InventoryOperationResult(sko: updated, movements: [movement], message: "\(unitsLabel(quantity)) consumed from \(code)")
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
        return InventoryOperationResult(sko: updated, movements: [movement], message: "\(trimmed) added")
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
        let message = removed.count == 1 ? "\(removed[0].code) removed" : "\(removed.count) locations removed"
        return InventoryOperationResult(sko: updated, movements: movements.reversed(), message: message)
    }

    static func unitsLabel(_ quantity: Int) -> String {
        quantity == 1 ? "1 unit" : "\(quantity) units"
    }
}
