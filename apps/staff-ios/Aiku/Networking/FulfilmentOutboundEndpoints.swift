import Foundation

struct MobilePalletReturnPlatform: Decodable, Sendable, Equatable {
    var code: String? = nil
    var name: String? = nil

    var label: String? { [name, code].compactMap { $0 }.first { !$0.isEmpty } }
}

struct MobilePalletReturnRow: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let reference: String
    var customerReference: String? = nil
    var type: String? = nil
    var typeGroup: String = "pallets"
    var typeLabel: String? = nil
    var platform: MobilePalletReturnPlatform? = nil
    let state: String
    var stateLabel: String? = nil
    var customer: MobileFulfilmentCustomer? = nil
    var numberPallets: Int = 0
    var numberItems: Int = 0
    var date: String? = nil
    var picker: MobileFulfilmentCustomer? = nil
    var isCollection: Bool = false
}

struct MobilePalletReturnsPage: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        struct Types: Decodable, Sendable, Equatable {
            var pallets = 0
            var items = 0
        }

        var confirmed = 0
        var picking = 0
        var picked = 0
        var dispatched = 0
        var cancel = 0
        var active = 0
        var types: Types = Types()
    }

    let data: [MobilePalletReturnRow]
    let meta: MobileLocationsPage.Meta
    var counts: Counts = Counts()
}

struct MobileReturnPalletRef: Decodable, Sendable, Equatable {
    let id: Int
    var reference: String? = nil
    var customerReference: String? = nil
    var type: String? = nil
    var typeLabel: String? = nil
    var state: String? = nil
    var stateLabel: String? = nil

    func matches(_ code: String) -> Bool {
        reference?.caseInsensitiveCompare(code) == .orderedSame || customerReference?.caseInsensitiveCompare(code) == .orderedSame
    }
}

struct MobileReturnPalletLine: Decodable, Sendable, Equatable, Identifiable {
    struct Content: Decodable, Sendable, Equatable {
        var reference: String? = nil
        var name: String? = nil
        var quantity: Int = 0
    }

    let lineId: Int
    let lineState: String
    var lineStateLabel: String? = nil
    var waitingCustomerService: Bool = false
    let pallet: MobileReturnPalletRef?
    var location: MobileStoredPallet.Location? = nil
    var storedItems: [Content] = []
    var availableActions: [MobileFulfilmentAction] = []

    var id: Int { lineId }
    func can(_ key: String) -> Bool { availableActions.contains { $0.key == key && $0.disabledReason == nil } }
    var hasActions: Bool { availableActions.contains { $0.disabledReason == nil } }
    func label(_ key: String) -> String? { availableActions.first { $0.key == key }?.label.nilIfEmpty }
}

struct MobileReturnItemLine: Decodable, Sendable, Equatable, Identifiable {
    let lineId: Int
    var pallet: MobileReturnPalletRef? = nil
    var location: MobileStoredPallet.Location? = nil
    var quantityOrdered: Int = 0
    var quantityPicked: Int = 0
    var quantityNotPicked: Int = 0
    var quantityToPick: Int = 0
    var quantityOnPallet: Int = 0
    var state: String? = nil
    var stateLabel: String? = nil
    var waitingCustomerService: Bool = false
    var availableActions: [MobileFulfilmentAction] = []

    var id: Int { lineId }
    func can(_ key: String) -> Bool { availableActions.contains { $0.key == key && $0.disabledReason == nil } }
    var hasActions: Bool { availableActions.contains { $0.disabledReason == nil } }
    func label(_ key: String) -> String? { availableActions.first { $0.key == key }?.label.nilIfEmpty }
    var maxPick: Int { max(min(quantityToPick, quantityOnPallet), 0) }
    var shortfall: Int { max(quantityToPick - quantityOnPallet, 0) }
}

