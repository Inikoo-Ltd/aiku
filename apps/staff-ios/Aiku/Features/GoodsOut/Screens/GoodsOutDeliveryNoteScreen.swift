import SwiftUI

enum GoodsOutNoteSheet: Identifiable, Hashable {
    case moreActions
    case picker
    case trolley(startsPicking: Bool)
    case itemActions(line: GoodsOutLine.ID)
    case pickLocation(line: GoodsOutLine.ID)
    case setPickingLocation(line: GoodsOutLine.ID)
    case blockReason(line: GoodsOutLine.ID, reason: GoodsOutWaitingReason)
    case pickedBay(setsPicked: Bool)
    case unblock
    case undo(GoodsOutUndoStep)
    case packLine(line: GoodsOutLine.ID)
    case printDeliveryNote
    case editAddress
    case cancelNote

    var id: String { String(describing: self) }
}

struct GoodsOutDeliveryNoteScreen: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var activeSheet: GoodsOutNoteSheet?
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            screen(note)
                .aikuSheet(item: $activeSheet) { sheet in
                    GoodsOutNoteSheetView(sheet: sheet, reference: note.reference, present: present, close: { activeSheet = nil })
                }
        } else {
            AppScreen(title: reference) {
                EmptyState(icon: .deliveryNote, title: "Delivery note not found", message: "It may have been removed.")
            }
        }
    }

    private func present(_ sheet: GoodsOutNoteSheet) {
        activeSheet = sheet
    }

    private func kicker(_ note: GoodsOutDeliveryNote) -> String {
        if note.state == .todo {
            return "Delivery note · \(note.state.title)"
        }
        var parts = ["Delivery note", note.channel.title]
        if let platform = note.platform {
            parts.append(platform)
        }
        if note.shipper == .collection {
            parts.append(GoodsOutShipper.collection.title)
        }
        if note.state == .waiting, let bay = note.pickedBay {
            parts.append(bay)
        }
        return parts.joined(separator: " · ")
    }

    private func actions(_ note: GoodsOutDeliveryNote) -> [HeaderAction] {
        guard note.state != .cancelled else { return [] }
        return [HeaderAction(.more, label: "More actions") { GoodsOutMoreActions.present(present) }]
    }

    private func hasFooter(_ note: GoodsOutDeliveryNote) -> Bool {
        switch note.state {
        case .dispatched, .cancelled: false
        default: true
        }
    }

    @ViewBuilder
    private func screen(_ note: GoodsOutDeliveryNote) -> some View {
        if hasFooter(note) {
            AppScreen(title: note.reference, kicker: kicker(note), actions: actions(note)) {
                content(note)
            } footer: {
                footer(note)
            }
        } else {
            AppScreen(title: note.reference, kicker: kicker(note), actions: actions(note)) {
                content(note)
            }
        }
    }

    @ViewBuilder
    private func content(_ note: GoodsOutDeliveryNote) -> some View {
        switch note.state {
        case .todo:
            GoodsOutTodoStateView(note: note, present: present)
        case .picking:
            if note.needsTakeOver(by: store.currentUser) {
                GoodsOutTakeOverStateView(note: note)
            } else {
                GoodsOutPickingStateView(note: note, present: present)
            }
        case .picked:
            GoodsOutPickedStateView(note: note, present: present)
        case .waiting:
            if note.incompleteSet != nil {
                GoodsOutIncompleteSetStateView(note: note)
            } else {
                GoodsOutWaitingStateView(note: note, present: present)
            }
        case .cancelled:
            GoodsOutCancelledStateView(note: note)
        case .packing:
            GoodsOutPackingStateView(note: note, present: present)
        case .packed:
            GoodsOutPackedStateView(note: note, present: present)
        case .finalised:
            GoodsOutFinalisedStateView(note: note)
        case .dispatched:
            GoodsOutDispatchedStateView(note: note)
        }
    }

    @ViewBuilder
    private func footer(_ note: GoodsOutDeliveryNote) -> some View {
        switch note.state {
        case .todo:
            GoodsOutTodoFooter(note: note, present: present)
        case .picking:
            if note.needsTakeOver(by: store.currentUser) {
                GoodsOutTakeOverFooter(note: note)
            } else {
                GoodsOutPickingFooter(note: note, present: present)
            }
        case .picked:
            GoodsOutPickedFooter(note: note)
        case .waiting:
            if note.incompleteSet != nil {
                GoodsOutIncompleteSetFooter(note: note)
            } else {
                GoodsOutWaitingFooter(note: note, present: present)
            }
        case .packing:
            GoodsOutPackingFooter(note: note)
        case .packed:
            GoodsOutPackedFooter(note: note)
        case .finalised:
            GoodsOutFinalisedFooter(note: note)
        case .dispatched, .cancelled:
            EmptyView()
        }
    }
}

struct GoodsOutNoteSheetView: View {
    let sheet: GoodsOutNoteSheet
    let reference: String
    let present: (GoodsOutNoteSheet) -> Void
    let close: () -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            switch sheet {
            case .moreActions:
                GoodsOutMoreActionsSheet(note: note, present: present, close: close)
            case .picker:
                GoodsOutPickerSheet(note: note, close: close)
            case .trolley(let startsPicking):
                GoodsOutTrolleySheet(note: note, startsPicking: startsPicking, close: close)
            case .itemActions(let line):
                lineSheet(note, line) { GoodsOutItemActionsSheet(note: note, line: $0, present: present, close: close) }
            case .pickLocation(let line):
                lineSheet(note, line) { GoodsOutPickLocationSheet(note: note, line: $0, present: present, close: close) }
            case .setPickingLocation(let line):
                lineSheet(note, line) { GoodsOutSetPickingLocationSheet(note: note, line: $0, present: present) }
            case .blockReason(let line, let reason):
                lineSheet(note, line) { GoodsOutBlockReasonSheet(note: note, line: $0, initialReason: reason, present: present, close: close) }
            case .pickedBay(let setsPicked):
                GoodsOutPickedBaySheet(note: note, setsPicked: setsPicked, close: close)
            case .unblock:
                GoodsOutUnblockSheet(note: note, close: close)
            case .undo(let step):
                GoodsOutUndoSheet(note: note, step: step, present: present, close: close)
            case .packLine(let line):
                lineSheet(note, line) { GoodsOutPackLineSheet(note: note, line: $0, close: close) }
            case .printDeliveryNote:
                GoodsOutPrintSheet(note: note, close: close)
            case .editAddress:
                GoodsOutAddressSheet(note: note, close: close)
            case .cancelNote:
                GoodsOutCancelSheet(note: note, present: present, close: close)
            }
        }
    }

    @ViewBuilder
    private func lineSheet<Content: View>(_ note: GoodsOutDeliveryNote, _ id: GoodsOutLine.ID, @ViewBuilder content: (GoodsOutLine) -> Content) -> some View {
        if let line = note.line(id) {
            content(line)
        } else {
            SheetScaffold(title: "Line not found") {
                AikuButton("Close", kind: .tertiary, action: close)
            }
        }
    }
}
