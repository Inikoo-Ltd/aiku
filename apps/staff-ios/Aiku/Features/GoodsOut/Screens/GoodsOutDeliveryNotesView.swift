import SwiftUI

struct GoodsOutDeliveryNotesView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @Bindable private var store = GoodsOutStore.shared
    @State private var isFiltering = false

    var body: some View {
        Group {
            if store.selectedForSession.isEmpty {
                AppScreen(title: "Delivery notes", kicker: kicker, actions: actions) {
                    ModuleTabs(.goodsOut, current: .deliveryNotes)
                } content: {
                    listContent
                }
            } else {
                AppScreen(title: "Delivery notes", kicker: kicker, actions: actions) {
                    ModuleTabs(.goodsOut, current: .deliveryNotes)
                } content: {
                    listContent
                } footer: {
                    sessionFooter
                }
            }
        }
        .aikuSheet(isPresented: $isFiltering) {
            GoodsOutFilterSheet(store: store, initial: store.listQuery.filter) { filter in
                store.listQuery.apply(filter)
                isFiltering = false
            } onClose: {
                isFiltering = false
            }
        }
    }

    private var kicker: String { "Goods out · \(session.warehouseLabel)" }

    private var actions: [HeaderAction] {
        let active = store.listQuery.filter.activeCount
        return [HeaderAction(.search, label: "Search and filter", badge: active > 0 ? active : nil) { isFiltering = true }]
    }

    @ViewBuilder
    private var listContent: some View {
        SearchField(text: $store.listQuery.search, placeholder: "Reference, customer or SKO")
        stateChips
        if store.listQuery.filter.activeCount > 0 {
            HStack(spacing: 8) {
                Text(store.listQuery.filter.summary)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .frame(maxWidth: .infinity, alignment: .leading)
                LinkButton("Clear filters", size: 13) {
                    store.listQuery.apply(GoodsOutDeliveryNoteFilter())
                }
            }
        }
        let results = store.listResults
        if results.isEmpty {
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
                        Text(state == .dispatched ? state.title : "\(state.title) \(store.listCount(state))")
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
        .padding(.horizontal, -16)
    }

    @ViewBuilder
    private func row(_ note: GoodsOutDeliveryNote) -> some View {
        let subtitle = note.listSubtitle(currentUser: store.currentUser)
        if note.state == .todo {
            let isSelected = store.selectedForSession.contains(note.reference)
            ListRow(note.reference, subtitle: subtitle, chips: [note.state.chip]) {
                router.open(GoodsOutRoute.deliveryNote(reference: note.reference))
            } trailing: {
                Button {
                    store.toggleSessionSelection(note.reference)
                } label: {
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
                .buttonStyle(.plain)
                .accessibilityLabel(isSelected ? "Deselect \(note.reference)" : "Select \(note.reference) for a picking session")
            }
        } else {
            ListRow(note.reference, subtitle: subtitle, chips: [note.state.chip]) {
                router.open(GoodsOutRoute.deliveryNote(reference: note.reference))
            }
        }
    }

    @ViewBuilder
    private var sessionFooter: some View {
        let count = store.selectedForSession.count
        AikuButton("Create picking session from \(count) selected", kind: .secondary) {
            store.selectedForSession = []
            toasts.show("\(count) delivery \(count == 1 ? "note" : "notes") ready for a picking session", tone: .info)
            router.open(DispatchRoute.pickingSessions)
        }
        .accessibilityIdentifier("goodsOut.createPickingSession")
    }
}
