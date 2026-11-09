import SwiftUI

struct GoodsInView: View {
    @Environment(AppRouter.self) private var router
    @State private var section = Section.stockDeliveries
    @State private var filter = StockDeliveryFilter()

    enum Section: Hashable {
        case backlog
        case stockDeliveries
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: "Goods in", subtitle: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code)", showsBack: false) {
                SectionSwitcher(items: [
                    SectionSwitcherItem(id: .backlog, title: "Backlog"),
                    SectionSwitcherItem(id: .stockDeliveries, title: "Stock deliveries"),
                ], selection: $section)
                if section == .stockDeliveries {
                    SearchBar(text: $filter.query, placeholder: "Reference", scanLabel: "Scan delivery barcode") {
                        router.scan(ScanContext(title: "Goods in · Find delivery", reference: nil))
                    }
                }
            }
            switch section {
            case .stockDeliveries:
                list
            case .backlog:
                ScrollView {
                    EmptyStateView(systemImage: "hammer", title: "Goods in backlog", message: "This section has no mobile design yet.")
                        .padding(16)
                }
            }
        }
        .aikuScreen()
        .mainTabBar()
    }

    private var list: some View {
        let deliveries = filter.apply(to: SampleData.stockDeliveries)
        let summary = SampleData.goodsIn
        return ScrollView {
            VStack(spacing: 10) {
                FilterChips(items: [
                    FilterChipItem(id: StockDeliveryStateFilter.open, title: "Open"),
                    FilterChipItem(id: .state(.dispatched), title: "Dispatched \(summary.dispatched)"),
                    FilterChipItem(id: .state(.received), title: "Received \(summary.received)"),
                    FilterChipItem(id: .state(.checked), title: "Checked \(summary.checked)"),
                ], selection: $filter.state)
                .padding(.horizontal, -16)

                VStack(alignment: .leading, spacing: 2) {
                    Text("Sorted by next step")
                    Text("Flow: Dispatched → Received → Checked → Placed")
                }
                .font(.caption)
                .foregroundStyle(AikuColor.textSecondary)
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(.horizontal, 4)

                if deliveries.isEmpty {
                    EmptyStateView(systemImage: AppIcon.goodsIn, title: "No stock deliveries", message: "Nothing matches this filter.")
                } else {
                    VStack(spacing: 0) {
                        ForEach(Array(deliveries.enumerated()), id: \.element.id) { index, delivery in
                            if index > 0 { RowDivider() }
                            Button {
                                router.open(.stockDelivery(delivery.reference))
                            } label: {
                                StockDeliveryRow(delivery: delivery)
                            }
                        }
                    }
                    .card()
                    ListFooterNote(text: "Placed and cancelled deliveries are in “All”")
                }
            }
            .padding(16)
        }
    }
}

struct StockDeliveryRow: View {
    let delivery: StockDelivery

    var body: some View {
        HStack(spacing: 12) {
            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 8) {
                    Text(delivery.reference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                    StatusBadge(text: delivery.state.label, tone: delivery.state.tone)
                }
                Text("Supplier \(delivery.supplier) · \(delivery.timing)")
                    .font(.caption)
                    .foregroundStyle(AikuColor.textSecondary)
                    .lineLimit(2)
                if let nextAction = delivery.nextAction {
                    Text(nextAction)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(AikuColor.navy)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(AikuColor.lavender, in: RoundedRectangle(cornerRadius: 6))
                }
            }
            Spacer(minLength: 8)
            ChevronIcon()
        }
        .multilineTextAlignment(.leading)
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .contentShape(Rectangle())
    }
}

#Preview {
    GoodsInView()
        .environment(AppRouter())
}
