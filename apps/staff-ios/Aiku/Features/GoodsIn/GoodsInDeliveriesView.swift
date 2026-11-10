import SwiftUI

struct GoodsInDeliveriesView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var isFiltering = false
    private let store = GoodsInStore.shared

    var body: some View {
        @Bindable var store = store
        let listed = store.listedDeliveries()
        let others = store.otherActiveDeliveries()
        AppScreen(
            title: "Stock deliveries",
            kicker: "Goods in · \(session.warehouseLabel)",
            actions: [HeaderAction(.search, label: "Search and filter", badge: store.filter.activeCount > 0 ? store.filter.activeCount : nil) { isFiltering = true }]
        ) {
            ModuleTabs(.goodsIn, current: .stockDeliveries)
        } content: {
            FilterChipBar(options: GoodsInListScope.options, selection: $store.listScope, title: \.title) { scope in
                scope.showsCount ? store.count(in: scope) : nil
            }
            if store.filter.activeCount > 0 {
                HStack {
                    Text(store.filter.summary)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                    Spacer(minLength: 8)
                    LinkButton("Clear filters") { store.filter = GoodsInDeliveryFilter(sort: store.filter.sort) }
                }
                .padding(.horizontal, 4)
            }
            if listed.isEmpty {
                EmptyState(icon: .stockDelivery, title: "No stock deliveries", message: "Nothing matches \(store.listScope.title.lowercased()) with these filters.")
            } else {
                CardList(listed) { delivery in
                    GoodsInDeliveryRow(delivery: delivery) { open(delivery) }
                }
            }
            if !others.isEmpty {
                SectionHeading("Other states")
                CardList(others) { delivery in
                    GoodsInDeliveryRow(delivery: delivery) { open(delivery) }
                }
            }
            HelperText("References follow the organisation format (SD + organisation + number). Suppliers and agents can have their own.")
                .padding(.horizontal, 4)
        }
        .aikuSheet(isPresented: $isFiltering) {
            GoodsInFilterSheet(initial: store.filter) { draft in
                store.filteredCount(draft)
            } onShow: { draft in
                store.applyFilter(draft)
                isFiltering = false
            }
        }
    }

    private func open(_ delivery: GoodsInDelivery) {
        router.open(GoodsInRoute.stockDelivery(reference: delivery.reference))
    }
}

struct GoodsInFilterSheet: View {
    let count: (GoodsInDeliveryFilter) -> Int
    let onShow: (GoodsInDeliveryFilter) -> Void
    @State private var draft: GoodsInDeliveryFilter

    init(initial: GoodsInDeliveryFilter, count: @escaping (GoodsInDeliveryFilter) -> Int, onShow: @escaping (GoodsInDeliveryFilter) -> Void) {
        self.count = count
        self.onShow = onShow
        _draft = State(initialValue: initial)
    }

    private static let stateDots: [GoodsInDeliveryState: UInt32] = [
        .arriving: 0x9CA3AF, .received: 0x6366F1, .checked: 0x3B82F6,
        .bookingIn: 0x14B8A6, .bookedIn: 0x22C55E, .notReceived: 0xEF4444
    ]

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            HStack(spacing: 10) {
                AppIcon.filter.image
                    .font(.system(size: 18))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityHidden(true)
                Text("Filter stock deliveries")
                    .font(.aiku(18, .bold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .accessibilityAddTraits(.isHeader)
                Button("Reset") { draft = GoodsInDeliveryFilter() }
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
                    .frame(minWidth: 44, minHeight: 44)
                    .accessibilityIdentifier("goodsIn.filter.reset")
            }
            section("State", hint: hint(draft.states.count)) {
                ForEach(GoodsInDeliveryFilter.stateOptions, id: \.self) { state in
                    option(state.title, dot: Self.stateDots[state], isOn: draft.states.contains(state)) {
                        draft.states.formSymmetricDifference([state])
                    }
                }
            }
            section("Source", hint: hint(draft.sources.count)) {
                ForEach(GoodsInSource.allCases, id: \.self) { source in
                    option(source.filterTitle, isOn: draft.sources.contains(source)) {
                        draft.sources.formSymmetricDifference([source])
                    }
                }
            }
            section("Dispatched", hint: hint(draft.dispatched.count)) {
                ForEach(GoodsInDispatchWindow.allCases, id: \.self) { window in
                    option(window.title, isOn: draft.dispatched.contains(window)) {
                        draft.dispatched.formSymmetricDifference([window])
                    }
                }
            }
            section("Sort by", hint: "Choose one") {
                ForEach(GoodsInDeliverySort.allCases, id: \.self) { sort in
                    option(sort.title, isOn: draft.sort == sort) { draft.sort = sort }
                }
            }
            VStack(spacing: 8) {
                Text(draft.summary)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
                    .frame(maxWidth: .infinity)
                AikuButton(showTitle) { onShow(draft) }
                    .accessibilityIdentifier("goodsIn.filter.show")
            }
        }
        .padding(.horizontal, 20)
        .padding(.top, 28)
        .padding(.bottom, 16)
    }

    private var showTitle: String {
        let total = count(draft)
        return "Show \(total.grouped) \(total == 1 ? "stock delivery" : "stock deliveries")"
    }

    private func hint(_ selected: Int) -> String {
        selected > 0 ? "\(selected) selected" : "Any"
    }

    private func section<Options: View>(_ label: String, hint: String, @ViewBuilder options: () -> Options) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(alignment: .firstTextBaseline) {
                Text(label.uppercased())
                    .font(.aiku(13, .bold))
                    .tracking(0.5)
                    .foregroundStyle(AikuColor.text3)
                Spacer(minLength: 8)
                Text(hint)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
            FlowLayout(spacing: 8, lineSpacing: 8) {
                options()
            }
        }
    }

    private func option(_ title: String, dot: UInt32? = nil, isOn: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(spacing: 6) {
                if let dot {
                    Circle().fill(Color(hex: dot)).frame(width: 8, height: 8)
                }
                Text(title)
                    .font(.aiku(14, .semibold))
                    .lineLimit(1)
            }
            .foregroundStyle(isOn ? AikuColor.ink : AikuColor.text2)
            .padding(.horizontal, 14)
            .frame(minHeight: 44)
            .background(isOn ? AikuColor.soft : AikuColor.surface, in: Capsule())
            .overlay(Capsule().strokeBorder(isOn ? AikuColor.primary : AikuColor.borderStrong, lineWidth: isOn ? 2 : 1))
            .contentShape(Capsule())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isOn ? .isSelected : [])
    }
}
