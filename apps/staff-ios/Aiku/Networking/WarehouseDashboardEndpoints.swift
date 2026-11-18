import Foundation

struct MobileWarehouseDashboard: Sendable, Equatable {
    struct DispatchedToday: Sendable, Equatable {
        var count = 0
        var yesterdayByNow: Int?
        var sameDayLastWeek: Int?
    }

    struct GoodsOut: Sendable, Equatable {
        var unassigned = 0
        var handling = 0
        var handlingBlocked = 0
        var picked: Int?
        var packed = 0
        var inWarehouse = 0
        var dispatchedToday: DispatchedToday?
    }

    struct GoodsIn: Sendable, Equatable {
        var arriving = 0
        var received = 0
        var checked = 0
        var bookingIn = 0
        var returnsToProcess: Int?
    }

    struct Inventory: Sendable, Equatable {
        var numberCurrentOrgStocks = 0
        var belowMinimumStock = 0
        var negativeStocks = 0
        var numberAbnormalities = 0
    }

    var generatedAt: String?
    var goodsOut: GoodsOut?
    var goodsIn: GoodsIn?
    var inventory: Inventory?
}

extension MobileWarehouseDashboard: Decodable {
    private enum CodingKeys: String, CodingKey {
        case generatedAt, goodsOut, goodsIn, inventory
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        generatedAt = try? container.decodeIfPresent(String.self, forKey: .generatedAt)
        goodsOut = try? container.decodeIfPresent(GoodsOut.self, forKey: .goodsOut)
        goodsIn = try? container.decodeIfPresent(GoodsIn.self, forKey: .goodsIn)
        inventory = try? container.decodeIfPresent(Inventory.self, forKey: .inventory)
    }
}

extension MobileWarehouseDashboard.DispatchedToday: Decodable {
    private enum CodingKeys: String, CodingKey {
        case count, yesterdayByNow, sameDayLastWeek
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        count = container.dashboardCount(.count) ?? 0
        yesterdayByNow = container.dashboardCount(.yesterdayByNow)
        sameDayLastWeek = container.dashboardCount(.sameDayLastWeek)
    }
}

extension MobileWarehouseDashboard.GoodsOut: Decodable {
    private enum CodingKeys: String, CodingKey {
        case unassigned, handling, handlingBlocked, picked, packed, inWarehouse, dispatchedToday
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        unassigned = container.dashboardCount(.unassigned) ?? 0
        handling = container.dashboardCount(.handling) ?? 0
        handlingBlocked = container.dashboardCount(.handlingBlocked) ?? 0
        picked = container.dashboardCount(.picked)
        packed = container.dashboardCount(.packed) ?? 0
        inWarehouse = container.dashboardCount(.inWarehouse) ?? 0
        dispatchedToday = try? container.decodeIfPresent(MobileWarehouseDashboard.DispatchedToday.self, forKey: .dispatchedToday)
    }
}

extension MobileWarehouseDashboard.GoodsIn: Decodable {
    private enum CodingKeys: String, CodingKey {
        case arriving, received, checked, bookingIn, returnsToProcess
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        arriving = container.dashboardCount(.arriving) ?? 0
        received = container.dashboardCount(.received) ?? 0
        checked = container.dashboardCount(.checked) ?? 0
        bookingIn = container.dashboardCount(.bookingIn) ?? 0
        returnsToProcess = container.dashboardCount(.returnsToProcess)
    }
}

extension MobileWarehouseDashboard.Inventory: Decodable {
    private enum CodingKeys: String, CodingKey {
        case numberCurrentOrgStocks, belowMinimumStock, negativeStocks, numberAbnormalities
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        numberCurrentOrgStocks = container.dashboardCount(.numberCurrentOrgStocks) ?? 0
        belowMinimumStock = container.dashboardCount(.belowMinimumStock) ?? 0
        negativeStocks = container.dashboardCount(.negativeStocks) ?? 0
        numberAbnormalities = container.dashboardCount(.numberAbnormalities) ?? 0
    }
}

private extension KeyedDecodingContainer {
    func dashboardCount(_ key: Key) -> Int? {
        if let value = try? decodeIfPresent(Int.self, forKey: key) {
            return value
        }
        if let value = try? decodeIfPresent(Double.self, forKey: key) {
            return Int(value)
        }
        if let text = try? decodeIfPresent(String.self, forKey: key) {
            return Int(text.trimmingCharacters(in: .whitespaces))
        }
        return nil
    }
}

extension Endpoint {
    static func mobileWarehouseDashboard(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/dashboard")
    }
}

extension APIClient {
    func fetchWarehouseDashboard(warehouseID: Int) async throws -> MobileWarehouseDashboard {
        try await send(.mobileWarehouseDashboard(warehouseID: warehouseID), as: DataEnvelope<MobileWarehouseDashboard>.self).value
    }
}
