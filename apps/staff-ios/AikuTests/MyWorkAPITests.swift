import Foundation
import Testing
@testable import Aiku

@MainActor
struct MyWorkAPITests {
    private final class RecordingTransport: HTTPTransport, @unchecked Sendable {
        let responses: [String: (Int, String)]
        private let lock = NSLock()
        private var recorded: [URLRequest] = []

        init(responses: [String: (Int, String)]) {
            self.responses = responses
        }

        var requests: [URLRequest] {
            lock.withLock { recorded }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            lock.withLock { recorded.append(request) }
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            let (status, body) = responses[path] ?? (404, "")
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
        }
    }

    private static let myWork = #"""
    {"data":[
      {"kind":"delivery_note","id":101,"reference":"GB592347","state":"handling_blocked","state_label":"Waiting","step":"waiting","step_label":"Waiting","items":48,"customer":"AW Artisan S.L.","trolleys":["Trolley-1",null," "],"since":"2026-11-18T08:00:00+00:00"},
      {"kind":"picking_session","id":42,"reference":"PS-0042","state":"handling","state_label":"Picking","step":"picking","step_label":"Picking","items":3,"customer":null,"trolleys":[],"since":null},
      {"kind":"return","id":635,"reference":"RET-635","state":"received","step":"to_start","step_label":"To start","items":"2"},
      {"kind":"pallet_return","id":6385,"reference":"412170-awf-r1591","state":"picking","step":"picking","step_label":"Picking","type_group":"items","items":1},
      {"kind":"stock_audit","id":9,"reference":"ignored"},
      {"kind":"delivery_note","reference":"no id"}
    ],"counts":{"total":4,"delivery_notes":1,"picking_sessions":1,"returns":1,"pallet_returns":1}}
    """#

    private func client(_ responses: [String: (Int, String)]) -> (RecordingTransport, APIClient) {
        let transport = RecordingTransport(responses: responses)
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
        return (transport, client)
    }

    @Test func myWorkKeepsKnownRowsInServerOrderAndSkipsTheRest() async throws {
        let (transport, client) = client(["mobile/warehouses/1/my-work": (200, Self.myWork)])

        let page = try await client.fetchMyWork(warehouseID: 1)

        #expect(transport.requests.first?.url?.path == "/app/api/mobile/warehouses/1/my-work")
        #expect(page.rows.map(\.kind) == [.deliveryNote, .pickingSession, .customerReturn, .palletReturn])
        #expect(page.rows.map(\.reference) == ["GB592347", "PS-0042", "RET-635", "412170-awf-r1591"])
        #expect(page.rows[0].trolleys == ["Trolley-1"])
        #expect(page.rows[2].items == 2)
        #expect(page.counts?.total == 4)
    }

    @Test func rowsReadLikeAndroid() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let page = try decoder.decode(MobileMyWorkPage.self, from: Data(Self.myWork.utf8))
        let now = ISO8601DateFormatter().date(from: "2026-11-18T10:30:00Z")!

        #expect(MyWorkText.subtitle(page.rows[0], now: now) == "Delivery note · 48 lines · AW Artisan S.L. · Trolley Trolley-1 · 2 hours ago")
        #expect(MyWorkText.subtitle(page.rows[1], now: now) == "Picking session · 3 delivery notes")
        #expect(MyWorkText.subtitle(page.rows[2], now: now) == "Customer return · 2 items")
        #expect(MyWorkText.subtitle(page.rows[3], now: now) == "Fulfilment return · 1 stored item")
        #expect(MyWorkText.chip(page.rows[2]) == "To start")
        #expect(MyWorkText.tone("waiting") == .warn)
        #expect(MyWorkText.tone("to_pick") == .info)
        #expect(MyWorkText.tone("picking") == .neutral)
        #expect(MyWorkText.since("2026-11-18T10:29:40Z", now: now) == "just now")
        #expect(MyWorkText.since("2026-11-18T10:25:00.000000Z", now: now) == "5 min ago")
        #expect(MyWorkText.since("2026-11-16T10:00:00Z", now: now) == "2 days ago")
    }

    @Test func feedLoadsAndReportsEmptyAndFailure() async {
        let feed = MyWorkFeed()
        let (_, empty) = client(["mobile/warehouses/1/my-work": (200, #"{"data":[],"counts":{"total":0}}"#)])
        await feed.reload(warehouseID: 1, apiClient: empty)
        #expect(feed.isEmpty)

        let (_, failing) = client(["mobile/warehouses/1/my-work": (500, "")])
        await feed.reload(warehouseID: 1, apiClient: failing)
        #expect(feed.loadError != nil)
        #expect(!feed.isEmpty)
    }

    @Test func badgesFollowTheWarehouseAndSurviveAnOutage() async {
        let store = WorkBadgesStore()
        let (transport, ok) = client(["mobile/warehouses/1/badges": (200, #"{"data":{"my_work":4,"goods_out_unassigned":"12"}}"#)])

        await store.refresh(warehouseID: 1, apiClient: ok)
        #expect(transport.requests.last?.url?.path == "/app/api/mobile/warehouses/1/badges")
        #expect(store.myWorkCount == 4)
        #expect(store.goodsOutUnassignedCount == 12)

        let (_, down) = client(["mobile/warehouses/1/badges": (503, "")])
        await store.refresh(warehouseID: 1, apiClient: down)
        #expect(store.myWorkCount == 4)

        let (_, forbidden) = client(["mobile/warehouses/1/badges": (403, "")])
        await store.refresh(warehouseID: 1, apiClient: forbidden)
        #expect(store.myWorkCount == 0)

        await store.refresh(warehouseID: 1, apiClient: ok)
        await store.refresh(warehouseID: 2, apiClient: ok)
        #expect(store.myWorkCount == 0)

        store.reset()
        #expect(store.goodsOutUnassignedCount == 0)
    }

    @Test func unassignedBadgeShowsOnTheGoodsOutShortcutAndBacklog() {
        let modules = ModuleAccess.everything

        #expect(ShellHomeContent.shortcuts(for: modules).first { $0.id == "goodsOut" }?.badge == nil)
        #expect(ShellHomeContent.shortcuts(for: modules, goodsOutUnassigned: 7).first { $0.id == "goodsOut" }?.badge == 7)

        let backlog = { (count: Int) in
            ShellMenuCatalog.groups(for: modules, goodsOutUnassigned: count).flatMap(\.items).first { $0.id == "goodsOut.backlog" }?.badge
        }
        #expect(backlog(0) == nil)
        #expect(backlog(3) == .pill(Chip("3 unassigned", .warn)))
    }
}
