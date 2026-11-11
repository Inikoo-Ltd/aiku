import Foundation
import Observation

enum OvertimeStatus: String, Sendable {
    case pending
    case approved
    case rejected
    case cancelled
    case recorded

    var tone: Tone {
        switch self {
        case .pending: .warn
        case .approved: .success
        case .rejected: .danger
        case .cancelled: .neutral
        case .recorded: .info
        }
    }
}

struct OvertimeFormError: Error, Equatable, Sendable {
    let message: String
}

struct OvertimeDraft: Equatable, Sendable {
    var typeID: Int?
    var date: String
    var startMinutes: Int
    var endMinutes: Int
    var reason: String
}

struct OvertimeSummary: Equatable, Sendable {
    let approvedMinutes: Int
    let pendingMinutes: Int
    let lieuMinutes: Int
}

enum OvertimeCalculator {
    static let minutesPerDay = 1440

    static func durationMinutes(start: Int, end: Int) -> Int {
        if end == start { return 0 }
        return end > start ? end - start : end + minutesPerDay - start
    }

    static func endsNextDay(start: Int, end: Int) -> Bool {
        end < start
    }

    static func durationLabel(_ minutes: Int?) -> String {
        guard let minutes, minutes > 0 else { return "—" }
        let hours = minutes / 60
        let rest = minutes % 60
        if hours > 0 && rest > 0 { return "\(hours)h \(rest)m" }
        if hours > 0 { return "\(hours)h" }
        return "\(rest)m"
    }

    static func timeLabel(_ minutes: Int) -> String {
        let wrapped = ((minutes % minutesPerDay) + minutesPerDay) % minutesPerDay
        return String(format: "%02d:%02d", wrapped / 60, wrapped % 60)
    }

    static func minutesOfDay(_ iso: String?, in timeZone: TimeZone) -> Int? {
        ClockingMapper.secondsSinceMidnight(iso, in: timeZone).map { $0 / 60 }
    }

    static func isoString(day: String, minutes: Int, in timeZone: TimeZone) -> String? {
        let parts = day.split(separator: "-").compactMap { Int($0) }
        guard parts.count == 3 else { return nil }
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        guard let midnight = calendar.date(from: DateComponents(year: parts[0], month: parts[1], day: parts[2])),
              let moment = calendar.date(byAdding: .minute, value: minutes, to: midnight) else { return nil }
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        formatter.timeZone = timeZone
        return formatter.string(from: moment)
    }

    static func timeRange(start: String?, end: String?, duration: Int?, in timeZone: TimeZone) -> String? {
        guard let startMinutes = minutesOfDay(start, in: timeZone) else { return nil }
        let endMinutes = minutesOfDay(end, in: timeZone) ?? duration.map { startMinutes + $0 }
        guard let endMinutes else { return timeLabel(startMinutes) }
        let nextDay = endMinutes >= minutesPerDay || endsNextDay(start: startMinutes, end: endMinutes)
        return "\(timeLabel(startMinutes)) – \(timeLabel(endMinutes))\(nextDay ? " (next day)" : "")"
    }
}

struct OvertimeService: Sendable {
    var list: @Sendable () async throws -> OvertimeRequestsResponse
    var request: @Sendable (OvertimeRequestBody) async throws -> OvertimeRequestResponse
    var update: @Sendable (Int, OvertimeRequestBody) async throws -> OvertimeRequestResponse

    static func live(_ client: APIClient) -> OvertimeService {
        OvertimeService(
            list: { try await client.overtimeRequests() },
            request: { try await client.requestOvertime($0) },
            update: { try await client.updateOvertime(id: $0, $1) }
        )
    }
}

@MainActor
@Observable
final class OvertimeStore {
    static let shared = OvertimeStore(service: .live(.shared), timeZone: { ClockingStore.shared.timeZone })

    private(set) var requests: [OvertimeRequestResponse] = []
    private(set) var typeOptions: [OvertimeRequestsResponse.TypeOption] = []
    private(set) var isLoading = false
    private(set) var hasLoaded = false
    private(set) var loadError: String?

    @ObservationIgnored private let service: OvertimeService
    @ObservationIgnored private let timeZoneProvider: @MainActor () -> TimeZone
    @ObservationIgnored private let clock: @Sendable () -> Date

    init(
        service: OvertimeService,
        timeZone: @escaping @MainActor () -> TimeZone = { .current },
        clock: @escaping @Sendable () -> Date = { .now }
    ) {
        self.service = service
        self.timeZoneProvider = timeZone
        self.clock = clock
    }

    var timeZone: TimeZone { timeZoneProvider() }

    var today: String { ClockingMapper.dayString(clock(), in: timeZone) }

