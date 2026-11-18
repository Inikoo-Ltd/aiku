import SwiftUI

struct ShellWarehouseDashboardView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var isChoosingWarehouse = false
    @State private var warehouseStore = WarehouseStore.shared
    @State private var store = WarehouseDashboardStore()
    @State private var appearance = 0

    private var modules: ModuleAccess { session.visibleModules }

    var body: some View {
        AppScreen(
            title: "Dashboard",
            kicker: "Warehouse · \(session.fullContextLabel)",
            actions: [HeaderAction(.search, label: "Search") { router.open(ShellRoute.search) }]
        ) {
            Card {
                ListRow(session.contextLabel, subtitle: "\(session.profile.warehouseCode) · tap to change", icon: .warehouse, action: {
                    isChoosingWarehouse = true
                }) {
                    Text("Change")
                        .font(.aiku(14, .semibold))
                        .foregroundStyle(AikuColor.link)
                }
                .accessibilityIdentifier("shell.dashboard.changeWarehouse")
            }
            shortcuts
            if warehouseStore.selectedID == nil {
                EmptyState(icon: .warehouse, title: "Choose a warehouse", message: "The dashboard shows one warehouse at a time.")
            } else {
                figures
            }
            HelperText("Your assigned work is in My work.")
        }
        .screenRefreshable { await reload() }
        .onAppear { appearance += 1 }
        .task(id: ListLoadKey(request: warehouseStore.selectedID, appearance: appearance)) { await reload() }
        .shellWarehouseSheet(isPresented: $isChoosingWarehouse)
    }

    @ViewBuilder
    private var figures: some View {
        if let message = store.loadError {
            InlineAlert(title: "Couldn’t load the dashboard", message: message) {
                LinkButton("Try again", size: 13) {
                    Task { await reload() }
                }
                .frame(minHeight: 44, alignment: .leading)
            }
        }
        if store.isShowingSkeleton {
            skeletonPanel
            skeletonPanel
        } else if let dashboard = store.dashboard {
            if let goodsOut = dashboard.goodsOut {
                panel(
                    title: "Goods out",
                    subtitle: WarehouseDashboardText.goodsOutSubtitle(goodsOut),
                    link: .goodsOut(.backlog),
                    tiles: WarehouseDashboardText.tiles(goodsOut),
                    figure: WarehouseDashboardText.dispatched(goodsOut)
                )
            }
            if let goodsIn = dashboard.goodsIn {
                panel(
                    title: "Goods in",
                    subtitle: WarehouseDashboardText.goodsInSubtitle,
                    link: .goodsIn(.backlog),
                    tiles: WarehouseDashboardText.tiles(goodsIn),
                    figure: WarehouseDashboardText.returnsToProcess(goodsIn)
                )
            }
            if let inventory = dashboard.inventory {
                panel(
                    title: "Inventory",
                    subtitle: WarehouseDashboardText.inventorySubtitle(inventory),
                    link: .inventory(.dashboard),
                    tiles: WarehouseDashboardText.tiles(inventory),
                    figure: nil
                )
            }
        }
    }

    private var skeletonPanel: some View {
        Card(padding: 14, spacing: 12) {
            SkeletonBlock(width: 120, height: 14)
            SkeletonBlock(width: 200, height: 10)
            HStack(spacing: 8) {
                ForEach(0..<4, id: \.self) { _ in
                    SkeletonBlock(height: 44, radius: 8)
                }
            }
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("Loading")
    }

    private var shortcuts: some View {
        LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 8), count: 5), spacing: 8) {
            ForEach(ShellDashboardContent.tiles(for: modules)) { tile in
                Button {
                    router.shellFollow(tile.link)
                } label: {
                    VStack(spacing: 6) {
                        IconTile(tile.icon, tone: .info, size: 40, radius: 10)
                        Text(tile.title)
                            .font(.aiku(11, .semibold))
                            .foregroundStyle(AikuColor.text2)
                            .lineLimit(1)
                            .minimumScaleFactor(0.7)
                    }
                    .padding(.vertical, 10)
                    .padding(.horizontal, 2)
                    .frame(maxWidth: .infinity)
                    .cardSurface(radius: 10)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityIdentifier("shell.dashboard.tile.\(tile.id)")
            }
        }
    }

    private func panel(title: String, subtitle: String, link: ShellLink, tiles: [WarehouseDashboardTile], figure: WarehouseDashboardFigure?) -> some View {
        Card(padding: 14, spacing: 10) {
            Button {
                router.shellFollow(link)
            } label: {
                HStack {
                    VStack(alignment: .leading, spacing: 2) {
                        Text(title)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                        Text(subtitle)
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text3)
                    }
                    Spacer()
                    Text("View")
                        .font(.aiku(13, .semibold))
                        .foregroundStyle(AikuColor.link)
                }
                .frame(minHeight: 44)
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityLabel("\(title), \(subtitle)")
            .accessibilityHint("View")
            LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 8), count: WarehouseDashboardText.columns(for: tiles.count)), spacing: 8) {
                ForEach(tiles) { tile in
                    countTile(tile)
                }
            }
            if let figure {
                figureLine(figure)
            }
        }
    }

    private func countTile(_ tile: WarehouseDashboardTile) -> some View {
        let isNeutral = tile.tone == .neutral
        return Button {
            router.shellFollow(tile.link)
        } label: {
            VStack(spacing: 2) {
                Text(tile.value.grouped)
                    .font(.aiku(17, .bold))
                    .foregroundStyle(isNeutral ? AikuColor.text2 : tile.tone.foreground)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
                Text(tile.label)
                    .font(.aiku(10, .semibold))
                    .foregroundStyle(AikuColor.text3)
                    .lineLimit(1)
                    .minimumScaleFactor(0.8)
            }
            .padding(.vertical, 8)
            .padding(.horizontal, 2)
            .frame(maxWidth: .infinity, minHeight: 44)
            .background(tile.tone.background, in: RoundedRectangle(cornerRadius: 8))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("shell.dashboard.count.\(tile.id)")
    }

    @ViewBuilder
    private func figureLine(_ figure: WarehouseDashboardFigure) -> some View {
        let line = HStack {
            Text(figure.label)
                .foregroundStyle(AikuColor.text3)
            Spacer()
            Text(figure.value)
                .bold()
                .foregroundStyle(AikuColor.text)
        }
        .font(.aiku(12))
        if let link = figure.link {
            Button {
                router.shellFollow(link)
            } label: {
                line
                    .frame(minHeight: 44)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityElement(children: .combine)
        } else {
            line
                .accessibilityElement(children: .combine)
        }
    }

    private func reload() async {
        await store.load(warehouseID: warehouseStore.selectedID)
    }
}
