import Foundation

struct ClockingStatusResponse: Decodable, Sendable {
    struct Employee: Decodable, Sendable {
        struct Organisation: Decodable, Sendable {
            let id: Int
            let code: String?
            let name: String?
        }

        let id: Int
        let name: String?
        let organisation: Organisation?
    }

    let employee: Employee?
    let timezone: String?
    let status: String
    let since: String?
    let today: ClockingTimesheetResponse?
    let sessions: [ClockingSessionResponse]
    let methods: [String]
    let code: String?
    let hasShifts: Bool
    let canEditNotes: Bool
}

struct ClockingSessionResponse: Decodable, Equatable, Sendable {
    let id: Int
    let sequence: Int?
    let isOpen: Bool
    let startsAt: String?
    let endsAt: String?
    let duration: Int?
    let clockIn: ClockingRecordResponse?
    let clockOut: ClockingRecordResponse?
}

struct ClockingRecordResponse: Decodable, Equatable, Sendable {
    struct Place: Decodable, Equatable, Sendable {
        let id: Int
        let name: String
    }

    let id: Int
    let clockedAt: String?
    let type: String?
    let isLate: Bool?
    let notes: String?
    var workplace: Place? = nil
    var clockingMachine: Place? = nil
}

struct ClockingTimesheetResponse: Decodable, Equatable, Sendable {
    var id: Int? = nil
    let date: String
    let firstClockIn: String?
    let lastClockOut: String?
    let workingSeconds: Int
    let breaksSeconds: Int
    var sessions: [ClockingSessionResponse]? = nil
}

struct ClockingTimesheetsResponse: Decodable, Sendable {
    let from: String
    let to: String
    let timezone: String?
    let targetSeconds: Int?
    let timesheets: [ClockingTimesheetResponse]
}

struct ClockingShiftResponse: Decodable, Equatable, Sendable {
    let id: Int
    let name: String
    let type: String?
    let isActive: Bool?
}

struct ClockingQrResponse: Decodable, Sendable {
    struct Machine: Decodable, Sendable {
        let id: Int
        let name: String?
    }

    struct WorkingHours: Decodable, Sendable {
        let start: String?
        let end: String?
        let name: String?
    }

    struct Record: Decodable, Sendable {
        let id: Int
        let clockedAt: String?
        let type: String?
        let isLate: Bool?
    }

    let success: Bool
    let message: String?
    let machine: Machine?
    let isVisiting: Bool?
    let workingHours: WorkingHours?
    let clocking: Record?
}

struct ClockingQrRequest: Encodable, Sendable {
    let qrCode: String
    let latitude: Double?
    let longitude: Double?
    var workScheduleId: Int?
}

struct ClockingAdjustmentRequestBody: Encodable, Sendable {
    let date: String
    var timesheetId: Int?
    var requestedStartAt: String?
    var requestedEndAt: String?
    let reason: String
}

struct ClockingAdjustmentResponse: Decodable, Equatable, Identifiable, Sendable {
    let id: Int
    let date: String?
    let timesheetId: Int?
    let originalStartTimeLocal: String?
    let originalEndTimeLocal: String?
    let requestedStartTimeLocal: String?
    let requestedEndTimeLocal: String?
    let reason: String?
    let status: String?
    let statusLabel: String?
    let approvalComment: String?
}

struct ClockingAdjustmentsResponse: Decodable, Sendable {
    let adjustments: [ClockingAdjustmentResponse]
}

private struct ClockingNotesBody: Encodable, Sendable {
    let notes: String?
}

extension Endpoint {
    static func jsonBody<Body: Encodable>(_ path: String, method: HTTPMethod, _ body: Body) -> Endpoint {
        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase
        return Endpoint(path: path, method: method, body: try? encoder.encode(body))
    }

    static let clockingStatus = Endpoint(path: "mobile/clocking")
    static let clockingShifts = Endpoint(path: "mobile/clocking/shifts")

    static func clockingTimesheets(from: String, to: String) -> Endpoint {
        Endpoint(path: "mobile/clocking/timesheets", queryItems: [URLQueryItem(name: "from", value: from), URLQueryItem(name: "to", value: to)])
    }
}

extension APIClient {
    func clockingStatus() async throws -> ClockingStatusResponse {
        try await send(.clockingStatus, as: DataEnvelope<ClockingStatusResponse>.self).value
    }

    func clockingTimesheets(from: String, to: String) async throws -> ClockingTimesheetsResponse {
        try await send(.clockingTimesheets(from: from, to: to), as: DataEnvelope<ClockingTimesheetsResponse>.self).value
    }

    func clockingShifts() async throws -> [ClockingShiftResponse] {
        try await send(.clockingShifts, as: DataEnvelope<[ClockingShiftResponse]>.self).value
    }

    func clockByQr(_ request: ClockingQrRequest) async throws -> ClockingQrResponse {
        try await send(.jsonBody("mobile/clocking/qr", method: .post, request), as: ClockingQrResponse.self)
    }

    func requestClockingAdjustment(_ body: ClockingAdjustmentRequestBody) async throws -> ClockingAdjustmentResponse {
        try await send(.jsonBody("mobile/clocking/adjustments", method: .post, body), as: DataEnvelope<ClockingAdjustmentResponse>.self).value
    }

    func clockingAdjustments() async throws -> [ClockingAdjustmentResponse] {
        try await send(Endpoint(path: "mobile/clocking/adjustments", queryItems: [URLQueryItem(name: "per_page", value: "100")]), as: DataEnvelope<ClockingAdjustmentsResponse>.self).value.adjustments
    }

    func updateClockingNotes(clockingID: Int, notes: String?) async throws {
        _ = try await send(.jsonBody("mobile/clocking/\(clockingID)/notes", method: .patch, ClockingNotesBody(notes: notes)))
    }
}
