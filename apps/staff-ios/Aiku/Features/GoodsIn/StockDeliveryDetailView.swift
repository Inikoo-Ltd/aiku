import SwiftUI

struct StockDeliveryDetailView: View {
    @Environment(AppRouter.self) private var router
    @State private var delivery: StockDelivery
    @State private var tab = Tab.items
    @State private var draftQuantity: Int

    enum Tab: Hashable {
        case supplierDelivery
        case items
        case attachments
        case history
        case data
    }

    init(delivery: StockDelivery) {
        _delivery = State(initialValue: delivery)
        let current = delivery.items.first { $0.received == nil }
        _draftQuantity = State(initialValue: current?.expected ?? 0)
    }

    private var currentIndex: Int? {
        delivery.items.firstIndex { $0.received == nil }
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: delivery.reference, eyebrow: "Supplier delivery", trailing: .more) {
                DetailTabs(items: [
                    SectionSwitcherItem(id: .supplierDelivery, title: "Supplier delivery"),
                    SectionSwitcherItem(id: .items, title: "Items"),
                    SectionSwitcherItem(id: .attachments, systemImage: "paperclip", accessibilityLabel: "Attachments"),
                    SectionSwitcherItem(id: .history, systemImage: "clock.arrow.circlepath", accessibilityLabel: "History"),
                    SectionSwitcherItem(id: .data, systemImage: "doc.text", accessibilityLabel: "Data"),
                ], selection: $tab)
            }
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    switch tab {
                    case .items:
                        stateCard
                        scanPanel
                        itemsSection
                    case .supplierDelivery:
                        stateCard
                    case .attachments:
                        EmptyStateView(systemImage: "paperclip", title: "Attachments", message: "Delivery documents will load from Aiku.")
                    case .history:
                        EmptyStateView(systemImage: "clock.arrow.circlepath", title: "History", message: "Changes to this delivery will load from Aiku.")
                    case .data:
                        EmptyStateView(systemImage: "doc.text", title: "Data", message: "Raw delivery data will load from Aiku.")
                    }
                }
                .padding(16)
            }
        }
        .aikuScreen()
        .bottomActionBar {
            ActionButton(title: "Scan", systemImage: AppIcon.scan, kind: .scan, expands: false) {
                openCamera()
            }
            ActionButton(title: currentIndex == nil ? "All items checked" : "Confirm item · next") {
                confirmCurrentItem()
            }
            .disabled(currentIndex == nil)
        }
    }

    private var stateCard: some View {
        VStack(alignment: .leading, spacing: 16) {
            StateStepper(steps: StockDeliveryState.allCases.map(\.label), currentIndex: StockDeliveryState.allCases.firstIndex(of: delivery.state) ?? 0)
            Grid(alignment: .leading, horizontalSpacing: 16, verticalSpacing: 4) {
                GridRow {
                    Text("Supplier").font(.caption).foregroundStyle(AikuColor.textSecondary)
                    Text("Received").font(.caption).foregroundStyle(AikuColor.textSecondary)
                }
                GridRow {
                    Text(delivery.supplier).font(.subheadline.weight(.semibold))
                    Text(delivery.receivedAt ?? "—").font(.subheadline.weight(.semibold))
                }
            }
            .foregroundStyle(AikuColor.textPrimary)
        }
        .padding(16)
        .card()
    }

    private var scanPanel: some View {
        ScanPanel(
            title: "Scan items to receive",
            subtitle: "Scanner device ready · just scan, each scan adds 1",
            primaryTitle: "Use camera",
            primaryAction: openCamera,
            secondaryTitle: "Type code",
            secondaryAction: {}
        )
    }

    private var itemsSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text("Items").font(.headline)
                Spacer()
                (Text("\(delivery.checkedCount)").bold().foregroundStyle(AikuColor.textPrimary)
                    + Text(" of \(delivery.items.count) checked").foregroundStyle(AikuColor.textSecondary))
                    .font(.subheadline)
            }
            .padding(.horizontal, 4)
            if delivery.items.isEmpty {
                EmptyStateView(systemImage: "shippingbox", title: "No items yet", message: "Items on this delivery will load from Aiku.")
            } else {
                VStack(spacing: 0) {
                    ForEach(Array(delivery.items.enumerated()), id: \.element.id) { index, item in
                        if index > 0 { RowDivider() }
                        if index == currentIndex {
                            currentItemRow(item)
                        } else {
                            itemRow(item)
                        }
                    }
                }
                .card()
            }
        }
    }

    private func itemRow(_ item: StockDeliveryItem) -> some View {
        let background: Color = item.received == nil ? .clear : item.isComplete ? AikuColor.successSoft : AikuColor.warningSoft
        return HStack(spacing: 12) {
            statusIcon(for: item)
            VStack(alignment: .leading, spacing: 2) {
                Text(item.skuReference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                Text(item.received != nil && !item.isComplete ? "Short by \(item.shortfall)" : item.name)
                    .font(.caption)
                    .foregroundStyle(item.received != nil && !item.isComplete ? AikuColor.warning : AikuColor.textSecondary)
                    .lineLimit(1)
            }
            Spacer()
            Text("\(item.received.map(String.init) ?? "—") / \(item.expected)")
                .font(.subheadline.bold())
                .monospacedDigit()
                .foregroundStyle(AikuColor.textPrimary)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(background)
    }

    private func currentItemRow(_ item: StockDeliveryItem) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 12) {
                Image(systemName: "arrow.right.circle.fill")
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.indigo)
                VStack(alignment: .leading, spacing: 2) {
                    Text(item.skuReference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                    Text("\(item.name) · expected \(item.expected)")
                        .font(.caption)
                        .foregroundStyle(AikuColor.textSecondary)
                }
            }
            HStack {
                Text("Received").font(.subheadline).foregroundStyle(AikuColor.textSecondary)
                Spacer()
                QuantityStepper(value: $draftQuantity, label: "received quantity")
            }
        }
        .padding(16)
        .overlay(RoundedRectangle(cornerRadius: 0).stroke(AikuColor.indigo, lineWidth: 2))
    }

    private func statusIcon(for item: StockDeliveryItem) -> some View {
        Group {
            if item.received == nil {
                Image(systemName: "circle").foregroundStyle(AikuColor.textFaint)
            } else if item.isComplete {
                Image(systemName: "checkmark.circle.fill").foregroundStyle(AikuColor.success)
            } else {
                Image(systemName: "exclamationmark.triangle.fill").foregroundStyle(AikuColor.warning)
            }
        }
        .font(.system(size: 20))
    }

    private func confirmCurrentItem() {
        guard let index = currentIndex else { return }
        delivery.items[index].received = draftQuantity
        draftQuantity = delivery.items.first { $0.received == nil }?.expected ?? 0
    }

    private func openCamera() {
        router.scan(ScanContext(title: "Goods in · Receiving", reference: delivery.reference))
    }
}

