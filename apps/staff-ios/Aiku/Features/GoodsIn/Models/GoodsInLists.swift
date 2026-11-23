import Foundation

struct GoodsInListRow: Equatable, Sendable {
    let reference: String
    var date: String?
    var dateCaption: String?
    var party: String?
    var badges: [Chip] = []
    var details: [String] = []

    static func delivery(_ row: MobileStockDeliveryRow, in timeZone: TimeZone = .current) -> GoodsInListRow {
        let progress: String
        switch row.state {
        case GoodsInDeliveryStateKey.received, GoodsInDeliveryStateKey.checked:
            progress = "\(row.numberChecked) of \(row.numberItems) checked"
        case GoodsInDeliveryStateKey.bookingIn, GoodsInDeliveryStateKey.bookedIn:
            progress = "\(row.numberPlaced) of \(row.numberItems) placed"
        default:
            progress = "\(row.numberItems) \(GoodsInText.plural(row.numberItems, "item", "items"))"
        }
        let dispatched = ListDates.recordDate(row.dispatchedAt, in: timeZone)
        let date: String?
        let caption: String
        if !GoodsInDeliveryStateKey.beforeReceiving.contains(row.state) {
            date = ListDates.recordDate(row.receivedAt, in: timeZone)
            caption = RecordDateCaption.received
        } else if let dispatched {
            date = dispatched
            caption = RecordDateCaption.dispatched
        } else {
            date = ListDates.recordDate(row.estimatedReceivingDate, in: timeZone)
            caption = RecordDateCaption.expected
        }
        return GoodsInListRow(
            reference: row.reference,
            date: date,
            dateCaption: date == nil ? nil : RecordDateCaption.unlessRepeating(caption, status: row.stateLabel),
            party: nonEmpty(row.supplier?.label),
            badges: row.stateLabel.isEmpty ? [] : [Chip(row.stateLabel, GoodsInText.deliveryTone(row.state))],
            details: [nonEmpty(row.agentReference), progress].compactMap { $0 }
        )
    }

    static func goodsReturn(_ row: MobileReturnRow, in timeZone: TimeZone = .current) -> GoodsInListRow {
        let progress = row.numberHandled > 0
            ? "\(row.numberHandled) of \(row.numberItems) handled"
            : "\(row.numberItems) \(GoodsInText.plural(row.numberItems, "item", "items"))"
        let date = ListDates.recordDate(row.createdAt, in: timeZone)
        return GoodsInListRow(
            reference: row.reference,
            date: date,
            dateCaption: date == nil ? nil : RecordDateCaption.unlessRepeating(RecordDateCaption.received, status: row.stateLabel),
            party: nonEmpty(row.customer?.name),
            badges: row.stateLabel.isEmpty ? [] : [Chip(row.stateLabel, GoodsInText.returnTone(row.state))],
            details: [
                row.type == GoodsInReturnStateKey.cancellation ? nonEmpty(row.typeLabel) : nil,
                progress,
                nonEmpty(row.deliveryNote?.reference),
            ]
            .compactMap { $0 }
        )
    }

    private static func nonEmpty(_ text: String?) -> String? {
        guard let trimmed = text?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return trimmed
    }
}

enum GoodsInListOffline {
    static let activeDeliveryStates: Set<String> = [
        GoodsInDeliveryStateKey.dispatched,
        GoodsInDeliveryStateKey.received,
        GoodsInDeliveryStateKey.checked,
        GoodsInDeliveryStateKey.bookingIn,
        GoodsInDeliveryStateKey.bookedIn,
    ]

    static let returnStatesToProcess: Set<String> = [GoodsInReturnStateKey.received, GoodsInReturnStateKey.returning]

