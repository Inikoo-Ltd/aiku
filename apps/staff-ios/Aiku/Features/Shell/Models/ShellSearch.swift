import Foundation
import Observation

struct ShellHighlightSegment: Hashable, Sendable {
    let text: String
    let isMatch: Bool
}

struct ShellSearchQuantity: Hashable, Sendable {
    let number: String

    @MainActor var text: String { L10n.format("%@ in locations", number) }
}

enum ShellSearchFormat {
    static let minimumQueryLength = 2
    static let debounce: Duration = .milliseconds(300)
    static let allScopeID = MobileSearchType.allKey

    static func title(_ type: MobileSearchType) -> String {
        switch type {
        case .skos: "SKOs"
        case .skoFamilies: "SKO families"
        case .locations: "Locations"
        case .deliveryNotes: "Delivery notes"
        case .stockDeliveries: "Stock deliveries"
        case .returns: "Returns"
        case .pallets: "Pallets"
        case .palletDeliveries: "Fulfilment deliveries"
        case .palletReturns: "Fulfilment returns"
        }
    }

    static func scopeID(_ scope: MobileSearchType?) -> String {
        scope?.rawValue ?? allScopeID
    }

    static func scopeTitle(_ scope: MobileSearchType?) -> String {
        scope.map(title) ?? "All"
    }

    static func scope(id: String) -> MobileSearchType? {
        MobileSearchType(rawValue: id)
    }

    static func heading(_ group: MobileSearchGroup) -> String {
        let label = group.label.isEmpty ? title(group.type) : group.label
        return group.hasMore ? "\(label) · \(group.rows.count)+" : "\(label) · \(group.rows.count)"
    }

    static func hasPhoto(_ type: MobileSearchType) -> Bool {
        type == .skos
    }

    static func hasStateBadge(_ type: MobileSearchType) -> Bool {
        switch type {
        case .skos, .skoFamilies, .locations: false
        default: true
        }
    }

    static func quantity(_ row: MobileSearchRow) -> ShellSearchQuantity? {
        guard row.type == .skos, let value = row.quantity else { return nil }
        return ShellSearchQuantity(number: value.trimmed)
    }

    static func tone(_ row: MobileSearchRow) -> Tone {
        switch row.type {
        case .deliveryNotes: GoodsOutDeliveryNoteState(apiState: row.state)?.tone ?? .neutral
        case .stockDeliveries: GoodsInText.deliveryTone(row.state ?? "")
        case .returns: GoodsInText.returnTone(row.state ?? "")
        case .pallets: FulfilmentStorageText.palletStateTone(row.state)
        case .palletDeliveries: PalletDeliveryText.deliveryTone(row.state)
        case .palletReturns: PalletReturnText.stateTone(row.state)
        case .skos: InventorySkoState.remoteTone(row.state)
        case .skoFamilies, .locations: .neutral
        }
    }

    static func link(_ row: MobileSearchRow) -> ShellLink {
        let reference = row.reference
        return switch row.type {
        case .skos: .inventory(.skoID(id: row.id))
        case .skoFamilies: .inventory(.family(code: reference))
        case .locations: .locations(.location(code: reference))
        case .deliveryNotes: .goodsOut(.deliveryNoteID(id: row.id))
        case .stockDeliveries: .goodsIn(.stockDelivery(id: row.id, reference: reference))
        case .returns: .goodsIn(.returnDetail(id: row.id, reference: reference))
        case .pallets: .fulfilment(.pallet(id: row.id, reference: reference))
        case .palletDeliveries: .fulfilment(.palletDelivery(id: row.id, reference: reference))
        case .palletReturns: .fulfilment(.palletReturn(id: row.id, reference: reference))
        }
    }

    static func problem(_ error: Error) -> String {
        switch error as? APIError {
        case .offline: "Search needs internet. Check Wi-Fi and try again."
        case .http(403): "You can’t search in this warehouse."
        default: error.localizedDescription
        }
    }

    static func highlight(_ text: String, query: String) -> [ShellHighlightSegment] {
        let needle = query.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !needle.isEmpty else { return [ShellHighlightSegment(text: text, isMatch: false)] }
        var segments: [ShellHighlightSegment] = []
        var cursor = text.startIndex
        while cursor < text.endIndex,
              let range = text.range(of: needle, options: [.caseInsensitive, .diacriticInsensitive], range: cursor..<text.endIndex) {
            if range.lowerBound > cursor {
                segments.append(ShellHighlightSegment(text: String(text[cursor..<range.lowerBound]), isMatch: false))
            }
            segments.append(ShellHighlightSegment(text: String(text[range]), isMatch: true))
            cursor = range.upperBound
        }
        if cursor < text.endIndex {
            segments.append(ShellHighlightSegment(text: String(text[cursor...]), isMatch: false))
        }
        return segments
    }
}

struct RecentSearches {
    static let limit = 8
    static let storageKey = "search.recent"

    private let defaults: UserDefaults

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    private struct Stored: Codable {
        let userID: Int
        var warehouses: [String: [String]] = [:]
    }

