import Foundation

struct MobilePickingSessionPerson: Decodable, Sendable, Equatable {
    let id: Int
    let name: String?
}

struct MobilePickingSessionRow: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let reference: String
    let state: String
    var stateLabel: String?
    var picker: MobilePickingSessionPerson?
    var numberDeliveryNotes: Int = 0
    var numberItems: Int = 0
    var numberTrolleys: Int = 0
    var pickingPercentage: Double = 0
    var packingPercentage: Double = 0
    var startAt: String?
    var endAt: String?
    var createdAt: String?

    private enum CodingKeys: String, CodingKey {
        case id, reference, state, stateLabel, picker, numberDeliveryNotes, numberItems, numberTrolleys, pickingPercentage, packingPercentage
        case startAt, endAt, createdAt
    }

    init(
        id: Int,
        reference: String,
        state: String,
        stateLabel: String? = nil,
        picker: MobilePickingSessionPerson? = nil,
        numberDeliveryNotes: Int = 0,
        numberItems: Int = 0,
        numberTrolleys: Int = 0,
        pickingPercentage: Double = 0,
        packingPercentage: Double = 0,
        startAt: String? = nil,
        endAt: String? = nil,
        createdAt: String? = nil
    ) {
        self.id = id
        self.reference = reference
        self.state = state
        self.stateLabel = stateLabel
        self.picker = picker
        self.numberDeliveryNotes = numberDeliveryNotes
        self.numberItems = numberItems
        self.numberTrolleys = numberTrolleys
        self.pickingPercentage = pickingPercentage
        self.packingPercentage = packingPercentage
        self.startAt = startAt
        self.endAt = endAt
        self.createdAt = createdAt
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        reference = try container.decodeIfPresent(String.self, forKey: .reference) ?? ""
        state = try container.decodeIfPresent(String.self, forKey: .state) ?? ""
        stateLabel = try container.decodeIfPresent(String.self, forKey: .stateLabel)
        picker = try container.decodeIfPresent(MobilePickingSessionPerson.self, forKey: .picker)
        numberDeliveryNotes = try container.decodeIfPresent(Int.self, forKey: .numberDeliveryNotes) ?? 0
        numberItems = try container.decodeIfPresent(Int.self, forKey: .numberItems) ?? 0
        numberTrolleys = try container.decodeIfPresent(Int.self, forKey: .numberTrolleys) ?? 0
        pickingPercentage = try container.decodeIfPresent(Double.self, forKey: .pickingPercentage) ?? 0
        packingPercentage = try container.decodeIfPresent(Double.self, forKey: .packingPercentage) ?? 0
        startAt = try? container.decodeIfPresent(String.self, forKey: .startAt)
        endAt = try? container.decodeIfPresent(String.self, forKey: .endAt)
        createdAt = try? container.decodeIfPresent(String.self, forKey: .createdAt)
    }
}

struct MobileSessionPagination: Decodable, Sendable, Equatable {
    var currentPage: Int = 1
    var lastPage: Int = 1
    var perPage: Int = 25
    var total: Int = 0
}

struct MobilePickingSessionsPage: Decodable, Sendable, Equatable {
    let data: [MobilePickingSessionRow]
    var counts: [String: Int] = [:]
    var pagination: MobileSessionPagination = MobileSessionPagination()

    private enum CodingKeys: String, CodingKey {
        case data, counts, pagination
    }

    init(data: [MobilePickingSessionRow], counts: [String: Int] = [:], pagination: MobileSessionPagination = MobileSessionPagination()) {
        self.data = data
        self.counts = counts
        self.pagination = pagination
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        data = try container.decode([MobilePickingSessionRow].self, forKey: .data)
        counts = (try? container.decodeIfPresent([String: Int].self, forKey: .counts)) ?? [:]
        pagination = try container.decodeIfPresent(MobileSessionPagination.self, forKey: .pagination) ?? MobileSessionPagination(total: data.count)
    }
}

struct MobileSessionRef: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    var name: String?
    var reference: String?
}

struct MobilePickingSessionSettings: Decodable, Sendable, Equatable {
    var allowScanToPick = false
    var allowScanToPack = false
}

