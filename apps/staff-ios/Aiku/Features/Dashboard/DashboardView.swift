import SwiftUI

struct DashboardView: View {
    @Environment(AppRouter.self) private var router

    private let goodsOut = SampleData.goodsOut
    private let goodsIn = SampleData.goodsIn

    var body: some View {
        VStack(spacing: 0) {
            header
            ScrollView {
                VStack(spacing: 16) {
                    quickLinks
                    goodsOutCard
                    goodsInCard
                    inventoryCard
                    assignedSection
                    movementsSection
                }
                .padding(16)
                .padding(.bottom, 16)
            }
        }
        .aikuScreen()
        .mainTabBar()
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(spacing: 10) {
                Button {
                    router.open(.profile)
                } label: {
                    Text(SampleData.currentUser.initials)
                        .font(.subheadline.bold())
                        .foregroundStyle(AikuColor.yellow)
                        .frame(width: 44, height: 44)
                        .background(AikuColor.indigoDeep, in: Circle())
                        .overlay(Circle().stroke(AikuColor.indigoRing))
                }
                .accessibilityLabel("Your profile")

                VStack(alignment: .leading, spacing: 0) {
                    Text(greeting)
                        .font(.caption)
                        .foregroundStyle(AikuColor.indigoMuted)
                    Text(SampleData.currentUser.name)
                        .font(.headline)
                        .foregroundStyle(.white)
                }
                .frame(maxWidth: .infinity, alignment: .leading)

                HeaderIconButton(systemImage: AppIcon.menu, label: "Warehouse menu") {
                    router.open(.warehouseMenu)
                }
                HeaderIconButton(systemImage: AppIcon.search, label: "Search") {
                    router.open(.warehouseMenu)
                }
                HeaderIconButton(systemImage: "bell", label: "Notifications, \(SampleData.unreadNotifications) unread") {}
                    .overlay(alignment: .topTrailing) {
                        Text("\(SampleData.unreadNotifications)")
                            .font(.system(size: 10, weight: .bold))
                            .foregroundStyle(.white)
                            .frame(minWidth: 18, minHeight: 18)
                            .background(AikuColor.dangerDot, in: Circle())
                            .offset(x: 4, y: -4)
                            .accessibilityHidden(true)
                    }
            }

            Button {} label: {
                HStack(spacing: 8) {
                    Image(systemName: "building.2")
                    Text(SampleData.warehouse.name).font(.subheadline.weight(.semibold))
                    Text(SampleData.warehouse.code)
                        .font(.caption.weight(.semibold))
                        .opacity(0.8)
                    Spacer()
                    Image(systemName: "chevron.down").font(.caption.weight(.bold))
                }
                .foregroundStyle(AikuColor.yellow)
                .padding(.horizontal, 14)
                .frame(height: 44)
                .background(AikuColor.indigoDeep, in: RoundedRectangle(cornerRadius: 8))
                .overlay(RoundedRectangle(cornerRadius: 8).stroke(AikuColor.indigoRing))
            }
            .accessibilityLabel("Change warehouse. Current: \(SampleData.warehouse.name)")
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
        .padding(.bottom, 16)
        .background(AikuColor.indigo.ignoresSafeArea(edges: .top))
    }

    private var greeting: String {
        switch Calendar.current.component(.hour, from: .now) {
        case 5..<12: "Good morning"
        case 12..<18: "Good afternoon"
        default: "Good evening"
        }
    }

    private var quickLinks: some View {
        HStack(spacing: 0) {
            quickLink("Inventory", AppIcon.inventory) { router.select(.inventory) }
            quickLink("Locations", AppIcon.locations) { router.open(.locations) }
            quickLink("Goods in", AppIcon.goodsIn) { router.select(.goodsIn) }
            quickLink("Goods out", AppIcon.goodsOut) { router.select(.goodsOut) }
        }
        .padding(.vertical, 10)
        .card()
    }