    func read(userID: Int, warehouseID: Int) -> [String] {
        savedSearches(for: userID)?.warehouses[String(warehouseID)] ?? []
    }

    @discardableResult
    func remember(_ query: String, userID: Int, warehouseID: Int) -> [String] {
        let term = query.trimmingCharacters(in: .whitespacesAndNewlines)
        var saved = savedSearches(for: userID) ?? Stored(userID: userID)
        let key = String(warehouseID)
        guard !term.isEmpty else { return saved.warehouses[key] ?? [] }
        let others = (saved.warehouses[key] ?? []).filter { $0.caseInsensitiveCompare(term) != .orderedSame }
        let recent = Array(([term] + others).prefix(Self.limit))
        saved.warehouses[key] = recent
        if let data = try? JSONEncoder().encode(saved) {
            defaults.set(data, forKey: Self.storageKey)
        }
        return recent
    }

    func clear() {
        defaults.removeObject(forKey: Self.storageKey)
    }

    private func savedSearches(for userID: Int) -> Stored? {
        guard let data = defaults.data(forKey: Self.storageKey) else { return nil }
        guard let saved = try? JSONDecoder().decode(Stored.self, from: data) else {
            clear()
            return nil
        }
        return saved.userID == userID ? saved : nil
    }
}

@MainActor
@Observable
final class ShellSearchStore {
    private(set) var query = ""
    private(set) var scope: MobileSearchType?
    private(set) var types: [MobileSearchType] = []
    private(set) var exact: MobileSearchRow?
    private(set) var groups: [MobileSearchGroup] = []
    private(set) var loadedQuery: String?
    private(set) var page = 1
    private(set) var isSearching = false
    private(set) var isLoadingMore = false
    private(set) var problem: String?
    private(set) var moreProblem: String?
    private(set) var recent: [String] = []
    private(set) var isSessionExpired = false
    private(set) var warehouseID: Int?
    private(set) var userID: Int?

    @ObservationIgnored private(set) var searchTask: Task<Void, Never>?
    @ObservationIgnored private(set) var typesTask: Task<Void, Never>?
    @ObservationIgnored private var generation = 0
    @ObservationIgnored private let service: SearchService
    @ObservationIgnored private let recentSearches: RecentSearches
    @ObservationIgnored private let debounce: Duration

    init(service: SearchService, recentSearches: RecentSearches = RecentSearches(), debounce: Duration = ShellSearchFormat.debounce) {
        self.service = service
        self.recentSearches = recentSearches
        self.debounce = debounce
    }

    static func live(_ client: APIClient) -> ShellSearchStore {
        ShellSearchStore(service: .live(client))
    }

    var needle: String { query.trimmingCharacters(in: .whitespacesAndNewlines) }

    var isQueryTooShort: Bool { needle.count < ShellSearchFormat.minimumQueryLength }

    var shownRecent: [String] { needle.isEmpty ? recent : [] }

    var hasResults: Bool { exact != nil || !groups.isEmpty }

    var isEmpty: Bool { !isQueryTooShort && !isSearching && problem == nil && loadedQuery == needle && !hasResults }

    var isPaging: Bool { scope != nil }

    var canLoadMore: Bool { scope != nil && groups.contains(where: \.hasMore) && !isLoadingMore && !isSearching }

    var scopes: [MobileSearchType?] {
        guard !types.isEmpty else { return [] }
        let typed: [MobileSearchType?] = types.map { $0 }
        return [nil] + typed
    }

    func start(warehouseID: Int?, userID: Int?) {
        if warehouseID != self.warehouseID || userID != self.userID {
            typesTask?.cancel()
            typesTask = nil
            types = []
            scope = nil
            self.warehouseID = warehouseID
            self.userID = userID
            if !needle.isEmpty {
                searchFromStart(after: .zero)
            }
        } else if !isQueryTooShort, !isSearching, loadedQuery != needle {
            searchFromStart(after: .zero)
        }
        if let warehouseID, let userID {
            recent = recentSearches.read(userID: userID, warehouseID: warehouseID)
        } else {
            recent = []
        }
        if types.isEmpty, typesTask == nil, let warehouseID {
            loadTypes(warehouseID: warehouseID)
        }
    }

    func updateQuery(_ text: String) {
        let previousNeedle = needle
        query = text
        guard needle != previousNeedle else { return }
        searchFromStart(after: debounce)
    }

    func searchNow() {
        if loadedQuery == needle, problem == nil, !isSearching {
            return
        }
        searchFromStart(after: .zero)
    }

    func pickRecent(_ term: String) {
        query = term
        searchFromStart(after: .zero)
    }

    func selectScope(_ scope: MobileSearchType?) {
        guard scope != self.scope else { return }
        self.scope = scope
        exact = nil
        groups = []
        loadedQuery = nil
        searchFromStart(after: .zero)
    }

    func showMore(_ type: MobileSearchType) {
        if scope == nil {
            selectScope(type)
        } else {
            loadMore()
        }
    }

