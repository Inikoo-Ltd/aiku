import SwiftUI

extension MobileStockDeliveryRow: Identifiable {}
extension MobileReturnRow: Identifiable {}

extension GoodsInDeliveryStateFilter {
    var title: String {
        switch self {
        case .active: "Active"
        case .dispatched: "Arriving"
        case .received: "Received"
        case .checked: "Checked"
        case .bookingIn: "Booking in"
        case .bookedIn: "Booked in"
        case .placed: "Placed"
        case .notReceived: "Not received"
        case .cancelled: "Cancelled"
        }
    }
}

extension GoodsInDeliverySource {
    var title: String {
        switch self {
        case .suppliers: "Suppliers"
        case .agents: "Agents"
        case .partners: "Partners"
        }
    }
}

extension GoodsInDispatchedBucket {
    var title: String {
        switch self {
        case .today: "Today"
        case .week: "This week"
        case .older: "Older"
        }
    }
}

extension GoodsInDeliverySort {
    static let options: [ListSortOption<GoodsInDeliverySort>] = [
        ListSortOption(sort: nil, label: "Newest first"),
        ListSortOption(sort: .oldest, label: "Oldest first"),
        ListSortOption(sort: .reference, label: "Reference"),
        ListSortOption(sort: .supplier, label: "Supplier"),
        ListSortOption(sort: .expectedDate, label: "Expected date"),
        ListSortOption(sort: .dispatchedDate, label: "Dispatched date"),
        ListSortOption(sort: .items, label: "Items"),
    ]
}

extension GoodsInDeliveryFilter {
    static let dateLabel = "Date"

    @MainActor
    func summary() -> [String] {
        let picked = [
            source.map { L10n.text($0.title) },
            dispatched.map { "\(L10n.text("Dispatched")): \(L10n.text($0.title))" },
        ]
        return picked.compactMap { $0 } + datesAndSort.summary(sorts: GoodsInDeliverySort.options, dateLabel: Self.dateLabel)
    }
}

extension GoodsInReturnSort {
    static let dateLabel = "Date received"
    static let options: [ListSortOption<GoodsInReturnSort>] = [
        ListSortOption(sort: nil, label: "Newest received"),
        ListSortOption(sort: .oldestReceived, label: "Oldest received"),
        ListSortOption(sort: .reference, label: "Reference"),
        ListSortOption(sort: .customer, label: "Customer"),
    ]
}

extension GoodsInReturnStateFilter {
    static let chips: [GoodsInReturnStateFilter] = [.all, .received, .returning, .returned, .done, .cancelled]

    var title: String {
        switch self {
        case .all: "All"
        case .toProcess: "To process"
        case .received: "Received"
        case .returning: "Returning"
        case .returned: "Returned"
        case .done: "Done"
        case .cancelled: "Cancelled"
        }
    }
}

struct GoodsInStateChips<Option: Hashable>: View {
    let options: [Option]
    let selection: Option?
    let title: (Option) -> String
    let count: (Option) -> Int?
    let onSelect: (Option) -> Void

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 6) {
                ForEach(options, id: \.self) { option in
                    FilterChip(title(option), count: count(option), isSelected: option == selection) {
                        onSelect(option)
                    }
                    .accessibilityIdentifier("goodsIn.chip.\(title(option))")
                }
            }
            .padding(.horizontal, 16)
        }
        .scrollBounceBehavior(.basedOnSize, axes: [.horizontal, .vertical])
        .padding(.horizontal, -16)
    }
}

struct GoodsInPagedFooter: View {
    let isLoadingMore: Bool
    let nextPageProblem: String?
    let failedTitle: String
    let canLoadMore: Bool
    let onLoadMore: () -> Void

    var body: some View {
        if let nextPageProblem {
            GoodsOutLoadProblem(title: failedTitle, message: nextPageProblem, retry: onLoadMore)
        } else if isLoadingMore {
            ProgressView()
                .frame(maxWidth: .infinity, minHeight: 44)
                .accessibilityLabel("Loading more")
        } else if canLoadMore {
            LazyVStack {
                Color.clear
                    .frame(height: 1)
                    .onAppear(perform: onLoadMore)
            }
        }
    }
}