    private func quickLink(_ title: String, _ icon: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            VStack(spacing: 6) {
                Image(systemName: icon)
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.indigo)
                    .frame(width: 44, height: 44)
                    .background(AikuColor.indigoSoft, in: RoundedRectangle(cornerRadius: 10))
                Text(title)
                    .font(.caption.weight(.medium))
                    .foregroundStyle(AikuColor.textBody)
            }
            .frame(maxWidth: .infinity)
        }
    }

    private func cardHeader(_ title: String, _ subtitle: String, action: String, perform: @escaping () -> Void) -> some View {
        HStack(alignment: .top) {
            VStack(alignment: .leading, spacing: 2) {
                Text(title).font(.headline).foregroundStyle(AikuColor.textPrimary)
                Text(subtitle).font(.caption).foregroundStyle(AikuColor.textSecondary)
            }
            Spacer()
            SectionHeader(title: "", actionTitle: action, action: perform)
                .fixedSize()
        }
    }

    private var goodsOutCard: some View {
        VStack(alignment: .leading, spacing: 14) {
            cardHeader("Goods out", "Dispatching backlog · Delivery notes", action: "View") { router.select(.goodsOut) }
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 10), GridItem(.flexible(), spacing: 10)], spacing: 10) {
                statTile("Unassigned", goodsOut.unassigned)
                statTile("In Queue", goodsOut.inQueue)
                statTile("Handling", goodsOut.handling, note: "\(goodsOut.blocked) blocked")
                statTile("Packed", goodsOut.packed)
            }
            VStack(alignment: .leading, spacing: 8) {
                HStack {
                    Text("Dispatched today").font(.subheadline).foregroundStyle(AikuColor.textSecondary)
                    Spacer()
                    (Text("\(goodsOut.dispatchedToday)").bold().foregroundStyle(AikuColor.textPrimary)
                        + Text(" of \(goodsOut.plannedToday)").foregroundStyle(AikuColor.textSecondary))
                        .font(.subheadline)
                }
                ProgressBar(value: Double(goodsOut.dispatchedToday) / Double(goodsOut.plannedToday))
            }
        }
        .padding(16)
        .card()
    }

    private func statTile(_ title: String, _ value: Int, note: String? = nil) -> some View {
        Button {
            router.select(.goodsOut)
        } label: {
            VStack(alignment: .leading, spacing: 6) {
                Text(title).font(.caption.weight(.medium)).foregroundStyle(AikuColor.textSecondary)
                HStack(alignment: .firstTextBaseline, spacing: 8) {
                    Text("\(value)").font(.system(size: 26, weight: .bold)).foregroundStyle(AikuColor.textPrimary)
                    if let note {
                        Text(note).font(.caption.weight(.semibold)).foregroundStyle(AikuColor.danger)
                    }
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(12)
            .overlay(RoundedRectangle(cornerRadius: 10).stroke(AikuColor.border))
        }
    }

    private var goodsInCard: some View {
        VStack(alignment: .leading, spacing: 14) {
            cardHeader("Goods in", "Stock deliveries", action: "View") { router.select(.goodsIn) }
            HStack(spacing: 10) {
                countTile(goodsIn.dispatched, "Dispatched")
                countTile(goodsIn.received, "Received")
                countTile(goodsIn.checked, "Checked")
            }
        }
        .padding(16)
        .card()
    }

    private func countTile(_ value: Int, _ title: String) -> some View {
        Button {
            router.select(.goodsIn)
        } label: {
            VStack(spacing: 2) {
                Text("\(value)").font(.title2.bold()).foregroundStyle(AikuColor.textPrimary)
                Text(title).font(.caption.weight(.medium)).foregroundStyle(AikuColor.textSecondary)
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, 12)
            .overlay(RoundedRectangle(cornerRadius: 10).stroke(AikuColor.border))
        }
    }

    private var inventoryCard: some View {
        VStack(alignment: .leading, spacing: 14) {
            cardHeader("Inventory", "\(SampleData.totalSKUs.grouped) SKUs in this warehouse", action: "SKUs") { router.select(.inventory) }
            HStack(spacing: 10) {
                ForEach([StockLevel.low, .critical, .outOfStock], id: \.self) { level in
                    Button {
                        router.select(.inventory)
                    } label: {
                        VStack(spacing: 2) {
                            Text("\(SampleData.stockLevelCounts[level] ?? 0)").font(.title2.bold())
                            Text(level.label).font(.caption.weight(.semibold))
                        }
                        .foregroundStyle(level.tone.foreground)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 12)
                        .background(level.tone.background, in: RoundedRectangle(cornerRadius: 10))
                    }
                }
            }
        }
        .padding(16)
        .card()
    }

    private var assignedSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "Assigned to me", actionTitle: "View all") {}
            VStack(spacing: 0) {
                ForEach(Array(SampleData.assignedTasks.enumerated()), id: \.element.id) { index, task in
                    if index > 0 { RowDivider() }
                    Button {
                        router.open(route(for: task))
                    } label: {
                        assignedRow(task)
                    }
                }
            }
            .card()
        }
    }

    private func assignedRow(_ task: AssignedTask) -> some View {
        HStack(spacing: 12) {
            Image(systemName: icon(for: task.kind))
                .font(.system(size: 17))
                .foregroundStyle(AikuColor.indigo)
                .frame(width: 40, height: 40)
                .background(AikuColor.indigoSoft, in: RoundedRectangle(cornerRadius: 10))
            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 8) {
                    Text(task.reference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                    StatusBadge(text: task.status, tone: tone(for: task))
                }
                Text(task.detail).font(.caption).foregroundStyle(AikuColor.textSecondary)
            }
            Spacer()
            ChevronIcon()
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
    }

    private func route(for task: AssignedTask) -> Route {
        switch task.kind {
        case .deliveryNote: .deliveryNote(task.reference)
        case .stockDelivery: .stockDelivery(task.reference)
        case .stockAudit: .location(task.reference)
        }
    }

    private func icon(for kind: TaskKind) -> String {
        switch kind {
        case .deliveryNote: AppIcon.goodsOut
        case .stockDelivery: AppIcon.goodsIn
        case .stockAudit: "checklist"
        }
    }

    private func tone(for task: AssignedTask) -> BadgeTone {
        switch task.kind {
        case .deliveryNote: .neutral
        case .stockDelivery: .success
        case .stockAudit: .progress
        }
    }

    private var movementsSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "Stock movements", actionTitle: "View all") {}
            VStack(spacing: 0) {
                ForEach(Array(SampleData.recentMovements.enumerated()), id: \.element.id) { index, movement in
                    if index > 0 { RowDivider() }
                    MovementRow(movement: movement)
                }
            }
            .card()
        }
    }
}

#Preview {
    DashboardView()
        .environment(AppRouter())
}
