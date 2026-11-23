import Foundation
import Testing
@testable import Aiku

@MainActor
struct FulfilmentInboundAPITests {
    private final class RecordingTransport: HTTPTransport, @unchecked Sendable {
        let responses: [String: String]
        private let lock = NSLock()
        private var recorded: [URLRequest] = []
        private var failures: [String: (Int, String)] = [:]

        init(responses: [String: String]) {
            self.responses = responses
        }

        var requests: [URLRequest] {
            lock.withLock { recorded }
        }

        func fail(_ path: String, status: Int, body: String = "{}") {
            lock.withLock { failures[path] = (status, body) }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            let failure = lock.withLock {
                recorded.append(request)
                return failures[path]
            }
            if let (status, body) = failure {
                return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
            }
            guard let body = responses[path] else {
                return (Data(), HTTPURLResponse(url: request.url!, statusCode: 404, httpVersion: nil, headerFields: nil)!)
            }
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
        }
    }

    private static let pallet = #"{"id":39217,"reference":"000016-awf-p0019","customer_reference":"BX-7","type":"pallet","type_label":"Pallet","state":"booking_in","state_label":"Booking in","status":"receiving","status_label":"Receiving","customer":{"id":4,"name":"Acme"},"location":null,"notes":null,"number_stored_items":2,"incident":null,"has_rental":true,"stored_items":[{"id":1,"reference":"SI-1","name":"Soap","quantity":10}],"available_actions":[{"key":"book_in","label":"Book in"},{"key":"not_received","label":"Not received"}]}"#

    private static let detail = #"{"id":392,"reference":"000016-awf-034","customer_reference":null,"state":"booking_in","state_label":"Booking in","customer":{"id":4,"name":"Acme"},"estimated_delivery_date":"2026-11-20","handing_type":null,"customer_notes":"Fragile","public_notes":null,"internal_notes":null,"timeline":[{"state":"confirmed","label":"Confirmed","at":"2026-11-10T09:00:00+00:00"},{"state":"received","label":"Received","at":"2026-11-11T09:00:00+00:00"},{"state":"booking_in","label":"Booking in","at":null},{"state":"booked_in","label":"Booked in","at":null}],"counts":{"pallets":1,"boxes":0,"oversizes":0,"total":1,"received":0,"booking_in":1,"booked_in":0,"not_received":0},"pallets":["# + pallet + #"],"services":[{"code":"S1","name":"Unloading","quantity":2,"unit_label":"service"}],"physical_goods":[],"finish_booking_blocked":"1 pallet still to book in","available_actions":[]}"#

    private static let responses: [String: String] = [
        "mobile/warehouses/1/pallet-deliveries": #"{"data":[{"id":392,"reference":"000016-awf-034","customer_reference":"PO-9","state":"booking_in","state_label":"Booking in","customer":{"id":4,"name":"Acme"},"estimated_delivery_date":"2026-11-20","received_at":"2026-11-11T09:00:00+00:00","booked_in_at":null,"counts":{"pallets":3,"boxes":1,"oversizes":0,"total":4,"booked_in":1,"not_received":1}}],"meta":{"current_page":1,"last_page":2,"per_page":50,"total":51},"counts":{"confirmed":1,"received":0,"booking_in":2,"booked_in":900,"not_received":3,"active":3}}"#,
        "mobile/warehouses/1/pallet-deliveries/392": #"{"data":"# + detail + "}",
        "mobile/warehouses/1/pallet-deliveries/392/receive": #"{"data":"# + detail + #","result":{"status":"done","message":"Delivery marked as received."}}"#,
        "mobile/warehouses/1/pallet-deliveries/392/start-booking": #"{"data":"# + detail + #","result":{"status":"done","message":"Booking in started."}}"#,
        "mobile/warehouses/1/pallet-deliveries/392/finish-booking": #"{"data":"# + detail + #","result":{"status":"done","message":"Delivery booked in."}}"#,
        "mobile/warehouses/1/pallets/39217/book-in": #"{"data":"# + detail + #","result":{"status":"done","message":"Pallet booked in at F-01."}}"#,
        "mobile/warehouses/1/pallets/39217/not-received": #"{"data":"# + detail + #","result":{"status":"done","message":"Pallet set as not received."}}"#,
        "mobile/warehouses/1/pallets/39217/undo-not-received": #"{"data":"# + detail + #","result":{"status":"done","message":"Pallet set back to received."}}"#,
        "mobile/warehouses/1/fulfilment-locations": #"{"data":[{"id":77,"code":"F-01","number_pallets":3}]}"#,
    ]

