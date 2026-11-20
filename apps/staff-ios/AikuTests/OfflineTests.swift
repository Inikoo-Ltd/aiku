import Foundation
import Testing
@testable import Aiku

enum OfflineTestData {
    static func entry(
        _ localID: Int64,
        userID: Int = 7,
        warehouseID: Int = 4,
        operation: OfflineOperation? = .pick,
        record: OfflineRecordKey = .deliveryNote(501),
        payload: OfflinePayload = ["delivery_note_item_id": .int(11), "location_org_stock_id": .int(91), "quantity": .number(2)],
        sequence: Int64? = nil,
        state: QueuedActionState = .waiting,
        performedAt: Date = Date(timeIntervalSince1970: 1_000)
    ) -> QueueEntry {
        QueueEntry(
            localID: localID,
            userID: userID,
            warehouseID: warehouseID,
            operation: operation,
            record: record,
            payload: payload,
            clientRequestID: "request-\(localID)",
            performedAt: performedAt,
            timeSourceIsServer: true,
            seenAt: nil,
            sequence: sequence,
            state: state,
            result: nil,
            syncedAt: nil,
            createdAt: performedAt
        )
    }

    static func temporaryDirectory() -> URL {
        let url = FileManager.default.temporaryDirectory.appending(path: "offline-\(UUID().uuidString)", directoryHint: .isDirectory)
        try? FileManager.default.createDirectory(at: url, withIntermediateDirectories: true)
        return url
    }
}

private final class SyncTransport: HTTPTransport, @unchecked Sendable {
    private let lock = NSLock()
    private var answers: [(Int, String)]
    private var recorded: [URLRequest] = []

    init(answers: [(Int, String)]) {
        self.answers = answers
    }

    var requests: [URLRequest] { lock.withLock { recorded } }

    func sentBody(_ index: Int) throws -> [String: Any] {
        let body = try #require(requests[index].httpBody)
        return try #require(try JSONSerialization.jsonObject(with: body) as? [String: Any])
    }

    func data(for request: URLRequest) async throws -> (Data, URLResponse) {
        let answer: (Int, String) = lock.withLock {
            recorded.append(request)
            return answers.isEmpty ? (200, #"{"data": []}"#) : answers.removeFirst()
        }
        let response = HTTPURLResponse(url: request.url!, statusCode: answer.0, httpVersion: nil, headerFields: ["Date": "Fri, 20 Nov 2026 10:00:00 GMT"])!
        return (Data(answer.1.utf8), response)
    }
}

struct OfflineTests {
    private let configuration = AppConfiguration(apiBaseURL: URL(string: "https://aiku.test/app/api")!)

    @Test("Every request names the device, the platform and the app version")
    func identityHeaders() {
        let client = APIClient(configuration: configuration, clientIdentity: APIClientIdentity(deviceID: { "device-1" }, appVersion: "0.1.0 (3)"))
        let request = client.makeRequest(for: .profile)

        #expect(request.value(forHTTPHeaderField: "X-Aiku-Device") == "device-1")
        #expect(request.value(forHTTPHeaderField: "X-Aiku-Platform") == "ios")
        #expect(request.value(forHTTPHeaderField: "X-Aiku-App-Version") == "0.1.0 (3)")
    }

    @Test("Routes map detail pages and writes to the record they belong to")
    func routes() throws {
        let note = try #require(OfflineRoutes.mobileRoute(of: URL(string: "https://aiku.test/app/api/mobile/delivery-notes/501")!))
        let pick = try #require(OfflineRoutes.mobileRoute(of: URL(string: "https://aiku.test/app/api/mobile/delivery-notes/501/lines/11/pick")!))
        let sko = try #require(OfflineRoutes.mobileRoute(of: URL(string: "https://aiku.test/app/api/mobile/warehouses/4/org-stocks/9")!))

        #expect(OfflineRoutes.detailRecord(of: note) == .deliveryNote(501))
        #expect(OfflineRoutes.detailRecord(of: pick) == nil)
        #expect(OfflineRoutes.record(under: pick) == .deliveryNote(501))
        #expect(OfflineRoutes.detailRecord(of: sko) == OfflineRecordKey(type: .orgStock, id: 9))
        #expect(OfflineRoutes.detailPath(OfflineRecordKey(type: .customerReturn, id: 3), warehouseID: 4) == "mobile/warehouses/4/returns/3")
        #expect(OfflineRoutes.warehouseList(of: ["warehouses", "4", "my-work"], named: "my-work") == 4)
    }

