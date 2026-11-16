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
        .loadsGoodsOut()
        .task(id: GoodsOutListLoadKey(query: GoodsOutListQuery(), warehouseID: store.warehouseID, isRemote: store.isRemote)) {
            await refresh()
        }
        .screenRefreshable {
            await refresh()
        }
    }

    private func refresh() async {
        guard store.isRemote else { return }
        async let personnel: Void = store.loadPersonnel()
        await store.loadBacklogCounts()
        await personnel
    }

    private func sessionAction(_ person: GoodsOutPersonActivity) -> (() -> Void)? {
        if let id = person.sessionID {
            return { router.open(DispatchRoute.pickingSessionID(id: id)) }
        }
        return person.sessionReference.map { reference in { router.open(DispatchRoute.pickingSession(reference: reference)) } }
    }

    private var pickerActivities: [GoodsOutPersonActivity] {
        if store.isRemote { return store.pickersNow }
        return store.pickers.enumerated().compactMap { index, picker in
            GoodsOutResources.pickerActivity(picker, notes: store.notes).map {
                GoodsOutPersonActivity(id: index, name: picker.name, subtitle: $0, sessionReference: picker.session)
            }
        }
    }

    private var packerActivities: [GoodsOutPersonActivity] {
        if store.isRemote { return store.packersNow }
        return GoodsOutBacklog.packers(for: store.notes).enumerated().map { index, packer in
            GoodsOutPersonActivity(id: index, name: packer.name, subtitle: "\(packer.count) \(packer.count == 1 ? "order" : "orders") to pack", orders: packer.count)
        }
    }

    @ViewBuilder
    private var channelCards: some View {
        let rows = store.backlogRows
        if let error = store.countsError, rows.isEmpty {
            GoodsOutLoadProblem(title: "Couldn’t load the backlog", message: error) {
                Task { await store.loadBacklogCounts() }
            }
        } else if store.isRemote, store.counts == nil {
            GoodsOutSkeletonCard(rows: 2)
        } else if rows.isEmpty {
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
        let active = pickerActivities
        GoodsOutSectionTitle(title: "Pickers now")
        if let error = store.personnelError, active.isEmpty {
            GoodsOutLoadProblem(title: "Couldn’t load who is working", message: error) {
                Task { await store.loadPersonnel() }
            }
        } else if active.isEmpty {
            EmptyState(icon: .person, title: "Nobody is picking", message: "Pickers appear here while they work.")
        } else {
            Card {
                ForEach(Array(active.enumerated()), id: \.element.id) { index, person in
                    if index > 0 {
                        RowDivider()
                    }
                    ListRow(
                        person.name,
                        subtitle: person.subtitle,
                        icon: .person,
                        action: sessionAction(person)
                    )
                }
            }
        }
    }

    @ViewBuilder
    private var sessions: some View {
        let withSessions = pickerActivities.filter { $0.sessionReference != nil }
        GoodsOutSectionTitle(title: "Picking sessions now")
        if withSessions.isEmpty {
            EmptyState(icon: .trolley, title: "No open picking sessions")
        } else {
            Card {
                ForEach(Array(withSessions.enumerated()), id: \.element.id) { index, person in
                    if index > 0 {
                        RowDivider()
                    }
                    if let reference = person.sessionReference {
                        ListRow(
                            reference,
                            subtitle: "\(person.name) · \(person.subtitle)",
                            icon: .trolley,
                            showsChevron: true
                        ) {
                            sessionAction(person)?()
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
        let packers = packerActivities
        GoodsOutSectionTitle(title: "Packers now")
        if packers.isEmpty {
            EmptyState(icon: .packed, title: "Nobody is packing", message: "Packers appear here while they work.")
        } else {
            Card {
                ForEach(Array(packers.enumerated()), id: \.element.id) { index, packer in
                    if index > 0 {
                        RowDivider()
                    }
                    ListRow(packer.name, subtitle: packer.subtitle, icon: .packed)
                }
            }
        }
        if !store.isRemote {
            AikuButton("Packing queue · \(store.packingQueue.count) to pack", icon: .swipe, kind: .secondary) {
                router.open(GoodsOutRoute.packingQueue)
            }
            .accessibilityIdentifier("goodsOut.packingQueue")
        }
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
