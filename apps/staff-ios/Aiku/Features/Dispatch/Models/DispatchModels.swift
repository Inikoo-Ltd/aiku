import Foundation

enum DispatchProblemKind: String, CaseIterable, Hashable, Sendable {
    case unknownBarcode
    case wrongStock
    case dataProblem
    case appError
    case other

    @MainActor
    var title: String {
        switch self {
        case .unknownBarcode: L10n.text("Unknown barcode")
        case .wrongStock: L10n.text("Wrong stock")
        case .dataProblem: L10n.text("Data problem")
        case .appError: L10n.text("App error")
        case .other: L10n.text("Other")
        }
    }
}

struct DispatchProblemReport: Hashable, Sendable {
    let kind: DispatchProblemKind
    let note: String
    let hasPhoto: Bool
    let context: String
}
