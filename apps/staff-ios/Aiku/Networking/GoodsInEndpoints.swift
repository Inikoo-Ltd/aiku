import Foundation

struct OptionalFlexibleInt: Decodable, Sendable, Equatable {
    let value: Int?

    init(_ value: Int?) {
        self.value = value
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if container.decodeNil() {
            value = nil
        } else if let number = try? container.decode(Int.self) {
            value = number
        } else if let number = try? container.decode(Double.self) {
            value = Int(number)
        } else if let text = try? container.decode(String.self), let number = Double(text.trimmingCharacters(in: .whitespaces)) {
            value = Int(number)
        } else {
            value = nil
        }
    }
}

struct FlexibleText: Decodable, Sendable, Equatable {
    let value: String?

    init(_ value: String?) {
        self.value = value
    }

    private struct AnyKey: CodingKey {
        var stringValue: String
        var intValue: Int? { nil }
        init(stringValue: String) { self.stringValue = stringValue }
        init?(intValue: Int) { nil }
    }

    init(from decoder: Decoder) throws {
        if let container = try? decoder.singleValueContainer() {
            if container.decodeNil() {
                value = nil
                return
            }
            if let text = try? container.decode(String.self) {
                value = Self.clean(text)
                return
            }
            if let number = try? container.decode(Double.self) {
                value = number == number.rounded() ? String(Int(number)) : String(number)
                return
            }
            if let flag = try? container.decode(Bool.self) {
                value = flag ? "Yes" : "No"
                return
            }
        }
        if let object = try? decoder.container(keyedBy: AnyKey.self) {
            for key in ["label", "name", "value", "code"] {
                if let text = try? object.decode(String.self, forKey: AnyKey(stringValue: key)), let cleaned = Self.clean(text) {
                    value = cleaned
                    return
                }
            }
        }
        value = nil
    }

    private static func clean(_ text: String) -> String? {
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        return trimmed.isEmpty ? nil : trimmed
    }
}

enum GoodsInDeliveryStateKey {
    static let inProcess = "in_process"
    static let confirmed = "confirmed"
    static let readyToShip = "ready_to_ship"
    static let dispatched = "dispatched"
    static let received = "received"
    static let checked = "checked"
    static let bookingIn = "booking_in"
    static let bookedIn = "booked_in"
    static let placed = "placed"
    static let cancelled = "cancelled"
    static let notReceived = "not_received"

    static let beforeReceiving: Set<String> = [inProcess, confirmed, readyToShip, dispatched]
    static let inGoodsIn: Set<String> = [received, checked, bookingIn, bookedIn]
    static let placing: Set<String> = [received, checked, bookingIn]
}

enum GoodsInHubKey {
    static let stockDeliveries = "stock_deliveries"
    static let partners = "partners"
    static let agents = "agents"
    static let fulfilment = "fulfilment"
    static let returns = "returns"
}

struct MobileGoodsInHubEntry: Decodable, Sendable, Equatable {
    let key: String
    let label: String
    let arriving: Int?
    let received: Int?
    let checked: Int?
    let bookingIn: Int?
    let bookedIn7D: Int?
    let placed7D: Int?

    init(key: String, label: String, arriving: Int? = nil, received: Int? = nil, checked: Int? = nil, bookingIn: Int? = nil, bookedIn7D: Int? = nil, placed7D: Int? = nil) {
        self.key = key
        self.label = label
        self.arriving = arriving
        self.received = received
        self.checked = checked
        self.bookingIn = bookingIn
        self.bookedIn7D = bookedIn7D
        self.placed7D = placed7D
    }

    private enum CodingKeys: String, CodingKey {
        case key, label, arriving, received, checked, bookingIn, bookedIn7D, placed7D
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        key = (try? container.decode(String.self, forKey: .key)) ?? ""
        label = (try? container.decode(String.self, forKey: .label)) ?? ""
        arriving = (try? container.decode(OptionalFlexibleInt.self, forKey: .arriving))?.value
        received = (try? container.decode(OptionalFlexibleInt.self, forKey: .received))?.value
        checked = (try? container.decode(OptionalFlexibleInt.self, forKey: .checked))?.value
        bookingIn = (try? container.decode(OptionalFlexibleInt.self, forKey: .bookingIn))?.value
        bookedIn7D = (try? container.decode(OptionalFlexibleInt.self, forKey: .bookedIn7D))?.value
        placed7D = (try? container.decode(OptionalFlexibleInt.self, forKey: .placed7D))?.value
    }
}

struct MobileGoodsInHub: Decodable, Sendable, Equatable {
    let data: [MobileGoodsInHubEntry]
}

struct MobileGoodsInParty: Decodable, Sendable, Equatable {
    var type: String? = nil
    var code: String? = nil
    var name: String? = nil

    var label: String {
        let name = name?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        return name.isEmpty ? (code ?? "") : name
    }
}

struct MobileGoodsInPlaceRef: Decodable, Sendable, Equatable {
    let id: Int
    var code: String = ""

    private enum CodingKeys: String, CodingKey {
        case id, code
    }

    init(id: Int, code: String) {
        self.id = id
        self.code = code
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        code = (try? container.decode(String.self, forKey: .code)) ?? ""
    }
}

struct MobileGoodsInStaffRef: Decodable, Sendable, Equatable {
    var id: Int? = nil
    var name: String = ""

    private enum CodingKeys: String, CodingKey {
        case id, name
    }

    init(id: Int? = nil, name: String) {
        self.id = id
        self.name = name
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = (try? container.decode(OptionalFlexibleInt.self, forKey: .id))?.value
        name = (try? container.decode(String.self, forKey: .name)) ?? ""
    }
}

struct MobileGoodsInAction: Decodable, Sendable, Equatable {
    let key: String
    var label: String = ""
    var disabledReason: String? = nil

