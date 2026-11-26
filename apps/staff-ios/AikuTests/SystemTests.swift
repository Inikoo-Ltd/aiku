import Foundation
import Testing
@testable import Aiku

@MainActor
struct SystemTests {
    private static func entry(_ json: String) throws -> OfflineOperationEntry {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(OfflineOperationEntry.self, from: Data(json.utf8))
    }

    private static let refusedPick = #"""
    {"operation_id": 41, "status": "partly_applied", "operation": "pick", "reason": "not_enough_stock", "instruction": "put_back",
     "requested_quantity": "3", "applied_quantity": 1, "labels": {"title": "AR24811 · Line 2", "location_code": "A-03-02"},
     "record": {"type": "delivery_note", "id": 501}, "resolutions": ["put_back", "done", "nonsense"], "can_rollback": false}
    """#

    private static func store(
        open: [OfflineOperationEntry],
        recent: [OfflineOperationEntry] = [],
        resolved: OfflineOperationEntry? = nil,
        failure: APIError? = nil
    ) -> SystemSyncStore {
        SystemSyncStore(service: SystemSyncService(
            operations: { state in state == .open ? open : recent },
            resolve: { _, _ in
                if let failure { throw failure }
                return OfflineResolveOutcome(operation: try #require(resolved), results: [])
            },
            rollback: { _, _ in
                if let failure { throw failure }
                return try #require(resolved)
            }
        ))
    }

    @Test("An operation reads lenient numbers and keeps only the resolutions the app knows")
    func operationDecodes() throws {
        let entry = try Self.entry(Self.refusedPick)

        #expect(entry.key == 41)
        #expect(entry.requestedQuantity == 3)
        #expect(entry.missingQuantity == 2)
        #expect(entry.allowedResolutions == [.putBack, .done])
        #expect(entry.record?.key == .deliveryNote(501))
        #expect(entry.isOpen)
        #expect(OfflineTexts.instruction(entry) == "Put the other 2 back at A-03-02")
        #expect(OfflineTexts.reason(entry.reason) == "Aiku shows less stock at this location")
    }

    @Test("Loading keeps open and recent operations apart")
    func loadsOpenAndRecent() async throws {
        let open = try Self.entry(Self.refusedPick)
        let recent = try Self.entry(#"{"id": 40, "status": "applied", "state": "rolled_back", "operation": "pack"}"#)
        let store = Self.store(open: [open], recent: [recent])

        await store.load()

        #expect(store.hasLoaded)
        #expect(store.openOperations.map(\.key) == [41])
        #expect(store.operation(40)?.currentState == "rolled_back")
    }

    @Test("Resolving moves the operation out of the open list")
    func resolvingClosesTheOperation() async throws {
        let resolved = try Self.entry(#"{"operation_id": 41, "status": "partly_applied", "state": "resolved", "operation": "pick"}"#)
        let store = Self.store(open: [try Self.entry(Self.refusedPick)], resolved: resolved)
        await store.load()

        let change = await store.resolve(41, with: .putBack)

        #expect(change == .resolved(.putBack))
        #expect(store.openOperations.isEmpty)
        #expect(store.operation(41)?.currentState == "resolved")
    }

    @Test("A resolution the operation does not offer is not sent")
    func unofferedResolutionIsIgnored() async throws {
        let store = Self.store(open: [try Self.entry(Self.refusedPick)])
        await store.load()

        #expect(await store.resolve(41, with: .keepCount) == nil)
        #expect(store.openOperations.count == 1)
    }

    @Test("A refused resolution keeps the operation open and says why")
    func refusedResolution() async throws {
        let store = Self.store(open: [try Self.entry(Self.refusedPick)], failure: .rejected(message: "Already resolved."))
        await store.load()

        #expect(await store.resolve(41, with: .done) == .refused("Already resolved."))
        #expect(store.openOperations.count == 1)
    }

    @Test("A record is titled with the reference the operation's item sends")
    func titleFromTheItem() async throws {
        let open = try Self.entry(#"""
        {"operation_id": 42, "status": "refused", "operation": "pick", "labels": {"title": ""}, "record": {"type": "delivery_note", "id": 502},
         "item": {"sko_code": "BBUG-06", "record_type": "delivery_note", "record_reference": "AR24812", "line": 3}}
        """#)
        let store = Self.store(open: [open])
        await store.load()

        let title = store.title(of: .deliveryNote(502), downloaded: { _ in nil })

        #expect(title == "Delivery note AR24812")
        #expect(store.operation(42)?.item?.line == 3)
    }

    @Test("Queued work is grouped per record and titled from the downloaded copy")
    func queueGroups() {
        let store = SystemSyncStore(service: SystemSyncService(operations: { _ in [] }, resolve: { _, _ in throw APIError.offline }, rollback: { _, _ in throw APIError.offline }))
        let note = OfflineRecordKey.deliveryNote(501)
        let location = OfflineRecordKey(type: .location, id: 7)
        let entries = [OfflineTestData.entry(1, record: note), OfflineTestData.entry(2, record: location), OfflineTestData.entry(3, record: note)]
        let body = Data(#"{"data": {"id": 501, "reference": "AR24811"}}"#.utf8)

        let groups = store.groups(of: entries) { record in
            record == note ? DownloadedRecord(body: body, seenAt: .now, downloadedAt: .now) : nil
        }

        #expect(groups.map(\.title) == ["Delivery note AR24811", "Location"])
        #expect(groups.first?.entries.map(\.localID) == [1, 3])
    }

    @Test("The states gallery lists every designed state once")
    func galleryListsEveryState() {
        let codes = SystemStateCatalog.sections.flatMap(\.entries).map(\.code)

        #expect(Set(codes).count == codes.count)
        #expect(codes.count == 17)
        #expect(codes.contains("S09"))
        #expect(codes.contains("105"))
    }

    @Test("No access is titled with the warehouse and offers another warehouse only when there is one, like Android")
    func noAccessFollowsTheWarehouse() {
        #expect(SystemNoAccessText.title(module: "Fulfilment", warehouse: "Europa Drive") == "Europa Drive")
        #expect(SystemNoAccessText.title(module: "Fulfilment", warehouse: " ") == "Fulfilment")
        #expect(SystemNoAccessText.message(module: "Fulfilment", warehouse: "Europa Drive") == "Your account can’t open **Fulfilment** in **Europa Drive**. Ask your warehouse manager to add the permission in Aiku.")
        #expect(SystemNoAccessText.message(module: "Fulfilment", warehouse: "") == "Your account can’t open **Fulfilment**. Ask your warehouse manager to add the permission in Aiku.")
        #expect(!SystemNoAccessText.canChangeWarehouse(warehouseCount: 1))
        #expect(SystemNoAccessText.canChangeWarehouse(warehouseCount: 2))
    }
}