    func loadMore() {
        guard let scope, let warehouseID, let searched = loadedQuery, canLoadMore else { return }
        let nextPage = page + 1
        isLoadingMore = true
        moreProblem = nil
        searchTask?.cancel()
        generation += 1
        let requestGeneration = generation
        let service = self.service
        searchTask = Task { [weak self] in
            do {
                let results = try await service.search(warehouseID, searched, scope, nextPage)
                self?.applyNextPage(results, scope: scope, page: nextPage, generation: requestGeneration)
            } catch {
                self?.applyMoreFailure(error, generation: requestGeneration)
            }
        }
    }

    func retry() {
        if moreProblem != nil {
            loadMore()
        } else {
            searchFromStart(after: .zero)
        }
    }

    func rememberSearch() {
        guard let warehouseID, let userID else { return }
        let searched = loadedQuery ?? needle
        guard !searched.isEmpty else { return }
        recent = recentSearches.remember(searched, userID: userID, warehouseID: warehouseID)
    }

    private func searchFromStart(after delay: Duration) {
        searchTask?.cancel()
        generation += 1
        let requestGeneration = generation
        let searched = needle
        guard !isQueryTooShort, let warehouseID else {
            searchTask = nil
            exact = nil
            groups = []
            loadedQuery = nil
            page = 1
            isSearching = false
            isLoadingMore = false
            problem = nil
            moreProblem = nil
            return
        }
        let scope = self.scope
        let service = self.service
        isSearching = true
        isLoadingMore = false
        problem = nil
        moreProblem = nil
        searchTask = Task { [weak self] in
            if delay > .zero {
                do {
                    try await Task.sleep(for: delay)
                } catch {
                    return
                }
            }
            do {
                let results = try await service.search(warehouseID, searched, scope, 1)
                self?.applyFirstPage(results, searched: searched, scope: scope, generation: requestGeneration)
            } catch {
                self?.applyFailure(error, scope: scope, generation: requestGeneration)
            }
        }
    }

    private func loadTypes(warehouseID: Int) {
        let service = self.service
        typesTask = Task { [weak self] in
            do {
                let results = try await service.search(warehouseID, "", nil, 1)
                self?.applyTypes(results.types, warehouseID: warehouseID)
            } catch {
                self?.applyTypesFailure(error, warehouseID: warehouseID)
            }
        }
    }

    private func applyTypes(_ loaded: [MobileSearchType], warehouseID: Int) {
        guard warehouseID == self.warehouseID else { return }
        types = loaded
    }

    private func applyTypesFailure(_ error: Error, warehouseID: Int) {
        guard warehouseID == self.warehouseID, !error.isCancellation else { return }
        typesTask = nil
        if (error as? APIError) == .unauthorized {
            isSessionExpired = true
        } else if Self.isForbidden(error), !isSearching, !hasResults {
            problem = ShellSearchFormat.problem(error)
        }
    }

    private func applyFirstPage(_ results: MobileSearchResults, searched: String, scope: MobileSearchType?, generation requestGeneration: Int) {
        guard requestGeneration == generation else { return }
        let exact = results.exact
        let groups = results.groups
            .filter { scope == nil || $0.type == scope }
            .map { group -> MobileSearchGroup in
                var trimmed = group
                if let exact {
                    trimmed.rows = group.rows.filter { !$0.isSameRecord(exact) }
                }
                return trimmed
            }
            .filter { !$0.rows.isEmpty || $0.hasMore }
        if !results.types.isEmpty {
            types = results.types
        }
        self.exact = exact
        self.groups = groups
        loadedQuery = searched
        page = 1
        isSearching = false
        problem = nil
    }

    private func applyNextPage(_ results: MobileSearchResults, scope: MobileSearchType, page nextPage: Int, generation requestGeneration: Int) {
        guard requestGeneration == generation else { return }
        let next = results.groups.first { $0.type == scope }
        groups = groups.map { group -> MobileSearchGroup in
            guard group.type == scope else { return group }
            let known = group.rows + [exact].compactMap { $0 }
            let added = (next?.rows ?? []).filter { row in !known.contains { $0.isSameRecord(row) } }
            var grown = group
            grown.rows = group.rows + added
            grown.hasMore = next?.hasMore == true
            return grown
        }
        page = nextPage
        isLoadingMore = false
        moreProblem = nil
    }

    private func applyFailure(_ error: Error, scope: MobileSearchType?, generation requestGeneration: Int) {
        guard requestGeneration == generation else { return }
        isSearching = false
        if error.isCancellation {
            return
        }
        if (error as? APIError) == .unauthorized {
            isSessionExpired = true
            return
        }
        if scope == nil, Self.isForbidden(error) {
            types = []
        }
        exact = nil
        groups = []
        loadedQuery = nil
        problem = ShellSearchFormat.problem(error)
    }

    private func applyMoreFailure(_ error: Error, generation requestGeneration: Int) {
        guard requestGeneration == generation else { return }
        isLoadingMore = false
        if error.isCancellation {
            return
        }
        if (error as? APIError) == .unauthorized {
            isSessionExpired = true
            return
        }
        moreProblem = ShellSearchFormat.problem(error)
    }

    private static func isForbidden(_ error: Error) -> Bool {
        switch error as? APIError {
        case .http(403), .rejected: true
        default: false
        }
    }
}