    var isDisabled: Bool { !(disabledReason?.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ?? true) }

    private enum CodingKeys: String, CodingKey {
        case key, label, disabledReason
    }

    init(key: String, label: String = "", disabledReason: String? = nil) {
        self.key = key
        self.label = label
        self.disabledReason = disabledReason
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        key = try container.decode(String.self, forKey: .key)
        label = (try? container.decode(String.self, forKey: .label)) ?? ""
        disabledReason = try? container.decodeIfPresent(String.self, forKey: .disabledReason)
    }
}

extension Array where Element == MobileGoodsInAction {
    func action(_ key: String) -> MobileGoodsInAction? {
        first { $0.key == key }
    }

    func can(_ key: String) -> Bool {
        guard let action = action(key) else { return false }
        return !action.isDisabled
    }
}

struct MobileStockDeliveryRow: Decodable, Sendable, Equatable {
    let id: Int
    var reference: String = ""
    var agentReference: String? = nil
    var supplier: MobileGoodsInParty? = nil
    var source: String? = nil
    var state: String = ""
    var stateLabel: String = ""
    var dispatchedAt: String? = nil
    var receivedAt: String? = nil
    var estimatedReceivingDate: String? = nil
    var numberItems: Int = 0
    var numberChecked: Int = 0
    var numberPlaced: Int = 0

    private enum CodingKeys: String, CodingKey {
        case id, reference, agentReference, supplier, source, state, stateLabel, dispatchedAt, receivedAt, estimatedReceivingDate, numberItems, numberChecked, numberPlaced
    }

    init(id: Int, reference: String, state: String, stateLabel: String = "") {
        self.id = id
        self.reference = reference
        self.state = state
        self.stateLabel = stateLabel
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        reference = (try? container.decode(String.self, forKey: .reference)) ?? ""
        agentReference = try? container.decodeIfPresent(String.self, forKey: .agentReference)
        supplier = try? container.decodeIfPresent(MobileGoodsInParty.self, forKey: .supplier)
        source = try? container.decodeIfPresent(String.self, forKey: .source)
        state = (try? container.decode(String.self, forKey: .state)) ?? ""
        stateLabel = (try? container.decode(String.self, forKey: .stateLabel)) ?? ""
        dispatchedAt = try? container.decodeIfPresent(String.self, forKey: .dispatchedAt)
        receivedAt = try? container.decodeIfPresent(String.self, forKey: .receivedAt)
        estimatedReceivingDate = try? container.decodeIfPresent(String.self, forKey: .estimatedReceivingDate)
        numberItems = (try? container.decode(OptionalFlexibleInt.self, forKey: .numberItems))?.value ?? 0
        numberChecked = (try? container.decode(OptionalFlexibleInt.self, forKey: .numberChecked))?.value ?? 0
        numberPlaced = (try? container.decode(OptionalFlexibleInt.self, forKey: .numberPlaced))?.value ?? 0
    }
}

struct MobileGoodsInCounts: Decodable, Sendable, Equatable {
    let values: [String: Int]

    init(_ values: [String: Int]) {
        self.values = values
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        let raw = (try? container.decode([String: OptionalFlexibleInt].self)) ?? [:]
        values = raw.compactMapValues(\.value)
    }

    func count(_ key: String) -> Int? {
        values[key]
    }
}

struct MobileStockDeliveriesPage: Decodable, Sendable, Equatable {
    let data: [MobileStockDeliveryRow]
    let meta: MobileLocationsPage.Meta
    var counts: MobileGoodsInCounts? = nil
}

struct MobileStockDeliveryInfo: Decodable, Sendable, Equatable {
    var type: FlexibleText? = nil
    var typeLabel: FlexibleText? = nil
    var containerSize: FlexibleText? = nil
    var incoterm: FlexibleText? = nil
    var portOfExport: FlexibleText? = nil
    var portOfImport: FlexibleText? = nil
    var shipperReference: FlexibleText? = nil
    var trackingNumber: FlexibleText? = nil
    var containerNumber: FlexibleText? = nil
    var shippingAgent: FlexibleText? = nil

    var typeText: String? { typeLabel?.value ?? type?.value }
}

struct MobileStockDeliverySummary: Decodable, Sendable, Equatable {
    var numberItems = 0
    var numberChecked = 0
    var numberPlaced = 0
    var numberNotReceived = 0
    var numberNewOrgStocks = 0
    var underDelivered = 0
    var overDelivered = 0

    private enum CodingKeys: String, CodingKey {
        case numberItems, numberChecked, numberPlaced, numberNotReceived, numberNewOrgStocks, underDelivered, overDelivered
    }

    init() {}

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        func int(_ key: CodingKeys) -> Int { (try? container.decode(OptionalFlexibleInt.self, forKey: key))?.value ?? 0 }
        numberItems = int(.numberItems)
        numberChecked = int(.numberChecked)
        numberPlaced = int(.numberPlaced)
        numberNotReceived = int(.numberNotReceived)
        numberNewOrgStocks = int(.numberNewOrgStocks)
        underDelivered = int(.underDelivered)
        overDelivered = int(.overDelivered)
    }
}

struct MobileGoodsInSko: Decodable, Sendable, Equatable {
    let id: Int
    var code: String = ""
    var name: String = ""
    var packedIn: Double? = nil
    var barcode: String? = nil
    var imageUrl: String? = nil

    private enum CodingKeys: String, CodingKey {
        case id, code, name, packedIn, barcode, imageUrl
    }

    init(id: Int, code: String, name: String = "", packedIn: Double? = nil, barcode: String? = nil) {
        self.id = id
        self.code = code
        self.name = name
        self.packedIn = packedIn
        self.barcode = barcode
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        code = (try? container.decode(String.self, forKey: .code)) ?? ""
        name = (try? container.decode(String.self, forKey: .name)) ?? ""
        packedIn = (try? container.decode(OptionalFlexibleDouble.self, forKey: .packedIn))?.value
        barcode = try? container.decodeIfPresent(String.self, forKey: .barcode)
        imageUrl = try? container.decodeIfPresent(String.self, forKey: .imageUrl)
    }
}

