import SwiftUI

enum GoodsInDeliverySheet: Identifiable, Hashable {
    case check(itemID: Int)
    case chooseLine(code: String, itemIDs: [Int])
    case checklistResult(key: String)
    case more

    var id: String {
        switch self {
        case .check(let itemID): "check-\(itemID)"
        case .chooseLine(let code, _): "choose-\(code)"
        case .checklistResult(let key): "checklist-\(key)"
        case .more: "more"
        }
    }
}

struct GoodsInDeliveryScreen: View {
    let reference: String
    let feed: GoodsInDeliveryFeed

    var body: some View {
        GoodsInAccessGate(isAllowed: \.viewGoodsIn) {
            if let delivery = feed.delivery {
                GoodsInDeliveryContent(feed: feed, delivery: delivery)
            } else {
                GoodsInLoadingScreen(
                    title: reference,
                    kicker: "Stock delivery",
                    failedTitle: "\(reference) could not be loaded",
                    problem: feed.loadError,
                    retry: { Task { await feed.load() } }
                )
                .screenRefreshable { await feed.load() }
            }
        }
        .onAppear {
            Task { await feed.load() }
        }
    }
}

private struct GoodsInDeliveryContent: View {
    let feed: GoodsInDeliveryFeed
    let delivery: MobileStockDelivery
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var sheet: GoodsInDeliverySheet?
    @State private var sheetProblem: String?
    @State private var isConfirmingCheckAll = false
    @State private var confirmingUnreceive: MobileGoodsInAction?

    private var view: GoodsInDeliveryView? { feed.view }
    private var unreceive: MobileGoodsInAction? { delivery.availableActions.action(GoodsInActionKey.unreceive) }

    var body: some View {
        screen
            .screenRefreshable { await feed.load() }
            .hardwareScanner(isEnabled: delivery.scansLines(in: view) && sheet == nil) { code in
                Task { await feed.scanLine(code) }
            }
            .goodsInEvents(
                feed.event,
                onHandled: { feed.event = nil },
                onSheetProblem: sheet == nil ? nil : { sheetProblem = $0 },
                doneText: doneText,
                onDone: closeSheet
            )
            .onChange(of: feed.scanOutcome) { _, outcome in
                handle(outcome)
            }
            .aikuSheet(item: $sheet) { current in
                sheetContent(current)
            }
            .aikuConfirm(
                "Check all items?",
                isPresented: $isConfirmingCheckAll,
                message: "Every item’s checked quantity is set to its expected quantity.",
                confirmTitle: "Check all items"
            ) {
                Task { await feed.checkAll() }
            }
            .aikuConfirm(
                "Unmark \(delivery.reference) as received?",
                isPresented: Binding(get: { confirmingUnreceive != nil }, set: { if !$0 { confirmingUnreceive = nil } }),
                message: "It goes back to dispatched. Only goods-in supervisors can do this.",
                confirmTitle: confirmingUnreceive.map { $0.label.isEmpty ? "Unmark as received" : $0.label } ?? "Unmark as received",
                isDestructive: true
            ) {
                Task { await feed.unreceive() }
            }
    }

    private var kicker: String {
        let supplier = delivery.supplier?.label ?? ""
        return "Stock delivery · \(supplier.isEmpty ? delivery.stateLabel : supplier)"
    }

    private var headerActions: [HeaderAction] {
        guard unreceive != nil else { return [] }
        return [HeaderAction(.more, label: "More actions") { open(.more) }]
    }

    @ViewBuilder
    private var screen: some View {
        if let footer = footerKind {
            AppScreen(title: delivery.reference, kicker: kicker, actions: headerActions) {
                content
            } footer: {
                footerView(footer)
            }
        } else {
            AppScreen(title: delivery.reference, kicker: kicker, actions: headerActions) {
                content
            }
        }
    }

