import SwiftUI

struct ShellSearchView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(AuthStore.self) private var authStore
    @Environment(\.signOut) private var signOut
    @State private var store = ShellSearchStore.live(.shared)
    @State private var warehouseStore = WarehouseStore.shared
    @State private var isChoosingWarehouse = false
    @FocusState private var isFocused: Bool

    var body: some View {
        ScreenScaffold(spacing: 16) {
            HeaderContainer {
                HStack(spacing: 8) {
                    IconButton(.back, label: "Back", tint: AikuColor.onNav) {
                        router.back()
                    }
                    .accessibilityIdentifier("header.back")
                    field
                }
                if !store.scopes.isEmpty {
                    PillTabs(
                        store.scopes.map { PillTab(id: ShellSearchFormat.scopeID($0), title: ShellSearchFormat.scopeTitle($0)) },
                        selectedID: ShellSearchFormat.scopeID(store.scope)
                    ) { tab in
                        store.selectScope(ShellSearchFormat.scope(id: tab.id))
                    }
                }
            }
        } content: {
            searchContent
        } footer: {
            EmptyView()
        }
        .shellWarehouseSheet(isPresented: $isChoosingWarehouse)
        .task(id: ShellSearchContext(warehouseID: warehouseStore.selectedID, userID: authStore.userID)) {
            store.start(warehouseID: warehouseStore.selectedID, userID: authStore.userID)
        }
        .onAppear {
            if store.query.isEmpty {
                isFocused = true
            }
        }
    }

    @ViewBuilder
    private var searchContent: some View {
        if store.isSessionExpired {
            InlineAlert(tone: .warn, title: "Your session has ended", message: "Sign in again to search.") {
                LinkButton("Sign in again") { signOut() }
            }
        } else if warehouseStore.selectedID == nil {
            InlineAlert(tone: .warn, title: "Choose a warehouse to search", message: "Search looks in the warehouse you work in.") {
                LinkButton("Choose warehouse") { isChoosingWarehouse = true }
            }
        } else if let problem = store.problem {
            InlineAlert(title: "Couldn’t search", message: problem) {
                LinkButton("Try again") { store.retry() }
            }
        } else if store.needle.isEmpty {
            if store.shownRecent.isEmpty {
                hint
            } else {
                recentSection
            }
        } else if store.isQueryTooShort {
            hint
        } else if store.isEmpty {
            emptyResults
        } else if !store.hasResults {
            loadingSkeleton
        } else {
            results
        }
    }

    private var field: some View {
        HStack(spacing: 10) {
            AppIcon.search.image
                .font(.system(size: 17))
                .foregroundStyle(AikuColor.text4)
                .accessibilityHidden(true)
            TextField("Reference, code or name", text: Binding(get: { store.query }, set: { store.updateQuery($0) }))
                .font(.aiku(16))
                .foregroundStyle(AikuColor.text)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.search)
                .focused($isFocused)
                .onSubmit { store.searchNow() }
                .accessibilityLabel("Search")
                .accessibilityIdentifier("shell.search.field")
            if !store.query.isEmpty {
                Button {
                    store.updateQuery("")
                    isFocused = true
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
    private var results: some View {
        if let exact = store.exact {
            VStack(alignment: .leading, spacing: 8) {
                SectionHeading("Exact match", isUppercased: false)
                Card {
                    resultRow(exact)
                }
            }
        }
        ForEach(store.groups) { group in
            VStack(alignment: .leading, spacing: 8) {
                SectionHeading(ShellSearchFormat.heading(group), isUppercased: false)
                Card {
                    ForEach(Array(group.rows.enumerated()), id: \.element.id) { index, row in
                        if index > 0 {
                            RowDivider()
                        }
                        resultRow(row)
                    }
                    groupFooter(group)
                }
            }
        }
        if let moreProblem = store.moreProblem {
            InlineAlert(title: "Couldn’t load more results", message: moreProblem) {
                LinkButton("Try again") { store.retry() }
            }
        }
    }

    @ViewBuilder
    private func groupFooter(_ group: MobileSearchGroup) -> some View {
        if store.isPaging, store.isLoadingMore {
            RowDivider()
            SkeletonRow()
        } else if group.hasMore, !store.isPaging || store.moreProblem == nil {
            if !group.rows.isEmpty {
                RowDivider()
            }
            Button {
                store.showMore(group.type)
            } label: {
                Text("Show more")
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
                    .frame(maxWidth: .infinity, minHeight: 48)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("shell.search.more.\(group.type.rawValue)")
        }
    }

    private func resultRow(_ row: MobileSearchRow) -> some View {
        Button {
            store.rememberSearch()
            router.shellFollow(ShellSearchFormat.link(row))
        } label: {
            HStack(spacing: 12) {
                if ShellSearchFormat.hasPhoto(row.type) {
                    photo(row)
                }
                VStack(alignment: .leading, spacing: 2) {
                    HStack(spacing: 8) {
                        highlighted(row.title)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                            .lineLimit(1)
                        if ShellSearchFormat.hasStateBadge(row.type), !row.stateLabel.isEmpty {
                            StatusChip(row.stateLabel, tone: ShellSearchFormat.tone(row))
                        }
                    }
                    if !row.subtitle.isEmpty {
                        highlighted(row.subtitle)
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                            .lineLimit(2)
                    }
                }
                .multilineTextAlignment(.leading)
                .frame(maxWidth: .infinity, alignment: .leading)
                if let quantity = ShellSearchFormat.quantity(row) {
                    Text("\(Text(quantity.number).bold().foregroundStyle(AikuColor.text)) in locations")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .lineLimit(1)
                        .accessibilityLabel(quantity.text)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .frame(minHeight: 56)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("shell.search.result.\(row.type.rowType).\(row.id)")
    }

    @ViewBuilder
    private func photo(_ row: MobileSearchRow) -> some View {
        let label = L10n.format("Product photo of %@", row.title)
        if let url = row.imageUrl.flatMap(URL.init(string:)) {
            AsyncImage(url: url) { image in
                image.resizable().scaledToFill()
            } placeholder: {
                ProductThumb(size: 48, label: label)
            }
            .frame(width: 48, height: 48)
            .clipShape(RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.border))
            .accessibilityLabel(label)
        } else {
            ProductThumb(size: 48, label: label)
        }
    }

    private func highlighted(_ text: String) -> Text {
        var attributed = AttributedString()
        for segment in ShellSearchFormat.highlight(text, query: store.needle) {
            var part = AttributedString(segment.text)
            if segment.isMatch {
                part.backgroundColor = AikuColor.softBd
            }
            attributed += part
        }
        return Text(attributed)
    }

    private var hint: some View {
        HelperText(L10n.format("Type at least %lld characters of a reference, code or name.", ShellSearchFormat.minimumQueryLength))
    }

    private var recentSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeading("Recent searches", isUppercased: false)
            FlowLayout(spacing: 8, lineSpacing: 8) {
                ForEach(store.shownRecent, id: \.self) { term in
                    Button {
                        store.pickRecent(term)
                    } label: {
                        Text(term)
                            .font(.aiku(13, .semibold))
                            .foregroundStyle(AikuColor.text2)
                            .lineLimit(1)
                            .padding(.horizontal, 12)
                            .frame(minHeight: 44)
                            .background(AikuColor.surface, in: Capsule())
                            .overlay(Capsule().strokeBorder(AikuColor.border))
                            .contentShape(Capsule())
                    }
                    .buttonStyle(.plain)
                    .accessibilityIdentifier("shell.search.recent.\(term)")
                }
            }
        }
    }

    private var emptyResults: some View {
        EmptyState(
            icon: .search,
            title: L10n.format("No results for “%@”", store.needle),
            message: "Try a SKO reference (SKO-00412), a location code (A-03-02) or a shorter word.",
            actionTitle: session.canScan ? "Scan instead" : nil,
            action: session.canScan ? { router.select(.scan) } : nil
        )
    }

    private var loadingSkeleton: some View {
        Card {
            ForEach(0..<4, id: \.self) { index in
                if index > 0 {
                    RowDivider()
                }
                SkeletonRow()
            }
        }
        .accessibilityLabel("Searching")
    }
}

private struct ShellSearchContext: Hashable {
    let warehouseID: Int?
    let userID: Int?
}
