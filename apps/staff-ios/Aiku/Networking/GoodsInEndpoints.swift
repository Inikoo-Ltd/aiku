import Foundation

struct MobileGoodsInHubEntry: Decodable, Sendable, Equatable {
    let key: String
    let label: String
    let arriving: Int?
    let received: Int?
    let checked: Int?
    let bookingIn: Int?
    let bookedIn7D: Int?
    let placed7D: Int?
}

struct MobileGoodsInHub: Decodable, Sendable, Equatable {
    let data: [MobileGoodsInHubEntry]
}

struct MobileGoodsInParty: Decodable, Sendable, Equatable {
    let type: String?
    let code: String?
    let name: String?
}

struct MobileGoodsInLocationRef: Decodable, Sendable, Equatable {
    let id: Int
    let code: String
}

struct MobileGoodsInAction: Decodable, Sendable, Equatable {
    let key: String
    let label: String
}

struct MobileStockDeliveryRow: Decodable, Sendable, Equatable {
    let id: Int
    let reference: String
    let agentReference: String?
    let supplier: MobileGoodsInParty?
    let source: String?
    let state: String
    let stateLabel: String?
    let dispatchedAt: String?
    let receivedAt: String?
    let estimatedReceivingDate: String?
    let numberItems: Int?
    let numberChecked: Int?
    let numberPlaced: Int?
}

struct MobileStockDeliveriesPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        let dispatched: Int
        let received: Int
        let checked: Int
        let bookingIn: Int
        let bookedIn: Int
        let placed: Int
        let notReceived: Int
        let cancelled: Int
        let active: Int
    }

    let data: [MobileStockDeliveryRow]
    let meta: MobileLocationsPage.Meta
    let counts: Counts
}

struct MobileStockDeliveryItem: Decodable, Sendable, Equatable {
    struct Sko: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
        let name: String?
        let packedIn: OptionalFlexibleDouble?
        let barcode: String?
    }

    struct Location: Decodable, Sendable, Equatable {
        let locationOrgStockId: Int
        let location: MobileGoodsInLocationRef
        let type: String?
        let quantity: OptionalFlexibleDouble?
        let isDefaultWholesale: Bool?
        let isDefaultDropshipping: Bool?
    }

    struct Placement: Decodable, Sendable, Equatable {
        let id: Int
        let quantity: OptionalFlexibleDouble?
        let location: MobileGoodsInLocationRef?
    }

    let id: Int
    let state: String
    let isNewOrgStock: Bool?
    let sko: Sko?
    let skoExpected: OptionalFlexibleDouble?
    let skoChecked: OptionalFlexibleDouble?
    let skoPlaced: OptionalFlexibleDouble?
    let skoToPlace: OptionalFlexibleDouble?
    let locations: [Location]?
    let placements: [Placement]?
}

struct MobileStockDeliveryChecklist: Decodable, Sendable, Equatable {
    struct Section: Decodable, Sendable, Equatable {
        let key: String
        let title: String
        let checks: [Check]
    }

    struct Check: Decodable, Sendable, Equatable {
        struct Person: Decodable, Sendable, Equatable {
            let name: String?
        }

        let key: String
        let label: String
        let detail: String?
        let result: String?
        let checkedBy: Person?
        let checkedAt: String?
    }

    let sections: [Section]
}

struct MobileStockDeliveryDetail: Decodable, Sendable, Equatable {
    struct PurchaseOrder: Decodable, Sendable, Equatable {
        let id: Int
        let reference: String
    }

    struct Delivery: Decodable, Sendable, Equatable {
        let typeLabel: String?
        let containerSize: String?
        let incoterm: String?
        let portOfExport: String?
        let portOfImport: String?
        let shipperReference: String?
        let trackingNumber: String?
        let containerNumber: String?
        let shippingAgent: String?
    }

    let id: Int
    let reference: String
    let agentReference: String?
    let state: String
    let stateLabel: String?
    let supplier: MobileGoodsInParty?
    let source: String?
    let dispatchedAt: String?
    let receivedAt: String?
    let estimatedReceivingDate: String?
    let delivery: Delivery?
    let purchaseOrders: [PurchaseOrder]?
    let items: [MobileStockDeliveryItem]
    let checklist: MobileStockDeliveryChecklist?
    let availableActions: [MobileGoodsInAction]?
}

struct MobileGoodsInResult: Decodable, Sendable, Equatable {
    let status: String?
    let message: String?
}

struct MobileStockDeliveryWriteResult: Decodable, Sendable, Equatable {
    let data: MobileStockDeliveryDetail
    let result: MobileGoodsInResult?
}

struct MobileReturnRow: Decodable, Sendable, Equatable {
    struct Customer: Decodable, Sendable, Equatable {
        let name: String?
    }

    let id: Int
    let reference: String
    let typeLabel: String?
    let state: String
    let stateLabel: String?
    let createdAt: String?
    let customer: Customer?
    let numberItems: Int?
    let numberHandled: Int?
}

struct MobileReturnsPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        let received: Int
        let returning: Int
        let returned: Int
        let done: Int
        let cancelled: Int
        let all: Int
    }

    let data: [MobileReturnRow]
    let meta: MobileLocationsPage.Meta
    let counts: Counts
}

struct MobileReturnItem: Decodable, Sendable, Equatable {
    struct Put: Decodable, Sendable, Equatable {
        let id: Int
        let type: String
        let quantity: OptionalFlexibleDouble?
        let location: MobileGoodsInLocationRef?
    }