    @ViewBuilder
    private var content: some View {
        if let problem = feed.loadError {
            GoodsOutLoadProblem(title: "The stock delivery could not be refreshed", message: problem) {
                Task { await feed.load() }
            }
        }
        GoodsInDeliveryStatus(delivery: delivery)
        GoodsInOrdererCard(delivery: delivery)
        GoodsInDeliveryInfoCard(delivery: delivery)
        GoodsInSummaryCard(delivery: delivery)
        GoodsInViewTabs(options: delivery.views.map { ($0, label(for: $0)) }, active: view) { feed.chosenView = $0 }
        if let code = feed.lookingUpCode {
            InlineAlert(tone: .info, title: "Looking up \(code)…")
        }
        switch view {
        case .items:
            GoodsInArrivingItems(delivery: delivery)
        case .check:
            GoodsInCheckItems(
                delivery: delivery,
                onScan: { router.open(GoodsInRoute.checkScan(deliveryID: delivery.id, reference: delivery.reference)) },
                onOpen: { open(.check(itemID: $0.id)) }
            )
        case .place:
            GoodsInPlaceItems(delivery: delivery, onOpen: openPlace)
        case .done:
            GoodsInDoneItems(delivery: delivery)
        case .checklist:
            if let checklist = delivery.checklist {
                GoodsInChecklistView(
                    delivery: delivery,
                    checklist: checklist,
                    onOpenCheck: delivery.canRecordChecks ? { open(.checklistResult(key: $0)) } : nil
                )
            }
        case nil:
            EmptyView()
        }
    }

    private func label(for view: GoodsInDeliveryView) -> String {
        switch view {
        case .items: "Items"
        case .check: "To check \(delivery.items.filter(\.needsCheck).count)"
        case .place: "To place \(delivery.items.filter(\.canBePlaced).count)"
        case .done: "Done"
        case .checklist: "Checklist"
        }
    }

    private enum FooterKind {
        case disabled(MobileGoodsInAction, fallback: String)
        case receive(MobileGoodsInAction)
        case checkAll(MobileGoodsInAction)
        case place(MobileGoodsInAction, next: MobileStockDeliveryItem)
    }

    private var footerKind: FooterKind? {
        switch view {
        case .items:
            guard let receive = delivery.availableActions.action(GoodsInActionKey.receive) else { return nil }
            return receive.isDisabled ? .disabled(receive, fallback: "Mark as received") : .receive(receive)
        case .check:
            guard let check = delivery.availableActions.action(GoodsInActionKey.check), delivery.items.contains(where: \.needsCheck) else { return nil }
            return check.isDisabled ? .disabled(check, fallback: "Check all items") : .checkAll(check)
        case .place:
            guard let place = delivery.availableActions.action(GoodsInActionKey.place), let next = delivery.nextToPlace else { return nil }
            return place.isDisabled ? .disabled(place, fallback: "Place") : .place(place, next: next)
        default:
            return nil
        }
    }

    @ViewBuilder
    private func footerView(_ kind: FooterKind) -> some View {
        switch kind {
        case let .disabled(action, fallback):
            GoodsInDisabledNotice(action: action, fallbackTitle: fallback)
        case .receive(let action):
            AikuButton(
                action.label.isEmpty ? "Mark as received" : action.label,
                look: GoodsInActionKey.look(for: action.key),
                isLoading: feed.working == .receive
            ) {
                Task { await feed.receive() }
            }
            .accessibilityIdentifier("goodsIn.markAsReceived")
        case .checkAll(let action):
            AikuButton("Check all items", look: GoodsInActionKey.look(for: action.key), isLoading: feed.working == .checkAll) {
                isConfirmingCheckAll = true
            }
            .accessibilityIdentifier("goodsIn.checkAll")
        case let .place(action, next):
            AikuButton("Place \(next.code)", look: GoodsInActionKey.look(for: action.key)) {
                openPlace(next)
            }
            .accessibilityIdentifier("goodsIn.placeNext")
        }
    }

