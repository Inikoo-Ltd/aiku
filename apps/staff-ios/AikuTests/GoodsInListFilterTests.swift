import Foundation
import Testing
@testable import Aiku

private let listMeta = MobileLocationsPage.Meta(currentPage: 1, lastPage: 1, perPage: 50, total: 1)

private let savedDeliveriesPage = #"""
{"data":[
{"id":1,"reference":"SD-3","agent_reference":"AG-7","supplier":{"type":"OrgPartner","code":"BEE","name":"Beeswax Co"},"source":"partners","state":"dispatched","state_label":"Dispatched","dispatched_at":"2026-11-20T09:00:00+00:00","received_at":null,"estimated_receiving_date":"2026-11-25","number_items":30,"number_checked":0,"number_placed":0},
{"id":2,"reference":"SD-1","agent_reference":"AWX-9","supplier":{"type":"OrgSupplier","code":"WICK","name":"Wick House"},"source":"suppliers","state":"received","state_label":"Received","dispatched_at":"2026-11-10T09:00:00+00:00","received_at":"2026-11-18T08:00:00+00:00","estimated_receiving_date":"2026-11-15","number_items":3,"number_checked":1,"number_placed":0},
{"id":3,"reference":"SD-2","agent_reference":null,"supplier":{"type":"OrgSupplier","code":"AMB","name":"Amber Oils"},"source":"suppliers","state":"booked_in","state_label":"Booked in","dispatched_at":"2026-11-01T09:00:00+00:00","received_at":"2026-11-03T08:00:00+00:00","estimated_receiving_date":null,"number_items":12,"number_checked":12,"number_placed":5},
{"id":4,"reference":"SD-4","agent_reference":null,"supplier":{"type":"OrgAgent","code":"AGT","name":"Agent Asia"},"source":"agents","state":"in_process","state_label":"In process","dispatched_at":null,"received_at":null,"estimated_receiving_date":"2026-12-01","number_items":8,"number_checked":0,"number_placed":0},
{"id":5,"reference":"SD-5","agent_reference":null,"supplier":{"type":"OrgSupplier","code":"ZN","name":"Zinc Ltd"},"source":"suppliers","state":"placed","state_label":"Placed","dispatched_at":"2026-10-01T09:00:00+00:00","received_at":"2026-10-03T08:00:00+00:00","estimated_receiving_date":null,"number_items":1,"number_checked":1,"number_placed":1}
],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":5}}
"""#

private let savedReturnsPage = #"""
{"data":[
{"id":10,"reference":"GB-3","type":"return","type_label":"Return","state":"received","state_label":"Received","created_at":"2026-11-20T10:00:00+00:00","customer":{"name":"Zed Crafts"},"delivery_note":{"id":5,"reference":"DN-55"},"number_items":2,"number_handled":0,"handler":null},
{"id":11,"reference":"GB-1","type":"cancellation","type_label":"Cancellation","state":"returning","state_label":"Returning","created_at":"2026-11-10T10:00:00+00:00","customer":{"name":"Amber Shop"},"delivery_note":null,"number_items":4,"number_handled":1,"handler":null},
{"id":12,"reference":"GB-2","type":"return","type_label":"Return","state":"done","state_label":"Done","created_at":"2026-11-01T10:00:00+00:00","customer":null,"delivery_note":null,"number_items":1,"number_handled":1,"handler":null}
],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":3}}
"""#

@MainActor
struct GoodsInListFilterTests {
    private final class Server: @unchecked Sendable {
        private let lock = NSLock()
        private var deliveryQueries: [MobileStockDeliveriesQuery] = []
        private var returnQueries: [MobileReturnsQuery] = []
        private var online: Bool

        init(online: Bool = true) {
            self.online = online
        }

        var isOnline: Bool {
            get { lock.withLock { online } }
            set { lock.withLock { online = newValue } }
        }

        var lastDeliveryQuery: MobileStockDeliveriesQuery? { lock.withLock { deliveryQueries.last } }
        var lastReturnQuery: MobileReturnsQuery? { lock.withLock { returnQueries.last } }

        func answerDeliveries(_ query: MobileStockDeliveriesQuery) throws -> MobileStockDeliveriesPage {
            let answers: Bool = lock.withLock {
                deliveryQueries.append(query)
                return online
            }
            guard answers else { throw APIError.offline }
            return MobileStockDeliveriesPage(data: [MobileStockDeliveryRow(id: 99, reference: "LIVE-1", state: "dispatched")], meta: listMeta)
        }

        func answerReturns(_ query: MobileReturnsQuery) throws -> MobileReturnsPage {
            let answers: Bool = lock.withLock {
                returnQueries.append(query)
                return online
            }
            guard answers else { throw APIError.offline }
            return MobileReturnsPage(data: [MobileReturnRow(id: 98, reference: "LIVE-R", state: "received")], meta: listMeta)
        }

        var service: GoodsInService {
            GoodsInService(
                deliveries: { [self] _, query in try self.answerDeliveries(query) },
                returns: { [self] _, query in try self.answerReturns(query) }
            )
        }
    }

    private let utc = TimeZone(identifier: "UTC")!

    private func day(_ text: String, in timeZone: TimeZone = .current) throws -> Date {
        try #require(ListDates.day(text, in: timeZone))
    }

    private func savedDeliveries() throws -> [MobileStockDeliveryRow] {
        try GoodsInDecoding.decode(MobileStockDeliveriesPage.self, from: Data(savedDeliveriesPage.utf8)).data
    }

    private func savedReturns() throws -> [MobileReturnRow] {
        try GoodsInDecoding.decode(MobileReturnsPage.self, from: Data(savedReturnsPage.utf8)).data
    }

    private func listsHolding(_ kind: SavedListKind, page: String) -> SavedLists {
        let lists = SavedLists(root: OfflineTestData.temporaryDirectory())
        lists.remember(userID: 7, warehouseID: 1, kind: kind, page: Data(page.utf8))
        return lists
    }

    @Test func stockDeliveriesSendTheDateRangeAndSortTheServerAccepts() throws {
        let range = ListDateRange(from: try day("2026-11-10"), to: try day("2026-11-12"))
        let filter = GoodsInDeliveryFilter(state: .active, source: .suppliers, search: " SD ", dispatched: .week, sort: .supplier, dates: range)
        let items = filter.query(page: 2).queryItems

        #expect(items.contains(URLQueryItem(name: "from", value: "2026-11-10")))
        #expect(items.contains(URLQueryItem(name: "to", value: "2026-11-12")))
        #expect(items.contains(URLQueryItem(name: "sort", value: "supplier")))
        #expect(items.contains(URLQueryItem(name: "q", value: "SD")))
        #expect(items.contains(URLQueryItem(name: "page", value: "2")))
        #expect(filter.activeFilterCount == 3)
        #expect(filter.panelCount == 4)
        #expect(filter.cleared() == GoodsInDeliveryFilter(state: .active, search: " SD "))

        let plain = GoodsInDeliveryFilter().query(page: 1).queryItems.map(\.name)
        #expect(!plain.contains("from"))
        #expect(!plain.contains("to"))
        #expect(!plain.contains("sort"))
    }

    @Test func stockDeliverySortsAreTheBackendKeysWithTheServerDefaultFirst() {
        let accepted: Set<String> = ["date", "dispatched_at", "reference", "number_items", "supplier", "estimated_receiving_date"]
        let sent = GoodsInDeliverySort.options.compactMap { $0.sort?.rawValue }

        #expect(GoodsInDeliverySort.options.first?.sort == nil)
        #expect(sent.allSatisfy { accepted.contains($0.hasPrefix("-") ? String($0.dropFirst()) : $0) })
        #expect(sent.contains("estimated_receiving_date"))
        #expect(sent.contains("supplier"))
        #expect(sent.contains("date"))
    }

    @Test func returnsSendTheDateRangeAndSortTheServerAccepts() throws {
        let range = ListDateRange(from: try day("2026-11-01"), to: nil)
        let items = MobileReturnsQuery(state: .received, search: " GB ", dates: range, sort: .customer).queryItems

        #expect(items.contains(URLQueryItem(name: "state", value: "received")))
        #expect(items.contains(URLQueryItem(name: "q", value: "GB")))
        #expect(items.contains(URLQueryItem(name: "from", value: "2026-11-01")))
        #expect(items.contains(URLQueryItem(name: "sort", value: "customer")))
        #expect(!items.map(\.name).contains("to"))
        #expect(!MobileReturnsQuery().queryItems.map(\.name).contains("sort"))

        let accepted: Set<String> = ["created_at", "reference", "customer"]
        #expect(GoodsInReturnSort.options.first?.sort == nil)
        #expect(GoodsInReturnSort.options.compactMap { $0.sort?.rawValue }.allSatisfy { accepted.contains($0) })
    }

    @Test func theDeliveriesFeedAsksForTheAppliedFilter() async throws {
        let server = Server()
        let feed = GoodsInDeliveriesFeed(warehouseID: 1, service: server.service)
        var filter = feed.filter
        filter.dates = ListDateRange(from: try day("2026-11-01"), to: try day("2026-11-30"))
        filter.sort = .expectedDate

        await feed.apply(filter)

        let items = try #require(server.lastDeliveryQuery).queryItems
        #expect(items.contains(URLQueryItem(name: "from", value: "2026-11-01")))
        #expect(items.contains(URLQueryItem(name: "to", value: "2026-11-30")))
        #expect(items.contains(URLQueryItem(name: "sort", value: "estimated_receiving_date")))
        #expect(feed.savedAt == nil)

        await feed.clearFilters()
        let cleared = try #require(server.lastDeliveryQuery)
        #expect(cleared.dates == ListDateRange())
        #expect(cleared.sort == nil)
    }

    @Test func theReturnsFeedAsksForTheAppliedFilterAndClearsIt() async throws {
        let server = Server()
        let feed = GoodsInReturnsFeed(warehouseID: 1, state: .returning, service: server.service)
        let range = ListDateRange(from: try day("2026-11-01"), to: try day("2026-11-15"))

        await feed.apply(ListFilter(dates: range, sort: .oldestReceived))

        let items = try #require(server.lastReturnQuery).queryItems
        #expect(items.contains(URLQueryItem(name: "state", value: "returning")))
        #expect(items.contains(URLQueryItem(name: "from", value: "2026-11-01")))
        #expect(items.contains(URLQueryItem(name: "to", value: "2026-11-15")))
        #expect(items.contains(URLQueryItem(name: "sort", value: "created_at")))
        #expect(feed.list.rows.map(\.reference) == ["LIVE-R"])
        #expect(feed.total(for: feed.filter) == 1)
        #expect(feed.total(for: ListFilter()) == nil)

        await feed.clearFilters()
        let cleared = try #require(server.lastReturnQuery).queryItems.map(\.name)
        #expect(!cleared.contains("from"))
        #expect(!cleared.contains("sort"))
        #expect(!feed.filter.isFiltered)
    }

    @Test func savedDeliveriesAreFilteredByStateSourceAndSearchOnThePhone() throws {
        let rows = try savedDeliveries()
        let now = try #require(ListDates.instant("2026-11-23T12:00:00Z"))
        func ids(_ filter: GoodsInDeliveryFilter) -> [Int] {
            GoodsInListOffline.deliveries(rows, filter: filter, now: now, in: utc).map(\.id)
        }

        #expect(ids(GoodsInDeliveryFilter()) == [1, 2, 3])
        #expect(ids(GoodsInDeliveryFilter(state: .placed)) == [5])
        #expect(ids(GoodsInDeliveryFilter(state: .dispatched)) == [1])
        #expect(ids(GoodsInDeliveryFilter(source: .suppliers)) == [2, 3])
        #expect(ids(GoodsInDeliveryFilter(search: "beeswax")) == [1])
        #expect(ids(GoodsInDeliveryFilter(search: "awx")) == [2])
        #expect(ids(GoodsInDeliveryFilter(search: " sd-2 ")) == [3])
        #expect(ids(GoodsInDeliveryFilter(search: "zinc")) == [])
        #expect(ids(GoodsInDeliveryFilter(dispatched: .today)) == [])
        #expect(ids(GoodsInDeliveryFilter(dispatched: .week)) == [1])
        #expect(ids(GoodsInDeliveryFilter(dispatched: .older)) == [2, 3])
    }

    @Test func savedDeliveriesKeepTheDateRangeOnTheDispatchedDay() throws {
        let rows = try savedDeliveries()
        func ids(_ range: ListDateRange) -> [Int] {
            GoodsInListOffline.deliveries(rows, filter: GoodsInDeliveryFilter(dates: range), in: utc).map(\.id)
        }
        let fifth = try day("2026-11-05", in: utc)
        let fifteenth = try day("2026-11-15", in: utc)
        let twentieth = try day("2026-11-20", in: utc)

        #expect(ids(ListDateRange(from: fifth, to: fifteenth)) == [2])
        #expect(ids(ListDateRange(from: fifteenth, to: nil)) == [1])
        #expect(ids(ListDateRange(from: nil, to: fifth)) == [3])
        #expect(ids(ListDateRange(from: twentieth, to: twentieth)) == [1])
    }

    @Test func savedDeliveriesAreSortedLikeTheServerWithEmptyValuesLast() throws {
        let rows = try savedDeliveries()
        func ids(_ sort: GoodsInDeliverySort?) -> [Int] {
            GoodsInListOffline.deliveries(rows, filter: GoodsInDeliveryFilter(sort: sort), in: utc).map(\.id)
        }

        #expect(ids(nil) == [1, 2, 3])
        #expect(ids(.dispatchedDate) == [1, 2, 3])
        #expect(ids(.oldest) == [3, 2, 1])
        #expect(ids(.reference) == [2, 3, 1])
        #expect(ids(.supplier) == [3, 1, 2])
        #expect(ids(.expectedDate) == [2, 1, 3])
        #expect(ids(.items) == [1, 3, 2])
    }

    @Test func savedReturnsAreFilteredSearchedAndSortedOnThePhone() throws {
        let rows = try savedReturns()
        func ids(_ state: GoodsInReturnStateFilter = .all, search: String = "", filter: ListFilter<GoodsInReturnSort> = ListFilter()) -> [Int] {
            GoodsInListOffline.returns(rows, state: state, search: search, filter: filter, in: utc).map(\.id)
        }
        let midNovember = ListDateRange(from: try day("2026-11-05", in: utc), to: try day("2026-11-15", in: utc))

        #expect(ids() == [10, 11, 12])
        #expect(ids(.toProcess) == [10, 11])
        #expect(ids(.done) == [12])
        #expect(ids(search: "gb-2") == [12])
        #expect(ids(search: "zed") == [], "Aiku searches returns by reference only")
        #expect(ids(filter: ListFilter(dates: midNovember)) == [11])
        #expect(ids(filter: ListFilter(sort: .oldestReceived)) == [12, 11, 10])
        #expect(ids(filter: ListFilter(sort: .reference)) == [11, 12, 10])
        #expect(ids(filter: ListFilter(sort: .customer)) == [11, 10, 12])
    }

    @Test func offlineTheDeliveriesListShowsTheSavedRowsAndFiltersThemOnThePhone() async throws {
        let server = Server(online: false)
        let feed = GoodsInDeliveriesFeed(warehouseID: 1, service: server.service, savedLists: listsHolding(.stockDeliveries, page: savedDeliveriesPage), userID: { 7 })

        await feed.start()

        #expect(feed.savedAt != nil)
        #expect(feed.list.rows.map(\.id) == [1, 2, 3])
        #expect(feed.list.firstPageProblem == nil)
        #expect(!feed.list.canLoadMore)
        #expect(feed.total(for: feed.filter) == 3)
        #expect(feed.total(for: GoodsInDeliveryFilter(state: .placed)) == 1)

        var filter = feed.filter
        filter.source = .suppliers
        filter.sort = .supplier
        await feed.apply(filter)
        #expect(feed.list.rows.map(\.id) == [3, 2])

        server.isOnline = true
        await feed.refresh()
        #expect(feed.savedAt == nil)
        #expect(feed.list.rows.map(\.reference) == ["LIVE-1"])
    }

    @Test func offlineTheReturnsListShowsTheSavedRowsForItsState() async throws {
        let server = Server(online: false)
        let feed = GoodsInReturnsFeed(warehouseID: 1, state: .toProcess, service: server.service, savedLists: listsHolding(.returns, page: savedReturnsPage), userID: { 7 })

        await feed.start()

        #expect(feed.savedAt != nil)
        #expect(feed.list.rows.map(\.id) == [10, 11])
        #expect(feed.total(for: ListFilter(sort: .reference)) == 2)

        await feed.apply(ListFilter(sort: .reference))
        #expect(feed.list.rows.map(\.id) == [11, 10])

        await feed.select(.done)
        #expect(feed.list.rows.map(\.id) == [12])
    }

    @Test func offlineWithoutSavedRowsTheListSaysThereIsNoConnection() async {
        let server = Server(online: false)
        let deliveries = GoodsInDeliveriesFeed(warehouseID: 1, service: server.service, savedLists: SavedLists(root: OfflineTestData.temporaryDirectory()), userID: { 7 })
        let returns = GoodsInReturnsFeed(warehouseID: 1, service: server.service, savedLists: listsHolding(.returns, page: savedReturnsPage), userID: { nil })

        await deliveries.start()
        await returns.start()

        #expect(deliveries.savedAt == nil)
        #expect(deliveries.list.firstPageProblem == GoodsInText.offline)
        #expect(returns.savedAt == nil)
        #expect(returns.list.firstPageProblem == GoodsInText.offline)
    }

    @Test func deliveryRowsShowTheMostRelevantDateWithACaptionOnlyWhenItAddsToTheStatus() throws {
        let rows = try savedDeliveries().map { GoodsInListRow.delivery($0, in: utc) }

        #expect(rows[0] == GoodsInListRow(
            reference: "SD-3",
            date: "20 Nov 2026",
            dateCaption: nil,
            party: "Beeswax Co",
            badges: [Chip("Dispatched", GoodsInText.deliveryTone("dispatched"))],
            details: ["AG-7", "30 items"]
        ))
        #expect(rows[1].date == "18 Nov 2026")
        #expect(rows[1].dateCaption == nil)
        #expect(rows[1].details == ["AWX-9", "1 of 3 checked"])
        #expect(rows[2].date == "3 Nov 2026")
        #expect(rows[2].dateCaption == RecordDateCaption.received)
        #expect(rows[2].details == ["5 of 12 placed"])
        #expect(rows[3].date == "1 Dec 2026")
        #expect(rows[3].dateCaption == RecordDateCaption.expected)
        #expect(rows[3].party == "Agent Asia")
        #expect(rows[4].dateCaption == RecordDateCaption.received)
    }

    @Test func returnRowsShowTheReceivedDateCustomerAndProgress() throws {
        let rows = try savedReturns().map { GoodsInListRow.goodsReturn($0, in: utc) }

        #expect(rows[0] == GoodsInListRow(
            reference: "GB-3",
            date: "20 Nov 2026",
            dateCaption: nil,
            party: "Zed Crafts",
            badges: [Chip("Received", GoodsInText.returnTone("received"))],
            details: ["2 items", "DN-55"]
        ))
        #expect(rows[1].dateCaption == RecordDateCaption.received)
        #expect(rows[1].details == ["Cancellation", "1 of 4 handled"])
        #expect(rows[2].party == nil)
        #expect(rows[2].date == "1 Nov 2026")
    }

    @Test func theActiveFiltersLineNamesEveryFilterAndTheSort() throws {
        let range = ListDateRange(from: try day("2026-11-10"), to: try day("2026-11-12"))
        let rangeText = try #require(ListDates.rangeText(range))
        let filter = GoodsInDeliveryFilter(source: .suppliers, dispatched: .today, sort: .supplier, dates: range)

        #expect(filter.summary() == [
            L10n.text("Suppliers"),
            "\(L10n.text("Dispatched")): \(L10n.text("Today"))",
            "\(L10n.text(GoodsInDeliveryFilter.dateLabel)): \(rangeText)",
            "Sorted by \(L10n.text("Supplier").lowercased())",
        ])
        #expect(GoodsInDeliveryFilter().summary().isEmpty)

        let returns = ListFilter<GoodsInReturnSort>(dates: range, sort: .oldestReceived)
        #expect(returns.summary(sorts: GoodsInReturnSort.options, dateLabel: GoodsInReturnSort.dateLabel) == [
            "\(L10n.text(GoodsInReturnSort.dateLabel)): \(rangeText)",
            "Sorted by \(L10n.text("Oldest received").lowercased())",
        ])
    }
}
