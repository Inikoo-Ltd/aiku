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
        "mobile/warehouses/1/org-stock-families/27/skos": #"{"family":{"id":27,"code":"EO","name":"Essential Oils","state":"active","number_current_org_stocks":84,"stock_value":17030.33,"currency_code":"GBP"},"data":[{"id":133,"slug":"eo-01-aw","code":"EO-01","name":"Lavender Essential Oil","quantity_available":"1797","quantity_in_locations":"1828","stock_value":"1221.96","number_locations":null,"woc":-9.7,"stock_cover":1.3,"state":"active"}],"meta":{"current_page":1,"last_page":2,"per_page":50,"total":84}}"#,
    ]

    private func client() -> APIClient {
        APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: RoutedTransport(responses: Self.responses),
            tokenProvider: { "1|tok" }
        )
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
        #expect(sko.quantityInLocations?.value == 1828)
    }

    @Test func sampleFamiliesKeepTheirWeeksOfCover() {
        let family = InventoryFamily(code: "BB", name: "Bath bombs", state: .active, skoCount: 42, stockValueLabel: "[£ 3.9k]", coverWeeks: 6)

        #expect(family.subtitle == "42 SKOs · stock value [£ 3.9k] · cover 6 weeks")
    }
}
