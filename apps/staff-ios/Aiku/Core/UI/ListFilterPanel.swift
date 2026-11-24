import SwiftUI

struct ListSortOption<Sort: Hashable & Sendable>: Hashable, Sendable {
    let sort: Sort?
    let label: String
}

struct ListFilter<Sort: Hashable & Sendable>: Hashable, Sendable {
    var dates = ListDateRange()
    var sort: Sort?

    var activeCount: Int { (dates.isSet ? 1 : 0) + (sort == nil ? 0 : 1) }
    var isFiltered: Bool { activeCount > 0 }

    @MainActor
    func summary(sorts: [ListSortOption<Sort>], dateLabel: String? = nil, onlineOnlySorts: Set<Sort> = []) -> [String] {
        var parts: [String] = []
        if let range = ListDates.rangeText(dates) {
            parts.append(dateLabel.map { "\(L10n.text($0)): \(range)" } ?? range)
        }
        if let sort, let label = sorts.first(where: { $0.sort == sort })?.label {
            let sorted = L10n.format("Sorted by %@", L10n.text(label).lowercased())
            parts.append(onlineOnlySorts.contains(sort) ? L10n.format("%@ (online only)", sorted) : sorted)
        }
        return parts
    }
}

struct FilterSquareButton: View {
    let count: Int
    var size: CGFloat = 46
    let action: () -> Void

    var body: some View {
        let isActive = count > 0
        Button(action: action) {
            AppIcon.filter.image
                .font(.system(size: 18, weight: .medium))
                .foregroundStyle(AikuColor.link)
                .frame(width: size, height: size)
                .background(isActive ? AikuColor.soft : AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
                .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(isActive ? AikuColor.primary : AikuColor.borderStrong, lineWidth: 1))
                .overlay(alignment: .topTrailing) {
                    if isActive {
                        CountBadge(count: count).offset(x: 4, y: -4)
                    }
                }
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(L10n.text("Filter and sort"))
        .accessibilityValue(isActive ? "\(count)" : "")
    }
}

struct SearchWithFilter<Search: View>: View {
    let filterCount: Int
    let onFilter: () -> Void
    @ViewBuilder let search: () -> Search

    var body: some View {
        HStack(spacing: 8) {
            search()
                .frame(maxWidth: .infinity)
            FilterSquareButton(count: filterCount, action: onFilter)
                .accessibilityIdentifier("list.filter")
        }
    }
}

struct ActiveFiltersLine: View {
    let parts: [String]
    let onClear: () -> Void

    var body: some View {
        if !parts.isEmpty {
            HStack(spacing: 8) {
                Text(parts.joined(separator: " · "))
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text2)
                    .frame(maxWidth: .infinity, alignment: .leading)
                LinkButton("Clear", size: 14, action: onClear)
                    .frame(minHeight: 44)
                    .accessibilityIdentifier("list.filter.clear")
            }
            .padding(.leading, 4)
        }
    }
}

struct SavedCopyNote: View {
    let savedAt: Date

    var body: some View {
        LastUpdatedNote(date: savedAt, savedRowsOnly: true)
    }
}

struct DateRangeFields: View {
    @Binding var range: ListDateRange

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            field("From (date)", date: range.from, bounds: range.to.map { ...$0 }) { range = range.withFrom($0) }
            field("To (date)", date: range.to, lower: range.from) { range = range.withTo($0) }
            if let shown = ListDates.rangeText(range) {
                HStack(spacing: 8) {
                    Text(shown)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text2)
                        .frame(maxWidth: .infinity, alignment: .leading)
                    LinkButton("Clear", size: 14) { range = ListDateRange() }
                        .frame(minHeight: 44)
                }
            }
        }
    }

    private func field(_ label: String, date: Date?, bounds: PartialRangeThrough<Date>? = nil, lower: Date? = nil, onPick: @escaping (Date?) -> Void) -> some View {
        HStack(spacing: 10) {
            AppIcon.faCalendar.image
                .font(.system(size: 15))
                .foregroundStyle(AikuColor.link)
                .accessibilityHidden(true)
            Text(L10n.text(label))
                .font(.aiku(14, .semibold))
                .foregroundStyle(AikuColor.text2)
            Spacer(minLength: 8)
            if let date {
                pickerFor(date: date, bounds: bounds, lower: lower, onPick: onPick)
                Button {
                    onPick(nil)
                } label: {
                    AppIcon.close.image
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(AikuColor.text4)
                        .frame(width: 32, height: 44)
                }
                .buttonStyle(.plain)
                .accessibilityLabel(L10n.text("Clear"))
            } else {
                Button {
                    onPick(lower.map { max($0, Date()) } ?? bounds.map { min($0.upperBound, Date()) } ?? Date())
                } label: {
                    Text(L10n.text("Any date"))
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text4)
                        .frame(minHeight: 44)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.horizontal, 12)
        .frame(minHeight: 48)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
    }

    @ViewBuilder
    private func pickerFor(date: Date, bounds: PartialRangeThrough<Date>?, lower: Date?, onPick: @escaping (Date?) -> Void) -> some View {
        let binding = Binding<Date>(get: { date }, set: { onPick($0) })
        if let bounds {
            DatePicker("", selection: binding, in: bounds, displayedComponents: .date)
                .labelsHidden()
        } else if let lower {
            DatePicker("", selection: binding, in: lower..., displayedComponents: .date)
                .labelsHidden()
        } else {
            DatePicker("", selection: binding, displayedComponents: .date)
                .labelsHidden()
        }
    }
}

