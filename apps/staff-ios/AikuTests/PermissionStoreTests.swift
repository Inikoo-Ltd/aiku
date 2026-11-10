import Foundation
import Testing
@testable import Aiku

final class InMemoryPermissionCache: PermissionCaching, @unchecked Sendable {
    private let lock = NSLock()
    private var stored: UserPermissions?

    init(_ stored: UserPermissions? = nil) {
        self.stored = stored
    }

    func load() -> UserPermissions? {
        lock.withLock { stored }
    }

    func save(_ permissions: UserPermissions) throws {
        lock.withLock { stored = permissions }
    }

    func clear() {
        lock.withLock { stored = nil }
    }
}

final class ManualPermissionUpdates: PermissionUpdateSource, @unchecked Sendable {
    private let lock = NSLock()
    private var continuations: [Int: AsyncStream<Void>.Continuation] = [:]

    func updates(forUserID userID: Int) -> AsyncStream<Void> {
        AsyncStream { continuation in
            lock.withLock { continuations[userID] = continuation }
        }
    }

    func send(toUserID userID: Int) {
        _ = lock.withLock { continuations[userID]?.yield() }
    }
}

final class FetchCounter: @unchecked Sendable {
    private let lock = NSLock()
    private var count = 0

    func next() -> Int {
        lock.withLock {
            count += 1
            return count
        }
    }

    var value: Int { lock.withLock { count } }
}

private func permissions(_ names: [String], userID: Int = 7) -> UserPermissions {
    UserPermissions(userID: userID, permissions: Set(names), roles: ["picker"], fetchedAt: Date(timeIntervalSince1970: 1_800_000_000))
}

@MainActor
struct PermissionStoreTests {
    @Test func startsFromCachedPermissions() {
        let store = PermissionStore(fetch: { throw APIError.unauthorized }, cache: InMemoryPermissionCache(permissions(["inventory.1.view"])))

        #expect(store.allows("inventory.1.view"))
        #expect(store.allows("inventory.1.edit") == false)
    }

    @Test func refreshReplacesAndCachesPermissions() async {
        let cache = InMemoryPermissionCache(permissions(["inventory.1.view"]))
        let store = PermissionStore(fetch: { permissions(["dispatching.1.edit"]) }, cache: cache)

        await store.refresh()

        #expect(store.allows("dispatching.1.edit"))
        #expect(store.allows("inventory.1.view") == false)
        #expect(cache.load()?.permissions == ["dispatching.1.edit"])
        #expect(store.lastRefreshError == nil)
    }

    @Test func failedRefreshKeepsCachedPermissions() async {
        let cache = InMemoryPermissionCache(permissions(["inventory.1.view"]))
        let store = PermissionStore(fetch: { throw APIError.http(statusCode: 500) }, cache: cache)

        await store.refresh()

        #expect(store.allows("inventory.1.view"))
        #expect(store.lastRefreshError == APIError.http(statusCode: 500).localizedDescription)
        #expect(store.isRefreshing == false)
    }

    @Test func clearRemovesPermissionsAndCache() async {
        let cache = InMemoryPermissionCache()
        let store = PermissionStore(fetch: { permissions(["inventory.1.view"]) }, cache: cache)
        await store.refresh()

        store.clear()

        #expect(store.current == nil)
        #expect(cache.load() == nil)
    }

    @Test func pushedUpdateTriggersRefetch() async throws {
        let counter = FetchCounter()
        let updates = ManualPermissionUpdates()
        let store = PermissionStore(
            fetch: { counter.next() == 1 ? permissions(["inventory.1.view"]) : permissions(["inventory.1.view", "inventory.1.edit"]) },
            cache: InMemoryPermissionCache(),
            updateSource: updates
        )
        await store.refresh()
        #expect(store.allows("inventory.1.edit") == false)

        updates.send(toUserID: 7)
        for _ in 0..<50 where counter.value < 2 || store.isRefreshing {
            try await Task.sleep(for: .milliseconds(10))
        }

        #expect(counter.value == 2)
        #expect(store.allows("inventory.1.edit"))
    }
}

struct UserPermissionsTests {
    @Test func matchesAnyAndPrefix() {
        let granted = permissions(["dispatching.1.view", "inventory.3.edit"])

        #expect(granted.allowsAny(["fulfilment.1.view", "inventory.3.edit"]))
        #expect(granted.allowsAny(withPrefix: "dispatching."))
        #expect(granted.allowsAny(withPrefix: "fulfilment.") == false)
    }
}

struct FilePermissionCacheTests {
    @Test func savesLoadsAndClears() throws {
        let fileURL = URL.temporaryDirectory.appending(path: "aiku-tests-\(UUID().uuidString)/permissions.json")
        let cache = FilePermissionCache(fileURL: fileURL)
        let stored = permissions(["inventory.1.view"])

        try cache.save(stored)
        #expect(cache.load() == stored)
        #expect(try fileURL.resourceValues(forKeys: [.isExcludedFromBackupKey]).isExcludedFromBackup == true)

        cache.clear()
        #expect(cache.load() == nil)
    }

    @Test func ignoresUnreadableFile() throws {
        let fileURL = URL.temporaryDirectory.appending(path: "aiku-tests-\(UUID().uuidString).json")
        try Data("not json".utf8).write(to: fileURL)

        #expect(FilePermissionCache(fileURL: fileURL).load() == nil)
    }
}

struct MobilePermissionsAPITests {
    @Test func sendsAppKeyWhenConfigured() {
        let client = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "https://example.test/app/api")!, mobileAppKey: "key-123"))
        let request = client.makeRequest(for: .mobilePermissions)

        #expect(request.url?.absoluteString == "https://example.test/app/api/mobile/permissions")
        #expect(request.value(forHTTPHeaderField: "X-Aiku-App-Key") == "key-123")
    }

    @Test func omitsEmptyAppKey() {
        let client = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "https://example.test/app/api")!, mobileAppKey: ""))

        #expect(client.makeRequest(for: .ping).value(forHTTPHeaderField: "X-Aiku-App-Key") == nil)
    }

    @Test func decodesPermissionsResponse() async throws {
        let body = #"{"user_id":7,"permissions":["inventory.1.view","inventory.1.view","dispatching.1.edit"],"roles":["picker"]}"#
        let client = APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://example.test/app/api")!),
            transport: StubTransport(statusCode: 200, body: body)
        )

        let fetched = try await client.fetchPermissions()

        #expect(fetched.userID == 7)
        #expect(fetched.permissions == ["inventory.1.view", "dispatching.1.edit"])
        #expect(fetched.roles == ["picker"])
    }
}