struct MobileReturnOtherPallet: Decodable, Sendable, Equatable, Identifiable {
    let palletStoredItemId: Int
    var pallet: MobileReturnPalletRef? = nil
    var location: MobileStoredPallet.Location? = nil
    var quantityAvailable: Int = 0

    var id: Int { palletStoredItemId }
}

struct MobileReturnStoredItem: Decodable, Sendable, Equatable, Identifiable {
    struct Item: Decodable, Sendable, Equatable {
        var id: Int? = nil
        var reference: String? = nil
        var name: String? = nil
        var barcode: String? = nil

        func matches(_ code: String) -> Bool {
            reference?.caseInsensitiveCompare(code) == .orderedSame || barcode?.caseInsensitiveCompare(code) == .orderedSame
        }
    }

    let storedItem: Item
    var quantityOrdered: Int = 0
    var quantityPicked: Int = 0
    var quantityNotPicked: Int = 0
    var quantityToPick: Int = 0
    var lines: [MobileReturnItemLine] = []
    var otherPallets: [MobileReturnOtherPallet] = []

    var id: Int { storedItem.id ?? lines.first?.lineId ?? 0 }
    var shortLine: MobileReturnItemLine? { lines.first { $0.quantityToPick > 0 && $0.shortfall > 0 } }
    var canPickFromOtherPallet: Bool {
        quantityToPick > 0 && shortLine != nil && otherPallets.contains { $0.quantityAvailable > 0 } && lines.contains { $0.hasActions }
    }
}

struct MobileReturnShipment: Decodable, Sendable, Equatable, Identifiable {
    struct Label: Decodable, Sendable, Equatable {
        var kind: String = "none"
        var url: String? = nil
    }

    let id: Int
    var shipper: String? = nil
    var shipperCode: String? = nil
    var tracking: String? = nil
    var trackings: [String]? = nil
    var trackingUrls: [String?]? = nil
    var isPrintable: Bool = false
    var label: Label = Label()
    var canDelete: Bool = false

    var trackingNumbers: [String] {
        let list = (trackings ?? []).filter { !$0.isEmpty }
        if !list.isEmpty { return list }
        return tracking.map { [$0] }?.filter { !$0.isEmpty } ?? []
    }

    func trackingURL(_ index: Int) -> URL? {
        guard let urls = trackingUrls, index < urls.count, let raw = urls[index], !raw.isEmpty else { return nil }
        return URL(string: raw)
    }
}

struct MobileReturnParcel: Decodable, Sendable, Equatable {
    var weight: Double = 0
    var dimensions: [Double] = []
}

struct MobilePalletReturnDetail: Decodable, Sendable, Equatable {
    struct Counts: Decodable, Sendable, Equatable {
        var lines = 0
        var picked = 0
        var notPicked = 0
        var toPick = 0
        var waitingCustomerService = 0
    }

    struct Inserts: Decodable, Sendable, Equatable {
        var total = 0
        var unprinted = 0
    }

    let id: Int
    let reference: String
    var customerReference: String? = nil
    var type: String? = nil
    var typeGroup: String = "pallets"
    var typeLabel: String? = nil
    var platform: MobilePalletReturnPlatform? = nil
    let state: String
    var stateLabel: String? = nil
    var customer: MobileFulfilmentCustomer? = nil
    var isCollection: Bool = false
    var collectionNotes: String? = nil
    var customerNotes: String? = nil
    var publicNotes: String? = nil
    var internalNotes: String? = nil
    var picker: MobileFulfilmentCustomer? = nil
    var packer: MobileFulfilmentCustomer? = nil
    var estimatedDeliveryDate: String? = nil
    var inPickingSession: Bool = false
    var timeline: [MobilePalletDeliveryTimelineEntry]? = nil
    var counts: Counts = Counts()
    var pallets: [MobileReturnPalletLine] = []
    var storedItems: [MobileReturnStoredItem] = []
    var inserts: Inserts = Inserts()
    var shipments: [MobileReturnShipment] = []
    var parcels: [MobileReturnParcel] = []
    var shipmentRequired: Bool = false
    var dispatchBlocked: String? = nil
    var canScan: Bool = false
    var availableActions: [MobileFulfilmentAction] = []

