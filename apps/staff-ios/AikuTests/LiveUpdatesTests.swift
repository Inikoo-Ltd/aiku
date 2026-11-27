import Foundation
import Testing
@testable import Aiku

@MainActor
private final class FakeLiveSocket: LiveSocket {
    private(set) var sent: [String] = []
    private(set) var isClosed = false
    let onMessage: @MainActor (String) -> Void
    let onClose: @MainActor () -> Void

    init(onMessage: @escaping @MainActor (String) -> Void, onClose: @escaping @MainActor () -> Void) {
        self.onMessage = onMessage
        self.onClose = onClose
    }

    func send(_ text: String) {
        sent.append(text)
    }

    func close() {
        isClosed = true
    }

    func sentEvents(_ name: String) -> [[String: String]] {
        sent.compactMap { text in
            guard let frame = (try? JSONSerialization.jsonObject(with: Data(text.utf8))) as? [String: Any], frame["event"] as? String == name else { return nil }
            return frame["data"] as? [String: String] ?? [:]
        }
    }
}

@MainActor
private final class FakeLiveSocketOpener: LiveSocketOpening {
    private(set) var urls: [URL] = []
    private(set) var sockets: [FakeLiveSocket] = []

    var current: FakeLiveSocket? { sockets.last }

    func open(_ url: URL, onMessage: @escaping @MainActor (String) -> Void, onClose: @escaping @MainActor () -> Void) -> LiveSocket {
        urls.append(url)
        let socket = FakeLiveSocket(onMessage: onMessage, onClose: onClose)
        sockets.append(socket)
        return socket
    }
}

private actor LiveCalls {
    var servers: [Result<RealtimeServer?, APIError>]
    private(set) var serverCalls = 0
    private(set) var authorized: [String] = []
    var refusedChannels: Set<String> = []

    init(servers: [Result<RealtimeServer?, APIError>]) {
        self.servers = servers
    }

    func server() throws -> RealtimeServer? {
        serverCalls += 1
        let next = servers.count > 1 ? servers.removeFirst() : servers.first ?? .success(nil)
        return try next.get()
    }

    func authorize(_ socketID: String, _ channel: String) -> String? {
        authorized.append("\(socketID) \(channel)")
        return refusedChannels.contains(channel) ? nil : "app-key:signed-\(channel)"
    }

    func refuse(_ channel: String) {
        refusedChannels.insert(channel)
    }
}

private actor ManualSleeper {
    private(set) var requested: [Duration] = []
    private var waiting: [(Duration, CheckedContinuation<Void, Error>)] = []

    func sleep(_ duration: Duration) async throws {
        requested.append(duration)
        try await withCheckedThrowingContinuation { waiting.append((duration, $0)) }
    }

    func wake(_ duration: Duration) {
        let woken = waiting.filter { $0.0 == duration }
        waiting.removeAll { $0.0 == duration }
        woken.forEach { $0.1.resume() }
    }

    func wakeAll() {
        let woken = waiting
        waiting = []
        woken.forEach { $0.1.resume() }
    }

    func isWaiting(for duration: Duration) -> Bool {
        waiting.contains { $0.0 == duration }
    }
}

private let server = RealtimeServer(key: "app-key", cluster: "ap1", host: "ws.aiku.io", port: 443, scheme: "https")
private let established = #"{"event":"pusher:connection_established","data":"{\"socket_id\":\"123.456\",\"activity_timeout\":30}"}"#

@MainActor
private func until(_ condition: () async -> Bool) async -> Bool {
    for _ in 0..<500 {
        if await condition() { return true }
        await Task.yield()
    }
    return await condition()
}

@MainActor
private func makeLive(
    _ calls: LiveCalls,
    opener: FakeLiveSocketOpener,
    sleeper: ManualSleeper = ManualSleeper()
) -> LiveUpdates {
    LiveUpdates(
        service: LiveService(
            server: { try await calls.server() },
            authorize: { await calls.authorize($0, $1) }
        ),
        opener: opener,
        clientVersion: "1.4",
        sleep: { try await sleeper.sleep($0) }
    )
}

