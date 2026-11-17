import Foundation
import Testing
@testable import Aiku

@MainActor
struct GoodsInAPITests {
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

        private func record(_ request: URLRequest) {
            lock.withLock { recorded.append(request) }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            record(request)
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            guard let body = responses[path] else {
                return (Data(), HTTPURLResponse(url: request.url!, statusCode: 404, httpVersion: nil, headerFields: nil)!)
            }
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
        }
    }

    private static let detail = #"{"id":36604,"reference":"AR27075","agent_reference":null,"state":"received","state_label":"Received","supplier":{"type":"OrgPartner","code":"Aroma","name":"AW Aromatics Ltd"},"source":"partners","dispatched_at":"2026-11-11T10:30:02+00:00","received_at":"2026-11-12T08:00:00+00:00","estimated_receiving_date":null,"delivery":{"type":null,"type_label":"Pallet","container_size":null,"incoterm":null,"port_of_export":null,"port_of_import":null,"shipper_reference":null,"tracking_number":"TRK1","container_number":null,"shipping_agent":null},"purchase_orders":[{"id":5,"reference":"PO-1"}],"summary":{"number_items":1},"items":[{"id":177817,"state":"received","state_label":"Received","supplier_product":null,"purchase_order_reference":null,"is_new_org_stock":false,"sko":{"id":39361,"code":"ABB-07a","name":"Stress Buster Potion Bath Ball","packed_in":8,"barcode":"5055796547838","image_url":null},"sko_expected":33,"sko_expected_label":"33","sko_checked":null,"sko_checked_label":null,"sko_placed":0,"sko_placed_label":"0","sko_to_place":0,"sko_to_place_label":"0","has_picking_location":true,"locations":[{"location_org_stock_id":77204,"location":{"id":7799,"code":"67A6"},"type":"picking","quantity":2.75,"quantity_label":"2 6/8","is_default_wholesale":true,"is_default_dropshipping":true}],"placements":[]}],"checklist":{"sections":[{"key":"arrival","title":"Arrival","subtitle":null,"checks":[{"key":"seal","label":"Seal intact","detail":"Check the seal","severity":"high","result":null,"checked_by":null,"checked_at":null}]}],"total":1,"checked":0,"failed":0,"incident_types":[]},"available_actions":[{"key":"check","label":"Check"},{"key":"place","label":"Place"}]}"#

    private static let returnDetail = #"{"id":635,"reference":"GB591132-r2","type":"cancellation","type_label":"Cancellation","state":"received","state_label":"Received","customer":{"name":"Thimble and Wick"},"order":{"reference":"GB591132"},"delivery_note":null,"handler":null,"timeline":[],"items":[{"id":6427,"state":"unassigned","is_handled":false,"sko":{"id":85405,"code":"GemTG-20","name":"Gemstone Dome","packed_in":1,"barcode":null,"image_url":null},"expected":1,"expected_label":"1","returned":0,"returned_label":"0","damaged":0,"damaged_label":"0","not_returned":0,"not_returned_label":"0","remaining":1,"remaining_label":"1","puts":[],"locations":[{"location_org_stock_id":157240,"location":{"id":1594,"code":"27A12"},"type":"picking","quantity":9,"quantity_label":"9"}]}],"available_actions":[{"key":"start","label":"Start"}]}"#

    private static let responses: [String: String] = [
        "mobile/warehouses/1/goods-in-hub": #"{"data":[{"key":"stock_deliveries","label":"Stock Deliveries","arriving":1,"received":2,"checked":0,"booking_in":1,"booked_in_7d":4,"placed_7d":0},{"key":"returns","label":"Returns","arriving":null,"received":14,"checked":null,"booking_in":21,"booked_in_7d":null,"placed_7d":null}]}"#,
        "mobile/warehouses/1/stock-deliveries": #"{"data":[{"id":36604,"reference":"AR27075","agent_reference":null,"supplier":{"type":"OrgPartner","code":"Aroma","name":"AW Aromatics Ltd"},"source":"partners","state":"dispatched","state_label":"Dispatched","dispatched_at":"2026-11-11T10:30:02+00:00","received_at":null,"estimated_receiving_date":null,"number_items":39,"number_checked":0,"number_placed":0}],"meta":{"current_page":1,"last_page":6,"per_page":50,"total":12},"counts":{"dispatched":8,"received":2,"checked":1,"booking_in":1,"booked_in":3,"placed":11739,"not_received":1,"cancelled":282,"active":12}}"#,
        "mobile/warehouses/1/stock-deliveries/36604": #"{"data":"# + detail + "}",
        "mobile/warehouses/1/stock-deliveries/36604/items/177817/check": #"{"data":"# + detail + #","result":{"status":"done","message":"Checked"}}"#,
        "mobile/warehouses/1/stock-deliveries/36604/items/177817/place": #"{"data":"# + detail + #","result":{"status":"done","message":"Placed"}}"#,
        "mobile/warehouses/1/returns": #"{"data":[{"id":635,"reference":"GB591132-r2","type":"cancellation","type_label":"Cancellation","state":"received","state_label":"Received","created_at":"2026-10-23T07:44:16+00:00","customer":{"name":"Thimble and Wick"},"delivery_note":null,"number_items":2,"number_handled":0,"handler":null}],"meta":{"current_page":1,"last_page":1,"per_page":150,"total":1},"counts":{"received":14,"returning":21,"returned":99,"done":112,"cancelled":15,"all":261}}"#,
        "mobile/warehouses/1/returns/635": #"{"data":"# + returnDetail + "}",
        "mobile/warehouses/1/returns/635/start": #"{"data":"# + returnDetail.replacingOccurrences(of: #""state":"received""#, with: #""state":"returning""#) + #","result":{"status":"done","message":"Started"}}"#,
        "mobile/warehouses/1/returns/635/items/6427/returned": #"{"data":"# + returnDetail + #","result":{"status":"done","message":"Returned"}}"#,
    ]

    private func setUp() -> (GoodsInStore, RecordingTransport, APIClient) {
        let transport = RecordingTransport(responses: Self.responses)
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
        let store = GoodsInStore(deliveries: [], returns: [])
        store.prepareRemote(warehouseID: 1, apiClient: client)
        return (store, transport, client)
    }

    private func body(_ request: URLRequest?) throws -> [String: Any] {
        let data = try #require(request?.httpBody)
        return try #require(try JSONSerialization.jsonObject(with: data) as? [String: Any])
    }

    private func waitFor(_ condition: () -> Bool) async {
        for _ in 0..<50 where !condition() {
            try? await Task.sleep(for: .milliseconds(20))
        }
    }

    @Test func hubFillsTheBacklogCards() async {
        let (store, _, client) = setUp()

        await store.loadHub(apiClient: client)

        let deliveries = store.backlogCounts(for: [.supplier, .production])
        #expect(deliveries.arriving == 1)
        #expect(deliveries.received == 2)
        #expect(deliveries.bookedInLastWeek == 4)
        #expect(store.returnsBacklog.received == 14)
    }

    @Test func listUsesTheServerStateBucketAndCounts() async throws {
        let (store, transport, client) = setUp()
        store.listScope = .state(.arriving)

        await store.reloadList(apiClient: client)

        let items = URLComponents(url: try #require(transport.requests.last?.url), resolvingAgainstBaseURL: false)?.queryItems ?? []
        #expect(items.contains(URLQueryItem(name: "state", value: "dispatched")))
        #expect(store.count(in: .state(.arriving)) == 8)
        #expect(store.count(in: .state(.checked)) == 2)
        #expect(store.hasMorePages)
        let row = try #require(store.listedDeliveries().first)
        #expect(row.reference == "AR27075")
        #expect(row.state == .arriving)
        #expect(row.source == .partner)
        #expect(row.listSubtitle().contains("39 items"))
    }

    @Test func detailMapsItemsChecklistAndLocations() async throws {
        let (store, _, client) = setUp()
        store.listScope = .all
        await store.reloadList(apiClient: client)

        await store.loadDelivery("AR27075", apiClient: client)

        let delivery = store.delivery("AR27075")
        #expect(delivery.isDetailLoaded)
        #expect(delivery.state == .received)
        #expect(delivery.purchaseOrders == "Purchase orders PO-1")
        #expect(delivery.deliveryFacts.contains(GoodsInFact("Tracking", "TRK1")))
        #expect(delivery.checks.first?.id == "seal")
        let item = try #require(delivery.items.first)
        #expect(item.sko == "ABB-07a")
        #expect(item.delivered == 33)
        #expect(item.checked == nil)
        #expect(item.pickingLocation == "67A6")
        #expect(item.locationLinks["67A6"] == 77204)
    }

    @Test func serverActionsDecideWhatTheDeliveryOffers() async {
        let (store, _, client) = setUp()
        store.listScope = .all
        await store.reloadList(apiClient: client)
        await store.loadDelivery("AR27075", apiClient: client)

        let actions = GoodsInDeliveryAction.available(for: store.delivery("AR27075"), rights: .full)

        #expect(actions == [.checkItems])
    }

    @Test func checkedItemsCanBePlacedBeforeBookingInLikeAndroid() async {
        let (store, _, client) = setUp()
        store.listScope = .all
        await store.reloadList(apiClient: client)
        await store.loadDelivery("AR27075", apiClient: client)

        store.setChecked("AR27075", sko: "ABB-07a", quantity: 30)
        let delivery = store.delivery("AR27075")

        #expect(GoodsInDeliveryAction.available(for: delivery, rights: .full).contains(.placeItems))
        #expect(delivery.nextItemToPlace?.sko == "ABB-07a")
        #expect(delivery.state == .received)
    }

    @Test func checkingAnItemSendsTheCountedSkos() async throws {
        let (store, transport, client) = setUp()
        await store.reloadList(apiClient: client)
        await store.loadDelivery("AR27075", apiClient: client)

        store.setChecked("AR27075", sko: "ABB-07a", quantity: 30)
        await waitFor { transport.requests.contains { $0.url?.path.hasSuffix("/check") == true } }

        let request = transport.requests.last { $0.url?.path.hasSuffix("/items/177817/check") == true }
        #expect(request?.httpMethod == "POST")
        #expect(try body(request)["sko_quantity"] as? Double == 30)
    }

    @Test func placingInAKnownLocationSendsItsLinkAndARequestID() async throws {
        let (store, transport, client) = setUp()
        await store.reloadList(apiClient: client)
        await store.loadDelivery("AR27075", apiClient: client)
        store.setChecked("AR27075", sko: "ABB-07a", quantity: 33)
        await waitFor { transport.requests.contains { $0.url?.path.hasSuffix("/check") == true } }
        await store.loadDelivery("AR27075", apiClient: client)
        if let index = store.deliveries.firstIndex(where: { $0.reference == "AR27075" }) {
            store.deliveries[index].items[0].checked = 33
        }

        store.place("AR27075", sko: "ABB-07a", quantity: 10, in: "67a6")
        await waitFor { transport.requests.contains { $0.url?.path.hasSuffix("/place") == true } }

        let sent = try body(transport.requests.last { $0.url?.path.hasSuffix("/items/177817/place") == true })
        #expect(sent["quantity"] as? Double == 10)
        #expect(sent["location_org_stock_id"] as? Int == 77204)
        #expect(sent["location_id"] == nil)
        #expect(UUID(uuidString: sent["client_request_id"] as? String ?? "") != nil)
    }

    @Test func returningAnItemStartsTheReturnFirst() async throws {
        let (store, transport, client) = setUp()
        await store.reloadReturns(apiClient: client)
        #expect(store.listedReturns.first?.listSubtitle == "Thimble and Wick · 2 items")
        await store.loadReturn("GB591132-r2", apiClient: client)

        store.processReturnItem("GB591132-r2", sko: "GemTG-20", outcome: .toLocation("27A12"))
        await waitFor { transport.requests.contains { $0.url?.path.hasSuffix("/returned") == true } }

        let paths = transport.requests.map { $0.url?.path ?? "" }
        let start = try #require(paths.firstIndex { $0.hasSuffix("/returns/635/start") })
        let returned = try #require(paths.firstIndex { $0.hasSuffix("/items/6427/returned") })
        #expect(start < returned)
        #expect(try body(transport.requests[returned])["location_org_stock_id"] as? Int == 157240)
    }

    @Test func actionsWithoutAnEndpointSayTheyAreDoneOnTheWeb() {
        let (store, _, _) = setUp()

        store.cancel("AR27075")

        #expect(store.actionError == "Cancelling a stock delivery is done on the web for now.")
    }

    @Test func signingOutGoesBackToSampleData() {
        let (store, _, _) = setUp()

        store.resetToSample()

        #expect(!store.isRemote)
        #expect(!store.deliveries.isEmpty)
    }

    @Test func fractionalSkosShowAsThePackAndLooseUnitsTheWebShows() {
        #expect(GoodsInQuantity.text(2.75, packedIn: 8) == "2 6/8")
        #expect(GoodsInQuantity.text(0.25, packedIn: 8) == "2/8")
        #expect(GoodsInQuantity.text(33, packedIn: 8) == "33")
        #expect(GoodsInQuantity.text(1.5) == "1.5")
        #expect(GoodsInQuantity.text(2.3333, packedIn: 8) == "2.3333")
    }

    @Test func fractionalExpectedQuantityIsKeptAndSentWhole() async throws {
        let (store, transport, client) = setUp()
        await store.reloadList(apiClient: client)
        await store.loadDelivery("AR27075", apiClient: client)
        if let index = store.deliveries.firstIndex(where: { $0.reference == "AR27075" }) {
            store.deliveries[index].items[0] = GoodsInItem(sko: "ABB-07a", name: "", delivered: 2.75, remoteID: 177817, packedIn: 8)
        }
        let item = try #require(store.delivery("AR27075").item("ABB-07a"))

        #expect(item.text(item.delivered) == "2 6/8")

        store.setChecked("AR27075", sko: "ABB-07a", quantity: item.delivered)
        await waitFor { transport.requests.contains { $0.url?.path.hasSuffix("/check") == true } }

        let sent = try body(transport.requests.last { $0.url?.path.hasSuffix("/items/177817/check") == true })
        #expect(sent["sko_quantity"] as? Double == 2.75)
    }

    @Test func fullyCheckedFractionalItemIsExactNotUnder() {
        let item = GoodsInItem(sko: "ABB-07a", name: "", delivered: 2.75, checked: 2.75, packedIn: 8)

        #expect(item.checkStatus == .exact)
        #expect(item.leftToCheck == 0)
        #expect(item.leftToPlace == 2.75)
    }

    @Test func placingAFractionalRemainderSendsItExactly() async throws {
        let (store, transport, client) = setUp()
        await store.reloadList(apiClient: client)
        await store.loadDelivery("AR27075", apiClient: client)
        if let index = store.deliveries.firstIndex(where: { $0.reference == "AR27075" }) {
            var item = GoodsInItem(sko: "ABB-07a", name: "", delivered: 2.75, checked: 2.75, remoteID: 177817, packedIn: 8)
            item.locationLinks = ["67A6": 77204]
            store.deliveries[index].items[0] = item
        }

        let placed = store.place("AR27075", sko: "ABB-07a", quantity: 2.75, in: "67A6")
        await waitFor { transport.requests.contains { $0.url?.path.hasSuffix("/place") == true } }

        #expect(placed == 2.75)
        let sent = try body(transport.requests.last { $0.url?.path.hasSuffix("/items/177817/place") == true })
        #expect(sent["quantity"] as? Double == 2.75)
    }

    @Test func detailKeepsTheFractionalQuantitiesFromTheServer() async throws {
        let (store, _, client) = setUp()
        await store.reloadList(apiClient: client)
        await store.loadDelivery("AR27075", apiClient: client)

        let item = try #require(store.delivery("AR27075").items.first)
        #expect(item.packedIn == 8)
        #expect(item.locationOptions.first?.detail == "Picking · 2 6/8 in location")
    }
}

