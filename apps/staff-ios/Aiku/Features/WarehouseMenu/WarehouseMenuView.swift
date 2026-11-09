import SwiftUI

struct WarehouseMenuView: View {
    @Environment(AppRouter.self) private var router
    @Environment(\.signOut) private var signOut
    @State private var searchText = ""

    private struct MenuItem: Identifiable {
        let title: String
        let icon: String
        var note: String?
        var noteTone: BadgeTone = .neutral
        let destination: Destination

        var id: String { title }
    }

    private enum Destination {
        case tab(MainTab)
        case route(Route)
    }

    private struct MenuSection: Identifiable {
        let title: String
        var caption: String?
        let items: [MenuItem]

        var id: String { title }
    }

    private let sections: [MenuSection] = [
        MenuSection(title: "Inventory", items: [
            MenuItem(title: "Dashboard", icon: AppIcon.dashboard, destination: .route(.placeholder("Inventory dashboard"))),
            MenuItem(title: "SKUs Families", icon: "square.grid.2x2", destination: .route(.placeholder("SKUs Families"))),
            MenuItem(title: "SKUs", icon: AppIcon.inventory, note: "17 low", noteTone: .warning, destination: .tab(.inventory)),
        ]),
        MenuSection(title: "Locations", items: [
            MenuItem(title: "Warehouse", icon: "building.2", destination: .route(.placeholder("Warehouse"))),
            MenuItem(title: "Areas", icon: "square.split.2x2", destination: .route(.placeholder("Areas"))),
            MenuItem(title: "Locations", icon: AppIcon.locations, destination: .route(.locations)),
        ]),
        MenuSection(title: "Goods in", items: [
            MenuItem(title: "Backlog", icon: "tray.full", destination: .route(.placeholder("Goods in backlog"))),
            MenuItem(title: "Stock deliveries", icon: AppIcon.goodsIn, note: "2 received", noteTone: .success, destination: .tab(.goodsIn)),
        ]),
        MenuSection(title: "Goods out", items: [
            MenuItem(title: "Backlog", icon: "tray.full", note: "12 unassigned", noteTone: .danger, destination: .route(.placeholder("Goods out backlog"))),
            MenuItem(title: "Delivery notes", icon: AppIcon.goodsOut, destination: .tab(.goodsOut)),
            MenuItem(title: "Shippers", icon: "truck.box", destination: .route(.placeholder("Shippers"))),
        ]),
        MenuSection(title: "Fulfilment", caption: "Only with fulfilment permission", items: [
            MenuItem(title: "Stored pallets", icon: "rectangle.stack", destination: .route(.placeholder("Stored pallets"))),
            MenuItem(title: "Stored items", icon: "shippingbox", destination: .route(.placeholder("Stored items"))),
            MenuItem(title: "Fulfilment deliveries", icon: "arrow.down.circle", destination: .route(.placeholder("Fulfilment deliveries"))),
            MenuItem(title: "Fulfilment Returns", icon: "arrow.uturn.backward.circle", destination: .route(.placeholder("Fulfilment Returns"))),
        ]),
    ]

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: "Warehouse", subtitle: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code)", trailing: .none) {
                SearchBar(text: $searchText, placeholder: "Search SKU, location, delivery note")
            }
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    ForEach(filteredSections) { section in
                        sectionView(section)
                    }
                    Button(role: .destructive) {
                        signOut()
                    } label: {
                        Text("Log out")
                            .font(.headline)
                            .foregroundStyle(AikuColor.danger)
                            .frame(maxWidth: .infinity, minHeight: 52)
                            .card(cornerRadius: 10)
                    }
                    .accessibilityIdentifier("menu.logout")
                }
                .padding(16)
            }
        }
        .aikuScreen()
        .mainTabBar(highlightsSelection: false)
    }

    private var filteredSections: [MenuSection] {
        let query = searchText.trimmingCharacters(in: .whitespaces)
        guard !query.isEmpty else { return sections }
        return sections.compactMap { section in
            let items = section.items.filter { $0.title.localizedCaseInsensitiveContains(query) || section.title.localizedCaseInsensitiveContains(query) }
            return items.isEmpty ? nil : MenuSection(title: section.title, caption: section.caption, items: items)
        }
    }

    private func sectionView(_ section: MenuSection) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 8) {
                Text(section.title.uppercased())
                    .font(.caption.weight(.bold))
                    .tracking(0.6)
                    .foregroundStyle(AikuColor.textSecondary)
                if let caption = section.caption {
                    Text(caption).font(.caption2).foregroundStyle(AikuColor.textMuted)
                }
            }
            .padding(.horizontal, 4)
            VStack(spacing: 0) {
                ForEach(Array(section.items.enumerated()), id: \.element.id) { index, item in
                    if index > 0 { RowDivider() }
                    Button {
                        switch item.destination {
                        case .tab(let tab): router.select(tab)
                        case .route(let route): router.open(route)
                        }
                    } label: {
                        HStack(spacing: 12) {
                            Image(systemName: item.icon)
                                .font(.system(size: 17))
                                .foregroundStyle(AikuColor.indigo)
                                .frame(width: 36, height: 36)
                                .background(AikuColor.indigoSoft, in: RoundedRectangle(cornerRadius: 8))
                            Text(item.title)
                                .font(.body.weight(.medium))
                                .foregroundStyle(AikuColor.textPrimary)
                            Spacer()
                            if let note = item.note {
                                StatusBadge(text: note, tone: item.noteTone)
                            }
                            ChevronIcon()
                        }
                        .padding(.horizontal, 16)
                        .frame(minHeight: 56)
                    }
                }
            }
            .card()
        }
    }
}

#Preview {
    WarehouseMenuView()
        .environment(AppRouter())
}
