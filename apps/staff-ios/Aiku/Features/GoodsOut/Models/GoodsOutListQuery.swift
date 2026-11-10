import Foundation

enum GoodsOutPickerFilter: String, CaseIterable, Hashable, Sendable {
    case me
    case noPicker
    case otherPickers

    var title: String {
        switch self {
        case .me: "Me"
        case .noPicker: "No picker"
        case .otherPickers: "Other pickers"
        }
    }

    func matches(_ note: GoodsOutDeliveryNote, currentUser: String) -> Bool {
        switch self {
        case .me: note.picker == currentUser
        case .noPicker: note.picker == nil
        case .otherPickers: note.picker != nil && note.picker != currentUser
        }
    }
}

enum GoodsOutDateFilter: String, CaseIterable, Hashable, Sendable {
    case today
    case yesterday
    case last7Days
    case overdue

    var title: String {
        switch self {
        case .today: "Today"
        case .yesterday: "Yesterday"
        case .last7Days: "Last 7 days"
        case .overdue: "Overdue"
        }
    }

    func matches(_ note: GoodsOutDeliveryNote) -> Bool {
        switch self {
        case .today: note.age == .today
        case .yesterday: note.age == .yesterday
        case .last7Days: note.age != .older
        case .overdue: note.isOverdue
        }
    }
}

enum GoodsOutSort: String, CaseIterable, Hashable, Sendable {
    case oldestFirst
    case newestFirst
    case items
    case weight
    case customer

    var title: String {
        switch self {
        case .oldestFirst: "Date (oldest first)"
        case .newestFirst: "Date (newest first)"
        case .items: "Items"
        case .weight: "Weight"
        case .customer: "Customer"
        }
    }

    func sorted(_ notes: [GoodsOutDeliveryNote]) -> [GoodsOutDeliveryNote] {
        notes.sorted { lhs, rhs in
            switch self {
            case .oldestFirst: (lhs.createdAt, lhs.reference) < (rhs.createdAt, rhs.reference)
            case .newestFirst: (lhs.createdAt, lhs.reference) > (rhs.createdAt, rhs.reference)
            case .items: (lhs.units, lhs.reference) > (rhs.units, rhs.reference)
            case .weight: (lhs.weightKg, lhs.reference) > (rhs.weightKg, rhs.reference)
            case .customer: (lhs.customer, lhs.reference) < (rhs.customer, rhs.reference)
            }
        }
    }
}

struct GoodsOutDeliveryNoteFilter: Hashable, Sendable {
    var states: Set<GoodsOutDeliveryNoteState> = []
    var pickers: Set<GoodsOutPickerFilter> = []
    var channels: Set<GoodsOutChannel> = []
    var shippers: Set<GoodsOutShipper> = []
    var dates: Set<GoodsOutDateFilter> = []
    var sort: GoodsOutSort = .oldestFirst

    var activeCount: Int { states.count + pickers.count + channels.count + shippers.count + dates.count }

    var summary: String {
        let count = activeCount
        let filters = count == 0 ? "No filters" : "\(count) \(count == 1 ? "filter" : "filters")"
        return "\(filters) · sorted by \(sort.title.lowercased())"
    }

    func matches(_ note: GoodsOutDeliveryNote, currentUser: String, includingState: Bool = true) -> Bool {
        if includingState, !states.isEmpty, !states.contains(note.state) { return false }
        if !pickers.isEmpty, !pickers.contains(where: { $0.matches(note, currentUser: currentUser) }) { return false }
        if !channels.isEmpty, !channels.contains(note.channel) { return false }
        if !shippers.isEmpty, !shippers.contains(where: { $0 == note.shipper }) { return false }
        if !dates.isEmpty, !dates.contains(where: { $0.matches(note) }) { return false }
        return true
    }
}

struct GoodsOutListQuery: Hashable, Sendable {
    var tab: GoodsOutDeliveryNoteState? = .todo
    var filter = GoodsOutDeliveryNoteFilter()
    var search = ""

    var effectiveStates: Set<GoodsOutDeliveryNoteState> {
        if !filter.states.isEmpty { return filter.states }
        if let tab { return [tab] }
        return Set(GoodsOutDeliveryNoteState.allCases)
    }

    func results(in notes: [GoodsOutDeliveryNote], currentUser: String) -> [GoodsOutDeliveryNote] {
        let states = effectiveStates
        let matching = notes.filter { note in
            states.contains(note.state)
                && filter.matches(note, currentUser: currentUser, includingState: false)
                && note.matches(search: search)
        }
        return filter.sort.sorted(matching)
    }

    func count(for state: GoodsOutDeliveryNoteState, in notes: [GoodsOutDeliveryNote], currentUser: String) -> Int {
        notes.filter { note in
            note.state == state
                && filter.matches(note, currentUser: currentUser, includingState: false)
                && note.matches(search: search)
        }.count
    }

    func tabs(in notes: [GoodsOutDeliveryNote], currentUser: String) -> [GoodsOutDeliveryNoteState] {
        let base = GoodsOutDeliveryNoteState.listTabs
        return count(for: .cancelled, in: notes, currentUser: currentUser) > 0 ? base + [.cancelled] : base
    }

    mutating func select(tab state: GoodsOutDeliveryNoteState) {
        tab = state
        filter.states = []
    }

    mutating func apply(_ newFilter: GoodsOutDeliveryNoteFilter) {
        filter = newFilter
        if !newFilter.states.isEmpty {
            tab = nil
        } else if tab == nil {
            tab = .todo
        }
    }
}

struct GoodsOutBacklogRow: Identifiable, Hashable, Sendable {
    let channel: GoodsOutChannel
    let counts: [GoodsOutDeliveryNoteState: Int]

    var id: GoodsOutChannel { channel }
    var total: Int { counts.values.reduce(0, +) }

    func count(_ state: GoodsOutDeliveryNoteState) -> Int { counts[state] ?? 0 }
}

enum GoodsOutBacklog {
    static func rows(for notes: [GoodsOutDeliveryNote]) -> [GoodsOutBacklogRow] {
        GoodsOutChannel.allCases.compactMap { channel in
            let channelNotes = notes.filter { $0.channel == channel && $0.state.isInBacklog }
            guard !channelNotes.isEmpty else { return nil }
            var counts: [GoodsOutDeliveryNoteState: Int] = [:]
            for state in GoodsOutDeliveryNoteState.backlogColumns {
                counts[state] = channelNotes.filter { $0.state == state }.count
            }
            return GoodsOutBacklogRow(channel: channel, counts: counts)
        }
    }

    static func packers(for notes: [GoodsOutDeliveryNote]) -> [(name: String, count: Int)] {
        let packing = notes.filter { $0.state == .packing || $0.state == .picked }
        let names = Set(packing.compactMap(\.picker)).sorted()
        return names.map { name in (name, packing.filter { $0.picker == name }.count) }
    }
}
