import Foundation

struct GoodsOutLooseText: Decodable, Equatable, Sendable {
    let text: String?

    init(_ text: String?) {
        self.text = text
    }

    init(from decoder: Decoder) throws {
        text = Self.text(from: try GoodsOutAnyJSON(from: decoder))
    }

    private static let displayKeys = ["name", "code", "label", "reference", "slug"]

    private static func text(from value: GoodsOutAnyJSON) -> String? {
        switch value {
        case .null: return nil
        case .bool(let flag): return flag ? "true" : "false"
        case .number(let number): return number.rounded() == number ? String(Int(number)) : String(number)
        case .string(let string): return string.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? nil : string
        case .array(let items):
            let joined = items.compactMap(text(from:)).joined(separator: ", ")
            return joined.isEmpty ? nil : joined
        case .object(let object):
            return displayKeys.lazy.compactMap { object[$0].flatMap(text(from:)) }.first
        }
    }
}

private indirect enum GoodsOutAnyJSON: Decodable {
    case null
    case bool(Bool)
    case number(Double)
    case string(String)
    case array([GoodsOutAnyJSON])
    case object([String: GoodsOutAnyJSON])

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if container.decodeNil() {
            self = .null
        } else if let flag = try? container.decode(Bool.self) {
            self = .bool(flag)
        } else if let number = try? container.decode(Double.self) {
            self = .number(number)
        } else if let string = try? container.decode(String.self) {
            self = .string(string)
        } else if let array = try? container.decode([GoodsOutAnyJSON].self) {
            self = .array(array)
        } else {
            self = .object(try container.decode([String: GoodsOutAnyJSON].self))
        }
    }
}

enum GoodsOutJSONValue: Encodable, Equatable, Sendable {
    case null
    case bool(Bool)
    case int(Int)
    case double(Double)
    case string(String)
    case array([GoodsOutJSONValue])
    case object([String: GoodsOutJSONValue])

    static func optional(_ value: Int?) -> GoodsOutJSONValue { value.map(GoodsOutJSONValue.int) ?? .null }

    static func optional(_ value: String?) -> GoodsOutJSONValue {
        guard let trimmed = value?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return .null }
        return .string(trimmed)
    }

    static func quantity(_ value: Double) -> GoodsOutJSONValue {
        value.rounded() == value && abs(value) < Double(Int.max) ? .int(Int(value)) : .double(value)
    }

    static func quantity(_ value: Double?) -> GoodsOutJSONValue { value.map(quantity) ?? .null }

    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()
        switch self {
        case .null: try container.encodeNil()
        case .bool(let value): try container.encode(value)
        case .int(let value): try container.encode(value)
        case .double(let value): try container.encode(value)
        case .string(let value): try container.encode(value)
        case .array(let value): try container.encode(value)
        case .object(let value): try container.encode(value)
        }
    }
}

struct GoodsOutIDName: Decodable, Equatable, Sendable {
    let id: Int
    let name: String?
}

struct GoodsOutIDReference: Decodable, Equatable, Sendable {
    let id: Int
    let reference: String?
}

struct GoodsOutShopDTO: Decodable, Equatable, Sendable {
    let id: Int?
    let name: String?
    let type: String?
}

struct GoodsOutPaginationDTO: Decodable, Equatable, Sendable {
    let currentPage: Int?
    let lastPage: Int?
    let perPage: Int?
    let total: Int?
}

struct GoodsOutDeliveryNoteRowDTO: Decodable, Equatable, Sendable {
    let id: Int
    let slug: String?
    let reference: String
    let type: String?
    let state: String
    let stateLabel: String?
    let date: String?
    let lastModifiedAt: String?
    let customerName: String?
    let isCustomerVip: Bool?
    let shop: GoodsOutShopDTO?
    let channel: String?
    let numberItems: Int?
    let estimatedWeightKg: Double?
    let parcels: Int?
    let isPremiumDispatch: Bool?
    let isPriority: Bool?
    let isCollection: Bool?
    let isExport: Bool?
    let hasExtraPacking: Bool?
    let picker: GoodsOutIDName?
    let packer: GoodsOutIDName?
    let trolleys: [GoodsOutIDName]?
    let pickedBays: [GoodsOutIDName]?
    let waitingWarehouseCount: Int?
    let waitingCrmCount: Int?
    let numberOfDaysInWarehouse: Double?
    let canAddToPickingSession: Bool?
}

