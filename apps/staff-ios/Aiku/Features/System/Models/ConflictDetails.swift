import Foundation

struct ConflictDifference: Equatable, Sendable {
    let title: String?
    let subtitle: String?
    let yours: Double?
    let aiku: Double?

    var difference: Double? {
        guard let yours, let aiku else { return nil }
        return StockFraction.total(aiku - yours)
    }

    var hasQuantity: Bool { yours != nil || aiku != nil }
}

struct ConflictFact: Equatable, Sendable {
    let label: String
    let value: String
}

struct ConflictItemSummary: Equatable, Sendable {
    let code: String?
    let name: String?
    let imageUrl: String?
    let record: String?

    var itemText: String? {
        let parts = [code, name].compactMap { $0 }
        return parts.isEmpty ? nil : parts.joined(separator: " · ")
    }
}

enum ConflictDetails {
    static func differences(_ entry: OfflineOperationEntry) -> [ConflictDifference] {
        let rows = entry.countedRows
        guard !rows.isEmpty else {
            return [ConflictDifference(
                title: nil,
                subtitle: present(entry.labels.locationCode),
                yours: entry.requestedQuantity,
                aiku: entry.appliedQuantity ?? entry.requestedQuantity.map { _ in 0.0 }
            )]
        }
        let isOneSko = Set(rows.compactMap(\.skoCode)).count <= 1
        return rows.map { row in
            ConflictDifference(
                title: isOneSko ? (row.locationCode ?? row.skoCode) : (row.skoCode ?? row.locationCode),
                subtitle: isOneSko ? nil : row.locationCode,
                yours: row.counted,
                aiku: row.quantity
            )
        }
    }

    static func summary(_ entry: OfflineOperationEntry, lineWord: String = "Line") -> ConflictItemSummary {
        let labelParts: [String] = entry.labels.subtitle.map { subtitle in
            subtitle.split(separator: " ", maxSplits: 1).map { String($0) }
        } ?? []
        return ConflictItemSummary(
            code: entry.item?.skoCode ?? labelParts.first,
            name: entry.item?.skoName ?? (labelParts.count > 1 ? labelParts[1] : nil),
            imageUrl: entry.item?.imageUrl,
            record: recordText(entry, lineWord: lineWord)
        )
    }

    static func facts(_ entry: OfflineOperationEntry, lineWord: String = "Line", timeZone: TimeZone = .current) -> [ConflictFact] {
        let item = entry.item
        let quantities = item?.quantities
        let packedIn = item?.packedIn
        var facts: [ConflictFact] = []
        if let label = recordLabel(item?.recordType), let recordValue = recordText(entry, lineWord: lineWord) {
            facts.append(ConflictFact(label: label, value: recordValue))
        }
        if let location = present(entry.labels.locationCode), item?.recordType != OfflineRecordType.location.rawValue {
            facts.append(ConflictFact(label: "Location", value: location))
        }
        if let requiredQuantity = quantities?.required {
            facts.append(ConflictFact(label: "Required", value: quantity(requiredQuantity, packedIn: packedIn)))
        }
        if let picked = quantities?.picked {
            facts.append(ConflictFact(label: "Picked", value: quantity(picked, packedIn: packedIn)))
        }
        if let notPicked = quantities?.notPicked, notPicked > 0 {
            facts.append(ConflictFact(label: "Not picked", value: quantity(notPicked, packedIn: packedIn)))
        }
        if let packed = quantities?.packed, packed > 0 {
            facts.append(ConflictFact(label: "Packed", value: quantity(packed, packedIn: packedIn)))
        }
        if let stockNow = quantities?.stockNow {
            facts.append(ConflictFact(label: "Stock now", value: quantity(stockNow, packedIn: packedIn)))
        }
        if let doneOffline = InventoryLive.momentText(entry.performedAt, timeZone: timeZone) {
            facts.append(ConflictFact(label: "Done offline", value: doneOffline))
        }
        if let changedBy = present(entry.conflictingUser?.name) {
            facts.append(ConflictFact(label: "Changed in Aiku by", value: changedBy))
        }
        return facts
    }

    static func notes(_ entry: OfflineOperationEntry) -> [ConflictFact] {
        var notes: [ConflictFact] = []
        if entry.countedSince {
            notes.append(ConflictFact(label: "Counted since", value: "Recorded without a stock movement"))
        }
        if let task = present(entry.taskReference) {
            notes.append(ConflictFact(label: "Stock check", value: task))
        }
        if let blockedBy = entry.blockedBySequence {
            notes.append(ConflictFact(label: "Waits for", value: "Your earlier action \(blockedBy)"))
        }
        return notes
    }

    static func recordReference(_ entry: OfflineOperationEntry) -> String? {
        guard let item = entry.item, let reference = item.recordReference, let record = entry.record else { return nil }
        let itemRecordType: String? = item.recordType == skoRecordType ? OfflineRecordType.orgStock.rawValue : item.recordType
        return itemRecordType == record.type ? reference : nil
    }

    static func quantity(_ value: Double, packedIn: Double?) -> String {
        FractionGlyphs.apply(StockFraction.label(value, packedIn: packedIn))
    }

    static func signedQuantity(_ value: Double, packedIn: Double?) -> String {
        if value > 0 { return "+" + quantity(value, packedIn: packedIn) }
        if value < 0 { return "\u{2212}" + quantity(-value, packedIn: packedIn) }
        return "0"
    }

    private static func recordText(_ entry: OfflineOperationEntry, lineWord: String) -> String? {
        if let reference = entry.item?.recordReference {
            guard let line = entry.item?.line else { return reference }
            return "\(reference) · \(lineWord) \(line)"
        }
        return present(entry.labels.title)
    }

    private static func recordLabel(_ recordType: String?) -> String? {
        guard let type = recordType.flatMap(OfflineRecordType.init(rawValue:)) else { return nil }
        switch type {
        case .deliveryNote: return "Delivery note"
        case .customerReturn: return "Return"
        case .stockDelivery: return "Stock delivery"
        case .location: return "Location"
        case .orgStock: return nil
        }
    }

    private static let skoRecordType = "sko"

    private static func present(_ text: String?) -> String? {
        guard let trimmed = text?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return trimmed
    }
}
