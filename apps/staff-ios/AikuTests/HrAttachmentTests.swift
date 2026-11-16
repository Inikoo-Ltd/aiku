import Foundation
import Testing
@testable import Aiku

private actor UploadCalls {
    var leaveFiles: [[UploadFile]] = []
    var adjustmentFiles: [[UploadFile]] = []
    var cancelled: [Int] = []
    var replaced: [(Int, Int)] = []

    func recordLeave(_ files: [UploadFile]) { leaveFiles.append(files) }
    func recordAdjustment(_ files: [UploadFile]) { adjustmentFiles.append(files) }
    func recordCancel(_ id: Int) { cancelled.append(id) }
    func recordReplace(_ id: Int, _ count: Int) { replaced.append((id, count)) }
}

private func pdf(_ name: String = "certificate.pdf", bytes: Int = 10) -> UploadFile {
    UploadFile(name: name, mimeType: "application/pdf", data: Data(repeating: 1, count: bytes))
}

private func leave(_ id: Int, type: String, status: String) -> LeaveResponse {
    LeaveResponse(id: id, type: type, typeLabel: type, startDate: "2026-11-20", endDate: "2026-11-20", durationDays: 1, session: "Full", reason: "x", status: status, statusLabel: status.capitalized, rejectionReason: nil, approvalTotalSteps: 1, approvalCompletedSteps: 0, approverName: nil)
}

private func adjustment(_ id: Int, status: String) -> ClockingAdjustmentResponse {
    ClockingAdjustmentResponse(id: id, date: "2026-11-10", timesheetId: nil, originalStartTimeLocal: nil, originalEndTimeLocal: nil, requestedStartTimeLocal: "08:00", requestedEndTimeLocal: nil, reason: "Machine down", status: status, statusLabel: status.capitalized, approvalComment: nil)
}

private let leaveTypes = LeavesResponse(
    leaves: [leave(1, type: "sick", status: "pending"), leave(2, type: "holiday", status: "pending"), leave(3, type: "sick", status: "approved")],
    balance: nil, annualSubmittedDays: nil, annualRemainingAfterSubmission: nil,
    typeOptions: [
        .init(code: "sick", label: "Sick leave", category: "medical", maxDaysPerYear: nil, value: 1),
        .init(code: "holiday", label: "Holiday", category: "annual", maxDaysPerYear: 14, value: 1),
    ],
    holidays: []
)

@MainActor
struct HrAttachmentTests {
    @Test("Files are limited to three PDF, JPG or PNG files of 5 MB each")
    func uploadRules() {
        #expect(UploadFile.problem(adding: [pdf()], to: []) == nil)
        #expect(UploadFile.problem(adding: [pdf(), pdf()], to: [pdf(), pdf()]) == "You can add up to 3 files.")
        #expect(UploadFile.problem(adding: [pdf(bytes: UploadFile.maxBytes + 1)], to: []) == "Each file must be 5 MB or smaller.")
        #expect(UploadFile.problem(adding: [UploadFile(name: "a.gif", mimeType: "image/gif", data: Data())], to: []) == "Use PDF, JPG or PNG files.")
        #expect(UploadFile.mimeType(forExtension: "JPG") == "image/jpeg")
    }

    @Test("A multipart form carries the fields and files with the boundary")
    func multipartBody() throws {
        var form = MultipartForm(boundary: "B")
        form.add("reason", "Sick")
        form.add("timesheet_id", nil)
        form.add(files: [pdf("note.pdf", bytes: 3)], as: "attachments[]")

        let text = String(decoding: form.data, as: UTF8.self)

        #expect(form.contentType == "multipart/form-data; boundary=B")
        #expect(text.contains("name=\"reason\"\r\n\r\nSick\r\n"))
        #expect(!text.contains("timesheet_id"))
        #expect(text.contains("name=\"attachments[]\"; filename=\"note.pdf\"\r\nContent-Type: application/pdf"))
        #expect(text.hasSuffix("--B--\r\n"))
        let request = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "http://localhost/app/api")!)).makeRequest(for: form.endpoint("mobile/clocking/leaves"))
        #expect(request.value(forHTTPHeaderField: "Content-Type") == "multipart/form-data; boundary=B")
    }

    @Test("Only medical leave sends files, and only pending medical leave can replace them")
    func medicalLeaveAttachments() async throws {
        let calls = UploadCalls()
        let store = LeaveStore(service: LeaveService(
            list: { leaveTypes },
            request: { body, files in
                await calls.recordLeave(files)
                return leave(9, type: body.type, status: "pending")
            },
            replaceAttachments: { id, files in
                await calls.recordReplace(id, files.count)
                return leave(id, type: "sick", status: "pending")
            }
        ), today: { "2026-11-16" })
        await store.load()

        _ = await store.request(type: "holiday", start: "2026-11-17", end: "2026-11-17", reason: "Trip", attachments: [pdf()])
        _ = await store.request(type: "sick", start: "2026-11-17", end: "2026-11-17", reason: "Flu", attachments: [pdf()])

        #expect(await calls.leaveFiles.map(\.count) == [0, 1])
        #expect(store.canReplaceCertificate(store.leaves.first { $0.id == 1 }!))
        #expect(store.canReplaceCertificate(leave(2, type: "holiday", status: "pending")) == false)
        #expect(store.canReplaceCertificate(leave(3, type: "sick", status: "approved")) == false)
        #expect(await store.replaceCertificate(of: leave(1, type: "sick", status: "pending"), with: [])?.message == "Choose at least one file.")
        #expect(await store.replaceCertificate(of: leave(1, type: "sick", status: "pending"), with: [pdf(), pdf()]) == nil)
        #expect(await calls.replaced.first?.0 == 1)
    }

    @Test("Cancelling a pending adjustment removes it from the list")
    func cancelAdjustment() async {
        let calls = UploadCalls()
        let store = ClockingStore(service: ClockingService(
            status: { throw APIError.offline },
            timesheets: { _, _ in throw APIError.offline },
            shifts: { [] },
            clockByQr: { _ in throw APIError.offline },
            requestAdjustment: { _, files in
                await calls.recordAdjustment(files)
                return adjustment(8, status: "pending")
            },
            adjustments: { [adjustment(7, status: "pending")] },
            cancelAdjustment: { id in await calls.recordCancel(id) },
            updateNotes: { _, _ in }
        ))
        await store.loadAdjustments()

        let problem = await store.cancelAdjustment(adjustment(7, status: "pending"))

        #expect(problem == nil)
        #expect(await calls.cancelled == [7])
    }

    @Test("A refused cancel shows the server message")
    func refusedCancel() async {
        let store = ClockingStore(service: ClockingService(
            status: { throw APIError.offline },
            timesheets: { _, _ in throw APIError.offline },
            shifts: { [] },
            clockByQr: { _ in throw APIError.offline },
            requestAdjustment: { _, _ in adjustment(1, status: "pending") },
            cancelAdjustment: { _ in throw FormValidationError(message: "Only a pending request can be cancelled.", fields: ["status": "Only a pending request can be cancelled."]) },
            updateNotes: { _, _ in }
        ))

        #expect(await store.cancelAdjustment(adjustment(7, status: "approved")) == "Only a pending request can be cancelled.")
    }

    @Test("Attachments decode with their download id")
    func attachmentsDecode() throws {
        let json = #"{"id":3,"name":"cert.pdf","url":"https://aiku.io/app/api/mobile/clocking/attachments/01JABCDEF"}"#
        let attachment = try JSONDecoder().decode(ClockingAttachment.self, from: Data(json.utf8))

        #expect(attachment.ulid == "01JABCDEF")
    }
}
