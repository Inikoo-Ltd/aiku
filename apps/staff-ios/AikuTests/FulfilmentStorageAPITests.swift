import Foundation
import Testing
@testable import Aiku

final class RequestIDSequence: @unchecked Sendable {
    private let lock = NSLock()
    private var issued = 0

    func next() -> String {
        lock.withLock {
            issued += 1
            return String(format: "00000000-0000-4000-8000-%012d", issued)
        }
    }

    static func id(_ number: Int) -> String {
        String(format: "00000000-0000-4000-8000-%012d", number)
    }
}

@MainActor
func waitUntil(_ condition: () -> Bool) async {
    for _ in 0..<400 where !condition() {
        try? await Task.sleep(for: .milliseconds(5))
    }
}

@MainActor
struct FulfilmentStorageAPITests {
    private final class RecordingTransport: HTTPTransport, @unchecked Sendable {
        let responses: [String: String]
        private let lock = NSLock()
        private var recorded: [URLRequest] = []
        private var statuses: [String: Int] = [:]
        private var held: Set<String> = []
        private var waiting: [CheckedContinuation<Void, Never>] = []

        init(responses: [String: String]) {
            self.responses = responses
        }

        var requests: [URLRequest] {
            lock.withLock { recorded }
        }

        func fail(_ path: String, status: Int) {
            lock.withLock { statuses[path] = status }
        }

        func heal(_ path: String) {
            lock.withLock { statuses[path] = nil }
        }

        func hold(_ path: String) {
            lock.withLock { _ = held.insert(path) }
        }

        func release() {
            let resumed = lock.withLock {
                held.removeAll()
                defer { waiting.removeAll() }
                return waiting
            }
            resumed.forEach { $0.resume() }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            let (isHeld, status) = lock.withLock {
                recorded.append(request)
                return (held.contains(path), statuses[path])
            }
            if isHeld {
                await withCheckedContinuation { continuation in
                    let stillHeld = lock.withLock {
                        guard held.contains(path) else { return false }
                        waiting.append(continuation)
                        return true
                    }
                    if !stillHeld {
                        continuation.resume()
                    }
                }
            }
            if let status {
                return (Data("{}".utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
            }
            guard let body = responses[path] else {
                return (Data(), HTTPURLResponse(url: request.url!, statusCode: 404, httpVersion: nil, headerFields: nil)!)
            }
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: 200, httpVersion: nil, headerFields: nil)!)
        }
    }

    private static let pallet = #"{"id":49928,"reference":"000035-awf-p0019","customer_reference":"BX-1","type":"pallet","type_label":"Pallet","state":"storing","state_label":"Storing","status":"storing","status_label":"Storing","customer":{"id":35,"name":"CAHM"},"location":{"id":7,"code":"4W11"},"notes":null,"number_stored_items":2,"incident":null,"delivery":{"id":1282,"reference":"000035-awf-022"},"return":null,"received_at":"2026-11-10T09:00:00+00:00","booked_in_at":null,"storing_at":"2026-11-11T09:00:00+00:00","items_storage":true,"stored_items":[{"id":5,"reference":"SI-5","name":"Soap","barcode":"501","quantity":10,"state":"active","state_label":"Active"}],"open_audit_id":null,"available_actions":[{"key":"move","label":"Move"},{"key":"damaged","label":"Damaged"},{"key":"lost","label":"Lost"},{"key":"audit","label":"Audit stored items"}]}"#

    private static let damaged = #"{"id":49928,"reference":"000035-awf-p0019","state":"damaged","state_label":"Damaged","status":"incident","status_label":"Incident","incident":{"state":"damaged","state_label":"Damaged","message":"Crushed","reporter":{"id":291,"name":"Andi"},"date":"2026-11-17T08:00:00+00:00","return":null},"available_actions":[]}"#

    private static let audit = #"{"id":70,"reference":"AUD-70","state":"in_process","state_label":"In process","pallet":{"id":49928,"reference":"000035-awf-p0019","customer_reference":"BX-1","location":{"id":7,"code":"4W11"}},"customer":{"id":35,"name":"CAHM"},"lines":[{"stored_item":{"id":5,"reference":"SI-5","name":"Soap","barcode":"501"},"quantity":10,"audited_quantity":8,"delta_id":900,"audit_type":"subtraction","audit_type_label":"Subtraction","is_new_item":false},{"stored_item":{"id":6,"reference":"SI-6","name":"Candle","barcode":"502"},"quantity":3,"audited_quantity":null,"delta_id":null,"audit_type":null,"audit_type_label":null,"is_new_item":false}],"counts":{"lines":2,"counted":1,"differences":1},"available_actions":[{"key":"count","label":"Count"},{"key":"add_item","label":"Add item"},{"key":"complete","label":"Complete audit"}]}"#

