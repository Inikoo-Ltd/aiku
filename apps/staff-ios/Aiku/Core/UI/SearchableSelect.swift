import SwiftUI

struct SearchableSelectOption: Identifiable, Hashable, Sendable {
    let id: Int
    let title: String

    static func matches(_ options: [SearchableSelectOption], query: String, limit: Int) -> [SearchableSelectOption] {
        let needle = query.trimmingCharacters(in: .whitespacesAndNewlines)
        let found = needle.isEmpty ? options : options.filter { $0.title.localizedCaseInsensitiveContains(needle) }
        return Array(found.prefix(limit))
    }
}

struct SearchableSelect: View {
    static let maxShown = 100

    let options: [SearchableSelectOption]
    let selectedID: Int?
    let onSelect: (Int?) -> Void
    @State private var isOpen = false
    @State private var query = ""

    private var selectedTitle: String? {
        options.first { $0.id == selectedID }?.title
    }

    private var matches: [SearchableSelectOption] {
        SearchableSelectOption.matches(options, query: query, limit: Self.maxShown)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Button {
                query = ""
                isOpen.toggle()
            } label: {
                HStack(spacing: 8) {
                    Text(selectedTitle ?? L10n.text("Any"))
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(selectedTitle == nil ? AikuColor.text4 : AikuColor.text)
                        .lineLimit(1)
                        .frame(maxWidth: .infinity, alignment: .leading)
                    (isOpen ? AppIcon.chevronUp : AppIcon.chevronDown).image
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(AikuColor.text3)
                        .accessibilityHidden(true)
                }
                .padding(.horizontal, 12)
                .frame(minHeight: 48)
                .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
                .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(isOpen ? AikuColor.primary : AikuColor.borderStrong, lineWidth: isOpen ? 2 : 1))
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityValue(selectedTitle ?? L10n.text("Any"))
            if isOpen {
                list
            }
        }
    }

    private var list: some View {
        VStack(alignment: .leading, spacing: 0) {
            SearchField(text: $query)
                .padding(8)
            RowDivider()
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 0) {
                    if selectedID != nil {
                        row(L10n.text("Any"), isSelected: false, isMuted: true) { choose(nil) }
                    }
                    if matches.isEmpty {
                        Text(L10n.text("Nothing matches"))
                            .font(.aiku(14))
                            .foregroundStyle(AikuColor.text3)
                            .padding(.horizontal, 14)
                            .padding(.vertical, 12)
                    }
                    ForEach(matches) { option in
                        row(option.title, isSelected: option.id == selectedID, isMuted: false) { choose(option.id) }
                    }
                }
            }
            .frame(maxHeight: 280)
        }
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
        .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.borderStrong))
    }

    private func row(_ title: String, isSelected: Bool, isMuted: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(spacing: 8) {
                Text(title)
                    .font(.aiku(15, isSelected ? .semibold : .regular))
                    .foregroundStyle(isSelected ? AikuColor.link : (isMuted ? AikuColor.text3 : AikuColor.text))
                    .lineLimit(2)
                    .frame(maxWidth: .infinity, alignment: .leading)
                if isSelected {
                    AppIcon.check.image
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(AikuColor.link)
                        .accessibilityHidden(true)
                }
            }
            .padding(.horizontal, 14)
            .frame(minHeight: 44)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }

    private func choose(_ id: Int?) {
        isOpen = false
        query = ""
        onSelect(id)
    }
}
