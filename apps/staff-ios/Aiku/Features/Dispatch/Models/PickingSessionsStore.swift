import Foundation
import Observation

@MainActor
@Observable
final class PickingSessionsStore {
    static let shared = PickingSessionsStore(service: .live(.shared))
    static let referenceSearchPages = 4
    static let searchDelay: Duration = .milliseconds(300)

    private(set) var warehouseID: Int?
    private(set) var filter: PickingSessionFilter = .all
    private(set) var searchText = ""
    private(set) var listFilter = ListFilter<PickingSessionSort>()
    private(set) var rows: [MobilePickingSessionRow] = []
    private(set) var counts: [String: Int] = [:]
    private(set) var total: Int?
    private(set) var page = 0
    private(set) var lastPage = 0
    private(set) var loadingPage: Int?
    private(set) var loadError: String?
    private(set) var loadMoreError: String?
    private(set) var savedCopyAt: Date?
    private(set) var candidates: [MobileSessionCandidateNote] = []
    private(set) var isLoadingCandidates = false
    private(set) var candidatesError: String?
    private(set) var isCreating = false

    @ObservationIgnored private let service: PickingSessionService
    @ObservationIgnored private let savedRows: @MainActor (_ warehouseID: Int) -> SavedRows<MobilePickingSessionRow>?
    @ObservationIgnored private var sessionStores: [Int: PickingSessionStore] = [:]
    @ObservationIgnored private var searchTask: Task<Void, Never>?

    init(
        service: PickingSessionService,
        savedRows: @escaping @MainActor (_ warehouseID: Int) -> SavedRows<MobilePickingSessionRow>? = { PickingSessionsStore.phoneSavedRows(warehouseID: $0) }
    ) {
        self.service = service
        self.savedRows = savedRows
    }

    static func phoneSavedRows(warehouseID: Int) -> SavedRows<MobilePickingSessionRow>? {
        guard let userID = OfflineWork.shared.userID else { return nil }
        return SavedLists.shared.rows(MobilePickingSessionRow.self, userID: userID, warehouseID: warehouseID, kind: .pickingSessions)
    }

    var hasLoaded: Bool { page > 0 }

    var canLoadMore: Bool { hasLoaded && page < lastPage && loadingPage == nil && loadMoreError == nil }

    var isLoadingFirstPage: Bool { loadingPage == 1 }

    var isSearching: Bool { !searchText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty }

    var shownTotal: Int? {
        guard hasLoaded, loadingPage == nil else { return nil }
        return total
    }

    func count(for filter: PickingSessionFilter) -> Int? {
        filter.stateKey.flatMap { counts[$0] }
    }

    func store(for sessionID: Int) -> PickingSessionStore {
        if let existing = sessionStores[sessionID] { return existing }
        let created = PickingSessionStore(sessionID: sessionID, service: service)
        sessionStores[sessionID] = created
        return created
    }

    func select(_ filter: PickingSessionFilter) async {
        guard filter != self.filter else { return }
        searchTask?.cancel()
        self.filter = filter
        resetList()
        await loadPage(1)
    }

    func search(_ text: String) {
        guard text != searchText else { return }
        searchText = text
        searchTask?.cancel()
        searchTask = Task {
            try? await Task.sleep(for: Self.searchDelay)
            guard !Task.isCancelled else { return }
            resetList()
            await loadPage(1)
        }
    }

    func apply(_ newFilter: ListFilter<PickingSessionSort>) async {
        searchTask?.cancel()
        listFilter = newFilter
        resetList()
        await loadPage(1)
    }

    func clearListFilter() async {
        await apply(ListFilter())
    }

    func load(warehouseID: Int) async {
        if self.warehouseID != warehouseID {
            searchTask?.cancel()
            self.warehouseID = warehouseID
            resetList()
            counts = [:]
            candidates = []
        }
        await loadPage(1)
    }

    func loadMore() async {
        guard canLoadMore else { return }
        await loadPage(page + 1)
    }

    func retryMore() async {
        loadMoreError = nil
        await loadPage(page + 1)
    }

    private func currentQuery(page: Int) -> MobilePickingSessionsQuery {
        MobilePickingSessionsQuery(state: filter.stateKey, search: searchText, sort: listFilter.sort, page: page)
    }

    private func resetList() {
        rows = []
        page = 0
        lastPage = 0
        total = nil
        savedCopyAt = nil
    }

    private func loadPage(_ requested: Int) async {
        guard let warehouseID else { return }
        let requestedQuery = currentQuery(page: requested)
        loadingPage = requested
        if requested == 1 { loadError = nil }
        loadMoreError = nil
        do {
            let result = try await service.sessions(warehouseID, requestedQuery)
            guard warehouseID == self.warehouseID, requestedQuery == currentQuery(page: requested) else { return }
            let loadedPage = result.pagination.currentPage
            if loadedPage <= 1 {
                rows = result.data
            } else {
                let known = Set(rows.map(\.id))
                rows += result.data.filter { !known.contains($0.id) }
            }
            page = loadedPage
            lastPage = result.pagination.lastPage
            total = result.pagination.total
            savedCopyAt = nil
            if !result.counts.isEmpty { counts = result.counts }
            loadingPage = nil
        } catch {
            guard warehouseID == self.warehouseID, requestedQuery == currentQuery(page: requested) else { return }
            loadingPage = nil
            guard !error.isCancellation else { return }
            if requested == 1 {
                loadError = PickingSessionErrorText.message(error, offline: "Picking sessions need internet. Check Wi-Fi and try again.")
                if let apiError = error as? APIError, apiError == .offline {
                    showSavedRows(warehouseID: warehouseID)
                }
            } else {
                loadMoreError = PickingSessionErrorText.message(error)
            }
        }
    }

    private func showSavedRows(warehouseID: Int) {
        guard let saved = savedRows(warehouseID) else { return }
        rows = PickingSessionOfflineList.rows(saved.rows, query: currentQuery(page: 1))
        page = 1
        lastPage = 1
        total = rows.count
        savedCopyAt = saved.savedAt
    }

    func sessionID(reference: String) async throws -> Int? {
        if let row = rows.first(where: { $0.reference == reference }) { return row.id }
        guard let warehouseID else { return nil }
        var page = 1
        while page <= Self.referenceSearchPages {
            let result = try await service.sessions(warehouseID, MobilePickingSessionsQuery(page: page))
            if let row = result.data.first(where: { $0.reference == reference }) { return row.id }
            guard page < result.pagination.lastPage else { return nil }
            page += 1
        }
        return nil
    }

    func loadCandidates() async {
        guard let warehouseID else { return }
        isLoadingCandidates = true
        candidatesError = nil
        do {
            candidates = try await service.candidates(warehouseID)
        } catch where error.isCancellation {
        } catch {
            candidatesError = PickingSessionErrorText.message(error)
        }
        isLoadingCandidates = false
    }

    func createSession(deliveryNoteIDs: [Int]) async -> Result<MobilePickingSession, PickingSessionFailure> {
        guard let warehouseID, !deliveryNoteIDs.isEmpty else { return .failure(PickingSessionFailure(message: "Choose at least one delivery note.")) }
        isCreating = true
        defer { isCreating = false }
        do {
            let session = try await service.create(warehouseID, deliveryNoteIDs)
            store(for: session.id).adopt(session)
            candidates.removeAll { deliveryNoteIDs.contains($0.id) }
            await loadPage(1)
            return .success(session)
        } catch {
            return .failure(PickingSessionFailure(message: PickingSessionErrorText.message(error)))
        }
    }
}

struct PickingSessionFailure: Error, Equatable {
    let message: String
}
