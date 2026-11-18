import Foundation
import Testing
@testable import Aiku

@MainActor
struct FulfilmentReturnsAPITests {
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

    private static let itemsReturn = #"{"id":6385,"reference":"412170-awf-r1591","customer_reference":"PO-1","type":"stored_item","type_group":"items","type_label":"Stored items","platform":{"code":"shopify","name":"Shopify"},"state":"picking","state_label":"Picking","customer":{"id":7,"name":"Acme"},"is_collection":false,"collection_notes":null,"customer_notes":"Fragile","public_notes":null,"internal_notes":null,"picker":{"id":291,"name":"Andi"},"packer":null,"estimated_delivery_date":"2026-11-20","in_picking_session":false,"timeline":[{"state":"confirmed","label":"Confirmed","at":"2026-11-17T08:00:00+00:00"}],"counts":{"lines":1,"picked":0,"not_picked":0,"to_pick":1,"waiting_customer_service":0},"pallets":[],"stored_items":[{"stored_item":{"id":55,"reference":"SI-55","name":"Soap","barcode":"5055"},"quantity_ordered":10,"quantity_picked":2,"quantity_not_picked":0,"quantity_to_pick":8,"lines":[{"line_id":900,"pallet":{"id":49928,"reference":"P-1","customer_reference":"BX-1"},"location":{"id":7,"code":"4W11"},"quantity_ordered":10,"quantity_picked":2,"quantity_not_picked":0,"quantity_to_pick":8,"quantity_on_pallet":5,"state":"picking","state_label":"Picking","waiting_customer_service":false,"available_actions":[{"key":"pick","label":"Pick"},{"key":"not_picked","label":"Not picked"},{"key":"undo","label":"Undo"}]}],"other_pallets":[{"pallet_stored_item_id":77,"pallet":{"id":50000,"reference":"P-2","customer_reference":null},"location":{"id":8,"code":"4W12"},"quantity_available":6}]}],"inserts":{"total":0,"unprinted":0},"shipments":[],"parcels":[],"shipment_required":true,"dispatch_blocked":null,"can_scan":true,"available_actions":[]}"#

    private static let palletsReturn = #"{"id":6381,"reference":"000137-awf-r426","type":"pallet","type_group":"pallets","type_label":"Pallets","state":"picked","state_label":"Picked","is_collection":false,"in_picking_session":false,"counts":{"lines":2,"picked":1,"not_picked":1,"to_pick":0,"waiting_customer_service":1},"pallets":[{"line_id":10,"line_state":"picked","line_state_label":"Picked","waiting_customer_service":false,"pallet":{"id":1,"reference":"P-10","type":"pallet","type_label":"Pallet"},"location":null,"stored_items":[{"reference":"SI-1","name":"Soap","quantity":4}],"available_actions":[]},{"line_id":11,"line_state":"not_picked","line_state_label":"Not picked","waiting_customer_service":true,"pallet":{"id":2,"reference":"P-11"},"location":{"id":3,"code":"1A1"},"stored_items":[],"available_actions":[{"key":"undo","label":"Undo"}]}],"stored_items":[],"inserts":{"total":2,"unprinted":1},"shipments":[{"id":31,"shipper":"DPD","shipper_code":"dpd","tracking":"TRK1","trackings":["TRK1"],"tracking_urls":["https://track.example/TRK1"],"is_printable":true,"label":{"kind":"download","url":"https://app.aiku.test/x"},"can_delete":true}],"parcels":[{"weight":2.5,"dimensions":[30,20,10]}],"shipment_required":true,"dispatch_blocked":"Print the customer inserts first.","can_scan":false,"available_actions":[{"key":"add_shipment","label":"Add shipment"}]}"#

    private static func write(_ detail: String, status: String = "done", message: String = "Done", lineID: String = "null") -> String {
        #"{"data":"# + detail + #","result":{"status":""# + status + #"","message":""# + message + #"","line_id":"# + lineID + "}}"
    }

