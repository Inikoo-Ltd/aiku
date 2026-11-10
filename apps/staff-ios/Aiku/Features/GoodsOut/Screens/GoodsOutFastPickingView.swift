import SwiftUI

struct GoodsOutFastPickingView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var open: GoodsOutSwipeOpen?
    @State private var activeSheet: GoodsOutNoteSheet?
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            ScreenScaffold(showsTabBar: false, spacing: 12) {
                header(note)
            } content: {
                if note.state == .picking {
                    list(note)
                } else {
                    EmptyState(
                        icon: .deliveryNote,
                        title: "\(note.reference) is \(note.state.title.lowercased())",
                        message: "Fast picking works while a delivery note is being picked.",
                        actionTitle: "Open delivery note"
                    ) {
                        router.replace(with: GoodsOutRoute.deliveryNote(reference: note.reference))
                    }
                }
            } footer: {
                footer(note)
            }
            .aikuSheet(item: $activeSheet) { sheet in
                if case .pickedBay = sheet {
                    GoodsOutPickedBaySheet(note: note, setsPicked: true, close: { activeSheet = nil }) { updated in
                        goToPacking(updated)
                    }
                } else {
                    GoodsOutNoteSheetView(sheet: sheet, reference: note.reference, present: { activeSheet = $0 }, close: { activeSheet = nil })
                }
            }
        }
    }

    private func header(_ note: GoodsOutDeliveryNote) -> some View {
        HeaderContainer {
            HStack(spacing: 8) {
                HeaderTitleRow(title: note.reference, kicker: "Fast picking · swipe")
                Text("\(note.handledLineCount) / \(note.lines.count)")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.onNav)
                    .monospacedDigit()
                    .accessibilityLabel("\(note.handledLineCount) of \(note.lines.count) lines handled")
            }
            GeometryReader { proxy in
                ZStack(alignment: .leading) {
                    Capsule().fill(AikuColor.navPill)
                    Capsule()
                        .fill(AikuColor.navAccent)
                        .frame(width: proxy.size.width * progress(note))
                }
            }
            .frame(height: 6)
            .accessibilityHidden(true)
        }
    }

    private func progress(_ note: GoodsOutDeliveryNote) -> CGFloat {
        guard !note.lines.isEmpty else { return 0 }
        return CGFloat(note.handledLineCount) / CGFloat(note.lines.count)
    }

    @ViewBuilder
    private func list(_ note: GoodsOutDeliveryNote) -> some View {
        HStack(alignment: .top, spacing: 10) {
            AppIcon.swipe.image
                .font(.system(size: 18))
                .accessibilityHidden(true)
            (Text("Swipe").bold() + Text(" to show the action buttons · ") + Text("swipe all the way").bold() + Text(" to do it at once · right = Picked, left = Partial / Not picked"))
                .font(.aiku(13))
                .fixedSize(horizontal: false, vertical: true)
        }
        .foregroundStyle(AikuColor.ink)
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.soft, in: RoundedRectangle(cornerRadius: 8))
        Card {
            ForEach(Array(note.linesByLocation.enumerated()), id: \.element.id) { index, line in
                if index > 0 {
                    RowDivider()
                }
                GoodsOutSwipeRow(
                    rowID: line.id,
                    open: $open,
                    leadingActions: line.isHandled ? [] : [pickAction(note, line)],
                    trailingActions: line.isHandled ? [] : [partialAction(line), notPickedAction(note, line)],
                    fullLeading: line.isHandled ? nil : { pickAll(note, line) },
                    fullTrailing: line.isHandled ? nil : { notPicked(note, line) }
                ) {
                    GoodsOutFastPickRow(line: line) {
                        open = nil
                        activeSheet = .itemActions(line: line.id)
                    }
                }
            }
        }
        Text("Sorted by pick route · scanner still works: scan = +1")
            .font(.aiku(12))
            .foregroundStyle(AikuColor.text3)
            .frame(maxWidth: .infinity)
    }

    @ViewBuilder
    private func footer(_ note: GoodsOutDeliveryNote) -> some View {
        if note.state == .picking {
            if note.allLinesHandled {
                AikuButton("All handled · Go to packing") {
                    if note.channel.skipsPacking || note.hasWaitingLines {
                        let previous = store.setAsPicked(note.reference, bay: nil)
                        GoodsOutFeedback.done("\(note.reference) set as \(store.note(note.reference)?.state.title.lowercased() ?? "picked")", previous: previous, toasts: toasts)
                        router.replace(with: GoodsOutRoute.deliveryNote(reference: note.reference))
                    } else {
                        activeSheet = .pickedBay(setsPicked: true)
                    }
                }
                .accessibilityIdentifier("goodsOut.goToPacking")
            } else {
                let left = note.linesStillToHandle
                Text("\(left) \(left == 1 ? "item" : "items") left")
                    .font(.aiku(15, .semibold))
                    .foregroundStyle(AikuColor.text4)
                    .frame(maxWidth: .infinity, minHeight: 52)
                    .background(AikuColor.bg, in: RoundedRectangle(cornerRadius: 8))
                    .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
            }
        }
    }

    private func goToPacking(_ note: GoodsOutDeliveryNote) {
        if note.state == .picked {
            store.startPacking(note.reference)
        }
        router.replace(with: GoodsOutRoute.deliveryNote(reference: note.reference))
    }

    private func pickAction(_ note: GoodsOutDeliveryNote, _ line: GoodsOutLine) -> GoodsOutSwipeAction {
        GoodsOutSwipeAction(id: "pick-\(line.id)", title: "Picked ×\(line.remaining)", icon: .check, background: AikuColor.success) {
            pickAll(note, line)
        }
    }

    private func partialAction(_ line: GoodsOutLine) -> GoodsOutSwipeAction {
        GoodsOutSwipeAction(id: "partial-\(line.id)", title: "Partial", icon: .filter, background: AikuColor.primary, foreground: AikuColor.onPrimary) {
            activeSheet = .itemActions(line: line.id)
        }
    }

    private func notPickedAction(_ note: GoodsOutDeliveryNote, _ line: GoodsOutLine) -> GoodsOutSwipeAction {
        GoodsOutSwipeAction(id: "notPicked-\(line.id)", title: "Not picked", icon: .warning, background: AikuColor.warn) {
            notPicked(note, line)
        }
    }

    private func pickAll(_ note: GoodsOutDeliveryNote, _ line: GoodsOutLine) {
        let previous = store.pickAll(note.reference, line: line.id)
        GoodsOutFeedback.done("\(line.sko) picked ×\(line.remaining)", previous: previous, toasts: toasts)
    }

    private func notPicked(_ note: GoodsOutDeliveryNote, _ line: GoodsOutLine) {
        let previous = store.markNotPicked(note.reference, line: line.id, reason: .outOfStock)
        GoodsOutFeedback.done("\(line.sko) not picked · out of stock", tone: .danger, previous: previous, toasts: toasts)
    }
}

