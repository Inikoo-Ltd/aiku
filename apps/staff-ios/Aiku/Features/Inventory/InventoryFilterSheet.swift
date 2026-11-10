import SwiftUI

struct InventoryFilterSheet: View {
    let families: [InventoryFamily]
    let resultCount: (InventorySkoFilter) -> Int
    let onApply: (InventorySkoFilter) -> Void
    @State private var filter: InventorySkoFilter

    init(
        initial: InventorySkoFilter,
        families: [InventoryFamily],
        resultCount: @escaping (InventorySkoFilter) -> Int,
        onApply: @escaping (InventorySkoFilter) -> Void
    ) {
        self.families = families
        self.resultCount = resultCount
        self.onApply = onApply
        _filter = State(initialValue: initial)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(spacing: 10) {
                AppIcon.filter.image
                    .font(.system(size: 18, weight: .medium))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityHidden(true)
                Text("Filter SKOs")
                    .font(.aiku(18, .bold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .accessibilityAddTraits(.isHeader)
                LinkButton("Reset") {
                    filter = InventorySkoFilter()
                }
                .frame(minHeight: 44)
                .accessibilityIdentifier("inventory.filter.reset")
            }
            .padding(.horizontal, 20)
            .padding(.top, 28)
            .padding(.bottom, 8)
            RowDivider()
            VStack(alignment: .leading, spacing: 20) {
                section("SKO state", hint: hint(filter.states.count)) {
                    ForEach(InventorySkoState.allCases, id: \.self) { state in
                        option(state.title, dot: state.dotHex, isOn: filter.states.contains(state)) {
                            filter.states = InventorySkoFilter.toggled(filter.states, state)
                        }
                    }
                }
                section("Stock status", hint: hint(filter.statuses.count)) {
                    ForEach(InventoryStockStatus.allCases, id: \.self) { status in
                        option(status.title, dot: status.dotHex, isOn: filter.statuses.contains(status)) {
                            filter.statuses = InventorySkoFilter.toggled(filter.statuses, status)
                        }
                    }
                }
                section("SKO family", hint: hint(filter.familyCodes.count)) {
                    ForEach(families) { family in
                        option(family.name, isOn: filter.familyCodes.contains(family.code)) {
                            filter.familyCodes = InventorySkoFilter.toggled(filter.familyCodes, family.code)
                        }
                    }
                }
                section("Area", hint: hint(filter.areas.count)) {
                    ForEach(InventoryArea.allCases, id: \.self) { area in
                        option(area.title, isOn: filter.areas.contains(area)) {
                            filter.areas = InventorySkoFilter.toggled(filter.areas, area)
                        }
                    }
                }
                section("Location type", hint: hint(filter.locationTypes.count)) {
                    ForEach(InventoryLocationType.allCases, id: \.self) { type in
                        option(type.title, isOn: filter.locationTypes.contains(type)) {
                            filter.locationTypes = InventorySkoFilter.toggled(filter.locationTypes, type)
                        }
                    }
                }
                section("Sort by", hint: "Choose one") {
                    ForEach(InventorySkoSort.allCases, id: \.self) { sort in
                        option(sort.title, isOn: filter.sort == sort) {
                            filter.sort = sort
                        }
                    }
                }
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 16)
            RowDivider()
            VStack(spacing: 8) {
                Text(filter.summary)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
                    .frame(maxWidth: .infinity)
                AikuButton(showTitle) {
                    onApply(filter)
                }
                .accessibilityIdentifier("inventory.filter.show")
            }
            .padding(.horizontal, 20)
            .padding(.top, 12)
            .padding(.bottom, 16)
        }
    }

    private var showTitle: String {
        let count = resultCount(filter)
        return count == 1 ? "Show 1 SKO" : "Show \(count.grouped) SKOs"
    }

    private func hint(_ count: Int) -> String {
        count == 0 ? "Any" : "\(count) selected"
    }

    private func section<Options: View>(_ label: String, hint: String, @ViewBuilder options: () -> Options) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(alignment: .firstTextBaseline) {
                Text(label.uppercased())
                    .font(.aiku(13, .bold))
                    .tracking(0.5)
                    .foregroundStyle(AikuColor.text3)
                    .accessibilityAddTraits(.isHeader)
                Spacer(minLength: 8)
                Text(hint)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
            FlowLayout(spacing: 8, lineSpacing: 8) {
                options()
            }
        }
    }

    private func option(_ title: String, dot: UInt32? = nil, isOn: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(spacing: 6) {
                if let dot {
                    Circle().fill(Color(hex: dot)).frame(width: 8, height: 8)
                }
                Text(title)
                    .font(.aiku(14, .semibold))
                    .lineLimit(1)
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