    private static let responses: [String: String] = [
        "mobile/warehouses/1/pallets": #"{"data":["# + pallet + "," + damaged + #"],"meta":{"current_page":1,"last_page":3,"per_page":50,"total":120},"counts":{"storing":2000,"receiving":2,"returning":4,"incidents":7,"returned":900,"incident_states":{"damaged":3,"lost":1,"other_incident":3}}}"#,
        "mobile/warehouses/1/pallets/49928": #"{"data":"# + pallet + "}",
        "mobile/warehouses/1/pallets/49928/move": #"{"data":"# + pallet + #","result":{"status":"done","message":"Pallet moved to 4W12."}}"#,
        "mobile/warehouses/1/pallets/49928/incident": #"{"data":"# + damaged + #","result":{"status":"done","message":"Pallet set as damaged."}}"#,
        "mobile/warehouses/1/pallets/49928/audit": #"{"data":"# + audit + #","result":{"status":"done","message":"Audit started."}}"#,
        "mobile/warehouses/1/stored-items": #"{"data":[{"id":5,"reference":"SI-5","name":"Soap","barcode":"501","state":"active","state_label":"Active","customer":{"id":35,"name":"CAHM"},"quantity":40,"number_pallets":3}],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":1},"counts":{"active":1,"discontinuing":0,"discontinued":2,"all":3}}"#,
        "mobile/warehouses/1/stored-items/5": #"{"data":{"id":5,"reference":"SI-5","name":"Soap","state":"active","quantity":40,"number_pallets":1,"pallets":[{"pallet":{"id":49928,"reference":"000035-awf-p0019","customer_reference":"BX-1","state":"storing","state_label":"Storing"},"location":{"id":7,"code":"4W11"},"quantity":10}]}}"#,
        "mobile/warehouses/1/stored-item-audits/70": #"{"data":"# + audit + "}",
        "mobile/warehouses/1/stored-item-audits/70/count": #"{"data":"# + audit + #","result":{"status":"done","message":"Counted."}}"#,
        "mobile/warehouses/1/stored-item-audits/70/deltas/900": #"{"data":"# + audit + #","result":{"status":"done","message":"Count removed."}}"#,
        "mobile/warehouses/1/stored-item-audits/70/complete": #"{"data":"# + audit.replacingOccurrences(of: #""state":"in_process""#, with: #""state":"completed""#) + #","result":{"status":"done","message":"Audit completed."}}"#,
        "mobile/warehouses/1/stored-item-audits/70/stored-items": #"{"data":[{"id":9,"reference":"SI-9","name":"Mug","barcode":"509"}]}"#,
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

    private func query(_ request: URLRequest?) -> [URLQueryItem] {
        request?.url.flatMap { URLComponents(url: $0, resolvingAgainstBaseURL: false)?.queryItems } ?? []
    }

    @Test func storedPalletsSendFilterSearchAndKeepCounts() async {
        let (transport, client) = client()
        let feed = StoredPalletsFeed()
        feed.search = " 4W "

        await feed.reload(warehouseID: 1, apiClient: client)

        let items = query(transport.requests.first)
        #expect(items.contains(URLQueryItem(name: "filter", value: "storing")))
        #expect(items.contains(URLQueryItem(name: "q", value: "4W")))
        #expect(!items.contains { $0.name == "incident_state" })
        #expect(feed.rows.count == 2)
        #expect(StoredPalletFilter.storing.count(in: feed.counts) == 2000)
        #expect(feed.hasMorePages)
    }

    @Test func incidentsListSendsTheIncidentState() async {
        let (transport, client) = client()
        let feed = StoredPalletsFeed(filter: .incidents)
        feed.incidentFilter = .otherIncident

        await feed.reload(warehouseID: 1, apiClient: client)

        let items = query(transport.requests.first)
        #expect(items.contains(URLQueryItem(name: "filter", value: "incidents")))
        #expect(items.contains(URLQueryItem(name: "incident_state", value: "other_incident")))
        #expect(PalletIncidentFilter.otherIncident.count(in: feed.counts) == 3)
        #expect(PalletIncidentFilter.all.count(in: feed.counts) == 7)
    }

    @Test func palletRowsAndIncidentsReadLikeAndroid() async {
        let (_, client) = client()
        let feed = StoredPalletsFeed()
        await feed.reload(warehouseID: 1, apiClient: client)

        let storing = feed.rows[0]
        let damaged = feed.rows[1]
        #expect(FulfilmentStorageText.subtitle(storing, showCustomer: true) == "CAHM · Pallet · “BX-1” · 4W11 · 2 stored items")
        #expect(FulfilmentStorageText.tone(storing) == .success)
        #expect(FulfilmentStorageText.chip(damaged) == "Damaged")
        #expect(FulfilmentStorageText.tone(damaged) == .danger)
        #expect(FulfilmentStorageText.incidentLine(damaged, includeCustomer: false).hasPrefix("“Crushed” · Andi · "))
    }

    @Test func palletDetailOffersTheServerActions() async throws {
        let (_, client) = client()
        let live = StoredPalletLive(warehouseID: 1, palletID: 49928, apiClient: client)

        await live.load()

        let pallet = try #require(live.pallet)
        #expect(pallet.can("move"))
        #expect(pallet.incidentStates == ["damaged", "lost"])
        #expect(pallet.action("audit")?.label == "Audit stored items")
        #expect(pallet.storedItems?.first?.reference == "SI-5")
    }

    @Test func movingSendsThePickedLocationOrTheScannedCode() async throws {
        let (transport, client) = client()
        let live = StoredPalletLive(warehouseID: 1, palletID: 49928, apiClient: client)
        await live.load()

        #expect(await live.move(to: MobileFulfilmentLocation(id: 8, code: "4W12", numberPallets: 0)))
        let picked = try body(transport.requests.last)
        #expect(picked["location_id"] as? Int == 8)
        #expect(picked["location_code"] == nil)

        #expect(await live.move(scannedCode: " 4w12 "))
        let scanned = try body(transport.requests.last)
        #expect(scanned["location_code"] as? String == "4w12")
        #expect(live.done?.message == "Pallet moved to 4W12.")
    }

    @Test func reportingAnIncidentSendsStateAndNote() async throws {
        let (transport, client) = client()
        let live = StoredPalletLive(warehouseID: 1, palletID: 49928, apiClient: client)
        await live.load()

        #expect(await live.reportIncident(state: "damaged", message: "  Crushed  "))

        let sent = try body(transport.requests.last)
        #expect(transport.requests.last?.url?.path.hasSuffix("/pallets/49928/incident") == true)
        #expect(sent["state"] as? String == "damaged")
        #expect(sent["message"] as? String == "Crushed")
        #expect(live.pallet?.status == "incident")
    }

    @Test func startingAnAuditGivesTheAuditToOpen() async {
        let (transport, client) = client()
        let live = StoredPalletLive(warehouseID: 1, palletID: 49928, apiClient: client)
        await live.load()

        await live.startAudit()

        #expect(transport.requests.last?.httpMethod == "POST")
        #expect(transport.requests.last?.url?.path.hasSuffix("/pallets/49928/audit") == true)
        #expect(live.startedAuditID == 70)
    }

    @Test func storedItemsListAndDetail() async throws {
        let (transport, client) = client()
        let feed = StoredItemsFeed()
        feed.state = .discontinued

        await feed.reload(warehouseID: 1, apiClient: client)

        #expect(query(transport.requests.first).contains(URLQueryItem(name: "state", value: "discontinued")))
        #expect(StoredItemStateFilter.all.count(in: feed.counts) == 3)
        let detail = try await client.fetchStoredItem(warehouseID: 1, id: 5)
        #expect(detail.pallets?.first?.location?.code == "4W11")
        #expect(detail.pallets?.first?.quantity == 10)
    }

    @Test func auditLinesShowTheDifferenceLikeAndroid() async throws {
        let (_, client) = client()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client)

        await live.load()

        let audit = try #require(live.audit)
        #expect(FulfilmentStorageText.auditChip(audit.lines[0]) == "Subtraction −2")
        #expect(FulfilmentStorageText.auditTone(type: audit.lines[0].auditType, difference: audit.lines[0].difference) == .warn)
        #expect(FulfilmentStorageText.auditChip(audit.lines[1]) == nil)
        #expect(audit.lineForCode("502")?.storedItem.reference == "SI-6")
    }

