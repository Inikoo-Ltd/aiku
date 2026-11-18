import SwiftUI

struct MyWorkView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.apiClient) private var apiClient
    @State private var filter: ShellWorkFilter = .all
    @State private var liveFilter: MyWorkFilter = .all
    @State private var store = MyWorkStore.shared
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        AppScreen(
            title: "My work",
            kicker: "\(session.profile.name) · assigned to you",
            back: .none,
            actions: session.hasWarehouseAccess ? [HeaderAction(.search, label: "Search") { router.open(ShellRoute.search) }] : [],
            spacing: 10
        ) {
            if let warehouseID = warehouseStore.selectedID {
                liveContent(warehouseID: warehouseID)
            } else {
                sampleContent
            }
            Text("Everything assigned to you, from every module you can open. Oldest and blocked first.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text4)
                .padding(.horizontal, 4)
                .padding(.top, 6)
        }
        .task(id: warehouseStore.selectedID) { await reload() }
        .screenRefreshable { await reload() }
    }

    @ViewBuilder
    private func liveContent(warehouseID: Int) -> some View {
        if let problem = store.loadError, !store.hasLoaded || store.warehouseID != warehouseID {
            InlineAlert(title: "Couldn’t load your work", message: problem)
            AikuButton("Try again", kind: .secondary) {
                Task { await reload() }
            }
        } else if !store.hasLoaded || store.warehouseID != warehouseID {
            ForEach(0..<4, id: \.self) { _ in
                SkeletonBlock(height: 72)
            }
        } else {
            let filters = store.filters()
            let selected = filters.contains(liveFilter) ? liveFilter : .all
            FilterChipBar(options: filters, selection: $liveFilter, title: \.title) { option in
                option.count(in: store.counts)
            }
            let rows = store.rows(for: selected)
            if let problem = store.loadError {
                InlineAlert(title: "Couldn’t refresh your work", message: problem)
            }
            if rows.isEmpty {
                EmptyState(icon: .work, title: "All caught up", message: "Nothing is assigned to you right now.")
            }
            ForEach(rows, id: \.rowID) { row in
                workRow(
                    reference: row.reference,
                    state: MyWorkFormat.stepTitle(row),
                    detail: MyWorkFormat.subtitle(row, now: .now),
                    tone: MyWorkFormat.tone(for: row.step),
                    tag: nil
                ) {
                    open(row)
                }
            }
        }
    }

    @ViewBuilder
    private var sampleContent: some View {
        let items = ShellWork.visibleItems(ShellWork.items, hasWarehouseAccess: session.hasWarehouseAccess)
        FilterChipBar(options: ShellWork.filters(for: items), selection: $filter, title: \.title) { option in
            ShellWork.count(items, for: option)
        }
        if ShellWork.filtered(items, by: filter).isEmpty {
            EmptyState(icon: .work, title: "All caught up", message: "Nothing is assigned to you right now.")
        }
        ForEach(ShellWork.filtered(items, by: filter)) { item in
            workRow(reference: item.reference, state: item.state, detail: item.detail, tone: item.tone, tag: item.module.rawValue) {
                open(item)
            }
        }
    }

    private func workRow(reference: String, state: String, detail: String, tone: Tone, tag: String?, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(spacing: 12) {
                Capsule()
                    .fill(barColour(tone))
                    .frame(width: 6)
                    .frame(maxHeight: .infinity)
                VStack(alignment: .leading, spacing: 3) {
                    FlowLayout(spacing: 8, lineSpacing: 4) {
                        Text(reference)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                        if !state.isEmpty {
                            StatusChip(state, tone: tone)
                        }
                    }
                    Text(detail)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .multilineTextAlignment(.leading)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                if let tag {
                    ShellOutlineTag(text: tag)
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .frame(minHeight: 72)
            .fixedSize(horizontal: false, vertical: true)
            .cardSurface()
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("shell.work.\(reference)")
    }

    private func barColour(_ tone: Tone) -> Color {
        switch tone {
        case .warn: AikuColor.warn
        case .neutral: AikuColor.borderStrong
        default: AikuColor.primary
        }
    }

    private func reload() async {
        guard let warehouseID = warehouseStore.selectedID else { return }
        await store.load(warehouseID: warehouseID, service: .live(apiClient))
        if store.hasLoaded {
            session.assignedCount = store.counts.total
        }
    }

    private func open(_ row: MobileMyWorkRow) {
        if let link = MyWorkFormat.link(for: row) {
            router.shellFollow(link)
        } else {
            toasts.show("Pallet returns open on the web for now", tone: .info)
        }
    }

    private func open(_ item: ShellWorkItem) {
        if let link = item.link {
            router.shellFollow(link)
        } else {
            toasts.show("Aiku Tasks come to the app later", tone: .info)
        }
    }
}
