import Foundation

struct MobileOrgStockFamily: Decodable, Sendable, Equatable {
    let id: Int?
    let state: String
    let code: String
    let name: String?
    let numberCurrentOrgStocks: Int?
    let currencyCode: String?
    let stockValue: OptionalFlexibleDouble?
    let stockCover: OptionalFlexibleDouble?
}

struct MobileOrgStockFamiliesPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        let inProcess: Int
        let active: Int
        let discontinuing: Int
        let discontinued: Int
    }

    let data: [MobileOrgStockFamily]
    let meta: MobileLocationsPage.Meta
    let counts: Counts
}

struct MobileFamilySko: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let code: String
    let name: String?
    let state: String
    let quantityInLocations: OptionalFlexibleDouble?
    let stockValue: OptionalFlexibleDouble?
}

struct MobileFamilySkosPage: Decodable, Sendable, Equatable {
    struct Family: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
        let name: String?
        let state: String
        let numberCurrentOrgStocks: Int
        let stockValue: Double
        let currencyCode: String?
    }

    let family: Family
    let data: [MobileFamilySko]
    let meta: MobileLocationsPage.Meta
}

extension Endpoint {
    static func mobileOrgStockFamilies(warehouseID: Int, state: String, search: String, page: Int, perPage: Int) -> Endpoint {
        var items = [
            URLQueryItem(name: "state", value: state),
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)"),
        ]
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty {
            items.append(URLQueryItem(name: "q", value: trimmed))
        }
        return Endpoint(path: "mobile/warehouses/\(warehouseID)/org-stock-families", queryItems: items)
    }

    static func mobileOrgStockFamilySkos(warehouseID: Int, familyID: Int, page: Int, perPage: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/org-stock-families/\(familyID)/skos", queryItems: [
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)"),
        ])
    }
}

extension APIClient {
    func fetchOrgStockFamilies(warehouseID: Int, state: String, search: String = "", page: Int = 1, perPage: Int = 50) async throws -> MobileOrgStockFamiliesPage {
        try await send(.mobileOrgStockFamilies(warehouseID: warehouseID, state: state, search: search, page: page, perPage: perPage), as: MobileOrgStockFamiliesPage.self)
    }

    func fetchOrgStockFamilySkos(warehouseID: Int, familyID: Int, page: Int = 1, perPage: Int = 50) async throws -> MobileFamilySkosPage {
        try await send(.mobileOrgStockFamilySkos(warehouseID: warehouseID, familyID: familyID, page: page, perPage: perPage), as: MobileFamilySkosPage.self)
    }
}
