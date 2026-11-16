import Foundation
import Testing
@testable import Aiku

private actor StaffChatCalls {
    var opened: [(StaffChatContextKind, Int)] = []
    var queries: [StaffChatPageQuery] = []
    var sent: [String] = []
    var pages: [StaffChatMessagePage] = []
    var sendFailure: APIError?

    init(pages: [StaffChatMessagePage] = [], sendFailure: APIError? = nil) {
        self.pages = pages
        self.sendFailure = sendFailure
    }

    func recordOpen(_ kind: StaffChatContextKind, _ id: Int) { opened.append((kind, id)) }

    func nextPage(_ query: StaffChatPageQuery) -> StaffChatMessagePage {
        queries.append(query)
        return pages.isEmpty ? StaffChatMessagePage(data: []) : pages.removeFirst()
    }

    func recordSend(_ body: String) throws -> StaffChatMessage {
        sent.append(body)
        if let sendFailure { throw sendFailure }
        return StaffChatMessage(id: 99, user: .init(id: 1, name: "Artha"), isMine: true, body: body, createdAt: "2026-11-16T10:00:00+00:00")
    }
}

private func message(_ id: Int, mine: Bool = false) -> StaffChatMessage {
    StaffChatMessage(id: id, user: .init(id: mine ? 1 : 2, name: mine ? "Artha" : "Sarah"), isMine: mine, body: "Message \(id)", createdAt: "2026-11-16T09:\(String(format: "%02d", id % 60)):00+00:00")
}

private func service(_ calls: StaffChatCalls, openFailure: APIError? = nil) -> StaffChatService {
    StaffChatService(
        open: { kind, id in
            await calls.recordOpen(kind, id)
            if let openFailure { throw openFailure }
            return StaffChatConversation(ulid: "01ULID", contextLabel: "AR24811", participants: [.init(id: 2, name: "Sarah"), .init(id: 3, name: " ")])
        },
        messages: { _, query in await calls.nextPage(query) },
        send: { _, body in try await calls.recordSend(body) }
    )
}

@MainActor
struct StaffChatTests {
    @Test func endpointsMatchTheMobileRoutes() {
        let open = Endpoint.openStaffChat(kind: .pickingSession, contextID: 42)
        #expect(open.path == "mobile/picking-sessions/42/chat")
        #expect(open.method == .post)
        #expect(Endpoint.openStaffChat(kind: .deliveryNote, contextID: 7).path == "mobile/delivery-notes/7/chat")
        #expect(Endpoint.staffChatMessages(ulid: "U1", query: .before(messageID: 30)).queryItems == [URLQueryItem(name: "before_id", value: "30")])
        #expect(Endpoint.staffChatMessages(ulid: "U1", query: .after(messageID: 31)).queryItems == [URLQueryItem(name: "after_id", value: "31")])
        #expect(Endpoint.staffChatMessages(ulid: "U1", query: .latest).queryItems.isEmpty)
        let send = Endpoint.sendStaffChatMessage(ulid: "U1", body: "Hi")
        #expect(send.path == "mobile/chat/U1/messages")
        #expect(send.body.flatMap { String(data: $0, encoding: .utf8) } == #"{"body":"Hi"}"#)
        #expect(StaffChatContextKind(rawValue: "delivery_note") == .deliveryNote)
        #expect(StaffChatContextKind(rawValue: "picking_session") == .pickingSession)
    }

