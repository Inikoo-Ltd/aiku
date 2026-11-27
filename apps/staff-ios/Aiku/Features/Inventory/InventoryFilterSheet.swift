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
        InventoryFilterSheetLayout(
            summary: filter.summary,
            showTitle: InventoryFilterText.showTitle(resultCount(filter)),
            onReset: { filter = InventorySkoFilter() },
            onShow: { onApply(filter) }
        ) {
            InventoryFilterSection("SKO state", hint: InventoryFilterText.hint(filter.states.count)) {
                ForEach(InventorySkoState.allCases, id: \.self) { state in
                    InventoryFilterOption(L10n.text(state.title), dot: state.dotHex, isOn: filter.states.contains(state)) {
                        filter.states = InventorySkoFilter.toggled(filter.states, state)
                    }
                }
            }
            InventoryFilterSection("Stock status", hint: InventoryFilterText.hint(filter.statuses.count)) {
                ForEach(InventoryStockStatus.allCases, id: \.self) { status in
                    InventoryFilterOption(L10n.text(status.title), dot: status.dotHex, isOn: filter.statuses.contains(status)) {
                        filter.statuses = InventorySkoFilter.toggled(filter.statuses, status)
                    }
                }
            }
            InventoryFilterSection("SKO family", hint: InventoryFilterText.hint(filter.familyCodes.count)) {
                ForEach(families) { family in
                    InventoryFilterOption(family.name, isOn: filter.familyCodes.contains(family.code)) {
                        filter.familyCodes = InventorySkoFilter.toggled(filter.familyCodes, family.code)
                    }
                }
            }
            InventoryFilterSection("Area", hint: InventoryFilterText.hint(filter.areas.count)) {
                ForEach(InventoryArea.allCases, id: \.self) { area in
                    InventoryFilterOption(area.title, isOn: filter.areas.contains(area)) {
                        filter.areas = InventorySkoFilter.toggled(filter.areas, area)
                    }
                }
            }
            InventoryFilterSection("Location type", hint: InventoryFilterText.hint(filter.locationTypes.count)) {
                ForEach(InventoryLocationType.allCases, id: \.self) { type in
                    InventoryFilterOption(L10n.text(type.title), isOn: filter.locationTypes.contains(type)) {
                        filter.locationTypes = InventorySkoFilter.toggled(filter.locationTypes, type)
                    }
                }
            }
            InventorySortSection(sort: $filter.sort)
        }
    }
}

struct InventoryLiveFilterSheet: View {
    let families: [InventoryFamilyOption]?
    let options: MobileOrgStocksPage.FilterOptions?
    let resultCount: (InventoryLiveSkoFilter) async -> Int?
    let onApply: (InventoryLiveSkoFilter) -> Void
    @State private var filter: InventoryLiveSkoFilter
    @State private var count: Int?

    init(
        initial: InventoryLiveSkoFilter,
        families: [InventoryFamilyOption]?,
        options: MobileOrgStocksPage.FilterOptions?,
        resultCount: @escaping (InventoryLiveSkoFilter) async -> Int?,
        onApply: @escaping (InventoryLiveSkoFilter) -> Void
    ) {
        self.families = families
        self.options = options
        self.resultCount = resultCount
        self.onApply = onApply
        _filter = State(initialValue: initial)
    }