    private static let base = "mobile/warehouses/1/pallet-returns"

    private static let responses: [String: String] = [
        base: #"{"data":[{"id":6385,"reference":"412170-awf-r1591","customer_reference":null,"type":"stored_item","type_group":"items","type_label":"Stored items","platform":{"code":"shopify","name":"Shopify"},"state":"confirmed","state_label":"Confirmed","customer":{"id":7,"name":"Acme"},"number_pallets":1,"number_items":3,"date":"2026-11-17T08:00:00+00:00","picker":null,"is_collection":false}],"meta":{"current_page":1,"last_page":2,"per_page":50,"total":60},"counts":{"confirmed":40,"picking":3,"picked":2,"dispatched":900,"cancel":5,"active":45,"types":{"pallets":10,"items":35}}}"#,
        "\(base)/6385": #"{"data":"# + itemsReturn + "}",
        "\(base)/6381": #"{"data":"# + palletsReturn + "}",
        "\(base)/6385/start-picking": write(itemsReturn),
        "\(base)/6385/finish-picking": write(itemsReturn),
        "\(base)/6385/dispatch": write(itemsReturn),
        "\(base)/6385/lines/900/pick": write(itemsReturn, message: "Picked 3 × SI-55."),
        "\(base)/6385/lines/900/not-picked": write(itemsReturn),
        "\(base)/6385/lines/900/undo": write(itemsReturn),
        "\(base)/6385/pallet-stored-items/77/pick": write(itemsReturn),
        "\(base)/6385/scan": write(itemsReturn, status: "picked", message: "SI-55 picked", lineID: "900"),
        "\(base)/6381/parcels": write(palletsReturn),
        "\(base)/6381/shipments": write(palletsReturn, message: "Shipment added."),
        "\(base)/6381/shipments/31": write(palletsReturn),
        "\(base)/6381/shipments/31/print-label": write(palletsReturn),
        "\(base)/6381/inserts/print": write(palletsReturn),
        "\(base)/6381/shippers": #"{"data":[{"id":4,"code":"dpd","name":"DPD","trade_as":null,"api_shipper":"dpd","type":"api","needs_tracking":false,"is_locked_out":false},{"id":5,"code":"own","name":"Own van","trade_as":null,"api_shipper":null,"type":"manual","needs_tracking":false,"is_locked_out":false}],"directive":{"locked_shipper_id":null,"preferred_shipper_id":null,"locked_by":null,"can_override_lock":false,"tracking_channel":null}}"#,
        "\(base)/6381/shipments/31/label": "%PDF-1.4 label",
    ]

    private func client() -> (RecordingTransport, APIClient) {
        let transport = RecordingTransport(responses: Self.responses)
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
        return (transport, client)
    }

    private func body(_ request: URLRequest?) throws -> [String: Any] {
        let data = try #require(request?.httpBody)
        return try #require(try JSONSerialization.jsonObject(with: data) as? [String: Any])
    }

    private func live(_ id: Int) async -> (PalletReturnLive, RecordingTransport) {
        let (transport, client) = client()
        let live = PalletReturnLive(warehouseID: 1, returnID: id, apiClient: client, makeRequestID: { "11111111-1111-4111-8111-111111111111" })
        await live.load()
        return (live, transport)
    }

    @Test func listSendsStateTypeAndSearchAndKeepsCounts() async throws {
        let (transport, client) = client()
        let feed = PalletReturnsFeed()
        feed.state = .confirmed
        feed.type = .items
        feed.search = " acme "

        await feed.reload(warehouseID: 1, apiClient: client)

        let items = transport.requests.first?.url.flatMap { URLComponents(url: $0, resolvingAgainstBaseURL: false)?.queryItems } ?? []
        #expect(items.contains(URLQueryItem(name: "state", value: "confirmed")))
        #expect(items.contains(URLQueryItem(name: "type", value: "items")))
        #expect(items.contains(URLQueryItem(name: "q", value: "acme")))
        #expect(PalletReturnStateFilter.active.count(in: feed.counts) == 45)
        #expect(PalletReturnTypeFilter.all.count(in: feed.counts) == 45)
        #expect(PalletReturnText.rowSubtitle(try #require(feed.rows.first)).hasPrefix("Acme · Stored items · from Shopify · 3 item lines · "))
        #expect(feed.hasMorePages)
    }

