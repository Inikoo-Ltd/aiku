import SwiftUI

struct DeliveryNoteDetailView: View {
    @Environment(AppRouter.self) private var router
    @State private var note: DeliveryNote

    init(note: DeliveryNote) {
        _note = State(initialValue: note)
    }

    private var currentIndex: Int? {
        note.items.firstIndex { !$0.isComplete && !$0.isOutOfStock }
    }

    private var itemsLeft: Int {
        note.items.filter { !$0.isOutOfStock }.reduce(0) { $0 + max($1.required - $1.picked, 0) }
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: note.reference, eyebrow: "Delivery Note", trailing: .more)
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    stateCard
                    ScanPanel(
                        title: "Scan location, then item",
                        subtitle: "Scanner device ready · just scan here",
                        primaryTitle: "Use camera",
                        primaryAction: { router.scan(ScanContext(title: "Goods out · Picking", reference: note.reference)) },
                        secondaryTitle: "Guided picking",
                        secondaryAction: { router.open(.scannerDevice(deliveryNote: note.reference)) }
                    )
                    itemsSection
                }
                .padding(16)
            }
        }
        .aikuScreen()
        .bottomActionBar {
            ActionButton(title: "Scan", systemImage: AppIcon.scan, kind: .scan, expands: false) {
                router.open(.scannerDevice(deliveryNote: note.reference))
            }
            if let action = note.state.primaryAction {
                ActionButton(title: note.state == .handling && itemsLeft > 0 ? "\(action) · \(itemsLeft) left" : action) {}
            }
        }
    }

    private var stateCard: some View {
        VStack(alignment: .leading, spacing: 14) {
            VStack(alignment: .leading, spacing: 4) {
                StatusBadge(text: note.state.label, tone: note.state.tone)
                Text("\(note.customer) · \(note.shop) · \(note.itemCount) items · \(note.weightKg.trimmed) kg")
                    .font(.subheadline)
                    .foregroundStyle(AikuColor.textSecondary)
            }
            if let picker = note.picker {
                HStack(spacing: 12) {
                    Text(StaffMember(name: picker).initials)
                        .font(.caption.bold())
                        .foregroundStyle(AikuColor.yellow)
                        .frame(width: 36, height: 36)
                        .background(AikuColor.indigoDeep, in: Circle())
                    VStack(alignment: .leading, spacing: 0) {
                        Text("Picker").font(.caption).foregroundStyle(AikuColor.textSecondary)
                        Text(picker == SampleData.currentUser.name ? "\(picker) (you)" : picker)
                            .font(.subheadline.weight(.semibold))
                            .foregroundStyle(AikuColor.textPrimary)
                    }
                    Spacer()
                    Button {} label: {
                        Image(systemName: "pencil").frame(width: 44, height: 44)
                    }
                    .foregroundStyle(AikuColor.textBody)
                    .accessibilityLabel("Change Picker")
                    Button {} label: {
                        Image(systemName: "person.badge.minus").frame(width: 44, height: 44)
                    }
                    .foregroundStyle(AikuColor.danger)
                    .accessibilityLabel("Remove Picker")
                }
                .padding(.leading, 12)
                .background(AikuColor.surfaceMuted, in: RoundedRectangle(cornerRadius: 10))
            }
            VStack(alignment: .leading, spacing: 8) {
                HStack {
                    Text("Picked").foregroundStyle(AikuColor.textSecondary)
                    Spacer()
                    (Text("\(note.pickedCount)").bold().foregroundStyle(AikuColor.textPrimary)
                        + Text(" of \(note.itemCount) items").foregroundStyle(AikuColor.textSecondary))
                }
                .font(.subheadline)
                ProgressBar(value: note.itemCount > 0 ? Double(note.pickedCount) / Double(note.itemCount) : 0)
            }
        }
        .padding(16)
        .card()
    }

    private var itemsSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text("Items").font(.headline)
                Spacer()
                Text("Pick route by location").font(.caption).foregroundStyle(AikuColor.textSecondary)
            }
            .padding(.horizontal, 4)
            VStack(spacing: 0) {
                ForEach(Array(note.items.enumerated()), id: \.element.id) { index, item in
                    if index > 0 { RowDivider() }
                    if index == currentIndex {
                        currentItemRow(index: index)
                    } else {
                        itemRow(item)
                    }
                }
            }
            .card()
        }
    }

    private func itemRow(_ item: PickItem) -> some View {
        let background: Color = item.isOutOfStock ? AikuColor.dangerSoft : item.isComplete ? AikuColor.successSoft : .clear
        let subtitle = item.isOutOfStock ? "Out of stock · not dispatched" : [item.locationCode, item.name].compactMap { $0 }.joined(separator: " · ")
        return HStack(spacing: 12) {
            Image(systemName: item.isOutOfStock ? "xmark.octagon.fill" : item.isComplete ? "checkmark.circle.fill" : "circle")
                .font(.system(size: 20))
                .foregroundStyle(item.isOutOfStock ? AikuColor.danger : item.isComplete ? AikuColor.success : AikuColor.textFaint)
            VStack(alignment: .leading, spacing: 2) {
                Text(item.skuReference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(item.isOutOfStock ? AikuColor.danger : AikuColor.textSecondary)
                    .lineLimit(1)
            }
            Spacer()
            Text("\(item.picked) / \(item.required)")
                .font(.subheadline.bold())
                .monospacedDigit()
                .foregroundStyle(AikuColor.textPrimary)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(background)
    }

    private func currentItemRow(index: Int) -> some View {
        let item = note.items[index]
        return VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 12) {
                Image(systemName: "arrow.right.circle.fill")
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.indigo)
                VStack(alignment: .leading, spacing: 2) {
                    Text(item.skuReference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                    Text(item.name).font(.caption).foregroundStyle(AikuColor.textSecondary)
                }
            }
            if let locationCode = item.locationCode {
                Text("Pick from \(locationCode)\(item.locationStock.map { " · \($0) in location" } ?? "")")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(AikuColor.indigoText)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 4)
                    .background(AikuColor.indigoSoft, in: RoundedRectangle(cornerRadius: 6))
            }
            HStack {
                Text("Picked · of \(item.required)").font(.subheadline).foregroundStyle(AikuColor.textSecondary)
                Spacer()
                QuantityStepper(value: $note.items[index].picked, range: 0...item.required, label: "picked quantity")
            }
        }
        .padding(16)
        .overlay(Rectangle().stroke(AikuColor.indigo, lineWidth: 2))
    }
}

#Preview {
    DeliveryNoteDetailView(note: SampleData.deliveryNotes[0])
        .environment(AppRouter())
}
