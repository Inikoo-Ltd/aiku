import Foundation
import Testing
@testable import Aiku

@MainActor
struct FulfilmentListFilterTests {
    private final class Transport: HTTPTransport, @unchecked Sendable {
        private let lock = NSLock()
        private let body: String
        private var recorded: [URLRequest] = []
        private var offline = false

        init(body: String) {
            self.body = body
        }

        var requests: [URLRequest] {
            lock.withLock { recorded }
        }

        func setOffline(_ isOffline: Bool) {
            lock.withLock { offline = isOffline }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            let isOffline = lock.withLock {
                recorded.append(request)
                return offline
            }
            if isOffline {
                throw URLError(.notConnectedToInternet)
            }
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
        }
    }

    private static let utc = TimeZone(identifier: "UTC")!

    private static let returnRows = [
        #"{"id":1,"reference":"AW-R1","customer_reference":null,"type":"pallet","type_group":"pallets","type_label":"Pallets","platform":null,"state":"confirmed","state_label":"Confirmed","customer":{"id":7,"name":"Acme"},"number_pallets":2,"number_items":0,"date":"2026-11-17T08:00:00+00:00","picker":null,"is_collection":false}"#,
        #"{"id":2,"reference":"AW-R2","customer_reference":"PO-77","type":"stored_item","type_group":"items","type_label":"Stored items","platform":{"code":"shopify","name":"Shopify"},"state":"picking","state_label":"Picking","customer":{"id":8,"name":"Bolt"},"number_pallets":1,"number_items":4,"date":"2026-11-19T08:00:00+00:00","picker":null,"is_collection":false}"#,
        #"{"id":3,"reference":"AW-R3","customer_reference":null,"type":"pallet","type_group":"pallets","type_label":"Pallets","platform":null,"state":"dispatched","state_label":"Dispatched","customer":{"id":7,"name":"Acme"},"number_pallets":1,"number_items":0,"date":"2026-11-20T08:00:00+00:00","picker":null,"is_collection":false}"#,
        #"{"id":4,"reference":"AW-R0","customer_reference":null,"type":"pallet","type_group":"pallets","type_label":"Pallets","platform":null,"state":"picked","state_label":"Picked","customer":{"id":9,"name":"Zed"},"number_pallets":1,"number_items":0,"date":null,"picker":null,"is_collection":false}"#,
    ]

    private static let deliveryCounts = #"{"pallets":2,"boxes":1,"oversizes":0,"total":3,"booked_in":0,"not_received":0}"#

    private static let deliveryRows = [
        #"{"id":10,"reference":"D-10","customer_reference":"PO-9","state":"confirmed","state_label":"Confirmed","customer":{"id":4,"name":"Acme"},"estimated_delivery_date":"2026-11-25","received_at":null,"booked_in_at":null,"counts":"# + deliveryCounts + "}",
        #"{"id":11,"reference":"D-11","customer_reference":null,"state":"received","state_label":"Received","customer":{"id":5,"name":"Bolt"},"estimated_delivery_date":"2026-11-20","received_at":"2026-11-20T09:00:00+00:00","booked_in_at":null,"counts":"# + deliveryCounts + "}",
        #"{"id":12,"reference":"D-12","customer_reference":null,"state":"booking_in","state_label":"Booking in","customer":{"id":4,"name":"Acme"},"estimated_delivery_date":"2026-11-20","received_at":"2026-11-21T09:00:00+00:00","booked_in_at":null,"counts":"# + deliveryCounts + "}",
        #"{"id":13,"reference":"D-13","customer_reference":null,"state":"booked_in","state_label":"Booked in","customer":{"id":6,"name":"Cog"},"estimated_delivery_date":"2026-11-18","received_at":"2026-11-18T09:00:00+00:00","booked_in_at":"2026-11-19T10:00:00+00:00","counts":"# + deliveryCounts + "}",
        #"{"id":14,"reference":"D-14","customer_reference":null,"state":"booked_in","state_label":"Booked in","customer":{"id":6,"name":"Cog"},"estimated_delivery_date":null,"received_at":"2026-11-21T09:00:00+00:00","booked_in_at":"2026-11-22T10:00:00+00:00","counts":"# + deliveryCounts + "}",
    ]

    private static let returnsPage = #"{"data":["# + returnRows[0] + #"],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":1},"counts":{"confirmed":1,"picking":0,"picked":0,"dispatched":0,"cancel":0,"active":1,"types":{"pallets":1,"items":0}}}"#

    private static let deliveriesPage = #"{"data":["# + deliveryRows[0] + #"],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":1},"counts":{"confirmed":1,"received":0,"booking_in":0,"booked_in":0,"not_received":0,"active":1}}"#

