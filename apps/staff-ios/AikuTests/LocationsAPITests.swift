import Foundation
import Testing
@testable import Aiku

@MainActor
struct LocationsAPITests {
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

    private static let locationJSON = #"""
    {"id":7,"slug":"a-03-02","code":"A-03-02","status":"operational","area":{"id":3,"code":"A","name":"Picking"},
     "allow_stocks":true,"allow_fulfilment":false,"allow_dropshipping":true,"is_goods_out":false,"keep_empty_org_stocks":true,
     "max_weight":120,"max_volume":1.2,"number_org_stock_slots":3,"number_empty_stock_slots":1,"number_pallets":0,
     "stock_value":288.5,"audited_at":"2026-10-28T09:15:00+00:00"
    """#

    private static let responses: [String: String] = [
        "mobile/warehouses": #"{"data":[{"id":1,"slug":"ed","code":"ED","name":"Europa Drive","organisation":{"id":1,"slug":"aw","name":"AW UK"},"can":{"view_locations":true,"edit_locations":false}},{"id":4,"slug":"par","code":"PAR","name":"Paris","organisation":{"id":2,"slug":"es","name":"AW Europe"},"can":{"view_locations":false,"edit_locations":false}}]}"#,
        "mobile/warehouses/1/locations-overview": #"{"data":{"warehouse":{"id":1,"code":"ED","name":"Europa Drive"},"number_areas":1,"number_locations":40,"number_locations_operational":40,"number_locations_broken":0,"number_locations_empty":10,"number_locations_partially_empty":5,"stock_value":1000,"empty_locations_removal":{"enabled":false,"can_edit":true,"last_change":{"by":"Raul","at":"2026-11-02T06:30:00.000000Z"},"to_remove":661,"removed_last_7_days":12,"max_per_run":200}}}"#,
        "mobile/warehouses/1/empty-location-org-stocks": #"{"data":[{"id":91403,"date":null,"slug":"jbb-gift","code":"JBB-Gift","name":"JBB Gift Boxes (empty)","location_slug":"1x13","location_code":"1X13"}],"meta":{"current_page":1,"last_page":2,"per_page":1,"total":2}}"#,
        "mobile/warehouses/1/removed-empty-location-org-stocks": #"{"data":[{"id":501,"date":"2026-11-10T08:00:00.000000Z","slug":"eo-13","code":"EO-13","name":null,"location_slug":"0a1","location_code":"0A1"}],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":1}}"#,
        "mobile/warehouses/1/empty-locations-removal": #"{"data":{"enabled":true,"can_edit":true,"last_change":{"by":"andi","at":"2026-11-11T08:00:00+00:00"},"to_remove":661,"removed_last_7_days":12,"max_per_run":200}}"#,
        "mobile/warehouses/1/areas": #"{"data":[{"id":3,"code":"A","name":"Picking","picking_position":1.5,"number_locations":40,"number_locations_broken":0,"number_empty_locations":10,"stock_value":1000}]}"#,
        "mobile/warehouses/1/locations": #"{"data":["# + locationJSON + #"}],"meta":{"current_page":1,"last_page":1,"per_page":100,"total":1}}"#,
        "mobile/locations/7": #"{"data":"# + locationJSON + #","can":{"edit_stocks":true,"edit_locations":false},"pallets":[{"id":48449,"slug":"p-49","reference":"451621-awf-p0049","customer_reference":"wax melts","customer_name":"UK Asia Direct LTD","state":"storing","state_label":"Storing","status":"storing","status_label":"Storing","type":"pallet"}],"org_stocks":[{"id":5,"code":"SKO-00733","name":"Bath bomb","slug":"sko-00733","sku_value":null,"type":"picking","picking_priority":1,"value":"288.50","dropshipping_pipe":null,"quantity":"30","notes":null,"location_org_stock_id":11,"packed_at":null,"packed_by":null,"packed_quantity":null,"un_numbers":[],"is_packed":false,"default_wholesale_picking_location":true,"default_dropshipping_picking_location":false,"min_stock":"12","max_stock":48,"replenishment_stock":null,"audited_at":null}]}}"#,
        "mobile/locations/7/audit": #"{"data":"# + locationJSON + #","can":{"edit_stocks":true,"edit_locations":false},"org_stocks":[{"id":5,"code":"SKO-00733","name":"Bath bomb","slug":"sko-00733","sku_value":null,"type":"picking","picking_priority":1,"value":"288.50","dropshipping_pipe":null,"quantity":"25","notes":null,"location_org_stock_id":11,"packed_at":null,"packed_by":null,"packed_quantity":null,"un_numbers":[],"is_packed":false,"default_wholesale_picking_location":true,"default_dropshipping_picking_location":false,"min_stock":"12","max_stock":48,"replenishment_stock":null,"audited_at":null}]}}"#,
        "mobile/locations/7/move-some": #"{"data":"# + locationJSON + #","can":{"edit_stocks":true,"edit_locations":false},"org_stocks":[{"id":5,"code":"SKO-00733","name":"Bath bomb","slug":"sko-00733","sku_value":null,"type":"picking","picking_priority":1,"value":"288.50","dropshipping_pipe":null,"quantity":"20","notes":null,"location_org_stock_id":11,"packed_at":null,"packed_by":null,"packed_quantity":null,"un_numbers":[],"is_packed":false,"default_wholesale_picking_location":true,"default_dropshipping_picking_location":false,"min_stock":"12","max_stock":48,"replenishment_stock":null,"audited_at":null}]}}"#,
        "mobile/location-org-stocks/11": #"{"data":"# + locationJSON + #","can":{"edit_stocks":true,"edit_locations":false},"org_stocks":[{"id":5,"code":"SKO-00733","name":"Bath bomb","slug":"sko-00733","sku_value":null,"type":"picking","picking_priority":1,"value":"288.50","dropshipping_pipe":null,"quantity":"30","notes":"Top shelf","location_org_stock_id":11,"packed_at":null,"packed_by":null,"packed_quantity":null,"un_numbers":[],"is_packed":false,"default_wholesale_picking_location":true,"default_dropshipping_picking_location":false,"min_stock":"12","max_stock":48,"replenishment_stock":null,"audited_at":null}]}}"#,
        "mobile/locations/7/stock-movements": #"{"data":[{"id":9,"date":"2026-11-12T06:47:10.580996Z","type":"location-transfer","type_label":"Location Transfer","flow":"out","audited_quantity":"","quantity":"-48","running_quantity":"0","reason":null,"reason_label":null,"note":null,"org_stock_name":"Basil Essential Oil","org_stock_slug":"eo-13","parent_type":null,"parent_reference":null,"org_stock_code":"EO-13","user_name":null},{"id":8,"date":"2026-11-12T05:45:41Z","type":"audit","type_label":"Audit","flow":"audit","audited_quantity":"46","quantity":"-2","running_quantity":"46","reason":"count_short","reason_label":"Count Shortage","note":"iOS audit test","org_stock_name":"Basil Essential Oil","org_stock_slug":"eo-13","parent_type":null,"parent_reference":null,"org_stock_code":"EO-13","user_name":"Andi"}],"meta":{"current_page":1,"last_page":3,"per_page":50,"total":120}}"#,
        "mobile/locations/7/remove-org-stocks": #"{"removed":1,"data":"# + locationJSON + #","can":{"edit_stocks":true,"edit_locations":true},"org_stocks":[]}}"#,
    ]

    private func warehouse(_ id: Int) async -> MobileWarehouse? {
        try? await client().fetchWarehouses().first { $0.id == id }
    }

    private func isolatedDefaults() -> UserDefaults {
        let name = "aiku.tests.\(UUID().uuidString)"
        let defaults = UserDefaults(suiteName: name)!
        defaults.removePersistentDomain(forName: name)
        return defaults
    }

    private func client(_ responses: [String: String] = Self.responses) -> APIClient {
        APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: RoutedTransport(responses: responses),
            tokenProvider: { "1|tok" }
        )
    }

    @Test func locationsQueryOnlySendsTheFiltersInUse() {
        let request = client().makeRequest(for: .mobileLocations(warehouseID: 1, query: MobileLocationsQuery(search: " A-03 ", areaID: 3, occupancy: .partiallyEmpty)))
        let items = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)?.queryItems ?? []

        #expect(request.url?.path == "/app/api/mobile/warehouses/1/locations")
        #expect(items.contains(URLQueryItem(name: "q", value: "A-03")))
        #expect(items.contains(URLQueryItem(name: "area_id", value: "3")))
        #expect(items.contains(URLQueryItem(name: "occupancy", value: "partially_empty")))
        #expect(request.value(forHTTPHeaderField: "X-Aiku-App-Key") == "key")
    }

    @Test func remoteLocationMapsToTheScreenModel() async throws {
        let detail = try await client().fetchLocation(id: 7)
        let location = LocationsLocation(remote: detail)

        #expect(location.code == "A-03-02")
        #expect(location.areaCode == "A")
        #expect(location.scopes == [.stocks, .dropshipping])
        #expect(location.neverRemoveEmptySkos)
        #expect(location.maxWeightKg == 120)
        #expect(location.auditedOn != nil)
        #expect(location.slots.count == 1)
        #expect(location.slots.first?.skoReference == "SKO-00733")
        #expect(location.slots.first?.quantity == 30)
        #expect(location.slots.first?.type == .picking)
        #expect(location.slots.first?.roles == [.wholesale])
        #expect(location.slots.first?.minStock == 12)
    }

    @Test func listRowsUseServerSlotCountsBeforeTheDetailLoads() async throws {
        let page = try await client().fetchLocations(warehouseID: 1, query: MobileLocationsQuery())
        let location = LocationsLocation(remote: try #require(page.data.first))

        #expect(location.slots.isEmpty)
        #expect(location.slotSummary == "3 slots, 1 empty")
        #expect(location.isPartiallyEmpty)
        #expect(location.stockValue == 288.5)
    }

    @Test func storeLoadsWarehouseAreasAndFirstPage() async {
        let store = LocationsStore()

        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        #expect(store.source == .remote)
        #expect(store.loadError == nil)
        #expect(store.remoteWarehouse?.code == "ED")
        #expect(store.warehouse.summary.locationCount == 40)
        #expect(store.warehouse.areas.map(\.code) == ["A"])
        #expect(store.warehouse.areas.first?.remoteID == 3)
        #expect(store.warehouse.areas.first?.pickingOrder == 2)
        #expect(store.warehouse.locations.map(\.code) == ["A-03-02"])
        #expect(store.count(for: .all) == 1)
        #expect(store.count(for: .empty) == 10)
    }

    @Test func storeKeepsLoadedSkosWhenTheListReloads() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        await store.loadLocation(code: "A-03-02", apiClient: client())
        await store.reloadList(apiClient: client())

        #expect(store.warehouse.location(code: "A-03-02")?.slots.count == 1)
    }

    @Test func storeRefusesAWarehouseWithoutLocationAccess() async {
        let store = LocationsStore()

        await store.loadRemote(for: 291, warehouse: await warehouse(4), apiClient: client())

        #expect(store.source == .sample)
        #expect(store.loadError == "You don’t have access to locations in Paris.")
    }

    @Test func warehouseStorePrefersAWarehouseWithLocationAccessThenRemembersTheChoice() async {
        let defaults = isolatedDefaults()
        let first = WarehouseStore(defaults: defaults)

        await first.load(for: 291, apiClient: client())
        #expect(first.selected?.code == "ED")

        first.select(4)
        let reopened = WarehouseStore(defaults: defaults)
        await reopened.load(for: 291, apiClient: client())

        #expect(reopened.selected?.code == "PAR")
    }

    @Test func warehouseStoreIgnoresAWarehouseItCannotList() async {
        let store = WarehouseStore(defaults: isolatedDefaults())
        await store.load(for: 291, apiClient: client())

        store.select(99)

        #expect(store.selectedID == 1)
    }

    @Test func warehouseSheetGroupsRealWarehousesByOrganisation() async throws {
        let warehouses = try await client().fetchWarehouses()
        let organisations = ShellWarehouseDirectory.organisations(from: warehouses)

        #expect(organisations.map(\.name) == ["AW UK", "AW Europe"])
        #expect(organisations.map(\.initials) == ["AU", "AE"])
        #expect(organisations.first?.warehouses.first?.remoteID == 1)
        #expect(ShellWarehouseDirectory.choice(organisation: organisations[1], warehouse: organisations[1].warehouses[0]).warehouseID == 4)
    }

    private struct PagedTransport: HTTPTransport {
        let base: [String: String]

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            let components = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            let page = components?.queryItems?.first { $0.name == "page" }?.value ?? "1"
            let body: String?
            if path == "mobile/warehouses/1/locations" {
                body = Self.page(Int(page) ?? 1)
            } else {
                body = base[path]
            }
            guard let body else {
                return (Data(), HTTPURLResponse(url: request.url!, statusCode: 404, httpVersion: nil, headerFields: nil)!)
            }
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
        }

        static func page(_ number: Int) -> String {
            let code = number == 1 ? "A-01" : "A-02"
            return #"{"data":[{"id":"# + "\(number)" + #","slug":"x","code":""# + code + #"","status":"operational","area":{"id":3,"code":"A","name":"Picking"},"allow_stocks":true,"allow_fulfilment":false,"allow_dropshipping":false,"is_goods_out":false,"keep_empty_org_stocks":false,"max_weight":null,"max_volume":null,"number_org_stock_slots":1,"number_empty_stock_slots":0,"number_pallets":0,"stock_value":0,"audited_at":null}],"meta":{"current_page":"# + "\(number)" + #","last_page":2,"per_page":1,"total":2}}"#
        }
    }

    private func pagedClient() -> APIClient {
        APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: PagedTransport(base: Self.responses),
            tokenProvider: { "1|tok" }
        )
    }

    @Test func storeLoadsTheNextPageUntilTheLastOne() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: pagedClient())

        #expect(store.warehouse.locations.map(\.code) == ["A-01"])
        #expect(store.hasMorePages)

        await store.loadNextPage(apiClient: pagedClient())

        #expect(store.warehouse.locations.map(\.code) == ["A-01", "A-02"])
        #expect(store.currentPage == 2)
        #expect(!store.hasMorePages)

        await store.loadNextPage(apiClient: pagedClient())
        #expect(store.warehouse.locations.count == 2)
    }

    @Test func changingTheTabStartsAgainFromTheFirstPage() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: pagedClient())
        await store.loadNextPage(apiClient: pagedClient())

        store.listTab = .partiallyEmpty
        await store.reloadList(apiClient: pagedClient())

        #expect(store.currentPage == 1)
        #expect(store.warehouse.locations.map(\.code) == ["A-01"])
        #expect(store.hasMorePages)
    }

    @Test func switchingTabsNeverShowsTheTotalOfAnotherTab() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        #expect(store.count(for: .all) == 1)

        store.listTab = .empty

        #expect(store.count(for: .empty) == 10)
        #expect(store.count(for: .all) == 1)
    }

    @Test func searchingHidesCountsThatAreNotKnownYet() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        store.listQuery = "A-03"

        #expect(store.count(for: .all) == nil)
        #expect(store.count(for: .empty) == nil)

        await store.reloadList(apiClient: client())

        #expect(store.count(for: .all) == 1)
        #expect(store.count(for: .empty) == nil)
    }

    @Test func areaTitleDropsACodeThatOnlyRepeatsTheName() {
        let area = LocationsArea(remote: MobileWarehouseArea(id: 1, code: "0-picking", name: "0 Picking", pickingPosition: 1, numberLocations: 89, numberEmptyLocations: 0, stockValue: 0))

        #expect(area.title == "0 Picking")
        #expect(area.filterTitle == "0 Picking")
        #expect(area.subtitle == "Picking order 1 · 89 locations · 0 empty")
    }

    @Test func areaWithoutAPickingPositionSaysSo() {
        let area = LocationsArea(remote: MobileWarehouseArea(id: 3, code: "01-storage", name: "01 Storage", pickingPosition: nil, numberLocations: 144, numberEmptyLocations: 0, stockValue: 0))

        #expect(area.pickingOrder == nil)
        #expect(area.subtitle == "No picking order · 144 locations · 0 empty")
    }

    @Test func areaTitleKeepsADistinctCodeAndShowsStockValue() {
        let area = LocationsArea(remote: MobileWarehouseArea(id: 2, code: "A", name: "Picking", pickingPosition: 2, numberLocations: 412, numberEmptyLocations: 12, stockValue: 96_400))

        #expect(area.title == "Area A · Picking")
        #expect(area.subtitle == "Picking order 2 · 412 locations · 12 empty · stock value 96,400")
    }

    @Test func overviewFillsTheEmptyLocationRemovalPanel() async {
        let store = LocationsStore()

        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        #expect(store.warehouse.summary.toRemoveNow == 661)
        #expect(store.warehouse.summary.removedLastWeek == 12)
        #expect(store.warehouse.autoRemoval.isOn == false)
        #expect(store.warehouse.autoRemoval.canEdit)
        #expect(store.warehouse.autoRemoval.changedBy == "Raul")
        #expect(store.warehouse.autoRemoval.changedOn != nil)
    }

    @Test func autoRemovalWithoutAChangeSaysSo() {
        let removal = LocationsAutoRemoval(remote: MobileEmptyLocationsRemoval(enabled: false, canEdit: false, lastChange: nil, toRemove: 0, removedLast7Days: 0, maxPerRun: 200))

        #expect(removal.detail == "Not changed yet")
        #expect(!removal.canEdit)
    }

    @Test func switchingAutoRemovalSavesItOnTheServer() async throws {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        try await store.setRemoteAutoRemoval(true, by: "andi", apiClient: client())

        #expect(store.warehouse.autoRemoval.isOn)
        #expect(store.warehouse.autoRemoval.changedBy == "andi")
    }

    @Test func autoRemovalGoesBackWhenTheServerRefuses() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        var refusing = Self.responses
        refusing["mobile/warehouses/1/empty-locations-removal"] = nil

        await #expect(throws: (any Error).self) {
            try await store.setRemoteAutoRemoval(true, by: "andi", apiClient: client(refusing))
        }
        #expect(store.warehouse.autoRemoval.isOn == false)
        #expect(store.warehouse.autoRemoval.changedBy == "Raul")
    }

    @Test func autoRemovalRequestSendsTheSwitchAsPatch() throws {
        let request = client().makeRequest(for: .mobileEmptyLocationsRemoval(warehouseID: 1, enabled: true))
        let body = try JSONSerialization.jsonObject(with: try #require(request.httpBody)) as? [String: Bool]

        #expect(request.httpMethod == "PATCH")
        #expect(request.url?.path == "/app/api/mobile/warehouses/1/empty-locations-removal")
        #expect(body == ["enabled": true])
    }

    @Test func removedListAsksForTheWebRemovalLog() {
        let request = client().makeRequest(for: .mobileEmptyLocationSlots(warehouseID: 1, kind: LocationsEmptySlotsKind.removedLastWeek.remoteKind, search: " 0A ", page: 2, perPage: 50))
        let items = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)?.queryItems ?? []

        #expect(request.url?.path == "/app/api/mobile/warehouses/1/removed-empty-location-org-stocks")
        #expect(!items.contains { $0.name == "days" })
        #expect(items.contains(URLQueryItem(name: "q", value: "0A")))
        #expect(items.contains(URLQueryItem(name: "page", value: "2")))
    }

    @Test func toRemoveFeedLoadsSlotsAndKnowsThereIsMore() async {
        let feed = LocationsEmptySlotsFeed(kind: .toRemove)

        await feed.reload(LocationsEmptySlotsRequest(warehouseID: 1, search: ""), apiClient: client())

        #expect(feed.loadError == nil)
        #expect(feed.total == 2)
        #expect(feed.hasMorePages)
        #expect(feed.slots.first == LocationsEmptySlot(id: 91403, skoReference: "JBB-Gift", name: "JBB Gift Boxes (empty)", locationCode: "1X13", removedOn: nil))
    }

    @Test func removedFeedReadsTheRemovalDate() async {
        let feed = LocationsEmptySlotsFeed(kind: .removedLastWeek)

        await feed.reload(LocationsEmptySlotsRequest(warehouseID: 1, search: ""), apiClient: client())

        #expect(feed.slots.map(\.locationCode) == ["0A1"])
        #expect(feed.slots.first?.removedOn != nil)
        #expect(feed.slots.first?.name == "")
        #expect(!feed.hasMorePages)
    }

    @Test func filterSendsEveryChoiceToTheServer() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        var filter = LocationsFilter()
        filter.areas = ["A"]
        filter.statuses = [.operational]
        filter.scopes = [.stocks, .dropshipping]
        filter.occupancies = [.empty, .neverRemoveEmptySkos]
        filter.sort = .stockValue
        store.listFilter = filter

        let items = store.remoteQuery(for: store.listRequestKey, page: 1).queryItems

        #expect(items.filter { $0.name == "area_ids[]" }.map(\.value) == ["3"])
        #expect(items.filter { $0.name == "statuses[]" }.map(\.value) == ["operational"])
        #expect(items.filter { $0.name == "scopes[]" }.map(\.value) == ["dropshipping", "stocks"])
        #expect(items.filter { $0.name == "occupancies[]" }.map(\.value) == ["empty", "keep_empty_org_stocks"])
        #expect(items.contains(URLQueryItem(name: "sort", value: "-stock_value")))
        #expect(!items.contains { $0.name == "area_id" })
    }

    @Test func severalAreasAreAllSent() {
        let query = MobileLocationsQuery(areaIDs: [9, 3])

        #expect(query.queryItems.filter { $0.name == "area_ids[]" }.map(\.value) == ["3", "9"])
    }

    @Test func remoteListShowsWhatTheServerReturnedNotEveryLoadedLocation() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        store.warehouse.locations.append(LocationsLocation(remote: MobileLocation(
            id: 99, code: "Z-99", status: "operational", area: nil, allowStocks: true, allowFulfilment: false, allowDropshipping: false,
            isGoodsOut: false, keepEmptyOrgStocks: false, maxWeight: nil, maxVolume: nil, numberOrgStockSlots: 0, numberEmptyStockSlots: 0,
            numberPallets: 0, stockValue: 0, auditedAt: nil, orgStocks: nil, can: nil
        )))

        #expect(store.listedLocations.map(\.code) == ["A-03-02"])
    }

    @Test func filterSheetCountComesFromTheServer() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        var filter = LocationsFilter()
        filter.areas = ["A"]

        #expect(await store.resultCount(for: filter, apiClient: client()) == 1)
        #expect(await store.resultCount(for: filter, apiClient: client([:])) == nil)
    }

    @Test func detailShowsLoadingUntilItsSkosArrive() async throws {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        let listed = try #require(store.warehouse.location(code: "A-03-02"))

        #expect(store.isWaitingForSkos(in: listed))

        await store.loadLocation(code: "A-03-02", apiClient: client())

        #expect(!store.isWaitingForSkos(in: try #require(store.warehouse.location(code: "A-03-02"))))
    }

    @Test func detailStopsLoadingWhenTheServerFails() async throws {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        var failing = Self.responses
        failing["mobile/locations/7"] = nil

        await store.loadLocation(code: "A-03-02", apiClient: client(failing))

        #expect(!store.isWaitingForSkos(in: try #require(store.warehouse.location(code: "A-03-02"))))
        #expect(store.loadError != nil)
    }

    @Test func scannedBarcodeFindsTheLocationWhateverTheCase() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        #expect(await store.locationMatching(scannedCode: " a-03-02 ", apiClient: client())?.code == "A-03-02")
    }

    @Test func scannedBarcodeIsLookedUpOnTheServerWhenNotLoaded() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        store.warehouse.locations = []

        #expect(await store.locationMatching(scannedCode: "a-03-02", apiClient: client())?.code == "A-03-02")
        #expect(store.warehouse.location(code: "A-03-02") != nil)
    }

    @Test func scannedBarcodeWithoutAnExactMatchFindsNothing() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        store.warehouse.locations = []

        #expect(await store.locationMatching(scannedCode: "A-03", apiClient: client()) == nil)
    }

    private func detailedStore() async -> LocationsStore {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())
        await store.loadLocation(code: "A-03-02", apiClient: client())
        return store
    }

    @Test func detailKeepsTheServerIdsOfEachSkoAndTheUserRights() async {
        let store = await detailedStore()
        let slot = store.warehouse.location(code: "A-03-02")?.slot("SKO-00733")

        #expect(slot?.remoteOrgStockID == 5)
        #expect(slot?.remoteLinkID == 11)
        #expect(store.canEditStocks(in: "A-03-02"))
        #expect(!store.canEditLocations(in: "A-03-02"))
    }

    @Test func auditSendsEachCountWithTheWebReason() throws {
        let request = client().makeRequest(for: .mobileLocationAudit(id: 7, counts: [
            MobileLocationAuditCount(locationOrgStockId: 11, quantity: 25, reason: LocationsAuditReason.countShortage.remoteValue, note: "Broken box"),
        ]))
        let body = try JSONSerialization.jsonObject(with: try #require(request.httpBody)) as? [String: [[String: Any]]]
        let count = try #require(body?["counts"]?.first)

        #expect(request.httpMethod == "POST")
        #expect(request.url?.path == "/app/api/mobile/locations/7/audit")
        #expect(count["location_org_stock_id"] as? Int == 11)
        #expect(count["quantity"] as? Int == 25)
        #expect(count["reason"] as? String == "count_short")
        #expect(count["note"] as? String == "Broken box")
    }

    @Test func remoteAuditUsesRecountWhenNothingChangedAndShowsTheNewCount() async throws {
        let store = await detailedStore()

        let differences = try await store.auditRemotely(location: "A-03-02", counts: [
            LocationsAuditCount(skoReference: "SKO-00733", counted: 25, reason: .countShortage),
        ], apiClient: client())

        #expect(differences == 1)
        #expect(store.warehouse.location(code: "A-03-02")?.slot("SKO-00733")?.quantity == 25)
    }

    @Test func moveSomeSendsTheOrgStocksAndTheTransferReason() throws {
        let request = client().makeRequest(for: .mobileLocationMoveSome(id: 7, targetID: 9, orgStocks: [
            MobileMovedOrgStock(orgStockId: 5, quantity: 10, removeAfterMove: false),
        ]))
        let data = try #require(request.httpBody)
        let body = try #require(try JSONSerialization.jsonObject(with: data) as? [String: Any])
        let moved = try #require((body["org_stocks"] as? [[String: Any]])?.first)

        #expect(request.url?.path == "/app/api/mobile/locations/7/move-some")
        #expect(body["location_id"] as? Int == 9)
        #expect(body["reason"] as? String == "transfer")
        #expect(moved["org_stock_id"] as? Int == 5)
        #expect(moved["quantity"] as? Int == 10)
        #expect(moved["remove_after_move"] as? Bool == false)
    }

    @Test func moveAllSendsTheTargetAndWhetherToRemove() throws {
        let request = client().makeRequest(for: .mobileLocationMoveAll(id: 7, targetID: 9, removeAfterMove: true))
        let data = try #require(request.httpBody)
        let body = try #require(try JSONSerialization.jsonObject(with: data) as? [String: Any])

        #expect(request.url?.path == "/app/api/mobile/locations/7/move-all")
        #expect(body["location_id"] as? Int == 9)
        #expect(body["remove_after_move"] as? Bool == true)
        #expect(body["reason"] as? String == "transfer")
    }

    @Test func remoteMoveFindsTheTargetOnTheServerAndRefreshesTheSource() async throws {
        let store = await detailedStore()

        let moved = try await store.moveRemotely(from: "A-03-02", to: "a-03-02", quantities: ["SKO-00733": 10], removeFromSource: false, movesEverything: false, apiClient: client())

        #expect(moved == 10)
        #expect(store.warehouse.location(code: "A-03-02")?.slot("SKO-00733")?.quantity == 20)
    }

    @Test func remoteMoveToAnUnknownLocationFails() async {
        let store = await detailedStore()

        await #expect(throws: LocationsActionError.targetNotFound("Z-99")) {
            try await store.moveRemotely(from: "A-03-02", to: "Z-99", quantities: ["SKO-00733": 1], removeFromSource: false, movesEverything: false, apiClient: client())
        }
    }

    @Test func remoteRemoveSendsOrgStockIdsAndEmptiesTheLocation() async throws {
        let request = client().makeRequest(for: .mobileLocationRemoveOrgStocks(id: 7, orgStockIDs: [5]))
        let data = try #require(request.httpBody)
        let body = try #require(try JSONSerialization.jsonObject(with: data) as? [String: [Int]])
        let store = await detailedStore()

        let removed = try await store.removeRemotely(skos: ["SKO-00733"], from: "A-03-02", apiClient: client())

        #expect(body["org_stock_ids"] == [5])
        #expect(removed == 1)
        #expect(store.warehouse.location(code: "A-03-02")?.slots.isEmpty == true)
        #expect(store.canEditLocations(in: "A-03-02"))
    }

    @Test func settingsSendOnlyWhatTheWebUpdateTakes() {
        let slot = LocationsSlot(skoReference: "SKO-1", name: "", quantity: 3, type: .storing, roles: [.dropshipping], unitWeightKg: 0, unitVolumeM3: 0, unitValue: 0)
        let picking = LocationsRemoteUpdate.body(from: slot, to: LocationsSlotSettings(type: .picking, roles: [.wholesale, .dropshipping], minStock: 12, maxStock: 48, replenishment: 9, note: "Top shelf"))
        let storing = LocationsRemoteUpdate.body(from: slot, to: LocationsSlotSettings(type: .storing, roles: [], minStock: 12, maxStock: 48, replenishment: 9, note: ""))

        #expect(picking == MobileLocationOrgStockUpdate(type: "picking", minStock: 12, maxStock: 48, replenishmentStock: nil, notes: "Top shelf", setAsPriorityWholesale: true, setAsPriorityDropshipping: nil))
        #expect(storing == MobileLocationOrgStockUpdate(type: "storing", minStock: nil, maxStock: nil, replenishmentStock: 9, notes: "", setAsPriorityWholesale: nil, setAsPriorityDropshipping: nil))
    }

    @Test func remoteSettingsUpdateShowsTheSavedNote() async throws {
        let store = await detailedStore()
        let slot = try #require(store.warehouse.location(code: "A-03-02")?.slot("SKO-00733"))
        var settings = slot.settings
        settings.note = "Top shelf"

        try await store.updateRemotely(sko: "SKO-00733", in: "A-03-02", settings: settings, apiClient: client())

        #expect(store.warehouse.location(code: "A-03-02")?.slot("SKO-00733")?.note == "Top shelf")
    }

    @Test func sampleDataKeepsEveryActionAvailable() {
        let store = LocationsStore()

        #expect(store.canEditStocks(in: "anything"))
        #expect(store.canEditLocations(in: "anything"))
    }

    @Test func onlyTheActiveTabShowsItsCount() async {
        let store = LocationsStore()
        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        #expect(store.pillTitle(for: .all) == "All 1")
        #expect(store.pillTitle(for: .empty) == "Empty")
        #expect(store.pillTitle(for: .partiallyEmpty) == "Partially empty")

        store.listTab = .empty

        #expect(store.pillTitle(for: .all) == "All")
        #expect(store.pillTitle(for: .empty) == "Empty")

        await store.reloadList(apiClient: client())

        #expect(store.pillTitle(for: .empty) == "Empty 1")
    }

    @Test func locationDetailShowsItsPalletsFromTheServer() async throws {
        let store = await detailedStore()
        let pallet = try #require(store.warehouse.location(code: "A-03-02")?.pallets.first)

        #expect(pallet == LocationsPallet(reference: "451621-awf-p0049", customer: "UK Asia Direct LTD", state: "Storing"))
    }

    @Test func movementsFeedReadsTheWebHistoryNewestFirst() async throws {
        let feed = LocationsMovementsFeed(locationID: 7)

        await feed.reload(apiClient: client())

        #expect(feed.loadError == nil)
        #expect(feed.total == 120)
        #expect(feed.hasMorePages)
        let transfer = try #require(feed.movements.first)
        let audit = try #require(feed.movements.last)
        #expect(transfer.skoReference == "EO-13")
        #expect(transfer.quantityLabel == "−48")
        #expect(transfer.detail.contains("Location Transfer"))
        #expect(transfer.detail.contains("System"))
        #expect(audit.quantityLabel == "−2")
        #expect(audit.detail.contains("Count Shortage"))
        #expect(audit.detail.contains("Andi"))
        #expect(audit.detail.contains("iOS audit test"))
    }

    @Test func movementsRequestPagesThroughTheLocation() {
        let request = client().makeRequest(for: .mobileLocationStockMovements(id: 7, page: 2, perPage: 50))
        let items = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)?.queryItems ?? []

        #expect(request.url?.path == "/app/api/mobile/locations/7/stock-movements")
        #expect(items.contains(URLQueryItem(name: "page", value: "2")))
    }

    @Test func signedInStoreShowsNoSampleLocationsWhileLoading() async {
        let store = LocationsStore()

        store.beginRemoteLoad()

        #expect(store.isRemote)
        #expect(store.listedLocations.isEmpty)
        #expect(store.isLoadingList)
        #expect(store.pillTitle(for: .all) == "All")

        await store.loadRemote(for: 291, warehouse: await warehouse(1), apiClient: client())

        #expect(store.listedLocations.map(\.code) == ["A-03-02"])
        #expect(!store.isLoadingList)
    }

    @Test func loadingStopsWhenTheUserHasNoLocationAccess() async {
        let store = LocationsStore()
        store.beginRemoteLoad()

        await store.loadRemote(for: 291, warehouse: await warehouse(4), apiClient: client())

        #expect(!store.isLoadingList)
        #expect(store.loadError != nil)
        #expect(store.listedLocations.isEmpty)
    }

    @Test func movementsWithoutARunningQuantityStillLoad() throws {
        let json = #"{"data":[{"id":1,"date":"2025-08-07T12:04:37.004000Z","type":"disassociate","type_label":"Disassociate","flow":"audit","audited_quantity":"0","quantity":"0","running_quantity":"","reason":null,"reason_label":null,"note":null,"org_stock_name":"Rosemary & Thyme Bath Bomb","org_stock_slug":"dsebb-05","parent_type":null,"parent_reference":null,"org_stock_code":"DSEBB-05","user_name":null}],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":1}}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let page = try decoder.decode(MobileLocationMovementsPage.self, from: Data(json.utf8))
        let movement = LocationsMovement(remote: try #require(page.data.first))

        #expect(movement.runningQuantity == nil)
        #expect(movement.quantityLabel == "")
        #expect(movement.detail.hasPrefix("Disassociate · 7 Aug at "))
        #expect(movement.detail.hasSuffix(" · System"))
    }
}