    @Test func decodesTheServerPayloads() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let page = try decoder.decode(StaffChatMessagePage.self, from: Data(#"{"data":[{"id":5,"user":{"id":2,"name":"Sarah"},"is_mine":false,"body":"Hello","image_url":null,"created_at":"2026-11-16T09:05:00+00:00"}],"has_more":true}"#.utf8))
        #expect(page.hasMore)
        #expect(page.data.first?.user?.name == "Sarah")
        let conversation = try decoder.decode(DataEnvelope<StaffChatConversation>.self, from: Data(#"{"data":{"ulid":"01U","name":null,"context_label":"AR1","participants":[{"id":2,"name":"Sarah"}],"unread_count":3}}"#.utf8)).value
        #expect(conversation.contextLabel == "AR1")
        #expect(conversation.unreadCount == 3)
    }

    @Test func openingLoadsTheLatestPageInOrder() async {
        let calls = StaffChatCalls(pages: [StaffChatMessagePage(data: [message(3), message(1), message(2)], hasMore: true)])
        let store = StaffChatStore(kind: .deliveryNote, contextID: 7, service: service(calls))

        #expect(await store.open())
        #expect(store.messages.map(\.id) == [1, 2, 3])
        #expect(store.hasOlder)
        #expect(store.participantNames == ["Sarah"])
        #expect(await calls.opened.first?.1 == 7)
        #expect(await calls.queries == [.latest])
    }

    @Test func refreshFetchesOnlyNewerMessagesAndOlderPagesPrepend() async {
        let calls = StaffChatCalls(pages: [
            StaffChatMessagePage(data: [message(10), message(11)], hasMore: true),
            StaffChatMessagePage(data: [message(11), message(12)]),
            StaffChatMessagePage(data: [message(8), message(9)], hasMore: false),
        ])
        let store = StaffChatStore(kind: .pickingSession, contextID: 3, service: service(calls))
        await store.open()
        await store.refresh()
        await store.loadOlder()

        #expect(store.messages.map(\.id) == [8, 9, 10, 11, 12])
        #expect(store.hasOlder == false)
        #expect(await calls.queries == [.latest, .after(messageID: 11), .before(messageID: 10)])
    }

    @Test func sendingTrimsAppendsAndClearsTheDraft() async {
        let calls = StaffChatCalls()
        let store = StaffChatStore(kind: .deliveryNote, contextID: 7, service: service(calls))
        await store.open()
        store.draft = "   "
        #expect(store.canSend == false)
        store.draft = "  Is the address right?  "
        await store.send()

        #expect(await calls.sent == ["Is the address right?"])
        #expect(store.messages.last?.isMine == true)
        #expect(store.draft.isEmpty)
    }

    @Test func aRefusedMessageKeepsTheDraftAndShowsTheServerMessage() async {
        let calls = StaffChatCalls(sendFailure: .validation(message: "The body field must not be greater than 5000 characters.", code: nil))
        let store = StaffChatStore(kind: .deliveryNote, contextID: 7, service: service(calls))
        await store.open()
        store.draft = "Hello"
        await store.send()

        #expect(store.sendError == "The body field must not be greater than 5000 characters.")
        #expect(store.draft == "Hello")
        store.draft = "Hello again"
        #expect(store.sendError == nil)
    }

    @Test func draftIsCappedAtTheServerLimit() {
        let store = StaffChatStore(kind: .deliveryNote, contextID: 7, service: service(StaffChatCalls()))
        store.draft = String(repeating: "a", count: 5005)

        #expect(store.draft.count == StaffChatStore.maxMessageLength)
    }

    @Test func openFailuresSayWhatWentWrong() async {
        let offline = StaffChatStore(kind: .deliveryNote, contextID: 7, service: service(StaffChatCalls(), openFailure: .offline))
        #expect(await offline.open() == false)
        #expect(offline.loadError == "The chat needs internet. Check Wi-Fi and try again.")

        let expired = StaffChatStore(kind: .deliveryNote, contextID: 7, service: service(StaffChatCalls(), openFailure: .unauthorized))
        await expired.open()
        #expect(expired.isSessionExpired)
        #expect(expired.loadError == nil)
    }

    @Test func runPollsForNewerMessagesUntilCancelled() async {
        let calls = StaffChatCalls(pages: [
            StaffChatMessagePage(data: [message(1)]),
            StaffChatMessagePage(data: [message(2)]),
        ])
        let ticks = TickCounter()
        let store = StaffChatStore(kind: .deliveryNote, contextID: 7, service: service(calls)) { _ in
            if await ticks.tick() > 1 { throw CancellationError() }
        }
        await store.run()

        #expect(store.messages.map(\.id) == [1, 2])
        #expect(await calls.queries == [.latest, .after(messageID: 1)])
    }

    @Test func metaShowsSenderAndTime() {
        let now = StaffChatFormat.parse("2026-11-16T12:00:00+00:00")!
        let utc = TimeZone(identifier: "UTC")!
        #expect(StaffChatFormat.meta(message(5), now: now, timeZone: utc) == "Sarah · 09:05")
        let older = StaffChatMessage(id: 1, user: nil, body: "x", createdAt: "2026-11-12T08:30:00.000000Z")
        #expect(StaffChatFormat.meta(older, now: now, timeZone: utc) == "12 Nov 08:30")
    }
}

private actor TickCounter {
    var count = 0

    func tick() -> Int {
        count += 1
        return count
    }
}