    @Test func countingSetsTheQuantityAndScanningAddsOneWithARequestID() async throws {
        let (transport, client) = client()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client, makeRequestID: { "11111111-1111-4111-8111-111111111111" })
        await live.load()

        #expect(await live.count(storedItemID: 6, quantity: 3))
        let counted = try body(transport.requests.last)
        #expect(counted["stored_item_id"] as? Int == 6)
        #expect(counted["audited_quantity"] as? Int == 3)
        #expect(counted["add"] == nil)

        await live.scan("502")
        let scanned = try body(transport.requests.last)
        #expect(scanned["barcode"] as? String == "502")
        #expect(scanned["add"] as? Int == 1)
        #expect(scanned["client_request_id"] as? String == "11111111-1111-4111-8111-111111111111")
        #expect(scanned["stored_item_id"] == nil)
        #expect(live.lastScanned == "SI-6")
    }

    @Test func removingACountAndCompletingTheAudit() async throws {
        let (transport, client) = client()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client)
        await live.load()

        #expect(await live.removeCount(deltaID: 900))
        #expect(transport.requests.last?.httpMethod == "DELETE")
        #expect(transport.requests.last?.url?.path.hasSuffix("/stored-item-audits/70/deltas/900") == true)

        await live.complete()
        #expect(transport.requests.last?.url?.path.hasSuffix("/stored-item-audits/70/complete") == true)
        #expect(live.isCompleted)
        #expect(live.done?.message == "Audit AUD-70 completed")
    }

    private static let countPath = "mobile/warehouses/1/stored-item-audits/70/count"

    private func countRequests(_ transport: RecordingTransport) throws -> [(code: String?, requestID: String?)] {
        try transport.requests.filter { $0.url?.path.hasSuffix("/count") == true }.map {
            let sent = try body($0)
            return (sent["barcode"] as? String, sent["client_request_id"] as? String)
        }
    }

    @Test func scansArrivingWhileOneIsSavingWaitAndAreAllSent() async throws {
        let (transport, client) = client()
        let ids = RequestIDSequence()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client, makeRequestID: { ids.next() })
        await live.load()
        transport.hold(Self.countPath)

        async let first: Void = live.scan("501")
        await waitUntil { (try? countRequests(transport).count) == 1 }
        await live.scan("501")
        await live.scan("502")

        #expect(live.queuedScans == 2)
        #expect(live.isWorking)
        #expect(try countRequests(transport).count == 1)

        transport.release()
        await first

        let sent = try countRequests(transport)
        #expect(sent.map(\.code) == ["501", "501", "502"])
        #expect(sent.map(\.requestID) == [RequestIDSequence.id(1), RequestIDSequence.id(2), RequestIDSequence.id(3)])
        #expect(live.queuedScans == 0)
        #expect(!live.isWorking)
        #expect(live.lastScanned == "SI-6")
    }

    @Test func anUnansweredScanIsResentWithTheSameRequestID() async throws {
        let (transport, client) = client()
        let ids = RequestIDSequence()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client, makeRequestID: { ids.next() })
        await live.load()
        transport.fail(Self.countPath, status: 502)

        await live.scan("501")

        #expect(live.unsure?.scannedCode == "501")
        #expect(live.done?.tone == .warn)
        #expect(live.queuedScans == 1)
        #expect(!live.isWorking)

        transport.heal(Self.countPath)
        await live.retryUnsure()

        let sent = try countRequests(transport)
        #expect(sent.map(\.requestID) == [RequestIDSequence.id(1), RequestIDSequence.id(1)])
        #expect(live.unsure == nil)
        #expect(live.queuedScans == 0)
        #expect(live.lastScanned == "SI-5")
    }

    @Test func aScanAfterAnUnansweredOneResendsThatOneFirst() async throws {
        let (transport, client) = client()
        let ids = RequestIDSequence()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client, makeRequestID: { ids.next() })
        await live.load()
        transport.fail(Self.countPath, status: 503)
        await live.scan("501")
        transport.heal(Self.countPath)

        await live.scan("502")

        let sent = try countRequests(transport)
        #expect(sent.map(\.code) == ["501", "501", "502"])
        #expect(sent.map(\.requestID) == [RequestIDSequence.id(1), RequestIDSequence.id(1), RequestIDSequence.id(2)])
        #expect(live.unsure == nil)
    }

    @Test func aRefusedScanIsDroppedNotLeftUnsure() async throws {
        let (transport, client) = client()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client)
        await live.load()
        transport.fail(Self.countPath, status: 403)

        await live.scan("501")

        #expect(live.unsure == nil)
        #expect(live.done?.tone == .danger)
        #expect(live.queuedScans == 0)
    }

    @Test func anUnansweredTypedCountIsNotLeftUnsure() async throws {
        let (transport, client) = client()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client)
        await live.load()
        transport.fail(Self.countPath, status: 502)

        #expect(await live.count(storedItemID: 6, quantity: 3) == false)

        #expect(live.unsure == nil)
        #expect(live.sheetError != nil)
    }

    @Test func addItemChoicesComeFromTheAudit() async {
        let (_, client) = client()
        let live = StoredItemAuditLive(warehouseID: 1, auditID: 70, apiClient: client)

        await live.searchChoices("")

        #expect(live.choices.map(\.reference) == ["SI-9"])
    }

    @Test func scannedPalletOpensThePalletPage() throws {
        let json = #"{"code":"000035-awf-p0019","type":"pallet","pallet":{"id":49928,"reference":"000035-awf-p0019","state":"storing","state_label":"Storing","status":"storing","location_code":"4W11"}}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let result = try decoder.decode(MobileScanResult.self, from: Data(json.utf8))

        let target = try #require(ShellScanCatalog.target(from: result))

        #expect(target.kind == .pallet)
        #expect(target.detail == "Storing · 4W11")
        #expect(target.link == .fulfilment(.pallet(id: 49928, reference: "000035-awf-p0019")))
    }
}
