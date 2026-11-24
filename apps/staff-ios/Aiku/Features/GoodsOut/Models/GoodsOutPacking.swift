import Foundation

enum GoodsOutWeight {
    static func text(_ kilograms: Double) -> String {
        Measurement(value: kilograms, unit: UnitMass.kilograms).formatted(
            .measurement(width: .abbreviated, usage: .asProvided, numberFormatStyle: .number.precision(.fractionLength(1)))
                .locale(L10n.formatLocale)
        )
    }

    static func rounded(_ kilograms: Double) -> Double {
        (kilograms * 10).rounded() / 10
    }
}

struct GoodsOutAddress: Hashable, Sendable {
    var company: String
    var street: String
    var town: String
    var postcode: String
    var country: String

    static let sample = GoodsOutAddress(company: "[Company]", street: "[Street]", town: "[Town]", postcode: "[Postcode]", country: "United Kingdom")

    var singleLine: String {
        [street, "\(town) \(postcode)".trimmingCharacters(in: .whitespaces), country]
            .filter { !$0.isEmpty }
            .joined(separator: ", ")
    }

    var countryCode: String {
        switch country.lowercased() {
        case "united kingdom", "uk", "gb", "great britain": "GB"
        case "slovakia", "sk": "SK"
        case "spain", "es": "ES"
        default: country
        }
    }

    func labelLine(customer: String) -> String {
        [customer, street, "\(town) \(postcode)".trimmingCharacters(in: .whitespaces), countryCode]
            .filter { !$0.isEmpty }
            .joined(separator: ", ")
    }
}

struct GoodsOutParcel: Identifiable, Hashable, Sendable {
    let number: Int
    var weightKg: Double
    var length: Int?
    var width: Int?
    var height: Int?
    var boxes: Int?

    var id: Int { number }
    @MainActor
    var title: String { L10n.format("Parcel %lld", number) }

    var hasDimensions: Bool {
        [length, width, height].allSatisfy { ($0 ?? 0) > 0 }
    }

    var dimensionsText: String? {
        guard hasDimensions, let length, let width, let height else { return nil }
        return "\(length) × \(width) × \(height) cm"
    }

    var weightText: String { GoodsOutWeight.text(weightKg) }

    @MainActor
    var summary: String {
        var parts = [weightText, dimensionsText ?? L10n.text("dimensions missing")]
        if let boxes {
            parts.append(L10n.format("%lld boxes", boxes))
        }
        return parts.joined(separator: " · ")
    }
}

enum GoodsOutBox: String, CaseIterable, Hashable, Sendable {
    case envelope
    case small
    case medium
    case large

    static let largeBoxMaxKg = 10.0

    @MainActor
    var title: String {
        switch self {
        case .envelope: L10n.text("Envelope")
        case .small: L10n.text("Box S")
        case .medium: L10n.text("Box M")
        case .large: L10n.text("Box L")
        }
    }

    var dimensions: (length: Int, width: Int, height: Int) {
        switch self {
        case .envelope: (25, 18, 3)
        case .small: (25, 20, 10)
        case .medium: (40, 30, 20)
        case .large: (60, 40, 40)
        }
    }

    static func suggestion(forWeight kilograms: Double) -> GoodsOutBoxSuggestion {
        switch kilograms {
        case ..<0.51: GoodsOutBoxSuggestion(box: .envelope, count: 1)
        case ..<2.01: GoodsOutBoxSuggestion(box: .small, count: 1)
        case ..<8.01: GoodsOutBoxSuggestion(box: .medium, count: 1)
        default: GoodsOutBoxSuggestion(box: .large, count: max(1, Int((kilograms / largeBoxMaxKg).rounded(.up))))
        }
    }
}

struct GoodsOutBoxSuggestion: Hashable, Sendable {
    let box: GoodsOutBox
    let count: Int

    @MainActor
    var title: String { count > 1 ? "\(count) × \(box.title)" : box.title }
}

enum GoodsOutShipmentSource: Hashable, Sendable {
    case api
    case manual
    case platform
}

struct GoodsOutShipment: Identifiable, Hashable, Sendable {
    let id: Int
    var shipper: String
    var tracking: String
    var parcelCount: Int
    var cost: String?
    var source: GoodsOutShipmentSource
    var labelPrinted = false
    var trackingNumbers: [String] = []
    var trackingURLs: [String?] = []
    var isPrintable = false
    var label: GoodsOutShipmentLabel = .none
    var canDelete = true
    var isRemote = false

