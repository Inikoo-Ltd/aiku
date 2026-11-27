import SwiftUI

enum GoodsInReturnItemAction: String, Hashable {
    case backToStock
    case damaged
    case notReturned
}

private enum GoodsInReturnSheet: Identifiable, Hashable {
    case item(itemID: Int, action: GoodsInReturnItemAction)

    var id: String {
        switch self {
        case let .item(itemID, action): "\(itemID)-\(action.rawValue)"
        }
    }
}

extension MobileReturnItem: Identifiable {}

struct GoodsInReturnScreen: View {
    let reference: String
    @State var feed: GoodsInReturnFeed

    var body: some View {
        GoodsInAccessGate(isAllowed: \.viewReturns, permission: "returns.view") {
            if let goodsReturn = feed.goodsReturn {
                GoodsInReturnContent(feed: feed, goodsReturn: goodsReturn)
            } else {
                GoodsInLoadingScreen(
                    title: reference,
                    kicker: "Return",
                    failedTitle: L10n.format("%@ could not be loaded", reference),
                    problem: feed.loadError,
                    retry: { Task { await feed.load() } }
                )
                .screenRefreshable { await feed.load() }
            }
        }
        .onAppear {
            Task { await feed.load() }
        }
        .liveReload(on: LiveChannelName.record(.customerReturn, loadedID: feed.goodsReturn?.id), events: LiveEventName.record) {
            await feed.load()
        }
    }
}

private struct GoodsInReturnContent: View {
    let feed: GoodsInReturnFeed
    let goodsReturn: MobileReturn
    @Environment(AppRouter.self) private var router
    @State private var warehouseStore = WarehouseStore.shared
    @State private var sheet: GoodsInReturnSheet?
    @State private var sheetProblem: String?
    @State private var undoing: MobileReturnPut?
    @State private var isConfirmingSetAsReturned = false

    private var can: MobileWarehouse.Abilities? { warehouseStore.selected?.can }
    private var canHandle: Bool { can?.handleReturns == true }
    private var canPutBack: Bool { canHandle && goodsReturn.availableActions.action(GoodsInActionKey.putBack)?.isDisabled == false }
    private var canUndo: Bool { canHandle && goodsReturn.availableActions.action(GoodsInActionKey.undo)?.isDisabled == false }
    private var start: MobileGoodsInAction? { canHandle ? goodsReturn.availableActions.action(GoodsInActionKey.start) : nil }
    private var setAsReturned: MobileGoodsInAction? { canHandle ? goodsReturn.availableActions.action(GoodsInActionKey.setAsReturned) : nil }

    private var kicker: String {
        if let name = goodsReturn.customer?.name, !name.isEmpty { return L10n.format("Return · %@", name) }
        return goodsReturn.typeLabel.isEmpty ? "Return" : goodsReturn.typeLabel
    }

    var body: some View {
        screen
            .screenRefreshable { await feed.load() }
            .goodsInEvents(
                feed.event,
                onHandled: { feed.event = nil },
                onSheetProblem: sheet == nil ? nil : { sheetProblem = $0 },
                doneText: doneText,
                onDone: closeSheet
            )
            .aikuSheet(item: $sheet) { current in
                switch current {
                case let .item(itemID, action):
                    if let item = goodsReturn.item(itemID) {
                        GoodsInReturnItemSheet(
                            item: item,
                            action: action,
                            isWorking: feed.isWorking,
                            problem: sheetProblem ?? feed.writeErrors.values.first,
                            onConfirm: { quantity, linkID in confirm(item: item, action: action, quantity: quantity, linkID: linkID) },
                            onDismiss: closeSheet
                        )
                    }
                }
            }
            .aikuConfirm(
                "Undo this?",
                isPresented: Binding(get: { undoing != nil }, set: { if !$0 { undoing = nil } }),
                message: undoMessage,
                confirmTitle: "Undo"
            ) {
                if let put = undoing {
                    Task { await feed.undo(putID: put.id) }
                }
            }
            .aikuConfirm(
                L10n.format("Set %@ as returned?", goodsReturn.reference),
                isPresented: $isConfirmingSetAsReturned,
                message: "Anything still remaining becomes Not returned.",
                confirmTitle: setAsReturned.map { $0.label.isEmpty ? "Set as returned" : $0.label } ?? "Set as returned"
            ) {
                Task { await feed.setAsReturned() }
            }
    }

    private var undoMessage: String {
        guard let put = undoing else { return "" }
        if put.type == GoodsInReturnPutType.sow {
            return L10n.format("%@ is taken back out of %@ and goes back to remaining.", GoodsInText.quantity(put.quantity, label: put.quantityLabel), put.location?.code ?? "")
        }
        return "The quantity goes back to remaining."
    }

