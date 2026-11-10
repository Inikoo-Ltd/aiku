import SwiftUI

struct GoodsInReturnsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    private let store = GoodsInStore.shared

    var body: some View {
        AppScreen(title: "Returns", kicker: "Goods in · \(session.warehouseLabel)") {
            ModuleTabs(.goodsIn, current: .goodsInReturns)
        } content: {
            CardList(store.returns) { item in
                ListRow(item.reference, subtitle: item.listSubtitle, chips: [item.state.chip]) {
                    router.open(GoodsInRoute.returnDetail(reference: item.reference))
                }
                .accessibilityIdentifier("goodsIn.return.\(item.reference)")
            }
            HelperText("Customer returns arriving at goods in. Each item goes back to a location, or is set as damaged or not returned.")
                .padding(.horizontal, 4)
        }
    }
}

struct GoodsInReturnDetailView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var selectedSKO: String?
    private let store = GoodsInStore.shared

    private var goodsReturn: GoodsInReturn { store.goodsReturn(reference) }
    private var canWork: Bool { goodsReturn.state == .received || goodsReturn.state == .returning }
    private var selectedItem: GoodsInReturnItem? {
        if let selectedSKO, let item = goodsReturn.items.first(where: { $0.sko == selectedSKO && $0.outcome == nil }) {
            return item
        }
        return goodsReturn.nextPendingItem
    }

    var body: some View {
        if canWork, goodsReturn.isFullyProcessed {
            AppScreen(title: goodsReturn.reference, kicker: "Return · \(goodsReturn.customer)") {
                content
            } footer: {
                AikuButton("Set as returned", icon: .check) {
                    store.setReturnAsReturned(reference)
                    toasts.show("\(reference) set as returned")
                }
                .accessibilityIdentifier("goodsIn.return.setReturned")
            }
        } else {
            AppScreen(title: goodsReturn.reference, kicker: "Return · \(goodsReturn.customer)") {
                content
            }
        }
    }

    @ViewBuilder
    private var content: some View {
        steps
        CardList(goodsReturn.items) { item in
            GoodsInItemRow(
                sko: item.sko,
                subtitle: item.subtitle,
                chip: item.chip ?? (canWork && item.sko == selectedItem?.sko ? Chip("Selected", .info) : nil),
                value: item.returnedQuantity,
                total: item.quantity,
                tone: item.tone,
                action: action(for: item)
            )
        }
        if canWork, let item = selectedItem {
            HStack(spacing: 8) {
                AikuButton("To location") { process(item, .toLocation(item.homeLocation)) }
                    .accessibilityIdentifier("goodsIn.return.toLocation")
                AikuButton("Damaged", kind: .tertiary) { process(item, .damaged) }
                    .accessibilityIdentifier("goodsIn.return.damaged")
                AikuButton("Not returned", kind: .tertiary) { process(item, .notReturned) }
                    .accessibilityIdentifier("goodsIn.return.notReturned")
            }
            .lineLimit(2)
            .minimumScaleFactor(0.8)
            HelperText("Item actions for \(item.sko). When every item is processed, set the return as returned.")
                .padding(.horizontal, 4)
        } else if canWork {
            HelperText("Every item is processed. Set the return as returned.")
                .padding(.horizontal, 4)
        } else {
            GoodsInStatusLine(text: "\(goodsReturn.state.title). Nothing left to do here.")
        }
    }

    private var steps: some View {
        let order = GoodsInReturnState.allCases
        let current = order.firstIndex(of: goodsReturn.state) ?? 0
        return HStack(spacing: 3) {
            ForEach(Array(order.enumerated()), id: \.offset) { index, state in
                Text(state.stepTitle)
                    .font(.aiku(10, .bold))
                    .lineLimit(1)
                    .minimumScaleFactor(0.8)
                    .foregroundStyle(index < current ? Color.white : index == current ? AikuColor.onPrimary : AikuColor.text3)
                    .frame(maxWidth: .infinity)
                    .frame(height: 26)
                    .background(index < current ? AikuColor.success : index == current ? AikuColor.primary : AikuColor.bg, in: RoundedRectangle(cornerRadius: 6))
                    .accessibilityAddTraits(index == current ? .isSelected : [])
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Return progress")
    }

    private func action(for item: GoodsInReturnItem) -> (() -> Void)? {
        if item.outcome == nil {
            return canWork ? { selectedSKO = item.sko } : nil
        }
        if case .toLocation(let location) = item.outcome {
            return { router.open(LocationsRoute.location(code: location)) }
        }
        return { router.open(InventoryRoute.sko(reference: item.sko)) }
    }

    private func process(_ item: GoodsInReturnItem, _ outcome: GoodsInReturnOutcome) {
        store.processReturnItem(reference, sko: item.sko, outcome: outcome)
        selectedSKO = nil
        switch outcome {
        case .toLocation(let location): toasts.show("\(item.quantity.grouped) back in \(location)")
        case .damaged: toasts.show("\(item.sko) set as damaged", tone: .warn)
        case .notReturned: toasts.show("\(item.sko) set as not returned", tone: .warn)
        }
    }
}
