import SwiftUI

struct DispatchPickingSessionsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(AuthStore.self) private var authStore
    @State private var warehouseStore = WarehouseStore.shared
    @State private var isChoosingNotes = false
    private let store = PickingSessionsStore.shared

    var body: some View {
        AppScreen(
            title: "Picking sessions",
            kicker: "Goods out · \(session.warehouseLabel)",
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
                list
            }
            DispatchNote("A picking session groups many delivery notes for one picker. Fulfilment returns have their own sessions.")
        }
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
    }

    @ViewBuilder
    private var list: some View {
        if let problem = store.loadError {
            DispatchLoadProblem(title: "Couldn’t load the picking sessions", message: problem) { await reload() }
        }
        if !store.rows.isEmpty {
            CardList(store.rows) { item in
                ListRow(
                    item.reference,
                    subtitle: item.subtitle(currentUserID: authStore.userID),
                    chips: [Chip(item.stateTitle, item.tone)],
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
                message: "Start one with + and choose delivery notes that are waiting to be picked."
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
