import SwiftUI

struct GoodsInPendingItems: View {
    let delivery: GoodsInDelivery
    let canCheck: Bool
    let onScan: () -> Void
    let onEdit: (GoodsInItem) -> Void

    private var pending: [GoodsInItem] { delivery.items.filter { !$0.isPlaced } }

    var body: some View {
        if canCheck, let item = delivery.currentScanItem {
            GoodsInScanCard(
                title: "Scan to check",
                subtitle: "Each scan adds 1 to the checked quantity",
                code: item.sko,
                detail: "\(item.name) · \((item.checked ?? 0).grouped) of \(item.delivered.grouped) checked",
                identifier: "goodsIn.scanToCheck",
                action: onScan
            )
        }
        SectionHeading("Pending items", detail: "\(delivery.checkedCount) of \(delivery.items.count) checked")
        if pending.isEmpty {
            EmptyState(icon: .checkCircle, title: "Nothing pending", message: "Every item on this delivery is done.")
        } else {
            CardList(pending) { item in
                GoodsInItemRow(
                    sko: item.sko,
                    subtitle: item.checked == 0 ? "\(item.name) · checked as 0" : "\(item.name) · delivered \(item.delivered.grouped)",
                    chip: item.checkStatus.chip,
                    value: item.checked ?? 0,
                    total: item.delivered,
                    tone: item.checkStatus.tone,
                    action: canCheck && !item.hasPlacements ? { onEdit(item) } : nil
                )
            }
        }
        HelperText("Tap an item to set its checked quantity or check all. Checking an item as 0 marks it Not received. Checked items can be placed straight away from Booking in.")
            .padding(.horizontal, 4)
    }
}

struct GoodsInDoneItems: View {
    let delivery: GoodsInDelivery
    let onOpenSko: (GoodsInItem) -> Void

    private var done: [GoodsInItem] { delivery.items.filter(\.isPlaced) }

    var body: some View {
        if done.isEmpty {
            EmptyState(icon: .locations, title: "Nothing placed yet", message: "Items show here once their checked quantity is placed in locations.")
        } else {
            CardList(done) { item in
                GoodsInItemRow(
                    sko: item.sko,
                    subtitle: item.placementSummary,
                    chip: Chip("Placed", .success),
                    value: item.placedQuantity,
                    total: item.checked ?? 0,
                    tone: .success,
                    action: { onOpenSko(item) }
                )
            }
        }
        if delivery.state == .bookedIn || delivery.state == .placed {
            HelperText("All items placed, so the delivery is Booked in by itself.")
                .padding(.horizontal, 4)
        }
    }
}

struct GoodsInChecklist: View {
    let delivery: GoodsInDelivery

    var body: some View {
        SectionHeading("Goods-in checks")
        CardList(delivery.checks) { check in
            ListRow(check.title, subtitle: check.detail, chips: [check.result.chip])
        }
        SectionHeading("Under / over delivered", detail: "read-only")
        if delivery.differenceItems.isEmpty {
            EmptyState(icon: .checkCircle, title: "No differences", message: "Checked quantities match the delivered quantities so far.")
        } else {
            CardList(delivery.differenceItems) { item in
                ListRow(item.sko, subtitle: item.name, chips: item.checkStatus.differenceChip.map { [$0] } ?? []) {
                    ProductThumb(size: 48, label: "Product photo of \(item.sko)")
                } trailing: {
                    EmptyView()
                }
            }
        }
        HelperText("A failed check needs a note. Purchasing follows up the differences.")
            .padding(.horizontal, 4)
    }
}

struct GoodsInBookingInContent: View {
    let delivery: GoodsInDelivery
    let canWork: Bool
    let onPlace: (GoodsInItem) -> Void
    let onCheck: (GoodsInItem) -> Void
    let onOpenSko: (GoodsInItem) -> Void

    private var checkedItems: [GoodsInItem] { delivery.items.filter(\.isChecked) }
    private var uncheckedItems: [GoodsInItem] { delivery.items.filter { !$0.isChecked } }

    var body: some View {
        SectionHeading("Place checked items", detail: "\(delivery.placedItemCount) of \(delivery.placeableItems.count) placed")
        CardList(checkedItems) { item in
            row(item)
        }
        if !uncheckedItems.isEmpty {
            SectionHeading("Still to check", detail: uncheckedItems.count == 1 ? "1 item" : "\(uncheckedItems.count) items")
            CardList(uncheckedItems) { item in
                GoodsInItemRow(
                    sko: item.sko,
                    subtitle: "\(item.name) · delivered \(item.delivered.grouped)",
                    value: 0,
                    total: item.delivered,
                    action: canWork ? { onCheck(item) } : nil
                )
            }
        }
        HelperText("No delivery-level action while booking in: place each checked item. You can only place what was checked. When every item is placed the delivery becomes Booked in by itself.")
            .padding(.horizontal, 4)
    }

