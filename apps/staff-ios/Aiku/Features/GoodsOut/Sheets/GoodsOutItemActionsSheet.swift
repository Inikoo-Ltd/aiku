import SwiftUI

struct GoodsOutItemActionsSheet: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared
    @State private var reason: GoodsOutNotPickedReason = .outOfStock
    @State private var amount = 1

    private var canEdit: Bool { note.state == .picking }

    var body: some View {
        SheetScaffold(title: "\(line.sko) · \(line.picked) of \(line.required) picked", subtitle: "\(line.pickLocation) · \(line.name)") {
            HStack(spacing: 16) {
                LinkButton("Open \(line.sko)", size: 13) {
                    close()
                    router.open(InventoryRoute.sko(reference: line.sko))
                }
                LinkButton("Open \(line.pickLocation)", size: 13) {
                    close()
                    router.open(LocationsRoute.location(code: line.pickLocation))
                }
            }
            if let chip = line.status.chip {
                StatusChip(chip, style: .pill)
            }
            if canEdit, line.remaining > 0 {
                pickSection
                notPickedSection
                waitingSection
            }
            if canEdit, line.picked > 0 || line.outcome != nil {
                AikuButton("Clear this line", icon: .undo, kind: .tertiary) {
                    let previous = store.resetLine(note.reference, line: line.id)
                    close()
                    GoodsOutFeedback.done("\(line.sko) is back to pick", tone: .info, previous: previous, toasts: toasts)
                }
            }
        } footer: {
            AikuButton("Close", kind: .tertiary, action: close)
        }
    }

    @ViewBuilder
    private var pickSection: some View {
        AikuButton("Pick all \(line.remaining)", icon: .check) {
            let previous = store.pickAll(note.reference, line: line.id)
            close()
            GoodsOutFeedback.done("\(line.sko) picked ×\(line.remaining)", previous: previous, toasts: toasts)
        }
        .accessibilityIdentifier("goodsOut.pickAll")
        AikuButton("Pick from another location", icon: .locations, kind: .tertiary) {
            present(.pickLocation(line: line.id))
        }
        if line.remaining > 1 {
            HStack(spacing: 8) {
                QuantityStepper(value: $amount, range: 1...max(1, line.remaining - 1))
                AikuButton("Pick \(amount)", kind: .tertiary, size: .regular, fullWidth: false) {
                    let previous = store.pick(note.reference, line: line.id, quantity: amount)
                    close()
                    GoodsOutFeedback.done("\(line.sko) picked ×\(amount)", previous: previous, toasts: toasts)
                }
            }
        }
    }

    @ViewBuilder
    private var notPickedSection: some View {
        SheetSectionLabel("Not picked · reason")
        ForEach(GoodsOutNotPickedReason.allCases, id: \.self) { option in
            RadioCard(option.title, isSelected: reason == option) {
                reason = option
            }
        }
        AikuButton("Mark \(line.remaining) as not picked", kind: .danger) {
            let previous = store.markNotPicked(note.reference, line: line.id, reason: reason)
            close()
            GoodsOutFeedback.done("\(line.sko) not picked · \(reason.title.lowercased())", tone: .warn, previous: previous, toasts: toasts)
        }
        .accessibilityIdentifier("goodsOut.markNotPicked")
    }

    @ViewBuilder
    private var waitingSection: some View {
        SheetSectionLabel("Can’t finish this line now?")
        HStack(spacing: 8) {
            AikuButton("Set as waiting", icon: .pause, kind: .tertiary) {
                present(.blockReason(line: line.id, reason: .warehouse))
            }
            AikuButton("Pass to CS", kind: .tertiary) {
                present(.blockReason(line: line.id, reason: .customerService))
            }
        }
    }
}
