import SwiftUI

struct DispatchPickingSessionsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(AuthStore.self) private var authStore
    @State private var warehouseStore = WarehouseStore.shared
    @State private var isChoosingNotes = false
    @State private var isFiltering = false
    @State private var search = ""
    private let store = PickingSessionsStore.shared

    var body: some View {
        AppScreen(
            title: "Picking sessions",
            kicker: L10n.format("Goods out · %@", session.warehouseLabel),
            actions: warehouseStore.selectedID == nil ? [] : [HeaderAction(.plus, label: "New picking session") { isChoosingNotes = true }]
        ) {
            ModuleTabs(.goodsOut, current: .pickingSessions)
        } content: {
            if warehouseStore.selectedID == nil {
                DispatchNoWarehouse()
            } else {
                FilterChipBar(
                    options: PickingSessionFilter.allCases,
                    selection: Binding(get: { store.filter }, set: { filter in Task { await store.select(filter) } }),
                    title: \.title,
                    count: { store.count(for: $0) }
                )
                SearchWithFilter(filterCount: store.listFilter.activeCount, onFilter: { isFiltering = true }) {
                    SearchField(text: $search, placeholder: "Search sessions")
                        .onChange(of: search) { _, text in
                            if text != store.searchText { store.search(text) }
                        }
                        .accessibilityIdentifier("dispatch.sessions.search")
                }
                ActiveFiltersLine(parts: store.listFilter.summary(sorts: PickingSessionSort.options)) {
                    Task { await store.clearListFilter() }
                }
                list
            }
            DispatchNote("A picking session groups many delivery notes for one picker. Fulfilment returns have their own sessions.")
        }
        .onAppear { search = store.searchText }
        .screenRefreshable { await reload() }
        .task(id: warehouseStore.selectedID) { await reload() }
        .aikuSheet(isPresented: $isChoosingNotes) {
            DispatchChooseDeliveryNotesSheet(mode: .create) { session in
                isChoosingNotes = false
                if let session {
                    router.open(DispatchRoute.pickingSessionID(id: session.id))
                }
            }
        }
        .sidePanel(isPresented: $isFiltering) {
            ListFilterPanel(
                title: "Filter picking sessions",
                filter: store.listFilter,
                sorts: PickingSessionSort.options,
                total: { _ in store.shownTotal },
                onApply: { applied in
                    isFiltering = false
                    Task { await store.apply(applied) }
                },
                onClose: { isFiltering = false }
            )
        }
    }

    @ViewBuilder
    private var list: some View {
        if let problem = store.loadError {
            DispatchLoadProblem(title: "Couldn’t load the picking sessions", message: problem) { await reload() }
        }
        if let savedAt = store.savedCopyAt {
            SavedCopyNote(savedAt: savedAt)
        }
        if !store.rows.isEmpty {
            CardList(store.rows) { item in
                RecordRow(
                    item.reference,
                    date: item.recordDate(),
                    dateCaption: item.recordDateCaption(),
                    party: PickingSessionFormat.personName(item.picker, currentUserID: authStore.userID),
                    badges: [Chip(item.stateTitle, item.tone)],
                    details: item.details,
                    action: { router.open(DispatchRoute.pickingSessionID(id: item.id)) }
                )
                .accessibilityIdentifier("dispatch.session.\(item.reference)")
            }
            if store.canLoadMore || store.loadingPage ?? 0 > 1 {
                DispatchLoadMoreRow(isLoading: store.loadingPage != nil, page: store.page) { await store.loadMore() }
            }
            if let problem = store.loadMoreError {
                DispatchLoadProblem(title: "Couldn’t load more picking sessions", message: problem) { await store.retryMore() }
            }
        } else if store.hasLoaded, store.loadingPage == nil {
            EmptyState(
                icon: .trolley,
                title: "No picking sessions",
                message: store.isSearching
                    ? "Nothing matches this state, search or filter."
                    : "Start one with + and choose delivery notes that are waiting to be picked."
            )
        } else if store.loadError == nil {
            DispatchSkeletonList()
        }
    }

    private func reload() async {
        guard let warehouseID = warehouseStore.selectedID else { return }
        await store.load(warehouseID: warehouseID)
    }
}

struct DispatchLoadMoreRow: View {
    let isLoading: Bool
    let page: Int
    let loadMore: () async -> Void
    @State private var isNearScreen = false

    var body: some View {
        let triggerLine = UIScreen.main.bounds.height + 240
        HStack(spacing: 8) {
            ProgressView()
            Text("Loading more…")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
        }
        .frame(maxWidth: .infinity, minHeight: 44)
        .opacity(isLoading ? 1 : 0.6)
        .onGeometryChange(for: Bool.self) { proxy in
            proxy.frame(in: .global).minY < triggerLine
        } action: { isNear in
            isNearScreen = isNear
        }
        .task(id: LoadTrigger(isNearScreen: isNearScreen, page: page)) {
            guard isNearScreen else { return }
            await loadMore()
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("Loading more picking sessions")
    }

    private struct LoadTrigger: Hashable {
        let isNearScreen: Bool
        let page: Int
    }
}
