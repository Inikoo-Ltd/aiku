import Foundation

struct DispatchShipper: Identifiable, Hashable, Sendable {
    let name: String
    let integration: String
    let shipmentsToday: Int

    var id: String { name }

    var subtitle: String {
        switch shipmentsToday {
        case 0: "No shipments today"
        case 1: "1 shipment today"
        default: "\(shipmentsToday) shipments today"
        }
    }

    var tone: Tone { shipmentsToday > 0 ? .info : .neutral }
}

enum DispatchProblemKind: String, CaseIterable, Hashable, Sendable {
    case unknownBarcode
    case wrongStock
    case dataProblem
    case appError
    case other

    var title: String {
        switch self {
        case .unknownBarcode: "Unknown barcode"
        case .wrongStock: "Wrong stock"
        case .dataProblem: "Data problem"
        case .appError: "App error"
        case .other: "Other"
        }
    }
}

struct DispatchProblemReport: Hashable, Sendable {
    let kind: DispatchProblemKind
    let note: String
    let hasPhoto: Bool
    let context: String
}
