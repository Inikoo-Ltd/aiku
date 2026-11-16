import Foundation
import Observation

@MainActor
@Observable
final class StaffChatStore {
    static let pollInterval: Duration = .seconds(10)
    static let maxMessageLength = 5000

    let kind: StaffChatContextKind
    let contextID: Int

    private(set) var conversation: StaffChatConversation?
    private(set) var messages: [StaffChatMessage] = []
    private(set) var hasOlder = false
    private(set) var isOpening = false
    private(set) var isLoadingOlder = false
    private(set) var isSending = false
    private(set) var loadError: String?
    private(set) var sendError: String?
    private(set) var isSessionExpired = false
    var draft = "" {
        didSet {
            if draft.count > Self.maxMessageLength {
                draft = String(draft.prefix(Self.maxMessageLength))
            }
            if sendError != nil, draft != oldValue {
                sendError = nil
            }
        }
    }

    @ObservationIgnored private let service: StaffChatService
    @ObservationIgnored private let sleep: @Sendable (Duration) async throws -> Void

    init(
        kind: StaffChatContextKind,
        contextID: Int,
        service: StaffChatService,
        sleep: @escaping @Sendable (Duration) async throws -> Void = { try await Task.sleep(for: $0) }
    ) {
        self.kind = kind
        self.contextID = contextID
        self.service = service
        self.sleep = sleep
    }

    var trimmedDraft: String { draft.trimmingCharacters(in: .whitespacesAndNewlines) }
    var canSend: Bool { conversation != nil && !trimmedDraft.isEmpty && !isSending }
    var participantNames: [String] {
        (conversation?.participants ?? []).compactMap { $0.name?.trimmingCharacters(in: .whitespacesAndNewlines) }.filter { !$0.isEmpty }
    }

    func run() async {
        if conversation == nil {
            guard await open() else { return }
        } else {
            await fetchNewer()
        }
        while !Task.isCancelled, !isSessionExpired {
            do {
                try await sleep(Self.pollInterval)
            } catch {
                return
            }
            await fetchNewer()
        }
    }

    func refresh() async {
        if conversation == nil {
            _ = await open()
        } else {
            await fetchNewer()
        }
    }

    @discardableResult
    func open() async -> Bool {
        guard !isOpening else { return false }
        isOpening = true
        loadError = nil
        defer { isOpening = false }
        do {
            let opened = try await service.open(kind, contextID)
            let page = try await service.messages(opened.ulid, .latest)
            conversation = opened
            messages = Self.merged(page.data, [])
            hasOlder = page.hasMore
            return true
        } catch {
            fail(error)
            return false
        }
    }

    func fetchNewer() async {
        guard let ulid = conversation?.ulid else { return }
        let query: StaffChatPageQuery = messages.last.map { .after(messageID: $0.id) } ?? .latest
        do {
            let page = try await service.messages(ulid, query)
            messages = Self.merged(messages, page.data)
            loadError = nil
        } catch APIError.unauthorized {
            isSessionExpired = true
        } catch {
            return
        }
    }

    func loadOlder() async {
        guard let ulid = conversation?.ulid, let oldest = messages.first?.id, hasOlder, !isLoadingOlder else { return }
        isLoadingOlder = true
        defer { isLoadingOlder = false }
        do {
            let page = try await service.messages(ulid, .before(messageID: oldest))
            messages = Self.merged(page.data, messages)
            hasOlder = page.hasMore
        } catch APIError.unauthorized {
            isSessionExpired = true
        } catch {
            return
        }
    }

    func send() async {
        guard let ulid = conversation?.ulid, canSend else { return }
        let body = trimmedDraft
        isSending = true
        sendError = nil
        defer { isSending = false }
        do {
            let sent = try await service.send(ulid, body)
            messages = Self.merged(messages, [sent])
            draft = ""
        } catch APIError.unauthorized {
            isSessionExpired = true
        } catch where error.isCancellation {
            return
        } catch {
            sendError = StaffChatFormat.problem(error)
        }
    }

    private func fail(_ error: Error) {
        if case APIError.unauthorized = error {
            isSessionExpired = true
        } else if !error.isCancellation {
            loadError = StaffChatFormat.problem(error)
        }
    }

    static func merged(_ first: [StaffChatMessage], _ second: [StaffChatMessage]) -> [StaffChatMessage] {
        var byID: [Int: StaffChatMessage] = [:]
        for message in first + second {
            byID[message.id] = message
        }
        return byID.values.sorted { $0.id < $1.id }
    }
}