    @ViewBuilder
    private func sheetContent(_ current: GoodsInDeliverySheet) -> some View {
        switch current {
        case .check(let itemID):
            if let item = delivery.item(itemID) {
                GoodsInCheckedQuantitySheet(
                    item: item,
                    isWorking: feed.isWorking,
                    problem: sheetProblem ?? feed.writeErrors.values.first,
                    onSave: { quantity in Task { await feed.check(itemID: item.id, skoQuantity: quantity) } },
                    onNotReceived: { Task { await feed.notReceived(itemID: item.id) } },
                    onDismiss: closeSheet
                )
            }
        case let .chooseLine(code, itemIDs):
            GoodsInChooseLineSheet(code: code, items: itemIDs.compactMap(delivery.item)) { item in
                sheet = nil
                Task { @MainActor in
                    try? await Task.sleep(for: .milliseconds(350))
                    openLine(item.id)
                }
            }
        case .checklistResult(let key):
            if let checklist = delivery.checklist, let check = checklist.check(key) {
                GoodsInCheckResultSheet(
                    check: check,
                    checklist: checklist,
                    isWorking: feed.isWorking,
                    problem: sheetProblem,
                    errors: feed.writeErrors,
                    onSave: { result, note, incidentType, quantity in
                        Task { await feed.recordCheck(key: check.key, result: result, body: note, incidentType: incidentType, incidentQuantity: quantity) }
                    },
                    onDismiss: closeSheet
                )
            }
        case .more:
            if let unreceive {
                SheetScaffold(title: "\(delivery.reference) · more") {
                    Card {
                        GoodsInActionLinkRow(
                            icon: .undo,
                            title: unreceive.label.isEmpty ? "Unmark as received" : unreceive.label,
                            subtitle: unreceive.disabledReason ?? "It goes back to dispatched. Only goods-in supervisors can do this.",
                            tone: .caution,
                            identifier: "goodsIn.unreceive",
                            action: unreceive.isDisabled ? nil : {
                                sheet = nil
                                Task { @MainActor in
                                    try? await Task.sleep(for: .milliseconds(350))
                                    confirmingUnreceive = unreceive
                                }
                            }
                        )
                    }
                }
            }
        }
    }

    private func open(_ next: GoodsInDeliverySheet) {
        sheetProblem = nil
        sheet = next
    }

    private func closeSheet() {
        sheet = nil
        sheetProblem = nil
        feed.clearWriteErrors()
    }

    private func openPlace(_ item: MobileStockDeliveryItem) {
        router.open(GoodsInRoute.placeItem(deliveryID: delivery.id, itemID: item.id, reference: delivery.reference, skoCode: item.code))
    }

    private func openLine(_ itemID: Int) {
        guard let item = delivery.item(itemID) else { return }
        if view == .place {
            if item.canBePlaced, delivery.canPlace { openPlace(item) }
        } else if delivery.canCheck {
            open(.check(itemID: itemID))
        }
    }

    private func handle(_ outcome: GoodsInLineScanOutcome?) {
        guard let outcome else { return }
        switch outcome {
        case .line(let itemID):
            openLine(itemID)
        case let .choose(code, itemIDs):
            open(.chooseLine(code: code, itemIDs: itemIDs))
        case .notOnDelivery(let code):
            toasts.show("\(code) is not on \(delivery.reference)", tone: .info)
        case .failed(_, let message):
            toasts.show(message, tone: .danger)
        }
        feed.scanOutcome = nil
    }

    private func doneText() -> String? {
        guard let write = feed.lastWrite else { return nil }
        let current = feed.delivery ?? delivery
        switch write {
        case .receive:
            return "\(current.reference) marked as received"
        case .unreceive:
            return "\(current.reference) is back to dispatched"
        case .checkAll:
            return "Every item of \(current.reference) checked"
        case let .check(itemID, sent):
            let item = current.item(itemID)
            let code = item?.code ?? ""
            if item?.wasRaised(above: sent) == true {
                return "\(code) raised to \(GoodsInText.quantity(item?.skoChecked ?? 0, label: item?.skoCheckedLabel)): that much is already placed"
            }
            return sent == 0 ? "\(code) set as not received" : "\(code) · \(GoodsInText.number(sent)) checked"
        case .recordCheck(_, let result, _, _, _):
            return result == .passed ? "Check passed" : "Check recorded as failed"
        case .place, .placeAll:
            return nil
        }
    }
}

private struct GoodsInDeliveryStatus: View {
    let delivery: MobileStockDelivery

    var body: some View {
        if let stage = delivery.stage {
            GoodsInStageStepper(stage: stage, isComplete: delivery.state == GoodsInDeliveryStateKey.placed)
        } else if delivery.state == GoodsInDeliveryStateKey.notReceived {
            InlineAlert(tone: .danger, title: delivery.stateLabel.isEmpty ? "Not received" : delivery.stateLabel, message: "Nothing on this stock delivery arrived.")
        } else if delivery.state == GoodsInDeliveryStateKey.cancelled {
            InlineAlert(tone: .danger, title: delivery.stateLabel.isEmpty ? "Cancelled" : delivery.stateLabel, message: "This stock delivery was cancelled.")
        }
    }
}