    func trackingURL(at index: Int) -> URL? {
        guard trackingURLs.indices.contains(index), let text = trackingURLs[index], !text.isEmpty else { return nil }
        return URL(string: text)
    }

    var chip: Chip {
        switch source {
        case .manual: Chip("Tracking added", .info)
        case .api, .platform: labelPrinted ? Chip("Label printed", .success) : Chip("Label queued for printing", .success)
        }
    }

    @MainActor
    var subtitle: String {
        var parts = [L10n.format("Tracking %@", tracking), L10n.format("%lld parcels", parcelCount)]
        if let cost {
            parts.append(L10n.format("shipment cost %@", cost))
        }
        return parts.joined(separator: " · ")
    }
}

struct GoodsOutShipperOption: Identifiable, Hashable, Sendable {
    let id: String
    let title: String
    let detail: String
    let hasAPI: Bool
}

struct GoodsOutLeaflet: Identifiable, Hashable, Sendable {
    let id: String
    var isPrinted = false
    var apiID: Int?
    var name: String?
    var copies = 1
    var stateLabel: String?
    var canOpenPdf = false

    @MainActor
    var title: String { name ?? L10n.format("Leaflet · %@", id) }
    var chip: Chip { Chip(stateLabel ?? (isPrinted ? "Printed" : "Not printed"), isPrinted ? .success : .warn) }
    @MainActor
    var copiesText: String { L10n.format("%lld copies · goes in the parcel", copies) }
}

enum GoodsOutShipmentLabel: Hashable, Sendable {
    case none
    case download
    case external(URL)
}

struct GoodsOutLineBox: Hashable, Sendable {
    var box: Int
    var quantity: Double
}

struct GoodsOutLinePacking: Hashable, Sendable {
    var quantityPicked: Double = 0
    var quantityPacked: Double = 0
    var quantityToPack: Double = 0
    var pickedLabel: String?
    var packedLabel: String?
    var toPackLabel: String?
    var isDonePacking = false
    var boxes: [GoodsOutLineBox] = []
    var noteToPackers: String?

    static func text(_ quantity: Double, label: String? = nil) -> String {
        if let label, !label.isEmpty { return label }
        if quantity.rounded() == quantity { return String(Int(quantity)) }
        return quantity.formatted(.number.precision(.fractionLength(0...3)).locale(Locale(identifier: "en_GB")))
    }

    var packedText: String { Self.text(quantityPacked, label: packedLabel) }
    var pickedText: String { Self.text(quantityPicked, label: pickedLabel) }
    var toPackText: String { Self.text(quantityToPack, label: toPackLabel) }
}

struct GoodsOutShipperDirective: Hashable, Sendable {
    var lockedShipperID: Int?
    var preferredShipperID: Int?
    var lockedBy: String?
    var canOverrideLock = false
    var trackingChannel: String?

    var chosenShipperID: Int? { lockedShipperID ?? preferredShipperID }
    var isLockedByCustomer: Bool { lockedBy == "customer" }
}

struct GoodsOutShipperChoice: Identifiable, Hashable, Sendable {
    let id: Int
    let name: String
    let isAPI: Bool
    let needsTracking: Bool
    let isLockedOut: Bool
    var code = ""

    @MainActor
    var detail: String {
        if isAPI { return L10n.text("Creates the label and the tracking number") }
        return needsTracking ? L10n.text("No API · tracking number required") : L10n.text("No API · tracking number optional")
    }

    @MainActor
    var option: GoodsOutShipperOption {
        GoodsOutShipperOption(id: String(id), title: name, detail: detail, hasAPI: isAPI)
    }
}

struct GoodsOutShipperList: Hashable, Sendable {
    var shippers: [GoodsOutShipperChoice] = []
    var directive = GoodsOutShipperDirective()

    var available: [GoodsOutShipperChoice] { shippers.filter { !$0.isLockedOut } }
    var apiShippers: [GoodsOutShipperChoice] { available.filter(\.isAPI) }
    var manualShippers: [GoodsOutShipperChoice] { available.filter { !$0.isAPI } }

    @MainActor
    func badge(for shipper: GoodsOutShipperChoice) -> String? {
        if shipper.id == directive.lockedShipperID { return L10n.text("Locked") }
        if shipper.id == directive.preferredShipperID { return L10n.text("Preferred") }
        return nil
    }