struct ListFilterPanel<Sort: Hashable & Sendable, Extra: View>: View {
    let title: String
    let sorts: [ListSortOption<Sort>]
    var dateLabel: String?
    let total: (ListFilter<Sort>) -> Int?
    let onApply: (ListFilter<Sort>) -> Void
    let onClose: () -> Void
    @ViewBuilder var extra: (Binding<ListFilter<Sort>>) -> Extra
    @State private var draft: ListFilter<Sort>

    init(
        title: String,
        filter: ListFilter<Sort>,
        sorts: [ListSortOption<Sort>],
        dateLabel: String? = nil,
        total: @escaping (ListFilter<Sort>) -> Int? = { _ in nil },
        onApply: @escaping (ListFilter<Sort>) -> Void,
        onClose: @escaping () -> Void,
        @ViewBuilder extra: @escaping (Binding<ListFilter<Sort>>) -> Extra
    ) {
        self.title = title
        self.sorts = sorts
        self.dateLabel = dateLabel
        self.total = total
        self.onApply = onApply
        self.onClose = onClose
        self.extra = extra
        _draft = State(initialValue: filter)
    }

    var body: some View {
        SidePanelScaffold(title: title, onClose: onClose) {
            HStack {
                Spacer()
                LinkButton("Reset", size: 14) { draft = ListFilter() }
                    .frame(minHeight: 44)
                    .accessibilityIdentifier("list.filter.reset")
            }
            extra($draft)
            if let dateLabel {
                VStack(alignment: .leading, spacing: 10) {
                    SheetSectionLabel(dateLabel)
                    DateRangeFields(range: $draft.dates)
                }
            }
            VStack(alignment: .leading, spacing: 10) {
                SheetSectionLabel("Sort by")
                Picker(L10n.text("Sort by"), selection: $draft.sort) {
                    ForEach(sorts, id: \.self) { option in
                        Text(L10n.text(option.label)).tag(option.sort)
                    }
                }
                .pickerStyle(.menu)
                .tint(AikuColor.link)
                .frame(maxWidth: .infinity, minHeight: 48, alignment: .leading)
                .padding(.horizontal, 8)
                .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
                .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
                .accessibilityIdentifier("list.filter.sort")
            }
        } footer: {
            AikuButton(showText) { onApply(draft) }
                .accessibilityIdentifier("list.filter.apply")
        }
    }

    private var showText: String {
        guard let count = total(draft) else { return L10n.text("Show results") }
        return L10n.format("Show %lld results", count)
    }
}

extension ListFilterPanel where Extra == EmptyView {
    init(
        title: String,
        filter: ListFilter<Sort>,
        sorts: [ListSortOption<Sort>],
        dateLabel: String? = nil,
        total: @escaping (ListFilter<Sort>) -> Int? = { _ in nil },
        onApply: @escaping (ListFilter<Sort>) -> Void,
        onClose: @escaping () -> Void
    ) {
        self.init(title: title, filter: filter, sorts: sorts, dateLabel: dateLabel, total: total, onApply: onApply, onClose: onClose) { _ in EmptyView() }
    }
}

struct SidePanelScaffold<Content: View, Footer: View>: View {
    let title: String
    let onClose: () -> Void
    @ViewBuilder let content: () -> Content
    @ViewBuilder let footer: () -> Footer

    var body: some View {
        VStack(spacing: 0) {
            HStack(spacing: 8) {
                Text(L10n.text(title))
                    .font(.aiku(18, .bold))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityAddTraits(.isHeader)
                    .frame(maxWidth: .infinity, alignment: .leading)
                Button(action: onClose) {
                    AppIcon.close.image
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundStyle(AikuColor.text3)
                        .frame(width: 44, height: 44)
                }
                .buttonStyle(.plain)
                .accessibilityLabel(L10n.text("Close"))
            }
            .padding(.leading, 18)
            .padding(.trailing, 8)
            .padding(.top, 8)
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    content()
                }
                .padding(.horizontal, 18)
                .padding(.bottom, 18)
            }
            VStack(spacing: 8) {
                footer()
            }
            .padding(.horizontal, 18)
            .padding(.vertical, 12)
            .background(AikuColor.surface)
            .overlay(alignment: .top) { Rectangle().fill(AikuColor.border).frame(height: 1) }
        }
        .background(AikuColor.bg)
    }
}

extension View {
    func sidePanel<Panel: View>(isPresented: Binding<Bool>, @ViewBuilder panel: @escaping () -> Panel) -> some View {
        modifier(SidePanelPresenter(isPresented: isPresented, panel: panel))
    }
}

private struct SidePanelPresenter<Panel: View>: ViewModifier {
    @Binding var isPresented: Bool
    let panel: () -> Panel

    func body(content: Content) -> some View {
        content.overlay {
            GeometryReader { proxy in
                ZStack(alignment: .trailing) {
                    if isPresented {
                        Color.black.opacity(0.35)
                            .ignoresSafeArea()
                            .onTapGesture { isPresented = false }
                            .accessibilityLabel(L10n.text("Close"))
                            .accessibilityAddTraits(.isButton)
                            .transition(.opacity)
                        panel()
                            .frame(width: min(proxy.size.width * 0.88, 420))
                            .frame(maxHeight: .infinity)
                            .background(AikuColor.bg.ignoresSafeArea())
                            .transition(.move(edge: .trailing))
                    }
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .trailing)
                .animation(.easeOut(duration: 0.22), value: isPresented)
            }
        }
    }
}