    let id: Int
    let state: String?
    let isHandled: Bool?
    let sko: MobileStockDeliveryItem.Sko?
    let expected: OptionalFlexibleDouble?
    let returned: OptionalFlexibleDouble?
    let damaged: OptionalFlexibleDouble?
    let notReturned: OptionalFlexibleDouble?
    let remaining: OptionalFlexibleDouble?
    let puts: [Put]?
    let locations: [MobileStockDeliveryItem.Location]?
}

struct MobileReturnDetail: Decodable, Sendable, Equatable {
    let id: Int
    let reference: String
    let typeLabel: String?
    let state: String
    let stateLabel: String?
    let customer: MobileReturnRow.Customer?
    let items: [MobileReturnItem]
    let availableActions: [MobileGoodsInAction]?
}

struct MobileReturnWriteResult: Decodable, Sendable, Equatable {
    let data: MobileReturnDetail
    let result: MobileGoodsInResult?
}

struct MobileStockDeliveriesQuery: Equatable, Sendable {
    var state: String?
    var source: String?
    var dispatched: String?
    var search = ""
    var sort: String?
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [URLQueryItem(name: "page", value: "\(page)"), URLQueryItem(name: "per_page", value: "\(perPage)")]
        if let state { items.append(URLQueryItem(name: "state", value: state)) }
        if let source { items.append(URLQueryItem(name: "source", value: source)) }
        if let dispatched { items.append(URLQueryItem(name: "dispatched", value: dispatched)) }
        if let sort { items.append(URLQueryItem(name: "sort", value: sort)) }
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        return items
    }
}

struct MobileCheckItemBody: Encodable, Sendable {
    let skoQuantity: Double
}

struct MobileChecklistBody: Encodable, Sendable {
    let key: String
    let result: String
    let body: String?
}

struct MobilePlaceItemBody: Encodable, Sendable, Equatable {
    var quantity: Double?
    var locationOrgStockId: Int?
    var locationId: Int?
    var setAsPickingLocation: Bool
    var clientRequestId: String
}

struct MobileReturnPutBody: Encodable, Sendable {
    var quantity: Double?
    var locationOrgStockId: Int?
}

struct MobileEmptyBody: Encodable, Sendable {}

extension Endpoint {
    static func mobileGoodsInHub(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/goods-in-hub")
    }

    static func mobileStockDeliveries(warehouseID: Int, query: MobileStockDeliveriesQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/stock-deliveries", queryItems: query.queryItems)
    }

    static func mobileStockDelivery(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/stock-deliveries/\(id)")
    }

    static func mobileStockDeliveryAction(warehouseID: Int, id: Int, path: String, body: some Encodable & Sendable) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/stock-deliveries/\(id)/\(path)", method: .post, body)
    }

    static func mobileReturns(warehouseID: Int, state: String?, search: String, page: Int, perPage: Int) -> Endpoint {
        var items = [URLQueryItem(name: "page", value: "\(page)"), URLQueryItem(name: "per_page", value: "\(perPage)")]
        if let state { items.append(URLQueryItem(name: "state", value: state)) }
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        return Endpoint(path: "mobile/warehouses/\(warehouseID)/returns", queryItems: items)
    }

    static func mobileReturn(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/returns/\(id)")
    }

    static func mobileReturnAction(warehouseID: Int, id: Int, path: String, body: some Encodable & Sendable) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/returns/\(id)/\(path)", method: .post, body)
    }
}

extension APIClient {
    func fetchGoodsInHub(warehouseID: Int) async throws -> [MobileGoodsInHubEntry] {
        try await send(.mobileGoodsInHub(warehouseID: warehouseID), as: MobileGoodsInHub.self).data
    }

    func fetchStockDeliveries(warehouseID: Int, query: MobileStockDeliveriesQuery) async throws -> MobileStockDeliveriesPage {
        try await send(.mobileStockDeliveries(warehouseID: warehouseID, query: query), as: MobileStockDeliveriesPage.self)
    }

    func fetchStockDelivery(warehouseID: Int, id: Int) async throws -> MobileStockDeliveryDetail {
        try await send(.mobileStockDelivery(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileStockDeliveryDetail>.self).value
    }

    func stockDeliveryAction(warehouseID: Int, id: Int, path: String, body: some Encodable & Sendable = MobileEmptyBody()) async throws -> MobileStockDeliveryWriteResult {
        try await send(.mobileStockDeliveryAction(warehouseID: warehouseID, id: id, path: path, body: body), as: MobileStockDeliveryWriteResult.self)
    }

    func fetchReturns(warehouseID: Int, state: String?, search: String = "", page: Int = 1, perPage: Int = 50) async throws -> MobileReturnsPage {
        try await send(.mobileReturns(warehouseID: warehouseID, state: state, search: search, page: page, perPage: perPage), as: MobileReturnsPage.self)
    }

    func fetchReturn(warehouseID: Int, id: Int) async throws -> MobileReturnDetail {
        try await send(.mobileReturn(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileReturnDetail>.self).value
    }

    func returnAction(warehouseID: Int, id: Int, path: String, body: some Encodable & Sendable = MobileEmptyBody()) async throws -> MobileReturnWriteResult {
        try await send(.mobileReturnAction(warehouseID: warehouseID, id: id, path: path, body: body), as: MobileReturnWriteResult.self)
    }
}
