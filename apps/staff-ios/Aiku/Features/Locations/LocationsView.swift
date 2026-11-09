import SwiftUI

struct LocationsView: View {
    @Environment(AppRouter.self) private var router
    @State private var section = Section.locations
    @State private var filter = LocationFilter()

    enum Section: Hashable {
        case warehouse
        case areas
        case locations
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(
                title: "Locations",
                subtitle: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code) · \(SampleData.totalLocations.grouped) locations"
            ) {
                SectionSwitcher(items: [
                    SectionSwitcherItem(id: .warehouse, title: "Warehouse"),
                    SectionSwitcherItem(id: .areas, title: "Areas"),
                    SectionSwitcherItem(id: .locations, title: "Locations"),
                ], selection: $section)
                if section == .locations {
                    SearchBar(text: $filter.query, placeholder: "Location code", scanLabel: "Scan location barcode") {
                        router.scan(ScanContext(title: "Locations · Find location", reference: nil))
                    }
                }
            }
            switch section {
            case .locations:
                list
            case .warehouse, .areas:
                ScrollView {
                    EmptyStateView(systemImage: "hammer", title: section == .warehouse ? "Warehouse" : "Areas", message: "This section has no mobile design yet.")
                        .padding(16)
                }
            }
        }
        .aikuScreen()
        .mainTabBar(highlightsSelection: false)
    }

    private var list: some View {
        let locations = filter.apply(to: SampleData.locations)
        return ScrollView {
            VStack(spacing: 10) {
                FilterChips(items: [
                    FilterChipItem(id: LocationAreaFilter.all, title: "All areas"),
                    FilterChipItem(id: .area("Area A"), title: "Area A"),
                    FilterChipItem(id: .area("Area B"), title: "Area B"),
                    FilterChipItem(id: .broken, title: "Broken", dot: AikuColor.dangerDot),
                ], selection: $filter.area)
                .padding(.horizontal, -16)

                HStack(spacing: 12) {
                    Text("Sorted by code")
                    Spacer()
                    Label("Stocks", systemImage: "shippingbox").labelStyle(.titleAndIcon)
                    Label("Fulfilment", systemImage: "rectangle.stack").labelStyle(.titleAndIcon)
                }
                .font(.caption)
                .foregroundStyle(AikuColor.textSecondary)
                .padding(.horizontal, 4)

                if locations.isEmpty {
                    EmptyStateView(systemImage: AppIcon.search, title: "No locations found", message: "Try another code or area.")
                } else {
                    VStack(spacing: 0) {
                        ForEach(Array(locations.enumerated()), id: \.element.id) { index, location in
                            if index > 0 { RowDivider() }
                            Button {
                                router.open(.location(location.code))
                            } label: {
                                LocationRow(location: location)
                            }
                        }
                    }
                    .card()
                    ListFooterNote(text: "Showing \(locations.count) of \(SampleData.totalLocations.grouped) · scroll for more")
                }
            }
            .padding(16)
        }
    }
}

struct LocationRow: View {
    let location: WarehouseLocation

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: location.allowsFulfilment ? "rectangle.stack" : "shippingbox")
                .font(.system(size: 16))
                .foregroundStyle(location.status == .broken ? AikuColor.danger : AikuColor.indigo)
                .frame(width: 36, height: 36)
                .background(location.status == .broken ? AikuColor.dangerSoft : AikuColor.indigoSoft, in: RoundedRectangle(cornerRadius: 8))
            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 8) {
                    Text(location.code).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                    switch location.status {
                    case .operational: EmptyView()
                    case .auditInProcess: StatusBadge(text: "Audit in process", tone: .progress)
                    case .broken: StatusBadge(text: "Broken", tone: .danger)
                    }
                }
                Text(location.summary)
                    .font(.caption)
                    .foregroundStyle(AikuColor.textSecondary)
                    .lineLimit(1)
            }
            Spacer(minLength: 8)
            VStack(alignment: .trailing, spacing: 2) {
                Text(location.stockValue?.wholePoundsFormatted ?? "—")
                    .font(.subheadline.bold())
                    .foregroundStyle(AikuColor.textPrimary)
                Text("stock value").font(.caption2).foregroundStyle(AikuColor.textSecondary)
            }
            ChevronIcon()
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .contentShape(Rectangle())
    }
}

#Preview {
    LocationsView()
        .environment(AppRouter())
}
