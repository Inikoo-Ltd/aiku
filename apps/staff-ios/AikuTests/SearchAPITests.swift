import Foundation
import Testing
@testable import Aiku

private struct SearchCall: Equatable, Sendable {
    let query: String
    let type: MobileSearchType?
    let page: Int
}

private actor SearchCalls {
    private(set) var calls: [SearchCall] = []

    func record(_ call: SearchCall) {
        calls.append(call)
    }

    func count(query: String) -> Int {
        calls.filter { $0.query == query }.count
    }
}

private actor SearchGate {
    private var isOpen = false

    func wait() async {
        while !isOpen {
            await Task.yield()
        }
    }

    func open() {
        isOpen = true
    }
}

private final class SearchTransport: HTTPTransport, @unchecked Sendable {
    let body: String
    private let lock = NSLock()
    private var recorded: [URLRequest] = []

    init(body: String) {
        self.body = body
    }

    var requests: [URLRequest] {
        lock.withLock { recorded }
    }

    func data(for request: URLRequest) async throws -> (Data, URLResponse) {
        lock.withLock { recorded.append(request) }
        return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
    }
}

private enum SearchFixtures {
    static let everyType = #"""
    {"data":{"query":"msd","types":["skos","sko_families","locations","delivery_notes","stock_deliveries","returns","pallets","pallet_deliveries","pallet_returns","orders",null],"exact":null,"groups":[
      {"type":"skos","label":"SKOs","has_more":false,"rows":[
        {"type":"sko","id":11,"code":"MSDSKO","title":"MSDSKO","subtitle":"Lavender oil 10ml","state":"active","state_label":"Active","quantity":12.0,"image_url":"https://media.aiku.test/sko.png","type_group":null},
        {"type":"sko","code":"NO-ID"},
        null
      ]},
      {"type":"sko_families","label":"SKO Families","has_more":false,"rows":[{"type":"sko_family","id":3,"code":"MSDFAM","title":"MSDFAM","subtitle":"Lavender range · 14 SKOs","state":"active","state_label":"Active","quantity":null,"image_url":null,"type_group":null}]},
      {"type":"locations","label":"Locations","has_more":true,"rows":[{"type":"location","id":21,"code":"MSDLOC","title":"MSDLOC","subtitle":"A · 6 SKOs","state":"operational","state_label":"Operational","quantity":null,"image_url":null,"type_group":null}]},
      {"type":"delivery_notes","label":"Delivery notes","has_more":false,"rows":[{"type":"delivery_note","id":31,"code":"MSDDN","title":"MSDDN","subtitle":"Shop · 6 items","state":"handling","state_label":"Picking","quantity":null,"image_url":null,"type_group":null}]},
      {"type":"stock_deliveries","label":"Stock deliveries","has_more":false,"rows":[{"type":"stock_delivery","id":41,"code":"MSDSD","title":"MSDSD","subtitle":"Supplier Ltd","state":"dispatched","state_label":"Dispatched","quantity":null,"image_url":null,"type_group":null}]},
      {"type":"returns","label":"Returns","has_more":false,"rows":[{"type":"return","id":51,"code":"R-MSD","title":"R-MSD","subtitle":"Customer","state":"received","state_label":"Received","quantity":null,"image_url":null,"type_group":null}]},
      {"type":"pallets","label":"Pallets","has_more":false,"rows":[{"type":"pallet","id":61,"code":"P-0061","title":"P-0061","subtitle":"C-MSD · A-01","state":"storing","state_label":"Storing","quantity":null,"image_url":null,"type_group":null}]},
      {"type":"pallet_deliveries","label":"Pallet deliveries","has_more":false,"rows":[{"type":"pallet_delivery","id":71,"code":"PD-71","title":"PD-71","subtitle":"Customer MSD","state":"confirmed","state_label":"Confirmed","quantity":null,"image_url":null,"type_group":null}]},
      {"type":"pallet_returns","label":"Pallet returns","has_more":false,"rows":[{"type":"pallet_return","id":81,"code":"PR-81","title":"PR-81","subtitle":"Customer MSD","state":"picking","state_label":"Picking","quantity":null,"image_url":null,"type_group":"pallets"}]},
      {"type":"orders","label":"Orders","has_more":false,"rows":[{"type":"order","id":1,"code":"O-1"}]}
    ]}}
    """#

    static let tooShort = #"{"data":{"query":"a","types":["locations","delivery_notes"],"exact":null,"groups":[]}}"#

    static func decode(_ json: String) throws -> MobileSearchResults {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(DataEnvelope<MobileSearchResults>.self, from: Data(json.utf8)).value
    }

    static func row(_ id: Int, _ type: MobileSearchType = .locations) -> MobileSearchRow {
        MobileSearchRow(type: type, id: id, code: "C-\(id)", title: "C-\(id)")
    }

    static func group(_ type: MobileSearchType, _ ids: ClosedRange<Int>, hasMore: Bool) -> MobileSearchGroup {
        MobileSearchGroup(type: type, label: ShellSearchFormat.title(type), hasMore: hasMore, rows: ids.map { row($0, type) })
    }

    static func defaults() -> UserDefaults {
        UserDefaults(suiteName: "SearchAPITests.\(UUID().uuidString)")!
    }
}

@MainActor
struct SearchAPITests {
    private func client(body: String) -> (SearchTransport, APIClient) {
        let transport = SearchTransport(body: body)
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
        return (transport, client)
    }

    @Test func searchAllSendsTheQueryWithoutAPageAndDecodesEveryType() async throws {
        let (transport, client) = client(body: SearchFixtures.everyType)

        let results = try await client.fetchSearch(warehouseID: 1, query: "msd", type: nil, page: 1)

        let request = try #require(transport.requests.first)
        let items = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)?.queryItems ?? []
        #expect(request.url?.path == "/app/api/mobile/warehouses/1/search")
        #expect(items == [URLQueryItem(name: "q", value: "msd"), URLQueryItem(name: "type", value: "all")])
        #expect(request.value(forHTTPHeaderField: "X-Aiku-App-Key") == "key")
        #expect(results.types == [.skos, .skoFamilies, .locations, .deliveryNotes, .stockDeliveries, .returns, .pallets, .palletDeliveries, .palletReturns])
        #expect(results.groups.map(\.type) == results.types)
        #expect(results.exact == nil)

        let sko = try #require(results.groups.first?.rows.first)
        #expect(results.groups.first?.rows.count == 1)
        #expect(sko == MobileSearchRow(type: .skos, id: 11, code: "MSDSKO", title: "MSDSKO", subtitle: "Lavender oil 10ml", state: "active", stateLabel: "Active", quantity: 12, imageUrl: "https://media.aiku.test/sko.png"))
        #expect(results.groups[2].hasMore)
        #expect(results.groups[2].label == "Locations")
        #expect(results.groups[8].rows.first?.typeGroup == "pallets")
    }

    @Test func searchOneTypeSendsItsPage() async throws {
        let (transport, client) = client(body: SearchFixtures.tooShort)

        let results = try await client.fetchSearch(warehouseID: 4, query: "a", type: .locations, page: 2)

        let items = transport.requests.first.flatMap { URLComponents(url: $0.url!, resolvingAgainstBaseURL: false)?.queryItems } ?? []
        #expect(items == [URLQueryItem(name: "q", value: "a"), URLQueryItem(name: "type", value: "locations"), URLQueryItem(name: "page", value: "2")])
        #expect(results.types == [.locations, .deliveryNotes])
        #expect(results.groups.isEmpty)
    }

    @Test func chipsOfferAllThenOnlyTheTypesTheServerSends() async {
        let service = SearchService { _, _, _, _ in try SearchFixtures.decode(SearchFixtures.tooShort) }
        let store = ShellSearchStore(service: service, recentSearches: RecentSearches(defaults: SearchFixtures.defaults()), debounce: .zero)

        #expect(store.scopes.isEmpty)
        store.start(warehouseID: 1, userID: 7)
        await store.typesTask?.value

        #expect(store.scopes == [nil, .locations, .deliveryNotes])
        #expect(store.scopes.map(ShellSearchFormat.scopeTitle) == ["All", "Locations", "Delivery notes"])
        #expect(store.scopes.map(ShellSearchFormat.scopeID) == ["all", "locations", "delivery_notes"])
        #expect(ShellSearchFormat.scope(id: "all") == nil)
        #expect(ShellSearchFormat.scope(id: "delivery_notes") == .deliveryNotes)
    }

    @Test func groupHeadingsKeepTheirCase() {
        let skos = MobileSearchGroup(type: .skos, label: "SKOs", hasMore: true, rows: (1...5).map { SearchFixtures.row($0, .skos) })
        let families = MobileSearchGroup(type: .skoFamilies, rows: [SearchFixtures.row(1, .skoFamilies)])

        #expect(ShellSearchFormat.heading(skos) == "SKOs · 5+")
        #expect(ShellSearchFormat.heading(families) == "SKO families · 1")
        #expect(ShellSearchFormat.title(.skos) == "SKOs")
    }

    @Test func skoQuantityCarriesItsLabel() {
        var sko = SearchFixtures.row(1, .skos)
        sko.quantity = 12
        var location = SearchFixtures.row(2, .locations)
        location.quantity = 12

        #expect(ShellSearchFormat.quantity(sko)?.text == "12 in locations")
        #expect(ShellSearchFormat.quantity(sko)?.number == "12")
        #expect(ShellSearchFormat.quantity(location) == nil)
        #expect(ShellSearchFormat.quantity(SearchFixtures.row(3, .skos)) == nil)
    }

    @Test func resultsOpenTheScreensScanUses() {
        #expect(ShellSearchFormat.link(SearchFixtures.row(11, .skos)) == .inventory(.skoID(id: 11)))
        #expect(ShellSearchFormat.link(SearchFixtures.row(21, .locations)) == .locations(.location(code: "C-21")))
        #expect(ShellSearchFormat.link(SearchFixtures.row(31, .deliveryNotes)) == .goodsOut(.deliveryNoteID(id: 31)))
        #expect(ShellSearchFormat.link(SearchFixtures.row(41, .stockDeliveries)) == .goodsIn(.stockDelivery(id: 41, reference: "C-41")))
        #expect(ShellSearchFormat.link(SearchFixtures.row(51, .returns)) == .goodsIn(.returnDetail(id: 51, reference: "C-51")))
        #expect(ShellSearchFormat.link(SearchFixtures.row(61, .pallets)) == .fulfilment(.pallet(id: 61, reference: "C-61")))
        #expect(ShellSearchFormat.link(SearchFixtures.row(71, .palletDeliveries)) == .fulfilment(.palletDelivery(id: 71, reference: "C-71")))
        #expect(ShellSearchFormat.link(SearchFixtures.row(81, .palletReturns)) == .fulfilment(.palletReturn(id: 81, reference: "C-81")))
    }

    @Test func stateBadgesUseTheAreaTones() {
        var note = SearchFixtures.row(1, .deliveryNotes)
        note.state = "handling_blocked"
        var pallet = SearchFixtures.row(2, .pallets)
        pallet.state = "storing"

        #expect(ShellSearchFormat.tone(note) == .warn)
        #expect(ShellSearchFormat.tone(pallet) == .success)
        #expect(!ShellSearchFormat.hasStateBadge(.skos))
        #expect(ShellSearchFormat.hasStateBadge(.deliveryNotes))
    }

    @Test func recentSearchesAreKeptPerUserAndWarehouse() {
        let recents = RecentSearches(defaults: SearchFixtures.defaults())

        recents.remember("A-12-03", userID: 7, warehouseID: 1)
        recents.remember(" ar24817 ", userID: 7, warehouseID: 1)
        recents.remember("a-12-03", userID: 7, warehouseID: 1)
        recents.remember("   ", userID: 7, warehouseID: 1)

        #expect(recents.read(userID: 7, warehouseID: 1) == ["a-12-03", "ar24817"])
        #expect(recents.read(userID: 7, warehouseID: 2).isEmpty)
        #expect(recents.read(userID: 8, warehouseID: 1).isEmpty)

        recents.remember("bath bomb", userID: 8, warehouseID: 1)
        #expect(recents.read(userID: 7, warehouseID: 1).isEmpty)
        #expect(recents.read(userID: 8, warehouseID: 1) == ["bath bomb"])

        for index in 1...10 {
            recents.remember("term \(index)", userID: 8, warehouseID: 1)
        }
        #expect(recents.read(userID: 8, warehouseID: 1).count == RecentSearches.limit)
        #expect(recents.read(userID: 8, warehouseID: 1).first == "term 10")

        recents.clear()
        #expect(recents.read(userID: 8, warehouseID: 1).isEmpty)
    }

    @Test func recentSearchesShowOnlyWhileTheQueryIsEmpty() async {
        let recents = RecentSearches(defaults: SearchFixtures.defaults())
        recents.remember("lav", userID: 7, warehouseID: 1)
        let service = SearchService { _, query, _, _ in
            MobileSearchResults(query: query, types: [.skos], groups: query.isEmpty ? [] : [SearchFixtures.group(.skos, 1...2, hasMore: false)])
        }
        let store = ShellSearchStore(service: service, recentSearches: recents, debounce: .zero)
        store.start(warehouseID: 1, userID: 7)
        await store.typesTask?.value

        #expect(store.shownRecent == ["lav"])

        store.updateQuery("l")
        #expect(store.isQueryTooShort)
        #expect(store.shownRecent.isEmpty)

        store.updateQuery("bath")
        await store.searchTask?.value
        #expect(store.hasResults)
        #expect(store.shownRecent.isEmpty)

        store.rememberSearch()
        store.updateQuery("")
        #expect(!store.hasResults)
        #expect(store.shownRecent == ["bath", "lav"])
    }

    @Test func showMoreSwitchesToTheTypeAndAppendsPages() async {
        let calls = SearchCalls()
        let service = SearchService { _, query, type, page in
            await calls.record(SearchCall(query: query, type: type, page: page))
            if query.isEmpty {
                return MobileSearchResults(types: [.locations])
            }
            switch (type, page) {
            case (.none, _):
                return MobileSearchResults(query: query, types: [.locations], exact: SearchFixtures.row(3), groups: [SearchFixtures.group(.locations, 1...5, hasMore: true)])
            case (.some(.locations), 1):
                return MobileSearchResults(query: query, types: [.locations], groups: [SearchFixtures.group(.locations, 1...20, hasMore: true)])
            case (.some(.locations), 2):
                return MobileSearchResults(query: query, types: [.locations], groups: [SearchFixtures.group(.locations, 20...22, hasMore: false)])
            default:
                return MobileSearchResults(query: query)
            }
        }
        let store = ShellSearchStore(service: service, recentSearches: RecentSearches(defaults: SearchFixtures.defaults()), debounce: .zero)
        store.start(warehouseID: 1, userID: 7)
        await store.typesTask?.value

        store.updateQuery("loc")
        await store.searchTask?.value
        #expect(store.exact?.id == 3)
        #expect(store.groups.first?.rows.map(\.id) == [1, 2, 4, 5])
        #expect(!store.canLoadMore)

        store.showMore(.locations)
        await store.searchTask?.value
        #expect(store.scope == .locations)
        #expect(store.exact == nil)
        #expect(store.groups.first?.rows.count == 20)
        #expect(store.canLoadMore)

        store.loadMore()
        await store.searchTask?.value
        #expect(store.groups.first?.rows.map(\.id) == Array(1...22))
        #expect(store.groups.first?.hasMore == false)
        #expect(store.page == 2)
        #expect(!store.canLoadMore)
        #expect(await calls.calls.suffix(3) == [
            SearchCall(query: "loc", type: nil, page: 1),
            SearchCall(query: "loc", type: .locations, page: 1),
            SearchCall(query: "loc", type: .locations, page: 2)
        ])
    }

    @Test func aStaleResponseNeverReplacesTheNewerOne() async {
        let calls = SearchCalls()
        let gate = SearchGate()
        let service = SearchService { _, query, type, page in
            await calls.record(SearchCall(query: query, type: type, page: page))
            if query == "ab" {
                await gate.wait()
                return MobileSearchResults(query: query, types: [.skos], groups: [SearchFixtures.group(.skos, 1...1, hasMore: false)])
            }
            return MobileSearchResults(query: query, types: [.skos], groups: query.isEmpty ? [] : [SearchFixtures.group(.skos, 2...2, hasMore: false)])
        }
        let store = ShellSearchStore(service: service, recentSearches: RecentSearches(defaults: SearchFixtures.defaults()), debounce: .zero)
        store.start(warehouseID: 1, userID: 7)
        await store.typesTask?.value

        store.updateQuery("ab")
        let stale = store.searchTask
        while await calls.count(query: "ab") == 0 {
            await Task.yield()
        }
        store.updateQuery("abc")
        await store.searchTask?.value
        await gate.open()
        await stale?.value

        #expect(store.loadedQuery == "abc")
        #expect(store.groups.first?.rows.map(\.id) == [2])
        #expect(!store.isSearching)
    }

    @Test func aFailedSearchShowsTheProblemAndRetries() async {
        let calls = SearchCalls()
        let service = SearchService { _, query, type, page in
            await calls.record(SearchCall(query: query, type: type, page: page))
            if query.isEmpty {
                return MobileSearchResults(types: [.skos])
            }
            if await calls.count(query: query) == 1 {
                throw APIError.offline
            }
            return MobileSearchResults(query: query, types: [.skos], groups: [SearchFixtures.group(.skos, 1...1, hasMore: false)])
        }
        let store = ShellSearchStore(service: service, recentSearches: RecentSearches(defaults: SearchFixtures.defaults()), debounce: .zero)
        store.start(warehouseID: 1, userID: 7)
        await store.typesTask?.value

        store.updateQuery("lav")
        await store.searchTask?.value
        #expect(store.problem == "Search needs internet. Check Wi-Fi and try again.")
        #expect(!store.isSearching)
        #expect(!store.hasResults)

        store.retry()
        await store.searchTask?.value
        #expect(store.problem == nil)
        #expect(store.groups.first?.rows.map(\.id) == [1])
    }

    @Test func nothingFoundIsEmptyAndAnExpiredSessionIsFlagged() async {
        let service = SearchService { _, query, _, _ in
            if query == "gone" {
                throw APIError.unauthorized
            }
            return MobileSearchResults(query: query, types: [.skos])
        }
        let store = ShellSearchStore(service: service, recentSearches: RecentSearches(defaults: SearchFixtures.defaults()), debounce: .zero)
        store.start(warehouseID: 1, userID: 7)
        await store.typesTask?.value

        store.updateQuery("zzz")
        #expect(store.isSearching)
        #expect(!store.isEmpty)
        await store.searchTask?.value
        #expect(store.isEmpty)

        store.updateQuery("gone")
        await store.searchTask?.value
        #expect(store.isSessionExpired)
    }

    @Test func highlightSplitsAroundEveryMatch() {
        let segments = ShellSearchFormat.highlight("Bath bomb – lavender 180g", query: " lav ")

        #expect(segments == [
            ShellHighlightSegment(text: "Bath bomb – ", isMatch: false),
            ShellHighlightSegment(text: "lav", isMatch: true),
            ShellHighlightSegment(text: "ender 180g", isMatch: false)
        ])
    }
}