    @ViewBuilder
    private var screen: some View {
        if let action = start ?? setAsReturned {
            AppScreen(title: goodsReturn.reference, kicker: kicker) {
                content
            } footer: {
                footer(action)
            }
        } else {
            AppScreen(title: goodsReturn.reference, kicker: kicker) {
                content
            }
        }
    }

    @ViewBuilder
    private func footer(_ action: MobileGoodsInAction) -> some View {
        if action.isDisabled {
            GoodsInDisabledNotice(action: action, fallbackTitle: action.key == GoodsInActionKey.start ? "Start" : "Set as returned")
        } else if action.key == GoodsInActionKey.start {
            AikuButton(action.label.isEmpty ? "Start" : action.label, look: GoodsInActionKey.look(for: action.key), isLoading: feed.working == .start) {
                Task { await feed.start() }
            }
            .accessibilityIdentifier("goodsIn.return.start")
        } else {
            AikuButton(action.label.isEmpty ? "Set as returned" : action.label, look: GoodsInActionKey.look(for: action.key), isLoading: feed.working == .setAsReturned) {
                isConfirmingSetAsReturned = true
            }
            .accessibilityIdentifier("goodsIn.return.setAsReturned")
        }
    }

    @ViewBuilder
    private var content: some View {
        if let problem = feed.loadError {
            GoodsOutLoadProblem(title: "The return could not be refreshed", message: problem) {
                Task { await feed.load() }
            }
        }
        OfflineRecordNotices(queue: feed.offlineQueue, operations: OfflineOperation.returnPuts, copyAt: feed.copyAt) { entry in
            GoodsInOfflineText.summary(entry, goodsReturn: goodsReturn)
        }
        if goodsReturn.state == GoodsInReturnStateKey.cancelled {
            InlineAlert(tone: .danger, title: goodsReturn.stateLabel, message: "This return was cancelled.")
        } else {
            GoodsInSteps(steps: steps, label: "Return progress")
        }
        details
        if !goodsReturn.timeline.isEmpty {
            GoodsInTimelineCard(entries: goodsReturn.timeline)
        }
        let handled = goodsReturn.items.filter(\.isHandled).count
        GoodsOutSectionTitle(title: "Items", detail: L10n.format("%lld of %lld handled", handled, goodsReturn.items.count))
        ForEach(goodsReturn.items) { item in
            GoodsInReturnItemCard(
                item: item,
                canPutBack: canPutBack && item.hasRemaining,
                canUndo: canUndo,
                onAction: { action in open(.item(itemID: item.id, action: action)) },
                onUndo: { undoing = $0 }
            )
        }
        GoodsInHelper(goodsReturn.state == GoodsInReturnStateKey.returning
            ? "Put each item back to stock, or set it as damaged or not returned. Then set the return as returned."
            : "Customer returns arriving at goods in. Each item goes back to stock, or is set as damaged or not returned.")
    }

    private var steps: [GoodsInStepItem] {
        let states = [
            (GoodsInReturnStateKey.received, "Received"),
            (GoodsInReturnStateKey.returning, "Returning"),
            (GoodsInReturnStateKey.returned, "Returned"),
            (GoodsInReturnStateKey.done, "Done"),
        ]
        let current = states.firstIndex { $0.0 == goodsReturn.state } ?? -1
        let isComplete = goodsReturn.state == GoodsInReturnStateKey.done
        return states.enumerated().map { index, step in
            GoodsInStepItem(id: step.0, title: step.1, isDone: index < current || (isComplete && index == current), isCurrent: index == current && !isComplete)
        }
    }

    @ViewBuilder
    private var details: some View {
        let rows = [
            goodsReturn.customer.flatMap { $0.name.isEmpty ? nil : GoodsOutDetailRow(icon: .person, text: $0.name) },
            goodsReturn.order.flatMap { $0.reference.isEmpty ? nil : GoodsOutDetailRow(icon: .order, text: L10n.format("Order %@", $0.reference)) },
            goodsReturn.typeLabel.isEmpty ? nil : GoodsOutDetailRow(icon: .document, text: L10n.format("Type %@", goodsReturn.typeLabel)),
            goodsReturn.handler.flatMap { $0.name.isEmpty ? nil : GoodsOutDetailRow(icon: .person, text: L10n.format("Handled by %@", $0.name)) },
        ].compactMap { $0 }
        if !rows.isEmpty {
            GoodsOutDetailCard(title: "Return", rows: rows)
        }
        if let note = goodsReturn.deliveryNote, !note.reference.isEmpty {
            Card {
                GoodsInActionLinkRow(
                    icon: .deliveryNote,
                    title: note.reference,
                    subtitle: "Original delivery note",
                    tone: .primary,
                    identifier: "goodsIn.return.deliveryNote",
                    action: can?.viewGoodsOut == true ? { router.open(GoodsOutRoute.deliveryNoteID(id: note.id)) } : nil
                )
            }
        }
    }