struct MobileGoodsInItemLocation: Decodable, Sendable, Equatable {
    let locationOrgStockId: Int
    let location: MobileGoodsInPlaceRef
    var type: String = ""
    var quantity: Double = 0
    var quantityLabel: String? = nil
    var isDefaultWholesale = false
    var isDefaultDropshipping = false

    private enum CodingKeys: String, CodingKey {
        case locationOrgStockId, location, type, quantity, quantityLabel, isDefaultWholesale, isDefaultDropshipping
    }

    init(locationOrgStockId: Int, location: MobileGoodsInPlaceRef, type: String = "", quantity: Double = 0, isDefaultWholesale: Bool = false, isDefaultDropshipping: Bool = false) {
        self.locationOrgStockId = locationOrgStockId
        self.location = location
        self.type = type
        self.quantity = quantity
        self.isDefaultWholesale = isDefaultWholesale
        self.isDefaultDropshipping = isDefaultDropshipping
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        locationOrgStockId = try container.decode(FlexibleInt.self, forKey: .locationOrgStockId).value
        location = try container.decode(MobileGoodsInPlaceRef.self, forKey: .location)
        type = (try? container.decode(String.self, forKey: .type)) ?? ""
        quantity = (try? container.decode(OptionalFlexibleDouble.self, forKey: .quantity))?.value ?? 0
        quantityLabel = try? container.decodeIfPresent(String.self, forKey: .quantityLabel)
        isDefaultWholesale = (try? container.decode(Bool.self, forKey: .isDefaultWholesale)) ?? false
        isDefaultDropshipping = (try? container.decode(Bool.self, forKey: .isDefaultDropshipping)) ?? false
    }

    var isPicking: Bool { type == "picking" }
}

struct MobileGoodsInPlacement: Decodable, Sendable, Equatable {
    let id: Int
    var quantity: Double = 0
    var quantityLabel: String? = nil
    var location: MobileGoodsInPlaceRef? = nil

    private enum CodingKeys: String, CodingKey {
        case id, quantity, quantityLabel, location
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        quantity = (try? container.decode(OptionalFlexibleDouble.self, forKey: .quantity))?.value ?? 0
        quantityLabel = try? container.decodeIfPresent(String.self, forKey: .quantityLabel)
        location = try? container.decodeIfPresent(MobileGoodsInPlaceRef.self, forKey: .location)
    }
}

struct MobileStockDeliveryItem: Decodable, Sendable, Equatable {
    struct SupplierProduct: Decodable, Sendable, Equatable {
        var code: String? = nil
        var name: String? = nil
    }

    let id: Int
    var state: String = ""
    var stateLabel: String = ""
    var supplierProduct: SupplierProduct? = nil
    var purchaseOrderReference: String? = nil
    var isNewOrgStock = false
    var sko: MobileGoodsInSko? = nil
    var skoExpected: Double = 0
    var skoExpectedLabel: String? = nil
    var skoChecked: Double? = nil
    var skoCheckedLabel: String? = nil
    var skoPlaced: Double = 0
    var skoPlacedLabel: String? = nil
    var skoToPlace: Double = 0
    var skoToPlaceLabel: String? = nil
    var hasPickingLocation = false
    var locations: [MobileGoodsInItemLocation] = []
    var placements: [MobileGoodsInPlacement] = []

    private enum CodingKeys: String, CodingKey {
        case id, state, stateLabel, supplierProduct, purchaseOrderReference, isNewOrgStock, sko, skoExpected, skoExpectedLabel, skoChecked, skoCheckedLabel
        case skoPlaced, skoPlacedLabel, skoToPlace, skoToPlaceLabel, hasPickingLocation, locations, placements
    }

    init(id: Int, state: String, sko: MobileGoodsInSko?, skoExpected: Double, skoChecked: Double? = nil, skoPlaced: Double = 0, skoToPlace: Double = 0, hasPickingLocation: Bool = false, locations: [MobileGoodsInItemLocation] = []) {
        self.id = id
        self.state = state
        self.sko = sko
        self.skoExpected = skoExpected
        self.skoChecked = skoChecked
        self.skoPlaced = skoPlaced
        self.skoToPlace = skoToPlace
        self.hasPickingLocation = hasPickingLocation
        self.locations = locations
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        state = (try? container.decode(String.self, forKey: .state)) ?? ""
        stateLabel = (try? container.decode(String.self, forKey: .stateLabel)) ?? ""
        supplierProduct = try? container.decodeIfPresent(SupplierProduct.self, forKey: .supplierProduct)
        purchaseOrderReference = try? container.decodeIfPresent(String.self, forKey: .purchaseOrderReference)
        isNewOrgStock = (try? container.decode(Bool.self, forKey: .isNewOrgStock)) ?? false
        sko = try? container.decodeIfPresent(MobileGoodsInSko.self, forKey: .sko)
        skoExpected = (try? container.decode(OptionalFlexibleDouble.self, forKey: .skoExpected))?.value ?? 0
        skoExpectedLabel = try? container.decodeIfPresent(String.self, forKey: .skoExpectedLabel)
        skoChecked = (try? container.decode(OptionalFlexibleDouble.self, forKey: .skoChecked))?.value
        skoCheckedLabel = try? container.decodeIfPresent(String.self, forKey: .skoCheckedLabel)
        skoPlaced = (try? container.decode(OptionalFlexibleDouble.self, forKey: .skoPlaced))?.value ?? 0
        skoPlacedLabel = try? container.decodeIfPresent(String.self, forKey: .skoPlacedLabel)
        skoToPlace = (try? container.decode(OptionalFlexibleDouble.self, forKey: .skoToPlace))?.value ?? 0
        skoToPlaceLabel = try? container.decodeIfPresent(String.self, forKey: .skoToPlaceLabel)
        hasPickingLocation = (try? container.decode(Bool.self, forKey: .hasPickingLocation)) ?? false
        locations = (try? container.decodeIfPresent([MobileGoodsInItemLocation].self, forKey: .locations)) ?? []
        placements = (try? container.decodeIfPresent([MobileGoodsInPlacement].self, forKey: .placements)) ?? []
    }