private struct GoodsInOrdererCard: View {
    let delivery: MobileStockDelivery

    var body: some View {
        if !rows.isEmpty {
            GoodsOutDetailCard(title: "Orderer", rows: rows)
        }
    }

    private var rows: [GoodsOutDetailRow] {
        var rows: [GoodsOutDetailRow] = []
        if let supplier = delivery.supplier {
            let text = [supplier.name, supplier.code].compactMap { $0?.trimmingCharacters(in: .whitespaces) }.filter { !$0.isEmpty }.joined(separator: GoodsInText.separator)
            if !text.isEmpty { rows.append(GoodsOutDetailRow(icon: .person, text: text)) }
        }
        if let agent = delivery.agentReference?.trimmingCharacters(in: .whitespaces), !agent.isEmpty {
            rows.append(GoodsOutDetailRow(icon: .document, text: "Agent reference \(agent)"))
        }
        let orders = delivery.purchaseOrders.map(\.reference).filter { !$0.isEmpty }
        if !orders.isEmpty {
            rows.append(GoodsOutDetailRow(icon: .document, text: "Purchase orders \(orders.joined(separator: ", "))"))
        }
        if let dispatched = GoodsInText.date(delivery.dispatchedAt) {
            rows.append(GoodsOutDetailRow(icon: .shipment, text: "Dispatched \(dispatched)"))
        }
        if let expected = GoodsInText.date(delivery.estimatedReceivingDate) {
            rows.append(GoodsOutDetailRow(icon: .history, text: "Estimated receiving \(expected)"))
        }
        if let received = GoodsInText.date(delivery.receivedAt) {
            rows.append(GoodsOutDetailRow(icon: .check, text: "Received \(received)"))
        }
        return rows
    }
}

private struct GoodsInDeliveryInfoCard: View {
    let delivery: MobileStockDelivery

    var body: some View {
        if let info = delivery.delivery {
            let fields = [
                info.typeText.map { GoodsInField("Type", $0) },
                info.containerSize?.value.map { GoodsInField("Container size", $0) },
                info.incoterm?.value.map { GoodsInField("Incoterm", $0) },
                info.portOfExport?.value.map { GoodsInField("Port of export", $0) },
                info.portOfImport?.value.map { GoodsInField("Port of import", $0) },
                info.containerNumber?.value.map { GoodsInField("Container number", $0) },
                info.shipperReference?.value.map { GoodsInField("Shipper reference", $0) },
                info.trackingNumber?.value.map { GoodsInField("Tracking number", $0) },
                info.shippingAgent?.value.map { GoodsInField("Shipping agent", $0) },
            ].compactMap { $0 }
            if !fields.isEmpty {
                GoodsInFieldGrid(title: "Delivery", fields: fields)
            }
        }
    }
}

private struct GoodsInSummaryCard: View {
    let delivery: MobileStockDelivery

    var body: some View {
        let summary = delivery.summary
        let isInGoodsIn = !delivery.isBeforeReceiving
        var fields = [GoodsInField("Items", summary.numberItems.grouped)]
        if isInGoodsIn {
            fields.append(GoodsInField("Received / checked", "\(summary.numberChecked.grouped) of \(summary.numberItems.grouped)"))
            fields.append(GoodsInField("Placed", summary.numberPlaced.grouped))
        }
        fields.append(GoodsInField("New SKOs", summary.numberNewOrgStocks.grouped))
        if isInGoodsIn {
            if summary.numberNotReceived > 0 {
                fields.append(GoodsInField("Not received", summary.numberNotReceived.grouped))
            }
            fields.append(GoodsInField("Under delivered", summary.underDelivered.grouped))
            fields.append(GoodsInField("Over delivered", summary.overDelivered.grouped))
        }
        return GoodsInFieldGrid(title: "Items", fields: fields)
    }
}

private struct GoodsInArrivingItems: View {
    let delivery: MobileStockDelivery

