import Foundation
import Testing
@testable import Aiku

@MainActor
struct InventoryAPITests {
    private struct RoutedTransport: HTTPTransport {
        let responses: [String: String]

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            guard let body = responses[path] else {
                return (Data(), HTTPURLResponse(url: request.url!, statusCode: 404, httpVersion: nil, headerFields: nil)!)
            }
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
        }
    }

    private static let responses: [String: String] = [
        "mobile/warehouses/1/org-stock-families": #"{"data":[{"id":27,"state":"active","slug":"eo-aw","code":"EO","name":"Essential Oils","number_current_org_stocks":84,"number_out_of_stock_org_stocks":3,"currency_code":"GBP","stock_value":"17030.33","potential_sales":"0","on_the_way_po_value":"0","on_the_way_po_count":0,"stock_turn":2.5,"stock_cover":4.25},{"id":5822,"state":"active","slug":"a-aw","code":"A","name":"A","number_current_org_stocks":1,"currency_code":"GBP","stock_value":"806.52","stock_cover":null}],"meta":{"current_page":1,"last_page":18,"per_page":50,"total":882},"counts":{"in_process":42,"active":882,"discontinuing":103,"discontinued":1726}}"#,
        "mobile/warehouses/1/org-stock-families/27/skos": #"{"family":{"id":27,"code":"EO","name":"Essential Oils","state":"active","number_current_org_stocks":84,"stock_value":17030.33,"currency_code":"GBP","sko_status":[{"key":"active","label":"Active","count":84},{"key":"discontinuing","label":"Discontinuing","count":3},{"key":"discontinued","label":"Discontinued","count":12},{"key":"suspended","label":"Suspended","count":0}],"quantity_status":[{"key":"out","label":"Out of stock","count":2},{"key":"w1","label":"Doomed","count":1},{"key":"w2","label":"Critical","count":"4"},{"key":"w3","label":"Danger","count":0},{"key":"w4","label":"On watch","count":0},{"key":"ok","label":"Covered","count":70},{"key":"excess","label":"Excess","count":5},{"key":"dead","label":"Dead stock","count":2}]},"data":[{"id":133,"slug":"eo-01-aw","code":"EO-01","name":"Lavender Essential Oil","quantity_available":"1797.75","quantity_available_label":"1797 3/4","quantity_available_fraction":[1797,[3,4]],"quantity_in_locations":"1828.75","quantity_in_locations_label":"1828 3/4","stock_value":"1221.96","number_locations":null,"woc":-9.7,"stock_cover":1.3,"state":"active"},{"id":134,"slug":"eo-02-aw","code":"EO-02","name":"Rose Essential Oil","quantity_available":0.5,"quantity_available_label":"1/2","quantity_in_locations":0.5,"quantity_in_locations_label":"1/2","stock_value":"0","number_locations":1,"woc":null,"stock_cover":null,"state":"discontinuing"}],"meta":{"current_page":1,"last_page":2,"per_page":50,"total":84}}"#,
        "mobile/warehouses/1/org-stock-families/28/skos": #"{"family":{"id":28,"code":"EO","name":"Essential Oils","state":"active","number_current_org_stocks":84,"stock_value":17030.33,"currency_code":"GBP"},"data":[{"id":133,"slug":"eo-01-aw","code":"EO-01","name":"Lavender Essential Oil","quantity_available":"1797","quantity_in_locations":"1828","stock_value":"1221.96","number_locations":null,"woc":-9.7,"stock_cover":1.3,"state":"active"}],"meta":{"current_page":1,"last_page":2,"per_page":50,"total":84}}"#,
    ]

    private func client() -> APIClient {
        APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: RoutedTransport(responses: Self.responses),
            tokenProvider: { "1|tok" }
        )
    }

    @Test func skoListSendsStockStatusAndLocationTypeFilters() {
        var filter = InventoryLiveSkoFilter()
        #expect(filter.activeCount == 0)
        filter.stockStatus = "low"
        filter.locationType = "picking"
        filter.familyID = 27
        #expect(filter.activeCount == 3)

        let items = filter.query(scope: .current, search: "").queryItems

        #expect(items.contains(URLQueryItem(name: "stock_status", value: "low")))
        #expect(items.contains(URLQueryItem(name: "location_type", value: "picking")))
        #expect(items.contains(URLQueryItem(name: "family_id", value: "27")))
        #expect(!InventoryLiveSkoFilter().query(scope: .current, search: "").queryItems.contains { $0.name == "stock_status" || $0.name == "location_type" })
    }

    @Test func skoListReadsTheFilterChoicesAikuSends() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let page = try decoder.decode(MobileOrgStocksPage.self, from: Data(#"{"data":[],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":0},"filters":{"stock_status":[{"key":"low","label":"Low"},{"key":"out_of_stock"}],"location_type":[{"key":"picking","label":"Picking"},{"key":"storing","label":"Storing"}]}}"#.utf8))

        #expect(page.filters?.stockStatus.map(\.key) == ["low", "out_of_stock"])
        #expect(page.filters?.stockStatus.map(\.title) == ["Low", "out_of_stock"])
        #expect(page.filters?.locationType.map(\.title) == ["Picking", "Storing"])
    }

    @Test func skoListStillLoadsWhenTheFilterChoicesAreMissingOrUnreadable() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let missing = try decoder.decode(MobileOrgStocksPage.self, from: Data(#"{"data":[],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":0}}"#.utf8))
        let unreadable = try decoder.decode(MobileOrgStocksPage.self, from: Data(#"{"data":[],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":0},"filters":{"stock_status":"low","location_type":[{"label":"No key"}]}}"#.utf8))

        #expect(missing.filters == nil)
        #expect(unreadable.filters?.stockStatus == [])
        #expect(unreadable.filters?.locationType == [])
    }

    @Test func searchableSelectFindsOptionsByAnyPartOfTheirTitle() {
        let options = (1...150).map { SearchableSelectOption(id: $0, title: "EO-\($0) · Essential Oils") } + [SearchableSelectOption(id: 999, title: "RDEO · Reed Diffusers")]

        #expect(SearchableSelectOption.matches(options, query: "reed", limit: 100).map(\.id) == [999])
        #expect(SearchableSelectOption.matches(options, query: "  ", limit: 100).count == 100)
        #expect(SearchableSelectOption.matches(options, query: "eo-14", limit: 100).map(\.id) == [14, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149])
    }

    @Test func familiesRequestSendsTheWebStateBucket() {
        let request = client().makeRequest(for: .mobileOrgStockFamilies(warehouseID: 1, state: InventoryFamilyState.inProcess.remoteValue, search: " eo ", page: 2, perPage: 50))
        let items = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)?.queryItems ?? []

        #expect(request.url?.path == "/app/api/mobile/warehouses/1/org-stock-families")
        #expect(items.contains(URLQueryItem(name: "state", value: "in_process")))
        #expect(items.contains(URLQueryItem(name: "q", value: "eo")))
        #expect(items.contains(URLQueryItem(name: "page", value: "2")))
    }

    @Test func familiesFeedLoadsFamiliesAndTheStateCounts() async throws {
        let feed = InventoryFamiliesFeed()

        await feed.reload(InventoryFamiliesRequest(warehouseID: 1, state: .active, search: ""), apiClient: client())

        #expect(feed.loadError == nil)
        #expect(feed.total == 882)
        #expect(feed.hasMorePages)
        #expect(feed.counts[.inProcess] == 42)
        #expect(feed.counts[.discontinued] == 1726)
        let essentialOils = try #require(feed.families.first)
        #expect(essentialOils.remoteID == 27)
        #expect(essentialOils.title == "EO · Essential Oils")
        #expect(essentialOils.skoCount == 84)
        #expect(essentialOils.stockValueLabel == "£17,030")
        #expect(essentialOils.coverLabel == "4.2 mo" || essentialOils.coverLabel == "4.3 mo")
        #expect(feed.families.last?.title == "A")
        #expect(feed.families.last?.coverLabel == "–")
        #expect(feed.family(code: "eo")?.remoteID == 27)
    }

    @Test func familySkosFeedReadsTheFamilySummaryAndSkos() async throws {
        let feed = InventoryFamilySkosFeed(warehouseID: 1, familyID: 27)

        await feed.reload(apiClient: client())

        #expect(feed.summary?.numberCurrentOrgStocks == 84)
        #expect(feed.hasMorePages)
        let sko = try #require(feed.skos.first)
        #expect(sko.code == "EO-01")
        #expect(sko.quantityInLocations?.value == 1828.75)
        #expect(sko.quantityInLocationsLabel == "1828 3/4")
        #expect(sko.quantityAvailableLabel == "1797 3/4")
    }

    @Test func familySkosFeedReadsTheWebFamilyStatusAndFractionLabels() async throws {
        let feed = InventoryFamilySkosFeed(warehouseID: 1, familyID: 27)

        await feed.reload(apiClient: client())

        let summary = try #require(feed.summary)
        #expect(summary.skoStatus.map(\.key) == ["active", "discontinuing", "discontinued", "suspended"])
        #expect(summary.skoStatus[1] == MobileFamilyStatusCount(key: "discontinuing", label: "Discontinuing", count: 3))
        #expect(summary.quantityStatus.map(\.key) == ["out", "w1", "w2", "w3", "w4", "ok", "excess", "dead"])
        #expect(summary.quantityStatus[2].count == 4)
        #expect(summary.skoStatusItems.map(\.tone) == [.success, .warn, .danger, .neutral])
        #expect(summary.quantityStatusItems.first?.title == "Out of stock")
        #expect(summary.quantityStatusItems.first?.tone == .danger)
        let lavender = try #require(feed.skos.first)
        #expect(lavender.quantityText == "1797\u{202F}³⁄₄")
        #expect(lavender.stateChip == nil)
        let rose = try #require(feed.skos.last)
        #expect(rose.quantityText == "¹⁄₂")
        #expect(rose.stateChip == Chip("Discontinuing", .warn))
    }

    @Test func familySkosFromAnOlderServerHaveNoStatusAndKeepTheirNumbers() async throws {
        let feed = InventoryFamilySkosFeed(warehouseID: 1, familyID: 28)

        await feed.reload(apiClient: client())

        #expect(feed.loadError == nil)
        let summary = try #require(feed.summary)
        #expect(summary.skoStatus.isEmpty)
        #expect(summary.quantityStatus.isEmpty)
        let sko = try #require(feed.skos.first)
        #expect(sko.quantityAvailableLabel == nil)
        #expect(sko.quantityText == "1,797")
    }

    @Test func sampleFamiliesKeepTheirWeeksOfCover() {
        let family = InventoryFamily(code: "BB", name: "Bath bombs", state: .active, skoCount: 42, stockValueLabel: "[£ 3.9k]", coverWeeks: 6)

        #expect(family.subtitle == "42 SKOs · stock value [£ 3.9k] · cover 6 weeks")
    }
}
