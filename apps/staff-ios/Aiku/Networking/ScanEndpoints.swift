import Foundation

enum MobileScanType: String, Sendable {
    case sko
    case location
    case deliveryNote = "delivery_note"
    case stockDelivery = "stock_delivery"
    case unknown
}

struct MobileScannedSko: Decodable, Equatable, Sendable {
    struct Location: Decodable, Equatable, Sendable {
        let code: String
        var quantity: Double? = nil
    }

    let id: Int
    let code: String
    var name: String? = nil
    var state: String? = nil
    var barcode: String? = nil
    var unitBarcode: String? = nil
    var packedIn: Double? = nil
    var quantityInLocations: Double? = nil
    var imageUrl: String? = nil
    var locations: [Location] = []
    var matchedOn: String? = nil

    private enum CodingKeys: String, CodingKey {
        case id, code, name, state, barcode, unitBarcode, packedIn, quantityInLocations, imageUrl, locations, matchedOn
    }

    init(id: Int, code: String, name: String? = nil, quantityInLocations: Double? = nil, locations: [Location] = []) {
        self.id = id
        self.code = code
        self.name = name
        self.quantityInLocations = quantityInLocations
        self.locations = locations
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        code = try container.decode(String.self, forKey: .code)
        name = try container.decodeIfPresent(String.self, forKey: .name)
        state = try container.decodeIfPresent(String.self, forKey: .state)
        barcode = try container.decodeIfPresent(String.self, forKey: .barcode)
        unitBarcode = try container.decodeIfPresent(String.self, forKey: .unitBarcode)
        packedIn = try container.decodeIfPresent(Double.self, forKey: .packedIn)
        quantityInLocations = try container.decodeIfPresent(Double.self, forKey: .quantityInLocations)
        imageUrl = try container.decodeIfPresent(String.self, forKey: .imageUrl)
        locations = try container.decodeIfPresent([Location].self, forKey: .locations) ?? []
        matchedOn = try container.decodeIfPresent(String.self, forKey: .matchedOn)
    }
}

struct MobileScannedLocation: Decodable, Equatable, Sendable {
    struct Area: Decodable, Equatable, Sendable {
        let id: Int
        var code: String? = nil
        var name: String? = nil
    }

    let id: Int
    let code: String
    var slug: String? = nil
    var warehouseArea: Area? = nil
    var numberOrgStocks: Double? = nil
}

struct MobileScannedDeliveryNote: Decodable, Equatable, Sendable {
    let id: Int
    let reference: String
    var state: String? = nil
    var stateLabel: String? = nil
    var customerName: String? = nil
    var numberItems: Double? = nil
}

struct MobileScannedStockDelivery: Decodable, Equatable, Sendable {
    let id: Int
    let reference: String
    var state: String? = nil
    var stateLabel: String? = nil
}

struct MobileScanResult: Decodable, Equatable, Sendable {
    let code: String
    let type: String
    var sko: MobileScannedSko? = nil
    var location: MobileScannedLocation? = nil
    var deliveryNote: MobileScannedDeliveryNote? = nil
    var stockDelivery: MobileScannedStockDelivery? = nil

    var scanType: MobileScanType { MobileScanType(rawValue: type) ?? .unknown }
}

extension Endpoint {
    static func mobileScan(warehouseID: Int, code: String) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/scan", queryItems: [URLQueryItem(name: "code", value: code)])
    }
}

extension APIClient {
    func scan(warehouseID: Int, code: String) async throws -> MobileScanResult {
        try await send(.mobileScan(warehouseID: warehouseID, code: code), as: DataEnvelope<MobileScanResult>.self).value
    }
}