struct MobilePickingSessionAction: Decodable, Sendable, Equatable {
    let key: String
    var label: String?
    var disabledReason: String?

    var isDisabled: Bool { !(disabledReason ?? "").isEmpty }
}

struct MobileSessionDeliveryNote: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let reference: String
    let state: String
    var stateLabel: String?
    var numberItems: Int = 0
    var pickingPercentage: Double = 0
    var packingPercentage: Double = 0
    var hasWaitingItems = false
    var isForCollection = false

    private enum CodingKeys: String, CodingKey {
        case id, reference, state, stateLabel, numberItems, pickingPercentage, packingPercentage, hasWaitingItems, isForCollection
    }

    init(
        id: Int,
        reference: String,
        state: String,
        stateLabel: String? = nil,
        numberItems: Int = 0,
        pickingPercentage: Double = 0,
        packingPercentage: Double = 0,
        hasWaitingItems: Bool = false,
        isForCollection: Bool = false
    ) {
        self.id = id
        self.reference = reference
        self.state = state
        self.stateLabel = stateLabel
        self.numberItems = numberItems
        self.pickingPercentage = pickingPercentage
        self.packingPercentage = packingPercentage
        self.hasWaitingItems = hasWaitingItems
        self.isForCollection = isForCollection
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        reference = try container.decodeIfPresent(String.self, forKey: .reference) ?? ""
        state = try container.decodeIfPresent(String.self, forKey: .state) ?? ""
        stateLabel = try container.decodeIfPresent(String.self, forKey: .stateLabel)
        numberItems = try container.decodeIfPresent(Int.self, forKey: .numberItems) ?? 0
        pickingPercentage = try container.decodeIfPresent(Double.self, forKey: .pickingPercentage) ?? 0
        packingPercentage = try container.decodeIfPresent(Double.self, forKey: .packingPercentage) ?? 0
        hasWaitingItems = try container.decodeIfPresent(Bool.self, forKey: .hasWaitingItems) ?? false
        isForCollection = try container.decodeIfPresent(Bool.self, forKey: .isForCollection) ?? false
    }
}

struct MobilePickListLocation: Decodable, Sendable, Equatable {
    let id: Int
    var code: String?
}

struct MobilePickListOrgStock: Decodable, Sendable, Equatable {
    let id: Int
    var code: String?
    var name: String?
    var barcode: String?
    var imageUrl: String?
}

struct MobilePickListLine: Decodable, Sendable, Equatable, Identifiable {
    let deliveryNoteItemId: Int
    let deliveryNoteId: Int
    var deliveryNoteReference: String?
    var quantityRequired: Double = 0
    var quantityPicked: Double = 0
    var quantityToPick: Double = 0

    var id: Int { deliveryNoteItemId }
}

struct MobilePickListEntry: Decodable, Sendable, Equatable, Identifiable {
    let key: String
    var location: MobilePickListLocation?
    var orgStock: MobilePickListOrgStock?
    var quantityRequired: Double = 0
    var quantityPicked: Double = 0
    var quantityToPick: Double = 0
    var quantityToPickLabel: String?
    var lines: [MobilePickListLine] = []

    var id: String { key }

    var isDone: Bool { quantityToPick <= 0 }
}

