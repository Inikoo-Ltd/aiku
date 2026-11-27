import Foundation

struct LiveEvent: Equatable, Sendable {
    let channel: String
    let event: String
    let data: String
}

enum LiveRecordType: String, Sendable {
    case deliveryNote = "delivery_note"
    case pickingSession = "picking_session"
    case customerReturn = "return"
    case palletReturn = "pallet_return"
    case stockDelivery = "stock_delivery"
}

enum LiveChannelName {
    static func personal(userID: Int) -> String {
        "private-grp.personal.\(userID)"
    }

    static func personal(signedInUserID userID: Int?) -> String? {
        guard let userID, userID > 0 else { return nil }
        return personal(userID: userID)
    }

    static func record(_ type: LiveRecordType, id: Int) -> String {
        "private-grp.mobile.\(type.rawValue).\(id)"
    }

    static func record(_ type: LiveRecordType, loadedID id: Int?) -> String? {
        guard let id, id > 0 else { return nil }
        return record(type, id: id)
    }

    static func dispatchingBacklog(organisationID: Int) -> String {
        "private-grp.org.\(organisationID).dispatching-backlog"
    }

    static func dispatchingBacklog(selectedOrganisationID organisationID: Int?) -> String? {
        organisationID.map(dispatchingBacklog(organisationID:))
    }
}

enum LiveEventName {
    static let recordChanged = "record-changed"
    static let permissionsChanged = "permissions-changed"
    static let personalNotification = "personal-notification"
    static let taskBadges = "task-badges-update"
    static let ticketBadges = "ticket-badges-update"
    static let staffMessage = "staff-message"
    static let staffMessageReaction = "staff-message-reaction"
    static let staffMessageTranslated = "staff-message-translated"
    static let dispatchingBacklog = "dispatching-backlog-changed"

    static let record: Set<String> = [recordChanged]
    static let backlog: Set<String> = [dispatchingBacklog]
    static let badges: Set<String> = [personalNotification, taskBadges, ticketBadges]
    static let inbox: Set<String> = [personalNotification]
    static let permissions: Set<String> = [permissionsChanged]
    static let chat: Set<String> = [staffMessage, staffMessageReaction, staffMessageTranslated]
}

enum LiveFrame: Equatable, Sendable {
    case established(socketID: String, activityTimeout: Int)
    case ping
    case event(LiveEvent)
    case ignored

    static let defaultActivityTimeout = 30

    private static let connectionEstablished = "pusher:connection_established"
    private static let pingEvent = "pusher:ping"
    private static let protocolPrefix = "pusher:"
    private static let internalPrefix = "pusher_internal:"

    static func parse(_ text: String) -> LiveFrame {
        guard let frame = jsonObject(text), let event = frame["event"] as? String else { return .ignored }
        let data = dataText(frame["data"])
        switch event {
        case connectionEstablished:
            return established(data)
        case pingEvent:
            return .ping
        default:
            guard !event.hasPrefix(protocolPrefix), !event.hasPrefix(internalPrefix), let channel = frame["channel"] as? String, !channel.isEmpty else {
                return .ignored
            }
            return .event(LiveEvent(channel: channel, event: event, data: data))
        }
    }

    private static func established(_ data: String) -> LiveFrame {
        guard let payload = jsonObject(data), let socketID = payload["socket_id"] as? String, !socketID.isEmpty else { return .ignored }
        let timeout = (payload["activity_timeout"] as? NSNumber)?.intValue ?? defaultActivityTimeout
        return .established(socketID: socketID, activityTimeout: timeout > 0 ? timeout : defaultActivityTimeout)
    }

    private static func jsonObject(_ text: String) -> [String: Any]? {
        (try? JSONSerialization.jsonObject(with: Data(text.utf8))) as? [String: Any]
    }

    private static func dataText(_ value: Any?) -> String {
        guard let value, !(value is NSNull) else { return "" }
        if let text = value as? String {
            return text
        }
        guard let data = try? JSONSerialization.data(withJSONObject: value, options: [.fragmentsAllowed, .sortedKeys]) else { return "" }
        return String(decoding: data, as: UTF8.self)
    }
}

enum LiveMessage {
    static let ping = message("pusher:ping", [:])
    static let pong = message("pusher:pong", [:])

    static func subscribe(channel: String, auth: String) -> String {
        message("pusher:subscribe", ["auth": auth, "channel": channel])
    }

    static func unsubscribe(channel: String) -> String {
        message("pusher:unsubscribe", ["channel": channel])
    }

    private static func message(_ event: String, _ data: [String: String]) -> String {
        let frame: [String: Any] = ["event": event, "data": data]
        guard let encoded = try? JSONSerialization.data(withJSONObject: frame, options: [.sortedKeys]) else { return "" }
        return String(decoding: encoded, as: UTF8.self)
    }
}
