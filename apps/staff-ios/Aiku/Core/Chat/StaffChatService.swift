import Foundation

struct StaffChatService: Sendable {
    var open: @Sendable (StaffChatContextKind, Int) async throws -> StaffChatConversation
    var messages: @Sendable (String, StaffChatPageQuery) async throws -> StaffChatMessagePage
    var send: @Sendable (String, String) async throws -> StaffChatMessage

    static func live(_ client: APIClient) -> StaffChatService {
        StaffChatService(
            open: { try await client.openStaffChat(kind: $0, contextID: $1) },
            messages: { try await client.staffChatMessages(ulid: $0, query: $1) },
            send: { try await client.sendStaffChatMessage(ulid: $0, body: $1) }
        )
    }
}

enum StaffChatFormat {
    static func meta(_ message: StaffChatMessage, now: Date = .now, timeZone: TimeZone = .current) -> String {
        let name = message.user?.name?.trimmingCharacters(in: .whitespacesAndNewlines)
        let parts = [name?.isEmpty == false ? name : nil, sentAt(message.createdAt, now: now, timeZone: timeZone)]
        return parts.compactMap { $0 }.joined(separator: " · ")
    }

    static func sentAt(_ iso: String?, now: Date = .now, timeZone: TimeZone = .current) -> String? {
        guard let iso, let date = parse(iso) else { return nil }
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        let formatter = DateFormatter()
        formatter.locale = L10n.dateLocale
        formatter.timeZone = timeZone
        if calendar.isDate(date, inSameDayAs: now) {
            formatter.dateFormat = "HH:mm"
        } else if calendar.component(.year, from: date) == calendar.component(.year, from: now) {
            formatter.dateFormat = "d MMM HH:mm"
        } else {
            formatter.dateFormat = "d MMM yyyy HH:mm"
        }
        return formatter.string(from: date)
    }

    static func parse(_ iso: String) -> Date? {
        let formatter = ISO8601DateFormatter()
        if let date = formatter.date(from: iso) { return date }
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter.date(from: iso)
    }

    static func problem(_ error: Error) -> String {
        switch error as? APIError {
        case .offline: "The chat needs internet. Check Wi-Fi and try again."
        case .http(403): "You can’t open this conversation."
        case .http(404): "This conversation was not found."
        default: error.localizedDescription
        }
    }
}
