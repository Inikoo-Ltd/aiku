import SwiftUI

struct GoodsOutWaitingStateView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        let waiting = note.waitingLines
        if !waiting.isEmpty {
            GoodsOutSectionTitle(title: "Waiting lines")
            GoodsOutLinesCard(lines: waiting, detail: { line in
                let quote = line.waitingNote.map { $0.isEmpty ? "" : " · “\($0)”" } ?? ""
                return "\(line.picked) of \(line.required)\(quote)"
            })
        }
        let others = note.linesByLocation.filter { !$0.isWaiting }
        if !others.isEmpty {
            GoodsOutSectionTitle(title: "Picked")
            GoodsOutLinesCard(lines: others, detail: { $0.name })
        }
        Card {
            ListRow("Ask CRM", subtitle: askCRMSubtitle, icon: .chat) {
                GoodsOutMoreActions.perform(.askCRM, note: note, router: router, toasts: toasts)
            }
            RowDivider()
            ListRow("Auto finish waiting", subtitle: "Check if all items are picked and finish waiting", icon: .check) {
                if let previous = store.autoFinishWaiting(note.reference) {
                    GoodsOutFeedback.done("Nothing is waiting any more · \(note.reference) set as picked", previous: previous, toasts: toasts)
                } else {
                    let count = note.waitingLines.count
                    toasts.show("\(count) \(count == 1 ? "line is" : "lines are") still waiting", tone: .warn)
                }
            }
        }
        HelperText("Only dispatching supervisors and org admins can send it back to picking. Resume picking (“nothing is waiting on this delivery note any more, carry on picking it”) appears once nothing is waiting.")
    }

    private var askCRMSubtitle: String {
        if let line = note.waitingLines.first(where: { if case .waiting(.customerService) = $0.status { return true } else { return false } }) {
            return "Messages about \(line.sko)"
        }
        return "Chat with customer services about this note"
    }
}

struct GoodsOutWaitingFooter: View {
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        if store.rights.isDispatchingSupervisor {
            AikuButton("Back to picking") {
                present(.unblock)
            }
            .accessibilityIdentifier("goodsOut.backToPicking")
            GoodsOutFooterCaption("Supervisor only")
        } else {
            let count = note.waitingLines.count
            GoodsOutFooterStatus(
                title: "\(count) \(count == 1 ? "line" : "lines") waiting",
                detail: "A dispatching supervisor can send it back to picking."
            )
        }
    }
}