    @ViewBuilder
    private func row(_ item: GoodsInItem) -> some View {
        if item.checked == 0 {
            GoodsInItemRow(sko: item.sko, subtitle: "\(item.name) · checked as 0", chip: Chip("Not received", .danger), value: 0, total: item.delivered, tone: .danger)
        } else if item.isPlaced {
            GoodsInItemRow(
                sko: item.sko,
                subtitle: "\(item.name) · in \(item.placements.map(\.location).joined(separator: ", "))",
                chip: Chip("Placed", .success),
                value: item.placedQuantity,
                total: item.checked ?? 0,
                tone: .success,
                action: { onOpenSko(item) }
            )
        } else {
            GoodsInItemRow(
                sko: item.sko,
                subtitle: pendingSubtitle(item),
                chip: item.pickingLocation == nil ? Chip("No picking location yet", .warn) : nil,
                value: item.placedQuantity,
                total: item.checked ?? 0,
                action: canWork ? { onPlace(item) } : nil
            )
            .accessibilityIdentifier("goodsIn.place.\(item.sko)")
        }
    }

    private func pendingSubtitle(_ item: GoodsInItem) -> String {
        var parts = [item.name]
        if item.hasPlacements {
            parts.append("\(item.leftToPlace.grouped) left · \(item.placementSummary)")
        } else if let picking = item.pickingLocation {
            parts.append("picking location \(picking)")
        }
        return parts.joined(separator: " · ")
    }
}

struct GoodsInCheckQuantitySheet: View {
    let item: GoodsInItem
    let onSave: (Int) -> Void
    @State private var quantity: Int

    init(item: GoodsInItem, onSave: @escaping (Int) -> Void) {
        self.item = item
        self.onSave = onSave
        _quantity = State(initialValue: item.checked ?? 0)
    }

    private var status: GoodsInCheckStatus { .classify(checked: quantity, delivered: item.delivered) }

    private var differenceText: String {
        switch status {
        case .pending, .exact: "Matches the delivered quantity"
        case .under(let gap): "\(gap.grouped) under the delivered quantity"
        case .over(let gap): "\(gap.grouped) over the delivered quantity"
        case .notReceived: "Nothing received"
        }
    }

    var body: some View {
        SheetScaffold(title: "\(item.sko) · checked quantity", subtitle: "\(item.name) · delivered \(item.delivered.grouped)") {
            HStack {
                Text("Checked")
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.text)
                Spacer(minLength: 8)
                GoodsInStepper(value: $quantity, range: max(item.placedQuantity, 0)...99999)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.border))
            Card {
                ListRow("Difference", subtitle: differenceText) {
                    StatusChip(status.differenceChip ?? Chip("Exact", .success))
                }
            }
            HelperText("Count in units or in SKOs (outers), as set for the item. Purchasing resolves differences: recount, unit error, supplier claim or surplus accepted.")
                .padding(.horizontal, 4)
        } footer: {
            AikuButton("Save \(quantity.grouped) checked") { onSave(quantity) }
                .accessibilityIdentifier("goodsIn.check.save")
            AikuButton("Check all \(item.delivered.grouped)", kind: .secondary) { onSave(item.delivered) }
                .accessibilityIdentifier("goodsIn.check.all")
            if item.placedQuantity == 0 {
                AikuButton("Check as 0 · not received", kind: .danger) { onSave(0) }
                    .accessibilityIdentifier("goodsIn.check.zero")
            }
        }
    }
}

struct GoodsInFailCheckSheet: View {
    let check: GoodsInDeliveryCheck
    let onSave: (String) -> Void
    let onCancel: () -> Void
    @State private var note = ""

    private var hasNote: Bool { !note.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty }

    var body: some View {
        SheetScaffold(title: "Fail check", subtitle: check.title) {
            AikuTextArea("Note", text: $note, placeholder: "What is wrong?")
            HelperText("A failed check needs a note. Purchasing follows up the differences.")
        } footer: {
            AikuButton("Save failed check", kind: .danger) { onSave(note) }
                .disabled(!hasNote)
                .accessibilityIdentifier("goodsIn.check.saveFail")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }
}
