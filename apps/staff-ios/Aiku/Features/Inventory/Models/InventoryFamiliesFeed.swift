import Foundation
import Observation

struct InventoryFamiliesRequest: Hashable, Sendable {
    var warehouseID: Int
    var state: InventoryFamilyState
    var search: String
}

@MainActor
@Observable
final class InventoryFamiliesFeed {
    static let shared = InventoryFamiliesFeed()
    static let pageSize = 50

    private(set) var families: [InventoryFamily] = []
    private(set) var counts: [InventoryFamilyState: Int] = [:]
    private(set) var total: Int?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var loadError: String?
    private(set) var loadedRequest: InventoryFamiliesRequest?
    @ObservationIgnored private var known: [String: InventoryFamily] = [:]

    var hasMorePages: Bool {
        currentPage > 0 && currentPage < lastPage
    }

    func family(code: String) -> InventoryFamily? {
        known[code.uppercased()]
    }

    func reload(_ request: InventoryFamiliesRequest, apiClient: APIClient) async {
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await apiClient.fetchOrgStockFamilies(warehouseID: request.warehouseID, state: request.state.remoteValue, search: request.search, perPage: Self.pageSize)
            families = remember(page.data)
            apply(page)
            loadedRequest = request
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func loadNextPage(apiClient: APIClient) async {
        guard hasMorePages, !isLoading, !isLoadingMore, let request = loadedRequest else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchOrgStockFamilies(warehouseID: request.warehouseID, state: request.state.remoteValue, search: request.search, page: currentPage + 1, perPage: Self.pageSize)
            guard loadedRequest == request else { return }
            let codes = Set(families.map(\.code))
            families += remember(page.data).filter { !codes.contains($0.code) }
            apply(page)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func reset() {
        families = []
        counts = [:]
        total = nil
        currentPage = 0
        lastPage = 0
        loadError = nil
        loadedRequest = nil
        known = [:]
    }

    private func remember(_ remote: [MobileOrgStockFamily]) -> [InventoryFamily] {
        let mapped = remote.map(InventoryFamily.init(remote:))
        for family in mapped {
            known[family.code.uppercased()] = family
        }
        return mapped
    }

    private func apply(_ page: MobileOrgStockFamiliesPage) {
        total = page.meta.total
        currentPage = page.meta.currentPage
        lastPage = page.meta.lastPage
        counts = [
            .inProcess: page.counts.inProcess,
            .active: page.counts.active,
            .discontinuing: page.counts.discontinuing,
            .discontinued: page.counts.discontinued,
        ]
    }
}

@MainActor
@Observable
final class InventoryFamilySkosFeed {
    static let pageSize = 50

    let warehouseID: Int
    let familyID: Int
    private(set) var summary: MobileFamilySkosPage.Family?
    private(set) var skos: [MobileFamilySko] = []
    private(set) var total: Int?
    private(set) var currentPage = 0
    private(set) var lastPage = 0
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var loadError: String?

    init(warehouseID: Int, familyID: Int) {
        self.warehouseID = warehouseID
        self.familyID = familyID
    }

    var hasMorePages: Bool {
        currentPage > 0 && currentPage < lastPage
    }

    func reload(apiClient: APIClient) async {
        isLoading = true
        defer { isLoading = false }
        do {
            let page = try await apiClient.fetchOrgStockFamilySkos(warehouseID: warehouseID, familyID: familyID, perPage: Self.pageSize)
            summary = page.family
            skos = page.data
            apply(page.meta)
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    func loadNextPage(apiClient: APIClient) async {
        guard hasMorePages, !isLoading, !isLoadingMore else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await apiClient.fetchOrgStockFamilySkos(warehouseID: warehouseID, familyID: familyID, page: currentPage + 1, perPage: Self.pageSize)
            let ids = Set(skos.map(\.id))
            skos += page.data.filter { !ids.contains($0.id) }
            apply(page.meta)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = error.localizedDescription
        }
    }

    private func apply(_ meta: MobileLocationsPage.Meta) {
        total = meta.total
        currentPage = meta.currentPage
        lastPage = meta.lastPage
    }
}

extension InventoryFamily {
    init(remote: MobileOrgStockFamily) {
        self.init(
            code: remote.code,
            name: remote.name ?? remote.code,
            state: InventoryFamilyState(remoteValue: remote.state) ?? .active,
            skoCount: remote.numberCurrentOrgStocks ?? 0,
            stockValueLabel: InventoryMoney.label(remote.stockValue?.value ?? 0, currencyCode: remote.currencyCode),
            coverWeeks: 0,
            remoteID: remote.id,
            coverMonths: remote.stockCover?.value
        )
    }
}

enum InventoryMoney {
    static func label(_ amount: Double, currencyCode: String?) -> String {
        guard let currencyCode else { return amount.formatted(.number.precision(.fractionLength(0))) }
        return amount.formatted(.currency(code: currencyCode).precision(.fractionLength(0)).locale(Locale(identifier: "en_GB")))
    }
}
