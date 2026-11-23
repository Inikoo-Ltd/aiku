import Foundation

struct MobileFulfilmentAction: Decodable, Sendable, Equatable {
    let key: String
    let label: String
    var disabledReason: String? = nil
}

struct MobileFulfilmentCustomer: Decodable, Sendable, Equatable {
    let id: Int?
    let name: String?
}

struct MobilePalletDeliveryCounts: Decodable, Sendable, Equatable {
    var pallets = 0
    var boxes = 0
    var oversizes = 0
    var total = 0
    var received: Int? = nil
    var bookingIn: Int? = nil
    var bookedIn = 0
    var notReceived = 0
}

struct MobilePalletDeliveryRow: Decodable, Sendable, Equatable {
    let id: Int
    let reference: String
    var customerReference: String? = nil
    let state: String
    var stateLabel: String? = nil
    var customer: MobileFulfilmentCustomer? = nil
    var estimatedDeliveryDate: String? = nil
    var receivedAt: String? = nil
    var bookedInAt: String? = nil
    var counts: MobilePalletDeliveryCounts = MobilePalletDeliveryCounts()
}

struct MobilePalletDeliveriesPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        var confirmed = 0
        var received = 0
        var bookingIn = 0
        var bookedIn = 0
        var notReceived = 0
        var active = 0
    }

    let data: [MobilePalletDeliveryRow]
    let meta: MobileLocationsPage.Meta
    var counts: Counts = Counts()
}

struct MobileDeliveryPalletStoredItem: Decodable, Sendable, Equatable {
    let id: Int
    var reference: String? = nil
    var name: String? = nil
    var quantity: Int = 0
}

struct MobileDeliveryPallet: Decodable, Sendable, Equatable {
    struct Location: Decodable, Sendable, Equatable {
        let id: Int
        let code: String
    }

    struct Incident: Decodable, Sendable, Equatable {
        var state: String? = nil
        var stateLabel: String? = nil
    }

    let id: Int
    let reference: String
    var customerReference: String? = nil
    var type: String? = nil
    var typeLabel: String? = nil
    let state: String
    var stateLabel: String? = nil
    var status: String? = nil
    var statusLabel: String? = nil
    var location: Location? = nil
    var notes: String? = nil
    var numberStoredItems: Int? = nil
    var incident: Incident? = nil
    var hasRental: Bool? = nil
    var storedItems: [MobileDeliveryPalletStoredItem]? = nil
    var availableActions: [MobileFulfilmentAction]? = nil
}

struct MobilePalletDeliveryTimelineEntry: Decodable, Sendable, Equatable {
    let state: String
    var label: String? = nil
    var at: String? = nil
}

struct MobilePalletDeliveryCharge: Decodable, Sendable, Equatable {
    var code: String? = nil
    var name: String? = nil
    var quantity: Int = 0
    var unitLabel: String? = nil
}

struct MobilePalletDeliveryDetail: Decodable, Sendable, Equatable {
    let id: Int
    let reference: String
    var customerReference: String? = nil
    let state: String
    var stateLabel: String? = nil
    var customer: MobileFulfilmentCustomer? = nil
    var estimatedDeliveryDate: String? = nil
    var customerNotes: String? = nil
    var publicNotes: String? = nil
    var internalNotes: String? = nil
    var timeline: [MobilePalletDeliveryTimelineEntry]? = nil
    var counts: MobilePalletDeliveryCounts = MobilePalletDeliveryCounts()
    var pallets: [MobileDeliveryPallet]? = nil
    var services: [MobilePalletDeliveryCharge]? = nil
    var physicalGoods: [MobilePalletDeliveryCharge]? = nil
    var finishBookingBlocked: String? = nil
    var availableActions: [MobileFulfilmentAction]? = nil
}

struct MobilePalletDeliveryWriteResult: Decodable, Sendable, Equatable {
    let data: MobilePalletDeliveryDetail
    var result: MobileGoodsInResult? = nil
}

struct MobileFulfilmentLocation: Decodable, Sendable, Equatable, Identifiable, Hashable {
    let id: Int
    let code: String
    var numberPallets: Int = 0
}