    var code: String {
        if let code = sko?.code, !code.isEmpty { return code }
        return supplierProduct?.code ?? ""
    }

    var name: String {
        if let name = sko?.name, !name.isEmpty { return name }
        return supplierProduct?.name ?? ""
    }

    var isDone: Bool { state == GoodsInDeliveryStateKey.placed || state == GoodsInDeliveryStateKey.cancelled }
    var isNotReceived: Bool { state == GoodsInDeliveryStateKey.notReceived || skoChecked == 0 }
    var needsCheck: Bool { !isDone && skoChecked == nil }
    var canBePlaced: Bool { state != GoodsInDeliveryStateKey.cancelled && skoToPlace > 0 }
    var hasBeenChecked: Bool { (skoChecked ?? 0) > 0 }
}

struct MobileStockDeliveryChecklist: Decodable, Sendable, Equatable {
    struct Check: Decodable, Sendable, Equatable {
        let key: String
        var label: String = ""
        var detail: String? = nil
        var severity: String? = nil
        var result: String? = nil
        var checkedBy: MobileGoodsInStaffRef? = nil
        var checkedAt: String? = nil

        private enum CodingKeys: String, CodingKey {
            case key, label, detail, severity, result, checkedBy, checkedAt
        }

        init(key: String, label: String, result: String? = nil) {
            self.key = key
            self.label = label
            self.result = result
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            key = try container.decode(String.self, forKey: .key)
            label = (try? container.decode(String.self, forKey: .label)) ?? ""
            detail = try? container.decodeIfPresent(String.self, forKey: .detail)
            severity = try? container.decodeIfPresent(String.self, forKey: .severity)
            result = try? container.decodeIfPresent(String.self, forKey: .result)
            checkedBy = try? container.decodeIfPresent(MobileGoodsInStaffRef.self, forKey: .checkedBy)
            checkedAt = try? container.decodeIfPresent(String.self, forKey: .checkedAt)
        }
    }

    struct Section: Decodable, Sendable, Equatable {
        var key: String = ""
        var title: String = ""
        var subtitle: String? = nil
        var checks: [Check] = []

        private enum CodingKeys: String, CodingKey {
            case key, title, subtitle, checks
        }

        init(key: String, title: String, subtitle: String? = nil, checks: [Check]) {
            self.key = key
            self.title = title
            self.subtitle = subtitle
            self.checks = checks
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            key = (try? container.decode(String.self, forKey: .key)) ?? ""
            title = (try? container.decode(String.self, forKey: .title)) ?? ""
            subtitle = try? container.decodeIfPresent(String.self, forKey: .subtitle)
            checks = (try? container.decodeIfPresent([Check].self, forKey: .checks)) ?? []
        }
    }

    struct IncidentType: Decodable, Sendable, Equatable {
        let value: String
        var label: String = ""

        private enum CodingKeys: String, CodingKey {
            case value, label
        }

        init(value: String, label: String) {
            self.value = value
            self.label = label
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            value = try container.decode(String.self, forKey: .value)
            label = (try? container.decode(String.self, forKey: .label)) ?? value
        }
    }

    var sections: [Section] = []
    var total = 0
    var checked = 0
    var failed = 0
    var incidentTypes: [IncidentType] = []

    private enum CodingKeys: String, CodingKey {
        case sections, total, checked, failed, incidentTypes
    }

    init(sections: [Section], total: Int = 0, checked: Int = 0) {
        self.sections = sections
        self.total = total
        self.checked = checked
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        sections = (try? container.decodeIfPresent([Section].self, forKey: .sections)) ?? []
        total = (try? container.decode(OptionalFlexibleInt.self, forKey: .total))?.value ?? 0
        checked = (try? container.decode(OptionalFlexibleInt.self, forKey: .checked))?.value ?? 0
        failed = (try? container.decode(OptionalFlexibleInt.self, forKey: .failed))?.value ?? 0
        incidentTypes = (try? container.decodeIfPresent([IncidentType].self, forKey: .incidentTypes)) ?? []
    }
}

struct MobileStockDelivery: Decodable, Sendable, Equatable {
    struct PurchaseOrder: Decodable, Sendable, Equatable {
        let id: Int
        var reference: String = ""

        private enum CodingKeys: String, CodingKey {
            case id, reference
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            id = try container.decode(FlexibleInt.self, forKey: .id).value
            reference = (try? container.decode(String.self, forKey: .reference)) ?? ""
        }
    }

    let id: Int
    var reference: String = ""
    var agentReference: String? = nil
    var state: String = ""
    var stateLabel: String = ""
    var supplier: MobileGoodsInParty? = nil
    var source: String? = nil
    var dispatchedAt: String? = nil
    var receivedAt: String? = nil
    var estimatedReceivingDate: String? = nil
    var delivery: MobileStockDeliveryInfo? = nil
    var purchaseOrders: [PurchaseOrder] = []
    var summary = MobileStockDeliverySummary()
    var items: [MobileStockDeliveryItem] = []
    var checklist: MobileStockDeliveryChecklist? = nil
    var availableActions: [MobileGoodsInAction] = []

    private enum CodingKeys: String, CodingKey {
        case id, reference, agentReference, state, stateLabel, supplier, source, dispatchedAt, receivedAt, estimatedReceivingDate, delivery, purchaseOrders, summary, items, checklist, availableActions
    }