    @MainActor
    var lockNotice: (title: String, message: String?)? {
        guard let lockedID = directive.lockedShipperID, let name = shippers.first(where: { $0.id == lockedID })?.name else { return nil }
        let title = directive.isLockedByCustomer ? L10n.format("The customer chose %@", name) : L10n.format("Shipping rules lock this delivery note to %@", name)
        return (title, directive.canOverrideLock ? L10n.text("You can still choose another shipper; you are asked to confirm.") : nil)
    }

    func defaultChoice() -> GoodsOutShipperChoice? {
        let shown = apiShippers + manualShippers
        return shown.first { $0.id == directive.chosenShipperID }
    }
}

struct GoodsOutNotePackingDetails: Hashable, Sendable {
    var packerID: Int?
    var packerName: String?
    var isEditable = false
    var parcelsEditable = false
    var hasBoxPackingList = false
    var numberBoxes: Int?
    var missingBoxesMessage: String?
    var packagingName: String?
    var insertsLabel: String?
    var canPrintAllInserts = false
    var showsInserts = false
    var canPrintLabels = false
    var directive = GoodsOutShipperDirective()
    var isShippingByExternal = false
    var externalEngine: String?
    var hasGiftMessage = false
    var address = GoodsOutAddressForm()
    var canEditContact = false
    var editableNotes: Set<String> = []
    var returnReference: String?
    var returnStateLabel: String?

    func canEdit(_ field: GoodsOutNoteField) -> Bool { editableNotes.contains(field.rawValue) }
}

struct GoodsOutPrinter: Identifiable, Hashable, Sendable {
    let id: String
    var detail: String?
}

enum GoodsOutEmailStatus: String, CaseIterable, Hashable, Sendable {
    case sent
    case delivered
    case opened
    case clicked
    case softBounce
    case bounced
    case spam

    var title: String {
        switch self {
        case .sent: "Sent"
        case .delivered: "Delivered"
        case .opened: "Opened"
        case .clicked: "Clicked"
        case .softBounce: "Soft bounce"
        case .bounced: "Bounced"
        case .spam: "Spam"
        }
    }

    var tone: Tone {
        switch self {
        case .sent: .neutral
        case .delivered: .info
        case .opened, .clicked: .success
        case .softBounce: .warn
        case .bounced, .spam: .danger
        }
    }
}

struct GoodsOutEmail: Identifiable, Hashable, Sendable {
    let id: Int
    let title: String
    let recipient: String
    let sentAt: Date
    var status: GoodsOutEmailStatus = .sent
    var orderReference: String?

    @MainActor
    var subtitle: String {
        var parts = [recipient, GoodsOutDateFormat.stamp(sentAt)]
        if let orderReference {
            parts.append(L10n.format("order %@", orderReference))
        }
        return parts.joined(separator: " · ")
    }
}

struct GoodsOutChatMessage: Identifiable, Hashable, Sendable {
    let id: Int
    let byline: String
    let sentAt: Date
    let text: String
    var isMine = false

    var meta: String { "\(byline) · \(GoodsOutDateFormat.shortTime(sentAt))" }
}

struct GoodsOutTask: Identifiable, Hashable, Sendable {
    let id: Int
    let title: String
    let assignee: String
    var isOpen = true

    var chip: Chip { isOpen ? Chip("Open", .info) : Chip("Done", .success) }
    @MainActor
    var subtitle: String { L10n.format("Linked to this delivery note · assigned to %@", assignee) }
}

struct GoodsOutSetPart: Identifiable, Hashable, Sendable {
    let sko: String
    let name: String
    let location: String
    var picked: Int
    var required = 1

    var id: String { sko }
    var wasPicked: Bool { picked > 0 }
    var chip: Chip { wasPicked ? Chip("Put back", .warn) : Chip("Not found", .danger) }
}

struct GoodsOutIncompleteSet: Hashable, Sendable {
    let name: String
    var parts: [GoodsOutSetPart]

    var partsToPutBack: [GoodsOutSetPart] { parts.filter(\.wasPicked) }
    var hasPicks: Bool { !partsToPutBack.isEmpty }
    var actionTitle: String { hasPicks ? "Parts put back" : "Mark set parts not picked" }

    @MainActor
    var instruction: String {
        guard hasPicks else { return L10n.text("Nothing was picked yet. Mark the set parts as not picked.") }
        let codes = partsToPutBack.map(\.sko).formatted(.list(type: .and).locale(Self.listLocale))
        return L10n.format("Put back %@ on the shelf, then press Parts put back.", codes)
    }

    @MainActor
    private static var listLocale: Locale {
        let language = LanguageStore.shared
        return language.languageID == AppLanguages.defaultID ? Locale(identifier: "en_GB") : language.locale
    }

