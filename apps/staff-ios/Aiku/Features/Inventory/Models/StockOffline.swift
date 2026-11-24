import Foundation

struct StockOfflineMove: Equatable, Sendable {
    let linkID: Int
    let quantity: Double
}

enum StockOfflinePayloads {
    static let targets = "targets"
    static let counts = "counts"
    static let versions = "versions"
    static let orgStockID = "org_stock_id"
    static let reason = "reason"
    static let note = "note"

    static func moveToLocation(sourceLinkID: Int, targetLocationID: Int, quantity: Double, clientRequestID: String) -> OfflinePayload {
        [
            OfflinePayloadKey.locationOrgStockID: .int(sourceLinkID),
            OfflinePayloadKey.locationID: .int(targetLocationID),
            OfflinePayloadKey.quantity: .number(GoodsOutOfflinePlanner.rounded(quantity)),
            OfflinePayloadKey.clientRequestID: .string(clientRequestID),
        ]
    }

    static func moveToRows(sourceLinkID: Int, moves: [StockOfflineMove], clientRequestID: String) -> OfflinePayload {
        let total = moves.reduce(0) { $0 + $1.quantity }
        let rows = moves.map { move -> OfflineJSON in
            .object([
                OfflinePayloadKey.locationOrgStockID: .int(move.linkID),
                OfflinePayloadKey.quantity: .number(GoodsOutOfflinePlanner.rounded(move.quantity)),
            ])
        }
        return [
            OfflinePayloadKey.locationOrgStockID: .int(sourceLinkID),
            Self.targets: .array(rows),
            OfflinePayloadKey.quantity: .number(GoodsOutOfflinePlanner.rounded(total)),
            OfflinePayloadKey.clientRequestID: .string(clientRequestID),
        ]
    }

    static func auditLocation(locationID: Int, counts: [MobileLocationAuditCount], versions: [Int: Int], clientRequestID: String) -> OfflinePayload? {
        let rows = counts.map { count in
            countRow(linkID: count.locationOrgStockId, quantity: Double(count.quantity), reason: count.reason, note: count.note)
        }
        guard let seen = seenVersions(of: counts.map(\.locationOrgStockId), in: versions) else { return nil }
        return [
            OfflinePayloadKey.locationID: .int(locationID),
            Self.counts: .array(rows),
            Self.versions: seen,
            OfflinePayloadKey.clientRequestID: .string(clientRequestID),
        ]
    }

    static func auditOrgStock(orgStockID: Int, counts: [MobileOrgStockAuditCount], versions: [Int: Int], clientRequestID: String) -> OfflinePayload? {
        let rows = counts.map { count in
            countRow(linkID: count.locationOrgStockId, quantity: count.quantity, reason: count.reason, note: count.note)
        }
        guard let seen = seenVersions(of: counts.map(\.locationOrgStockId), in: versions) else { return nil }
        return [
            Self.orgStockID: .int(orgStockID),
            Self.counts: .array(rows),
            Self.versions: seen,
            OfflinePayloadKey.clientRequestID: .string(clientRequestID),
        ]
    }

    private static func countRow(linkID: Int, quantity: Double, reason: String?, note: String?) -> OfflineJSON {
        .object([
            OfflinePayloadKey.locationOrgStockID: .int(linkID),
            OfflinePayloadKey.quantity: .number(GoodsOutOfflinePlanner.rounded(quantity)),
            Self.reason: reason.map(OfflineJSON.string) ?? .null,
            Self.note: .optional(note),
        ])
    }

    private static func seenVersions(of linkIDs: [Int], in versions: [Int: Int]) -> OfflineJSON? {
        var seen: [String: OfflineJSON] = [:]
        for linkID in linkIDs {
            guard let version = versions[linkID] else { return nil }
            seen[String(linkID)] = .int(version)
        }
        return linkIDs.isEmpty ? nil : .object(seen)
    }
}

extension MobileLocation {
    var versions: [Int: Int] {
        Dictionary((orgStocks ?? []).compactMap { stock in stock.version.map { (stock.locationOrgStockId, $0) } }, uniquingKeysWith: { first, _ in first })
    }
}

extension MobileOrgStockDetail {
    var versions: [Int: Int] {
        Dictionary(locations.compactMap { link in link.version.map { (link.locationOrgStockId, $0) } }, uniquingKeysWith: { first, _ in first })
    }
}

enum StockOfflineOverlay {
    static func levels(_ entries: [QueueEntry], from start: [Int: Double]) -> [Int: Double] {
        entries
            .filter { entry in entry.isUnsent && entry.operation.map { OfflineOperation.stock.contains($0) } == true }
            .reduce(start) { applied($1, to: $0) }
    }