extension GoodsInPagedList {
    var isLoadingMore: Bool { (loadingPage ?? 0) > 1 }
    var firstPageProblem: String? { failedPage == 1 ? problem : nil }
    var nextPageProblem: String? { (failedPage ?? 0) > 1 ? problem : nil }
    var showsEmpty: Bool { hasLoaded && (loadingPage == nil || isRefreshing) && rows.isEmpty }
}

private struct GoodsInRecordRow: View {
    let row: GoodsInListRow
    let identifier: String
    let action: () -> Void

    var body: some View {
        RecordRow(
            row.reference,
            date: row.date,
            dateCaption: row.dateCaption,
            party: row.party,
            badges: row.badges,
            details: row.details,
            action: action
        )
        .accessibilityIdentifier(identifier)
    }
}

struct GoodsInDeliveriesScreen: View {
    @State var feed: GoodsInDeliveriesFeed
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(Session.self) private var session
    @State private var isFiltering = false
    @State private var search = ""

    var body: some View {
        GoodsInAccessGate(isAllowed: \.viewGoodsIn) {
            AppScreen(title: "Stock deliveries", kicker: "Goods in · \(session.warehouseLabel)") {
                ModuleTabs(.goodsIn, current: .stockDeliveries)
            } content: {
                content
            }
        }
        .task { await feed.start() }
        .screenRefreshable { await feed.refresh() }
        .hardwareScanner(isEnabled: !isFiltering) { code in
            Task { await feed.lookUpScan(code) }
        }
        .onChange(of: feed.scanOutcome) { _, outcome in
            guard let outcome else { return }
            switch outcome {
            case let .open(id, reference):
                router.open(GoodsInRoute.stockDelivery(id: id, reference: reference))
            case .noMatch(let code):
                ScanFeedback.shared.rejected()
                toasts.show("\(code) is not a stock delivery", tone: .info)
            case .failed(_, let message):
                toasts.show(message, tone: .danger)
            }
            feed.scanOutcome = nil
        }
        .sidePanel(isPresented: $isFiltering) {
            GoodsInDeliveryFilterPanel(feed: feed, initial: feed.filter) { applied in
                isFiltering = false
                Task { await feed.apply(applied) }
            } onClose: {
                isFiltering = false
            }
        }
        .onAppear { search = feed.filter.search }
    }

    @ViewBuilder
    private var content: some View {
        let list = feed.list
        let trimmed = feed.filter.search.trimmingCharacters(in: .whitespaces)
        GoodsInStateChips(
            options: GoodsInDeliveryStateFilter.allCases,
            selection: feed.filter.state,
            title: \.title,
            count: { feed.counts?.count($0.rawValue) }
        ) { state in
            Task { await feed.select(state) }
        }
        SearchWithFilter(filterCount: feed.filter.panelCount, onFilter: { isFiltering = true }) {
            SearchField(text: $search, placeholder: "Search stock deliveries", onScan: { router.select(.scan) })
                .onChange(of: search) { _, text in
                    if text != feed.filter.search { feed.search(text) }
                }
                .accessibilityIdentifier("goodsIn.deliveries.search")
        }
        ActiveFiltersLine(parts: feed.filter.summary()) {
            Task { await feed.clearFilters() }
        }
        if let code = feed.lookingUpCode {
            InlineAlert(tone: .info, title: "Looking up \(code)…")
        }
        if let problem = list.firstPageProblem {
            GoodsOutLoadProblem(title: "Stock deliveries could not be loaded", message: problem) {
                Task { await feed.retry() }
            }
        }
        if let savedAt = feed.savedAt {
            SavedCopyNote(savedAt: savedAt)
        }
        if !list.rows.isEmpty {
            GoodsInCardList(items: list.rows) { row in
                GoodsInRecordRow(row: GoodsInListRow.delivery(row), identifier: "goodsIn.delivery.\(row.reference)") {
                    router.open(GoodsInRoute.stockDelivery(id: row.id, reference: row.reference))
                }
            }
        } else if list.showsEmpty, !trimmed.isEmpty {
            GoodsInHelper("No stock delivery matches “\(trimmed)”.")
        } else if list.showsEmpty {
            GoodsInHelper("No stock deliveries here.")
        } else if list.problem == nil {
            GoodsOutSkeletonCard()
        }
        GoodsInPagedFooter(
            isLoadingMore: list.isLoadingMore,
            nextPageProblem: list.nextPageProblem,
            failedTitle: "More stock deliveries could not be loaded",
            canLoadMore: list.canLoadMore,
            onLoadMore: { Task { await feed.loadMore() } }
        )
        GoodsInHelper("Scan a stock delivery reference to open it.")
    }
}

