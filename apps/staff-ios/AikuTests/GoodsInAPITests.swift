import Foundation
import Testing
@testable import Aiku

@MainActor
struct GoodsInAPITests {
    private final class RecordingTransport: HTTPTransport, @unchecked Sendable {
        let responses: [String: String]
        private let lock = NSLock()
        private var recorded: [URLRequest] = []
        private var statuses: [String: Int] = [:]
        private var bodies: [String: String] = [:]

        init(responses: [String: String]) {
            self.responses = responses
        }

        var requests: [URLRequest] {
            lock.withLock { recorded }
        }

        func fail(_ path: String, status: Int, body: String = "{}") {
            lock.withLock {
                statuses[path] = status
                bodies[path] = body
            }
        }

        func heal(_ path: String) {
            lock.withLock {
                statuses[path] = nil
                bodies[path] = nil
            }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            let failure: (Int, String)? = lock.withLock {
                recorded.append(request)
                return statuses[path].map { ($0, bodies[path] ?? "{}") }
            }
            if let failure {
                return (Data(failure.1.utf8), HTTPURLResponse(url: request.url!, statusCode: failure.0, httpVersion: nil, headerFields: nil)!)
            }
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
        "mobile/warehouses/1/stock-deliveries/36604/items/177817/place-all": #"{"data":"# + detail + #","result":{"status":"done","message":"Placed"}}"#,
        "mobile/warehouses/1/stock-deliveries/36604/check-all": #"{"data":"# + detail + #","result":{"status":"done","message":"1 item checked as expected."}}"#,
        "mobile/warehouses/1/stock-deliveries/36604/checklist": #"{"data":"# + detail + #","result":{"status":"done","message":"Check failed"}}"#,
        "mobile/warehouses/1/returns/635/puts/91": #"{"data":"# + returnDetail + #","result":{"status":"done","message":"Undone: Returned."}}"#,
        "mobile/warehouses/1/returns": #"{"data":[{"id":635,"reference":"GB591132-r2","type":"cancellation","type_label":"Cancellation","state":"received","state_label":"Received","created_at":"2026-10-23T07:44:16+00:00","customer":{"name":"Thimble and Wick"},"delivery_note":null,"number_items":2,"number_handled":0,"handler":null}],"meta":{"current_page":1,"last_page":1,"per_page":150,"total":1},"counts":{"received":14,"returning":21,"returned":99,"done":112,"cancelled":15,"all":261}}"#,
        "mobile/warehouses/1/returns/635": #"{"data":"# + returnDetail + "}",
        "mobile/warehouses/1/returns/635/start": #"{"data":"# + returnDetail.replacingOccurrences(of: #""state":"received""#, with: #""state":"returning""#) + #","result":{"status":"done","message":"Started"}}"#,
        "mobile/warehouses/1/returns/635/items/6427/returned": #"{"data":"# + returnDetail + #","result":{"status":"done","message":"Returned"}}"#,
    ]

    private func setUp() -> (GoodsInService, RecordingTransport) {
        let transport = RecordingTransport(responses: Self.responses)
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
        return (.live(client), transport)
    }

    private func loadedDelivery() async -> (GoodsInDeliveryFeed, RecordingTransport) {
        let (service, transport) = setUp()
        let feed = GoodsInDeliveryFeed(warehouseID: 1, deliveryID: 36604, service: service)
        await feed.load()
        return (feed, transport)
    }

    private func loadedReturn() async -> (GoodsInReturnFeed, RecordingTransport) {
        let (service, transport) = setUp()
        let feed = GoodsInReturnFeed(warehouseID: 1, returnID: 635, service: service)
        await feed.load()
        return (feed, transport)
    }

    private func body(_ request: URLRequest?) throws -> [String: Any] {
        let data = try #require(request?.httpBody)
        return try #require(try JSONSerialization.jsonObject(with: data) as? [String: Any])
    }

    private func last(_ transport: RecordingTransport, _ suffix: String) -> URLRequest? {
        transport.requests.last { $0.url?.path.hasSuffix(suffix) == true }
    }