    private func setUp() -> (RecordingTransport, APIClient) {
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

    private func loadedLive() async -> (PalletDeliveryLive, RecordingTransport) {
        let (transport, client) = setUp()
        let live = PalletDeliveryLive(warehouseID: 1, deliveryID: 392, apiClient: client)
        await live.load()
        return (live, transport)
    }

    @Test func listSendsStateSearchAndPageAndKeepsServerCounts() async throws {
        let (transport, client) = setUp()
        let feed = PalletDeliveriesFeed()
        feed.filter = .bookingIn
        feed.search = " acme "

        await feed.reload(warehouseID: 1, apiClient: client)

        let url = try #require(transport.requests.first?.url)
        let items = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems ?? []
        #expect(items.contains(URLQueryItem(name: "state", value: "booking_in")))
        #expect(items.contains(URLQueryItem(name: "q", value: "acme")))
        #expect(items.contains(URLQueryItem(name: "page", value: "1")))
        #expect(feed.rows.first?.reference == "000016-awf-034")
        #expect(PalletDeliveryFilter.active.count(in: feed.counts) == 3)
        #expect(PalletDeliveryFilter.bookedIn.count(in: feed.counts) == 900)
        #expect(feed.hasMorePages)
    }

    @Test func listRowReadsLikeAndroid() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let page = try decoder.decode(MobilePalletDeliveriesPage.self, from: Data(Self.responses["mobile/warehouses/1/pallet-deliveries"]!.utf8))
        let row = try #require(page.data.first)

        let moment = PalletDeliveryText.rowMoment(row, in: TimeZone(identifier: "UTC")!)
        #expect(row.customer?.name == "Acme")
        #expect(PalletDeliveryText.rowDetails(row) == ["“PO-9”", "1 of 3 booked in"])
        #expect(moment.date == "11 Nov 2026")
        #expect(moment.caption == "Received")
        #expect(PalletDeliveryText.rowChip(row) == Chip("Booking in", .info))
        #expect(PalletDeliveryText.deliveryTone("booked_in") == .success)
        #expect(PalletDeliveryText.deliveryTone("not_received") == .danger)
    }

    @Test func detailDecodesPalletsActionsAndBlockedReason() async throws {
        let (live, _) = await loadedLive()
        let delivery = try #require(live.delivery)
        let pallet = try #require(delivery.palletList.first)

        #expect(delivery.state == "booking_in")
        #expect(delivery.finishBookingBlocked == "1 pallet still to book in")
        #expect(delivery.action("finish_booking") == nil)
        #expect(pallet.can("book_in"))
        #expect(pallet.isWorkable)
        #expect(delivery.nextToBookIn?.id == 39217)
        #expect(delivery.bookInTotal == 1)
        #expect(PalletDeliveryText.palletSubtitle(pallet) == "Pallet · “BX-7” · no location yet · 2 stored items")
        #expect(delivery.services?.first?.name == "Unloading")
    }

