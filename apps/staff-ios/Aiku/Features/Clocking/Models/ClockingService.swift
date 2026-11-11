import Foundation

struct ClockingService: Sendable {
    var status: @Sendable () async throws -> ClockingStatusResponse
    var timesheets: @Sendable (String, String) async throws -> ClockingTimesheetsResponse
    var shifts: @Sendable () async throws -> [ClockingShiftResponse]
    var clockByQr: @Sendable (ClockingQrRequest) async throws -> ClockingQrResponse
    var requestAdjustment: @Sendable (ClockingAdjustmentRequestBody) async throws -> ClockingAdjustmentResponse
    var adjustments: @Sendable () async throws -> [ClockingAdjustmentResponse] = { [] }
    var updateNotes: @Sendable (Int, String?) async throws -> Void

    static func live(_ client: APIClient) -> ClockingService {
        ClockingService(
            status: { try await client.clockingStatus() },
            timesheets: { try await client.clockingTimesheets(from: $0, to: $1) },
            shifts: { try await client.clockingShifts() },
            clockByQr: { try await client.clockByQr($0) },
            requestAdjustment: { try await client.requestClockingAdjustment($0) },
            adjustments: { try await client.clockingAdjustments() },
            updateNotes: { try await client.updateClockingNotes(clockingID: $0, notes: $1) }
        )
    }
}
