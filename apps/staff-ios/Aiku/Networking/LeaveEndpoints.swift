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
    var attachments: [ClockingAttachment]? = nil
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

    func requestLeave(_ body: LeaveRequestBody, attachments: [UploadFile] = []) async throws -> LeaveResponse {
        if attachments.isEmpty {
            return try await send(.jsonBody("mobile/clocking/leaves", method: .post, body), as: DataEnvelope<LeaveResponse>.self).value
        }
        var form = MultipartForm()
        form.add("type", body.type)
        form.add("start_date", body.startDate)
        form.add("end_date", body.endDate)
        form.add("reason", body.reason)
        form.add(files: attachments, as: "attachments[]")
        return try await send(form.endpoint("mobile/clocking/leaves"), as: DataEnvelope<LeaveResponse>.self).value
    }

    func replaceLeaveAttachments(leaveID: Int, attachments: [UploadFile]) async throws -> LeaveResponse {
        var form = MultipartForm()
        form.add(files: attachments, as: "attachments[]")
        return try await send(form.endpoint("mobile/clocking/leaves/\(leaveID)"), as: DataEnvelope<LeaveResponse>.self).value
    }
}