    var body: some View {
        GoodsOutSectionTitle(title: "Items", detail: "\(delivery.items.count) \(GoodsInText.plural(delivery.items.count, "item", "items"))")
        GoodsInCardList(items: delivery.items) { item in
            GoodsInItemRow(
                code: item.code,
                subtitle: [item.name, item.purchaseOrderReference ?? ""].filter { !$0.isEmpty }.joined(separator: GoodsInText.separator),
                chip: item.isNewOrgStock ? Chip("New SKO", .info) : nil,
                done: GoodsInText.quantity(item.skoExpected, label: item.skoExpectedLabel),
                imageURL: item.sko?.imageUrl
            )
        }
        GoodsInHelper("Mark it as received when it arrives at goods in. Checking starts after that.")
    }
}

private struct GoodsInCheckItems: View {
    let delivery: MobileStockDelivery
    let onScan: () -> Void
    let onOpen: (MobileStockDeliveryItem) -> Void

    var body: some View {
        let items = delivery.items(for: .check)
        let checkedCount = items.filter { $0.skoChecked != nil }.count
        if delivery.canCheck, items.contains(where: \.needsCheck) {
            GoodsInScanPrompt(title: "Scan to check", message: "Scan an SKO barcode to open its line", identifier: "goodsIn.scanToCheck", action: onScan)
        }
        GoodsOutSectionTitle(title: "To check", detail: "\(checkedCount) of \(items.count) checked")
        if items.isEmpty {
            GoodsInHelper("Nothing left to check.")
        }
        GoodsInCardList(items: items) { item in
            let look = item.checkLook
            GoodsInItemRow(
                code: item.code,
                subtitle: look.subtitle,
                chip: look.chip,
                done: GoodsInText.quantity(item.skoChecked ?? 0, label: item.skoCheckedLabel),
                required: GoodsInText.quantity(item.skoExpected, label: item.skoExpectedLabel),
                quantityTone: look.chip?.tone,
                imageURL: item.sko?.imageUrl,
                action: delivery.canCheck ? { onOpen(item) } : nil
            )
        }
        GoodsInHelper("Tap an item to set its checked quantity in SKOs. Checking an item as 0 marks it Not received. Checked items can be placed straight away.")
    }
}

private struct GoodsInPlaceItems: View {
    let delivery: MobileStockDelivery
    let onOpen: (MobileStockDeliveryItem) -> Void

    var body: some View {
        let items = delivery.items(for: .place)
        let placedCount = items.filter { !$0.canBePlaced }.count
        GoodsOutSectionTitle(title: "Place checked items", detail: "\(placedCount) of \(items.count) placed")
        if items.isEmpty {
            GoodsInHelper("Nothing has been checked yet, so there is nothing to place.")
        }
        GoodsInCardList(items: items) { item in
            GoodsInItemRow(
                code: item.code,
                subtitle: subtitle(item),
                chip: chip(item),
                done: GoodsInText.quantity(item.skoPlaced, label: item.skoPlacedLabel),
                required: GoodsInText.quantity(item.skoChecked ?? 0, label: item.skoCheckedLabel),
                quantityTone: item.canBePlaced ? nil : .success,
                imageURL: item.sko?.imageUrl,
                action: delivery.canPlace && item.canBePlaced ? { onOpen(item) } : nil
            )
        }
        GoodsInHelper("Place each checked item. You can only place what was checked. When every item is placed the delivery becomes Booked in by itself.")
    }

    private func chip(_ item: MobileStockDeliveryItem) -> Chip? {
        if !item.canBePlaced { return Chip("Placed", .success) }
        if !item.hasPickingLocation { return Chip("No picking location yet", .warn) }
        return nil
    }

    private func subtitle(_ item: MobileStockDeliveryItem) -> String {
        let placedIn = Array(NSOrderedSet(array: item.placements.compactMap { $0.location?.code }.filter { !$0.isEmpty })).compactMap { $0 as? String }.joined(separator: ", ")
        if !item.canBePlaced, !placedIn.isEmpty { return "\(item.name) · in \(placedIn)" }
        if let picking = item.pickingLocationCode { return "\(item.name) · picking location \(picking)" }
        return item.name
    }
}

private struct GoodsInDoneItems: View {
    let delivery: MobileStockDelivery