struct MobileFulfilmentLocations: Decodable, Sendable, Equatable {
    let data: [MobileFulfilmentLocation]
}

struct MobilePalletBookInBody: Encodable, Sendable, Equatable {
    var locationId: Int?
    var locationCode: String?
}

struct MobilePalletDeliveriesQuery: Equatable, Sendable {
    var state = "active"
    var search = ""
    var dates = ListDateRange()
    var sort: String?
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [
            URLQueryItem(name: "state", value: state),
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)")
        ]
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        items.append(contentsOf: dates.queryItems)
        if let sort { items.append(URLQueryItem(name: "sort", value: sort)) }
        return items
    }
}

enum MobilePalletDeliveryWrite: Equatable, Sendable {
    case receive
    case startBooking
    case finishBooking
    case bookIn(palletID: Int, body: MobilePalletBookInBody)
    case notReceived(palletID: Int)
    case undoNotReceived(palletID: Int)

    var palletID: Int? {
        switch self {
        case .bookIn(let id, _), .notReceived(let id), .undoNotReceived(let id): id
        case .receive, .startBooking, .finishBooking: nil
        }
    }
}

extension Endpoint {
    static func mobilePalletDeliveries(warehouseID: Int, query: MobilePalletDeliveriesQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallet-deliveries", queryItems: query.queryItems)
    }

    static func mobilePalletDelivery(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallet-deliveries/\(id)")
    }

    static func mobileFulfilmentLocations(warehouseID: Int, search: String) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/fulfilment-locations", queryItems: [URLQueryItem(name: "q", value: search)])
    }

    static func mobilePalletDeliveryWrite(warehouseID: Int, deliveryID: Int, write: MobilePalletDeliveryWrite) -> Endpoint {
        let base = "mobile/warehouses/\(warehouseID)"
        switch write {
        case .receive: return .jsonBody("\(base)/pallet-deliveries/\(deliveryID)/receive", method: .post, MobileEmptyBody())
        case .startBooking: return .jsonBody("\(base)/pallet-deliveries/\(deliveryID)/start-booking", method: .post, MobileEmptyBody())
        case .finishBooking: return .jsonBody("\(base)/pallet-deliveries/\(deliveryID)/finish-booking", method: .post, MobileEmptyBody())
        case .bookIn(let palletID, let body): return .jsonBody("\(base)/pallets/\(palletID)/book-in", method: .post, body)
        case .notReceived(let palletID): return .jsonBody("\(base)/pallets/\(palletID)/not-received", method: .post, MobileEmptyBody())
        case .undoNotReceived(let palletID): return .jsonBody("\(base)/pallets/\(palletID)/undo-not-received", method: .post, MobileEmptyBody())
        }
    }
}

extension APIClient {
    func fetchPalletDeliveries(warehouseID: Int, query: MobilePalletDeliveriesQuery) async throws -> MobilePalletDeliveriesPage {
        try await send(.mobilePalletDeliveries(warehouseID: warehouseID, query: query), as: MobilePalletDeliveriesPage.self)
    }

    func fetchPalletDelivery(warehouseID: Int, id: Int) async throws -> MobilePalletDeliveryDetail {
        try await send(.mobilePalletDelivery(warehouseID: warehouseID, id: id), as: DataEnvelope<MobilePalletDeliveryDetail>.self).value
    }

    func fetchFulfilmentLocations(warehouseID: Int, search: String) async throws -> [MobileFulfilmentLocation] {
        try await send(.mobileFulfilmentLocations(warehouseID: warehouseID, search: search), as: MobileFulfilmentLocations.self).data
    }

    func writePalletDelivery(warehouseID: Int, deliveryID: Int, write: MobilePalletDeliveryWrite) async throws -> MobilePalletDeliveryWriteResult {
        try await send(.mobilePalletDeliveryWrite(warehouseID: warehouseID, deliveryID: deliveryID, write: write), as: MobilePalletDeliveryWriteResult.self)
    }
}
