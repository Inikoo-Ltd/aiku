import SwiftUI

struct GoodsOutView: View {
    @Environment(AppRouter.self) private var router
    @State private var section = Section.deliveryNotes
    @State private var filter = DeliveryNoteFilter()

    enum Section: Hashable {
        case backlog
        case deliveryNotes
        case shippers
    }

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: "Goods out", subtitle: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code)", showsBack: false) {
                SectionSwitcher(items: [
                    SectionSwitcherItem(id: .backlog, title: "Backlog"),
                    SectionSwitcherItem(id: .deliveryNotes, title: "Delivery notes"),
                    SectionSwitcherItem(id: .shippers, title: "Shippers"),
                ], selection: $section)
                if section == .deliveryNotes {
                    SearchBar(text: $filter.query, placeholder: "Reference or customer", scanLabel: "Scan delivery note barcode") {
                        router.scan(ScanContext(title: "Goods out · Find delivery note", reference: nil))
                    }
                }
            }
            switch section {
            case .deliveryNotes:
                list
            case .backlog, .shippers:
                ScrollView {
                    EmptyStateView(systemImage: "hammer", title: section == .backlog ? "Goods out backlog" : "Shippers", message: "This section has no mobile design yet.")
                        .padding(16)
                }
            }
        }
        .aikuScreen()
        .mainTabBar()
    }

    private var list: some View {
        let notes = filter.apply(to: SampleData.deliveryNotes)
        let summary = SampleData.goodsOut
        return ScrollView {
            VStack(spacing: 10) {
                FilterChips(items: [
                    FilterChipItem(id: DeliveryNoteState.unassigned, title: "Unassigned \(summary.unassigned)"),
                    FilterChipItem(id: .inQueue, title: "In Queue \(summary.inQueue)"),
                    FilterChipItem(id: .handling, title: "Handling \(summary.handling)"),
                    FilterChipItem(id: .packed, title: "Packed \(summary.packed)"),
                ], selection: $filter.state)
                .padding(.horizontal, -16)

                HStack {
                    Text("Oldest first")
                    Spacer()
                    Text("Items · weight")
                }
                .font(.caption)
                .foregroundStyle(AikuColor.textSecondary)
                .padding(.horizontal, 4)

                if notes.isEmpty {
                    EmptyStateView(systemImage: AppIcon.goodsOut, title: "No delivery notes", message: "Nothing is \(filter.state.label.lowercased()) right now.")
                } else {
                    VStack(spacing: 0) {
                        ForEach(Array(notes.enumerated()), id: \.element.id) { index, note in
                            if index > 0 { RowDivider() }
                            Button {
                                router.open(.deliveryNote(note.reference))
                            } label: {
                                DeliveryNoteRow(note: note)
                            }
                        }
                    }
                    .card()
                    ListFooterNote(text: "Showing \(notes.count) \(filter.state.label.lowercased()) · Packed, Finalised and Dispatched in filters")
                }
            }
            .padding(16)
        }
    }
}

struct DeliveryNoteRow: View {
    let note: DeliveryNote

    var body: some View {
        HStack(spacing: 12) {
            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 8) {
                    Text(note.reference).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                    StatusBadge(text: note.state.label, tone: note.state.tone)
                }
                Text("\(note.customer) · \(note.shop) · \(note.date)")
                    .font(.caption)
                    .foregroundStyle(AikuColor.textSecondary)
                Text("\(note.itemCount) \(note.itemCount == 1 ? "item" : "items") · \(note.weightKg.trimmed) kg")
                    .font(.caption.weight(.medium))
                    .foregroundStyle(AikuColor.textBody)
            }
            Spacer(minLength: 8)
            if note.state == .unassigned || note.state == .inQueue {
                Text("Start picking")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(AikuColor.navy)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(AikuColor.yellow, in: RoundedRectangle(cornerRadius: 6))
            }
            ChevronIcon()
        }
        .multilineTextAlignment(.leading)
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .contentShape(Rectangle())
    }
}

#Preview {
    GoodsOutView()
        .environment(AppRouter())
}
