import Foundation

enum MobileMyWorkKind: String, Sendable, Equatable {
    case deliveryNote = "delivery_note"
    case pickingSession = "picking_session"
    case customerReturn = "return"
    case palletReturn = "pallet_return"
}

struct MobileMyWorkRow: Sendable, Equatable, Identifiable {
    let kind: MobileMyWorkKind
    let id: Int
    var reference = ""
    var state = ""
    var stateLabel = ""
    var step = ""
    var stepLabel = ""
    var typeGroup: String?
    var items: Int?
    var customer: String?
    var trolleys: [String] = []
    var since: String?

    var listID: String { "\(kind.rawValue)-\(id)" }
}

struct MobileMyWorkCounts: Decodable, Sendable, Equatable {
    var total = 0
    var deliveryNotes = 0
    var pickingSessions = 0
    var returns = 0
    var palletReturns = 0

    private enum CodingKeys: String, CodingKey {
        case total, deliveryNotes, pickingSessions, returns, palletReturns
    }

    init() {}

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        total = container.lenientInt(.total) ?? 0
        deliveryNotes = container.lenientInt(.deliveryNotes) ?? 0
        pickingSessions = container.lenientInt(.pickingSessions) ?? 0
        returns = container.lenientInt(.returns) ?? 0
        palletReturns = container.lenientInt(.palletReturns) ?? 0
    }
}

struct MobileMyWorkPage: Decodable, Sendable, Equatable {
    let rows: [MobileMyWorkRow]
    var counts: MobileMyWorkCounts?

    private enum CodingKeys: String, CodingKey {
        case data, counts
    }

    private struct WireRow: Decodable {
        var kind: String?
        var id: Int?
        var reference: String?
        var state: String?
        var stateLabel: String?
        var step: String?
        var stepLabel: String?
        var typeGroup: String?
        var items: Int?
        var customer: String?
        var trolleys: [String?]?
        var since: String?

        private enum CodingKeys: String, CodingKey {
            case kind, id, reference, state, stateLabel, step, stepLabel, typeGroup, items, customer, trolleys, since
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            kind = try? container.decodeIfPresent(String.self, forKey: .kind)
            id = container.lenientInt(.id)
            reference = try? container.decodeIfPresent(String.self, forKey: .reference)
            state = try? container.decodeIfPresent(String.self, forKey: .state)
            stateLabel = try? container.decodeIfPresent(String.self, forKey: .stateLabel)
            step = try? container.decodeIfPresent(String.self, forKey: .step)
            stepLabel = try? container.decodeIfPresent(String.self, forKey: .stepLabel)
            typeGroup = try? container.decodeIfPresent(String.self, forKey: .typeGroup)
            items = container.lenientInt(.items)
            customer = try? container.decodeIfPresent(String.self, forKey: .customer)
            trolleys = try? container.decodeIfPresent([String?].self, forKey: .trolleys)
            since = try? container.decodeIfPresent(String.self, forKey: .since)
        }

        var row: MobileMyWorkRow? {
            guard let kind = kind.flatMap(MobileMyWorkKind.init(rawValue:)), let id else { return nil }
            return MobileMyWorkRow(
                kind: kind,
                id: id,
                reference: reference ?? "",
                state: state ?? "",
                stateLabel: stateLabel ?? "",
                step: step ?? "",
                stepLabel: stepLabel ?? "",
                typeGroup: typeGroup.trimmedOrNil,
                items: items,
                customer: customer.trimmedOrNil,
                trolleys: (trolleys ?? []).compactMap(\.trimmedOrNil),
                since: since.trimmedOrNil
            )
        }
    }

    private struct Skipped: Decodable {}

    init(rows: [MobileMyWorkRow], counts: MobileMyWorkCounts? = nil) {
        self.rows = rows
        self.counts = counts
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        var list = try container.nestedUnkeyedContainer(forKey: .data)
        var rows: [MobileMyWorkRow] = []
        while !list.isAtEnd {
            if let wire = try? list.decode(WireRow.self), let row = wire.row {
                rows.append(row)
            } else {
                _ = try? list.decode(Skipped.self)
            }
        }
        self.rows = rows
        counts = try? container.decodeIfPresent(MobileMyWorkCounts.self, forKey: .counts)
    }
}

struct MobileWorkBadges: Decodable, Sendable, Equatable {
    var myWork: Int?
    var goodsOutUnassigned: Int?
    var goodsInReceived: Int?
    var inboxUnread: Int?

    private enum CodingKeys: String, CodingKey {
        case myWork, goodsOutUnassigned, goodsInReceived, inboxUnread
    }

    init(myWork: Int? = nil, goodsOutUnassigned: Int? = nil, goodsInReceived: Int? = nil, inboxUnread: Int? = nil) {
        self.myWork = myWork
        self.goodsOutUnassigned = goodsOutUnassigned
        self.goodsInReceived = goodsInReceived
        self.inboxUnread = inboxUnread
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        myWork = container.lenientInt(.myWork)
        goodsOutUnassigned = container.lenientInt(.goodsOutUnassigned)
        goodsInReceived = container.lenientInt(.goodsInReceived)
        inboxUnread = container.lenientInt(.inboxUnread)
    }
}

private extension KeyedDecodingContainer {
    func lenientInt(_ key: Key) -> Int? {
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

private extension Optional where Wrapped == String {
    var trimmedOrNil: String? {
        guard let text = self?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty else { return nil }
        return text
    }
}

extension Endpoint {
    static func mobileMyWork(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/my-work")
    }

    static func mobileWorkBadges(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/badges")
    }
}

extension APIClient {
    func fetchMyWork(warehouseID: Int) async throws -> MobileMyWorkPage {
        try await send(.mobileMyWork(warehouseID: warehouseID), as: MobileMyWorkPage.self)
    }

    func fetchWorkBadges(warehouseID: Int) async throws -> MobileWorkBadges {
        try await send(.mobileWorkBadges(warehouseID: warehouseID), as: DataEnvelope<MobileWorkBadges>.self).value
    }
}