    @Test("The server clock keeps counting from the last Date header and falls back after a reboot")
    func serverClock() {
        let defaults = UserDefaults(suiteName: "offline-clock-\(UUID().uuidString)")!
        let elapsed = LockedValue<TimeInterval>(100)
        let boot = LockedValue<Int?>(1)
        let clock = ServerClock(defaults: defaults, elapsed: { elapsed.value }, bootTime: { boot.value }, deviceClock: { Date(timeIntervalSince1970: 0) })

        clock.record(dateHeader: "Fri, 20 Nov 2026 10:00:00 GMT")
        elapsed.value = 160
        let later = clock.now()
        boot.value = 2

        #expect(later.isServerTime)
        #expect(later.date == Date(timeIntervalSince1970: 1_795_168_860))
        #expect(clock.now() == OfflineTime(date: Date(timeIntervalSince1970: 0), isServerTime: false))
    }

    @Test("A batch numbers only unnumbered actions, keeps one warehouse and resumes after a restart")
    func claimBatch() {
        let directory = OfflineTestData.temporaryDirectory()
        let queue = OfflineQueue(directory: directory)
        queue.add(OfflineTestData.entry(0))
        queue.add(OfflineTestData.entry(0, sequence: 5, state: .void))
        queue.add(OfflineTestData.entry(0, warehouseID: 9))
        queue.add(OfflineTestData.entry(0, userID: 8))

        let batch = queue.claimBatch(userID: 7, maxSize: 50) { count in (0..<count).map { Int64(10 + $0) } }

        #expect(batch.map(\.sequence) == [5, 10])
        #expect(batch.map(\.state) == [.void, .sending])
        #expect(OfflineQueue(directory: directory).entries().first { $0.localID == 1 }?.state == .waiting)
    }

    @Test("Settled actions are purged after a day, unsent ones never")
    func purge() {
        let now = Date(timeIntervalSince1970: 200_000)
        let queue = OfflineQueue(directory: OfflineTestData.temporaryDirectory(), clock: { now })
        var old = OfflineTestData.entry(0, state: .applied)
        old.syncedAt = now.addingTimeInterval(-25 * 60 * 60)
        queue.add(old)
        queue.add(OfflineTestData.entry(0))

        queue.purgeSettled()

        #expect(queue.entries().map(\.state) == [.waiting])
    }

