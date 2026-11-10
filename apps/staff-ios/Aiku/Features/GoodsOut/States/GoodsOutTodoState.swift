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
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void

    var body: some View {
        AikuButton("Start picking") {
            present(.trolley(startsPicking: true))
        }
        .accessibilityIdentifier("goodsOut.startPicking")
        GoodsOutFooterCaption("Makes you the picker and starts picking")
    }
}