struct GoodsOutDeliveryNotesPageDTO: Decodable, Equatable, Sendable {
    struct Payload: Decodable, Equatable, Sendable {
        let deliveryNotes: [GoodsOutDeliveryNoteRowDTO]
        let canWorkInWarehouse: Bool?
    }

    let data: Payload
    let pagination: GoodsOutPaginationDTO?
}

struct GoodsOutCountsDTO: Decodable, Equatable, Sendable {
    struct Channel: Decodable, Equatable, Sendable {
        let channel: String
        let label: String?
        let buckets: [String: Int]
    }

    let buckets: [String: Int]
    let channels: [Channel]
}

struct GoodsOutNoteActionDTO: Decodable, Equatable, Sendable {
    let key: String
    let label: String?
    let disabledReason: String?
}

struct GoodsOutPickingSettingsDTO: Decodable, Equatable, Sendable {
    let allowScanToPick: Bool?
    let allowWaiting: Bool?
    let allowPickerSetNotPicked: Bool?
    let allowPickerChooseLocation: Bool?
    let allowScanToPack: Bool?
}

struct GoodsOutCustomerDTO: Decodable, Equatable, Sendable {
    let name: String?
    let reference: String?
    let contactName: String?
    let companyName: String?
    let email: String?
    let phone: String?
    let isVip: Bool?
}

struct GoodsOutAddressFieldsDTO: Decodable, Equatable, Sendable {
    let contactName: String?
    let companyName: String?
    let email: String?
    let phone: String?
    let addressLine1: String?
    let addressLine2: String?
    let sortingCode: String?
    let postalCode: String?
    let locality: String?
    let dependentLocality: String?
    let administrativeArea: String?
    let countryCode: String?
}

struct GoodsOutDeliveryAddressDTO: Decodable, Equatable, Sendable {
    let lines: [String]?
    let countryCode: String?
    let fields: GoodsOutAddressFieldsDTO?
}

struct GoodsOutSummaryDTO: Decodable, Equatable, Sendable {
    let numberItems: Double?
    let numberSkos: Double?
    let numberUnits: Double?
    let estimatedWeightKg: Double?
    let estimatedPickingMinutes: Double?
    let estimatedPackingMinutes: Double?
}

struct GoodsOutParcelDTO: Decodable, Equatable, Sendable {
    let weightKg: Double?
    let dimensionsCm: [Double?]?
    let boxes: Int?
}

struct GoodsOutNoteTextDTO: Decodable, Equatable, Sendable {
    let text: String?
    let editable: Bool?
}

struct GoodsOutNotesDTO: Decodable, Equatable, Sendable {
    let shipping: GoodsOutNoteTextDTO?
    let customer: GoodsOutNoteTextDTO?
    let warehouse: GoodsOutNoteTextDTO?
}

struct GoodsOutTimelineEntryDTO: Decodable, Equatable, Sendable {
    let state: String
    let label: String?
    let at: String?
}

struct GoodsOutShipmentLabelDTO: Decodable, Equatable, Sendable {
    let kind: String?
    let url: String?
}

struct GoodsOutShipmentDTO: Decodable, Equatable, Sendable {
    let id: Int
    let shipper: String?
    let shipperCode: String?
    let tracking: String?
    let trackings: [String?]?
    let trackingUrls: [String?]?
    let isPrintable: Bool?
    let label: GoodsOutShipmentLabelDTO?
    let canDelete: Bool?

    var trackingNumbers: [String] {
        let numbers = (trackings ?? []).compactMap { $0 }.filter { !$0.isEmpty }
        if !numbers.isEmpty { return numbers }
        return [tracking].compactMap { $0 }.filter { !$0.isEmpty }
    }
}

struct GoodsOutShippingDTO: Decodable, Equatable, Sendable {
    struct External: Decodable, Equatable, Sendable {
        let engineLabel: String?
    }

    let canAdd: Bool?
    let canPrintLabels: Bool?
    let lockedShipperId: Int?
    let preferredShipperId: Int?
    let lockedBy: String?
    let canOverrideLock: Bool?
    let trackingChannel: String?
    let unconfirmedShippingOrder: Bool?
    let external: External?
}

struct GoodsOutDocumentsDTO: Decodable, Equatable, Sendable {
    let deliveryNotePdfUrl: String?
    let giftMessagePdfUrl: String?
}

struct GoodsOutConsumableDTO: Decodable, Equatable, Sendable {
    let code: String?
    let quantity: Double?
}

