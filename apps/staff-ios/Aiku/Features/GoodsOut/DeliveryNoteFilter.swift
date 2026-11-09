import Foundation

struct DeliveryNoteFilter {
    var state: DeliveryNoteState = .unassigned
    var query = ""

    func apply(to notes: [DeliveryNote]) -> [DeliveryNote] {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines)
        return notes
            .filter { $0.state == state }
            .filter { trimmed.isEmpty || $0.reference.localizedCaseInsensitiveContains(trimmed) || $0.customer.localizedCaseInsensitiveContains(trimmed) }
            .sorted { $0.reference < $1.reference }
    }
}