    var body: some View {
        let items = delivery.items(for: .done)
        if items.isEmpty {
            GoodsInHelper("Nothing is done yet.")
        }
        GoodsInCardList(items: items) { item in
            let placements = item.placements.map { "\(GoodsInText.quantity($0.quantity, label: $0.quantityLabel)) in \($0.location?.code ?? "")" }.joined(separator: GoodsInText.separator)
            GoodsInItemRow(
                code: item.code,
                subtitle: placements.isEmpty ? item.name : placements,
                chip: item.stateLabel.isEmpty ? nil : Chip(item.stateLabel, item.state == GoodsInDeliveryStateKey.cancelled ? .danger : .success),
                done: GoodsInText.quantity(item.skoPlaced, label: item.skoPlacedLabel),
                required: GoodsInText.quantity(item.skoChecked ?? item.skoExpected, label: item.skoCheckedLabel ?? item.skoExpectedLabel),
                quantityTone: .success,
                imageURL: item.sko?.imageUrl
            )
        }
        if delivery.state == GoodsInDeliveryStateKey.bookedIn || delivery.state == GoodsInDeliveryStateKey.placed {
            GoodsInHelper("All items placed, so the delivery is Booked in by itself.")
        }
    }
}

extension MobileStockDeliveryItem: Identifiable {}

struct GoodsInChooseLineSheet: View {
    let code: String
    let items: [MobileStockDeliveryItem]
    let onChoose: (MobileStockDeliveryItem) -> Void

    var body: some View {
        SheetScaffold(title: "Choose the line", subtitle: "\(code) is on \(items.count) \(GoodsInText.plural(items.count, "line", "lines"))") {
            ForEach(items) { item in
                GoodsInChoiceRow(
                    title: item.code,
                    subtitle: [
                        item.purchaseOrderReference.flatMap { $0.isEmpty ? nil : $0 },
                        item.expectedLine,
                        item.skoChecked.map { "\(GoodsInText.quantity($0, label: item.skoCheckedLabel)) checked" },
                    ].compactMap { $0 }.joined(separator: GoodsInText.separator),
                    isSelected: false,
                    identifier: "goodsIn.chooseLine.\(item.id)"
                ) {
                    onChoose(item)
                }
            }
        }
    }
}

struct GoodsInCheckedQuantitySheet: View {
    let item: MobileStockDeliveryItem
    let isWorking: Bool
    let problem: String?
    let onSave: (Double) -> Void
    let onNotReceived: () -> Void
    let onDismiss: () -> Void
    @State private var checked: Double

    init(
        item: MobileStockDeliveryItem,
        isWorking: Bool,
        problem: String?,
        onSave: @escaping (Double) -> Void,
        onNotReceived: @escaping () -> Void,
        onDismiss: @escaping () -> Void
    ) {
        self.item = item
        self.isWorking = isWorking
        self.problem = problem
        self.onSave = onSave
        self.onNotReceived = onNotReceived
        self.onDismiss = onDismiss
        _checked = State(initialValue: item.skoChecked ?? item.skoExpected)
    }

    var body: some View {
        let difference = GoodsInDifference.of(checked: checked, expected: item.skoExpected)
        SheetScaffold(title: "\(item.code) · checked quantity", subtitle: item.expectedLine) {
            SheetSectionLabel("Checked (SKOs)")
            GoodsInQuantityStepper(value: checked, minimum: 0) { checked = $0 }
            Card {
                ListRow("Difference", subtitle: difference.description) {
                    StatusChip(difference.chip)
                }
            }
            .accessibilityIdentifier("goodsIn.check.difference")
            GoodsInHelper("Count in SKOs (outers). Purchasing resolves differences: recount, unit error, supplier claim or surplus accepted.")
        } footer: {
            GoodsInSheetProblem(problem: problem)
            AikuButton("Save \(GoodsInText.number(checked)) checked", icon: .check, isLoading: isWorking) {
                onSave(checked)
            }
            .accessibilityIdentifier("goodsIn.check.save")
            AikuButton("Check all \(GoodsInText.quantity(item.skoExpected, label: item.skoExpectedLabel))", icon: .checkDouble, kind: .secondary) {
                onSave(item.skoExpected)
            }
            .disabled(isWorking)
            .accessibilityIdentifier("goodsIn.check.all")
            AikuButton("Check as 0 · not received", kind: .danger) {
                onNotReceived()
            }
            .disabled(isWorking)
            .accessibilityIdentifier("goodsIn.check.zero")
        }
    }
}
