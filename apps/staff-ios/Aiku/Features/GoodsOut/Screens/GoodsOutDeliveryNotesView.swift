import SwiftUI

struct GoodsOutDeliveryNotesView: View {
    var initialTab: GoodsOutDeliveryNoteState?
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @Bindable private var store = GoodsOutStore.shared
    @State private var isFiltering = false
    @State private var hasAppliedInitialTab = false

    var body: some View {
        Group {
            if store.selectedForSession.isEmpty {
                AppScreen(title: "Delivery notes", kicker: kicker) {
                    ModuleTabs(.goodsOut, current: .deliveryNotes)
                } content: {
                    listContent
                }
            } else {
                AppScreen(title: "Delivery notes", kicker: kicker) {
                    ModuleTabs(.goodsOut, current: .deliveryNotes)
                } content: {
                    listContent
                } footer: {
                    sessionFooter
                }
            }
        }
        .onAppear { applyInitialTab() }
        .loadsGoodsOut()
        .task(id: GoodsOutListLoadKey(query: store.listQuery, warehouseID: store.warehouseID, isRemote: store.isRemote)) {
            guard store.isRemote else { return }
            if store.listPage > 0 {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            async let counts: Void = store.loadCounts()
            await store.loadList()
            await counts
        }
        .screenRefreshable {
            guard store.isRemote else { return }
            async let counts: Void = store.loadCounts()
            await store.loadList()
            await counts
        }
        .sidePanel(isPresented: $isFiltering) {
            GoodsOutFilterPanel(store: store, query: store.listQuery) { filter in
                store.listQuery.apply(filter)
                isFiltering = false
            } onClose: {
                isFiltering = false
            }
        }
    }

    private var kicker: String { L10n.format("Goods out · %@", session.warehouseLabel) }

    private func applyInitialTab() {
        guard !hasAppliedInitialTab else { return }
        hasAppliedInitialTab = true
        guard let initialTab else { return }
        store.listQuery = GoodsOutListQuery(tab: initialTab)
    }

    private var searchPlaceholder: String {
        store.isRemote ? L10n.text("Reference or tracking number") : L10n.text("Reference, customer or SKO")
    }

    @ViewBuilder
    private var listContent: some View {
        stateChips
        SearchWithFilter(filterCount: store.listQuery.filter.activeCount, onFilter: { isFiltering = true }) {
            SearchField(text: $store.listQuery.search, placeholder: searchPlaceholder)
                .accessibilityIdentifier("goodsOut.search")
        }
        ActiveFiltersLine(parts: store.listQuery.filter.summary()) {
            store.listQuery.clearFilters()
        }
        if let savedAt = store.listSavedAt {
            SavedCopyNote(savedAt: savedAt)
        }
        let results = store.listResults
        if let error = store.listError, results.isEmpty {
            GoodsOutLoadProblem(title: "Couldn’t load delivery notes", message: error) {
                Task { await store.loadList() }
            }
        } else if store.isRemote, store.isLoadingList, results.isEmpty {
            GoodsOutSkeletonCard()
        } else if results.isEmpty {
            EmptyState(
                icon: .deliveryNote,
                title: "No delivery notes",
                message: "Nothing matches this state, search or filter."
            )
        } else {
            Card {
                ForEach(Array(results.enumerated()), id: \.element.id) { index, note in
                    if index > 0 {
                        RowDivider()
                    }
                    row(note)
                }
            }
            if let error = store.listError {
                GoodsOutLoadProblem(title: "Couldn’t load more delivery notes", message: error) {
                    Task { await store.loadMoreList() }
                }
            } else if store.hasMoreList {
                AikuButton("Show more", kind: .tertiary, isLoading: store.isLoadingList) {
                    Task { await store.loadMoreList() }
                }
                .accessibilityIdentifier("goodsOut.loadMore")
            }
        }
        HelperText("The reference is the order reference. Select several To do notes to put them in a picking session.")
    }

    private var stateChips: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 6) {
                ForEach(store.listTabs, id: \.self) { state in
                    let isSelected = store.listQuery.effectiveStates == [state] && store.listQuery.filter.states.isEmpty
                    Button {
                        store.listQuery.select(tab: state)
                    } label: {
                        Text(state == .dispatched || !store.hasCounts ? state.title : "\(state.title) \(store.listCount(state))")
                            .font(.aiku(13, .semibold))
                            .foregroundStyle(isSelected ? AikuColor.onPrimary : AikuColor.text2)
                            .padding(.horizontal, 12)
                            .frame(minHeight: 34)
                            .background(isSelected ? AikuColor.primary : AikuColor.surface, in: Capsule())
                            .overlay(Capsule().strokeBorder(isSelected ? .clear : AikuColor.borderStrong))
                            .frame(minHeight: 44)
                            .contentShape(Capsule())
                    }
                    .buttonStyle(.plain)
                    .accessibilityAddTraits(isSelected ? .isSelected : [])
                    .accessibilityIdentifier("goodsOut.tab.\(state.rawValue)")
                }
            }
            .padding(.horizontal, 16)
        }
        .scrollBounceBehavior(.basedOnSize, axes: [.horizontal, .vertical])
        .padding(.horizontal, -16)
    }

    @ViewBuilder
    private func row(_ note: GoodsOutDeliveryNote) -> some View {
        let details = note.listDetails(currentUser: store.currentUser).map { L10n.text($0) }
        let open = { router.open(GoodsOutRoute.deliveryNote(reference: note.reference)) }
        if note.state == .todo, note.remote?.canAddToPickingSession ?? true {
            let isSelected = store.selectedForSession.contains(note.reference)
            let selectionLabel = isSelected ? L10n.format("Deselect %@", note.reference) : L10n.format("Select %@ for a picking session", note.reference)
            RecordRow(note.reference, date: note.recordDate, party: note.customer, badges: rowChips(note), details: details, action: open) {
                Button {
                    store.toggleSessionSelection(note.reference)
                } label: {
                    SessionSelectionMark(isSelected: isSelected)
                }
                .buttonStyle(.plain)
                .accessibilityHidden(true)
            }
            .accessibilityAddTraits(isSelected ? .isSelected : [])
            .accessibilityAction(named: Text(selectionLabel)) {
                store.toggleSessionSelection(note.reference)
            }
            .accessibilityIdentifier("goodsOut.note.\(note.reference)")
        } else {
            RecordRow(note.reference, date: note.recordDate, party: note.customer, badges: rowChips(note), details: details, action: open)
                .accessibilityIdentifier("goodsOut.note.\(note.reference)")
        }
    }

    private func rowChips(_ note: GoodsOutDeliveryNote) -> [Chip] {
        var chips = [note.state.chip]
        guard let remote = note.remote else { return chips }
        if remote.isPriority {
            chips.append(Chip("Priority", .orange))
        }
        if remote.isCustomerVip {
            chips.append(Chip("VIP", .lime))
        }
        if remote.waitingCount > 0 {
            chips.append(Chip(L10n.format("%lld waiting", remote.waitingCount), .warn))
        }
        return chips
    }

    @ViewBuilder
    private var sessionFooter: some View {
        let count = store.selectedForSession.count
        AikuButton(L10n.format("Create picking session from %lld selected", count), kind: .secondary) {
            store.selectedForSession = []
            toasts.show(L10n.format("%lld delivery notes ready for a picking session", count), tone: .info)
            router.open(DispatchRoute.pickingSessions)
        }
        .accessibilityIdentifier("goodsOut.createPickingSession")
    }
}

private struct SessionSelectionMark: View {
    let isSelected: Bool

    var body: some View {
        ZStack {
            Circle()
                .strokeBorder(isSelected ? AikuColor.primary : AikuColor.borderStrong, lineWidth: 2)
                .background(Circle().fill(isSelected ? AikuColor.primary : AikuColor.surface))
            if isSelected {
                AppIcon.check.image
                    .font(.system(size: 12, weight: .bold))
                    .foregroundStyle(AikuColor.onPrimary)
            }
        }
        .frame(width: 24, height: 24)
        .frame(width: 44, height: 44)
        .contentShape(Rectangle())
    }
}

struct GoodsOutListLoadKey: Hashable {
    let query: GoodsOutListQuery
    let warehouseID: Int?
    let isRemote: Bool
}
