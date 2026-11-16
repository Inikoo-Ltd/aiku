import Foundation
import Observation

@MainActor
@Observable
final class PickingSessionsStore {
    static let shared = PickingSessionsStore(service: .live(.shared))
    static let referenceSearchPages = 4

    private(set) var warehouseID: Int?
    private(set) var filter: PickingSessionFilter = .all
    private(set) var rows: [MobilePickingSessionRow] = []
    private(set) var counts: [String: Int] = [:]
    private(set) var page = 0
    private(set) var lastPage = 0
    private(set) var loadingPage: Int?
    private(set) var loadError: String?
    private(set) var loadMoreError: String?
    private(set) var candidates: [MobileSessionCandidateNote] = []
    private(set) var isLoadingCandidates = false
    private(set) var candidatesError: String?
    private(set) var isCreating = false

    @ObservationIgnored private let service: PickingSessionService
    @ObservationIgnored private var sessionStores: [Int: PickingSessionStore] = [:]

    init(service: PickingSessionService) {
        self.service = service
    }

    var hasLoaded: Bool { page > 0 }

    var canLoadMore: Bool { hasLoaded && page < lastPage && loadingPage == nil && loadMoreError == nil }

    var isLoadingFirstPage: Bool { loadingPage == 1 }

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
        self.filter = filter
        rows = []
        page = 0
        lastPage = 0
        await loadPage(1)
    }

    func load(warehouseID: Int) async {
        if self.warehouseID != warehouseID {
            self.warehouseID = warehouseID
            rows = []
            counts = [:]
            page = 0
            lastPage = 0
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

    private func loadPage(_ requested: Int) async {
        guard let warehouseID else { return }
        let filter = filter
        loadingPage = requested
        if requested == 1 { loadError = nil }
        loadMoreError = nil
        do {
            let result = try await service.sessions(warehouseID, filter.stateKey, requested)
            guard warehouseID == self.warehouseID, filter == self.filter else { return }
            let loadedPage = result.pagination.currentPage
            if loadedPage <= 1 {
                rows = result.data
            } else {
                let known = Set(rows.map(\.id))
                rows += result.data.filter { !known.contains($0.id) }
            }
            page = loadedPage
            lastPage = result.pagination.lastPage
            if !result.counts.isEmpty { counts = result.counts }
            loadingPage = nil
        } catch where error.isCancellation {
            loadingPage = nil
        } catch {
            loadingPage = nil
            if requested == 1 {
                loadError = PickingSessionErrorText.message(error, offline: "Picking sessions need internet. Check Wi-Fi and try again.")
            } else {
                loadMoreError = PickingSessionErrorText.message(error)
            }
        }
    }

    func sessionID(reference: String) async throws -> Int? {
        if let row = rows.first(where: { $0.reference == reference }) { return row.id }
        guard let warehouseID else { return nil }
        var page = 1
        while page <= Self.referenceSearchPages {
            let result = try await service.sessions(warehouseID, nil, page)
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
