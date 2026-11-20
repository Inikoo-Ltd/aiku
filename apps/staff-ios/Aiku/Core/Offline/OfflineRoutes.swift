import Foundation

enum OfflineRoutes {
    static func mobileRoute(of url: URL) -> [String]? {
        let segments = url.pathComponents.filter { $0 != "/" && !$0.isEmpty }
        let prefix = ["app", "api", "mobile"]
        guard segments.count >= prefix.count else { return nil }
        for start in 0...(segments.count - prefix.count) where Array(segments[start..<start + prefix.count]) == prefix {
            return Array(segments[(start + prefix.count)...])
        }
        return nil
    }

    static func detailRecord(of route: [String]) -> OfflineRecordKey? {
        guard let (record, length) = recordPrefix(of: route), length == route.count else { return nil }
        return record
    }

    static func record(under route: [String]) -> OfflineRecordKey? {
        recordPrefix(of: route)?.0
    }

    static func detailPath(_ record: OfflineRecordKey, warehouseID: Int) -> String {
        switch record.type {
        case .deliveryNote: "mobile/delivery-notes/\(record.id)"
        case .location: "mobile/locations/\(record.id)"
        case .customerReturn: "mobile/warehouses/\(warehouseID)/returns/\(record.id)"
        case .stockDelivery: "mobile/warehouses/\(warehouseID)/stock-deliveries/\(record.id)"
        case .orgStock: "mobile/warehouses/\(warehouseID)/org-stocks/\(record.id)"
        }
    }

    static func warehouseList(of route: [String], named list: String) -> Int? {
        guard route.count == 3, route[0] == "warehouses", route[2] == list else { return nil }
        return Int(route[1])
    }

    private static func recordPrefix(of route: [String]) -> (OfflineRecordKey, Int)? {
        guard let first = route.first else { return nil }
        switch first {
        case "delivery-notes":
            return route.count > 1 ? Int(route[1]).map { (OfflineRecordKey(type: .deliveryNote, id: $0), 2) } : nil
        case "locations":
            return route.count > 1 ? Int(route[1]).map { (OfflineRecordKey(type: .location, id: $0), 2) } : nil
        case "warehouses":
            guard route.count > 3, Int(route[1]) != nil, let id = Int(route[3]) else { return nil }
            let type: OfflineRecordType? = switch route[2] {
            case "returns": .customerReturn
            case "stock-deliveries": .stockDelivery
            case "org-stocks": .orgStock
            default: nil
            }
            return type.map { (OfflineRecordKey(type: $0, id: id), 4) }
        default:
            return nil
        }
    }
}