    var pending: [OvertimeRequestResponse] {
        newestFirst(requests.filter { $0.status == OvertimeStatus.pending.rawValue })
    }

    var history: [OvertimeRequestResponse] {
        newestFirst(requests.filter { $0.status != OvertimeStatus.pending.rawValue })
    }

    var monthSummary: OvertimeSummary {
        let month = String(today.prefix(7))
        let inMonth = requests.filter { ($0.requestedDate ?? "").hasPrefix(month) }
        let approved = inMonth.filter { $0.status == OvertimeStatus.approved.rawValue || $0.status == OvertimeStatus.recorded.rawValue }
        return OvertimeSummary(
            approvedMinutes: approved.reduce(0) { $0 + ($1.recordedDurationMinutes ?? $1.requestedDurationMinutes ?? 0) },
            pendingMinutes: inMonth.filter { $0.status == OvertimeStatus.pending.rawValue }.reduce(0) { $0 + ($1.requestedDurationMinutes ?? 0) },
            lieuMinutes: approved.reduce(0) { $0 + ($1.lieuRequestedMinutes ?? 0) }
        )
    }

    func newDraft() -> OvertimeDraft {
        OvertimeDraft(typeID: typeOptions.count == 1 ? typeOptions.first?.value : nil, date: today, startMinutes: 17 * 60, endMinutes: 17 * 60, reason: "")
    }

    func draft(for request: OvertimeRequestResponse) -> OvertimeDraft {
        let start = OvertimeCalculator.minutesOfDay(request.requestedStartAt, in: timeZone) ?? 0
        let end = OvertimeCalculator.minutesOfDay(request.requestedEndAt, in: timeZone) ?? (start + (request.requestedDurationMinutes ?? 0)) % OvertimeCalculator.minutesPerDay
        return OvertimeDraft(typeID: request.overtimeTypeId, date: request.requestedDate ?? today, startMinutes: start, endMinutes: end, reason: request.reason ?? "")
    }

    func load() async {
        guard !isLoading else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await service.list()
            requests = response.overtimeRequests
            typeOptions = response.typeOptions
            loadError = nil
            hasLoaded = true
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func validate(_ draft: OvertimeDraft) -> OvertimeFormError? {
        guard let typeID = draft.typeID, typeOptions.contains(where: { $0.value == typeID }) else {
            return OvertimeFormError(message: "Choose an overtime type")
        }
        if ClockingMapper.parseDay(draft.date) == nil { return OvertimeFormError(message: "Choose the date you worked") }
        if OvertimeCalculator.durationMinutes(start: draft.startMinutes, end: draft.endMinutes) <= 0 {
            return OvertimeFormError(message: "Duration must be greater than zero.")
        }
        return nil
    }

    func body(for draft: OvertimeDraft) -> OvertimeRequestBody? {
        guard validate(draft) == nil, let typeID = draft.typeID else { return nil }
        let duration = OvertimeCalculator.durationMinutes(start: draft.startMinutes, end: draft.endMinutes)
        guard let startAt = OvertimeCalculator.isoString(day: draft.date, minutes: draft.startMinutes, in: timeZone),
              let endAt = OvertimeCalculator.isoString(day: draft.date, minutes: draft.startMinutes + duration, in: timeZone) else { return nil }
        let reason = draft.reason.trimmingCharacters(in: .whitespacesAndNewlines)
        return OvertimeRequestBody(
            overtimeTypeId: typeID,
            requestedDate: draft.date,
            requestedStartAt: startAt,
            requestedEndAt: endAt,
            requestedDurationMinutes: duration,
            reason: reason.isEmpty ? nil : reason
        )
    }

    func submit(_ draft: OvertimeDraft, editing: OvertimeRequestResponse? = nil) async -> Result<OvertimeRequestResponse, OvertimeFormError> {
        if let error = validate(draft) { return .failure(error) }
        if let editing, editing.status != OvertimeStatus.pending.rawValue {
            return .failure(OvertimeFormError(message: "Only pending overtime requests can be edited."))
        }
        guard let body = body(for: draft) else { return .failure(OvertimeFormError(message: "Choose the date you worked")) }
        do {
            let saved: OvertimeRequestResponse
            if let editing {
                saved = try await service.update(editing.id, body)
                requests = requests.map { $0.id == saved.id ? saved : $0 }
            } else {
                saved = try await service.request(body)
                requests.insert(saved, at: 0)
            }
            await load()
            return .success(saved)
        } catch {
            return .failure(OvertimeFormError(message: error.localizedDescription))
        }
    }

    private func newestFirst(_ list: [OvertimeRequestResponse]) -> [OvertimeRequestResponse] {
        list.sorted { ($0.requestedDate ?? "", $0.id) > ($1.requestedDate ?? "", $1.id) }
    }
}
