import SwiftUI

struct MyWorkView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient
    @State private var feed = MyWorkFeed()
    @State private var warehouseStore = WarehouseStore.shared
    @State private var appearance = 0
    private let badges = WorkBadgesStore.shared

    var body: some View {
        let now = Date()
        AppScreen(
            title: "My work",
            kicker: session.hasProfile ? L10n.format("%@ · assigned to you", session.profile.name) : "Assigned to you",
            back: .none,
            actions: session.hasWarehouseAccess ? [HeaderAction(.search, label: "Search") { router.open(ShellRoute.search) }] : [],
            spacing: 10
        ) {
            if warehouseStore.selectedID == nil {
                EmptyState(icon: .work, title: "Choose a warehouse", message: "Your work is listed per warehouse.")
            } else {
                if let message = feed.loadError {
                    InlineAlert(title: "Couldn’t load your work", message: message)
                    AikuButton("Try again", kind: .secondary) {
                        Task { await reload() }
                    }
                }
                if !feed.rows.isEmpty {
                    ForEach(feed.rows, id: \.listID) { row in
                        workRow(row, now: now)
                    }
                } else if feed.isEmpty {
                    EmptyState(icon: .work, title: "Nothing assigned to you right now", message: "Delivery notes, picking sessions and returns assigned to you appear here.")
                } else if feed.loadError == nil {
                    SkeletonRow()
                    SkeletonRow()
                    SkeletonRow()
                }
            }
            Text("Everything assigned to you, from every module you can open. Oldest and blocked first.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text4)
                .padding(.horizontal, 4)
                .padding(.top, 6)
        }
        .screenRefreshable { await reload() }
        .onAppear { appearance += 1 }
        .task(id: ListLoadKey(request: warehouseStore.selectedID, appearance: appearance)) { await reload() }
    }

    private func workRow(_ row: MobileMyWorkRow, now: Date) -> some View {
        let tone = MyWorkText.tone(row.step)
        return Button {
            open(row)
        } label: {
            HStack(spacing: 12) {
                Capsule()
                    .fill(barColour(tone))
                    .frame(width: 6, height: 44)
                VStack(alignment: .leading, spacing: 3) {
                    FlowLayout(spacing: 8, lineSpacing: 4) {
                        Text(row.reference)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                        StatusChip(MyWorkText.chip(row), tone: tone)
                    }
                    Text(MyWorkText.subtitle(row, now: now))
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .lineLimit(1)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .frame(minHeight: 72)
            .cardSurface()
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("shell.work.\(row.reference)")
    }

    private func open(_ row: MobileMyWorkRow) {
        switch row.kind {
        case .deliveryNote: router.open(GoodsOutRoute.deliveryNoteID(id: row.id))
        case .pickingSession: router.open(DispatchRoute.pickingSessionID(id: row.id))
        case .customerReturn: router.open(GoodsInRoute.returnDetail(id: row.id, reference: row.reference))
        case .palletReturn: router.open(FulfilmentRoute.palletReturn(id: row.id, reference: row.reference))
        }
    }

    private func barColour(_ tone: Tone) -> Color {
        switch tone {
        case .warn: AikuColor.warn
        case .info: AikuColor.primary
        default: AikuColor.borderStrong
        }
    }

    private func reload() async {
        guard let warehouseID = warehouseStore.selectedID else { return }
        async let list: Void = feed.reload(warehouseID: warehouseID, apiClient: apiClient)
        async let counts: Void = badges.refresh(warehouseID: warehouseID, apiClient: apiClient)
        _ = await (list, counts)
    }
}
