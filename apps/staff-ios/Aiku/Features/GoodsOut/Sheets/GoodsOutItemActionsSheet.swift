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

    private var canEdit: Bool { note.canWorkLines }
    private var settings: GoodsOutPickingSettings? { note.remote?.settings }
    private var allowsNotPicked: Bool { settings?.allowPickerSetNotPicked ?? true }
    private var allowsWaiting: Bool { settings?.allowWaiting ?? true }
    private var canPickElsewhere: Bool {
        guard note.isRemote else { return true }
        return line.stockLocations.count > 1
    }
    private var isWorking: Bool { store.isWorking(on: note.reference) }

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
            if let pickerNote = line.noteToPickers {
                InlineAlert(tone: .info, title: "Note to pickers", message: pickerNote)
            }
            if canEdit, line.remaining > 0 {
                pickSection
                if allowsNotPicked {
                    notPickedSection
                }
                if allowsWaiting {
                    waitingSection
                }
            }
            if canEdit, line.picked > 0 || line.outcome != nil, !note.isRemote || !line.pickings.isEmpty {
                AikuButton("Clear this line", icon: .undo, kind: .tertiary, isLoading: isWorking) {
                    clearLine()
                }
            }
        } footer: {
            AikuButton("Close", kind: .tertiary, action: close)
        }
    }

    @ViewBuilder
    private var pickSection: some View {
        AikuButton("Pick all \(line.remaining)", icon: .check, isLoading: isWorking) {
            if let lineID = line.apiID {
                GoodsOutRun.action(.pickAll(line: lineID, locationOrgStockID: line.defaultStockLocation?.locationOrgStockID), on: note.reference, fallback: "\(line.sko) picked ×\(line.remaining)", toasts: toasts) { close() }
            } else {
                let previous = store.pickAll(note.reference, line: line.id)
                close()
                GoodsOutFeedback.done("\(line.sko) picked ×\(line.remaining)", previous: previous, toasts: toasts)
            }
        }
        .accessibilityIdentifier("goodsOut.pickAll")
        if canPickElsewhere {
            AikuButton("Pick from another location", icon: .locations, kind: .tertiary) {
                present(.pickLocation(line: line.id))
            }
        }
        if line.remaining > 1 {
            HStack(spacing: 8) {
                QuantityStepper(value: $amount, range: 1...max(1, line.remaining - 1))
                AikuButton("Pick \(amount)", kind: .tertiary, size: .regular, fullWidth: false) {
                    pick(amount)
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
        AikuButton("Mark \(line.remaining) as not picked", kind: .danger, isLoading: isWorking) {
            if let lineID = line.apiID {
                let action = GoodsOutNoteAction.notPicked(line: lineID, quantity: nil, reason: GoodsOutMapper.reasonKey(reason), note: nil)
                GoodsOutRun.action(action, on: note.reference, fallback: "\(line.sko) not picked · \(reason.title.lowercased())", toasts: toasts) { close() }
            } else {
                let previous = store.markNotPicked(note.reference, line: line.id, reason: reason)
                close()
                GoodsOutFeedback.done("\(line.sko) not picked · \(reason.title.lowercased())", tone: .warn, previous: previous, toasts: toasts)
            }
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

    private func pick(_ quantity: Int) {
        guard let lineID = line.apiID else {
            let previous = store.pick(note.reference, line: line.id, quantity: quantity)
            close()
            GoodsOutFeedback.done("\(line.sko) picked ×\(quantity)", previous: previous, toasts: toasts)
            return
        }
        guard let location = line.defaultStockLocation?.locationOrgStockID else {
            toasts.show("No location of \(line.sko) holds stock", tone: .warn)
            return
        }
        GoodsOutRun.action(.pick(line: lineID, locationOrgStockID: location, quantity: Double(quantity)), on: note.reference, fallback: "\(line.sko) picked ×\(quantity)", toasts: toasts) { close() }
    }

    private func clearLine() {
        guard let lineID = line.apiID else {
            let previous = store.resetLine(note.reference, line: line.id)
            close()
            GoodsOutFeedback.done("\(line.sko) is back to pick", tone: .info, previous: previous, toasts: toasts)
            return
        }
        let actions = line.pickings.reversed().map { GoodsOutNoteAction.undoPicking(line: lineID, pickingID: $0.id) }
        GoodsOutRun.run(actions, on: note.reference, fallback: "\(line.sko) is back to pick", toasts: toasts) { close() }
    }
}