    init(id: Int, reference: String, state: String, items: [MobileStockDeliveryItem] = [], checklist: MobileStockDeliveryChecklist? = nil, availableActions: [MobileGoodsInAction] = []) {
        self.id = id
        self.reference = reference
        self.state = state
        self.items = items
        self.checklist = checklist
        self.availableActions = availableActions
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        reference = (try? container.decode(String.self, forKey: .reference)) ?? ""
        agentReference = try? container.decodeIfPresent(String.self, forKey: .agentReference)
        state = (try? container.decode(String.self, forKey: .state)) ?? ""
        stateLabel = (try? container.decode(String.self, forKey: .stateLabel)) ?? ""
        supplier = try? container.decodeIfPresent(MobileGoodsInParty.self, forKey: .supplier)
        source = try? container.decodeIfPresent(String.self, forKey: .source)
        dispatchedAt = try? container.decodeIfPresent(String.self, forKey: .dispatchedAt)
        receivedAt = try? container.decodeIfPresent(String.self, forKey: .receivedAt)
        estimatedReceivingDate = try? container.decodeIfPresent(String.self, forKey: .estimatedReceivingDate)
        delivery = try? container.decodeIfPresent(MobileStockDeliveryInfo.self, forKey: .delivery)
        purchaseOrders = (try? container.decodeIfPresent([PurchaseOrder].self, forKey: .purchaseOrders)) ?? []
        summary = (try? container.decodeIfPresent(MobileStockDeliverySummary.self, forKey: .summary)) ?? MobileStockDeliverySummary()
        items = (try? container.decodeIfPresent([MobileStockDeliveryItem].self, forKey: .items)) ?? []
        checklist = try? container.decodeIfPresent(MobileStockDeliveryChecklist.self, forKey: .checklist)
        availableActions = (try? container.decodeIfPresent([MobileGoodsInAction].self, forKey: .availableActions)) ?? []
    }

    func item(_ id: Int) -> MobileStockDeliveryItem? {
        items.first { $0.id == id }
    }
}

struct MobileGoodsInResult: Decodable, Sendable, Equatable {
    var status: String? = nil
    var message: String? = nil
}

struct MobileStockDeliveryWriteResult: Decodable, Sendable, Equatable {
    let data: MobileStockDelivery
    var result: MobileGoodsInResult? = nil
}

struct MobileReturnCustomer: Decodable, Sendable, Equatable {
    var name: String = ""

    private enum CodingKeys: String, CodingKey {
        case name
    }

    init(name: String) {
        self.name = name
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        name = (try? container.decode(String.self, forKey: .name)) ?? ""
    }
}

struct MobileReturnRow: Decodable, Sendable, Equatable {
    let id: Int
    var reference: String = ""
    var type: String = ""
    var typeLabel: String = ""
    var state: String = ""
    var stateLabel: String = ""
    var createdAt: String? = nil
    var customer: MobileReturnCustomer? = nil
    var deliveryNote: MobileGoodsInNoteRef? = nil
    var numberItems = 0
    var numberHandled = 0
    var handler: MobileGoodsInStaffRef? = nil

    private enum CodingKeys: String, CodingKey {
        case id, reference, type, typeLabel, state, stateLabel, createdAt, customer, deliveryNote, numberItems, numberHandled, handler
    }

    init(id: Int, reference: String, state: String) {
        self.id = id
        self.reference = reference
        self.state = state
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        reference = (try? container.decode(String.self, forKey: .reference)) ?? ""
        type = (try? container.decode(String.self, forKey: .type)) ?? ""
        typeLabel = (try? container.decode(String.self, forKey: .typeLabel)) ?? ""
        state = (try? container.decode(String.self, forKey: .state)) ?? ""
        stateLabel = (try? container.decode(String.self, forKey: .stateLabel)) ?? ""
        createdAt = try? container.decodeIfPresent(String.self, forKey: .createdAt)
        customer = try? container.decodeIfPresent(MobileReturnCustomer.self, forKey: .customer)
        deliveryNote = try? container.decodeIfPresent(MobileGoodsInNoteRef.self, forKey: .deliveryNote)
        numberItems = (try? container.decode(OptionalFlexibleInt.self, forKey: .numberItems))?.value ?? 0
        numberHandled = (try? container.decode(OptionalFlexibleInt.self, forKey: .numberHandled))?.value ?? 0
        handler = try? container.decodeIfPresent(MobileGoodsInStaffRef.self, forKey: .handler)
    }
}

struct MobileGoodsInNoteRef: Decodable, Sendable, Equatable {
    let id: Int
    var reference: String = ""

    private enum CodingKeys: String, CodingKey {
        case id, reference
    }

    init(id: Int, reference: String) {
        self.id = id
        self.reference = reference
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        reference = (try? container.decode(String.self, forKey: .reference)) ?? ""
    }
}

struct MobileReturnsPage: Decodable, Sendable, Equatable {
    let data: [MobileReturnRow]
    let meta: MobileLocationsPage.Meta
    var counts: MobileGoodsInCounts? = nil
}

struct MobileReturnPut: Decodable, Sendable, Equatable {
    let id: Int
    var type: String = ""
    var typeLabel: String = ""
    var quantity: Double = 0
    var quantityLabel: String? = nil
    var location: MobileGoodsInPlaceRef? = nil
    var by: MobileGoodsInStaffRef? = nil

    private enum CodingKeys: String, CodingKey {
        case id, type, typeLabel, quantity, quantityLabel, location, by
    }

