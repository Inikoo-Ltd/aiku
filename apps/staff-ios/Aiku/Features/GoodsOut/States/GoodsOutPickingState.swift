import SwiftUI

struct GoodsOutPickingStateView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        GoodsOutStateStepper(state: note.state)
        Card {
            ListRow(pickerTitle, subtitle: "Picker", icon: .person) {
                present(.picker)
            } trailing: {
                changeLabel
            }
            RowDivider()
            ListRow(note.trolley.map { "Trolley \($0)" } ?? "No trolley", subtitle: "Picking into", icon: .trolley) {
                present(.trolley(startsPicking: false))
            } trailing: {
                changeLabel
            }
        }
        GoodsOutNoteOverview(
            note: note,
            onQuickAction: { GoodsOutMoreActions.perform(.quick($0), note: note, router: router, toasts: toasts, present: present) },
            onEditAddress: { GoodsOutMoreActions.perform(.editAddress, note: note, router: router, toasts: toasts, present: present) }
        )
        if store.rights.canScanToPick, let next = note.nextLineToPick {
            GoodsOutScanPromptCard(
                title: "Scan to pick",
                location: next.pickLocation,
                detail: "\(next.sko) · \(next.remaining) left on this item"
            ) {
                router.open(GoodsOutRoute.scanToPick(reference: note.reference))
            }
            .accessibilityIdentifier("goodsOut.scanToPick")
        }
        if !note.allLinesHandled {
            Card {
                ListRow("Fast picking", subtitle: "Swipe through the lines in pick route order", icon: .swipe, showsChevron: true) {
                    router.open(GoodsOutRoute.fastPicking(reference: note.reference))
                }
            }
            .accessibilityIdentifier("goodsOut.fastPicking")
        }
        GoodsOutSectionTitle(title: "Items by location", detail: note.allLinesHandled ? "every line handled" : note.pickedProgress)
        GoodsOutLinesCard(lines: note.linesByLocation, showsLocationStock: true, onTap: { line in
            present(.itemActions(line: line.id))
        })
        HelperText(note.allLinesHandled
            ? "Set as picked only appears once every line is picked, not picked or waiting. Dropshipping notes skip packing, so they show Set as packed here instead."
            : "Scan to pick shows only when the organisation allows it. Tap a line for Pick all, another location, Not picked or Set as waiting.")
    }

    private var pickerTitle: String {
        guard let picker = note.picker else { return "No picker" }
        return picker == store.currentUser ? "\(picker) (you)" : picker
    }

    private var changeLabel: some View {
        Text("Change")
            .font(.aiku(14, .semibold))
            .foregroundStyle(AikuColor.link)
    }
}

struct GoodsOutPickingFooter: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared

    var body: some View {
        if note.allLinesHandled {
            AikuButton(note.finishPickingTitle) {
                if note.channel.skipsPacking, !note.hasWaitingLines {
                    let previous = store.setAsPicked(note.reference, bay: nil)
                    GoodsOutFeedback.done("\(note.reference) set as packed", previous: previous, toasts: toasts)
                } else {
                    present(.pickedBay(setsPicked: true))
                }
            }
            .accessibilityIdentifier("goodsOut.setAsPicked")
        } else {
            let count = note.linesStillToHandle
            GoodsOutFooterStatus(
                title: "\(count) \(count == 1 ? "line" : "lines") still to handle",
                detail: count == 1
                    ? "Pick it, mark it not picked or set it as waiting. Then Set as picked appears."
                    : "Pick them, mark them not picked or set them as waiting. Then Set as picked appears."
            )
        }
    }
}
