import SwiftUI

struct LocationDetailView: View {
    @Environment(AppRouter.self) private var router
    let location: WarehouseLocation
    @State private var tab = Tab.overview

    enum Tab: Hashable {
        case overview
        case skus
        case pallets
        case movements
        case history
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: location.code, eyebrow: "Location", trailing: .more) {
                DetailTabs(items: [
                    SectionSwitcherItem(id: .overview, title: "Overview"),
                    SectionSwitcherItem(id: .skus, title: "SKUs"),
                    SectionSwitcherItem(id: .pallets, title: "Pallets"),
                    SectionSwitcherItem(id: .movements, title: "Stock movements"),
                    SectionSwitcherItem(id: .history, systemImage: "clock.arrow.circlepath", accessibilityLabel: "History"),
                ], selection: $tab)
            }
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    switch tab {
                    case .overview:
                        details
                        skusSection(limit: 3)
                        movementsSection
                    case .skus:
                        skusSection(limit: nil)
                    case .movements:
                        movementsSection
                    case .pallets:
                        EmptyStateView(systemImage: "rectangle.stack", title: "Pallets", message: "Pallets stored here will load from Aiku.")
                    case .history:
                        EmptyStateView(systemImage: "clock.arrow.circlepath", title: "History", message: "Changes to this location will load from Aiku.")
                    }
                }
                .padding(16)
            }
        }
        .aikuScreen()
        .bottomActionBar {
            ActionButton(title: "", systemImage: AppIcon.scan, kind: .scan, expands: false) {
                router.scan(ScanContext(title: "Location \(location.code)", reference: location.code))
            }
            ActionButton(title: "Stock audit", kind: .secondary) {}
            ActionButton(title: "Location transfer") {}
        }
    }

    private var details: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(spacing: 6) {
                switch location.status {
                case .operational: StatusBadge(text: "Operational", tone: .success)
                case .auditInProcess: StatusBadge(text: "Audit in process", tone: .progress)
                case .broken: StatusBadge(text: "Broken", tone: .danger)
                }
                if location.allowsStocks { StatusBadge(text: "Stocks", tone: .indigo) }
                if location.allowsFulfilment { StatusBadge(text: "Fulfilment", tone: .indigo) }
            }
            Grid(alignment: .leading, horizontalSpacing: 16, verticalSpacing: 12) {
                GridRow {
                    detail("Warehouse area", location.area)
                    detail("Stock value", location.stockValue?.wholePoundsFormatted ?? "—")
                }
                GridRow {
                    detail("Weight", "\(location.weightKg.trimmed) / \(location.maxWeightKg.trimmed) kg")
                    detail("CBM (Cubic meter)", "\(location.cbm.trimmed) / \(location.maxCbm.trimmed)")
                }
            }
            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text("Capacity used (weight)")
                    Spacer()
                    Text("\(Int((location.weightUsage * 100).rounded()))%")
                }
                .font(.caption)
                .foregroundStyle(AikuColor.textSecondary)
                ProgressBar(value: location.weightUsage)
            }
        }
        .padding(16)
        .card()
    }

    private func detail(_ label: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(label).font(.caption).foregroundStyle(AikuColor.textSecondary)
            Text(value).font(.subheadline.weight(.semibold)).foregroundStyle(AikuColor.textPrimary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func skusSection(limit: Int?) -> some View {
        let skus = limit.map { Array(location.skus.prefix($0)) } ?? location.skus
        return VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "SKUs · \(location.skus.count)", actionTitle: limit == nil ? nil : "View all") { tab = .skus }
            if skus.isEmpty {
                EmptyStateView(systemImage: "shippingbox", title: "No SKUs", message: "This location is empty.")
            } else {
                VStack(spacing: 0) {
                    ForEach(Array(skus.enumerated()), id: \.element.id) { index, item in
                        if index > 0 { RowDivider() }
                        Button {
                            router.open(.sku(item.skuReference))
                        } label: {
                            HStack(spacing: 12) {
                                VStack(alignment: .leading, spacing: 3) {
                                    Text(item.skuReference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                                    Text(item.name).font(.caption).foregroundStyle(AikuColor.textSecondary).lineLimit(1)
                                }
                                Spacer()
                                Text(item.quantity.grouped).font(.body.bold()).foregroundStyle(AikuColor.textPrimary)
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

    private var movementsSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "Stock movements")
            if location.movements.isEmpty {
                EmptyStateView(systemImage: "arrow.left.arrow.right", title: "No movements", message: "Stock moving in or out of this location will show here.")
            } else {
                VStack(spacing: 0) {
                    ForEach(Array(location.movements.enumerated()), id: \.element.id) { index, movement in
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
    LocationDetailView(location: SampleData.locations[0])
        .environment(AppRouter())
}