    @Test func hubKeepsNullFiguresHiddenAndOpensTheListItCounts() async throws {
        let (service, _) = setUp()
        let feed = GoodsInBacklogFeed(warehouseID: 1, service: service)
        await feed.load()
        let rows = try #require(feed.rows)

        #expect(rows.map(\.key) == ["stock_deliveries", "returns"])
        #expect(rows[0].counts.map(\.figure) == [.arriving, .received, .checked, .bookingIn, .bookedInWeek, .placedWeek])
        #expect(rows[1].counts.map(\.figure) == [.received, .bookingIn])
        #expect(rows[0].source == .suppliers)
        #expect(GoodsInHubFigure.arriving.deliveryState == .dispatched)
        #expect(GoodsInHubFigure.bookedInWeek.deliveryState == .bookedIn)
        #expect(GoodsInHubFigure.bookingIn.returnState == .returning)
        #expect(GoodsInHubFigure.checked.returnState == nil)
    }

    @Test func hubRowsFollowTheWarehouseRights() async throws {
        let (service, _) = setUp()
        let feed = GoodsInBacklogFeed(warehouseID: 1, service: service)
        await feed.load()
        let rows = try #require(feed.rows)
        let returnsOnly = MobileWarehouse.Abilities(viewLocations: false, editLocations: false, viewReturns: true)

        #expect(rows.filter { $0.isVisible(returnsOnly) }.map(\.key) == ["returns"])
    }

