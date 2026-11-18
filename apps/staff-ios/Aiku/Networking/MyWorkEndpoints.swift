import Foundation

enum MobileMyWorkKind: String, Decodable, Sendable {
    case deliveryNote = "delivery_note"
    case pickingSession = "picking_session"
    case customerReturn = "return"
    case palletReturn = "pallet_return"
}

struct MobileMyWorkRow: Decodable, Equatable, Sendable, Identifiable {
    let kind: MobileMyWorkKind
    let id: Int
    let reference: String
    var state: String = ""
    var stateLabel: String? = nil
    var step: String = ""
    var stepLabel: String? = nil
    var typeGroup: String? = nil
    var items: Int? = nil
    var customer: String? = nil
    var trolleys: [String] = []
    var since: String? = nil

    var rowID: String { "\(kind.rawValue).\(id)" }

    private enum CodingKeys: String, CodingKey {
        case kind, id, reference, state, stateLabel, step, stepLabel, typeGroup, items, customer, trolleys, since
    }

    init(kind: MobileMyWorkKind, id: Int, reference: String, step: String = "", stepLabel: String? = nil, items: Int? = nil, customer: String? = nil, trolleys: [String] = [], since: String? = nil) {
        self.kind = kind
        self.id = id
        self.reference = reference
        self.step = step
        self.stepLabel = stepLabel
        self.items = items
        self.customer = customer
        self.trolleys = trolleys
        self.since = since
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        kind = try container.decode(MobileMyWorkKind.self, forKey: .kind)
        id = try container.decode(Int.self, forKey: .id)
        reference = try container.decode(String.self, forKey: .reference)
        state = try container.decodeIfPresent(String.self, forKey: .state) ?? ""
        stateLabel = try container.decodeIfPresent(String.self, forKey: .stateLabel)
        step = try container.decodeIfPresent(String.self, forKey: .step) ?? ""
        stepLabel = try container.decodeIfPresent(String.self, forKey: .stepLabel)
        typeGroup = try container.decodeIfPresent(String.self, forKey: .typeGroup)
        items = try container.decodeIfPresent(Int.self, forKey: .items)
        customer = try container.decodeIfPresent(String.self, forKey: .customer)
        trolleys = try container.decodeIfPresent([String].self, forKey: .trolleys) ?? []
        since = try container.decodeIfPresent(String.self, forKey: .since)
    }
}

struct MobileMyWorkCounts: Decodable, Equatable, Sendable {
    var total = 0
    var deliveryNotes = 0
    var pickingSessions = 0
    var returns = 0
    var palletReturns = 0

    private enum CodingKeys: String, CodingKey {
        case total, deliveryNotes, pickingSessions, returns, palletReturns
    }

    init(total: Int = 0, deliveryNotes: Int = 0, pickingSessions: Int = 0, returns: Int = 0, palletReturns: Int = 0) {
        self.total = total
        self.deliveryNotes = deliveryNotes
        self.pickingSessions = pickingSessions
        self.returns = returns
        self.palletReturns = palletReturns
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        total = try container.decodeIfPresent(Int.self, forKey: .total) ?? 0
        deliveryNotes = try container.decodeIfPresent(Int.self, forKey: .deliveryNotes) ?? 0
        pickingSessions = try container.decodeIfPresent(Int.self, forKey: .pickingSessions) ?? 0
        returns = try container.decodeIfPresent(Int.self, forKey: .returns) ?? 0
        palletReturns = try container.decodeIfPresent(Int.self, forKey: .palletReturns) ?? 0
    }
}

struct MobileMyWorkPage: Decodable, Equatable, Sendable {
    let data: [MobileMyWorkRow]
    var counts: MobileMyWorkCounts? = nil
}

extension Endpoint {
    static func mobileMyWork(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/my-work")
    }
}

extension APIClient {
    func fetchMyWork(warehouseID: Int) async throws -> MobileMyWorkPage {
        try await send(.mobileMyWork(warehouseID: warehouseID), as: MobileMyWorkPage.self)
    }
}