    @Test func deliveryWritesHitTheirEndpoints() async {
        let (live, transport) = await loadedLive()

        await live.receive()
        await live.startBooking()
        await live.finishBooking()
        await live.notReceived(39217)
        await live.undoNotReceived(39217)

        let paths = transport.requests.filter { $0.httpMethod == "POST" }.map { $0.url?.path ?? "" }
        #expect(paths == [
            "/app/api/mobile/warehouses/1/pallet-deliveries/392/receive",
            "/app/api/mobile/warehouses/1/pallet-deliveries/392/start-booking",
            "/app/api/mobile/warehouses/1/pallet-deliveries/392/finish-booking",
            "/app/api/mobile/warehouses/1/pallets/39217/not-received",
            "/app/api/mobile/warehouses/1/pallets/39217/undo-not-received"
        ])
        #expect(live.done?.message == "Pallet set back to received.")
    }

    @Test func bookingInAPickedLocationSendsItsID() async throws {
        let (live, transport) = await loadedLive()
        live.openPallet(39217)

        await live.bookIn(39217, location: MobileFulfilmentLocation(id: 77, code: "F-01", numberPallets: 3))

        let sent = try body(transport.requests.last { $0.url?.path.hasSuffix("/pallets/39217/book-in") == true })
        #expect(sent["location_id"] as? Int == 77)
        #expect(sent["location_code"] == nil)
        #expect(live.openPalletID == nil)
    }

    private static let bookInPath = "mobile/warehouses/1/pallets/39217/book-in"

    @Test func aRefusedLocationShowsUnderTheLocationAndKeepsThePalletOpen() async {
        let (live, transport) = await loadedLive()
        transport.fail(Self.bookInPath, status: 422, body: #"{"message":"That location is not in this warehouse.","errors":{"location_code":["That location is not in this warehouse."]}}"#)
        live.openPallet(39217)

        await live.bookIn(39217, scannedCode: "ZZZ999")

        #expect(live.fieldError == "That location is not in this warehouse.")
        #expect(live.done == PalletDeliveryDone(message: "That location is not in this warehouse.", tone: .danger))
        #expect(live.openPalletID == 39217)
    }

    @Test func otherBookInFailuresAreAToastOnlyAndDoNotReload() async {
        let (live, transport) = await loadedLive()
        transport.fail(Self.bookInPath, status: 403, body: #"{"message":"You can’t book in pallets here."}"#)
        live.openPallet(39217)
        let loadsBefore = transport.requests.filter { $0.httpMethod == "GET" }.count

        await live.bookIn(39217, location: MobileFulfilmentLocation(id: 77, code: "F-01", numberPallets: 3))

        #expect(live.fieldError == nil)
        #expect(live.done == PalletDeliveryDone(message: "You can’t book in pallets here.", tone: .danger))
        #expect(transport.requests.filter { $0.httpMethod == "GET" }.count == loadsBefore)
    }

    @Test func aServerErrorOnBookInIsNotShownUnderTheLocation() async {
        let (live, transport) = await loadedLive()
        transport.fail(Self.bookInPath, status: 500)
        live.openPallet(39217)

        await live.bookIn(39217, scannedCode: "F-02")

        #expect(live.fieldError == nil)
        #expect(live.done?.tone == .danger)
    }

    @Test func scanningThePalletLabelOpensItThenTheLocationBooksItIn() async throws {
        let (live, transport) = await loadedLive()

        let first = await live.scanForBookIn("bx-7")
        #expect(first == .openedPallet)
        #expect(live.openPalletID == 39217)

        let second = await live.scanForBookIn("F-02")
        #expect(second == .bookingIn)
        let sent = try body(transport.requests.last { $0.url?.path.hasSuffix("/book-in") == true })
        #expect(sent["location_code"] as? String == "F-02")
    }

    @Test func scanningAnUnknownCodeWithNoPalletOpenIsNotOnTheDelivery() async {
        let (live, _) = await loadedLive()

        let outcome = await live.scanForBookIn("ZZZ")

        #expect(outcome == .notOnDelivery("ZZZ"))
    }

    @Test func locationSearchListsFulfilmentLocations() async throws {
        let (live, transport) = await loadedLive()

        await live.searchLocations("F")

        #expect(live.locationResults.map(\.code) == ["F-01"])
        let url = try #require(transport.requests.last?.url)
        #expect(url.path.hasSuffix("/fulfilment-locations"))
        #expect(url.query?.contains("q=F") == true)
    }

    @Test func scannedPalletDeliveryOpensIt() throws {
        let json = #"{"code":"000016-awf-034","type":"pallet_delivery","pallet_delivery":{"id":392,"reference":"000016-awf-034","state":"booking_in","state_label":"Booking in"}}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let result = try decoder.decode(MobileScanResult.self, from: Data(json.utf8))

        let target = try #require(ShellScanCatalog.target(from: result))

        #expect(target.kind == .palletDelivery)
        #expect(target.link == .fulfilment(.palletDelivery(id: 392, reference: "000016-awf-034")))
    }

    @Test func eachDeliveryWriteSpinsOnlyItsOwnButtonLikeAndroid() {
        let body = MobilePalletBookInBody(locationId: 7, locationCode: nil)

        #expect(MobilePalletDeliveryWrite.receive.actionKey == "receive")
        #expect(MobilePalletDeliveryWrite.startBooking.actionKey == "start_booking")
        #expect(MobilePalletDeliveryWrite.finishBooking.actionKey == "finish_booking")
        #expect(MobilePalletDeliveryWrite.bookIn(palletID: 1, body: body).actionKey == "book_in")
        #expect(MobilePalletDeliveryWrite.notReceived(palletID: 1).actionKey == "not_received")
        #expect(MobilePalletDeliveryWrite.undoNotReceived(palletID: 1).actionKey == "undo_not_received")
    }

    @Test func somethingNotInTheWarehouseIsNotCalledADeliveryLikeAndroid() {
        #expect(PalletDeliveryText.message(for: APIError.http(statusCode: 404)) == "It doesn’t exist, or it belongs to another warehouse.")
    }
}
