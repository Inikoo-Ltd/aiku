import Foundation
import Testing
@testable import Aiku

private func decode<T: Decodable>(_ type: T.Type, _ json: String) throws -> T {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode(T.self, from: Data(json.utf8))
}

private actor Attempts {
    private(set) var count = 0

    func next() -> Int {
        count += 1
        return count
    }
}

@MainActor
struct WarehouseDashboardAPITests {
    private final class RecordingTransport: HTTPTransport, @unchecked Sendable {
        let responses: [String: String]
        private let lock = NSLock()
        private var recorded: [URLRequest] = []

        init(responses: [String: String]) {
            self.responses = responses
        }

        var requests: [URLRequest] {
            lock.withLock { recorded }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            lock.withLock { recorded.append(request) }
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            guard let body = responses[path] else {
                return (Data(), HTTPURLResponse(url: request.url!, statusCode: 404, httpVersion: nil, headerFields: nil)!)
            }
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
        }
    }

    nonisolated private static let full = #"""
    {"data":{"generated_at":"2026-11-18T15:00:00+00:00","goods_out":{"unassigned":47,"handling":17,"handling_blocked":2,"picked":9,"packed":26,"in_warehouse":101,"dispatched_today":{"count":142,"yesterday_by_now":151,"same_day_last_week":120}},"goods_in":{"arriving":3,"received":2,"checked":1,"booking_in":4,"returns_to_process":5},"inventory":{"number_current_org_stocks":4812,"below_minimum_stock":17,"negative_stocks":3,"number_abnormalities":0}}}
    """#

    nonisolated private static let goodsInOnly = #"""
    {"data":{"generated_at":"2026-11-18T15:00:00+00:00","goods_out":null,"goods_in":{"arriving":"3","received":2.0,"checked":1,"booking_in":0,"returns_to_process":null},"inventory":null}}
    """#

    nonisolated private static let olderServer = #"""
    {"data":{"goods_out":{"unassigned":4,"handling":1,"handling_blocked":0,"packed":2,"in_warehouse":7},"goods_in":[],"inventory":{"number_current_org_stocks":1,"below_minimum_stock":0,"negative_stocks":0,"number_abnormalities":2}}}
    """#

    private static let returns = #"{"data":[{"id":635,"reference":"GB591132-r2","type":"cancellation","type_label":"Cancellation","state":"received","state_label":"Received","created_at":"2026-10-23T07:44:16+00:00","customer":{"name":"Thimble and Wick"},"delivery_note":null,"number_items":2,"number_handled":0,"handler":null}],"meta":{"current_page":1,"last_page":1,"per_page":150,"total":1},"counts":{"received":1,"returning":0,"returned":4,"done":7,"cancelled":0,"all":12,"to_process":1}}"#

    private func client(_ responses: [String: String]) -> (RecordingTransport, APIClient) {
        let transport = RecordingTransport(responses: responses)
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
        return (transport, client)
    }

    private func decodeDashboard(_ json: String) throws -> MobileWarehouseDashboard {
        try decode(DataEnvelope<MobileWarehouseDashboard>.self, json).value
    }

    @Test func dashboardReadsEveryBlockTheServerSends() async throws {
        let (transport, client) = client(["mobile/warehouses/7/dashboard": Self.full])

        let dashboard = try await client.fetchWarehouseDashboard(warehouseID: 7)

        #expect(transport.requests.first?.url?.path == "/app/api/mobile/warehouses/7/dashboard")
        #expect(dashboard.generatedAt == "2026-11-18T15:00:00+00:00")
        #expect(dashboard.goodsOut == MobileWarehouseDashboard.GoodsOut(
            unassigned: 47,
            handling: 17,
            handlingBlocked: 2,
            picked: 9,
            packed: 26,
            inWarehouse: 101,
            dispatchedToday: MobileWarehouseDashboard.DispatchedToday(count: 142, yesterdayByNow: 151, sameDayLastWeek: 120)
        ))
        #expect(dashboard.goodsIn == MobileWarehouseDashboard.GoodsIn(arriving: 3, received: 2, checked: 1, bookingIn: 4, returnsToProcess: 5))
        #expect(dashboard.inventory == MobileWarehouseDashboard.Inventory(numberCurrentOrgStocks: 4812, belowMinimumStock: 17, negativeStocks: 3, numberAbnormalities: 0))
    }

    @Test func blocksWithoutTheRightAreHidden() throws {
        let dashboard = try decodeDashboard(Self.goodsInOnly)

        #expect(dashboard.goodsOut == nil)
        #expect(dashboard.inventory == nil)
        let goodsIn = try #require(dashboard.goodsIn)
        #expect(goodsIn.arriving == 3)
        #expect(goodsIn.received == 2)
        #expect(goodsIn.returnsToProcess == nil)
        #expect(WarehouseDashboardText.returnsToProcess(goodsIn) == nil)
        #expect(WarehouseDashboardText.tiles(goodsIn).map(\.value) == [3, 2, 1, 0])
    }

    @Test func olderServerHidesToPackAndTheDispatchedLine() throws {
        let dashboard = try decodeDashboard(Self.olderServer)

        let goodsOut = try #require(dashboard.goodsOut)
        #expect(goodsOut.picked == nil)
        #expect(WarehouseDashboardText.tiles(goodsOut).map(\.label) == ["To do", "Picking", "Waiting", "Packed"])
        #expect(WarehouseDashboardText.dispatched(goodsOut) == nil)
        #expect(dashboard.goodsIn == nil)
        #expect(dashboard.inventory?.numberAbnormalities == 2)
    }

    @Test func blocksReadLikeAndroid() throws {
        let dashboard = try decodeDashboard(Self.full)
        let goodsOut = try #require(dashboard.goodsOut)
        let goodsIn = try #require(dashboard.goodsIn)
        let inventory = try #require(dashboard.inventory)

        #expect(WarehouseDashboardText.goodsOutSubtitle(goodsOut) == "Dispatching backlog · 101 in the warehouse")
        #expect(WarehouseDashboardText.dispatched(goodsOut)?.value == "142 today · 151 yesterday by now")
        #expect(WarehouseDashboardText.inventorySubtitle(inventory) == "4,812 current SKOs")
        #expect(WarehouseDashboardText.returnsToProcess(goodsIn) == WarehouseDashboardFigure(label: "Returns to process", value: "5", link: .goodsIn(.returnsToProcess)))
        #expect(WarehouseDashboardText.tiles(goodsOut).map(\.label) == ["To do", "Picking", "Waiting", "To pack", "Packed"])
        #expect(WarehouseDashboardText.tiles(goodsOut).map(\.tone) == [.neutral, .info, .warn, .info, .info])
        #expect(WarehouseDashboardText.tiles(inventory).map(\.tone) == [.warn, .danger, .neutral])
        #expect(WarehouseDashboardText.columns(for: 5) == 3)
        #expect(WarehouseDashboardText.columns(for: 4) == 4)
        #expect(WarehouseDashboardText.columns(for: 3) == 3)

        var quiet = goodsOut
        quiet.handlingBlocked = 0
        #expect(WarehouseDashboardText.tiles(quiet).first(where: { $0.id == "handling_blocked" })?.tone == .neutral)
    }

    @Test func eachGoodsOutTileOpensTheDeliveryNotesInTheBucketItCounts() throws {
        let goodsOut = try #require(try decodeDashboard(Self.full).goodsOut)
        let tiles = WarehouseDashboardText.tiles(goodsOut)

        #expect(tiles.map(\.link) == [
            .goodsOut(.deliveryNotesIn(state: .todo)),
            .goodsOut(.deliveryNotesIn(state: .picking)),
            .goodsOut(.deliveryNotesIn(state: .waiting)),
            .goodsOut(.deliveryNotesIn(state: .picked)),
            .goodsOut(.deliveryNotesIn(state: .packed))
        ])
        for tile in tiles {
            guard case .goodsOut(.deliveryNotesIn(let state)) = tile.link else {
                Issue.record("\(tile.id) does not open the delivery notes")
                continue
            }
            let request = GoodsOutMapper.listRequest(for: GoodsOutListQuery(tab: state), page: 1, today: Date())
            #expect(request.bucket == tile.id)
            #expect(request.channel == "all")
            #expect(request.search == nil)
            #expect(request.picker == nil)
        }
    }

    @Test func eachGoodsInTileOpensTheStockDeliveriesInTheStateItCounts() throws {
        let goodsIn = try #require(try decodeDashboard(Self.full).goodsIn)
        let states = ["arriving": "dispatched", "received": "received", "checked": "checked", "booking_in": "booking_in"]

        for tile in WarehouseDashboardText.tiles(goodsIn) {
            guard case .goodsIn(.stockDeliveriesIn(let state)) = tile.link else {
                Issue.record("\(tile.id) does not open the stock deliveries")
                continue
            }
            let query = GoodsInListRequest(scope: .state(state), filter: GoodsInDeliveryFilter()).query(page: 1)
            #expect(query.state == states[tile.id])
            #expect(query.source == nil)
        }
        #expect(WarehouseDashboardText.tiles(goodsIn).map(\.id) == ["arriving", "received", "checked", "booking_in"])
    }

    @Test func openingAStockDeliveryStateClearsTheFilters() {
        let store = GoodsInStore(deliveries: [], returns: [])
        store.applyFilter(GoodsInDeliveryFilter(sources: [.agent]))

        store.showScope(.state(.checked))

        #expect(store.filter == GoodsInDeliveryFilter())
        #expect(store.listScope == .state(.checked))
        #expect(store.currentListRequest.query(page: 1).state == "checked")
    }

    @Test func returnsToProcessAsksTheServerForThoseReturnsOnly() async throws {
        let (transport, client) = client(["mobile/warehouses/1/returns": Self.returns])
        let store = GoodsInStore(deliveries: [], returns: [])
        store.prepareRemote(warehouseID: 1, apiClient: client)

        await store.reloadReturns(scope: .toProcess, apiClient: client)

        let url = try #require(transport.requests.last?.url)
        let query = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems ?? []
        #expect(query.contains(URLQueryItem(name: "state", value: "to_process")))
        #expect(store.visibleReturns(in: .toProcess).map(\.reference) == ["GB591132-r2"])
        #expect(store.visibleReturns(in: .all).isEmpty)
    }

    @Test func sampleReturnsToProcessAreReceivedOrReturning() {
        let store = GoodsInStore()

        let toProcess = store.visibleReturns(in: .toProcess)

        #expect(toProcess.allSatisfy { $0.state == .received || $0.state == .returning })
        #expect(store.visibleReturns(in: .all).count == store.returns.count)
    }

    @Test func eachInventoryTileOpensTheSkosInTheBucketItCounts() throws {
        let inventory = try #require(try decodeDashboard(Self.full).inventory)
        let tiles = WarehouseDashboardText.tiles(inventory)

        #expect(tiles.map(\.id) == ["below_minimum", "negative", "abnormality"])
        for tile in tiles {
            guard case .inventory(.skosIn(let scope)) = tile.link else {
                Issue.record("\(tile.id) does not open the SKO list")
                continue
            }
            let query = InventorySkoListRequest(warehouseID: 1, scope: scope, search: "", filter: InventoryLiveSkoFilter()).query(page: 1, perPage: 50)
            #expect(query.bucket == tile.id)
            #expect(query.familyID == nil)
        }
    }

    @Test func belowMinimumAndNegativeListsKeepTheirCountsAndTitles() throws {
        let counts = try decode(MobileOrgStocksPage.Counts.self, #"{"current":6979,"discontinued":12,"abnormality":2,"below_minimum":17,"negative":3,"all":7100}"#)

        #expect(InventorySkoScope.belowMinimum.count(in: counts) == 17)
        #expect(InventorySkoScope.negative.count(in: counts) == 3)
        #expect(InventorySkoScope.belowMinimum.listTitle == "SKOs below minimum")
        #expect(InventorySkoScope.negative.emptyTitle == "No SKOs with negative stock")
        #expect(InventorySkoScope.chipScopes == [.current, .discontinued, .abnormalities, .all])

        let older = try decode(MobileOrgStocksPage.Counts.self, #"{"current":1,"discontinued":0,"abnormality":0,"all":1}"#)
        #expect(InventorySkoScope.negative.count(in: older) == 0)
    }

    @Test func storeLoadsTheSelectedWarehouse() async throws {
        let remote = try decodeDashboard(Self.full)
        let store = WarehouseDashboardStore(service: WarehouseDashboardService(dashboard: { id in
            guard id == 7 else { throw APIError.http(statusCode: 404) }
            return remote
        }))

        #expect(store.isShowingSkeleton)
        await store.load(warehouseID: 7)

        #expect(store.dashboard == remote)
        #expect(store.loadedWarehouseID == 7)
        #expect(store.loadError == nil)
        #expect(!store.isShowingSkeleton)
        #expect(!store.isLoading)
    }

    @Test func failedRefreshKeepsTheFiguresAndRetryRecovers() async throws {
        let remote = try decodeDashboard(Self.full)
        let attempts = Attempts()
        let store = WarehouseDashboardStore(service: WarehouseDashboardService(dashboard: { _ in
            if await attempts.next() == 2 { throw APIError.offline }
            return remote
        }))

        await store.load(warehouseID: 7)
        await store.load(warehouseID: 7)

        #expect(store.dashboard == remote)
        #expect(store.loadError == "This needs internet. Check Wi-Fi and try again.")

        await store.load(warehouseID: 7)

        #expect(store.loadError == nil)
        #expect(await attempts.count == 3)
    }

    @Test func changingWarehouseDropsTheOldFigures() async throws {
        let remote = try decodeDashboard(Self.full)
        let store = WarehouseDashboardStore(service: WarehouseDashboardService(dashboard: { id in
            guard id == 7 else { throw APIError.offline }
            return remote
        }))

        await store.load(warehouseID: 7)
        await store.load(warehouseID: 8)

        #expect(store.dashboard == nil)
        #expect(store.loadedWarehouseID == 8)
        #expect(store.loadError != nil)
        #expect(!store.isShowingSkeleton)

        await store.load(warehouseID: nil)

        #expect(store.dashboard == nil)
        #expect(store.loadedWarehouseID == nil)
        #expect(store.loadError == nil)
    }
}