struct GoodsOutPackagingDTO: Decodable, Equatable, Sendable {
    let current: GoodsOutIDName?
}

struct GoodsOutInsertLeafletDTO: Decodable, Equatable, Sendable {
    let id: Int
    let name: String?
    let type: String?
    let copies: Int?
    let state: String?
    let stateLabel: String?
    let canOpenPdf: Bool?
}

struct GoodsOutInsertsDTO: Decodable, Equatable, Sendable {
    let leaflets: [GoodsOutInsertLeafletDTO]?
    let total: Int?
    let printed: Int?
    let allPrinted: Bool?
    let label: String?
    let canPrintAll: Bool?
}

struct GoodsOutBoxPackingListDTO: Decodable, Equatable, Sendable {
    let required: Bool?
    let numberBoxes: Int?
    let missingMessage: String?
    let pdfUrl: String?
}

struct GoodsOutReturnNoteDTO: Decodable, Equatable, Sendable {
    let id: Int
    let reference: String?
    let stateLabel: String?
}

struct GoodsOutLineCountsDTO: Decodable, Equatable, Sendable {
    let all: Int?
    let todo: Int?
    let done: Int?
}

struct GoodsOutIncompleteSetPartDTO: Decodable, Equatable, Sendable {
    let code: String?
    let name: String?
    let quantity: Double?
    let quantityLabel: String?
    let locations: [String]?
}

struct GoodsOutLineOrgStockDTO: Decodable, Equatable, Sendable {
    let id: Int?
    let code: String?
    let name: String?
    let barcode: String?
    let packedIn: Double?
    let imageUrl: String?
}

struct GoodsOutLineLocationDTO: Decodable, Equatable, Sendable {
    let locationOrgStockId: Int?
    let locationId: Int?
    let code: String?
    let stock: Double?
    let stockLabel: String?
    let type: String?
    let quantityPicked: Double?
    let pickingId: Int?
}

struct GoodsOutLinePickingDTO: Decodable, Equatable, Sendable {
    let id: Int
    let type: String?
    let locationOrgStockId: Int?
    let locationCode: String?
    let quantity: Double?
    let quantityLabel: String?
    let notPickedReason: String?
    let notPickedReasonLabel: String?

    var isNotPicked: Bool { type == "not-pick" }
}

struct GoodsOutLineBoxDTO: Decodable, Equatable, Sendable {
    let box: Int
    let quantity: Double?
}

struct GoodsOutLineDTO: Decodable, Equatable, Sendable {
    let id: Int
    let state: String?
    let stateLabel: String?
    let orgStock: GoodsOutLineOrgStockDTO?
    let quantityRequired: Double?
    let quantityRequiredLabel: String?
    let quantityPicked: Double?
    let quantityPickedLabel: String?
    let quantityToPick: Double?
    let quantityToPickLabel: String?
    let quantityNotPicked: Double?
    let quantityPacked: Double?
    let quantityPackedLabel: String?
    let quantityToPack: Double?
    let quantityToPackLabel: String?
    let quantityWaitingWarehouse: Double?
    let quantityWaitingCrm: Double?
    let isHandled: Bool?
    let isPicked: Bool?
    let isDonePacking: Bool?
    let isPacked: Bool?
    let locations: [GoodsOutLineLocationDTO]?
    let pickings: [GoodsOutLinePickingDTO]?
    let boxes: [GoodsOutLineBoxDTO]?
    let warehouseArea: GoodsOutLooseText?
    let noteToPickers: GoodsOutLooseText?
    let noteToPackers: GoodsOutLooseText?
    let unNumbers: GoodsOutLooseText?
    let replacementReason: GoodsOutLooseText?
    let indivisibleSet: Bool?
}

struct GoodsOutDeliveryNoteDTO: Decodable, Equatable, Sendable {
    struct Platform: Decodable, Equatable, Sendable {
        let name: String?
    }

    struct Order: Decodable, Equatable, Sendable {
        let id: Int?
        let reference: String?
    }

