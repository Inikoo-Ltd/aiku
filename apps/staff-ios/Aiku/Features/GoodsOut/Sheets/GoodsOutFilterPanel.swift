import SwiftUI

struct GoodsOutFilterPanel: View {
    let store: GoodsOutStore
    let query: GoodsOutListQuery
    let onApply: (GoodsOutDeliveryNoteFilter) -> Void
    let onClose: () -> Void
    @State private var draft: GoodsOutDeliveryNoteFilter

    init(store: GoodsOutStore, query: GoodsOutListQuery, onApply: @escaping (GoodsOutDeliveryNoteFilter) -> Void, onClose: @escaping () -> Void) {
        self.store = store
        self.query = query
        self.onApply = onApply
        self.onClose = onClose
        _draft = State(initialValue: query.filter)
    }

    var body: some View {
        SidePanelScaffold(title: "Filter delivery notes", onClose: onClose) {
            HStack {
                Spacer()
                LinkButton("Reset", size: 14) { draft = GoodsOutDeliveryNoteFilter() }
                    .frame(minHeight: 44)
                    .accessibilityIdentifier("goodsOut.filter.reset")
            }
            section("State", values: GoodsOutDeliveryNoteState.listTabs, selected: draft.states, title: \.title, dot: { $0.tone }) {
                draft.states = toggled(draft.states, $0)
            }
            section("Picker", values: GoodsOutPickerFilter.allCases, selected: draft.pickers, title: \.title) {
                draft.pickers = toggled(draft.pickers, $0)
            }
            section("Channel", values: GoodsOutChannel.allCases.filter { store.isRemote || $0 != .fulfilment }, selected: draft.channels, title: \.title) {
                draft.channels = toggled(draft.channels, $0)
            }
            if !store.isRemote {
                section("Shipper", values: GoodsOutShipper.allCases, selected: draft.shippers, title: \.title) {
                    draft.shippers.formSymmetricDifference([$0])
                }
            }
            VStack(alignment: .leading, spacing: 12) {
                section(GoodsOutDeliveryNoteFilter.dateLabel, values: datePresets, selected: draft.dates, title: \.title, alsoChosen: draft.range.isSet ? 1 : 0) {
                    draft.toggleDate($0, allowsSeveral: !store.isRemote)
                }
                DateRangeFields(range: rangeBinding)
            }
            sortSection
        } footer: {
            AikuButton(showText) { onApply(draft) }
                .accessibilityIdentifier("goodsOut.filter.apply")
        }
    }

    private var datePresets: [GoodsOutDateFilter] {
        GoodsOutDateFilter.allCases.filter { !store.isRemote || $0 != .overdue }
    }

    private var draftQuery: GoodsOutListQuery {
        var preview = query
        preview.apply(draft)
        return preview
    }

    private var rangeBinding: Binding<ListDateRange> {
        Binding(get: { draft.range }, set: { draft = draft.withRange($0) })
    }

    private var sortBinding: Binding<GoodsOutSort?> {
        Binding(get: { draftQuery.filter.sort }, set: { draft.sort = $0 })
    }

    private var sortSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            SheetSectionLabel("Sort by")
            Picker(L10n.text("Sort by"), selection: sortBinding) {
                ForEach(draftQuery.sortOptions, id: \.self) { option in
                    Text(L10n.text(option.label)).tag(option.sort)
                }
            }
            .pickerStyle(.menu)
            .tint(AikuColor.link)
            .frame(maxWidth: .infinity, minHeight: 48, alignment: .leading)
            .padding(.horizontal, 8)
            .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
            .accessibilityIdentifier("goodsOut.filter.sort")
        }
    }

    private var resultCount: Int? {
        guard store.isRemote else {
            return draftQuery.results(in: store.notes, currentUser: store.currentUser).count
        }
        return store.savedListTotal(for: draftQuery)
    }

    private var showText: String {
        guard let count = resultCount else { return L10n.text("Show results") }
        return L10n.format("Show %lld results", count)
    }

    private func toggled<Value: Hashable>(_ selection: Set<Value>, _ value: Value) -> Set<Value> {
        if store.isRemote {
            return selection.contains(value) ? [] : [value]
        }
        return selection.symmetricDifference([value])
    }

    private func hint(chosen: Int) -> String {
        guard chosen > 0 else { return L10n.text("Any") }
        return store.isRemote ? L10n.text("Choose one") : "\(chosen) selected"
    }

    private func section<Value: Hashable>(
        _ label: String,
        values: [Value],
        selected: Set<Value>,
        title: @escaping (Value) -> String,
        dot: ((Value) -> Tone)? = nil,
        alsoChosen: Int = 0,
        toggle: @escaping (Value) -> Void
    ) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(alignment: .firstTextBaseline) {
                SheetSectionLabel(label)
                Spacer()
                Text(hint(chosen: selected.count + alsoChosen))
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
                            if let tone = dot?(value) {
                                Circle().fill(tone.foreground).frame(width: 8, height: 8)
                            }
                            Text(L10n.text(title(value)))
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
