import SwiftUI

struct InventoryView: View {
    @Environment(AppRouter.self) private var router
    @State private var section = InventorySection.skus
    @State private var filter = SKUFilter()

    enum InventorySection: Hashable {
        case dashboard
        case families
        case skus
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(
                title: "Inventory",
                subtitle: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code) · \(SampleData.totalSKUs.grouped) SKUs",
                showsBack: false
            ) {
                SectionSwitcher(items: [
                    SectionSwitcherItem(id: .dashboard, systemImage: AppIcon.dashboard, accessibilityLabel: "Inventory dashboard"),
                    SectionSwitcherItem(id: .families, title: "SKUs Families", systemImage: "square.grid.2x2"),
                    SectionSwitcherItem(id: .skus, title: "SKUs", systemImage: AppIcon.inventory),
                ], selection: $section)
                if section == .skus {
                    SearchBar(text: $filter.query, placeholder: "Reference or name", scanLabel: "Scan SKU barcode") {
                        router.scan(ScanContext(title: "Inventory · Find SKU", reference: nil))
                    }
                }
            }
            switch section {
            case .skus:
                skuList
            case .dashboard:
                comingSoon("Inventory dashboard")
            case .families:
                comingSoon("SKUs Families")
            }
        }
        .aikuScreen()
        .mainTabBar()
    }

    private var skuList: some View {
        let skus = filter.apply(to: SampleData.skus)
        return ScrollView {
            VStack(spacing: 10) {
                FilterChips(items: [
                    FilterChipItem(id: SKULevelFilter.all, title: "All"),
                    FilterChipItem(id: .low, title: "Low \(SampleData.stockLevelCounts[.low] ?? 0)", dot: StockLevel.low.dot),
                    FilterChipItem(id: .critical, title: "Critical \(SampleData.stockLevelCounts[.critical] ?? 0)", dot: StockLevel.critical.dot),
                    FilterChipItem(id: .outOfStock, title: "Out of stock \(SampleData.stockLevelCounts[.outOfStock] ?? 0)", dot: StockLevel.outOfStock.dot),
                ], selection: $filter.level)
                .padding(.horizontal, -16)

                HStack {
                    Text("Sorted by reference")
                    Spacer()
                    Text("Stock · unit value")
                }
                .font(.caption)
                .foregroundStyle(AikuColor.textSecondary)
                .padding(.horizontal, 4)

                if skus.isEmpty {
                    EmptyStateView(systemImage: AppIcon.search, title: "No SKUs found", message: "Try another reference, name or filter.")
                } else {
                    VStack(spacing: 0) {
                        ForEach(Array(skus.enumerated()), id: \.element.id) { index, sku in
                            if index > 0 { RowDivider() }
                            Button {
                                router.open(.sku(sku.reference))
                            } label: {
                                SKURow(sku: sku)
                            }
                        }
                    }
                    .card()
                    ListFooterNote(text: "Showing \(skus.count) of \(SampleData.totalSKUs.grouped) · scroll for more")
                }
            }
            .padding(16)
        }
    }

    private func comingSoon(_ title: String) -> some View {
        ScrollView {
            EmptyStateView(systemImage: "hammer", title: title, message: "This section has no mobile design yet.")
                .padding(16)
        }
    }
}

struct SKURow: View {
    let sku: SKU

    var body: some View {
        HStack(spacing: 12) {
            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 8) {
                    Text(sku.reference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                    StatusBadge(text: sku.familyCode, tone: .indigo)
                    if sku.level != .normal {
                        StatusBadge(text: sku.level.label, tone: sku.level.tone)
                    }
                }
                Text(sku.name)
                    .font(.footnote)
                    .foregroundStyle(AikuColor.textSecondary)
                    .lineLimit(1)
            }
            Spacer(minLength: 8)
            VStack(alignment: .trailing, spacing: 2) {
                Text(sku.stock.grouped)
                    .font(.body.bold())
                    .foregroundStyle(sku.stock == 0 ? AikuColor.danger : AikuColor.textPrimary)
                Text(sku.unitValue.poundsFormatted)
                    .font(.caption2)
                    .foregroundStyle(AikuColor.textSecondary)
            }
            ChevronIcon()
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .contentShape(Rectangle())
    }
}

#Preview {
    InventoryView()
        .environment(AppRouter())
}