    let id: Int
    let slug: String?
    let reference: String
    let type: String?
    let state: String
    let stateLabel: String?
    let warehouseId: Int?
    let shop: GoodsOutShopDTO?
    let channel: String?
    let platform: Platform?
    let order: Order?
    let customer: GoodsOutCustomerDTO?
    let deliveryAddress: GoodsOutDeliveryAddressDTO?
    let isCollection: Bool?
    let isShippingByExternal: Bool?
    let isPremiumDispatch: Bool?
    let isCashOnDelivery: Bool?
    let hasExtraPacking: Bool?
    let hasGiftMessage: Bool?
    let isExport: Bool?
    let summary: GoodsOutSummaryDTO?
    let picker: GoodsOutIDName?
    let packer: GoodsOutIDName?
    let trolleys: [GoodsOutIDName]?
    let pickedBays: [GoodsOutIDName]?
    let parcels: [GoodsOutParcelDTO]?
    let parcelsEditable: Bool?
    let consumables: [GoodsOutConsumableDTO]?
    let packaging: GoodsOutPackagingDTO?
    let inserts: GoodsOutInsertsDTO?
    let boxPackingList: GoodsOutBoxPackingListDTO?
    let shipping: GoodsOutShippingDTO?
    let notes: GoodsOutNotesDTO?
    let timeline: [GoodsOutTimelineEntryDTO]?
    let shipments: [GoodsOutShipmentDTO]?
    let documents: GoodsOutDocumentsDTO?
    let waitingWarehouseCount: Int?
    let waitingCrmCount: Int?
    let isEditable: Bool?
    let canHandle: Bool?
    let isTakenOver: Bool?
    let allowActions: Bool?
    let canEditContact: Bool?
    let availableActions: [GoodsOutNoteActionDTO]?
    let settings: GoodsOutPickingSettingsDTO?
    let incompleteSets: [GoodsOutIncompleteSetPartDTO]?
    let readOnlyNotice: String?
    let warning: String?
    let returnDeliveryNote: GoodsOutReturnNoteDTO?
    let lineCounts: GoodsOutLineCountsDTO?
    let lines: [GoodsOutLineDTO]?

    func action(_ key: String) -> GoodsOutNoteActionDTO? {
        availableActions?.first { $0.key == key }
    }
}

struct GoodsOutActionItemDTO: Decodable, Equatable, Sendable {
    let id: Int
    let code: String?
    let name: String?
    let quantityRequired: Double?
    let quantityPicked: Double?
    let quantityToPick: Double?
    let quantityToPickLabel: String?
    let locationCode: String?
    let locationOrgStockId: Int?
    let quantityPacked: Double?
    let quantityToPack: Double?
    let quantityToPackLabel: String?
}

struct GoodsOutActionResponseDTO: Decodable, Equatable, Sendable {
    struct Result: Decodable, Equatable, Sendable {
        let status: String?
        let message: String?
        let warning: String?
        let item: GoodsOutActionItemDTO?
    }

    let data: GoodsOutDeliveryNoteDTO
    let result: Result?

    var status: String { result?.status ?? "" }
    var isNothingChanged: Bool { status == "nothing_changed" }
}

struct GoodsOutTrolleyDTO: Decodable, Equatable, Sendable {
    let id: Int
    let name: String
    let currentDeliveryNote: GoodsOutIDReference?
    let currentPickingSession: GoodsOutIDReference?
}

struct GoodsOutPickedBayDTO: Decodable, Equatable, Sendable {
    struct Note: Decodable, Equatable, Sendable {
        let id: Int
        let reference: String?
        let state: String?
        let stateLabel: String?
    }

    let id: Int
    let code: String
    let numberDeliveryNotes: Int?
    let numberWaiting: Int?
    let deliveryNotes: [Note]?
}

struct GoodsOutPickerDTO: Decodable, Equatable, Sendable {
    let userId: Int
    let name: String?
    let alias: String?
}

struct GoodsOutDispatchPersonDTO: Decodable, Equatable, Sendable {
    let user: GoodsOutIDName
    let deliveryNotes: [GoodsOutIDReference]?
    let trolleys: [GoodsOutIDName]?
    let pickingSessions: [GoodsOutIDReference]?
}

enum GoodsOutDispatchRole: String, Sendable {
    case pickers
    case packers
}

struct GoodsOutListRequest: Equatable, Sendable {
    var bucket = "unassigned"
    var channel = "all"
    var search: String?
    var from: String?
    var to: String?
    var picker: String?
    var sort: String?
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [URLQueryItem(name: "bucket", value: bucket), URLQueryItem(name: "channel", value: channel)]
        if let search = search?.trimmingCharacters(in: .whitespacesAndNewlines), !search.isEmpty {
            items.append(URLQueryItem(name: "search", value: search))
        }
        if let from { items.append(URLQueryItem(name: "from", value: from)) }
        if let to { items.append(URLQueryItem(name: "to", value: to)) }
        if let picker { items.append(URLQueryItem(name: "picker", value: picker)) }
        if let sort { items.append(URLQueryItem(name: "sort", value: sort)) }
        items.append(URLQueryItem(name: "page", value: String(page)))
        items.append(URLQueryItem(name: "per_page", value: String(perPage)))
        return items
    }
}