struct GoodsOutFastPickRow: View {
    let line: GoodsOutLine
    let onMore: () -> Void

    private var statusColor: Color {
        switch line.status {
        case .toPick: AikuColor.text3
        case .partlyPicked: AikuColor.ink
        case .picked: AikuColor.success
        case .notPicked, .waiting: AikuColor.warn
        }
    }

    private var barColor: Color {
        switch line.status {
        case .toPick, .partlyPicked: AikuColor.borderStrong
        case .picked: AikuColor.success
        case .notPicked, .waiting: AikuColor.warn
        }
    }

    private var isProblem: Bool {
        switch line.status {
        case .notPicked, .waiting: true
        default: false
        }
    }

    var body: some View {
        HStack(spacing: 12) {
            Capsule()
                .fill(barColor)
                .frame(width: 6)
                .frame(maxHeight: .infinity)
            ProductThumb(size: 48, label: "Product photo of \(line.sko)")
            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 8) {
                    Text(line.pickLocation)
                        .font(.aiku(13, .bold))
                        .foregroundStyle(AikuColor.ink)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(AikuColor.soft, in: RoundedRectangle(cornerRadius: 6))
                    Text(line.sko)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                }
                Text(line.name)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
                Text(line.swipeStatusText)
                    .font(.aiku(12, .semibold))
                    .foregroundStyle(statusColor)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            Text("×\(line.required)")
                .font(.aiku(20, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
            IconButton(.more, label: "More for \(line.sko)", tint: AikuColor.text3, action: onMore)
        }
        .padding(.leading, 14)
        .padding(.trailing, 6)
        .padding(.vertical, 10)
        .frame(minHeight: 72)
        .background(isProblem ? AikuColor.warnSoft : AikuColor.surface)
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(line.pickLocation), \(line.sko), \(line.name), \(line.swipeStatusText), quantity \(line.required)")
    }
}
