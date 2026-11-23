import Foundation

struct GoodsOutScanLogEntry: Identifiable, Hashable, Sendable {
    let id: UUID
    let code: String
    let time: Date
    let outcome: GoodsOutScanOutcome

    init(id: UUID = UUID(), code: String, time: Date, outcome: GoodsOutScanOutcome) {
        self.id = id
        self.code = code
        self.time = time
        self.outcome = outcome
    }

    var sko: String {
        switch outcome {
        case .picked(let sko, _), .lineComplete(let sko): sko
        case .notOnNote(let code): code
        case .notPicking: code
        }
    }

    var chip: Chip {
        switch outcome {
        case .picked: Chip("+1", .success)
        case .lineComplete: Chip("Already picked", .warn)
        case .notOnNote, .notPicking: Chip("Could not pick", .danger)
        }
    }

    @MainActor
    var detail: String {
        let stamp = GoodsOutDateFormat.time(time)
        switch outcome {
        case .picked: return L10n.format("%@ · It belongs to this order", stamp)
        case .lineComplete: return L10n.format("%@ · every unit of this line is picked", stamp)
        case .notOnNote: return L10n.format("%@ · not on this delivery note", stamp)
        case .notPicking: return L10n.format("%@ · the delivery note is not being picked", stamp)
        }
    }

    static func wrongItemCode(for note: GoodsOutDeliveryNote) -> String {
        let onNote = Set(note.lines.map(\.sko))
        return GoodsOutSampleData.products.keys.sorted().first { !onNote.contains($0) } ?? "SKO-00000"
    }
}