enum GoodsOutNotPickedReasonKey: String, Sendable {
    case outOfStock = "out-of-stock"
    case cancelledByCustomer = "cancelled-by-customer"
    case cancelledByWarehouse = "cancelled-by-warehouse"
    case notApplicable = "not-applicable"
}

enum GoodsOutWaitingKind: String, Sendable {
    case warehouse
    case crm
}

struct GoodsOutNoteAction: Equatable, Sendable {
    let method: HTTPMethod
    let path: String
    var body: [String: GoodsOutJSONValue]?

    static func post(_ path: String, _ body: [String: GoodsOutJSONValue] = [:]) -> GoodsOutNoteAction {
        GoodsOutNoteAction(method: .post, path: path, body: body)
    }

    static func delete(_ path: String) -> GoodsOutNoteAction {
        GoodsOutNoteAction(method: .delete, path: path, body: nil)
    }

    static func line(_ lineID: Int, _ action: String) -> String { "lines/\(lineID)/\(action)" }

    static let platformTimeout: TimeInterval = 120
    private static let platformPaths: Set<String> = ["set-as-packed", "external-shipment", "finalise", "finalise-and-dispatch", "dispatch"]

    var waitsForPlatform: Bool { method == .post && Self.platformPaths.contains(path) }

    static func startPicking(trolleyID: Int?) -> GoodsOutNoteAction { .post("start-picking", ["trolley_id": .optional(trolleyID)]) }
    static let takeOver = GoodsOutNoteAction.post("take-over")
    static func setAsPicked(pickedBayID: Int?) -> GoodsOutNoteAction { .post("set-as-picked", ["picked_bay_id": .optional(pickedBayID)]) }
    static let putBackIncompleteSets = GoodsOutNoteAction.post("put-back-incomplete-sets")
    static let autoFinishWaiting = GoodsOutNoteAction.post("auto-finish-waiting")
    static let backToPicking = GoodsOutNoteAction.post("back-to-picking")
    static let undoSetAsPicked = GoodsOutNoteAction.post("undo-set-as-picked")
    static func changeTrolley(trolleyID: Int?) -> GoodsOutNoteAction { .post("trolley", ["trolley_id": .optional(trolleyID)]) }
    static func changePickedBay(pickedBayID: Int?) -> GoodsOutNoteAction { .post("picked-bay", ["picked_bay_id": .optional(pickedBayID)]) }
    static func setPicker(userID: Int?) -> GoodsOutNoteAction { .post("picker", ["user_id": .optional(userID)]) }

    static func pick(line: Int, locationOrgStockID: Int, quantity: Double) -> GoodsOutNoteAction {
        .post(Self.line(line, "pick"), ["location_org_stock_id": .int(locationOrgStockID), "quantity": .quantity(quantity)])
    }

    static func pickAll(line: Int, locationOrgStockID: Int?) -> GoodsOutNoteAction {
        .post(Self.line(line, "pick-all"), ["location_org_stock_id": .optional(locationOrgStockID)])
    }

    static func notPicked(line: Int, quantity: Double?, reason: GoodsOutNotPickedReasonKey, note: String?) -> GoodsOutNoteAction {
        .post(Self.line(line, "not-picked"), ["quantity": .quantity(quantity), "reason": .string(reason.rawValue), "note": .optional(note)])
    }

    static func waiting(line: Int, kind: GoodsOutWaitingKind, quantity: Double, note: String?) -> GoodsOutNoteAction {
        .post(Self.line(line, "waiting"), ["kind": .string(kind.rawValue), "quantity": .quantity(quantity), "note": .optional(note)])
    }

    static func undoPicking(line: Int, pickingID: Int) -> GoodsOutNoteAction {
        .delete(Self.line(line, "pickings/\(pickingID)"))
    }

    static func scanPick(barcode: String, quantity: Double? = nil, lineID: Int? = nil, locationOrgStockID: Int? = nil) -> GoodsOutNoteAction {
        .post("scan-pick", [
            "barcode": .string(barcode),
            "quantity": .quantity(quantity),
            "delivery_note_item_id": .optional(lineID),
            "location_org_stock_id": .optional(locationOrgStockID)
        ])
    }
}

