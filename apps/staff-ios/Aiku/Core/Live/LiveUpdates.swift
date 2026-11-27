import Foundation
import Observation

struct LiveService: Sendable {
    var server: @Sendable () async throws -> RealtimeServer?
    var authorize: @Sendable (_ socketID: String, _ channel: String) async throws -> String?

    static func live(_ client: APIClient) -> LiveService {
        LiveService(
            server: { try await client.fetchRealtimeServer() },
            authorize: { try await client.authorizeRealtimeChannel(socketID: $0, channel: $1) }
        )
    }
}

@MainActor
@Observable
final class LiveUpdates {
    static let shared = LiveUpdates(
        service: .live(.shared),
        opener: URLSessionLiveSocketOpener(),
        clientVersion: InstalledAppVersion.current().name,
        isEnabled: !CrashReporting.isRunningTests
    )

    static let clientName = "aiku-ios"
    static let firstPause: Duration = .seconds(1)
    static let longestPause: Duration = .seconds(60)
    static let maxDoublings = 6
    static let settle: Duration = .milliseconds(600)
    static let eventBuffer = 64

    private(set) var isConnected = false

    @ObservationIgnored private let service: LiveService
    @ObservationIgnored private let opener: LiveSocketOpening
    @ObservationIgnored private let clientVersion: String
    @ObservationIgnored private let isEnabled: Bool
    @ObservationIgnored private let sleep: @Sendable (Duration) async throws -> Void
    @ObservationIgnored private(set) var wanted: [String: Int] = [:]
    @ObservationIgnored private var listeners: [UUID: Listener] = [:]
    @ObservationIgnored private var socket: LiveSocket?
    @ObservationIgnored private(set) var socketID: String?
    @ObservationIgnored private var generation = 0
    @ObservationIgnored private var isForeground = false
    @ObservationIgnored private var isSignedIn = false
    @ObservationIgnored private var connectTask: Task<Void, Never>?
    @ObservationIgnored private var pingTask: Task<Void, Never>?
    @ObservationIgnored private var reconnectTask: Task<Void, Never>?
    @ObservationIgnored private(set) var attempt = 0

    private struct Listener {
        let channel: String
        let continuation: AsyncStream<LiveEvent>.Continuation
    }

    init(
        service: LiveService,
        opener: LiveSocketOpening,
        clientVersion: String,
        isEnabled: Bool = true,
        sleep: @escaping @Sendable (Duration) async throws -> Void = { try await Task.sleep(for: $0) }
    ) {
        self.service = service
        self.opener = opener
        self.clientVersion = clientVersion
        self.isEnabled = isEnabled
        self.sleep = sleep
    }

    static func reconnectPause(attempt: Int) -> Duration {
        let doublings = min(max(attempt, 0), maxDoublings)
        return min(firstPause * (1 << doublings), longestPause)
    }

    func setForeground(_ isForeground: Bool) {
        self.isForeground = isForeground
        connectOrDisconnect()
    }

    func setSignedIn(_ isSignedIn: Bool) {
        self.isSignedIn = isSignedIn
        connectOrDisconnect()
    }

    func events(on channel: String) -> AsyncStream<LiveEvent> {
        let id = UUID()
        let (stream, continuation) = AsyncStream.makeStream(of: LiveEvent.self, bufferingPolicy: .bufferingNewest(Self.eventBuffer))
        listeners[id] = Listener(channel: channel, continuation: continuation)
        retain(channel)
        continuation.onTermination = { [weak self] _ in
            Task { @MainActor in self?.stopListening(id) }
        }
        return stream
    }

    func settledEvents(on channel: String, named names: Set<String>) -> AsyncStream<Void> {
        let events = events(on: channel)
        let sleep = sleep
        let settle = Self.settle
        return AsyncStream { continuation in
            let listening = Task {
                var pending: Task<Void, Never>?
                for await event in events where names.contains(event.event) {
                    pending?.cancel()
                    pending = Task {
                        do {
                            try await sleep(settle)
                        } catch {
                            return
                        }
                        guard !Task.isCancelled else { return }
                        continuation.yield()
                    }
                }
                pending?.cancel()
                continuation.finish()
            }
            continuation.onTermination = { _ in listening.cancel() }
        }
    }

