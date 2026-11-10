import SwiftUI

struct ShellSearchView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var query = ""
    @State private var scope: ShellSearchScope = .all
    @State private var recents = ShellSearch.recentSearches
    @FocusState private var isFocused: Bool

    var body: some View {
        let sections = ShellSearch.sections(for: query, scope: scope)
        ScreenScaffold(spacing: 16) {
            HeaderContainer {
                HStack(spacing: 8) {
                    IconButton(.back, label: "Back", tint: AikuColor.onNav) {
                        router.back()
                    }
                    .accessibilityIdentifier("header.back")
                    field
                }
                PillTabs(ShellSearchScope.allCases.map { PillTab(id: $0.id, title: $0.title) }, selectedID: scope.id) { tab in
                    scope = ShellSearchScope(rawValue: tab.id) ?? .all
                }
            }
        } content: {
            if ShellSearch.normalised(query).isEmpty {
                recentSection
            } else if sections.isEmpty {
                emptyResults
            } else {
                ForEach(sections) { section in
                    VStack(alignment: .leading, spacing: 8) {
                        SectionHeading(section.heading)
                        CardList(section.records) { record in
                            resultRow(record)
                        }
                    }
                }
                recentSection
            }
        } footer: {
            EmptyView()
        }
        .onAppear {
            isFocused = true
        }
    }

    private var field: some View {
        HStack(spacing: 10) {
            AppIcon.search.image
                .font(.system(size: 17))
                .foregroundStyle(AikuColor.text4)
                .accessibilityHidden(true)
            TextField("Search", text: $query)
                .font(.aiku(16))
                .foregroundStyle(AikuColor.text)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.search)
                .focused($isFocused)
                .onSubmit { recents = ShellSearch.remembering(query, in: recents) }
                .accessibilityLabel("Search")
                .accessibilityIdentifier("shell.search.field")
            if !query.isEmpty {
                Button {
                    query = ""
                } label: {
                    AppIcon.close.image
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(AikuColor.text4)
                        .frame(width: 32, height: 44)
                }
                .buttonStyle(.plain)
                .accessibilityLabel("Clear search")
            }
        }
        .padding(.horizontal, 12)
        .frame(height: 46)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
        .overlay(
            RoundedRectangle(cornerRadius: 11)
                .strokeBorder(AikuColor.navAccent.opacity(isFocused ? 0.6 : 0), lineWidth: 3)
                .padding(-3)
        )
    }

    @ViewBuilder
    private func resultRow(_ record: ShellSearchRecord) -> some View {
        Button {
            recents = ShellSearch.remembering(query, in: recents)
            router.shellFollow(record.link)
        } label: {
            HStack(spacing: 12) {
                if record.kind == .sko {
                    ProductThumb(size: 48, label: "Product photo of \(record.code)")
                } else if record.kind != .family {
                    IconTile(record.kind == .location ? .locations : .deliveryNote, tone: .info, size: 48, radius: 8)
                }
                VStack(alignment: .leading, spacing: 2) {
                    highlighted(record.title, bold: true)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    highlighted(record.subtitle, bold: false)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .multilineTextAlignment(.leading)
                .frame(maxWidth: .infinity, alignment: .leading)
                if let trailing = record.trailing {
                    Text(trailing)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("shell.search.result.\(record.code)")
    }

    private func highlighted(_ text: String, bold: Bool) -> Text {
        var attributed = AttributedString()
        for segment in ShellSearch.highlight(text, query: query) {
            var part = AttributedString(segment.text)
            if segment.isMatch {
                part.backgroundColor = AikuColor.softBd
            }
            attributed += part
        }
        return Text(attributed)
    }

    private var recentSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeading("Recent searches")
            chips(recents)
        }
    }

    private func chips(_ terms: [String]) -> some View {
        FlowLayout(spacing: 8, lineSpacing: 8) {
            ForEach(terms, id: \.self) { term in
                Button {
                    query = term
                } label: {
                    Text(term)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text)
                        .padding(.horizontal, 12)
                        .frame(minHeight: 44)
                        .background(AikuColor.surface, in: Capsule())
                        .overlay(Capsule().strokeBorder(AikuColor.border))
                        .contentShape(Capsule())
                }
                .buttonStyle(.plain)
            }
        }
    }

    private var emptyResults: some View {
        VStack(alignment: .leading, spacing: 14) {
            EmptyState(
                icon: .search,
                title: "No results for “\(ShellSearch.normalised(query))”",
                message: "Try a SKO reference (SKO-00412), a location code (A-03-02) or a shorter word.",
                actionTitle: session.canScan ? "Scan instead" : nil,
                action: session.canScan ? { router.select(.scan) } : nil
            )
            chips(ShellSearch.emptySuggestions)
        }
    }
}
