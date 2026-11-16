import SwiftUI

struct GoodsOutTodoStateView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        GoodsOutNoteOverview(
            note: note,
            onQuickAction: { GoodsOutMoreActions.perform(.quick($0), note: note, router: router, toasts: toasts, present: present) },
            onEditAddress: { GoodsOutMoreActions.perform(.editAddress, note: note, router: router, toasts: toasts, present: present) }
        )
        GoodsOutSectionTitle(title: "Items by location")
        GoodsOutLinesCard(lines: note.linesByLocation)
    }
}

struct GoodsOutTodoFooter: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        if note.isRemote {
            remoteFooter
        } else {
            AikuButton("Start picking") {
                present(.trolley(startsPicking: true))
            }
            .accessibilityIdentifier("goodsOut.startPicking")
            GoodsOutFooterCaption("Makes you the picker and starts picking")
        }
    }

    @ViewBuilder
    private var remoteFooter: some View {
        if let action = note.action(.startPicking) {
            if let reason = action.disabledReason {
                GoodsOutFooterStatus(title: action.label, detail: reason)
            } else {
                AikuButton(action.label, isLoading: store.isWorking(on: note.reference)) {
                    if note.remote?.isDropshipping == true {
                        GoodsOutRun.action(.startPicking(trolleyID: nil), on: note.reference, fallback: "Picking started", toasts: toasts)
                    } else {
                        present(.trolley(startsPicking: true))
                    }
                }
                .accessibilityIdentifier("goodsOut.startPicking")
                GoodsOutFooterCaption("Makes you the picker and starts picking")
            }
        } else {
            GoodsOutFooterStatus(title: "You can’t start picking this delivery note", detail: note.remote?.readOnlyNotice ?? "It may be in a picking session or you can only view it.")
        }
    }
}
