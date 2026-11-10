import SwiftUI

struct GoodsOutFilterSheet: View {
    let store: GoodsOutStore
    let onApply: (GoodsOutDeliveryNoteFilter) -> Void
    let onClose: () -> Void
    @State private var draft: GoodsOutDeliveryNoteFilter

    init(store: GoodsOutStore, initial: GoodsOutDeliveryNoteFilter, onApply: @escaping (GoodsOutDeliveryNoteFilter) -> Void, onClose: @escaping () -> Void) {
        self.store = store
        self.onApply = onApply
        self.onClose = onClose
        _draft = State(initialValue: initial)
    }

    private static let stateDots: [GoodsOutDeliveryNoteState: UInt32] = [
        .todo: 0x9CA3AF, .picking: 0x6366F1, .waiting: 0xF59E0B, .picked: 0x3B82F6,
        .packing: 0x0EA5E9, .packed: 0x14B8A6, .finalised: 0x22C55E, .dispatched: 0x16A34A
    ]

    private var resultCount: Int {
        var query = store.listQuery
        query.apply(draft)
        return query.results(in: store.notes, currentUser: store.currentUser).count
    }

    var body: some View {
        SheetScaffold(title: "Filter delivery notes", spacing: 20) {
            HStack {
                Spacer()
                LinkButton("Reset") { draft = GoodsOutDeliveryNoteFilter() }
                    .frame(minHeight: 44)
            }
            .padding(.top, -12)
            section("State", values: GoodsOutDeliveryNoteState.listTabs, selected: draft.states, title: \.title, dot: { Self.stateDots[$0] }) { draft.states.formSymmetricDifference([$0]) }
            section("Picker", values: GoodsOutPickerFilter.allCases, selected: draft.pickers, title: \.title) { draft.pickers.formSymmetricDifference([$0]) }
            section("Channel", values: GoodsOutChannel.allCases, selected: draft.channels, title: \.title) { draft.channels.formSymmetricDifference([$0]) }
            section("Shipper", values: GoodsOutShipper.allCases, selected: draft.shippers, title: \.title) { draft.shippers.formSymmetricDifference([$0]) }
            section("Date", values: GoodsOutDateFilter.allCases, selected: draft.dates, title: \.title) { draft.dates.formSymmetricDifference([$0]) }
            section("Sort by", hint: "Choose one", values: GoodsOutSort.allCases, selected: [draft.sort], title: \.title) { draft.sort = $0 }
        } footer: {
            Text(draft.summary)
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
                .frame(maxWidth: .infinity)
            AikuButton("Show \(resultCount) delivery \(resultCount == 1 ? "note" : "notes")") {
                onApply(draft)
            }
            .accessibilityIdentifier("goodsOut.filter.apply")
        }
    }

    private func section<Value: Hashable>(
        _ label: String,
        hint: String? = nil,
        values: [Value],
        selected: Set<Value>,
        title: @escaping (Value) -> String,
        dot: ((Value) -> UInt32?)? = nil,
        toggle: @escaping (Value) -> Void
    ) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(alignment: .firstTextBaseline) {
                Text(label.uppercased())
                    .font(.aiku(13, .bold))
                    .tracking(0.5)
                    .foregroundStyle(AikuColor.text3)
                Spacer()
                Text(hint ?? (selected.isEmpty ? "Any" : "\(selected.count) selected"))
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
            FlowLayout(spacing: 8, lineSpacing: 8) {
                ForEach(values, id: \.self) { value in
                    let isOn = selected.contains(value)
                    Button {
                        toggle(value)
                    } label: {
                        HStack(spacing: 6) {
                            if let hex = dot?(value) {
                                Circle().fill(Color(hex: hex)).frame(width: 8, height: 8)
                            }
                            Text(title(value))
                                .font(.aiku(14, .semibold))
                        }
                        .foregroundStyle(isOn ? AikuColor.ink : AikuColor.text2)
                        .padding(.horizontal, 14)
                        .frame(minHeight: 44)
                        .background(isOn ? AikuColor.soft : AikuColor.surface, in: Capsule())
                        .overlay(Capsule().strokeBorder(isOn ? AikuColor.primary : AikuColor.borderStrong, lineWidth: isOn ? 2 : 1))
                        .contentShape(Capsule())
                    }
                    .buttonStyle(.plain)
                    .accessibilityAddTraits(isOn ? .isSelected : [])
                }
            }
        }
    }
}