    init(id: Int, type: String, quantity: Double, location: MobileGoodsInPlaceRef? = nil) {
        self.id = id
        self.type = type
        self.quantity = quantity
        self.location = location
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        type = (try? container.decode(String.self, forKey: .type)) ?? ""
        typeLabel = (try? container.decode(String.self, forKey: .typeLabel)) ?? ""
        quantity = (try? container.decode(OptionalFlexibleDouble.self, forKey: .quantity))?.value ?? 0
        quantityLabel = try? container.decodeIfPresent(String.self, forKey: .quantityLabel)
        location = try? container.decodeIfPresent(MobileGoodsInPlaceRef.self, forKey: .location)
        by = try? container.decodeIfPresent(MobileGoodsInStaffRef.self, forKey: .by)
    }
}

struct MobileReturnItem: Decodable, Sendable, Equatable {
    let id: Int
    var state: String = ""
    var isHandled = false
    var sko: MobileGoodsInSko? = nil
    var expected: Double = 0
    var expectedLabel: String? = nil
    var returned: Double = 0
    var returnedLabel: String? = nil
    var damaged: Double = 0
    var damagedLabel: String? = nil
    var notReturned: Double = 0
    var notReturnedLabel: String? = nil
    var remaining: Double = 0
    var remainingLabel: String? = nil
    var puts: [MobileReturnPut] = []
    var locations: [MobileGoodsInItemLocation] = []
    var barcodes: [String] = []

    private enum CodingKeys: String, CodingKey {
        case id, state, isHandled, sko, expected, expectedLabel, returned, returnedLabel, damaged, damagedLabel, notReturned, notReturnedLabel, remaining, remainingLabel, puts, locations, barcodes
    }

    init(id: Int, sko: MobileGoodsInSko?, expected: Double, remaining: Double, locations: [MobileGoodsInItemLocation] = [], puts: [MobileReturnPut] = []) {
        self.id = id
        self.sko = sko
        self.expected = expected
        self.remaining = remaining
        self.locations = locations
        self.puts = puts
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        func number(_ key: CodingKeys) -> Double { (try? container.decode(OptionalFlexibleDouble.self, forKey: key))?.value ?? 0 }
        func text(_ key: CodingKeys) -> String? { try? container.decodeIfPresent(String.self, forKey: key) }
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        state = text(.state) ?? ""
        isHandled = (try? container.decode(Bool.self, forKey: .isHandled)) ?? false
        sko = try? container.decodeIfPresent(MobileGoodsInSko.self, forKey: .sko)
        expected = number(.expected)
        expectedLabel = text(.expectedLabel)
        returned = number(.returned)
        returnedLabel = text(.returnedLabel)
        damaged = number(.damaged)
        damagedLabel = text(.damagedLabel)
        notReturned = number(.notReturned)
        notReturnedLabel = text(.notReturnedLabel)
        remaining = number(.remaining)
        remainingLabel = text(.remainingLabel)
        puts = (try? container.decodeIfPresent([MobileReturnPut].self, forKey: .puts)) ?? []
        locations = (try? container.decodeIfPresent([MobileGoodsInItemLocation].self, forKey: .locations)) ?? []
        barcodes = (try? container.decodeIfPresent([String].self, forKey: .barcodes)) ?? []
    }

    var code: String { sko?.code ?? "" }
    var hasRemaining: Bool { remaining > 0 }
}

struct MobileReturnTimelineEntry: Decodable, Sendable, Equatable {
    var state: String = ""
    var label: String = ""
    var at: String? = nil

    private enum CodingKeys: String, CodingKey {
        case state, label, at
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        state = (try? container.decode(String.self, forKey: .state)) ?? ""
        label = (try? container.decode(String.self, forKey: .label)) ?? ""
        at = try? container.decodeIfPresent(String.self, forKey: .at)
    }
}

struct MobileReturnOrder: Decodable, Sendable, Equatable {
    var id: Int? = nil
    var reference: String = ""

    private enum CodingKeys: String, CodingKey {
        case id, reference
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = (try? container.decode(OptionalFlexibleInt.self, forKey: .id))?.value
        reference = (try? container.decode(String.self, forKey: .reference)) ?? ""
    }
}

struct MobileReturn: Decodable, Sendable, Equatable {
    let id: Int
    var reference: String = ""
    var type: String = ""
    var typeLabel: String = ""
    var state: String = ""
    var stateLabel: String = ""
    var customer: MobileReturnCustomer? = nil
    var order: MobileReturnOrder? = nil
    var deliveryNote: MobileGoodsInNoteRef? = nil
    var handler: MobileGoodsInStaffRef? = nil
    var timeline: [MobileReturnTimelineEntry] = []
    var items: [MobileReturnItem] = []
    var availableActions: [MobileGoodsInAction] = []

    private enum CodingKeys: String, CodingKey {
        case id, reference, type, typeLabel, state, stateLabel, customer, order, deliveryNote, handler, timeline, items, availableActions
    }

    init(id: Int, reference: String, state: String, items: [MobileReturnItem] = [], availableActions: [MobileGoodsInAction] = []) {
        self.id = id
        self.reference = reference
        self.state = state
        self.items = items
        self.availableActions = availableActions
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(FlexibleInt.self, forKey: .id).value
        reference = (try? container.decode(String.self, forKey: .reference)) ?? ""
        type = (try? container.decode(String.self, forKey: .type)) ?? ""
        typeLabel = (try? container.decode(String.self, forKey: .typeLabel)) ?? ""
        state = (try? container.decode(String.self, forKey: .state)) ?? ""
        stateLabel = (try? container.decode(String.self, forKey: .stateLabel)) ?? ""
        customer = try? container.decodeIfPresent(MobileReturnCustomer.self, forKey: .customer)
        order = try? container.decodeIfPresent(MobileReturnOrder.self, forKey: .order)
        deliveryNote = try? container.decodeIfPresent(MobileGoodsInNoteRef.self, forKey: .deliveryNote)
        handler = try? container.decodeIfPresent(MobileGoodsInStaffRef.self, forKey: .handler)
        timeline = (try? container.decodeIfPresent([MobileReturnTimelineEntry].self, forKey: .timeline)) ?? []
        items = (try? container.decodeIfPresent([MobileReturnItem].self, forKey: .items)) ?? []
        availableActions = (try? container.decodeIfPresent([MobileGoodsInAction].self, forKey: .availableActions)) ?? []
    }

