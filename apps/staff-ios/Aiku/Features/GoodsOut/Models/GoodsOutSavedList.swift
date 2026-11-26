import Foundation

enum GoodsOutSavedList {
    static func rows(
        _ rows: [GoodsOutDeliveryNoteRowDTO],
        query: GoodsOutListQuery,
        userID: Int?,
        today: Date,
        timeZone: TimeZone = GoodsOutDateFormat.timeZone
    ) -> [GoodsOutDeliveryNoteRowDTO] {
        let bucket = query.apiBucket
        let filter = query.filter
        let matching = rows.filter { row in
            isInBucket(row.state, bucket: bucket)
                && isInChannel(row, filter: filter)
                && isForPicker(row, filter: filter, userID: userID)
                && SavedListFilter.matches(query.search, row.reference)
                && isWithinDates(row, filter: filter, today: today, timeZone: timeZone)
        }
        let ordered = sorted(matching, by: query.shownSort)
        guard bucket != "all", bucket != "dispatched" else { return ordered }
        return ordered.filter { $0.isPriority == true } + ordered.filter { $0.isPriority != true }
    }

    static func isInBucket(_ state: String, bucket: String) -> Bool {
        switch bucket {
        case "all": true
        case "in_warehouse": state != "dispatched" && state != "cancelled"
        default: state == bucket
        }
    }

    private static func isInChannel(_ row: GoodsOutDeliveryNoteRowDTO, filter: GoodsOutDeliveryNoteFilter) -> Bool {
        guard filter.channels.count == 1, let channel = filter.channels.first else { return true }
        return row.channel == channel.apiKey
    }

    private static func isForPicker(_ row: GoodsOutDeliveryNoteRowDTO, filter: GoodsOutDeliveryNoteFilter, userID: Int?) -> Bool {
        guard filter.pickers.count == 1, let picker = filter.pickers.first else { return true }
        switch picker {
        case .me: return userID == nil || row.picker?.id == userID
        case .noPicker: return row.picker == nil
        case .otherPickers: return row.picker != nil && row.picker?.id != userID
        }
    }

    private static func isWithinDates(_ row: GoodsOutDeliveryNoteRowDTO, filter: GoodsOutDeliveryNoteFilter, today: Date, timeZone: TimeZone) -> Bool {
        if filter.range.isSet {
            return SavedListFilter.within(row.date, range: filter.range)
        }
        guard filter.dates.count == 1, let preset = filter.dates.first else { return true }
        if preset == .overdue {
            return isOverdue(row, now: today, timeZone: timeZone)
        }
        guard let days = GoodsOutMapper.dateRange(preset, today: today, timeZone: timeZone) else { return true }
        let presetRange = ListDateRange(from: ListDates.day(days.from, in: timeZone), to: ListDates.day(days.to, in: timeZone))
        return SavedListFilter.within(row.date, range: presetRange, in: timeZone)
    }

    static let overdueStates: Set<String> = ["unassigned", "queued", "handling", "handling_blocked", "picked", "packing", "packed", "finalised"]

    static func overdueCutoff(now: Date, timeZone: TimeZone) -> Date {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        var cutoff = calendar.date(byAdding: .day, value: -1, to: now) ?? now
        while calendar.isDateInWeekend(cutoff) {
            cutoff = calendar.date(byAdding: .day, value: -1, to: cutoff) ?? cutoff
        }
        return cutoff
    }

    static func isOverdue(_ row: GoodsOutDeliveryNoteRowDTO, now: Date, timeZone: TimeZone) -> Bool {
        guard overdueStates.contains(row.state), let date = instant(row.date) else { return false }
        return date < overdueCutoff(now: now, timeZone: timeZone)
    }

    private static func sorted(_ rows: [GoodsOutDeliveryNoteRowDTO], by sort: GoodsOutSort) -> [GoodsOutDeliveryNoteRowDTO] {
        switch sort {
        case .oldestFirst:
            SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { instant($0.date) })
        case .newestFirst:
            SavedListFilter.sorted(rows, descending: true, id: { $0.id }, key: { instant($0.date) })
        case .items:
            SavedListFilter.sorted(rows, descending: true, id: { $0.id }, key: { $0.numberItems })
        case .weight:
            SavedListFilter.sorted(rows, descending: true, id: { $0.id }, key: { $0.estimatedWeightKg })
        case .customer:
            SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { nameKey($0.customerName) })
        case .reference:
            SavedListFilter.sorted(rows, descending: false, id: { $0.id }, key: { nameKey($0.reference) })
        }
    }

    private static func instant(_ text: String?) -> Date? {
        text.flatMap { ListDates.instant($0) }
    }

    private static func nameKey(_ text: String?) -> String? {
        guard let trimmed = text?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return trimmed.lowercased()
    }
}
