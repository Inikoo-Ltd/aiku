import SwiftUI

struct LocationsFilterSheet: View {
    let areas: [LocationsArea]
    let resultCount: (LocationsFilter) async -> Int?
    let onApply: (LocationsFilter) -> Void
    @State private var draft: LocationsFilter
    @State private var count: Int?
    @State private var isCounting = false

    init(
        initial: LocationsFilter,
        areas: [LocationsArea],
        resultCount: @escaping (LocationsFilter) async -> Int?,
        onApply: @escaping (LocationsFilter) -> Void
    ) {
        self.areas = areas
        self.resultCount = resultCount
        self.onApply = onApply
        _draft = State(initialValue: initial)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            HStack(spacing: 10) {
                AppIcon.filter.image
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityHidden(true)
                Text("Filter locations")
                    .font(.aiku(18, .bold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .accessibilityAddTraits(.isHeader)
                LinkButton("Reset") {
                    draft = .none
                }
                .accessibilityIdentifier("locations.filter.reset")
            }
            section("Area", hint: hint(draft.areas.count)) {
                ForEach(areas) { area in
                    option(area.filterTitle, isOn: draft.areas.contains(area.code)) {
                        draft.areas.formSymmetricDifference([area.code])
                    }
                }
            }
            section("Status", hint: hint(draft.statuses.count)) {
                ForEach(LocationsStatus.allCases, id: \.self) { status in
                    option(status.title, dot: status.tone, isOn: draft.statuses.contains(status)) {
                        draft.statuses.formSymmetricDifference([status])
                    }
                }
            }
            section("Scope", hint: hint(draft.scopes.count)) {
                ForEach(LocationsScope.allCases, id: \.self) { scope in
                    option(scope.title, isOn: draft.scopes.contains(scope)) {
                        draft.scopes.formSymmetricDifference([scope])
                    }
                }
            }
            section("Occupancy", hint: hint(draft.occupancies.count)) {
                ForEach(LocationsOccupancyFilter.allCases, id: \.self) { occupancy in
                    option(occupancy.title, isOn: draft.occupancies.contains(occupancy)) {
                        draft.occupancies.formSymmetricDifference([occupancy])
                    }
                }
            }
            section("Sort by", hint: "Choose one") {
                ForEach(LocationsSort.allCases, id: \.self) { sort in
                    option(sort.title, isOn: draft.sort == sort) {
                        draft.sort = sort
                    }
                }
            }
            VStack(spacing: 8) {
                Text(draft.summary)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
                    .frame(maxWidth: .infinity)
                AikuButton(applyTitle) {
                    onApply(draft)
                }
                .accessibilityIdentifier("locations.filter.apply")
            }
        }
        .padding(.horizontal, 20)
        .padding(.top, 28)
        .padding(.bottom, 16)
        .task(id: draft) {
            isCounting = true
            if count != nil {
                try? await Task.sleep(for: .milliseconds(250))
                guard !Task.isCancelled else { return }
            }
            let counted = await resultCount(draft)
            guard !Task.isCancelled else { return }
            count = counted
            isCounting = false
        }
    }

    private var applyTitle: String {
        guard let count, !isCounting else { return "Show locations" }
        return "Show \(count.locationsGrouped) \(count == 1 ? "location" : "locations")"
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

    private func option(_ title: String, dot: Tone? = nil, isOn: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(spacing: 6) {
                if let dot {
                    Circle()
                        .fill(dot.foreground)
                        .frame(width: 8, height: 8)
                        .accessibilityHidden(true)
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