    static func deliveries(_ rows: [MobileStockDeliveryRow], filter: GoodsInDeliveryFilter, now: Date = Date(), in timeZone: TimeZone = .current) -> [MobileStockDeliveryRow] {
        let shown = rows.filter { row in
            isIn(filter.state, row: row)
                && (filter.source == nil || row.source == filter.source?.rawValue)
                && wasDispatched(in: filter.dispatched, row: row, now: now, timeZone: timeZone)
                && SavedListFilter.matches(filter.search, row.reference, row.agentReference, row.supplier?.name)
                && SavedListFilter.within(row.dispatchedAt, range: filter.dates, in: timeZone)
        }
        return sortedDeliveries(shown, by: filter.sort ?? .newest, timeZone: timeZone)
    }

    static func returns(_ rows: [MobileReturnRow], state: GoodsInReturnStateFilter, search: String, filter: ListFilter<GoodsInReturnSort>, in timeZone: TimeZone = .current) -> [MobileReturnRow] {
        let shown = rows.filter { row in
            isIn(state, row: row)
                && SavedListFilter.matches(search, row.reference)
                && SavedListFilter.within(row.createdAt, range: filter.dates, in: timeZone)
        }
        guard let sort = filter.sort else {
            return SavedListFilter.sorted(shown, descending: true, id: { $0.id }, key: { Self.instant($0.createdAt) })
        }
        switch sort {
        case .oldestReceived:
            return SavedListFilter.sorted(shown, descending: false, id: { $0.id }, key: { Self.instant($0.createdAt) })
        case .reference:
            return SavedListFilter.sorted(shown, descending: false, id: { $0.id }, key: { Self.nameKey($0.reference) })
        case .customer:
            return SavedListFilter.sorted(shown, descending: false, id: { $0.id }, key: { Self.nameKey($0.customer?.name) })
        }
    }

    static func nameKey(_ value: String?) -> String? {
        guard let trimmed = value?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return trimmed.lowercased()
    }

    private static func isIn(_ state: GoodsInDeliveryStateFilter, row: MobileStockDeliveryRow) -> Bool {
        state == .active ? activeDeliveryStates.contains(row.state) : row.state == state.rawValue
    }

    private static func isIn(_ state: GoodsInReturnStateFilter, row: MobileReturnRow) -> Bool {
        switch state {
        case .all: return true
        case .toProcess: return returnStatesToProcess.contains(row.state)
        default: return row.state == state.rawValue
        }
    }

    private static func wasDispatched(in bucket: GoodsInDispatchedBucket?, row: MobileStockDeliveryRow, now: Date, timeZone: TimeZone) -> Bool {
        guard let bucket else { return true }
        guard let day = SavedListFilter.day(row.dispatchedAt, in: timeZone) else { return false }
        let calendar = Calendar.gregorian(in: timeZone)
        let today = calendar.startOfDay(for: now)
        guard let weekStart = calendar.date(byAdding: .day, value: -6, to: today) else { return false }
        switch bucket {
        case .today: return day >= today
        case .week: return day >= weekStart
        case .older: return day < weekStart
        }
    }

    private static func sortedDeliveries(_ rows: [MobileStockDeliveryRow], by sort: GoodsInDeliverySort, timeZone: TimeZone) -> [MobileStockDeliveryRow] {
        switch sort {
        case .newest, .dispatchedDate:
            return SavedListFilter.sorted(rows, descending: true, id: { $0.id }, key: { Self.instant($0.dispatchedAt) })
        case .oldest:
            return SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { Self.instant($0.dispatchedAt) })
        case .reference:
            return SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { Self.nameKey($0.reference) })
        case .supplier:
            return SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { Self.nameKey($0.supplier?.label) })
        case .expectedDate:
            return SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { SavedListFilter.day($0.estimatedReceivingDate, in: timeZone) })
        case .items:
            return SavedListFilter.sorted(rows, descending: true, id: { $0.id }, key: { row -> Int? in row.numberItems })
        }
    }

    private static func instant(_ value: String?) -> Date? {
        guard let trimmed = value?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return ListDates.instant(trimmed) ?? SavedListFilter.day(trimmed)
    }
}
