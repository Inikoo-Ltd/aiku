import Foundation

enum GoodsOutWeight {
    static func text(_ kilograms: Double) -> String {
        kilograms.formatted(.number.precision(.fractionLength(1)).locale(Locale(identifier: "en_GB"))) + " kg"
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

    var id: Int { number }
    var title: String { "Parcel \(number)" }

    var hasDimensions: Bool {
        [length, width, height].allSatisfy { ($0 ?? 0) > 0 }
    }

    var dimensionsText: String? {
        guard hasDimensions, let length, let width, let height else { return nil }
        return "\(length) × \(width) × \(height) cm"
    }

    var weightText: String { GoodsOutWeight.text(weightKg) }

    var summary: String { "\(weightText) · \(dimensionsText ?? "dimensions missing")" }
}

enum GoodsOutBox: String, CaseIterable, Hashable, Sendable {
    case envelope
    case small
    case medium
    case large

    static let largeBoxMaxKg = 10.0

    var title: String {
        switch self {
        case .envelope: "Envelope"
        case .small: "Box S"
        case .medium: "Box M"
        case .large: "Box L"
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

    var chip: Chip {
        switch source {
        case .manual: Chip("Tracking added", .info)
        case .api, .platform: labelPrinted ? Chip("Label printed", .success) : Chip("Label queued for printing", .success)
        }
    }

    var subtitle: String {
        var parts = ["Tracking \(tracking)", "\(parcelCount) \(parcelCount == 1 ? "parcel" : "parcels")"]
        if let cost {
            parts.append("shipment cost \(cost)")
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

    var title: String { "Leaflet · \(id)" }
    var chip: Chip { isPrinted ? Chip("Printed", .success) : Chip("Not printed", .warn) }
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

    var subtitle: String {
        var parts = [recipient, GoodsOutDateFormat.stamp(sentAt)]
        if let orderReference {
            parts.append("order \(orderReference)")
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
    var subtitle: String { "Linked to this delivery note · assigned to \(assignee)" }
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

    var instruction: String {
        guard hasPicks else { return "Nothing was picked yet. Mark the set parts as not picked." }
        let codes = partsToPutBack.map(\.sko).formatted(.list(type: .and).locale(Locale(identifier: "en_GB")))
        return "Put back \(codes) on the shelf, then press Parts put back."
    }

    func detail(for part: GoodsOutSetPart) -> String {
        let index = (parts.firstIndex(of: part) ?? 0) + 1
        var text = "\(part.name) · part \(index) of \(parts.count)"
        if part.wasPicked {
            text += " · to \(part.location)"
        }
        return text
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

    var tradeUnitText: String {
        let tariffText = tariff.map { "Tariff \($0)" } ?? "no tariff code"
        let originText = origin.map { "Origin \($0)" } ?? "no country of origin"
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
    var packed: Int { packedInto.values.reduce(0, +) }
    var packRemaining: Int { max(0, picked - packed) }
    var isPackable: Bool { picked > 0 }
    var isFullyPacked: Bool { isPackable && packRemaining == 0 }
    var parcelNumbers: [Int] { packedInto.filter { $0.value > 0 }.keys.sorted() }

    var parcelText: String {
        let numbers = parcelNumbers
        guard let first = numbers.first else { return "no parcel yet" }
        if numbers.count == 1 { return "Parcel \(first)" }
        return "Parcels " + numbers.map(String.init).joined(separator: ", ")
    }

    @discardableResult
    mutating func pack(_ quantity: Int, into parcel: Int) -> Int {
        let amount = min(max(0, quantity), packRemaining)
        guard amount > 0 else { return 0 }
        packedInto[parcel, default: 0] += amount
        return amount
    }
}