    var isItems: Bool { typeGroup == "items" }
    func action(_ key: String) -> MobileFulfilmentAction? { availableActions.first { $0.key == key && $0.disabledReason == nil } }
    var hasLineActions: Bool { pallets.contains { $0.hasActions } || storedItems.contains { $0.lines.contains { $0.hasActions } } }
    func palletLine(_ id: Int) -> MobileReturnPalletLine? { pallets.first { $0.lineId == id } }
    func itemLine(_ id: Int) -> (MobileReturnStoredItem, MobileReturnItemLine)? {
        for item in storedItems {
            if let line = item.lines.first(where: { $0.lineId == id }) { return (item, line) }
        }
        return nil
    }
    func storedItem(_ id: Int) -> MobileReturnStoredItem? { storedItems.first { $0.id == id } }
    func at(_ state: String) -> String? { timeline?.first { $0.state == state }?.at }
}

struct MobilePalletReturnWriteResult: Decodable, Sendable, Equatable {
    struct Result: Decodable, Sendable, Equatable {
        var status: String? = nil
        var message: String? = nil
        var lineId: Int? = nil
    }

    let data: MobilePalletReturnDetail
    var result: Result? = nil
}

struct MobileReturnParcelBody: Encodable, Sendable, Equatable {
    let weightKg: Double
    let dimensionsCm: [Double]
}

enum MobilePalletReturnWrite: Equatable, Sendable {
    case startPicking
    case finishPicking
    case dispatch
    case printInserts
    case pickPallet(lineID: Int, requestID: String)
    case pickItem(lineID: Int, quantity: Int?, requestID: String)
    case notPicked(lineID: Int, quantity: Int?)
    case undo(lineID: Int)
    case pickFromOtherPallet(palletStoredItemID: Int, quantity: Int, fromLineID: Int?, requestID: String)
    case scan(barcode: String, requestID: String)
    case setParcels([MobileReturnParcelBody])
    case addShipment(shipperID: Int, tracking: String?)
    case deleteShipment(id: Int)
    case printLabel(shipmentID: Int)
}

private struct PickBody: Encodable, Sendable {
    var quantity: Int?
    var all: Bool?
    let clientRequestId: String
}

private struct NotPickedBody: Encodable, Sendable {
    var quantity: Int?
}

private struct OtherPalletBody: Encodable, Sendable {
    let quantity: Int
    var fromLineId: Int?
    let clientRequestId: String
}

private struct ScanBody: Encodable, Sendable {
    let barcode: String
    let quantity: Int
    let clientRequestId: String
}

private struct ParcelsBody: Encodable, Sendable {
    let parcels: [MobileReturnParcelBody]
}

private struct ShipmentBody: Encodable, Sendable {
    let shipperId: Int
    var tracking: String?
}

struct MobilePalletReturnsQuery: Equatable, Sendable {
    var state = "active"
    var type: String?
    var search = ""
    var sort: String?
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [
            URLQueryItem(name: "state", value: state),
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)")
        ]
        if let type { items.append(URLQueryItem(name: "type", value: type)) }
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        if let sort { items.append(URLQueryItem(name: "sort", value: sort)) }
        return items
    }
}

extension Endpoint {
    static func mobilePalletReturns(warehouseID: Int, query: MobilePalletReturnsQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallet-returns", queryItems: query.queryItems)
    }

    static func mobilePalletReturn(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallet-returns/\(id)")
    }

    static func mobilePalletReturnShippers(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallet-returns/\(id)/shippers")
    }

    static func mobilePalletReturnLabel(warehouseID: Int, id: Int, shipmentID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pallet-returns/\(id)/shipments/\(shipmentID)/label")
    }

