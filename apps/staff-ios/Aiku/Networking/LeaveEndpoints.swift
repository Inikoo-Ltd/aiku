import Foundation

struct LeavesResponse: Decodable, Sendable {
    struct Balance: Decodable, Equatable, Sendable {
        let annualDays: Double
        let annualUsed: Double
        let annualRemaining: Double
        let medicalUsed: Double
        let unpaidUsed: Double
    }

    struct TypeOption: Decodable, Equatable, Sendable {
        let code: String
        let label: String
        let category: String?
        let maxDaysPerYear: Double?
        let value: Double?
    }

    struct Holiday: Decodable, Equatable, Sendable {
        let date: String
        let label: String?
    }

    let leaves: [LeaveResponse]
    let balance: Balance?
    let annualSubmittedDays: Double?
    let annualRemainingAfterSubmission: Double?
    let typeOptions: [TypeOption]
    let holidays: [Holiday]
}

struct LeaveResponse: Decodable, Equatable, Identifiable, Sendable {
    let id: Int
    let type: String?
    let typeLabel: String?
    let startDate: String?
    let endDate: String?
    let durationDays: Double?
    let session: String?
    let reason: String?
    let status: String?
    let statusLabel: String?
    let rejectionReason: String?
    let approvalTotalSteps: Int?
    let approvalCompletedSteps: Int?
    let approverName: String?
}

struct LeaveRequestBody: Encodable, Sendable {
    let type: String
    let startDate: String
    let endDate: String
    let reason: String
}

extension APIClient {
    func leaves() async throws -> LeavesResponse {
        try await send(Endpoint(path: "mobile/clocking/leaves", queryItems: [URLQueryItem(name: "per_page", value: "100")]), as: DataEnvelope<LeavesResponse>.self).value
    }

    func requestLeave(_ body: LeaveRequestBody) async throws -> LeaveResponse {
        try await send(.jsonBody("mobile/clocking/leaves", method: .post, body), as: DataEnvelope<LeaveResponse>.self).value
    }
}