    static func moves(_ entry: QueueEntry) -> [StockOfflineMove] {
        (entry.payload?[StockOfflinePayloads.targets]?.arrayValue ?? []).compactMap { target in
            guard let row = target.objectValue, let linkID = row[OfflinePayloadKey.locationOrgStockID]?.intValue else { return nil }
            return StockOfflineMove(linkID: linkID, quantity: row[OfflinePayloadKey.quantity]?.numberValue ?? 0)
        }
    }

    static func counted(_ entry: QueueEntry) -> [Int: Double] {
        let rows = (entry.payload?[StockOfflinePayloads.counts]?.arrayValue ?? []).compactMap { count -> (Int, Double)? in
            guard let row = count.objectValue,
                  let linkID = row[OfflinePayloadKey.locationOrgStockID]?.intValue,
                  let quantity = row[OfflinePayloadKey.quantity]?.numberValue else { return nil }
            return (linkID, quantity)
        }
        return Dictionary(rows, uniquingKeysWith: { _, last in last })
    }

    static func apply(_ entries: [QueueEntry], to location: MobileLocation) -> MobileLocation {
        guard let stocks = location.orgStocks else { return location }
        let start = Dictionary(stocks.map { ($0.locationOrgStockId, $0.quantity.value) }, uniquingKeysWith: { first, _ in first })
        let shown = levels(entries, from: start)
        guard shown != start else { return location }
        var overlaid = location
        overlaid.orgStocks = stocks.map { stock in
            guard let level = shown[stock.locationOrgStockId], level != stock.quantity.value else { return stock }
            var changed = stock
            changed.quantity = FlexibleDouble(level)
            changed.quantityLabel = StockFraction.label(level, packedIn: stock.packedIn?.value)
            return changed
        }
        return overlaid
    }

    static func apply(_ entries: [QueueEntry], to detail: MobileOrgStockDetail) -> MobileOrgStockDetail {
        let start = Dictionary(detail.locations.map { ($0.locationOrgStockId, $0.quantity.value) }, uniquingKeysWith: { first, _ in first })
        let shown = levels(entries, from: start)
        guard shown != start else { return detail }
        var overlaid = detail
        overlaid.locations = detail.locations.map { link in
            guard let level = shown[link.locationOrgStockId], level != link.quantity.value else { return link }
            var changed = link
            changed.quantity = FlexibleDouble(level)
            changed.quantityLabel = StockFraction.label(level, packedIn: detail.packedIn?.value)
            return changed
        }
        let before = detail.locations.reduce(0) { $0 + $1.quantity.value }
        let after = overlaid.locations.reduce(0) { $0 + $1.quantity.value }
        let change = GoodsOutOfflinePlanner.rounded(after - before)
        if change != 0 {
            let total = StockFraction.total((detail.quantityInLocations?.value ?? before) + change)
            overlaid.quantityInLocations = OptionalFlexibleDouble(total)
            overlaid.quantityInLocationsLabel = StockFraction.label(total, packedIn: detail.packedIn?.value)
        }
        return overlaid
    }

    private static func applied(_ entry: QueueEntry, to levels: [Int: Double]) -> [Int: Double] {
        var changed = levels
        if entry.operation == .moveStock {
            if let source = entry.payloadInt(OfflinePayloadKey.locationOrgStockID), let level = changed[source] {
                changed[source] = GoodsOutOfflinePlanner.rounded(max(0, level - entry.quantity))
            }
            for move in moves(entry) {
                if let level = changed[move.linkID] {
                    changed[move.linkID] = GoodsOutOfflinePlanner.rounded(level + move.quantity)
                }
            }
        } else {
            for (linkID, quantity) in counted(entry) where changed[linkID] != nil {
                changed[linkID] = quantity
            }
        }
        return changed
    }
}

enum StockOfflineText {
    struct Names {
        var sko: (Int) -> String? = { _ in nil }
        var linkLocation: (Int) -> String? = { _ in nil }
        var location: (Int) -> String? = { _ in nil }
    }

    @MainActor
    static func summary(_ entry: QueueEntry, names: Names) -> String {
        let quantity = OfflineTexts.quantity(entry.quantity)
        guard entry.operation == .moveStock else {
            let counted = StockOfflineOverlay.counted(entry).keys.sorted().compactMap { names.sko($0) ?? names.linkLocation($0) }
            return L10n.format("Count · %@", counted.isEmpty ? L10n.text(entry.operation?.label ?? "") : counted.joined(separator: ", "))
        }
        let source = entry.payloadInt(OfflinePayloadKey.locationOrgStockID)
        if let target = StockOfflineOverlay.moves(entry).first {
            let from = source.flatMap(names.linkLocation) ?? ""
            let to = names.linkLocation(target.linkID) ?? ""
            return L10n.format("Move · %@ from %@ to %@", quantity, from, to)
        }
        let sko = source.flatMap(names.sko) ?? ""
        let to = entry.payloadInt(OfflinePayloadKey.locationID).flatMap(names.location) ?? ""
        return L10n.format("Move · %@ × %@ to %@", quantity, sko, to)
    }
}
