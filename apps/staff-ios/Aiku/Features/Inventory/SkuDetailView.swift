import SwiftUI

struct SkuDetailView: View {
    @Environment(AppRouter.self) private var router
    let sku: SKU
    @State private var tab = Tab.overview

    enum Tab: Hashable {
        case overview
        case stockHistory
        case supplierProduct
        case products
        case images
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: sku.reference, eyebrow: "SKU", trailing: .more) {
                DetailTabs(items: [
                    SectionSwitcherItem(id: .overview, title: "Overview"),
                    SectionSwitcherItem(id: .stockHistory, title: "Stock history"),
                    SectionSwitcherItem(id: .supplierProduct, title: "Supplier product"),
                    SectionSwitcherItem(id: .products, title: "Products"),
                    SectionSwitcherItem(id: .images, systemImage: "photo", accessibilityLabel: "Images"),
                ], selection: $tab)
            }
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    switch tab {
                    case .overview:
                        summary
                        stockStats
                        locationsSection
                        historySection(limit: 3)
                    case .stockHistory:
                        historySection(limit: nil)
                    case .supplierProduct:
                        EmptyStateView(systemImage: "building.2", title: "Supplier product", message: "Supplier details will load from Aiku.")
                    case .products:
                        EmptyStateView(systemImage: "tag", title: "Products", message: "Products using this SKU will load from Aiku.")
                    case .images:
                        EmptyStateView(systemImage: "photo", title: "Images", message: "SKU images will load from Aiku.")
                    }
                }
                .padding(16)
            }
        }
        .aikuScreen()
        .bottomActionBar {
            ActionButton(title: "", systemImage: AppIcon.scan, kind: .scan, expands: false) {
                router.scan(ScanContext(title: "SKU · Scan location or SKU", reference: sku.reference))
            }
            ActionButton(title: "Adjustment", kind: .secondary) {}
            ActionButton(title: "Location transfer") {}
        }
    }

    private var summary: some View {
        HStack(spacing: 14) {
            RoundedRectangle(cornerRadius: 10)
                .fill(AikuColor.background)
                .frame(width: 72, height: 72)
                .overlay(Image(systemName: "photo").foregroundStyle(AikuColor.textMuted))
                .accessibilityLabel("No image")
            VStack(alignment: .leading, spacing: 6) {
                Text(sku.name).font(.headline).foregroundStyle(AikuColor.textPrimary)
                HStack(spacing: 6) {
                    StatusBadge(text: "Family \(sku.familyCode)", tone: .indigo)
                    if sku.level != .normal {
                        StatusBadge(text: sku.level.label, tone: sku.level.tone)
                    }
                }
                Text("Unit value \(sku.unitValue.poundsFormatted)")
                    .font(.caption)
                    .foregroundStyle(AikuColor.textSecondary)
            }
            Spacer(minLength: 0)
        }
        .padding(14)
        .card()
    }

    private var stockStats: some View {
        HStack(spacing: 0) {
            stat(sku.stock.grouped, "Stock")
            Rectangle().fill(AikuColor.border).frame(width: 1, height: 40)
            stat("\(sku.locations.count)", "Locations")
            Rectangle().fill(AikuColor.border).frame(width: 1, height: 40)
            stat(sku.stockValue.wholePoundsFormatted, "Stock value")
        }
        .padding(.vertical, 14)
        .card()
    }

    private func stat(_ value: String, _ label: String) -> some View {
        VStack(spacing: 2) {
            Text(value).font(.title3.bold()).foregroundStyle(AikuColor.textPrimary)
            Text(label).font(.caption).foregroundStyle(AikuColor.textSecondary)
        }
        .frame(maxWidth: .infinity)
    }

    private var locationsSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "Locations")
            if sku.locations.isEmpty {
                EmptyStateView(systemImage: AppIcon.locations, title: "No locations", message: "This SKU has no stock in any location.")
            } else {
                VStack(spacing: 0) {
                    ForEach(Array(sku.locations.enumerated()), id: \.element.id) { index, location in
                        if index > 0 { RowDivider() }
                        Button {
                            router.open(.location(location.locationCode))
                        } label: {
                            HStack(spacing: 12) {
                                VStack(alignment: .leading, spacing: 3) {
                                    HStack(spacing: 8) {
                                        Text(location.locationCode).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                                        if location.isPicking {
                                            StatusBadge(text: "Picking", tone: .indigo)
                                        }
                                    }
                                    Text(location.note).font(.caption).foregroundStyle(AikuColor.textSecondary)
                                }
                                Spacer()
                                Text(location.quantity.grouped).font(.body.bold()).foregroundStyle(AikuColor.textPrimary)
                                ChevronIcon()
                            }
                            .padding(.horizontal, 16)
                            .padding(.vertical, 12)
                        }
                    }
                }
                .card()
            }
        }
    }

    private func historySection(limit: Int?) -> some View {
        let movements = limit.map { Array(sku.history.prefix($0)) } ?? sku.history
        return VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "Stock history", actionTitle: limit == nil ? nil : "View all") { tab = .stockHistory }
            if movements.isEmpty {
                EmptyStateView(systemImage: "clock", title: "No stock history", message: "Movements for this SKU will show here.")
            } else {
                VStack(spacing: 0) {
                    ForEach(Array(movements.enumerated()), id: \.element.id) { index, movement in
                        if index > 0 { RowDivider() }
                        MovementRow(movement: movement, showsActor: false)
                    }
                }
                .card()
            }
        }
    }
}

#Preview {
    SkuDetailView(sku: SampleData.skus[1])
        .environment(AppRouter())
}