@MainActor
struct LiveUpdatesTests {
    @Test func theServerComesFromTheMobileAPIAndIsOpenedWithProtocol7() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let decoded = try decoder.decode(RealtimeServerEnvelope.self, from: Data(#"{"data":{"key":"app-key","cluster":"mt1","host":"ws.aiku.io","port":443,"scheme":"https"}}"#.utf8)).data

        #expect(decoded?.socketURL(client: "aiku-ios", version: "1.4")?.absoluteString == "wss://ws.aiku.io/app/app-key?protocol=7&client=aiku-ios&version=1.4&flash=false")
        #expect(RealtimeServer(key: "k", host: "192.168.0.140", port: 6001, scheme: "http").socketURL(client: "a", version: "1")?.absoluteString == "ws://192.168.0.140:6001/app/k?protocol=7&client=a&version=1&flash=false")
        #expect(RealtimeServer(key: "k", host: "ws.local", port: 80, scheme: "http").socketURL(client: "a", version: "1")?.absoluteString == "ws://ws.local/app/k?protocol=7&client=a&version=1&flash=false")
        #expect(RealtimeServer(key: "k", host: "ws.local", port: 6002).socketURL(client: "a", version: "1")?.absoluteString == "wss://ws.local:6002/app/k?protocol=7&client=a&version=1&flash=false")
        #expect(try decoder.decode(RealtimeServerEnvelope.self, from: Data(#"{"data":null}"#.utf8)).data == nil)
        #expect(try decoder.decode(RealtimeServerEnvelope.self, from: Data(#"{"data":{"key":"k","host":"h","port":443}}"#.utf8)).data?.scheme == "https")

        #expect(Endpoint.realtimeServer.path == "mobile/realtime")
        let auth = Endpoint.authorizeRealtimeChannel(socketID: "1.2", channel: "private-grp.personal.5")
        #expect(auth.path == "mobile/broadcasting/auth")
        #expect(auth.method == .post)
        let body = try #require(auth.body.flatMap { try JSONSerialization.jsonObject(with: $0) as? [String: String] })
        #expect(body == ["socket_id": "1.2", "channel_name": "private-grp.personal.5"])
    }

    @Test func framesAreReadAsThePusherProtocolSendsThem() {
        #expect(LiveFrame.parse(established) == .established(socketID: "123.456", activityTimeout: 30))
        #expect(LiveFrame.parse(#"{"event":"pusher:connection_established","data":{"socket_id":"9.8"}}"#) == .established(socketID: "9.8", activityTimeout: LiveFrame.defaultActivityTimeout))
        #expect(LiveFrame.parse(#"{"event":"pusher:connection_established","data":"{\"activity_timeout\":30}"}"#) == .ignored)
        #expect(LiveFrame.parse(#"{"event":"pusher:ping","data":{}}"#) == .ping)
        #expect(LiveFrame.parse(#"{"event":"permissions-changed","channel":"private-grp.personal.5","data":"{\"user_id\":5}"}"#) == .event(LiveEvent(channel: "private-grp.personal.5", event: "permissions-changed", data: #"{"user_id":5}"#)))
        #expect(LiveFrame.parse(#"{"event":"record-changed","channel":"private-grp.mobile.delivery_note.7","data":{"type":"delivery_note","id":7}}"#) == .event(LiveEvent(channel: "private-grp.mobile.delivery_note.7", event: "record-changed", data: #"{"id":7,"type":"delivery_note"}"#)))
        #expect(LiveFrame.parse(#"{"event":"pusher_internal:subscription_succeeded","channel":"private-grp.personal.5","data":"{}"}"#) == .ignored)
        #expect(LiveFrame.parse(#"{"event":"pusher:error","data":{"code":4200,"message":"Reconnect"}}"#) == .ignored)
        #expect(LiveFrame.parse(#"{"event":"record-changed","data":"{}"}"#) == .ignored)
        #expect(LiveFrame.parse("not json") == .ignored)

        #expect(LiveMessage.subscribe(channel: "private-grp.personal.5", auth: "k:s") == #"{"data":{"auth":"k:s","channel":"private-grp.personal.5"},"event":"pusher:subscribe"}"#)
        #expect(LiveMessage.unsubscribe(channel: "private-grp.personal.5") == #"{"data":{"channel":"private-grp.personal.5"},"event":"pusher:unsubscribe"}"#)
        #expect(LiveMessage.pong == #"{"data":{},"event":"pusher:pong"}"#)
    }

    @Test func channelNamesFollowTheServersChannels() {
        #expect(LiveChannelName.personal(userID: 5) == "private-grp.personal.5")
        #expect(LiveChannelName.personal(signedInUserID: 0) == nil)
        #expect(LiveChannelName.personal(signedInUserID: nil) == nil)
        #expect(LiveChannelName.record(.stockDelivery, id: 12) == "private-grp.mobile.stock_delivery.12")
        #expect(LiveChannelName.record(.customerReturn, id: 3) == "private-grp.mobile.return.3")
        #expect(LiveChannelName.record(.deliveryNote, loadedID: nil) == nil)
        #expect(LiveChannelName.dispatchingBacklog(organisationID: 3) == "private-grp.org.3.dispatching-backlog")
    }

    @Test func aChannelEventReachesOnlyTheScreensListeningToThatChannel() async throws {
        let calls = LiveCalls(servers: [.success(nil)])
        let live = makeLive(calls, opener: FakeLiveSocketOpener())
        var personal = live.events(on: LiveChannelName.personal(userID: 5)).makeAsyncIterator()

        #expect(!live.isConnected)
        live.receive(established)
        live.receive(#"{"event":"pusher_internal:subscription_succeeded","channel":"private-grp.personal.5","data":"{}"}"#)
        live.receive(#"{"event":"permissions-changed","channel":"private-grp.personal.5","data":"{\"user_id\":5}"}"#)
        live.receive(#"{"event":"permissions-changed","channel":"private-grp.personal.6","data":"{}"}"#)
        live.receive("not json")
        live.receive(#"{"event":"task-badges-update","channel":"private-grp.personal.5","data":"{}"}"#)

        #expect(live.isConnected)
        #expect(live.socketID == "123.456")
        #expect(await until { await calls.authorized == ["123.456 private-grp.personal.5"] })
        #expect(await personal.next() == LiveEvent(channel: "private-grp.personal.5", event: "permissions-changed", data: #"{"user_id":5}"#))
        #expect(await personal.next()?.event == "task-badges-update")
    }

    @Test func aChannelIsSubscribedOnceAndUnsubscribedWithItsLastListener() async throws {
        let calls = LiveCalls(servers: [.success(server)])
        let opener = FakeLiveSocketOpener()
        let live = makeLive(calls, opener: opener)
        live.setSignedIn(true)
        live.setForeground(true)
        #expect(await until { opener.current != nil })
        let socket = try #require(opener.current)
        #expect(opener.urls.first?.absoluteString == "wss://ws.aiku.io/app/app-key?protocol=7&client=aiku-ios&version=1.4&flash=false")

        let note = LiveChannelName.record(.deliveryNote, id: 7)
        live.retain(note)
        socket.onMessage(established)
        #expect(await until { socket.sentEvents("pusher:subscribe").count == 1 })
        live.retain(note)
        #expect(live.wanted[note] == 2)

        live.release(note)
        #expect(socket.sentEvents("pusher:unsubscribe").isEmpty)
        live.release(note)
        #expect(live.wanted[note] == nil)
        #expect(socket.sentEvents("pusher:unsubscribe") == [["channel": note]])
        #expect(socket.sentEvents("pusher:subscribe") == [["auth": "app-key:signed-\(note)", "channel": note]])

        let backlog = LiveChannelName.dispatchingBacklog(organisationID: 3)
        let listening = Task {
            for await _ in live.events(on: backlog) {}
        }
        #expect(await until { socket.sentEvents("pusher:subscribe").count == 2 })
        #expect(live.wanted[backlog] == 1)
        listening.cancel()
        #expect(await until { live.wanted[backlog] == nil })
        #expect(socket.sentEvents("pusher:unsubscribe").last == ["channel": backlog])

        await calls.refuse(LiveChannelName.personal(userID: 9))
        live.retain(LiveChannelName.personal(userID: 9))
        #expect(await until { await calls.authorized.count == 3 })
        await Task.yield()
        #expect(socket.sentEvents("pusher:subscribe").count == 2)
    }

    @Test func channelsWantedBeforeTheConnectionAreSubscribedOnceItIsEstablished() async throws {
        let calls = LiveCalls(servers: [.success(server)])
        let opener = FakeLiveSocketOpener()
        let live = makeLive(calls, opener: opener)
        live.retain(LiveChannelName.personal(userID: 5))
        live.retain(LiveChannelName.record(.pickingSession, id: 2))
        #expect(await calls.authorized.isEmpty)

        live.setForeground(true)
        live.setSignedIn(true)
        #expect(await until { opener.current != nil })
        let socket = try #require(opener.current)
        socket.onMessage(established)

        #expect(await until { socket.sentEvents("pusher:subscribe").count == 2 })
        #expect(Set(socket.sentEvents("pusher:subscribe").compactMap { $0["channel"] }) == ["private-grp.personal.5", "private-grp.mobile.picking_session.2"])
    }

    @Test func pingsAreAnsweredAndSentWhenTheServersActivityTimeoutPasses() async throws {
        let calls = LiveCalls(servers: [.success(server)])
        let opener = FakeLiveSocketOpener()
        let sleeper = ManualSleeper()
        let live = makeLive(calls, opener: opener, sleeper: sleeper)
        live.setSignedIn(true)
        live.setForeground(true)
        #expect(await until { opener.current != nil })
        let socket = try #require(opener.current)

        socket.onMessage(#"{"event":"pusher:ping","data":{}}"#)
        #expect(socket.sentEvents("pusher:pong").count == 1)

        socket.onMessage(#"{"event":"pusher:connection_established","data":"{\"socket_id\":\"1.1\",\"activity_timeout\":120}"}"#)
        #expect(await until { await sleeper.isWaiting(for: .seconds(120)) })
        await sleeper.wake(.seconds(120))
        #expect(await until { socket.sentEvents("pusher:ping").count == 1 })
        #expect(await until { await sleeper.isWaiting(for: .seconds(120)) })

        live.setForeground(false)
        #expect(socket.isClosed)
        #expect(!live.isConnected)
        await sleeper.wakeAll()
        await Task.yield()
        #expect(socket.sentEvents("pusher:ping").count == 1)
    }

    @Test func theReconnectPauseDoublesUpToAMinute() {
        #expect(LiveUpdates.reconnectPause(attempt: 0) == .seconds(1))
        #expect(LiveUpdates.reconnectPause(attempt: 1) == .seconds(2))
        #expect(LiveUpdates.reconnectPause(attempt: 3) == .seconds(8))
        #expect(LiveUpdates.reconnectPause(attempt: 5) == .seconds(32))
        #expect(LiveUpdates.reconnectPause(attempt: 6) == .seconds(60))
        #expect(LiveUpdates.reconnectPause(attempt: 40) == .seconds(60))
        #expect(LiveUpdates.reconnectPause(attempt: -1) == .seconds(1))
    }

    @Test func aDroppedConnectionComesBackWithAGrowingPauseThatResetsOnceConnected() async throws {
        let calls = LiveCalls(servers: [.success(server)])
        let opener = FakeLiveSocketOpener()
        let sleeper = ManualSleeper()
        let live = makeLive(calls, opener: opener, sleeper: sleeper)
        live.setSignedIn(true)
        live.setForeground(true)
        #expect(await until { opener.sockets.count == 1 })

        opener.sockets[0].onClose()
        #expect(await until { await sleeper.isWaiting(for: .seconds(1)) })
        await sleeper.wake(.seconds(1))
        #expect(await until { opener.sockets.count == 2 })

        opener.sockets[1].onClose()
        #expect(await until { await sleeper.isWaiting(for: .seconds(2)) })
        await sleeper.wake(.seconds(2))
        #expect(await until { opener.sockets.count == 3 })

        opener.sockets[2].onMessage(established)
        #expect(live.isConnected)
        #expect(live.attempt == 0)
        opener.sockets[2].onClose()
        #expect(!live.isConnected)
        #expect(await until { await sleeper.isWaiting(for: .seconds(1)) })

        opener.sockets[0].onClose()
        opener.sockets[0].onMessage(established)
        #expect(!live.isConnected)
        #expect(live.attempt == 1)
        await sleeper.wakeAll()
    }

    @Test func aFailedServerLookupIsRetriedButNoServerOrAnExpiredSessionIsNot() async throws {
        let sleeper = ManualSleeper()
        let failing = LiveCalls(servers: [.failure(.offline), .success(server)])
        let opener = FakeLiveSocketOpener()
        let live = makeLive(failing, opener: opener, sleeper: sleeper)
        live.setSignedIn(true)
        live.setForeground(true)
        #expect(await until { await sleeper.isWaiting(for: .seconds(1)) })
        await sleeper.wake(.seconds(1))
        #expect(await until { opener.sockets.count == 1 })

        let offSleeper = ManualSleeper()
        let off = LiveCalls(servers: [.success(nil)])
        let offOpener = FakeLiveSocketOpener()
        let liveOff = makeLive(off, opener: offOpener, sleeper: offSleeper)
        liveOff.setSignedIn(true)
        liveOff.setForeground(true)
        #expect(await until { await off.serverCalls == 1 })
        await Task.yield()
        #expect(offOpener.sockets.isEmpty)
        #expect(await offSleeper.requested.isEmpty)
        liveOff.setForeground(true)
        #expect(await until { await off.serverCalls == 2 })

        let expiredSleeper = ManualSleeper()
        let expired = LiveCalls(servers: [.failure(.unauthorized)])
        let liveExpired = makeLive(expired, opener: FakeLiveSocketOpener(), sleeper: expiredSleeper)
        liveExpired.setSignedIn(true)
        liveExpired.setForeground(true)
        #expect(await until { await expired.serverCalls == 1 })
        await Task.yield()
        #expect(await expiredSleeper.requested.isEmpty)
        await sleeper.wakeAll()
    }

    @Test func liveUpdatesConnectOnlyWhileSignedInAndInTheForeground() async throws {
        let calls = LiveCalls(servers: [.success(server)])
        let opener = FakeLiveSocketOpener()
        let live = makeLive(calls, opener: opener)
        live.setForeground(true)
        await Task.yield()
        #expect(await calls.serverCalls == 0)

        live.setSignedIn(true)
        #expect(await until { opener.sockets.count == 1 })
        opener.sockets[0].onMessage(established)
        #expect(live.isConnected)

        live.setSignedIn(false)
        #expect(opener.sockets[0].isClosed)
        #expect(!live.isConnected)
        live.setForeground(true)
        await Task.yield()
        #expect(opener.sockets.count == 1)
    }

    @Test func aBurstOfEventsSettlesIntoOneReload() async throws {
        let sleeper = ManualSleeper()
        let live = makeLive(LiveCalls(servers: [.success(nil)]), opener: FakeLiveSocketOpener(), sleeper: sleeper)
        let channel = LiveChannelName.record(.stockDelivery, id: 4)
        var reloads = live.settledEvents(on: channel, named: LiveEventName.record).makeAsyncIterator()

        live.receive(#"{"event":"record-changed","channel":"private-grp.mobile.stock_delivery.4","data":"{}"}"#)
        live.receive(#"{"event":"record-changed","channel":"private-grp.mobile.stock_delivery.4","data":"{}"}"#)
        live.receive(#"{"event":"pusher_internal:subscription_succeeded","channel":"private-grp.mobile.stock_delivery.4","data":"{}"}"#)
        live.receive(#"{"event":"staff-message","channel":"private-grp.mobile.stock_delivery.4","data":"{}"}"#)
        #expect(await until { await sleeper.requested.count == 2 })
        await sleeper.wakeAll()

        #expect(await reloads.next() != nil)
        #expect(await sleeper.requested == [LiveUpdates.settle, LiveUpdates.settle])
    }

    @Test func permissionsAreFetchedAgainWhenTheyChange() async throws {
        let sleeper = ManualSleeper()
        let live = makeLive(LiveCalls(servers: [.success(nil)]), opener: FakeLiveSocketOpener(), sleeper: sleeper)
        let counter = FetchCounter()
        let store = PermissionStore(
            fetch: {
                _ = counter.next()
                return UserPermissions(userID: 5, permissions: [], roles: [], fetchedAt: .now)
            },
            cache: InMemoryPermissionCache(),
            updateSource: LivePermissionUpdates(live: live)
        )

        await store.refresh()
        #expect(counter.value == 1)
        #expect(await until { live.wanted[LiveChannelName.personal(userID: 5)] == 1 })

        live.receive(#"{"event":"permissions-changed","channel":"private-grp.personal.5","data":"{}"}"#)
        #expect(await until { await sleeper.isWaiting(for: LiveUpdates.settle) })
        await sleeper.wakeAll()
        #expect(await until { counter.value == 2 })

        store.clear()
        #expect(await until { live.wanted.isEmpty })
    }
}