struct MobilePickingSession: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let reference: String
    let state: String
    var stateLabel: String?
    var picker: MobilePickingSessionPerson?
    var numberDeliveryNotes: Int = 0
    var numberItems: Int = 0
    var numberTrolleys: Int = 0
    var pickingPercentage: Double = 0
    var packingPercentage: Double = 0
    var canWork = false
    var trolleys: [MobileSessionRef] = []
    var canChangeTrolleys = false
    var settings = MobilePickingSessionSettings()
    var availableActions: [MobilePickingSessionAction] = []
    var deliveryNotes: [MobileSessionDeliveryNote] = []
    var pickList: [MobilePickListEntry] = []

    private enum CodingKeys: String, CodingKey {
        case id, reference, state, stateLabel, picker, numberDeliveryNotes, numberItems, numberTrolleys, pickingPercentage, packingPercentage
        case canWork, trolleys, canChangeTrolleys, settings, availableActions, deliveryNotes, pickList
    }

    init(
        id: Int,
        reference: String,
        state: String,
        stateLabel: String? = nil,
        picker: MobilePickingSessionPerson? = nil,
        numberDeliveryNotes: Int = 0,
        numberItems: Int = 0,
        numberTrolleys: Int = 0,
        pickingPercentage: Double = 0,
        packingPercentage: Double = 0,
        canWork: Bool = false,
        trolleys: [MobileSessionRef] = [],
        canChangeTrolleys: Bool = false,
        availableActions: [MobilePickingSessionAction] = [],
        deliveryNotes: [MobileSessionDeliveryNote] = [],
        pickList: [MobilePickListEntry] = []
    ) {
        self.id = id
        self.reference = reference
        self.state = state
        self.stateLabel = stateLabel
        self.picker = picker
        self.numberDeliveryNotes = numberDeliveryNotes
        self.numberItems = numberItems
        self.numberTrolleys = numberTrolleys
        self.pickingPercentage = pickingPercentage
        self.packingPercentage = packingPercentage
        self.canWork = canWork
        self.trolleys = trolleys
        self.canChangeTrolleys = canChangeTrolleys
        self.availableActions = availableActions
        self.deliveryNotes = deliveryNotes
        self.pickList = pickList
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        reference = try container.decodeIfPresent(String.self, forKey: .reference) ?? ""
        state = try container.decodeIfPresent(String.self, forKey: .state) ?? ""
        stateLabel = try container.decodeIfPresent(String.self, forKey: .stateLabel)
        picker = try container.decodeIfPresent(MobilePickingSessionPerson.self, forKey: .picker)
        numberDeliveryNotes = try container.decodeIfPresent(Int.self, forKey: .numberDeliveryNotes) ?? 0
        numberItems = try container.decodeIfPresent(Int.self, forKey: .numberItems) ?? 0
        numberTrolleys = try container.decodeIfPresent(Int.self, forKey: .numberTrolleys) ?? 0
        pickingPercentage = try container.decodeIfPresent(Double.self, forKey: .pickingPercentage) ?? 0
        packingPercentage = try container.decodeIfPresent(Double.self, forKey: .packingPercentage) ?? 0
        canWork = try container.decodeIfPresent(Bool.self, forKey: .canWork) ?? false
        trolleys = try container.decodeIfPresent([MobileSessionRef].self, forKey: .trolleys) ?? []
        canChangeTrolleys = try container.decodeIfPresent(Bool.self, forKey: .canChangeTrolleys) ?? false
        settings = try container.decodeIfPresent(MobilePickingSessionSettings.self, forKey: .settings) ?? MobilePickingSessionSettings()
        availableActions = try container.decodeIfPresent([MobilePickingSessionAction].self, forKey: .availableActions) ?? []
        deliveryNotes = try container.decodeIfPresent([MobileSessionDeliveryNote].self, forKey: .deliveryNotes) ?? []
        pickList = try container.decodeIfPresent([MobilePickListEntry].self, forKey: .pickList) ?? []
    }

    func action(_ key: String) -> MobilePickingSessionAction? {
        availableActions.first { $0.key == key }
    }
}

struct MobileSessionScanItem: Decodable, Sendable, Equatable {
    let id: Int
    var code: String?
    var name: String?
    var quantityRequired: Double?
    var quantityPicked: Double?
    var quantityToPick: Double?
    var quantityToPickLabel: String?
    var locationCode: String?
    var locationOrgStockId: Int?
    var quantityPacked: Double?
    var quantityToPack: Double?
    var quantityToPackLabel: String?
}

struct MobileSessionScanDeliveryNote: Decodable, Sendable, Equatable {
    let id: Int
    var reference: String?
    var state: String?
}

struct MobilePickingSessionResult: Decodable, Sendable, Equatable {
    var status: String = ""
    var message: String?
    var item: MobileSessionScanItem?
    var deliveryNote: MobileSessionScanDeliveryNote?
}

struct MobilePickingSessionOutcome: Decodable, Sendable, Equatable {
    let session: MobilePickingSession
    var result = MobilePickingSessionResult()