    @Test func listAsksForTheChosenStateAndKeepsTheCounts() async throws {
        let (service, transport) = setUp()
        let feed = GoodsInDeliveriesFeed(warehouseID: 1, filter: GoodsInDeliveryFilter(state: .dispatched, source: .partners), service: service)

        await feed.start()

        let query = URLComponents(url: try #require(transport.requests.last?.url), resolvingAgainstBaseURL: false)?.queryItems ?? []
        #expect(query.contains(URLQueryItem(name: "state", value: "dispatched")))
        #expect(query.contains(URLQueryItem(name: "source", value: "partners")))
        #expect(feed.list.rows.map(\.reference) == ["AR27075"])
        #expect(feed.list.total == 12)
        #expect(feed.list.canLoadMore)
        #expect(feed.counts?.count("placed") == 11739)
        #expect(GoodsInRowText.delivery(feed.list.rows[0]) == "AW Aromatics Ltd · 39 items · Dispatched 11 Nov")
    }

    @Test func theFilterSheetShowsHowManyDeliveriesItWouldList() async throws {
        let (service, _) = setUp()
        let feed = GoodsInDeliveriesFeed(warehouseID: 1, filter: GoodsInDeliveryFilter(state: .dispatched), service: service)
        await feed.start()

        #expect(feed.total(for: feed.filter) == 12)

        var draft = feed.filter
        draft.source = .agents
        feed.previewTotal(draft)
        for _ in 0..<40 where feed.total(for: draft) == nil {
            try await Task.sleep(for: .milliseconds(25))
        }
        #expect(feed.total(for: draft) == 12)
    }

    @Test func detailDecodesItemsChecklistLocationsAndActions() async throws {
        let (feed, _) = await loadedDelivery()
        let delivery = try #require(feed.delivery)
        let item = try #require(delivery.item(177817))

        #expect(item.code == "ABB-07a")
        #expect(item.needsCheck)
        #expect(item.locations.first?.location.code == "67A6")
        #expect(item.locations.first?.quantityLabel == "2 6/8")
        #expect(delivery.checklist?.check("seal")?.label == "Seal intact")
        #expect(delivery.views == [.check, .place, .done, .checklist])
        #expect(delivery.defaultView == .check)
        #expect(delivery.canCheck && delivery.canPlace)
        #expect(!delivery.canRecordChecks)
    }

    @Test func checkingWaitsForAikuAndShowsItsMessage() async throws {
        let (feed, transport) = await loadedDelivery()

        await feed.check(itemID: 177817, skoQuantity: 30)

        let request = last(transport, "/items/177817/check")
        #expect(request?.httpMethod == "POST")
        #expect(try body(request)["sko_quantity"] as? Int == 30)
        #expect(feed.event == .done(message: "Checked", isNothingChanged: false, wasRaised: false))
        #expect(feed.delivery?.item(177817)?.skoChecked == nil)
        #expect(!feed.isWorking)
    }

    @Test func aRefusedCheckChangesNothingAndSaysWhy() async throws {
        let (feed, transport) = await loadedDelivery()
        transport.fail("mobile/warehouses/1/stock-deliveries/36604/items/177817/check", status: 422, body: #"{"message":"The quantity is too big.","errors":{"sko_quantity":["The quantity is too big."]}}"#)

        await feed.check(itemID: 177817, skoQuantity: 3000)

        #expect(feed.event == .refused(message: "The quantity is too big."))
        #expect(feed.writeErrors["sko_quantity"] == "The quantity is too big.")
        #expect(feed.delivery?.item(177817)?.skoChecked == nil)
    }

    @Test func aServerErrorIsReportedNotHidden() async throws {
        let (feed, transport) = await loadedDelivery()
        transport.fail("mobile/warehouses/1/stock-deliveries/36604/check-all", status: 500)

        await feed.checkAll()

        #expect(feed.event == .refused(message: GoodsInText.server))
        #expect(feed.delivery?.items.allSatisfy { $0.skoChecked == nil } == true)
    }

    @Test func placingInAKnownLocationSendsItsLinkAndARequestID() async throws {
        let (feed, transport) = await loadedDelivery()

        await feed.place(itemID: 177817, quantity: 10, target: .link(locationOrgStockID: 77204, code: "67A6"), setAsPickingLocation: false)

        let sent = try body(last(transport, "/items/177817/place"))
        #expect(sent["quantity"] as? Int == 10)
        #expect(sent["location_org_stock_id"] as? Int == 77204)
        #expect(sent["location_id"] == nil)
        #expect(sent["set_as_picking_location"] as? Bool == false)
        #expect(UUID(uuidString: sent["client_request_id"] as? String ?? "") != nil)
    }

    @Test func placingInAnotherLocationSendsTheLocationAndPlaceAllSendsNoQuantity() async throws {
        let (feed, transport) = await loadedDelivery()

        await feed.placeAll(itemID: 177817, target: .location(locationID: 1594, code: "27A12"), setAsPickingLocation: true)

        let sent = try body(last(transport, "/items/177817/place-all"))
        #expect(sent["quantity"] == nil)
        #expect(sent["location_id"] as? Int == 1594)
        #expect(sent["set_as_picking_location"] as? Bool == true)
    }

    @Test func anUnsurePlacementIsRetriedWithTheSameRequestID() async throws {
        let (feed, transport) = await loadedDelivery()
        let path = "mobile/warehouses/1/stock-deliveries/36604/items/177817/place"
        transport.fail(path, status: 502)

        await feed.place(itemID: 177817, quantity: 4, target: .link(locationOrgStockID: 77204, code: "67A6"), setAsPickingLocation: false)
        let first = try body(last(transport, "/items/177817/place"))["client_request_id"] as? String
        #expect(feed.unsurePlacement != nil)

        transport.heal(path)
        await feed.retryPlacement()
        let second = try body(last(transport, "/items/177817/place"))["client_request_id"] as? String

        #expect(first != nil)
        #expect(first == second)
        #expect(feed.unsurePlacement == nil)
    }

    @Test func aRefusedPlacementIsNotUnsure() async throws {
        let (feed, transport) = await loadedDelivery()
        transport.fail("mobile/warehouses/1/stock-deliveries/36604/items/177817/place", status: 403, body: #"{"message":"You can’t place here."}"#)

        await feed.place(itemID: 177817, quantity: 4, target: .link(locationOrgStockID: 77204, code: "67A6"), setAsPickingLocation: false)

        #expect(feed.unsurePlacement == nil)
        #expect(feed.event == .refused(message: "You can’t place here."))
    }

    @Test func failedCheckSendsTheNoteIncidentTypeAndQuantity() async throws {
        let (feed, transport) = await loadedDelivery()

        await feed.recordCheck(key: "seal", result: .failed, body: "  Broken  ", incidentType: "damaged", incidentQuantity: 2)

        let sent = try body(last(transport, "/checklist"))
        #expect(sent["key"] as? String == "seal")
        #expect(sent["result"] as? String == "failed")
        #expect(sent["body"] as? String == "Broken")
        #expect(sent["incident_type"] as? String == "damaged")
        #expect(sent["incident_quantity"] as? Int == 2)
    }

    @Test func passedCheckSendsNoIncident() async throws {
        let (feed, transport) = await loadedDelivery()

        await feed.recordCheck(key: "seal", result: .passed, body: "", incidentType: "damaged", incidentQuantity: 2)

        let sent = try body(last(transport, "/checklist"))
        #expect(sent["result"] as? String == "passed")
        #expect(sent["body"] is NSNull)
        #expect(sent["incident_type"] is NSNull)
        #expect(sent["incident_quantity"] is NSNull)
    }

    @Test func aScannedBarcodeOpensItsLine() async throws {
        let (feed, _) = await loadedDelivery()

        await feed.scanLine("5055796547838")
        #expect(feed.scanOutcome == .line(itemID: 177817))

        await feed.scanLine("abb-07A")
        #expect(feed.scanOutcome == .line(itemID: 177817))
    }

    @Test func scannedStockDeliveryOpensItsScreen() throws {
        let json = #"{"code":"AEU10106","type":"stock_delivery","sko":null,"location":null,"delivery_note":null,"stock_delivery":{"id":36573,"reference":"AEU10106","state":"checked","state_label":"Checked"}}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let result = try decoder.decode(MobileScanResult.self, from: Data(json.utf8))

        let target = try #require(ShellScanCatalog.target(from: result))

        #expect(target.kind == .stockDelivery)
        #expect(target.link == .goodsIn(.stockDelivery(id: 36573, reference: "AEU10106")))
    }

    @Test func startingAReturnWaitsForAiku() async throws {
        let (feed, transport) = await loadedReturn()

        await feed.start()

        #expect(last(transport, "/returns/635/start")?.httpMethod == "POST")
        #expect(feed.goodsReturn?.state == "returning")
        #expect(feed.event == .done(message: "Started", isNothingChanged: false, wasRaised: false))
    }

    @Test func backToStockSendsTheQuantityAndTheChosenLocation() async throws {
        let (feed, transport) = await loadedReturn()

        await feed.backToStock(itemID: 6427, quantity: 1, locationOrgStockID: 157240)

        let sent = try body(last(transport, "/items/6427/returned"))
        #expect(sent["quantity"] as? Int == 1)
        #expect(sent["location_org_stock_id"] as? Int == 157240)
        #expect(UUID(uuidString: sent["client_request_id"] as? String ?? "") != nil)
        #expect(transport.requests.contains { $0.url?.path.hasSuffix("/start") == true } == false)
    }

    @Test func undoingAReturnPutDeletesItOnTheServer() async throws {
        let (feed, transport) = await loadedReturn()

        await feed.undo(putID: 91)

        #expect(last(transport, "/returns/635/puts/91")?.httpMethod == "DELETE")
        #expect(feed.event == .done(message: "Undone: Returned.", isNothingChanged: false, wasRaised: false))
    }

    @Test func returnsListAsksForTheChosenState() async throws {
        let (service, transport) = setUp()
        let feed = GoodsInReturnsFeed(warehouseID: 1, state: .toProcess, service: service)

        await feed.start()

        let query = URLComponents(url: try #require(transport.requests.last?.url), resolvingAgainstBaseURL: false)?.queryItems ?? []
        #expect(query.contains(URLQueryItem(name: "state", value: "to_process")))
        #expect(feed.list.rows.map(\.reference) == ["GB591132-r2"])
        #expect(feed.counts?.count("returning") == 21)
        #expect(GoodsInRowText.goodsReturn(feed.list.rows[0]) == "Cancellation · Thimble and Wick · 2 items · 23 Oct")
    }
}