    static func mobilePalletReturnWrite(warehouseID: Int, id: Int, write: MobilePalletReturnWrite) -> Endpoint {
        let base = "mobile/warehouses/\(warehouseID)/pallet-returns/\(id)"
        switch write {
        case .startPicking: return .jsonBody("\(base)/start-picking", method: .post, MobileEmptyBody())
        case .finishPicking: return .jsonBody("\(base)/finish-picking", method: .post, MobileEmptyBody())
        case .dispatch: return .jsonBody("\(base)/dispatch", method: .post, MobileEmptyBody())
        case .printInserts: return .jsonBody("\(base)/inserts/print", method: .post, MobileEmptyBody())
        case .pickPallet(let lineID, let requestID):
            return .jsonBody("\(base)/lines/\(lineID)/pick", method: .post, PickBody(quantity: nil, all: true, clientRequestId: requestID))
        case .pickItem(let lineID, let quantity, let requestID):
            return .jsonBody("\(base)/lines/\(lineID)/pick", method: .post, PickBody(quantity: quantity, all: quantity == nil ? true : nil, clientRequestId: requestID))
        case .notPicked(let lineID, let quantity):
            return .jsonBody("\(base)/lines/\(lineID)/not-picked", method: .post, NotPickedBody(quantity: quantity))
        case .undo(let lineID): return .jsonBody("\(base)/lines/\(lineID)/undo", method: .post, MobileEmptyBody())
        case .pickFromOtherPallet(let palletStoredItemID, let quantity, let fromLineID, let requestID):
            return .jsonBody("\(base)/pallet-stored-items/\(palletStoredItemID)/pick", method: .post, OtherPalletBody(quantity: quantity, fromLineId: fromLineID, clientRequestId: requestID))
        case .scan(let barcode, let requestID):
            return .jsonBody("\(base)/scan", method: .post, ScanBody(barcode: barcode, quantity: 1, clientRequestId: requestID))
        case .setParcels(let parcels): return .jsonBody("\(base)/parcels", method: .post, ParcelsBody(parcels: parcels))
        case .addShipment(let shipperID, let tracking):
            return .jsonBody("\(base)/shipments", method: .post, ShipmentBody(shipperId: shipperID, tracking: tracking))
        case .deleteShipment(let shipmentID): return Endpoint(path: "\(base)/shipments/\(shipmentID)", method: .delete)
        case .printLabel(let shipmentID): return .jsonBody("\(base)/shipments/\(shipmentID)/print-label", method: .post, MobileEmptyBody())
        }
    }
}

extension APIClient {
    func fetchPalletReturns(warehouseID: Int, query: MobilePalletReturnsQuery) async throws -> MobilePalletReturnsPage {
        try await send(.mobilePalletReturns(warehouseID: warehouseID, query: query), as: MobilePalletReturnsPage.self)
    }

    func fetchPalletReturn(warehouseID: Int, id: Int) async throws -> MobilePalletReturnDetail {
        try await send(.mobilePalletReturn(warehouseID: warehouseID, id: id), as: DataEnvelope<MobilePalletReturnDetail>.self).value
    }

    func fetchPalletReturnShippers(warehouseID: Int, id: Int) async throws -> GoodsOutShippersDTO {
        try await send(.mobilePalletReturnShippers(warehouseID: warehouseID, id: id), as: GoodsOutShippersDTO.self)
    }

    func downloadPalletReturnLabel(warehouseID: Int, id: Int, shipmentID: Int) async throws -> Data {
        try await send(.mobilePalletReturnLabel(warehouseID: warehouseID, id: id, shipmentID: shipmentID))
    }

    func writePalletReturn(warehouseID: Int, id: Int, write: MobilePalletReturnWrite) async throws -> MobilePalletReturnWriteResult {
        try await send(.mobilePalletReturnWrite(warehouseID: warehouseID, id: id, write: write), as: MobilePalletReturnWriteResult.self)
    }
}

extension String {
    var nilIfEmpty: String? { isEmpty ? nil : self }
}