extension Endpoint {
    static func goodsOutDeliveryNotes(warehouseID: Int, request: GoodsOutListRequest) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/delivery-notes", queryItems: request.queryItems)
    }

    static func goodsOutCounts(warehouseID: Int, picker: String?) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/delivery-notes/counts", queryItems: picker.map { [URLQueryItem(name: "picker", value: $0)] } ?? [])
    }

    static func goodsOutDeliveryNote(id: Int) -> Endpoint {
        Endpoint(path: "mobile/delivery-notes/\(id)")
    }

    static func goodsOutTrolleys(warehouseID: Int, availableOnly: Bool = false) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/trolleys", queryItems: availableOnly ? [URLQueryItem(name: "available", value: "1")] : [])
    }

    static func goodsOutPickedBays(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/picked-bays")
    }

    static func goodsOutPickers(warehouseID: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/pickers")
    }

    static func goodsOutDispatchPersonnel(warehouseID: Int, role: GoodsOutDispatchRole) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/dispatch-personnel", queryItems: [URLQueryItem(name: "role", value: role.rawValue)])
    }

    static func goodsOutNoteAction(noteID: Int, _ action: GoodsOutNoteAction) -> Endpoint {
        let encoder = JSONEncoder()
        encoder.outputFormatting = .sortedKeys
        let body = action.body.flatMap { try? encoder.encode($0) }
        return Endpoint(
            path: "mobile/delivery-notes/\(noteID)/\(action.path)",
            method: action.method,
            body: body,
            timeout: action.waitsForPlatform ? GoodsOutNoteAction.platformTimeout : nil
        )
    }
}

extension APIClient {
    func goodsOutDeliveryNotes(warehouseID: Int, request: GoodsOutListRequest) async throws -> GoodsOutDeliveryNotesPageDTO {
        try await send(.goodsOutDeliveryNotes(warehouseID: warehouseID, request: request), as: GoodsOutDeliveryNotesPageDTO.self)
    }

    func goodsOutCounts(warehouseID: Int, picker: String?) async throws -> GoodsOutCountsDTO {
        try await send(.goodsOutCounts(warehouseID: warehouseID, picker: picker), as: DataEnvelope<GoodsOutCountsDTO>.self).value
    }

    func goodsOutDeliveryNote(id: Int) async throws -> GoodsOutDeliveryNoteDTO {
        try await send(.goodsOutDeliveryNote(id: id), as: DataEnvelope<GoodsOutDeliveryNoteDTO>.self).value
    }

    func goodsOutPerform(noteID: Int, _ action: GoodsOutNoteAction) async throws -> GoodsOutActionResponseDTO {
        try await send(.goodsOutNoteAction(noteID: noteID, action), as: GoodsOutActionResponseDTO.self)
    }

    func goodsOutTrolleys(warehouseID: Int) async throws -> [GoodsOutTrolleyDTO] {
        try await send(.goodsOutTrolleys(warehouseID: warehouseID), as: DataEnvelope<[GoodsOutTrolleyDTO]>.self).value
    }

    func goodsOutPickedBays(warehouseID: Int) async throws -> [GoodsOutPickedBayDTO] {
        try await send(.goodsOutPickedBays(warehouseID: warehouseID), as: DataEnvelope<[GoodsOutPickedBayDTO]>.self).value
    }

    func goodsOutPickers(warehouseID: Int) async throws -> [GoodsOutPickerDTO] {
        try await send(.goodsOutPickers(warehouseID: warehouseID), as: DataEnvelope<[GoodsOutPickerDTO]>.self).value
    }

    func goodsOutDispatchPersonnel(warehouseID: Int, role: GoodsOutDispatchRole) async throws -> [GoodsOutDispatchPersonDTO] {
        try await send(.goodsOutDispatchPersonnel(warehouseID: warehouseID, role: role), as: DataEnvelope<[GoodsOutDispatchPersonDTO]>.self).value
    }
}

struct GoodsOutShipperDTO: Decodable, Equatable, Sendable {
    let id: Int
    let code: String?
    let name: String?
    let tradeAs: String?
    let apiShipper: String?
    let type: String?
    let needsTracking: Bool?
    let isLockedOut: Bool?
}

struct GoodsOutShipperDirectiveDTO: Decodable, Equatable, Sendable {
    let lockedShipperId: Int?
    let preferredShipperId: Int?
    let lockedBy: String?
    let canOverrideLock: Bool?
    let trackingChannel: String?
}