    private enum CodingKeys: String, CodingKey {
        case data, result
    }

    init(session: MobilePickingSession, result: MobilePickingSessionResult = MobilePickingSessionResult()) {
        self.session = session
        self.result = result
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        session = try container.decode(MobilePickingSession.self, forKey: .data)
        result = try container.decodeIfPresent(MobilePickingSessionResult.self, forKey: .result) ?? MobilePickingSessionResult()
    }
}

struct MobileSessionScanBody: Encodable, Sendable, Equatable {
    let barcode: String
    var quantity: Double?
    var deliveryNoteItemId: Int?
    var locationOrgStockId: Int?
}

enum PickingSessionWrite: Equatable, Sendable {
    case startPicking
    case backToPicking
    case attachTrolley(Int)
    case detachTrolley(Int)
    case addDeliveryNotes([Int])
    case removeDeliveryNotes([Int])
}

struct MobileSessionTrolley: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let name: String
    var currentDeliveryNote: MobileSessionRef?
    var currentPickingSession: MobileSessionRef?
}

struct MobileSessionPickedBay: Decodable, Sendable, Equatable, Identifiable {
    struct DeliveryNote: Decodable, Sendable, Equatable, Identifiable {
        let id: Int
        let reference: String
        var state: String?
        var stateLabel: String?
    }

    let id: Int
    let code: String
    var numberDeliveryNotes: Int = 0
    var numberWaiting: Int = 0
    var deliveryNotes: [DeliveryNote] = []
}

struct MobileSessionCandidateNote: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let reference: String
    var stateLabel: String?
    var customerName: String?
    var numberItems: Int?
    var canAddToPickingSession: Bool?
}

struct MobileSessionCandidatesPage: Decodable, Sendable, Equatable {
    let deliveryNotes: [MobileSessionCandidateNote]
    var canWorkInWarehouse: Bool?
}

private struct DeliveryNoteIDsBody: Encodable, Sendable {
    let deliveryNoteIds: [Int]
}

private struct TrolleyBody: Encodable, Sendable {
    let trolleyId: Int
}

private struct EmptyBody: Encodable, Sendable {}

enum PickingSessionSort: String, CaseIterable, Hashable, Sendable {
    case reference
    case picker = "user_name"
    case started = "-start_at"
    case finished = "-end_at"
}

struct MobilePickingSessionsQuery: Hashable, Sendable {
    var state: String?
    var search = ""
    var sort: PickingSessionSort?
    var page = 1

    var queryItems: [URLQueryItem] {
        var items: [URLQueryItem] = []
        if let state { items.append(URLQueryItem(name: "state", value: state)) }
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        if let sort { items.append(URLQueryItem(name: "sort", value: sort.rawValue)) }
        items.append(URLQueryItem(name: "page", value: String(page)))
        return items
    }
}

extension Endpoint {
    static func pickingSessions(warehouseID: Int, query: MobilePickingSessionsQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/picking-sessions", queryItems: query.queryItems)
    }

    static func pickingSession(id: Int) -> Endpoint {
        Endpoint(path: "mobile/picking-sessions/\(id)")
    }

    static func createPickingSession(warehouseID: Int, deliveryNoteIDs: [Int]) -> Endpoint {
        .jsonBody("mobile/warehouses/\(warehouseID)/picking-sessions", method: .post, DeliveryNoteIDsBody(deliveryNoteIds: deliveryNoteIDs))
    }

    static func pickingSessionWrite(id: Int, _ write: PickingSessionWrite) -> Endpoint {
        let base = "mobile/picking-sessions/\(id)"
        switch write {
        case .startPicking:
            return .jsonBody("\(base)/start-picking", method: .post, EmptyBody())
        case .backToPicking:
            return .jsonBody("\(base)/back-to-picking", method: .post, EmptyBody())
        case .attachTrolley(let trolleyID):
            return .jsonBody("\(base)/trolleys", method: .post, TrolleyBody(trolleyId: trolleyID))
        case .detachTrolley(let trolleyID):
            return Endpoint(path: "\(base)/trolleys/\(trolleyID)", method: .delete)
        case .addDeliveryNotes(let ids):
            return .jsonBody("\(base)/delivery-notes", method: .post, DeliveryNoteIDsBody(deliveryNoteIds: ids))
        case .removeDeliveryNotes(let ids):
            return .jsonBody("\(base)/delivery-notes/remove", method: .post, DeliveryNoteIDsBody(deliveryNoteIds: ids))
        }
    }