    @MainActor
    func detail(for part: GoodsOutSetPart) -> String {
        let index = (parts.firstIndex(of: part) ?? 0) + 1
        if part.wasPicked {
            return L10n.format("%@ · part %lld of %lld · to %@", part.name, index, parts.count, part.location)
        }
        return L10n.format("%@ · part %lld of %lld", part.name, index, parts.count)
    }
}

struct GoodsOutTradeUnit: Hashable, Sendable {
    var tariff: String?
    var origin: String?
    var isDangerous = false

    var isComplete: Bool { tariff != nil && origin != nil }
}

enum GoodsOutDescriptionLanguage: String, CaseIterable, Hashable, Sendable {
    case en
    case es
    case sk

    var title: String { rawValue.uppercased() }

    func describe(_ name: String) -> String {
        self == .en ? name : "[\(title)] \(name)"
    }
}

struct GoodsOutCustomsRow: Identifiable, Hashable, Sendable {
    let row: Int
    let sko: String
    let name: String
    var tariff: String?
    var origin: String?
    let quantity: Int
    var isDangerous = false

    var id: String { sko }
    var isMissing: Bool { tariff == nil || origin == nil }

    @MainActor
    var tradeUnitText: String {
        let tariffText = tariff.map { L10n.format("Tariff %@", $0) } ?? L10n.text("no tariff code")
        let originText = origin.map { L10n.format("Origin %@", $0) } ?? L10n.text("no country of origin")
        return isMissing ? "\(tariffText) · \(originText)" : "\(tariffText) · \(originText) · \(name)"
    }
}

enum GoodsOutCustoms {
    static func rows(for note: GoodsOutDeliveryNote, tradeUnits: [String: GoodsOutTradeUnit], language: GoodsOutDescriptionLanguage = .en) -> [GoodsOutCustomsRow] {
        let lines = note.lines.filter { $0.picked > 0 || note.state == .todo || note.state == .picking }
        return lines.enumerated().map { index, line in
            let unit = tradeUnits[line.sko] ?? GoodsOutTradeUnit()
            return GoodsOutCustomsRow(
                row: index + 1,
                sko: line.sko,
                name: language.describe(line.name),
                tariff: unit.tariff,
                origin: unit.origin,
                quantity: line.picked > 0 ? line.picked : line.required,
                isDangerous: unit.isDangerous
            )
        }
    }

    static func missingCodes(_ rows: [GoodsOutCustomsRow]) -> [String] {
        rows.filter(\.isMissing).map(\.sko)
    }
}

enum GoodsOutPackScanOutcome: Hashable, Sendable {
    case packed(sko: String, left: Int, parcel: Int)
    case allPacked
    case notOnNote(code: String)
    case notPacking
}

extension GoodsOutLine {
    var packed: Int {
        if let packing { return Int(packing.quantityPacked.rounded()) }
        return packedInto.values.reduce(0, +)
    }

    var packRemaining: Int {
        if let packing { return Int(max(0, packing.quantityToPack).rounded(.up)) }
        return max(0, picked - packed)
    }

    var isPackable: Bool { picked > 0 }

    var isFullyPacked: Bool {
        if let packing { return isPackable && (packing.isDonePacking || packing.quantityToPack <= 0) }
        return isPackable && packRemaining == 0
    }

    var parcelNumbers: [Int] { packedInto.filter { $0.value > 0 }.keys.sorted() }

    @MainActor
    var parcelText: String {
        if let packing {
            let boxes = packing.boxes.filter { $0.quantity > 0 }.map(\.box).sorted()
            if boxes.isEmpty { return L10n.format("%@ of %@ packed", packing.packedText, packing.pickedText) }
            let joined = boxes.map(String.init).joined(separator: ", ")
            return boxes.count == 1 ? L10n.format("Box %@", joined) : L10n.format("Boxes %@", joined)
        }
        let numbers = parcelNumbers
        guard let first = numbers.first else { return L10n.text("no parcel yet") }
        if numbers.count == 1 { return L10n.format("Parcel %lld", first) }
        return L10n.format("Parcels %@", numbers.map(String.init).joined(separator: ", "))
    }

    @discardableResult
    mutating func pack(_ quantity: Int, into parcel: Int) -> Int {
        let amount = min(max(0, quantity), packRemaining)
        guard amount > 0 else { return 0 }
        packedInto[parcel, default: 0] += amount
        return amount
    }
}
