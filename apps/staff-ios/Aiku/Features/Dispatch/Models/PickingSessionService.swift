import Foundation

struct PickingSessionService: Sendable {
    var sessions: @Sendable (_ warehouseID: Int, _ query: MobilePickingSessionsQuery) async throws -> MobilePickingSessionsPage
    var session: @Sendable (_ id: Int) async throws -> MobilePickingSession
    var create: @Sendable (_ warehouseID: Int, _ deliveryNoteIDs: [Int]) async throws -> MobilePickingSession
    var write: @Sendable (_ id: Int, _ write: PickingSessionWrite) async throws -> MobilePickingSessionOutcome
    var scanPick: @Sendable (_ id: Int, _ body: MobileSessionScanBody) async throws -> MobilePickingSessionOutcome
    var scanPack: @Sendable (_ id: Int, _ body: MobileSessionScanBody) async throws -> MobilePickingSessionOutcome
    var trolleys: @Sendable (_ warehouseID: Int, _ availableOnly: Bool) async throws -> [MobileSessionTrolley]
    var pickedBays: @Sendable (_ warehouseID: Int) async throws -> [MobileSessionPickedBay]
    var candidates: @Sendable (_ warehouseID: Int) async throws -> [MobileSessionCandidateNote]

    static func live(_ client: APIClient) -> PickingSessionService {
        PickingSessionService(
            sessions: { try await client.pickingSessions(warehouseID: $0, query: $1) },
            session: { try await client.pickingSession(id: $0) },
            create: { try await client.createPickingSession(warehouseID: $0, deliveryNoteIDs: $1) },
            write: { try await client.writePickingSession(id: $0, $1) },
            scanPick: { try await client.scanPickInPickingSession(id: $0, $1) },
            scanPack: { try await client.scanPackInPickingSession(id: $0, $1) },
            trolleys: { try await client.warehouseTrolleys(warehouseID: $0, availableOnly: $1) },
            pickedBays: { try await client.warehousePickedBays(warehouseID: $0) },
            candidates: { try await client.pickingSessionCandidates(warehouseID: $0) }
        )
    }
}
