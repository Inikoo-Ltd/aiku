import Foundation
import Observation

enum LeaveStatus: String, Sendable {
    case pending
    case approved
    case rejected

    var tone: Tone {
        switch self {
        case .pending: .warn
        case .approved: .success
        case .rejected: .danger
        }
    }
}

struct LeaveFormError: Error, Equatable, Sendable {
    let message: String
}

enum LeaveCalculator {
    static func dates(from start: String, to end: String) -> [String] {
        guard let first = ClockingMapper.parseDay(start), let last = ClockingMapper.parseDay(end), first <= last else { return [] }
        var result: [String] = []
        var day = first
        let utc = TimeZone(identifier: "UTC")!
        while day <= last {
            result.append(ClockingMapper.dayString(day, in: utc))
            day = day.addingTimeInterval(86_400)
        }
        return result
    }

    static func workingDays(from start: String, to end: String, holidays: Set<String>) -> Int {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone(identifier: "UTC")!
        return dates(from: start, to: end).filter { date in
            guard let parsed = ClockingMapper.parseDay(date) else { return false }
            return !calendar.isDateInWeekend(parsed) && !holidays.contains(date)
        }.count
    }

    static func days(type: LeavesResponse.TypeOption?, start: String, end: String, holidays: Set<String>) -> Double {
        Double(workingDays(from: start, to: end, holidays: holidays)) * (type?.value ?? 1)
    }

    static func isSingleDay(_ type: LeavesResponse.TypeOption?) -> Bool {
        (type?.value ?? 1) < 1
    }

    static func daysLabel(_ days: Double) -> String {
        let text = days.truncatingRemainder(dividingBy: 1) == 0 ? String(Int(days)) : String(format: "%.1f", days)
        return days == 1 ? "1 day" : "\(text) days"
    }

    static func rangeLabel(start: String?, end: String?) -> String {
        guard let start, let first = ClockingMapper.parseDay(start) else { return "—" }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_GB")
        formatter.timeZone = TimeZone(identifier: "UTC")!
        formatter.dateFormat = "EEE dd MMM yyyy"
        let startText = formatter.string(from: first)
        guard let end, end != start, let last = ClockingMapper.parseDay(end) else { return startText }
        return "\(startText) – \(formatter.string(from: last))"
    }
}

struct LeaveService: Sendable {
    var list: @Sendable () async throws -> LeavesResponse
    var request: @Sendable (LeaveRequestBody) async throws -> LeaveResponse

    static func live(_ client: APIClient) -> LeaveService {
        LeaveService(list: { try await client.leaves() }, request: { try await client.requestLeave($0) })
    }
}

@MainActor
@Observable
final class LeaveStore {
    static let shared = LeaveStore(service: .live(.shared))

    private(set) var leaves: [LeaveResponse] = []
    private(set) var balance: LeavesResponse.Balance?
    private(set) var remainingAfterSubmission: Double?
    private(set) var typeOptions: [LeavesResponse.TypeOption] = []
    private(set) var holidays: [LeavesResponse.Holiday] = []
    private(set) var isLoading = false
    private(set) var hasLoaded = false
    private(set) var loadError: String?

    @ObservationIgnored private let service: LeaveService
    @ObservationIgnored private let today: @Sendable () -> String

    init(service: LeaveService, today: @escaping @Sendable () -> String = { ClockingMapper.dayString(.now, in: .current) }) {
        self.service = service
        self.today = today
    }

    var holidayDates: Set<String> { Set(holidays.map(\.date)) }

    var pending: [LeaveResponse] { leaves.filter { $0.status == LeaveStatus.pending.rawValue } }

    var upcoming: [LeaveResponse] {
        leaves.filter { $0.status != LeaveStatus.pending.rawValue && ($0.endDate ?? "") >= today() }
            .sorted { ($0.startDate ?? "") < ($1.startDate ?? "") }
    }

    var past: [LeaveResponse] {
        leaves.filter { $0.status != LeaveStatus.pending.rawValue && ($0.endDate ?? "") < today() }
            .sorted { ($0.startDate ?? "") > ($1.startDate ?? "") }
    }

    var upcomingHolidays: [LeavesResponse.Holiday] {
        holidays.filter { $0.date >= today() }.sorted { $0.date < $1.date }
    }

    func load() async {
        guard !isLoading else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await service.list()
            leaves = response.leaves
            balance = response.balance
            remainingAfterSubmission = response.annualRemainingAfterSubmission
            typeOptions = response.typeOptions
            holidays = response.holidays
            loadError = nil
            hasLoaded = true
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func validate(type: String?, start: String, end: String, reason: String) -> LeaveFormError? {
        guard let type, typeOptions.contains(where: { $0.code == type }) else { return LeaveFormError(message: "Choose a leave type") }
        if start < today() { return LeaveFormError(message: "Leave can’t start in the past") }
        if end < start { return LeaveFormError(message: "The last day must be on or after the first day") }
        if LeaveCalculator.workingDays(from: start, to: end, holidays: holidayDates) == 0 {
            return LeaveFormError(message: "These dates are only weekends or holidays")
        }
        if reason.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty { return LeaveFormError(message: "Add a reason for your manager") }
        return nil
    }

    func request(type: String?, start: String, end: String, reason: String) async -> Result<LeaveResponse, LeaveFormError> {
        if let error = validate(type: type, start: start, end: end, reason: reason) { return .failure(error) }
        let option = typeOptions.first { $0.code == type }
        let body = LeaveRequestBody(
            type: type ?? "",
            startDate: start,
            endDate: LeaveCalculator.isSingleDay(option) ? start : end,
            reason: reason.trimmingCharacters(in: .whitespacesAndNewlines)
        )
        do {
            let leave = try await service.request(body)
            leaves.insert(leave, at: 0)
            await load()
            return .success(leave)
        } catch {
            return .failure(LeaveFormError(message: error.localizedDescription))
        }
    }
}