    func retain(_ channel: String) {
        let count = (wanted[channel] ?? 0) + 1
        wanted[channel] = count
        if count == 1 {
            subscribe(channel)
        }
    }

    func release(_ channel: String) {
        guard let count = wanted[channel] else { return }
        if count > 1 {
            wanted[channel] = count - 1
            return
        }
        wanted[channel] = nil
        if socketID != nil {
            socket?.send(LiveMessage.unsubscribe(channel: channel))
        }
    }

    func receive(_ text: String) {
        switch LiveFrame.parse(text) {
        case .established(let socketID, let activityTimeout):
            established(socketID: socketID, activityTimeout: activityTimeout)
        case .ping:
            socket?.send(LiveMessage.pong)
        case .event(let event):
            deliver(event)
        case .ignored:
            return
        }
    }

    private var shouldConnect: Bool { isEnabled && isForeground && isSignedIn }

    private func connectOrDisconnect() {
        if shouldConnect {
            connect()
        } else {
            disconnect()
        }
    }

    private func connect() {
        guard shouldConnect, socket == nil, connectTask == nil else { return }
        reconnectTask?.cancel()
        reconnectTask = nil
        generation += 1
        let current = generation
        connectTask = Task { [weak self] in
            await self?.openSocket(generation: current)
        }
    }

    private func openSocket(generation current: Int) async {
        let server: RealtimeServer?
        do {
            server = try await service.server()
        } catch {
            guard current == generation else { return }
            connectTask = nil
            if !error.isCancellation, error as? APIError != .unauthorized {
                scheduleReconnect()
            }
            return
        }
        guard current == generation else { return }
        connectTask = nil
        guard shouldConnect, let url = server?.socketURL(client: Self.clientName, version: clientVersion) else { return }
        socket = opener.open(
            url,
            onMessage: { [weak self] text in
                guard let self, self.generation == current else { return }
                self.receive(text)
            },
            onClose: { [weak self] in
                guard let self, self.generation == current else { return }
                self.dropped()
            }
        )
    }

    private func disconnect() {
        generation += 1
        connectTask?.cancel()
        connectTask = nil
        reconnectTask?.cancel()
        reconnectTask = nil
        pingTask?.cancel()
        pingTask = nil
        socket?.close()
        clearSocket()
        attempt = 0
    }

    private func dropped() {
        generation += 1
        pingTask?.cancel()
        pingTask = nil
        clearSocket()
        scheduleReconnect()
    }

    private func clearSocket() {
        socket = nil
        socketID = nil
        if isConnected {
            isConnected = false
        }
    }

    private func scheduleReconnect() {
        guard shouldConnect else { return }
        let pause = Self.reconnectPause(attempt: attempt)
        attempt += 1
        reconnectTask?.cancel()
        let sleep = sleep
        reconnectTask = Task { [weak self] in
            do {
                try await sleep(pause)
            } catch {
                return
            }
            guard let self, !Task.isCancelled else { return }
            self.reconnectTask = nil
            self.connect()
        }
    }

    private func established(socketID: String, activityTimeout: Int) {
        self.socketID = socketID
        attempt = 0
        isConnected = true
        for channel in wanted.keys.sorted() {
            subscribe(channel)
        }
        pingTask?.cancel()
        let current = generation
        let sleep = sleep
        pingTask = Task { [weak self] in
            while !Task.isCancelled {
                do {
                    try await sleep(.seconds(activityTimeout))
                } catch {
                    return
                }
                guard let self, self.generation == current, !Task.isCancelled else { return }
                self.socket?.send(LiveMessage.ping)
            }
        }
    }

    private func subscribe(_ channel: String) {
        guard let socketID else { return }
        let current = generation
        let service = service
        Task { [weak self] in
            let auth = try? await service.authorize(socketID, channel)
            guard let self, let auth, self.generation == current, self.socketID == socketID, self.wanted[channel] != nil else { return }
            self.socket?.send(LiveMessage.subscribe(channel: channel, auth: auth))
        }
    }

    private func deliver(_ event: LiveEvent) {
        for listener in listeners.values where listener.channel == event.channel {
            listener.continuation.yield(event)
        }
    }

    private func stopListening(_ id: UUID) {
        guard let listener = listeners.removeValue(forKey: id) else { return }
        release(listener.channel)
    }
}