    private static func savedPage(_ rows: [String]) -> Data {
        Data((#"{"data":["# + rows.joined(separator: ",") + #"],"meta":{}}"#).utf8)
    }

    private static func decoded<Row: Decodable>(_ type: Row.Type, _ rows: [String]) throws -> [Row] {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode([Row].self, from: Data("[\(rows.joined(separator: ","))]".utf8))
    }

    private func client(_ transport: Transport) -> APIClient {
        APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
    }

    private func lastQuery(_ transport: Transport) -> [URLQueryItem] {
        transport.requests.last?.url.flatMap { URLComponents(url: $0, resolvingAgainstBaseURL: false)?.queryItems } ?? []
    }

    private func savedLists(_ kind: SavedListKind, rows: [String]) -> SavedLists {
        let lists = SavedLists(root: OfflineTestData.temporaryDirectory())
        lists.remember(userID: 7, warehouseID: 1, kind: kind, page: Self.savedPage(rows))
        return lists
    }

    @Test("Fulfilment returns send the chosen sort with the keys Aiku accepts and never a date range")
    func returnsSendTheSort() async {
        let transport = Transport(body: Self.returnsPage)
        let api = client(transport)
        let feed = PalletReturnsFeed(savedLists: SavedLists(root: OfflineTestData.temporaryDirectory()), currentUserID: { 7 })

        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(!lastQuery(transport).contains { $0.name == "sort" })

        let expected: [(PalletReturnSort, String)] = [(.oldest, "date"), (.reference, "reference"), (.customer, "customer"), (.dispatched, "-dispatched_at")]
        for (sort, key) in expected {
            feed.listFilter = ListFilter(sort: sort)
            await feed.reload(warehouseID: 1, apiClient: api)
            let items = lastQuery(transport)
            #expect(items.contains(URLQueryItem(name: "sort", value: key)))
            #expect(items.contains(URLQueryItem(name: "state", value: "active")))
            #expect(!items.contains { $0.name == "from" || $0.name == "to" })
        }
        #expect(feed.listFilter.activeCount == 1)
        #expect(feed.previewTotal(warehouseID: 1) == 1)
        #expect(PalletReturnSort.options.first?.sort == nil)
        #expect(PalletReturnSort.options.map(\.label) == ["Newest first", "Oldest first", "Reference", "Customer", "Dispatched"])
        #expect(MobilePalletReturnsQuery(search: " ab ", sort: "customer").queryItems.contains(URLQueryItem(name: "sort", value: "customer")))
    }

    @Test("Fulfilment deliveries send the booked in range as days and the chosen sort")
    func deliveriesSendRangeAndSort() async throws {
        let transport = Transport(body: Self.deliveriesPage)
        let api = client(transport)
        let feed = PalletDeliveriesFeed(savedLists: SavedLists(root: OfflineTestData.temporaryDirectory()), currentUserID: { 7 })

        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(!lastQuery(transport).contains { ["sort", "from", "to"].contains($0.name) })

        let from = try #require(ListDates.day("2026-11-18"))
        let to = try #require(ListDates.day("2026-11-20"))
        feed.listFilter = ListFilter(dates: ListDateRange(from: from, to: to), sort: .bookedIn)
        await feed.reload(warehouseID: 1, apiClient: api)

        let items = lastQuery(transport)
        #expect(items.contains(URLQueryItem(name: "from", value: "2026-11-18")))
        #expect(items.contains(URLQueryItem(name: "to", value: "2026-11-20")))
        #expect(items.contains(URLQueryItem(name: "sort", value: "-booked_in_at")))
        #expect(feed.listFilter.activeCount == 2)
        #expect(feed.previewTotal(for: feed.listFilter, warehouseID: 1) == 1)
        #expect(feed.previewTotal(for: ListFilter(), warehouseID: 1) == nil)

        feed.listFilter = ListFilter(sort: .reference)
        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(lastQuery(transport).contains(URLQueryItem(name: "sort", value: "reference")))
        #expect(PalletDeliverySort.options.map(\.label) == ["Expected", "Reference", "Booked in"])

        feed.reset()
        #expect(feed.listFilter == ListFilter<PalletDeliverySort>())
        #expect(feed.total == nil)
    }

    @Test("Saved returns are filtered by tab, type and search and sorted like Aiku on the phone")
    func savedReturnsFilteredOnThePhone() throws {
        let rows = try Self.decoded(MobilePalletReturnRow.self, Self.returnRows)
        func ids(_ state: PalletReturnStateFilter = .active, type: PalletReturnTypeFilter = .all, search: String = "", sort: PalletReturnSort? = nil) -> [Int] {
            PalletReturnsOffline.rows(rows, state: state, type: type, search: search, sort: sort).map(\.id)
        }

        #expect(ids() == [2, 1, 4])
        #expect(ids(.dispatched) == [3])
        #expect(ids(.picked) == [4])
        #expect(ids(type: .items) == [2])
        #expect(ids(type: .pallets) == [1, 4])
        #expect(ids(search: "po-77") == [2])
        #expect(ids(search: "acme") == [1])
        #expect(ids(search: "aw-r") == [2, 1, 4])
        #expect(ids(sort: .oldest) == [1, 2, 4])
        #expect(ids(sort: .reference) == [4, 1, 2])
        #expect(ids(sort: .customer) == [1, 2, 4])
        #expect(ids(sort: .dispatched) == [2, 1, 4])
    }

    @Test("Offline, fulfilment returns show the saved rows with the tab, search and sort applied and mark the online only sort")
    func offlineReturnsShowSavedRows() async {
        let transport = Transport(body: Self.returnsPage)
        transport.setOffline(true)
        let api = client(transport)
        let feed = PalletReturnsFeed(savedLists: savedLists(.palletReturns, rows: Self.returnRows), currentUserID: { 7 })

        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(feed.rows.map(\.id) == [2, 1, 4])
        #expect(feed.isShowingSavedRows)
        #expect(feed.savedAt != nil)
        #expect(feed.loadError == nil)
        #expect(!feed.hasMorePages)
        #expect(feed.loadedRequest == feed.request(warehouseID: 1))
        #expect(feed.previewTotal(warehouseID: 1) == 3)

        feed.search = "PO-77"
        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(feed.rows.map(\.id) == [2])

        feed.search = ""
        feed.listFilter = ListFilter(sort: .dispatched)
        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(feed.rows.map(\.id) == [2, 1, 4])
        #expect(feed.onlineOnlySorts == PalletReturnSort.onlineOnly)
        #expect(PalletReturnSort.onlineOnly.contains(.dispatched))
        #expect(feed.listFilter.summary(sorts: PalletReturnSort.options, onlineOnlySorts: feed.onlineOnlySorts).last?.hasSuffix("(online only)") == true)

        transport.setOffline(false)
        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(!feed.isShowingSavedRows)
        #expect(feed.savedAt == nil)
        #expect(feed.onlineOnlySorts.isEmpty)
        #expect(feed.rows.map(\.id) == [1])
    }

    @Test("Offline without saved rows, fulfilment returns say a connection is needed")
    func offlineReturnsWithoutSavedRows() async {
        let transport = Transport(body: Self.returnsPage)
        transport.setOffline(true)
        let feed = PalletReturnsFeed(savedLists: SavedLists(root: OfflineTestData.temporaryDirectory()), currentUserID: { 7 })

        await feed.reload(warehouseID: 1, apiClient: client(transport))

        #expect(feed.rows.isEmpty)
        #expect(!feed.isShowingSavedRows)
        #expect(feed.loadError == PalletDeliveryText.message(for: APIError.offline))
    }

    @Test("Saved deliveries are filtered by tab, search and booked in day and sorted like Aiku on the phone")
    func savedDeliveriesFilteredOnThePhone() throws {
        let rows = try Self.decoded(MobilePalletDeliveryRow.self, Self.deliveryRows)
        func ids(_ state: PalletDeliveryFilter = .active, search: String = "", dates: ListDateRange = ListDateRange(), sort: PalletDeliverySort? = nil) -> [Int] {
            PalletDeliveriesOffline.rows(rows, state: state, search: search, dates: dates, sort: sort, in: Self.utc).map(\.id)
        }
        let day22 = try #require(ListDates.day("2026-11-22", in: Self.utc))
        let day19 = try #require(ListDates.day("2026-11-19", in: Self.utc))

        #expect(ids() == [12, 11, 10])
        #expect(ids(.confirmed) == [10])
        #expect(ids(.bookedIn) == [13, 14])
        #expect(ids(search: "acme") == [12, 10])
        #expect(ids(search: "po-9") == [10])
        #expect(ids(search: "d-11") == [11])
        #expect(ids(sort: .reference) == [10, 11, 12])
        #expect(ids(.bookedIn, sort: .bookedIn) == [14, 13])
        #expect(ids(.bookedIn, dates: ListDateRange(from: day22, to: day22)) == [14])
        #expect(ids(.bookedIn, dates: ListDateRange(from: nil, to: day19)) == [13])
        #expect(ids(dates: ListDateRange(from: day19, to: nil)).isEmpty)
    }

    @Test("Offline, fulfilment deliveries show the saved rows and count the panel's booked in range on the phone")
    func offlineDeliveriesShowSavedRows() async throws {
        let transport = Transport(body: Self.deliveriesPage)
        transport.setOffline(true)
        let api = client(transport)
        let feed = PalletDeliveriesFeed(savedLists: savedLists(.palletDeliveries, rows: Self.deliveryRows), currentUserID: { 7 })
        feed.filter = .bookedIn

        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(feed.rows.map(\.id) == [13, 14])
        #expect(feed.isShowingSavedRows)
        #expect(feed.savedAt != nil)
        #expect(feed.loadError == nil)
        #expect(!feed.hasMorePages)

        let november = ListDateRange(from: try #require(ListDates.day("2026-11-01")), to: try #require(ListDates.day("2026-11-30")))
        let december = ListDateRange(from: try #require(ListDates.day("2026-12-01")), to: nil)
        #expect(feed.previewTotal(for: ListFilter(dates: november), warehouseID: 1) == 2)
        #expect(feed.previewTotal(for: ListFilter(dates: december), warehouseID: 1) == 0)

        feed.listFilter = ListFilter(sort: .bookedIn)
        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(feed.rows.map(\.id) == [14, 13])

        feed.listFilter = ListFilter(dates: december)
        await feed.reload(warehouseID: 1, apiClient: api)
        #expect(feed.rows.isEmpty)
        #expect(feed.isShowingSavedRows)

        feed.reset()
        #expect(!feed.isShowingSavedRows)
    }

    @Test("Delivery rows show the date of their stage with a caption only when the status doesn't already say it")
    func deliveryRowMoments() throws {
        let rows = try Self.decoded(MobilePalletDeliveryRow.self, Self.deliveryRows)
        func moment(_ id: Int) throws -> (date: String?, caption: String?) {
            PalletDeliveryText.rowMoment(try #require(rows.first { $0.id == id }), in: Self.utc)
        }

        #expect(try moment(10).date == "25 Nov 2026")
        #expect(try moment(10).caption == "Expected")
        #expect(try moment(11).date == "20 Nov 2026")
        #expect(try moment(11).caption == nil)
        #expect(try moment(12).date == "21 Nov 2026")
        #expect(try moment(12).caption == "Received")
        #expect(try moment(13).date == "19 Nov 2026")
        #expect(try moment(13).caption == nil)

        let undated = PalletDeliveryText.rowMoment(MobilePalletDeliveryRow(id: 1, reference: "D-1", state: "confirmed", stateLabel: "Confirmed"), in: Self.utc)
        #expect(undated.date == nil)
        #expect(undated.caption == nil)
        let unlabelled = PalletDeliveryText.rowMoment(MobilePalletDeliveryRow(id: 2, reference: "D-2", state: "booked_in", bookedInAt: "2026-11-19T10:00:00+00:00"), in: Self.utc)
        #expect(unlabelled.caption == "Booked in")

        let confirmed = try #require(rows.first { $0.id == 10 })
        #expect(PalletDeliveryText.rowDetails(confirmed) == ["“PO-9”", "2 pallets, 1 box"])
        #expect(PalletDeliveryText.rowChip(confirmed) == Chip("Confirmed", .neutral))
        #expect(PalletDeliveryText.rowChip(MobilePalletDeliveryRow(id: 3, reference: "D-3", state: "booked_in")) == Chip("booked_in", .success))
    }

    @Test("Return rows show the listed date with its year, the state chip and the type, platform and size")
    func returnRows() throws {
        let rows = try Self.decoded(MobilePalletReturnRow.self, Self.returnRows)
        let pallets = try #require(rows.first { $0.id == 1 })
        let items = try #require(rows.first { $0.id == 2 })
        let undated = try #require(rows.first { $0.id == 4 })

        #expect(PalletReturnText.rowDate(pallets, in: Self.utc) == "17 Nov 2026")
        #expect(PalletReturnText.rowDate(undated, in: Self.utc) == nil)
        #expect(PalletReturnText.rowDetails(pallets) == ["Pallets", "2 pallets"])
        #expect(PalletReturnText.rowDetails(items) == ["Stored items", "from Shopify", "4 item lines"])
        #expect(PalletReturnText.rowChip(items) == Chip("Picking", .info))
        #expect(PalletReturnText.rowChip(MobilePalletReturnRow(id: 9, reference: "R-9", state: "cancel")) == Chip("cancel", .danger))
    }
}