    func item(_ id: Int) -> MobileReturnItem? {
        items.first { $0.id == id }
    }
}

struct MobileReturnWriteResult: Decodable, Sendable, Equatable {
    let data: MobileReturn
    var result: MobileGoodsInResult? = nil
}

enum GoodsInDeliveryStateFilter: String, CaseIterable, Hashable, Sendable {
    case active
    case dispatched
    case received
    case checked
    case bookingIn = "booking_in"
    case bookedIn = "booked_in"
    case placed
    case notReceived = "not_received"
    case cancelled
}

enum GoodsInDeliverySource: String, CaseIterable, Hashable, Sendable {
    case suppliers
    case agents
    case partners
}

enum GoodsInDispatchedBucket: String, CaseIterable, Hashable, Sendable {
    case today
    case week
    case older
}

enum GoodsInDeliverySort: String, CaseIterable, Hashable, Sendable {
    case newest = "-date"
    case dispatchedDate = "-dispatched_at"
    case reference
    case items = "-number_items"
}

struct MobileStockDeliveriesQuery: Hashable, Sendable {
    var state: GoodsInDeliveryStateFilter = .active
    var source: GoodsInDeliverySource?
    var search = ""
    var dispatched: GoodsInDispatchedBucket?
    var sort: GoodsInDeliverySort?
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [URLQueryItem(name: "state", value: state.rawValue)]
        if let source { items.append(URLQueryItem(name: "source", value: source.rawValue)) }
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        if let dispatched { items.append(URLQueryItem(name: "dispatched", value: dispatched.rawValue)) }
        if let sort { items.append(URLQueryItem(name: "sort", value: sort.rawValue)) }
        items.append(URLQueryItem(name: "page", value: "\(page)"))
        items.append(URLQueryItem(name: "per_page", value: "\(perPage)"))
        return items
    }
}

enum GoodsInReturnStateFilter: String, CaseIterable, Hashable, Sendable {
    case all
    case toProcess = "to_process"
    case received
    case returning
    case returned
    case done
    case cancelled
}

struct MobileReturnsQuery: Hashable, Sendable {
    var state: GoodsInReturnStateFilter = .all
    var search = ""
    var page = 1
    var perPage = 50

    var queryItems: [URLQueryItem] {
        var items = [URLQueryItem(name: "state", value: state.rawValue)]
        let trimmed = search.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { items.append(URLQueryItem(name: "q", value: trimmed)) }
        items.append(URLQueryItem(name: "page", value: "\(page)"))
        items.append(URLQueryItem(name: "per_page", value: "\(perPage)"))
        return items
    }
}

enum GoodsInPlaceTarget: Hashable, Sendable {
    case link(locationOrgStockID: Int, code: String)
    case location(locationID: Int, code: String)

    var code: String {
        switch self {
        case .link(_, let code), .location(_, let code): code
        }
    }
}

enum GoodsInChecklistResult: String, Hashable, Sendable {
    case passed
    case failed
}

enum GoodsInDeliveryWrite: Hashable, Sendable {
    case receive
    case unreceive
    case checkAll
    case check(itemID: Int, skoQuantity: Double)
    case place(itemID: Int, quantity: Double, target: GoodsInPlaceTarget, setAsPickingLocation: Bool, clientRequestID: String)
    case placeAll(itemID: Int, target: GoodsInPlaceTarget, setAsPickingLocation: Bool, clientRequestID: String)
    case recordCheck(key: String, result: GoodsInChecklistResult, body: String?, incidentType: String?, incidentQuantity: Double?)

    var isPlacement: Bool {
        switch self {
        case .place, .placeAll: true
        default: false
        }
    }

    var path: String {
        switch self {
        case .receive: "receive"
        case .unreceive: "unreceive"
        case .checkAll: "check-all"
        case .check(let itemID, _): "items/\(itemID)/check"
        case .place(let itemID, _, _, _, _): "items/\(itemID)/place"
        case .placeAll(let itemID, _, _, _): "items/\(itemID)/place-all"
        case .recordCheck: "checklist"
        }
    }

    var body: [String: GoodsInJSONValue] {
        switch self {
        case .receive, .unreceive, .checkAll:
            return [:]
        case .check(_, let skoQuantity):
            return ["sko_quantity": .number(skoQuantity)]
        case let .place(_, quantity, target, setAsPickingLocation, clientRequestID):
            return Self.targetBody(target).merging([
                "quantity": .number(quantity),
                "set_as_picking_location": .bool(setAsPickingLocation),
                "client_request_id": .text(clientRequestID),
            ]) { _, new in new }
        case let .placeAll(_, target, setAsPickingLocation, clientRequestID):
            return Self.targetBody(target).merging([
                "set_as_picking_location": .bool(setAsPickingLocation),
                "client_request_id": .text(clientRequestID),
            ]) { _, new in new }
        case let .recordCheck(key, result, body, incidentType, incidentQuantity):
            var values: [String: GoodsInJSONValue] = ["key": .text(key), "result": .text(result.rawValue)]
            values["body"] = GoodsInJSONValue.text(body)
            values["incident_type"] = GoodsInJSONValue.text(incidentType)
            values["incident_quantity"] = incidentQuantity.map(GoodsInJSONValue.number) ?? .null
            return values
        }
    }

    private static func targetBody(_ target: GoodsInPlaceTarget) -> [String: GoodsInJSONValue] {
        switch target {
        case .link(let id, _): ["location_org_stock_id": .int(id)]
        case .location(let id, _): ["location_id": .int(id)]
        }
    }
}