struct GoodsInDeliveryFilterPanel: View {
    let feed: GoodsInDeliveriesFeed
    let onApply: (GoodsInDeliveryFilter) -> Void
    let onClose: () -> Void
    @State private var draft: GoodsInDeliveryFilter

    init(feed: GoodsInDeliveriesFeed, initial: GoodsInDeliveryFilter, onApply: @escaping (GoodsInDeliveryFilter) -> Void, onClose: @escaping () -> Void) {
        self.feed = feed
        self.onApply = onApply
        self.onClose = onClose
        _draft = State(initialValue: initial)
    }

    var body: some View {
        SidePanelScaffold(title: "Filter stock deliveries", onClose: onClose) {
            HStack {
                Spacer()
                LinkButton("Reset", size: 14) { draft = draft.cleared() }
                    .frame(minHeight: 44)
                    .accessibilityIdentifier("list.filter.reset")
            }
            chipSection("Source") {
                ForEach(GoodsInDeliverySource.allCases, id: \.self) { source in
                    FilterChip(source.title, isSelected: draft.source == source) {
                        draft.source = draft.source == source ? nil : source
                    }
                    .accessibilityIdentifier("goodsIn.filter.source.\(source.rawValue)")
                }
            }
            chipSection("Dispatched") {
                ForEach(GoodsInDispatchedBucket.allCases, id: \.self) { bucket in
                    FilterChip(bucket.title, isSelected: draft.dispatched == bucket) {
                        draft.dispatched = draft.dispatched == bucket ? nil : bucket
                    }
                    .accessibilityIdentifier("goodsIn.filter.dispatched.\(bucket.rawValue)")
                }
            }
            VStack(alignment: .leading, spacing: 10) {
                SheetSectionLabel(GoodsInDeliveryFilter.dateLabel)
                DateRangeFields(range: $draft.dates)
            }
            VStack(alignment: .leading, spacing: 10) {
                SheetSectionLabel("Sort by")
                Picker(L10n.text("Sort by"), selection: $draft.sort) {
                    ForEach(GoodsInDeliverySort.options, id: \.self) { option in
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
            AikuButton(showTitle) { onApply(draft) }
                .accessibilityIdentifier("list.filter.apply")
        }
        .onAppear { feed.previewTotal(draft) }
        .onChange(of: draft) { _, changed in feed.previewTotal(changed) }
    }

    private var showTitle: String {
        guard let total = feed.total(for: draft) else { return "Show results" }
        return total == 1 ? "Show 1 result" : "Show \(total) results"
    }

    private func chipSection<Content: View>(_ title: String, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            SheetSectionLabel(title)
            FlowLayout(spacing: 8, lineSpacing: 8) {
                content()
            }
        }
    }
}

struct GoodsInReturnsScreen: View {
    @State var feed: GoodsInReturnsFeed
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var isFiltering = false
    @State private var search = ""

    var body: some View {
        GoodsInAccessGate(isAllowed: \.viewReturns, permission: "returns.view") {
            AppScreen(title: feed.state == .toProcess ? "Returns to process" : "Returns", kicker: "Goods in · \(session.warehouseLabel)") {
                ModuleTabs(.goodsIn, current: .goodsInReturns)
            } content: {
                content
            }
        }
        .task { await feed.start() }
        .screenRefreshable { await feed.refresh() }
        .sidePanel(isPresented: $isFiltering) {
            ListFilterPanel(
                title: "Filter returns",
                filter: feed.filter,
                sorts: GoodsInReturnSort.options,
                dateLabel: GoodsInReturnSort.dateLabel,
                total: { feed.total(for: $0) },
                onApply: { applied in
                    isFiltering = false
                    Task { await feed.apply(applied) }
                },
                onClose: { isFiltering = false }
            )
        }
        .onAppear { search = feed.search }
    }

    @ViewBuilder
    private var content: some View {
        let list = feed.list
        let trimmed = feed.search.trimmingCharacters(in: .whitespaces)
        GoodsInStateChips(
            options: GoodsInReturnStateFilter.chips,
            selection: feed.state,
            title: \.title,
            count: { feed.counts?.count($0.rawValue) }
        ) { state in
            Task { await feed.select(state) }
        }
        SearchWithFilter(filterCount: feed.filter.activeCount, onFilter: { isFiltering = true }) {
            SearchField(text: $search, placeholder: "Search returns")
                .onChange(of: search) { _, text in
                    if text != feed.search { feed.search(text) }
                }
                .accessibilityIdentifier("goodsIn.returns.search")
        }
        ActiveFiltersLine(parts: feed.filter.summary(sorts: GoodsInReturnSort.options, dateLabel: GoodsInReturnSort.dateLabel)) {
            Task { await feed.clearFilters() }
        }
        if let problem = list.firstPageProblem {
            GoodsOutLoadProblem(title: "Returns could not be loaded", message: problem) {
                Task { await feed.retry() }
            }
        }
        if let savedAt = feed.savedAt {
            SavedCopyNote(savedAt: savedAt)
        }
        if !list.rows.isEmpty {
            GoodsInCardList(items: list.rows) { row in
                GoodsInRecordRow(row: GoodsInListRow.goodsReturn(row), identifier: "goodsIn.return.\(row.reference)") {
                    router.open(GoodsInRoute.returnDetail(id: row.id, reference: row.reference))
                }
            }
        } else if list.showsEmpty, !trimmed.isEmpty {
            GoodsInHelper("No return matches “\(trimmed)”.")
        } else if list.showsEmpty {
            GoodsInHelper("No returns here.")
        } else if list.problem == nil {
            GoodsOutSkeletonCard()
        }
        GoodsInPagedFooter(
            isLoadingMore: list.isLoadingMore,
            nextPageProblem: list.nextPageProblem,
            failedTitle: "More returns could not be loaded",
            canLoadMore: list.canLoadMore,
            onLoadMore: { Task { await feed.loadMore() } }
        )
        GoodsInHelper("Customer returns arriving at goods in. Each item goes back to stock, or is set as damaged or not returned.")
    }
}

struct GoodsInBacklogScreen: View {
    @State var feed: GoodsInBacklogFeed
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        GoodsInAccessGate(isAllowed: \.viewGoodsIn) {
            AppScreen(title: "Incoming hub", kicker: "Goods in · \(session.warehouseLabel)") {
                ModuleTabs(.goodsIn, current: .goodsInBacklog)
            } content: {
                content
            }
        }
        .onAppear { Task { await feed.load() } }
        .screenRefreshable { await feed.load() }
    }

    @ViewBuilder
    private var content: some View {
        let can = warehouseStore.selected?.can
        let rows = feed.rows.map { rows in rows.filter { row in can.map(row.isVisible) ?? false } }
        if let problem = feed.loadError {
            GoodsOutLoadProblem(title: "The incoming hub could not be loaded", message: problem) {
                Task { await feed.load() }
            }
        }
        if let rows {
            if rows.isEmpty {
                GoodsInHelper("Nothing to show for this warehouse.")
            } else {
                ForEach(rows, id: \.key) { row in
                    GoodsInSourceCard(row: row) { figure in open(row, figure: figure) }
                }
            }
        } else if feed.loadError == nil {
            GoodsOutSkeletonCard(rows: 3)
        }
        GoodsInHelper("Arriving means dispatched by the supplier. Tap a source or a figure to open its list.")
    }

    private func open(_ row: MobileGoodsInHubEntry, figure: GoodsInHubFigure?) {
        switch row.key {
        case GoodsInHubKey.fulfilment:
            router.open(FulfilmentRoute.deliveries)
        case GoodsInHubKey.returns:
            router.open(GoodsInRoute.returnsIn(state: figure?.returnState ?? .all))
        default:
            guard let source = row.source else { return }
            var filter = GoodsInDeliveryFilter(source: source)
            if let figure { filter.state = figure.deliveryState }
            router.open(GoodsInRoute.stockDeliveriesFiltered(filter))
        }
    }
}

private struct GoodsInSourceCard: View {
    let row: MobileGoodsInHubEntry
    let onOpen: (GoodsInHubFigure?) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Button { onOpen(nil) } label: {
                HStack {
                    Text(row.label.isEmpty ? "Goods in" : row.label)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                        .frame(maxWidth: .infinity, alignment: .leading)
                    AppIcon.chevronRight.image
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(AikuColor.faint)
                        .accessibilityHidden(true)
                }
                .padding(.horizontal, 14)
                .padding(.vertical, 10)
                .frame(minHeight: 48)
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("goodsIn.hub.\(row.key)")
            let counts = row.counts
            if !counts.isEmpty {
                HStack(spacing: 6) {
                    ForEach(counts, id: \.figure) { count in
                        Button { onOpen(count.figure) } label: {
                            VStack(spacing: 2) {
                                Text(count.value.grouped)
                                    .font(.aiku(17, .bold))
                                    .foregroundStyle(foreground(count.figure))
                                    .monospacedDigit()
                                Text(label(count.figure))
                                    .font(.aiku(10, .semibold))
                                    .foregroundStyle(AikuColor.text3)
                                    .multilineTextAlignment(.center)
                                    .lineLimit(2)
                                    .minimumScaleFactor(0.8)
                            }
                            .padding(.horizontal, 2)
                            .padding(.vertical, 8)
                            .frame(maxWidth: .infinity, minHeight: 44)
                            .background(background(count.figure), in: RoundedRectangle(cornerRadius: 8))
                            .contentShape(Rectangle())
                        }
                        .buttonStyle(.plain)
                        .accessibilityIdentifier("goodsIn.hub.\(row.key).\(count.figure.rawValue)")
                    }
                }
                .padding(.horizontal, 14)
            }
        }
        .padding(.bottom, 14)
        .cardSurface()
    }

    private func label(_ figure: GoodsInHubFigure) -> String {
        switch figure {
        case .arriving: "Arriving"
        case .received: "Received"
        case .checked: "Checked"
        case .bookingIn: row.key == GoodsInHubKey.returns ? "Returning" : "Booking in"
        case .bookedInWeek: "Booked in 7d"
        case .placedWeek: "Placed 7d"
        }
    }

    private func foreground(_ figure: GoodsInHubFigure) -> Color {
        switch figure {
        case .arriving: AikuColor.text2
        case .received, .checked, .bookingIn: AikuColor.link
        case .bookedInWeek, .placedWeek: AikuColor.success
        }
    }

    private func background(_ figure: GoodsInHubFigure) -> Color {
        switch figure {
        case .arriving: AikuColor.bg
        case .received, .checked, .bookingIn: AikuColor.soft
        case .bookedInWeek, .placedWeek: AikuColor.successSoft
        }
    }
}
