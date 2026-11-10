import SwiftUI

struct GoodsOutPackingQueueView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var references: [String] = []
    @State private var hasLoaded = false
    @State private var open: GoodsOutSwipeOpen?
    private let store = GoodsOutStore.shared

    private var items: [GoodsOutDeliveryNote] {
        references.compactMap { reference in store.notes.first { $0.reference == reference } }
    }

    private var doneCount: Int { items.filter { $0.state != .picked || $0.handlingBlocked }.count }
    private var packed: [GoodsOutDeliveryNote] { items.filter { $0.state == .packed } }

    var body: some View {
        ScreenScaffold(showsTabBar: false, spacing: 12) {
            header
        } content: {
            if items.isEmpty {
                EmptyState(icon: .packed, title: "Nothing to pack", message: "Picked delivery notes appear here, ready to pack.")
            } else {
                list
            }
        } footer: {
            footer
        }
        .onAppear {
            guard !hasLoaded else { return }
            hasLoaded = true
            references = store.packingQueue.map(\.reference)
        }
    }

    private var header: some View {
        HeaderContainer {
            HStack(spacing: 8) {
                HeaderTitleRow(title: "Packing queue", kicker: "Goods out · Picked, ready to pack")
                Text("\(doneCount) / \(items.count)")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.onNav)
                    .monospacedDigit()
                    .accessibilityLabel("\(doneCount) of \(items.count) delivery notes handled")
            }
            GeometryReader { proxy in
                ZStack(alignment: .leading) {
                    Capsule().fill(AikuColor.navPill)
                    Capsule()
                        .fill(AikuColor.navAccent)
                        .frame(width: proxy.size.width * progress)
                }
            }
            .frame(height: 6)
            .accessibilityHidden(true)
        }
    }

    private var progress: CGFloat {
        guard !items.isEmpty else { return 0 }
        return CGFloat(doneCount) / CGFloat(items.count)
    }

    @ViewBuilder
    private var list: some View {
        HStack(alignment: .top, spacing: 10) {
            AppIcon.swipe.image
                .font(.system(size: 18))
                .accessibilityHidden(true)
            (Text("Swipe").bold() + Text(" to show the action buttons · ") + Text("swipe all the way").bold() + Text(" to do it at once · right = Packed, left = Other box / Waiting"))
                .font(.aiku(13))
                .fixedSize(horizontal: false, vertical: true)
        }
        .foregroundStyle(AikuColor.ink)
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.soft, in: RoundedRectangle(cornerRadius: 8))
        Card {
            ForEach(Array(items.enumerated()), id: \.element.id) { index, note in
                if index > 0 {
                    RowDivider()
                }
                let canAct = note.state == .picked && !note.handlingBlocked
                GoodsOutSwipeRow(
                    rowID: note.reference,
                    open: $open,
                    leadingActions: canAct ? [packAction(note)] : [],
                    trailingActions: canAct ? [otherBoxAction(note), waitingAction(note)] : [],
                    trailingBackground: AikuColor.danger,
                    fullLeading: canAct ? { pack(note) } : nil,
                    fullTrailing: canAct ? { block(note) } : nil
                ) {
                    GoodsOutPackQueueRow(note: note) {
                        open = nil
                        router.open(GoodsOutRoute.deliveryNote(reference: note.reference))
                    }
                }
            }
        }
        Text("Box and weight are suggested from the items · weigh to correct")
            .font(.aiku(12))
            .foregroundStyle(AikuColor.text3)
            .multilineTextAlignment(.center)
            .frame(maxWidth: .infinity)
    }

    @ViewBuilder
    private var footer: some View {
        if packed.isEmpty {
            Text("Swipe a delivery note to pack it")
                .font(.aiku(15, .semibold))
                .foregroundStyle(AikuColor.text4)
                .frame(maxWidth: .infinity, minHeight: 52)
                .background(AikuColor.bg, in: RoundedRectangle(cornerRadius: 8))
                .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
        } else {
            AikuButton("Create shipments · \(packed.count) packed") {
                if let next = packed.first(where: { $0.needsShipment && !$0.shipsViaPlatform && $0.shipments.isEmpty }) {
                    router.open(GoodsOutRoute.addShipment(reference: next.reference))
                } else if let first = packed.first {
                    router.open(GoodsOutRoute.deliveryNote(reference: first.reference))
                }
            }
            .accessibilityIdentifier("goodsOut.createShipments")
        }
    }

    private func packAction(_ note: GoodsOutDeliveryNote) -> GoodsOutSwipeAction {
        GoodsOutSwipeAction(id: "pack-\(note.reference)", title: "Packed\n\(note.boxSuggestion.title)", icon: .packed, background: AikuColor.success) {
            pack(note)
        }
    }

    private func otherBoxAction(_ note: GoodsOutDeliveryNote) -> GoodsOutSwipeAction {
        GoodsOutSwipeAction(id: "otherBox-\(note.reference)", title: "Other box", icon: .sko, background: AikuColor.primary, foreground: AikuColor.onPrimary) {
            store.startPacking(note.reference)
            router.open(GoodsOutRoute.deliveryNote(reference: note.reference))
        }
    }

    private func waitingAction(_ note: GoodsOutDeliveryNote) -> GoodsOutSwipeAction {
        GoodsOutSwipeAction(id: "waiting-\(note.reference)", title: "Waiting", icon: .handling, background: AikuColor.danger) {
            block(note)
        }
    }

    private func pack(_ note: GoodsOutDeliveryNote) {
        let previous = store.quickPack(note.reference)
        GoodsOutFeedback.done("\(note.reference) set as packed · \(note.boxSuggestion.title)", previous: previous, toasts: toasts)
    }

    private func block(_ note: GoodsOutDeliveryNote) {
        let previous = store.blockHandling(note.reference)
        GoodsOutFeedback.done("\(note.reference) set as handling blocked", tone: .danger, previous: previous, toasts: toasts)
    }
}