struct GoodsOutShippersDTO: Decodable, Equatable, Sendable {
    let data: [GoodsOutShipperDTO]
    let directive: GoodsOutShipperDirectiveDTO?
}

struct GoodsOutAddressForm: Hashable, Sendable {
    var contactName = ""
    var companyName = ""
    var email = ""
    var phone = ""
    var addressLine1 = ""
    var addressLine2 = ""
    var sortingCode = ""
    var postalCode = ""
    var locality = ""
    var dependentLocality = ""
    var administrativeArea = ""
    var countryCode = ""

    init() {}

    init(_ fields: GoodsOutAddressFieldsDTO?, fallbackCountry: String? = nil) {
        contactName = fields?.contactName ?? ""
        companyName = fields?.companyName ?? ""
        email = fields?.email ?? ""
        phone = fields?.phone ?? ""
        addressLine1 = fields?.addressLine1 ?? ""
        addressLine2 = fields?.addressLine2 ?? ""
        sortingCode = fields?.sortingCode ?? ""
        postalCode = fields?.postalCode ?? ""
        locality = fields?.locality ?? ""
        dependentLocality = fields?.dependentLocality ?? ""
        administrativeArea = fields?.administrativeArea ?? ""
        countryCode = fields?.countryCode ?? fallbackCountry ?? ""
    }

    var contactBody: [String: GoodsOutJSONValue] {
        [
            "contact_name": .optional(contactName),
            "company_name": .optional(companyName),
            "email": .optional(email),
            "phone": .optional(phone)
        ]
    }

    var addressBody: GoodsOutJSONValue {
        .object([
            "address_line_1": .optional(addressLine1),
            "address_line_2": .optional(addressLine2),
            "sorting_code": .optional(sortingCode),
            "postal_code": .optional(postalCode),
            "locality": .optional(locality),
            "dependent_locality": .optional(dependentLocality),
            "administrative_area": .optional(administrativeArea),
            "country_code": .optional(countryCode.trimmingCharacters(in: .whitespacesAndNewlines).uppercased())
        ])
    }
}

struct GoodsOutParcelInput: Equatable, Sendable {
    var weightKg: Double
    var dimensionsCm: [Double]
    var boxes: Int?
}

struct GoodsOutLineBoxInput: Equatable, Sendable {
    var box: Int
    var quantity: Double
}

enum GoodsOutNoteField: String, Sendable {
    case warehouse
    case shipping
}

extension GoodsOutNoteAction {
    static let startPacking = GoodsOutNoteAction.post("start-packing")
    static let undoPacking = GoodsOutNoteAction.post("undo-packing")
    static let unpack = GoodsOutNoteAction.post("unpack")
    static let setAsPacked = GoodsOutNoteAction.post("set-as-packed")
    static let finalise = GoodsOutNoteAction.post("finalise")
    static let dispatch = GoodsOutNoteAction.post("dispatch")
    static let finaliseAndDispatch = GoodsOutNoteAction.post("finalise-and-dispatch")
    static let externalShipment = GoodsOutNoteAction.post("external-shipment")
    static let printInserts = GoodsOutNoteAction.post("inserts/print")

    static func parcels(_ parcels: [GoodsOutParcelInput]) -> GoodsOutNoteAction {
        .post("parcels", ["parcels": .array(parcels.map { parcel in
            .object([
                "weight_kg": .quantity(parcel.weightKg),
                "dimensions_cm": .array(parcel.dimensionsCm.map { GoodsOutJSONValue.quantity($0) }),
                "boxes": .optional(parcel.boxes)
            ])
        })])
    }

    static func scanPack(barcode: String, quantity: Double? = 1, lineID: Int? = nil) -> GoodsOutNoteAction {
        .post("scan-pack", [
            "barcode": .string(barcode),
            "quantity": .quantity(quantity),
            "delivery_note_item_id": .optional(lineID)
        ])
    }

    static func pack(line: Int, quantity: Double?) -> GoodsOutNoteAction {
        .post(Self.line(line, "pack"), ["quantity": .quantity(quantity)])
    }

    static func unpack(line: Int) -> GoodsOutNoteAction {
        .post(Self.line(line, "unpack"))
    }

    static func boxes(line: Int, _ boxes: [GoodsOutLineBoxInput]) -> GoodsOutNoteAction {
        .post(Self.line(line, "boxes"), ["boxes": .array(boxes.map { .object(["box": .int($0.box), "quantity": .quantity($0.quantity)]) })])
    }