struct StateStepper: View {
    let steps: [String]
    let currentIndex: Int

    var body: some View {
        HStack(spacing: 0) {
            ForEach(Array(steps.enumerated()), id: \.offset) { index, step in
                VStack(spacing: 6) {
                    ZStack {
                        Circle()
                            .fill(index < currentIndex ? AikuColor.success : index == currentIndex ? AikuColor.indigo : AikuColor.background)
                            .frame(width: 28, height: 28)
                        if index < currentIndex {
                            Image(systemName: "checkmark").font(.caption.bold()).foregroundStyle(.white)
                        } else {
                            Text("\(index + 1)")
                                .font(.caption.bold())
                                .foregroundStyle(index == currentIndex ? .white : AikuColor.textSecondary)
                        }
                    }
                    Text(step)
                        .font(.caption2.weight(index == currentIndex ? .bold : .medium))
                        .foregroundStyle(index <= currentIndex ? AikuColor.textPrimary : AikuColor.textSecondary)
                }
                .frame(maxWidth: .infinity)
                .accessibilityElement(children: .combine)
                .accessibilityAddTraits(index == currentIndex ? .isSelected : [])
            }
        }
    }
}

struct ScanPanel: View {
    let title: String
    let subtitle: String
    let primaryTitle: String
    let primaryAction: () -> Void
    let secondaryTitle: String
    let secondaryAction: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(spacing: 12) {
                Image(systemName: AppIcon.scan)
                    .font(.system(size: 22, weight: .semibold))
                    .foregroundStyle(AikuColor.yellow)
                VStack(alignment: .leading, spacing: 2) {
                    Text(title).font(.headline).foregroundStyle(.white)
                    HStack(spacing: 6) {
                        Circle().fill(Color.green).frame(width: 8, height: 8)
                        Text(subtitle).font(.caption).foregroundStyle(AikuColor.indigoMuted)
                    }
                }
            }
            HStack(spacing: 10) {
                Button(action: primaryAction) {
                    Label(primaryTitle, systemImage: "camera")
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(AikuColor.navy)
                        .frame(maxWidth: .infinity, minHeight: 44)
                        .background(AikuColor.yellow, in: RoundedRectangle(cornerRadius: 8))
                }
                Button(action: secondaryAction) {
                    Text(secondaryTitle)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(.white)
                        .frame(maxWidth: .infinity, minHeight: 44)
                        .overlay(RoundedRectangle(cornerRadius: 8).stroke(Color.white.opacity(0.4)))
                }
            }
        }
        .padding(16)
        .background(AikuColor.navy, in: RoundedRectangle(cornerRadius: 12))
    }
}

#Preview {
    StockDeliveryDetailView(delivery: SampleData.stockDeliveries[0])
        .environment(AppRouter())
}