struct GoodsOutPackQueueRow: View {
    let note: GoodsOutDeliveryNote
    let onOpen: () -> Void

    private enum Status {
        case toPack
        case packing
        case packed
        case blocked
    }

    private var status: Status {
        if note.handlingBlocked { return .blocked }
        switch note.state {
        case .picked: return .toPack
        case .packing: return .packing
        default: return .packed
        }
    }

    private var statusText: String {
        switch status {
        case .toPack: "Picked · ready to pack"
        case .packing: "Packing · \(note.packedProgress)"
        case .packed: "Packed · \(note.boxSuggestion.title) · \(note.weightText)"
        case .blocked: "Waiting · needs a supervisor"
        }
    }

    private var statusColor: Color {
        switch status {
        case .toPack: AikuColor.text3
        case .packing: AikuColor.ink
        case .packed: AikuColor.success
        case .blocked: AikuColor.danger
        }
    }

    private var barColor: Color {
        switch status {
        case .toPack: AikuColor.borderStrong
        case .packing: AikuColor.primary
        case .packed: AikuColor.success
        case .blocked: AikuColor.danger
        }
    }

    var body: some View {
        HStack(spacing: 12) {
            Capsule()
                .fill(barColor)
                .frame(width: 6)
                .frame(maxHeight: .infinity)
            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 8) {
                    Text(note.reference)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text("\(note.units) \(note.units == 1 ? "item" : "items")")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                }
                (Text("Suggested: ") + Text(note.boxSuggestion.title).bold() + Text(" · \(note.weightText)"))
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text2)
                    .fixedSize(horizontal: false, vertical: true)
                Text(statusText)
                    .font(.aiku(12, .semibold))
                    .foregroundStyle(statusColor)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            IconButton(.more, label: "Open \(note.reference) to pack", tint: AikuColor.text3, action: onOpen)
        }
        .padding(.leading, 14)
        .padding(.trailing, 6)
        .padding(.vertical, 10)
        .frame(minHeight: 76)
        .background(status == .blocked ? AikuColor.dangerSoft : AikuColor.surface)
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(note.reference), \(note.units) items, suggested \(note.boxSuggestion.title), \(note.weightText), \(statusText)")
    }
}