    private func open(_ next: GoodsInReturnSheet) {
        sheetProblem = nil
        sheet = next
    }

    private func closeSheet() {
        sheet = nil
        sheetProblem = nil
        feed.clearWriteErrors()
    }

    private func confirm(item: MobileReturnItem, action: GoodsInReturnItemAction, quantity: Double, linkID: Int?) {
        Task {
            switch action {
            case .backToStock:
                if let linkID {
                    await feed.backToStock(itemID: item.id, quantity: quantity, locationOrgStockID: linkID)
                }
            case .damaged:
                await feed.damaged(itemID: item.id, quantity: quantity)
            case .notReturned:
                await feed.notReturned(itemID: item.id, quantity: quantity)
            }
        }
    }

    private func doneText() -> String? {
        let current = feed.goodsReturn ?? goodsReturn
        switch feed.lastWrite {
        case .start: return L10n.format("%@ started", current.reference)
        case .setAsReturned: return L10n.format("%@ set as returned", current.reference)
        case .returned(let itemID, _, _, _): return L10n.format("%@ back to stock", current.item(itemID)?.code ?? "")
        case .damaged(let itemID, _, _): return L10n.format("%@ set as damaged", current.item(itemID)?.code ?? "")
        case .notReturned(let itemID, _, _): return L10n.format("%@ set as not returned", current.item(itemID)?.code ?? "")
        case .undoPut: return L10n.text("Undone")
        case nil: return nil
        }
    }
}

private struct GoodsInTimelineCard: View {
    let entries: [MobileReturnTimelineEntry]

    var body: some View {
        StateTimeline(steps: entries.map { StateTimelineStep(label: $0.label, date: GoodsInText.moment($0.at)) })
    }
}

private struct GoodsInReturnItemCard: View {
    let item: MobileReturnItem
    let canPutBack: Bool
    let canUndo: Bool
    let onAction: (GoodsInReturnItemAction) -> Void
    let onUndo: (MobileReturnPut) -> Void

    var body: some View {
        let code = item.code.isEmpty ? L10n.text("Unknown SKO") : item.code
        Card {
            GoodsInItemRow(
                code: code,
                subtitle: item.sko?.name ?? "",
                chip: item.isHandled ? Chip("Handled", .success) : nil,
                done: GoodsInText.number(item.returned + item.damaged + item.notReturned),
                required: GoodsInText.quantity(item.expected, label: item.expectedLabel),
                quantityTone: item.isHandled ? .success : nil,
                imageURL: item.sko?.imageUrl,
                identifier: "goodsIn.return.item.\(item.id)"
            )
            VStack(alignment: .leading, spacing: 8) {
                Text(breakdown)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .accessibilityIdentifier("goodsIn.return.breakdown.\(item.id)")
                ForEach(item.puts, id: \.id) { put in
                    RowDivider()
                    HStack {
                        Text(putText(put))
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text2)
                            .frame(maxWidth: .infinity, alignment: .leading)
                        if canUndo {
                            Button("Undo") { onUndo(put) }
                                .font(.aiku(14, .semibold))
                                .foregroundStyle(AikuColor.cautionInk)
                                .frame(minWidth: 44, minHeight: 44)
                                .accessibilityIdentifier("goodsIn.return.undo.\(put.id)")
                        }
                    }
                    .frame(minHeight: 44)
                }
                if canPutBack {
                    AikuButton("Back to stock", look: GoodsInActionKey.look(for: GoodsInActionKey.putBack).inFooter(isFirst: false), size: .regular) {
                        onAction(.backToStock)
                    }
                    .accessibilityIdentifier("goodsIn.return.backToStock.\(item.id)")
                    HStack(spacing: 8) {
                        AikuButton("Damaged", icon: .warning, kind: .danger, size: .regular) {
                            onAction(.damaged)
                        }
                        .accessibilityIdentifier("goodsIn.return.damaged.\(item.id)")
                        AikuButton("Not returned", icon: .xCircle, kind: .danger, size: .regular) {
                            onAction(.notReturned)
                        }
                        .accessibilityIdentifier("goodsIn.return.notReturned.\(item.id)")
                    }
                }
            }
            .padding(.horizontal, 14)
            .padding(.bottom, 12)
        }
    }

    private var breakdown: String {
        [
            L10n.format("Returned %@", GoodsInText.quantity(item.returned, label: item.returnedLabel)),
            L10n.format("Damaged %@", GoodsInText.quantity(item.damaged, label: item.damagedLabel)),
            L10n.format("Not returned %@", GoodsInText.quantity(item.notReturned, label: item.notReturnedLabel)),
            L10n.format("Remaining %@", GoodsInText.quantity(item.remaining, label: item.remainingLabel)),
        ].joined(separator: GoodsInText.separator)
    }

    private func putText(_ put: MobileReturnPut) -> String {
        [
            GoodsInText.quantity(put.quantity, label: put.quantityLabel),
            put.typeLabel.isEmpty ? put.type : put.typeLabel,
            put.location?.code ?? "",
            put.by?.name ?? "",
        ].filter { !$0.isEmpty }.joined(separator: GoodsInText.separator)
    }
}