    var body: some View {
        InventoryFilterSheetLayout(
            summary: filter.summary,
            showTitle: count.map { InventoryFilterText.showTitle($0) } ?? "Show SKOs",
            onReset: { filter = InventoryLiveSkoFilter() },
            onShow: { onApply(filter) }
        ) {
            if let choices = options?.stockStatus, !choices.isEmpty {
                InventoryFilterSection("Stock status", hint: InventoryFilterText.hint(filter.stockStatus == nil ? 0 : 1)) {
                    ForEach(choices) { choice in
                        InventoryFilterOption(choice.title, isOn: filter.stockStatus == choice.key) {
                            filter.stockStatus = filter.stockStatus == choice.key ? nil : choice.key
                        }
                    }
                }
            }
            if let choices = options?.locationType, !choices.isEmpty {
                InventoryFilterSection("Location type", hint: InventoryFilterText.hint(filter.locationType == nil ? 0 : 1)) {
                    ForEach(choices) { choice in
                        InventoryFilterOption(choice.title, isOn: filter.locationType == choice.key) {
                            filter.locationType = filter.locationType == choice.key ? nil : choice.key
                        }
                    }
                }
            }
            InventoryFilterSection("SKO family", hint: InventoryFilterText.hint(filter.familyID == nil ? 0 : 1), layout: .stacked) {
                if let families {
                    SearchableSelect(
                        options: families.map { SearchableSelectOption(id: $0.id, title: $0.title) },
                        selectedID: filter.familyID
                    ) { id in
                        filter.familyID = id
                        filter.familyCode = id.flatMap { chosen in families.first { $0.id == chosen }?.code }
                    }
                    .accessibilityIdentifier("inventory.filter.family")
                } else {
                    ProgressView()
                        .frame(minHeight: 44)
                        .accessibilityLabel("Loading SKO families")
                }
            }
            InventorySortSection(sort: $filter.sort)
        }
        .task(id: filter) {
            count = nil
            try? await Task.sleep(for: .milliseconds(300))
            guard !Task.isCancelled else { return }
            count = await resultCount(filter)
        }
    }
}

@MainActor
private enum InventoryFilterText {
    static func showTitle(_ count: Int) -> String {
        L10n.format("Show %lld SKOs", count)
    }

    static func hint(_ count: Int) -> String {
        count == 0 ? L10n.text("Any") : L10n.format("%lld selected", count)
    }
}

private struct InventorySortSection: View {
    @Binding var sort: InventorySkoSort

    var body: some View {
        InventoryFilterSection("Sort by", hint: L10n.text("Choose one")) {
            ForEach(InventorySkoSort.allCases, id: \.self) { option in
                InventoryFilterOption(L10n.text(option.title), isOn: sort == option) {
                    sort = option
                }
            }
        }
    }
}

private struct InventoryFilterSheetLayout<Sections: View>: View {
    let summary: String
    let showTitle: String
    let onReset: () -> Void
    let onShow: () -> Void
    @ViewBuilder let sections: Sections

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
                LinkButton("Reset", action: onReset)
                    .frame(minHeight: 44)
                    .accessibilityIdentifier("inventory.filter.reset")
            }
            .padding(.horizontal, 20)
            .padding(.top, 28)
            .padding(.bottom, 8)
            RowDivider()
            VStack(alignment: .leading, spacing: 20) {
                sections
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 16)
            RowDivider()
            VStack(spacing: 8) {
                Text(summary)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
                    .frame(maxWidth: .infinity)
                AikuButton(showTitle, icon: .next, action: onShow)
                    .accessibilityIdentifier("inventory.filter.show")
            }
            .padding(.horizontal, 20)
            .padding(.top, 12)
            .padding(.bottom, 16)
        }
    }
}

private struct InventoryFilterSection<Options: View>: View {
    enum Layout {
        case chips
        case stacked
    }

    let label: String
    let hint: String
    let layout: Layout
    @ViewBuilder let options: Options

    init(_ label: String, hint: String, layout: Layout = .chips, @ViewBuilder options: () -> Options) {
        self.label = label
        self.hint = hint
        self.layout = layout
        self.options = options()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(alignment: .firstTextBaseline) {
                Text(L10n.text(label).uppercased(with: LanguageStore.shared.locale))
                    .font(.aiku(13, .bold))
                    .tracking(0.5)
                    .foregroundStyle(AikuColor.text3)
                    .accessibilityAddTraits(.isHeader)
                Spacer(minLength: 8)
                Text(hint)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
            switch layout {
            case .chips:
                FlowLayout(spacing: 8, lineSpacing: 8) {
                    options
                }
            case .stacked:
                options
            }
        }
    }
}

private struct InventoryFilterOption: View {
    let title: String
    var dot: UInt32?
    let isOn: Bool
    let action: () -> Void

    init(_ title: String, dot: UInt32? = nil, isOn: Bool, action: @escaping () -> Void) {
        self.title = title
        self.dot = dot
        self.isOn = isOn
        self.action = action
    }

    var body: some View {
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
