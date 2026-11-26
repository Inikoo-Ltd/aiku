import Foundation
import Testing
@testable import Aiku

private actor ListConnection {
    var isOffline = true

    func goOnline() { isOffline = false }
}

private func savedRowJSON(
    id: Int,
    reference: String,
    state: String = "unassigned",
    date: String? = "2026-11-10T12:00:00Z",
    customer: String? = "Bath Bombs Ltd",
    channel: String = "b2b",
    items: Int = 3,
    weight: Double? = 2.5,
    isPriority: Bool = false,
    isCollection: Bool = false,
    pickerID: Int? = nil
) -> String {
    let dateValue = date.map { "\"\($0)\"" } ?? "null"
    let customerValue = customer.map { "\"\($0)\"" } ?? "null"
    let weightValue = weight.map { String($0) } ?? "null"
    let pickerValue = pickerID.map { #"{"id": \#($0), "name": "Picker \#($0)"}"# } ?? "null"
    return """
    {"id": \(id), "slug": "x", "reference": "\(reference)", "type": "order", "state": "\(state)", "state_label": "S", "date": \(dateValue),
     "customer_name": \(customerValue), "is_customer_vip": false, "shop": {"id": 1, "name": "AW", "type": "b2b"}, "channel": "\(channel)",
     "number_items": \(items), "estimated_weight_kg": \(weightValue), "parcels": 0, "is_premium_dispatch": false, "is_priority": \(isPriority),
     "is_collection": \(isCollection), "picker": \(pickerValue), "packer": null, "trolleys": [], "picked_bays": [],
     "waiting_warehouse_count": 0, "waiting_crm_count": 0, "can_add_to_picking_session": true}
    """
}

private let savedRowsJSON = [
    savedRowJSON(id: 1, reference: "AR100", customer: "Zeta Shop", isPriority: true),
    savedRowJSON(id: 2, reference: "AR101", date: "2026-11-08T12:00:00Z", customer: "alpha store", channel: "partners", items: 10, weight: 0.5, pickerID: 7),
    savedRowJSON(id: 3, reference: "AR102", state: "handling", date: "2026-11-11T09:00:00Z", customer: "Mid Ltd", items: 1, weight: nil, pickerID: 8),
    savedRowJSON(id: 4, reference: "AR103", state: "dispatched", date: "2026-11-05T12:00:00Z", customer: "Beta", items: 5, weight: 9.0),
    savedRowJSON(id: 5, reference: "BX200", date: nil, customer: nil, channel: "dropshipping", items: 2, weight: 1.0),
    savedRowJSON(id: 6, reference: "AR104", state: "cancelled", date: "2026-11-09T12:00:00Z", customer: "Omega", items: 4, weight: nil)
]

private func decodeRows(_ rows: [String]) throws -> [GoodsOutDeliveryNoteRowDTO] {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode([GoodsOutDeliveryNoteRowDTO].self, from: Data("[\(rows.joined(separator: ","))]".utf8))
}

private func savedPage(_ rows: [String]) -> Data {
    Data(#"{"data": [\#(rows.joined(separator: ","))]}"#.utf8)
}

private let listWarehouseJSON = """
{"id": 4, "code": "PAR", "name": "Paris", "organisation": {"id": 1, "name": "AW"},
 "can": {"view_locations": true, "edit_locations": false, "view_goods_out": true, "pick_and_pack": true, "supervise_goods_out": false}}
"""

private let today = GoodsOutDateFormat.date(day: 11, hour: 10, minute: 0)

private func day(_ text: String) throws -> Date {
    try #require(ListDates.day(text))
}

private func listService(connection: ListConnection) -> GoodsOutService {
    GoodsOutService(
        deliveryNotes: { _, request in
            if await connection.isOffline { throw APIError.offline }
            let decoder = JSONDecoder()
            decoder.keyDecodingStrategy = .convertFromSnakeCase
            return try decoder.decode(GoodsOutDeliveryNotesPageDTO.self, from: Data("""
            {"data": {"delivery_notes": [\(savedRowJSON(id: 9, reference: "AR900"))], "can_work_in_warehouse": true},
             "pagination": {"current_page": \(request.page), "last_page": 1, "per_page": 50, "total": 1}}
            """.utf8))
        },
        counts: { _, _ in throw APIError.offline },
        deliveryNote: { _ in throw APIError.offline },
        perform: { _, _ in throw APIError.offline },
        trolleys: { _ in [] },
        pickedBays: { _ in [] },
        pickers: { _ in [] },
        dispatchPersonnel: { _, _ in [] }
    )
}

@MainActor
private func listStore(connection: ListConnection, lists: SavedLists) throws -> GoodsOutStore {
    let store = GoodsOutStore(service: listService(connection: connection), now: { today })
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    store.configure(userID: 7, warehouse: try decoder.decode(MobileWarehouse.self, from: Data(listWarehouseJSON.utf8)), currentUser: "Artha")
    store.savedLists = lists
    store.savedListUserID = { 7 }
    return store
}

@MainActor
struct GoodsOutListFilterTests {
    @Test("Sort, date range and presets go to the request with the keys the backend accepts")
    func requestCarriesSortAndDates() throws {
        var query = GoodsOutListQuery()
        #expect(GoodsOutMapper.listRequest(for: query, page: 1, today: today).sort == nil)

        query.apply(GoodsOutDeliveryNoteFilter(sort: .reference))
        #expect(GoodsOutMapper.listRequest(for: query, page: 1, today: today).sort == "reference")

        query.select(tab: .dispatched)
        query.apply(GoodsOutDeliveryNoteFilter(sort: .oldestFirst))
        var request = GoodsOutMapper.listRequest(for: query, page: 1, today: today)
        #expect(request.bucket == "dispatched")
        #expect(request.sort == "date")

        #expect(GoodsOutSort.allCases.map(\.apiKey) == ["date", "-date", "-number_items", "-effective_weight", "customer_name", "reference"])

        query.apply(GoodsOutDeliveryNoteFilter(dates: [.today]))
        request = GoodsOutMapper.listRequest(for: query, page: 1, today: today)
        #expect(request.from == "2026-11-11")
        #expect(request.to == "2026-11-11")

        let third = try day("2026-11-03")
        let ninth = try day("2026-11-09")
        var both = GoodsOutDeliveryNoteFilter(dates: [.today])
        both.range = ListDateRange(from: third, to: ninth)
        query.apply(both)
        request = GoodsOutMapper.listRequest(for: query, page: 1, today: today)
        #expect(request.from == "2026-11-03")
        #expect(request.to == "2026-11-09")
        #expect(request.queryItems.first { $0.name == "from" }?.value == "2026-11-03")
        #expect(request.queryItems.first { $0.name == "to" }?.value == "2026-11-09")

        query.apply(GoodsOutDeliveryNoteFilter(range: ListDateRange(from: nil, to: ninth)))
        request = GoodsOutMapper.listRequest(for: query, page: 1, today: today)
        #expect(request.from == nil)
        #expect(request.to == "2026-11-09")
    }

    @Test("The first sort option is the server default of the tab, and choosing it clears the sort")
    func defaultSortFollowsTheTab() {
        let todo = GoodsOutListQuery()
        #expect(todo.defaultSort == .oldestFirst)
        #expect(todo.sortOptions.first == ListSortOption<GoodsOutSort>(sort: nil, label: "Date (oldest first)"))
        #expect(todo.sortOptions.compactMap(\.sort) == [.newestFirst, .items, .weight, .customer, .reference])

        let dispatched = GoodsOutListQuery(tab: .dispatched)
        #expect(dispatched.defaultSort == .newestFirst)
        #expect(dispatched.sortOptions.first?.label == "Date (newest first)")
        #expect(GoodsOutListQuery(tab: .cancelled).defaultSort == .newestFirst)

        var query = GoodsOutListQuery()
        query.apply(GoodsOutDeliveryNoteFilter(sort: .newestFirst))
        #expect(query.filter.sort == .newestFirst)
        #expect(query.filter.activeCount == 1)
        query.select(tab: .dispatched)
        #expect(query.filter.sort == nil)
        #expect(query.shownSort == .newestFirst)

        query.apply(GoodsOutDeliveryNoteFilter(sort: .oldestFirst))
        query.select(tab: .picking)
        #expect(query.filter.sort == nil)
    }

    @Test("A date preset clears the range and a range clears the presets")
    func presetsAndRangeExcludeEachOther() throws {
        let from = try day("2026-11-03")
        var filter = GoodsOutDeliveryNoteFilter().withRange(ListDateRange(from: from, to: nil))
        #expect(filter.range.isSet)
        #expect(filter.activeCount == 1)

        filter.toggleDate(.today, allowsSeveral: false)
        #expect(filter.dates == [.today])
        #expect(filter.range.isSet == false)

        filter = filter.withRange(ListDateRange(from: from, to: from))
        #expect(filter.dates.isEmpty)
        #expect(filter.range.from == from)

        filter.toggleDate(.yesterday, allowsSeveral: false)
        filter.toggleDate(.yesterday, allowsSeveral: false)
        #expect(filter.dates.isEmpty)
        #expect(filter.range.isSet == false)

        filter.toggleDate(.today, allowsSeveral: true)
        filter.toggleDate(.yesterday, allowsSeveral: true)
        #expect(filter.dates == [.today, .yesterday])

        filter = filter.withRange(ListDateRange())
        #expect(filter.dates == [.today, .yesterday])
    }

    @MainActor
    @Test("The active filters line names the filters, the range and the sort")
    func summaryNamesWhatIsChosen() throws {
        let tenth = try day("2026-11-10")
        let twelfth = try day("2026-11-12")
        var filter = GoodsOutDeliveryNoteFilter(pickers: [.me], channels: [.partners], sort: .reference)
        filter = filter.withRange(ListDateRange(from: tenth, to: twelfth))
        #expect(filter.summary() == [
            L10n.text("Me"),
            L10n.text("Partners"),
            "\(L10n.text("Date")): 10 Nov 2026 – 12 Nov 2026",
            "Sorted by \(L10n.text("Reference").lowercased())"
        ])
        #expect(GoodsOutDeliveryNoteFilter().summary().isEmpty)
    }

    @Test("Saved rows keep to the tab, channel, picker and the reference search")
    func savedRowsFollowTheTabAndFilters() throws {
        let rows = try decodeRows(savedRowsJSON)

        #expect(GoodsOutSavedList.rows(rows, query: GoodsOutListQuery(), userID: 7, today: today).map(\.id) == [1, 2, 5])
        #expect(GoodsOutSavedList.rows(rows, query: GoodsOutListQuery(tab: .dispatched), userID: 7, today: today).map(\.id) == [4])
        #expect(GoodsOutSavedList.rows(rows, query: GoodsOutListQuery(tab: .picking), userID: 7, today: today).map(\.id) == [3])
        #expect(Set(GoodsOutSavedList.rows(rows, query: GoodsOutListQuery(tab: nil), userID: 7, today: today).map(\.id)) == [1, 2, 3, 5])

        var partners = GoodsOutListQuery()
        partners.apply(GoodsOutDeliveryNoteFilter(channels: [.partners]))
        #expect(GoodsOutSavedList.rows(rows, query: partners, userID: 7, today: today).map(\.id) == [2])

        var mine = GoodsOutListQuery()
        mine.apply(GoodsOutDeliveryNoteFilter(pickers: [.me]))
        #expect(GoodsOutSavedList.rows(rows, query: mine, userID: 7, today: today).map(\.id) == [2])

        var nobody = GoodsOutListQuery()
        nobody.apply(GoodsOutDeliveryNoteFilter(pickers: [.noPicker]))
        #expect(GoodsOutSavedList.rows(rows, query: nobody, userID: 7, today: today).map(\.id) == [1, 5])

        var others = GoodsOutListQuery(tab: .picking)
        others.apply(GoodsOutDeliveryNoteFilter(pickers: [.otherPickers]))
        #expect(GoodsOutSavedList.rows(rows, query: others, userID: 7, today: today).map(\.id) == [3])

        var search = GoodsOutListQuery()
        search.search = " ar10 "
        #expect(GoodsOutSavedList.rows(rows, query: search, userID: 7, today: today).map(\.id) == [1, 2])
        search.search = "Zeta"
        #expect(GoodsOutSavedList.rows(rows, query: search, userID: 7, today: today).isEmpty)
    }

    @Test("Saved rows keep to the date range or the date preset")
    func savedRowsFollowTheDates() throws {
        let rows = try decodeRows(savedRowsJSON)

        let ninth = try day("2026-11-09")
        let tenth = try day("2026-11-10")
        let eighth = try day("2026-11-08")
        var ranged = GoodsOutListQuery()
        ranged.apply(GoodsOutDeliveryNoteFilter(range: ListDateRange(from: ninth, to: tenth)))
        #expect(GoodsOutSavedList.rows(rows, query: ranged, userID: 7, today: today).map(\.id) == [1])

        var untilEighth = GoodsOutListQuery()
        untilEighth.apply(GoodsOutDeliveryNoteFilter(range: ListDateRange(from: nil, to: eighth)))
        #expect(GoodsOutSavedList.rows(rows, query: untilEighth, userID: 7, today: today).map(\.id) == [2])

        var yesterday = GoodsOutListQuery()
        yesterday.apply(GoodsOutDeliveryNoteFilter(dates: [.yesterday]))
        #expect(GoodsOutSavedList.rows(rows, query: yesterday, userID: 7, today: today).map(\.id) == [1])

        var lastWeek = GoodsOutListQuery()
        lastWeek.apply(GoodsOutDeliveryNoteFilter(dates: [.last7Days]))
        #expect(GoodsOutSavedList.rows(rows, query: lastWeek, userID: 7, today: today).map(\.id) == [1, 2])
    }

    @Test("Saved rows sort on the phone, with empty values last and priority first in the warehouse")
    func savedRowsSort() throws {
        let rows = try decodeRows(savedRowsJSON)
        func ids(_ sort: GoodsOutSort?, tab: GoodsOutDeliveryNoteState? = .cancelled) -> [Int] {
            var query = GoodsOutListQuery(tab: tab)
            query.filter.sort = sort
            return GoodsOutSavedList.rows(rows, query: query, userID: 7, today: today).map(\.id)
        }

        #expect(ids(nil) == [3, 1, 6, 2, 4, 5])
        #expect(ids(.newestFirst) == [3, 1, 6, 2, 4, 5])
        #expect(ids(.oldestFirst) == [4, 2, 6, 1, 3, 5])
        #expect(ids(.items) == [2, 4, 6, 1, 5, 3])
        #expect(ids(.weight) == [4, 1, 5, 2, 6, 3])
        #expect(ids(.customer) == [2, 4, 3, 6, 1, 5])
        #expect(ids(.reference) == [1, 2, 3, 4, 6, 5])

        #expect(ids(nil, tab: .todo) == [1, 2, 5])
        #expect(ids(.reference, tab: .todo) == [1, 2, 5])
        #expect(ids(.items, tab: .todo) == [1, 2, 5])
        #expect(ids(.items, tab: nil) == [1, 2, 5, 3])
    }

    @Test("A list row shows the note's date with its year, the customer and the details")
    func rowMapping() throws {
        let rows = try decodeRows([
            savedRowJSON(id: 1, reference: "AR100", isCollection: true),
            savedRowJSON(id: 2, reference: "AR101", date: nil, channel: "partners", items: 1, weight: nil)
        ])
        let collected = GoodsOutMapper.note(from: rows[0], currentUserID: 7, currentUser: "Artha", now: today)
        #expect(collected.recordDate == "10 Nov 2026")
        #expect(collected.customer == "Bath Bombs Ltd")
        #expect(collected.listDetails(currentUser: "Artha") == ["Wholesale", "Collection", "3 items", "2.5 kg"])

        let undated = GoodsOutMapper.note(from: rows[1], currentUserID: 7, currentUser: "Artha", now: today)
        #expect(undated.recordDate == nil)
        #expect(undated.listDetails(currentUser: "Artha") == ["Partners", "1 item"])

        let sample = try #require(GoodsOutSampleData.notes.first)
        #expect(sample.recordDate == ListDates.text(sample.createdAt))
    }

    @Test("Sample notes keep to the date range")
    func sampleNotesFollowTheRange() throws {
        let notes = GoodsOutSampleData.notes
        let target = try #require(notes.first)
        var query = GoodsOutListQuery(tab: target.state)
        query.apply(GoodsOutDeliveryNoteFilter(range: ListDateRange(from: target.createdAt, to: target.createdAt)))
        #expect(query.results(in: notes, currentUser: "Alex Morgan").map(\.reference).contains(target.reference))

        let longAgo = try day("2020-01-01")
        query.apply(GoodsOutDeliveryNoteFilter(range: ListDateRange(from: longAgo, to: longAgo)))
        #expect(query.results(in: notes, currentUser: "Alex Morgan").isEmpty)

        var byReference = GoodsOutListQuery()
        byReference.apply(GoodsOutDeliveryNoteFilter(sort: .reference))
        let references = byReference.results(in: notes, currentUser: "Alex Morgan").map(\.reference)
        #expect(references.count > 1)
        #expect(references == references.sorted())
    }

    @MainActor
    @Test("Offline, the list shows the saved rows filtered, searched and counted on the phone")
    func offlineListUsesTheSavedRows() async throws {
        let lists = SavedLists(root: OfflineTestData.temporaryDirectory())
        lists.remember(userID: 7, warehouseID: 4, kind: .deliveryNotes, page: savedPage(savedRowsJSON))
        let connection = ListConnection()
        let store = try listStore(connection: connection, lists: lists)

        await store.loadList()
        #expect(store.listSavedAt != nil)
        #expect(store.listError == nil)
        #expect(store.listResults.map(\.reference) == ["AR100", "AR101", "BX200"])
        #expect(store.hasMoreList == false)
        #expect(store.noteID(for: "AR101") == 2)
        #expect(store.listResults.first?.recordDate == "10 Nov 2026")

        store.toggleSessionSelection("AR101")
        store.listQuery.search = "ar101"
        await store.loadList()
        #expect(store.listResults.map(\.reference) == ["AR101"])
        #expect(store.selectedForSession == ["AR101"])

        #expect(store.savedListTotal(for: GoodsOutListQuery(tab: .dispatched)) == 1)
        #expect(store.savedListTotal(for: GoodsOutListQuery(tab: nil)) == 4)

        await connection.goOnline()
        await store.loadList()
        #expect(store.listSavedAt == nil)
        #expect(store.listResults.map(\.reference) == ["AR900"])
        #expect(store.savedListTotal(for: GoodsOutListQuery()) == nil)
    }

    @MainActor
    @Test("Offline without a saved copy, the list says it could not load")
    func offlineWithoutSavedRows() async throws {
        let store = try listStore(connection: ListConnection(), lists: SavedLists(root: OfflineTestData.temporaryDirectory()))

        await store.loadList()
        #expect(store.listSavedAt == nil)
        #expect(store.listError == GoodsOutMapper.message(for: APIError.offline))
        #expect(store.listResults.isEmpty)
    }

    @Test("The state chips are counted with the list's search, dates, picker and Overdue, as on Android")
    func countsFollowTheListFilters() throws {
        var query = GoodsOutListQuery()
        query.search = "  AR27  "
        query.apply(GoodsOutDeliveryNoteFilter(pickers: [.me], dates: [.today]))

        let counts = GoodsOutMapper.listRequest(for: query, page: 1, today: today).countsRequest

        #expect(counts == GoodsOutCountsRequest(picker: "me", search: "AR27", from: "2026-11-11", to: "2026-11-11", overdue: false))
        #expect(counts.queryItems.map(\.name) == ["picker", "search", "from", "to"])
    }

    @Test("Overdue is sent to the list and the counts online, without a date range")
    func overdueGoesToTheServer() {
        var query = GoodsOutListQuery()
        query.apply(GoodsOutDeliveryNoteFilter(dates: [.overdue]))

        let request = GoodsOutMapper.listRequest(for: query, page: 1, today: today)

        #expect(request.overdue)
        #expect(request.from == nil && request.to == nil)
        #expect(request.queryItems.first { $0.name == "overdue" }?.value == "1")
        #expect(request.countsRequest.queryItems == [URLQueryItem(name: "overdue", value: "1")])

        var range = GoodsOutDeliveryNoteFilter(dates: [.overdue])
        range.range = ListDateRange(from: today, to: today)
        query.apply(range)
        #expect(GoodsOutMapper.listRequest(for: query, page: 1, today: today).overdue == false)
    }

    @Test("Offline, Overdue uses the warehouse page's late rule: before the last working day, still in the warehouse")
    func overdueOnTheSavedCopy() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        func row(_ id: Int, state: String, date: String) throws -> GoodsOutDeliveryNoteRowDTO {
            try decoder.decode(GoodsOutDeliveryNoteRowDTO.self, from: Data(savedRowJSON(id: id, reference: "AR\(id)", state: state, date: date).utf8))
        }
        let rows = [
            try row(1, state: "unassigned", date: "2026-11-09T08:00:00Z"),
            try row(2, state: "unassigned", date: "2026-11-11T08:00:00Z"),
            try row(3, state: "dispatched", date: "2026-11-02T08:00:00Z"),
            try row(4, state: "packed", date: "2026-11-05T08:00:00Z"),
        ]
        var query = GoodsOutListQuery()
        query.apply(GoodsOutDeliveryNoteFilter(states: Set(GoodsOutDeliveryNoteState.allCases), dates: [.overdue]))

        #expect(GoodsOutSavedList.rows(rows, query: query, userID: 7, today: today).map(\.id).sorted() == [1, 4])

        let monday = GoodsOutDateFormat.date(day: 16, hour: 10, minute: 0)
        let friday = GoodsOutDateFormat.date(day: 13, hour: 10, minute: 0)
        #expect(GoodsOutSavedList.overdueCutoff(now: monday, timeZone: GoodsOutDateFormat.timeZone) == friday)
    }
}
