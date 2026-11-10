import SwiftUI

enum GoodsOutBacklogSegment: String, CaseIterable, Hashable {
    case deliveryNotes
    case sessions
    case pickers
    case packers

    var title: String {
        switch self {
        case .deliveryNotes: "Delivery notes"
        case .sessions: "Sessions"
        case .pickers: "Pickers"
        case .packers: "Packers"
        }
    }
}

struct GoodsOutBacklogView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var segment: GoodsOutBacklogSegment = .deliveryNotes
    private let store = GoodsOutStore.shared

    var body: some View {
        AppScreen(title: "Dispatching backlog", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .goodsOutBacklog)
        } content: {
            SegmentedControl(options: GoodsOutBacklogSegment.allCases, selection: $segment, title: \.title)
            switch segment {
            case .deliveryNotes:
                channelCards
                pickersNow
                HelperText("Same as the web Dispatching backlog: one row per sales channel, counts by state. Tap a channel to open its delivery notes.")
            case .sessions:
                sessions
            case .pickers:
                pickersNow
            case .packers:
                packers
            }
        }
    }

    @ViewBuilder
    private var channelCards: some View {
        let rows = store.backlogRows
        if rows.isEmpty {
            EmptyState(icon: .backlog, title: "Nothing in the backlog", message: "Every delivery note has been dispatched.")
        }
        ForEach(rows) { row in
            GoodsOutBacklogChannelCard(row: row) {
                store.showChannel(row.channel)
                router.open(GoodsOutRoute.deliveryNotes)
            }
        }
    }

    @ViewBuilder
    private var pickersNow: some View {
        let active = store.pickers.compactMap { picker in
            GoodsOutResources.pickerActivity(picker, notes: store.notes).map { (picker, $0) }
        }
        GoodsOutSectionTitle(title: "Pickers now")
        if active.isEmpty {
            EmptyState(icon: .person, title: "Nobody is picking", message: "Pickers appear here while they work.")
        } else {
            Card {
                ForEach(Array(active.enumerated()), id: \.element.0.id) { index, item in
                    if index > 0 {
                        RowDivider()
                    }
                    let picker = item.0
                    ListRow(
                        picker.name,
                        subtitle: item.1,
                        icon: .person,
                        action: picker.session.map { reference in { router.open(DispatchRoute.pickingSession(reference: reference)) } }
                    )
                }
            }
        }
    }

    @ViewBuilder
    private var sessions: some View {
        let withSessions = store.pickers.filter { $0.session != nil }
        GoodsOutSectionTitle(title: "Picking sessions now")
        if withSessions.isEmpty {
            EmptyState(icon: .trolley, title: "No open picking sessions")
        } else {
            Card {
                ForEach(Array(withSessions.enumerated()), id: \.element.id) { index, picker in
                    if index > 0 {
                        RowDivider()
                    }
                    if let reference = picker.session {
                        ListRow(
                            reference,
                            subtitle: "\(picker.name) · \(picker.sessionOrders) orders · \(picker.sessionTrolleys) trolleys",
                            icon: .trolley,
                            showsChevron: true
                        ) {
                            router.open(DispatchRoute.pickingSession(reference: reference))
                        }
                    }
                }
            }
        }
        AikuButton("All picking sessions", kind: .secondary) {
            router.open(DispatchRoute.pickingSessions)
        }
    }

    @ViewBuilder
    private var packers: some View {
        let packers = GoodsOutBacklog.packers(for: store.notes)
        GoodsOutSectionTitle(title: "Packers now")
        if packers.isEmpty {
            EmptyState(icon: .packed, title: "Nobody is packing", message: "Packers appear here while they work.")
        } else {
            Card {
                ForEach(Array(packers.enumerated()), id: \.element.name) { index, packer in
                    if index > 0 {
                        RowDivider()
                    }
                    ListRow(packer.name, subtitle: "\(packer.count) \(packer.count == 1 ? "order" : "orders") to pack", icon: .packed)
                }
            }
        }
        AikuButton("Packing queue · \(store.packingQueue.count) to pack", icon: .swipe, kind: .secondary) {
            router.open(GoodsOutRoute.packingQueue)
        }
        .accessibilityIdentifier("goodsOut.packingQueue")
    }
}

private struct GoodsOutBacklogChannelCard: View {
    let row: GoodsOutBacklogRow
    let onOpen: () -> Void

    private let columns = Array(repeating: GridItem(.flexible(), spacing: 6), count: 4)

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Button(action: onOpen) {
                HStack {
                    Text(row.channel.title)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Spacer()
                    AppIcon.chevronRight.image
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundStyle(AikuColor.faint)
                }
                .frame(minHeight: 44)
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityLabel("\(row.channel.title) delivery notes")
            .accessibilityIdentifier("goodsOut.backlog.\(row.channel.rawValue)")
            LazyVGrid(columns: columns, spacing: 6) {
                ForEach(GoodsOutDeliveryNoteState.backlogColumns, id: \.self) { state in
                    tile(value: row.count(state), label: state.title, tone: tone(for: state, count: row.count(state)))
                }
                tile(value: row.total, label: "Total", tone: .neutral)
            }
        }
        .padding(.horizontal, 14)
        .padding(.top, 4)
        .padding(.bottom, 14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }

    private func tone(for state: GoodsOutDeliveryNoteState, count: Int) -> Tone {
        switch state {
        case .todo: .neutral
        case .waiting: count > 0 ? .warn : .neutral
        case .finalised: .success
        default: .info
        }
    }

    private func tile(value: Int, label: String, tone: Tone) -> some View {
        VStack(spacing: 2) {
            Text("\(value)")
                .font(.aiku(17, .bold))
                .foregroundStyle(tone == .neutral ? AikuColor.text2 : tone.foreground)
                .monospacedDigit()
            Text(label)
                .font(.aiku(10, .semibold))
                .foregroundStyle(AikuColor.text3)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .padding(.vertical, 8)
        .padding(.horizontal, 2)
        .frame(maxWidth: .infinity)
        .background(tone.background, in: RoundedRectangle(cornerRadius: 8))
        .accessibilityElement(children: .combine)
    }
}
