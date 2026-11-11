import Foundation

struct OvertimeRequestsResponse: Decodable, Sendable {
    struct TypeOption: Decodable, Equatable, Sendable {
        let value: Int
        let label: String
    }

    let overtimeRequests: [OvertimeRequestResponse]
    let typeOptions: [TypeOption]
}

struct OvertimeRequestResponse: Decodable, Equatable, Identifiable, Sendable {
    let id: Int
    let overtimeTypeId: Int?
    let overtimeTypeName: String?
    let requestedDate: String?
    let requestedStartAt: String?
    let requestedEndAt: String?
    let requestedDurationMinutes: Int?
    let recordedStartAt: String?
    let recordedEndAt: String?
    let recordedDurationMinutes: Int?
    let lieuRequestedMinutes: Int?
    let reason: String?
    let status: String?
    let statusLabel: String?
    let approvedAt: String?
    let rejectedAt: String?
}

struct OvertimeRequestBody: Encodable, Equatable, Sendable {
    let overtimeTypeId: Int
    let requestedDate: String
    let requestedStartAt: String
    let requestedEndAt: String
    let requestedDurationMinutes: Int
    let reason: String?
}

extension APIClient {
    func overtimeRequests() async throws -> OvertimeRequestsResponse {
        try await send(Endpoint(path: "mobile/clocking/overtime-requests", queryItems: [URLQueryItem(name: "per_page", value: "100")]), as: DataEnvelope<OvertimeRequestsResponse>.self).value
    }

    func requestOvertime(_ body: OvertimeRequestBody) async throws -> OvertimeRequestResponse {
        try await send(.jsonBody("mobile/clocking/overtime-requests", method: .post, body), as: DataEnvelope<OvertimeRequestResponse>.self).value
    }

    func updateOvertime(id: Int, _ body: OvertimeRequestBody) async throws -> OvertimeRequestResponse {
        try await send(.jsonBody("mobile/clocking/overtime-requests/\(id)", method: .patch, body), as: DataEnvelope<OvertimeRequestResponse>.self).value
    }
}
