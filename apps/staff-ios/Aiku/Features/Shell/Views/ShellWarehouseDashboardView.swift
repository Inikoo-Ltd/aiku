import SwiftUI

struct ShellWarehouseDashboardView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var isChoosingWarehouse = false

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
            tiles
            panel(ShellDashboardContent.goodsOut, columns: 4) {
                VStack(spacing: 10) {
                    HStack {
                        Text("Dispatched today")
                            .foregroundStyle(AikuColor.text3)
                        Spacer()
                        Text("\(ShellDashboardContent.dispatchedToday) of \(ShellDashboardContent.dispatchTarget)")
                            .bold()
                            .foregroundStyle(AikuColor.text)
                    }
                    .font(.aiku(12))
                    ProgressBar(value: ShellDashboardContent.dispatchedFraction, height: 6)
                }
            }
            panel(ShellDashboardContent.goodsIn, columns: 4) { EmptyView() }
            panel(ShellDashboardContent.inventory, columns: 3) { EmptyView() }
            SectionHeading("Stock history · today", actionTitle: "All") {
                router.open(InventoryRoute.stockHistory)
            }
            CardList(ShellDashboardContent.movements) { movement in
                ListRow(movement.sko, subtitle: movement.detail, chips: [movement.change]) {
                    ProductThumb(size: 48, label: "Product photo of \(movement.sko)")
                } trailing: {
                    EmptyView()
                }
            }
            HelperText("Your assigned work is in My work.")
        }
        .shellWarehouseSheet(isPresented: $isChoosingWarehouse)
    }

    private var tiles: some View {
        LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 8), count: 5), spacing: 8) {
            ForEach(ShellDashboardContent.tiles) { tile in
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

    private func panel<Extra: View>(_ panel: ShellDashboardPanel, columns: Int, @ViewBuilder extra: () -> Extra) -> some View {
        Card(padding: 14, spacing: 10) {
            Button {
                router.shellFollow(panel.link)
            } label: {
                HStack {
                    VStack(alignment: .leading, spacing: 2) {
                        Text(panel.title)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                        Text(panel.subtitle)
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
            .accessibilityLabel("\(panel.title), \(panel.subtitle)")
            .accessibilityHint("View")
            LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 8), count: columns), spacing: 8) {
                ForEach(panel.stats) { stat in
                    VStack(spacing: 2) {
                        Text(stat.value)
                            .font(.aiku(17, .bold))
                            .foregroundStyle(stat.tone.foreground)
                        Text(stat.label)
                            .font(.aiku(10, .semibold))
                            .foregroundStyle(AikuColor.text3)
                            .lineLimit(1)
                            .minimumScaleFactor(0.8)
                    }
                    .padding(.vertical, 8)
                    .padding(.horizontal, 2)
                    .frame(maxWidth: .infinity)
                    .background(stat.tone.background, in: RoundedRectangle(cornerRadius: 8))
                    .accessibilityElement(children: .combine)
                }
            }
            extra()
        }
    }
}