    @Test func itemsReturnKnowsWhatCanBePickedAndFromWhere() async throws {
        let (live, _) = await live(6385)
        let detail = try #require(live.detail)
        let item = try #require(detail.storedItems.first)
        let line = try #require(item.lines.first)

        #expect(detail.isItems)
        #expect(detail.canScan)
        #expect(line.maxPick == 5)
        #expect(line.shortfall == 3)
        #expect(item.canPickFromOtherPallet)
        #expect(item.shortLine?.lineId == 900)
        #expect(PalletGroup.grouping(detail.storedItems).first?.locationCode == "4W11")
        #expect(PalletReturnText.itemLineTone(line) == .warn)
    }

    @Test func pickingAnAmountOrAllSendsTheRightBodyWithARequestID() async throws {
        let (live, transport) = await live(6385)

        #expect(await live.pickItem(900, quantity: 3))
        let amount = try body(transport.requests.last)
        #expect(amount["quantity"] as? Int == 3)
        #expect(amount["all"] == nil)
        #expect(amount["client_request_id"] as? String == "11111111-1111-4111-8111-111111111111")

        #expect(await live.pickItem(900, quantity: nil))
        let all = try body(transport.requests.last)
        #expect(all["all"] as? Bool == true)
        #expect(all["quantity"] == nil)
        #expect(live.event == .done("Picked 3 × SI-55.", .success))
    }