struct GoodsInReturnItemSheet: View {
    let item: MobileReturnItem
    let action: GoodsInReturnItemAction
    let isWorking: Bool
    let problem: String?
    let onConfirm: (Double, Int?) -> Void
    let onDismiss: () -> Void
    @State private var quantity: Double
    @State private var linkID: Int?

    init(
        item: MobileReturnItem,
        action: GoodsInReturnItemAction,
        isWorking: Bool,
        problem: String?,
        onConfirm: @escaping (Double, Int?) -> Void,
        onDismiss: @escaping () -> Void
    ) {
        self.item = item
        self.action = action
        self.isWorking = isWorking
        self.problem = problem
        self.onConfirm = onConfirm
        self.onDismiss = onDismiss
        _quantity = State(initialValue: item.remaining)
        _linkID = State(initialValue: item.locations.first?.locationOrgStockId)
    }

    private var remaining: Double { item.remaining }
    private var shownQuantity: Double { min(max(quantity, 0), max(remaining, 0)) }
    private var location: MobileGoodsInItemLocation? { item.locations.first { $0.locationOrgStockId == linkID } }
    private var hasNoLocation: Bool { action == .backToStock && item.locations.isEmpty }

    private var title: String {
        switch action {
        case .backToStock: L10n.format("Back to stock · %@", item.code)
        case .damaged: L10n.format("Damaged · %@", item.code)
        case .notReturned: L10n.format("Not returned · %@", item.code)
        }
    }

    private var confirmTitle: String {
        let amount = GoodsInText.number(shownQuantity)
        switch action {
        case .backToStock: return L10n.format("Put %@ back in %@", amount, location?.location.code ?? "")
        case .damaged: return L10n.format("Set %@ as damaged", amount)
        case .notReturned: return L10n.format("Set %@ as not returned", amount)
        }
    }

    var body: some View {
        SheetScaffold(title: title, subtitle: L10n.format("%@ · %@ remaining", item.sko?.name ?? "", GoodsInText.quantity(remaining, label: item.remainingLabel))) {
            switch action {
            case .backToStock:
                SheetSectionLabel("Location")
                if hasNoLocation {
                    InlineAlert(tone: .warn, title: "This SKO has no location in this warehouse yet.")
                }
                ForEach(item.locations, id: \.locationOrgStockId) { option in
                    GoodsInChoiceRow(
                        title: option.location.code,
                        subtitle: [
                            option.type == "picking" ? L10n.text("Picking location") : option.type == "storing" ? L10n.text("Storing") : nil,
                            L10n.format("%@ in location", GoodsInText.quantity(option.quantity, label: option.quantityLabel)),
                        ].compactMap { $0 }.joined(separator: GoodsInText.separator),
                        isSelected: option.locationOrgStockId == linkID,
                        identifier: "goodsIn.return.location.\(option.location.code)"
                    ) {
                        linkID = option.locationOrgStockId
                    }
                }
            case .damaged:
                Text("Damaged goods do not go back to stock.")
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.dangerInk)
            case .notReturned:
                GoodsInHelper("Not returned means it never came back. Nothing goes back to stock.")
            }
            if remaining > 0 {
                SheetSectionLabel("Quantity")
                GoodsInQuantityStepper(value: shownQuantity, minimum: min(1, remaining), maximum: remaining) { quantity = $0 }
                GoodsInHelper(L10n.format("up to %@", GoodsInText.quantity(remaining, label: item.remainingLabel)))
            }
        } footer: {
            GoodsInSheetProblem(problem: problem)
            if !hasNoLocation, shownQuantity > 0 {
                AikuButton(
                    confirmTitle,
                    icon: action == .backToStock ? .next : action == .damaged ? .warning : .xCircle,
                    kind: action == .backToStock ? .primary : .dangerStrong,
                    isLoading: isWorking
                ) {
                    onConfirm(shownQuantity, linkID)
                }
                .accessibilityIdentifier("goodsIn.return.confirm")
            }
            AikuButton("Cancel", kind: .tertiary, action: onDismiss)
        }
    }
}
