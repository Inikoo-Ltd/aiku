import Foundation
import Observation

@MainActor
@Observable
final class PermissionStore {
    private(set) var current: UserPermissions?
    private(set) var isRefreshing = false
    private(set) var lastRefreshError: String?

    @ObservationIgnored private let fetch: @Sendable () async throws -> UserPermissions
    @ObservationIgnored private let cache: PermissionCaching
    @ObservationIgnored private let updateSource: PermissionUpdateSource
    @ObservationIgnored private var listeningTask: Task<Void, Never>?
    @ObservationIgnored private var listeningUserID: Int?

    init(
        fetch: @escaping @Sendable () async throws -> UserPermissions,
        cache: PermissionCaching = FilePermissionCache.standard,
        updateSource: PermissionUpdateSource = NoPermissionUpdates()
    ) {
        self.fetch = fetch
        self.cache = cache
        self.updateSource = updateSource
        current = cache.load()
    }

    convenience init(apiClient: APIClient) {
        self.init(fetch: { try await apiClient.fetchPermissions() })
    }

    func allows(_ permission: String) -> Bool {
        current?.allows(permission) ?? false
    }

    func refresh() async {
        guard !isRefreshing else { return }
        isRefreshing = true
        defer { isRefreshing = false }

        do {
            let fetched = try await fetch()
            current = fetched
            lastRefreshError = nil
            try? cache.save(fetched)
            listenForUpdates(userID: fetched.userID)
        } catch where error.isCancellation {
            return
        } catch {
            lastRefreshError = error.localizedDescription
        }
    }

    func clear() {
        listeningTask?.cancel()
        listeningTask = nil
        listeningUserID = nil
        current = nil
        lastRefreshError = nil
        cache.clear()
    }

    private func listenForUpdates(userID: Int) {
        guard listeningUserID != userID else { return }
        listeningTask?.cancel()
        listeningUserID = userID
        let updates = updateSource.updates(forUserID: userID)
        listeningTask = Task { [weak self] in
            for await _ in updates {
                await self?.refresh()
            }
        }
    }
}
