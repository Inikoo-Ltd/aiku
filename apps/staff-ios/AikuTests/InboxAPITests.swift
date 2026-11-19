import Foundation
import Testing
@testable import Aiku

@MainActor
struct InboxAPITests {
    private final class RecordingTransport: HTTPTransport, @unchecked Sendable {
        let responses: [String: (Int, String)]
        private let lock = NSLock()
        private var recorded: [URLRequest] = []

        init(responses: [String: (Int, String)]) {
            self.responses = responses
        }

        var requests: [URLRequest] {
            lock.withLock { recorded }
        }

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            lock.withLock { recorded.append(request) }
            let path = request.url!.path.replacingOccurrences(of: "/app/api/", with: "")
            let (status, body) = responses[path] ?? (404, "")
            return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
        }
    }

    private final class CallRecorder: @unchecked Sendable {
        private let lock = NSLock()
        private var recorded: [String] = []

        var calls: [String] {
            lock.withLock { recorded }
        }

        func record(_ call: String) {
            lock.withLock { recorded.append(call) }
        }
    }

    private static let assignedID = "9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e61"
    private static let unreachableID = "9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e62"
    private static let cancelledID = "9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e63"

    private static let inbox = #"""
    {"data":[
      {"id":"9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e61","kind":"delivery_note","event":"assigned","title":"GB592347 assigned to you","body":"Louis Perez assigned GB592347 to you.","created_at":"2026-11-18T09:30:00+00:00","read_at":null,"target":{"screen":"delivery_note","id":101,"reference":"GB592347","state":"handling","warehouse_id":1,"type_group":null}},
      {"id":"9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e62","kind":"delivery_note","event":"assigned","title":"GB592300 assigned to you","body":"Aiku assigned GB592300 to you.","created_at":"2026-11-17T16:05:00+00:00","read_at":null,"target":null},
      {"id":"9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e63","kind":"pallet_return","event":"cancelled","title":"412170-awf-r1591 was cancelled","body":"","created_at":"2026-11-18T08:00:00+00:00","read_at":"2026-11-18T08:10:00+00:00","target":{"screen":"pallet_return","id":6385,"reference":"412170-awf-r1591","state":"cancel","warehouse_id":"2","type_group":"pallets"}},
      {"kind":"delivery_note","title":"no id"}
    ],"meta":{"current_page":1,"last_page":2,"per_page":30,"total":33},"unread":2}
    """#

    private static let secondPage = #"""
    {"data":[
      {"id":"9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e63","kind":"pallet_return","event":"cancelled","title":"duplicate","body":"","created_at":"2026-11-18T08:00:00+00:00","read_at":"2026-11-18T08:10:00+00:00","target":null},
      {"id":"9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e64","kind":"picking_session","event":"ready_again","title":"PS-0042 is ready again","body":"The waiting on PS-0042 is resolved.","created_at":"2026-11-16T07:00:00+00:00","read_at":null,"target":{"screen":"picking_session","id":42,"reference":"PS-0042","state":"handling","warehouse_id":1,"type_group":null}}
    ],"meta":{"current_page":2,"last_page":2,"per_page":30,"total":33},"unread":3}
    """#

    private static let warehousesJSON = #"""
    [
      {"id":1,"code":"WH1","name":"Main Warehouse","organisation":{"id":1,"name":"AW UK"},"can":{"view_locations":true,"edit_locations":false}},
      {"id":2,"code":"ES1","name":"Almacén","organisation":{"id":3,"name":"AW Spain"},"can":{"view_locations":true,"edit_locations":false}}
    ]
    """#

    private static let now = ISO8601DateFormatter().date(from: "2026-11-18T10:30:00Z")!

    private func decode<T: Decodable>(_ type: T.Type, _ json: String) throws -> T {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(T.self, from: Data(json.utf8))
    }

    private func client(_ responses: [String: (Int, String)]) -> (RecordingTransport, APIClient) {
        let transport = RecordingTransport(responses: responses)
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: transport,
            tokenProvider: { "1|tok" }
        )
        return (transport, client)
    }

    private func service(
        pages: [Int: MobileInboxPage],
        unread: Int = 0,
        failingWrites: Bool = false,
        recorder: CallRecorder = CallRecorder()
    ) -> InboxService {
        InboxService(
            page: { page in
                recorder.record("page \(page)")
                guard let result = pages[page] else { throw APIError.offline }
                return result
            },
            unreadCount: {
                recorder.record("unread-count")
                return unread
            },
            markRead: { id in
                recorder.record("read \(id)")
                if failingWrites { throw APIError.http(statusCode: 500) }
                return MobileInboxNotification(id: id, title: "Read on the server", readAt: "2026-11-18T10:30:00+00:00")
            },
            markUnread: { id in
                recorder.record("unread \(id)")
                if failingWrites { throw APIError.http(statusCode: 500) }
                return MobileInboxNotification(id: id, title: "Unread on the server")
            },
            markAllRead: {
                recorder.record("read-all")
                if failingWrites { throw APIError.http(statusCode: 500) }
                return 0
            }
        )
    }

    @Test func listDecodesTheServerRowsInOrderAndSkipsRowsWithoutAnID() async throws {
        let (transport, client) = client(["mobile/inbox": (200, Self.inbox)])

        let page = try await client.fetchInbox(page: 1)

        let request = try #require(transport.requests.first)
        #expect(request.httpMethod == "GET")
        #expect(request.url?.path == "/app/api/mobile/inbox")
        let query = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)?.queryItems ?? []
        #expect(query.contains(URLQueryItem(name: "filter", value: "all")))
        #expect(query.contains(URLQueryItem(name: "page", value: "1")))
        #expect(query.contains(URLQueryItem(name: "per_page", value: "30")))

        #expect(page.notifications.map(\.id) == [Self.assignedID, Self.unreachableID, Self.cancelledID])
        #expect(page.currentPage == 1)
        #expect(page.lastPage == 2)
        #expect(page.unread == 2)

        let assigned = page.notifications[0]
        #expect(assigned.kind == "delivery_note")
        #expect(assigned.event == "assigned")
        #expect(assigned.title == "GB592347 assigned to you")
        #expect(assigned.body == "Louis Perez assigned GB592347 to you.")
        #expect(assigned.createdAt == "2026-11-18T09:30:00+00:00")
        #expect(assigned.isUnread)
        #expect(assigned.target == MobileInboxTarget(kind: .deliveryNote, id: 101, reference: "GB592347", state: "handling", warehouseID: 1, typeGroup: nil))

        #expect(page.notifications[1].target == nil)
        #expect(page.notifications[2].isUnread == false)
        #expect(page.notifications[2].target?.warehouseID == 2)
        #expect(page.notifications[2].target?.typeGroup == "pallets")
        #expect(InboxFormat.headline(page.notifications[2]) == "412170-awf-r1591 was cancelled")
        #expect(InboxFormat.detail(page.notifications[2]) == nil)
        #expect(InboxFormat.detail(assigned) == "Louis Perez assigned GB592347 to you.")
    }

    @Test func readUnreadAndReadAllPostToTheInboxRoutes() async throws {
        let row = #"{"data":{"id":"9f3c2a54-7d1e-4b9a-9a3e-2f1c0b8d7e61","kind":"delivery_note","event":"assigned","title":"GB592347 assigned to you","body":"x","created_at":"2026-11-18T09:30:00+00:00","read_at":"2026-11-18T10:00:00+00:00","target":null}}"#
        let (transport, client) = client([
            "mobile/inbox/\(Self.assignedID)/read": (200, row),
            "mobile/inbox/\(Self.assignedID)/unread": (200, row.replacingOccurrences(of: #""read_at":"2026-11-18T10:00:00+00:00""#, with: #""read_at":null"#)),
            "mobile/inbox/read-all": (200, #"{"data":{"unread":0}}"#),
            "mobile/inbox/unread-count": (200, #"{"data":{"unread":100}}"#)
        ])

        let read = try await client.markInboxRead(id: Self.assignedID)
        let unread = try await client.markInboxUnread(id: Self.assignedID)
        let remaining = try await client.markAllInboxRead()
        let count = try await client.fetchInboxUnreadCount()

        #expect(read.readAt == "2026-11-18T10:00:00+00:00")
        #expect(unread.isUnread)
        #expect(remaining == 0)
        #expect(count == 100)
        #expect(transport.requests.map(\.httpMethod) == ["POST", "POST", "POST", "GET"])
        #expect(transport.requests.map { $0.url?.path } == [
            "/app/api/mobile/inbox/\(Self.assignedID)/read",
            "/app/api/mobile/inbox/\(Self.assignedID)/unread",
            "/app/api/mobile/inbox/read-all",
            "/app/api/mobile/inbox/unread-count"
        ])
    }

    @Test func unreadCountAboveNinetyNineReadsNinetyNinePlus() {
        #expect(InboxFormat.unreadText(100) == "99+")
        #expect(InboxFormat.unreadText(99) == "99")
        #expect(InboxFormat.kicker(unread: 100) == "99+ unread")
        #expect(InboxFormat.kicker(unread: 3) == "3 unread")
        #expect(InboxFormat.kicker(unread: 0) == "All read")
    }

    @Test func badgesCarryTheInboxUnreadCountToTheTabBar() async {
        let inbox = ShellInboxStore(service: service(pages: [:]))
        let badges = WorkBadgesStore(inbox: inbox)
        let (_, client) = client(["mobile/warehouses/1/badges": (200, #"{"data":{"my_work":4,"goods_out_unassigned":null,"inbox_unread":100}}"#)])

        await badges.refresh(warehouseID: 1, apiClient: client)

        #expect(badges.badges.inboxUnread == 100)
        #expect(inbox.unreadCount == 100)
        #expect(InboxFormat.unreadText(inbox.unreadCount) == "99+")
    }

    @Test func unreadCountEndpointFeedsTheBadgeWithoutAWarehouse() async {
        let inbox = ShellInboxStore(service: service(pages: [:], unread: 7))

        await inbox.refreshUnread()

        #expect(inbox.unreadCount == 7)
    }

    @Test func noticeWithoutTargetIsTextOnlyAndStillMarksRead() async throws {
        let page = try decode(MobileInboxPage.self, Self.inbox)
        let warehouses = try decode([MobileWarehouse].self, Self.warehousesJSON)
        let recorder = CallRecorder()
        let store = ShellInboxStore(service: service(pages: [1: page], recorder: recorder))
        await store.load()

        let textOnly = page.notifications[1]
        #expect(InboxFormat.opening(for: textOnly, currentWarehouseID: 1, warehouses: warehouses) == .textOnly)

        let marked = await store.markRead(textOnly.id)

        #expect(marked)
        #expect(store.notifications.first { $0.id == textOnly.id }?.isUnread == false)
        #expect(recorder.calls == ["page 1", "read \(textOnly.id)"])
    }

    @Test func noticeInAnotherWarehouseSwitchesOnlyWhenTheUserHasIt() throws {
        let page = try decode(MobileInboxPage.self, Self.inbox)
        let warehouses = try decode([MobileWarehouse].self, Self.warehousesJSON)
        let assigned = page.notifications[0]
        let elsewhere = page.notifications[2]
        let assignedTarget = try #require(assigned.target)
        let elsewhereTarget = try #require(elsewhere.target)

        #expect(InboxFormat.opening(for: assigned, currentWarehouseID: 1, warehouses: warehouses) == .record(assignedTarget, switchTo: nil))

        let opening = InboxFormat.opening(for: elsewhere, currentWarehouseID: 1, warehouses: warehouses)
        #expect(opening == .record(elsewhereTarget, switchTo: warehouses[1]))
        #expect(InboxFormat.switchedText(warehouses[1]) == "Switched to AW Spain · Almacén to open this")

        #expect(InboxFormat.opening(for: elsewhere, currentWarehouseID: 1, warehouses: [warehouses[0]]) == .unavailable)
        #expect(InboxFormat.opening(for: assigned, currentWarehouseID: nil, warehouses: []) == .unavailable)
    }

    @Test func markReadUnreadAndAllUpdateTheRowsAndTheCount() async throws {
        let page = try decode(MobileInboxPage.self, Self.inbox)
        let recorder = CallRecorder()
        let store = ShellInboxStore(service: service(pages: [1: page], recorder: recorder))

        await store.load()
        #expect(store.hasLoaded)
        #expect(store.unreadCount == 2)

        let read = await store.markRead(Self.assignedID)
        #expect(read)
        #expect(store.notifications[0].isUnread == false)
        #expect(store.notifications[0].title == "Read on the server")
        #expect(store.unreadCount == 1)

        let alreadyRead = await store.markRead(Self.assignedID)
        #expect(alreadyRead)
        #expect(store.unreadCount == 1)

        let unread = await store.markUnread(Self.assignedID)
        #expect(unread)
        #expect(store.notifications[0].isUnread)
        #expect(store.unreadCount == 2)

        let all = await store.markAllRead()
        #expect(all)
        #expect(store.notifications.allSatisfy { !$0.isUnread })
        #expect(store.unreadCount == 0)
        #expect(recorder.calls == ["page 1", "read \(Self.assignedID)", "unread \(Self.assignedID)", "read-all"])
    }

    @Test func failedWritesPutTheRowsBack() async throws {
        let page = try decode(MobileInboxPage.self, Self.inbox)
        let store = ShellInboxStore(service: service(pages: [1: page], failingWrites: true))
        await store.load()

        let read = await store.markRead(Self.assignedID)
        #expect(!read)
        #expect(store.notifications[0].isUnread)
        #expect(store.unreadCount == 2)

        let unread = await store.markUnread(Self.cancelledID)
        #expect(!unread)
        #expect(store.notifications[2].readAt == "2026-11-18T08:10:00+00:00")
        #expect(store.unreadCount == 2)

        let all = await store.markAllRead()
        #expect(!all)
        #expect(store.notifications.map(\.isUnread) == [true, true, false])
        #expect(store.unreadCount == 2)
    }

    @Test func pagesAppendWithoutDuplicatesAndFailuresAreReported() async throws {
        let first = try decode(MobileInboxPage.self, Self.inbox)
        let second = try decode(MobileInboxPage.self, Self.secondPage)
        let store = ShellInboxStore(service: service(pages: [1: first, 2: second]))

        await store.load()
        #expect(store.canLoadMore)
        await store.loadMore()

        #expect(store.notifications.count == 4)
        #expect(store.notifications.last?.target?.kind == .pickingSession)
        #expect(store.unreadCount == 3)
        #expect(!store.canLoadMore)

        let offline = ShellInboxStore(service: service(pages: [:]))
        await offline.load()
        #expect(offline.loadError == "The inbox needs internet. Check Wi-Fi and try again.")
        #expect(!offline.isEmpty)

        let empty = ShellInboxStore(service: service(pages: [1: MobileInboxPage(notifications: [], unread: 0)]))
        await empty.load()
        #expect(empty.isEmpty)
        #expect(empty.unreadCount == 0)
    }

    @Test func signOutClearsTheInbox() async throws {
        let page = try decode(MobileInboxPage.self, Self.inbox)
        let store = ShellInboxStore(service: service(pages: [1: page]))
        await store.load()

        store.reset()

        #expect(store.notifications.isEmpty)
        #expect(!store.hasLoaded)
        #expect(store.unreadCount == 0)
    }

    @Test func rowsGroupByDayAndKeepTheIconCircleVisibleWhenUnread() throws {
        let page = try decode(MobileInboxPage.self, Self.inbox)
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone(identifier: "UTC")!

        #expect(InboxFormat.notifications(page.notifications, in: .today, now: Self.now, calendar: calendar).map(\.id) == [Self.assignedID, Self.cancelledID])
        #expect(InboxFormat.notifications(page.notifications, in: .earlier, now: Self.now, calendar: calendar).map(\.id) == [Self.unreachableID])

        #expect(InboxFormat.look(kind: "delivery_note", event: "assigned") == InboxLook(icon: .deliveryNote, tone: .info))
        #expect(InboxFormat.look(kind: "pallet_return", event: "cancelled") == InboxLook(icon: .close, tone: .danger))
        #expect(InboxFormat.look(kind: "picking_session", event: "something_new") == InboxLook(icon: .trolley, tone: .neutral))
        #expect(InboxFormat.iconBlendsIntoRow(tone: .info, isUnread: true))
        #expect(!InboxFormat.iconBlendsIntoRow(tone: .info, isUnread: false))
        #expect(!InboxFormat.iconBlendsIntoRow(tone: .danger, isUnread: true))
    }
}