    static func addShipment(shipperID: Int, tracking: String?, overrideLock: Bool = false) -> GoodsOutNoteAction {
        .post("shipments", [
            "shipper_id": .int(shipperID),
            "tracking": .optional(tracking),
            "cost": .null,
            "sender_name": .null,
            "override_lock": .bool(overrideLock)
        ])
    }

    static func retryShipment(shipperID: Int, address: GoodsOutAddressForm, overrideLock: Bool = false) -> GoodsOutNoteAction {
        var body = address.contactBody
        body["shipper_id"] = .int(shipperID)
        body["address"] = address.addressBody
        body["override_lock"] = .bool(overrideLock)
        return .post("shipments/retry-with-address", body)
    }

    static func deleteShipment(_ shipmentID: Int) -> GoodsOutNoteAction {
        .delete("shipments/\(shipmentID)")
    }

    static func printLabel(_ shipmentID: Int) -> GoodsOutNoteAction {
        .post("shipments/\(shipmentID)/print-label")
    }

    static func cancel(createReturn: Bool) -> GoodsOutNoteAction {
        .post("cancel", ["create_return": .bool(createReturn)])
    }

    static func setNote(_ field: GoodsOutNoteField, text: String) -> GoodsOutNoteAction {
        .post("notes", ["field": .string(field.rawValue), "text": .string(text.trimmingCharacters(in: .whitespacesAndNewlines))])
    }

    static func setPacker(userID: Int) -> GoodsOutNoteAction {
        .post("packer", ["user_id": .int(userID)])
    }

    static func editAddress(_ address: GoodsOutAddressForm, includesContact: Bool) -> GoodsOutNoteAction {
        var body: [String: GoodsOutJSONValue] = includesContact ? address.contactBody : [:]
        body["address"] = address.addressBody
        return .post("delivery-address", body)
    }
}

enum GoodsOutDocument: Hashable, Sendable {
    case deliveryNote
    case packingList
    case giftMessage
    case label(shipmentID: Int)
    case insert(leafletID: Int)

    var key: String {
        switch self {
        case .deliveryNote: "delivery-note"
        case .packingList: "packing-list"
        case .giftMessage: "gift-message"
        case .label(let id): "label-\(id)"
        case .insert(let id): "insert-\(id)"
        }
    }

    var title: String {
        switch self {
        case .deliveryNote: "Delivery note PDF"
        case .packingList: "Packing list PDF"
        case .giftMessage: "Gift message PDF"
        case .label: "Label PDF"
        case .insert: "Insert PDF"
        }
    }

    var path: String {
        switch self {
        case .deliveryNote: "pdf"
        case .packingList: "packing-list.pdf"
        case .giftMessage: "gift-message.pdf"
        case .label(let id): "shipments/\(id)/label"
        case .insert(let id): "inserts/\(id)/pdf"
        }
    }

    var method: HTTPMethod {
        if case .insert = self { return .post }
        return .get
    }

    func fileName(reference: String) -> String {
        let safe = reference.unicodeScalars.map { CharacterSet.alphanumerics.contains($0) || $0 == "-" ? String($0) : "-" }.joined()
        return "\(safe.isEmpty ? "delivery-note" : safe)-\(key).pdf"
    }
}

extension Endpoint {
    static func goodsOutShippers(noteID: Int) -> Endpoint {
        Endpoint(path: "mobile/delivery-notes/\(noteID)/shippers")
    }

    static func goodsOutDocument(noteID: Int, _ document: GoodsOutDocument) -> Endpoint {
        Endpoint(path: "mobile/delivery-notes/\(noteID)/\(document.path)", method: document.method)
    }
}

extension APIClient {
    func goodsOutWrite(noteID: Int, _ action: GoodsOutNoteAction) async throws -> GoodsOutActionResponseDTO {
        let data = try await sendForm(.goodsOutNoteAction(noteID: noteID, action))
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(GoodsOutActionResponseDTO.self, from: data)
    }

    func goodsOutShippers(noteID: Int) async throws -> GoodsOutShippersDTO {
        try await send(.goodsOutShippers(noteID: noteID), as: GoodsOutShippersDTO.self)
    }

    func goodsOutDocument(noteID: Int, _ document: GoodsOutDocument) async throws -> Data {
        try await send(.goodsOutDocument(noteID: noteID, document))
    }
}