    @Test("A sync records outcomes, refetches changed records and fills a gap with voids")
    func syncEngine() async throws {
        let transport = SyncTransport(answers: [
            (200, #"{"data": {"results": [{"sequence": 1, "status": "applied"}, {"sequence": 2, "status": "refused", "operation_id": 77, "reason": "wrong_state", "instruction": "put_back"}]}}"#),
            (200, #"{"data": {"id": 501}}"#),
            (200, #"{"data": [{"operation_id": 77, "status": "refused", "reason": "wrong_state", "instruction": "put_back"}]}"#)
        ])
        let client = APIClient(configuration: configuration, transport: transport, tokenProvider: { "token" })
        let queue = OfflineQueue(directory: OfflineTestData.temporaryDirectory())
        let device = OfflineDevice(service: "offline-tests-\(UUID().uuidString)", defaults: UserDefaults(suiteName: UUID().uuidString)!)
        let sessions = OfflineSessionBox()
        sessions.set(OfflineSessionBox.Current(userID: 7, token: "token"))
        queue.add(OfflineTestData.entry(0))
        queue.add(OfflineTestData.entry(0, operation: .notPicked))
        let engine = OfflineSyncEngine(queue: queue, device: device, api: OfflineSyncAPI(client: client), sessions: sessions)

        let outcome = await engine.run(maxBatches: 5)

        #expect(outcome.status == .complete)
        #expect(outcome.newlyNeedingYou == 1)
        #expect(outcome.changed == [.deliveryNote(501)])
        #expect(queue.entries().map(\.state) == [.applied, .needsYou])
        #expect(queue.entries().last?.result?.operationID == 77)
        #expect(transport.requests.map { $0.url?.path } == [
            "/app/api/mobile/warehouses/4/offline/sync",
            "/app/api/mobile/delivery-notes/501",
            "/app/api/mobile/offline/operations"
        ])
        let sent = try #require(try transport.sentBody(0)["actions"] as? [[String: Any]])
        #expect(sent.map { $0["sequence"] as? Int } == [1, 2])
        #expect(sent.first?["operation"] as? String == "pick")
        #expect(sent.first?["time_source"] as? String == "server")
        #expect(sent.first?["performed_at"] as? String == "1970-01-01T00:16:40.000Z")
        #expect(device.lastSequence(userID: 7) == 2)
    }

    @Test("A missing earlier number is sent again as a void before the rest")
    func missingPrevious() async throws {
        let transport = SyncTransport(answers: [
            (200, #"{"data": {"results": [{"sequence": 3, "status": "missing_previous"}], "expected_sequence": 2}}"#),
            (200, #"{"data": {"results": [{"sequence": 2, "status": "void"}, {"sequence": 3, "status": "applied"}]}}"#)
        ])
        let client = APIClient(configuration: configuration, transport: transport, tokenProvider: { "token" })
        let queue = OfflineQueue(directory: OfflineTestData.temporaryDirectory())
        let device = OfflineDevice(service: "offline-tests-\(UUID().uuidString)", defaults: UserDefaults(suiteName: UUID().uuidString)!)
        _ = device.reserveSequences(userID: 7, count: 2)
        let sessions = OfflineSessionBox()
        sessions.set(OfflineSessionBox.Current(userID: 7, token: "token"))
        queue.add(OfflineTestData.entry(0))
        let engine = OfflineSyncEngine(queue: queue, device: device, api: OfflineSyncAPI(client: client), sessions: sessions)

        _ = await engine.run(maxBatches: 5)

        let second = try #require(try transport.sentBody(1)["actions"] as? [[String: Any]])
        #expect(second.map { $0["operation"] as? String } == ["void", "pick"])
        #expect(queue.entries().first { $0.operation == .pick }?.state == .applied)
    }

    @Test("An unreachable server releases the batch to be sent again")
    func unreachable() async {
        struct Down: HTTPTransport {
            func data(for request: URLRequest) async throws -> (Data, URLResponse) { throw URLError(.notConnectedToInternet) }
        }
        let queue = OfflineQueue(directory: OfflineTestData.temporaryDirectory())
        let sessions = OfflineSessionBox()
        sessions.set(OfflineSessionBox.Current(userID: 7, token: "token"))
        queue.add(OfflineTestData.entry(0))
        let device = OfflineDevice(service: "offline-tests-\(UUID().uuidString)", defaults: UserDefaults(suiteName: UUID().uuidString)!)
        let engine = OfflineSyncEngine(queue: queue, device: device, api: OfflineSyncAPI(client: APIClient(configuration: configuration, transport: Down())), sessions: sessions)

        let outcome = await engine.run(maxBatches: 5)

        #expect(outcome.status == .retry)
        #expect(queue.entries().first?.state == .waiting)
        #expect(queue.entries().first?.sequence == 1)
    }

    @Test("Downloads belong to one user and are forgotten for others")
    func downloads() {
        let downloads = OfflineDownloads(root: OfflineTestData.temporaryDirectory())
        downloads.replace(userID: 7, record: .deliveryNote(501), body: Data("note".utf8), seenAt: nil)
        downloads.replace(userID: 8, record: .deliveryNote(502), body: Data("other".utf8), seenAt: nil)

        downloads.keepOnly(userID: 7)

        #expect(downloads.read(userID: 7, record: .deliveryNote(501)).map { String(decoding: $0.body, as: UTF8.self) } == "note")
        #expect(downloads.read(userID: 8, record: .deliveryNote(502)) == nil)
    }
}

final class LockedValue<Value: Sendable>: @unchecked Sendable {
    private let lock = NSLock()
    private var stored: Value

    init(_ value: Value) {
        stored = value
    }

    var value: Value {
        get { lock.withLock { stored } }
        set { lock.withLock { stored = newValue } }
    }
}