    static func pickingSessionScanPick(id: Int, _ body: MobileSessionScanBody) -> Endpoint {
        .jsonBody("mobile/picking-sessions/\(id)/scan-pick", method: .post, body)
    }

    static func pickingSessionScanPack(id: Int, _ body: MobileSessionScanBody) -> Endpoint {
        .jsonBody("mobile/picking-sessions/\(id)/scan-pack", method: .post, body)
    }

    static func warehouseTrolleys(warehouseID: Int, availableOnly: Bool) -> Endpoint {
        Endpoint(
            path: "mobile/warehouses/\(warehouseID)/trolleys",
            queryItems: availableOnly ? [URLQueryItem(name: "available", value: "1")] : []
        )
    }

    static func warehousePickedBays(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/picked-bays")
    }

    static func pickingSessionCandidates(warehouseID: Int, bucket: String) -> Endpoint {
        Endpoint(
            path: "mobile/warehouses/\(warehouseID)/delivery-notes",
            queryItems: [URLQueryItem(name: "bucket", value: bucket), URLQueryItem(name: "per_page", value: "100")]
        )
    }
}

extension APIClient {
    func pickingSessions(warehouseID: Int, query: MobilePickingSessionsQuery) async throws -> MobilePickingSessionsPage {
        try await send(.pickingSessions(warehouseID: warehouseID, query: query), as: MobilePickingSessionsPage.self)
    }

    func pickingSession(id: Int) async throws -> MobilePickingSession {
        try await send(.pickingSession(id: id), as: DataEnvelope<MobilePickingSession>.self).value
    }

    func createPickingSession(warehouseID: Int, deliveryNoteIDs: [Int]) async throws -> MobilePickingSession {
        try await send(.createPickingSession(warehouseID: warehouseID, deliveryNoteIDs: deliveryNoteIDs), as: DataEnvelope<MobilePickingSession>.self).value
    }

    func writePickingSession(id: Int, _ write: PickingSessionWrite) async throws -> MobilePickingSessionOutcome {
        try await send(.pickingSessionWrite(id: id, write), as: MobilePickingSessionOutcome.self)
    }

    func scanPickInPickingSession(id: Int, _ body: MobileSessionScanBody) async throws -> MobilePickingSessionOutcome {
        try await send(.pickingSessionScanPick(id: id, body), as: MobilePickingSessionOutcome.self)
    }

    func scanPackInPickingSession(id: Int, _ body: MobileSessionScanBody) async throws -> MobilePickingSessionOutcome {
        try await send(.pickingSessionScanPack(id: id, body), as: MobilePickingSessionOutcome.self)
    }

    func warehouseTrolleys(warehouseID: Int, availableOnly: Bool) async throws -> [MobileSessionTrolley] {
        try await send(.warehouseTrolleys(warehouseID: warehouseID, availableOnly: availableOnly), as: DataEnvelope<[MobileSessionTrolley]>.self).value
    }

    func warehousePickedBays(warehouseID: Int) async throws -> [MobileSessionPickedBay] {
        try await send(.warehousePickedBays(warehouseID: warehouseID), as: DataEnvelope<[MobileSessionPickedBay]>.self).value
    }

    func pickingSessionCandidates(warehouseID: Int) async throws -> [MobileSessionCandidateNote] {
        var candidates: [MobileSessionCandidateNote] = []
        for bucket in ["unassigned", "queued"] {
            let page = try await send(.pickingSessionCandidates(warehouseID: warehouseID, bucket: bucket), as: DataEnvelope<MobileSessionCandidatesPage>.self).value
            candidates += page.deliveryNotes.filter { $0.canAddToPickingSession == true && !candidates.map(\.id).contains($0.id) }
        }
        return candidates
    }
}
