import Foundation

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