    @Test func notPickedUndoAndOtherPalletHitTheirEndpoints() async throws {
        let (live, transport) = await live(6385)

        #expect(await live.notPicked(900, quantity: 2))
        #expect(try body(transport.requests.last)["quantity"] as? Int == 2)
        #expect(await live.undo(900))
        #expect(await live.pickFromOtherPallet(77, quantity: 3, fromLineID: 900))
        let other = try body(transport.requests.last)
        #expect(other["quantity"] as? Int == 3)
        #expect(other["from_line_id"] as? Int == 900)

        let paths = transport.requests.filter { $0.httpMethod == "POST" }.map { $0.url?.path ?? "" }
        #expect(paths == [
            "/app/api/\(Self.base)/6385/lines/900/not-picked",
            "/app/api/\(Self.base)/6385/lines/900/undo",
            "/app/api/\(Self.base)/6385/pallet-stored-items/77/pick"
        ])
    }

    @Test func returnStateWritesHitTheirEndpoints() async {
        let (live, transport) = await live(6385)

        #expect(await live.startPicking())
        #expect(await live.finishPicking())
        #expect(await live.dispatch())

        let paths = transport.requests.filter { $0.httpMethod == "POST" }.map { $0.url?.path.components(separatedBy: "/").last ?? "" }
        #expect(paths == ["start-picking", "finish-picking", "dispatch"])
    }

    @Test func scanningGoesToTheServerWithOneAndARequestID() async throws {
        let (live, transport) = await live(6385)

        await live.scan(" 5055 ")

        let sent = try body(transport.requests.last { $0.url?.path.hasSuffix("/scan") == true })
        #expect(sent["barcode"] as? String == "5055")
        #expect(sent["quantity"] as? Int == 1)
        #expect(sent["client_request_id"] as? String == "11111111-1111-4111-8111-111111111111")
        #expect(live.event == .done("SI-55 picked", .success))
    }

    @Test func scanningWithoutScanToPickMatchesTheLineLocally() async {
        let (live, transport) = await live(6381)

        await live.scan("p-11")

        #expect(live.event == .lineFound(11))
        #expect(!transport.requests.contains { $0.url?.path.hasSuffix("/scan") == true })
    }

    @Test func pickedReturnShowsWhyDispatchIsBlocked() async throws {
        let (live, _) = await live(6381)
        let detail = try #require(live.detail)

        #expect(detail.action("dispatch") == nil)
        #expect(detail.dispatchBlocked == "Print the customer inserts first.")
        #expect(detail.inserts.unprinted == 1)
        #expect(detail.pallets[1].waitingCustomerService)
        #expect(detail.shipments.first?.trackingURL(0)?.absoluteString == "https://track.example/TRK1")
        #expect(detail.parcels.first?.dimensions == [30, 20, 10])
    }

    @Test func apiShipmentSavesChangedParcelsFirst() async throws {
        let (live, transport) = await live(6381)

        let parcels = [MobileReturnParcelBody(weightKg: 3, dimensionsCm: [40, 30, 20])]
        #expect(await live.createShipment(shipperID: 4, tracking: nil, parcels: parcels))

        let posts = transport.requests.filter { $0.httpMethod == "POST" }
        #expect(posts.map { $0.url?.path.components(separatedBy: "/").last ?? "" } == ["parcels", "shipments"])
        let sentParcels = try #require(try body(posts[0])["parcels"] as? [[String: Any]])
        #expect(sentParcels.first?["weight_kg"] as? Double == 3)
        #expect(sentParcels.first?["dimensions_cm"] as? [Double] == [40, 30, 20])
        #expect(try body(posts[1])["shipper_id"] as? Int == 4)
        #expect(live.event == .done("Shipment added.", .success))
    }

    @Test func manualShipmentSendsTheTrackingOnly() async throws {
        let (live, transport) = await live(6381)

        #expect(await live.createShipment(shipperID: 5, tracking: "  VAN-1 ", parcels: nil))

        let sent = try body(transport.requests.last)
        #expect(sent["shipper_id"] as? Int == 5)
        #expect(sent["tracking"] as? String == "VAN-1")
        #expect(!transport.requests.contains { $0.url?.path.hasSuffix("/parcels") == true })
    }

    @Test func shippersSplitIntoAPIAndManual() async {
        let (live, _) = await live(6381)

        await live.loadShippers()

        #expect(live.shippers.apiShippers.map(\.name) == ["DPD"])
        #expect(live.shippers.manualShippers.map(\.name) == ["Own van"])
    }

    @Test func shipmentDeletePrintAndInserts() async {
        let (live, transport) = await live(6381)

        #expect(await live.deleteShipment(31))
        #expect(transport.requests.last?.httpMethod == "DELETE")
        #expect(await live.printLabel(31))
        #expect(transport.requests.last?.url?.path.hasSuffix("/shipments/31/print-label") == true)
        #expect(await live.printInserts())
        #expect(transport.requests.last?.url?.path.hasSuffix("/inserts/print") == true)
    }

    @Test func labelIsSavedAsAPDF() async throws {
        let (live, _) = await live(6381)
        let shipment = try #require(live.detail?.shipments.first)

        let result = await live.downloadLabel(shipment)

        let url = try result.get()
        #expect(url.lastPathComponent == "000137-awf-r426-label-31.pdf")
    }

    @Test func parcelDraftChecksLikeAndroid() {
        var draft = ReturnParcelDraft()
        #expect(draft.problem == "Enter the weight.")
        draft.weight = "2,5"
        #expect(draft.problem == "Enter the length, width and height.")
        draft.length = "30"
        draft.width = "20"
        draft.height = "1001"
        #expect(draft.problem == "Each side must be above 0 and at most 1000 cm.")
        draft.height = "10"
        #expect(draft.body == MobileReturnParcelBody(weightKg: 2.5, dimensionsCm: [30, 20, 10]))
    }
}