enum GoodsInReturnWrite: Hashable, Sendable {
    case start
    case returned(itemID: Int, quantity: Double?, locationOrgStockID: Int, clientRequestID: String)
    case damaged(itemID: Int, quantity: Double?, clientRequestID: String)
    case notReturned(itemID: Int, quantity: Double?, clientRequestID: String)
    case undoPut(putID: Int)
    case setAsReturned

    var method: HTTPMethod {
        if case .undoPut = self { return .delete }
        return .post
    }

    var path: String {
        switch self {
        case .start: "start"
        case .returned(let itemID, _, _, _): "items/\(itemID)/returned"
        case .damaged(let itemID, _, _): "items/\(itemID)/damaged"
        case .notReturned(let itemID, _, _): "items/\(itemID)/not-returned"
        case .undoPut(let putID): "puts/\(putID)"
        case .setAsReturned: "set-as-returned"
        }
    }

    var body: [String: GoodsInJSONValue]? {
        switch self {
        case .start, .setAsReturned:
            return [:]
        case let .returned(_, quantity, locationOrgStockID, clientRequestID):
            return ["quantity": quantity.map(GoodsInJSONValue.number) ?? .null, "location_org_stock_id": .int(locationOrgStockID), "client_request_id": .text(clientRequestID)]
        case let .damaged(_, quantity, clientRequestID), let .notReturned(_, quantity, clientRequestID):
            return ["quantity": quantity.map(GoodsInJSONValue.number) ?? .null, "client_request_id": .text(clientRequestID)]
        case .undoPut:
            return nil
        }
    }
}

enum GoodsInJSONValue: Hashable, Sendable, Encodable {
    case text(String)
    case number(Double)
    case int(Int)
    case bool(Bool)
    case null

    static func text(_ value: String?) -> GoodsInJSONValue {
        guard let value = value?.trimmingCharacters(in: .whitespacesAndNewlines), !value.isEmpty else { return .null }
        return .text(value)
    }

    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()
        switch self {
        case .text(let value): try container.encode(value)
        case .number(let value):
            if value == value.rounded(), abs(value) < 1e15 {
                try container.encode(Int(value))
            } else {
                try container.encode((value * 10_000).rounded() / 10_000)
            }
        case .int(let value): try container.encode(value)
        case .bool(let value): try container.encode(value)
        case .null: try container.encodeNil()
        }
    }
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

    static func mobileStockDeliveryWrite(warehouseID: Int, id: Int, write: GoodsInDeliveryWrite) -> Endpoint {
        goodsInJSON("mobile/warehouses/\(warehouseID)/stock-deliveries/\(id)/\(write.path)", method: .post, body: write.body)
    }

    static func mobileReturns(warehouseID: Int, query: MobileReturnsQuery) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/returns", queryItems: query.queryItems)
    }

    static func mobileReturn(warehouseID: Int, id: Int) -> Endpoint {
        Endpoint(path: "mobile/warehouses/\(warehouseID)/returns/\(id)")
    }

    static func mobileReturnWrite(warehouseID: Int, id: Int, write: GoodsInReturnWrite) -> Endpoint {
        let path = "mobile/warehouses/\(warehouseID)/returns/\(id)/\(write.path)"
        guard let body = write.body else { return Endpoint(path: path, method: write.method) }
        return goodsInJSON(path, method: write.method, body: body)
    }

    private static func goodsInJSON(_ path: String, method: HTTPMethod, body: [String: GoodsInJSONValue]) -> Endpoint {
        let encoder = JSONEncoder()
        encoder.outputFormatting = .sortedKeys
        return Endpoint(path: path, method: method, body: try? encoder.encode(body), contentType: "application/json")
    }
}

extension APIClient {
    func fetchGoodsInHub(warehouseID: Int) async throws -> [MobileGoodsInHubEntry] {
        try await send(.mobileGoodsInHub(warehouseID: warehouseID), as: MobileGoodsInHub.self).data
    }

    func fetchStockDeliveries(warehouseID: Int, query: MobileStockDeliveriesQuery) async throws -> MobileStockDeliveriesPage {
        try await send(.mobileStockDeliveries(warehouseID: warehouseID, query: query), as: MobileStockDeliveriesPage.self)
    }

    func fetchStockDelivery(warehouseID: Int, id: Int) async throws -> MobileStockDelivery {
        try await send(.mobileStockDelivery(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileStockDelivery>.self).value
    }

    func writeStockDelivery(warehouseID: Int, id: Int, write: GoodsInDeliveryWrite) async throws -> MobileStockDeliveryWriteResult {
        try GoodsInDecoding.decode(MobileStockDeliveryWriteResult.self, from: try await sendForm(.mobileStockDeliveryWrite(warehouseID: warehouseID, id: id, write: write)))
    }

    func fetchReturns(warehouseID: Int, query: MobileReturnsQuery) async throws -> MobileReturnsPage {
        try await send(.mobileReturns(warehouseID: warehouseID, query: query), as: MobileReturnsPage.self)
    }

    func fetchReturn(warehouseID: Int, id: Int) async throws -> MobileReturn {
        try await send(.mobileReturn(warehouseID: warehouseID, id: id), as: DataEnvelope<MobileReturn>.self).value
    }

    func writeReturn(warehouseID: Int, id: Int, write: GoodsInReturnWrite) async throws -> MobileReturnWriteResult {
        try GoodsInDecoding.decode(MobileReturnWriteResult.self, from: try await sendForm(.mobileReturnWrite(warehouseID: warehouseID, id: id, write: write)))
    }
}

enum GoodsInDecoding {
    static func decode<Value: Decodable>(_ type: Value.Type, from data: Data) throws -> Value {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        do {
            return try decoder.decode(type, from: data)
        } catch {
            throw APIError.unexpectedBody
        }
    }
}
