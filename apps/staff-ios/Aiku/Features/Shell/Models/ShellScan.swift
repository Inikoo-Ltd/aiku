import Foundation

enum ShellScanKind: String, Hashable, Sendable {
    case location
    case sko
    case deliveryNote
    case stockDelivery
    case pallet
    case unknown

    var article: String {
        switch self {
        case .location: "a location"
        case .sko: "a SKO"
        case .deliveryNote: "a delivery note"
        case .stockDelivery: "a stock delivery"
        case .pallet: "a pallet"
        case .unknown: "unknown"
        }
    }

    var icon: AppIcon {
        switch self {
        case .location: .locations
        case .sko: .sko
        case .deliveryNote: .deliveryNote
        case .stockDelivery: .stockDelivery
        case .pallet: .pallet
        case .unknown: .question
        }
    }

    var openTitle: String {
        switch self {
        case .location: "Open location"
        case .sko: "Open SKO"
        case .deliveryNote: "Open delivery note"
        case .stockDelivery: "Open stock delivery"
        case .pallet: "Open pallet"
        case .unknown: "Type code"
        }
    }

    var fallbackDetail: String {
        switch self {
        case .location: "Location"
        case .sko: "SKO"
        case .deliveryNote: "Delivery note"
        case .stockDelivery: "Stock delivery"
        case .pallet: "Pallet"
        case .unknown: ""
        }
    }
}

struct ShellScanAction: Identifiable, Hashable, Sendable {
    var id: String { title }
    let title: String
    let icon: AppIcon
    let link: ShellLink
}

struct ShellScanTarget: Hashable, Sendable {
    let code: String
    let kind: ShellScanKind
    let detail: String
    let link: ShellLink
    let actions: [ShellScanAction]
}

struct ShellScanCycle: Hashable, Sendable {
    static let sampleCodes = ["A-03-02", "SKO-00733", "AR24811", "SDaw-0093", "5060123456789"]

    private(set) var index = 0
    let codes: [String]

    init(codes: [String] = ShellScanCycle.sampleCodes) {
        self.codes = codes
    }

    mutating func next() -> String {
        let code = codes[index % codes.count]
        index = (index + 1) % codes.count
        return code
    }
}

enum ShellScanCatalog {
    static let capabilities: [(icon: AppIcon, text: String)] = [
        (.sko, "SKO barcode → SKO detail"),
        (.locations, "Location label → Location detail"),
        (.deliveryNote, "Delivery note → Delivery note detail"),
        (.stockDelivery, "Stock delivery → Stock delivery detail")
    ]

    private static let details: [String: String] = [
        "A-03-02": "Area A · 6 SKOs · Operational",
        "A-12-03": "Area A · 4 SKOs · Audit requested",
        "B-01-04": "Area B · 2 SKOs · Operational",
        "SKO-00733": "Bath bomb – rose 180g",
        "SKO-00412": "Lavender essential oil 10ml · 1,240 in stock",
        "AR24811": "To pick · 14 items · aisle A–C",
        "AR24790": "To pack · 6 items",
        "SDAW-0093": "Received · 6 items to check",
        "ACME-P0231": "Storing · [Customer A] · D-02-01"
    ]

    static func classify(_ rawCode: String) -> ShellScanKind {
        let code = rawCode.trimmingCharacters(in: .whitespacesAndNewlines).uppercased()
        if code.wholeMatch(of: /[A-Z]-\d{2}-\d{2}/) != nil { return .location }
        if code.wholeMatch(of: /SKO-\d{3,}/) != nil { return .sko }
        if code.wholeMatch(of: /AR\d{4,}/) != nil { return .deliveryNote }
        if code.wholeMatch(of: /SD[A-Z]*-\d{3,}/) != nil { return .stockDelivery }
        if code.wholeMatch(of: /[A-Z]+-P\d{3,}/) != nil { return .pallet }
        return .unknown
    }

    static func target(for rawCode: String) -> ShellScanTarget? {
        let code = rawCode.trimmingCharacters(in: .whitespacesAndNewlines)
        let kind = classify(code)
        let detail = details[code.uppercased()] ?? kind.fallbackDetail
        switch kind {
        case .location:
            let link = ShellLink.locations(.location(code: code.uppercased()))
            return ShellScanTarget(code: code.uppercased(), kind: kind, detail: detail, link: link, actions: [
                ShellScanAction(title: "Audit location", icon: .audit, link: link),
                ShellScanAction(title: "Move SKOs", icon: .transfer, link: link)
            ])
        case .sko:
            let link = ShellLink.inventory(.sko(reference: code.uppercased()))
            return ShellScanTarget(code: code.uppercased(), kind: kind, detail: detail, link: link, actions: [
                ShellScanAction(title: "Audit stock", icon: .audit, link: link),
                ShellScanAction(title: "Stock history", icon: .history, link: .inventory(.stockHistory))
            ])
        case .deliveryNote:
            return ShellScanTarget(code: code.uppercased(), kind: kind, detail: detail, link: .goodsOut(.deliveryNote(reference: code.uppercased())), actions: [])
        case .stockDelivery:
            return ShellScanTarget(code: code, kind: kind, detail: detail, link: .goodsIn(.stockDelivery(reference: code)), actions: [])
        case .pallet:
            return ShellScanTarget(code: code.lowercased(), kind: kind, detail: detail, link: .fulfilment(.pallet(reference: code.lowercased())), actions: [])
        case .unknown:
            return nil
        }
    }
}
