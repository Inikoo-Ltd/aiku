import Foundation

struct ClockingService: Sendable {
    var status: @Sendable () async throws -> ClockingStatusResponse
    var timesheets: @Sendable (String, String) async throws -> ClockingTimesheetsResponse
    var shifts: @Sendable () async throws -> [ClockingShiftResponse]
    var clockByQr: @Sendable (ClockingQrRequest) async throws -> ClockingQrResponse
    var requestAdjustment: @Sendable (ClockingAdjustmentRequestBody, [UploadFile]) async throws -> ClockingAdjustmentResponse
    var adjustments: @Sendable () async throws -> [ClockingAdjustmentResponse] = { [] }
    var cancelAdjustment: @Sendable (Int) async throws -> Void = { _ in }
    var downloadAttachment: @Sendable (ClockingAttachment) async throws -> URL = { _ in throw APIError.unexpectedBody }
    var updateNotes: @Sendable (Int, String?) async throws -> Void

    static func live(_ client: APIClient) -> ClockingService {
        ClockingService(
            status: { try await client.clockingStatus() },
            timesheets: { try await client.clockingTimesheets(from: $0, to: $1) },
            shifts: { try await client.clockingShifts() },
            clockByQr: { try await client.clockByQr($0) },
            requestAdjustment: { try await client.requestClockingAdjustment($0, attachments: $1) },
            adjustments: { try await client.clockingAdjustments() },
            cancelAdjustment: { try await client.cancelClockingAdjustment(id: $0) },
            downloadAttachment: { try await client.downloadClockingAttachment($0) },
            updateNotes: { try await client.updateClockingNotes(clockingID: $0, notes: $1) }
        )
    }
}
