import SwiftUI

struct FulfilmentAuditView: View {
    let auditID: Int
    let reference: String
    @Environment(\.apiClient) private var apiClient
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouseID = warehouseStore.selected?.id {
            StoredItemAuditScreen(live: StoredItemAuditLive(warehouseID: warehouseID, auditID: auditID, apiClient: apiClient), reference: reference)
                .id("\(warehouseID)-\(auditID)")
        } else {
            AppScreen(title: reference, kicker: "Stored item audit") {
                EmptyState(icon: .pallet, title: "Choose a warehouse first")
            }
        }
    }
}

private struct CountTarget: Identifiable, Equatable {
    let storedItemID: Int
    let reference: String
    let name: String?
    let expected: Int
    let counted: Int?
    let deltaID: Int?
    var id: Int { storedItemID }
}

private struct AuditLineItem: Identifiable {
    let line: MobileStoredItemAudit.Line
    var id: Int { line.storedItem.id }
}

private struct StoredItemAuditScreen: View {
    @State var live: StoredItemAuditLive
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var counting: CountTarget?
    @State private var isAddingItem = false
    @State private var isConfirmingComplete = false
    @State private var isScanning = false

    var body: some View {
        Group {
            if let audit = live.audit {
                loaded(audit)
            } else {
                AppScreen(title: reference, kicker: "Stored item audit") {
                    if let message = live.loadError {
                        InlineAlert(title: "Couldn’t load the audit of \(reference)", message: message)
                        AikuButton("Try again", kind: .secondary) {
                            Task { await live.load() }
                        }
                    } else {
                        SkeletonRow()
                        SkeletonRow()
                    }
                }
            }
        }
        .task { await live.load() }
        .screenRefreshable { await live.load() }
        .onChange(of: live.done) { _, done in
            guard let done else { return }
            toasts.show(done.message, tone: done.tone)
            live.clearDone()
        }
        .onChange(of: live.isCompleted) { _, completed in
            if completed { router.back() }
        }
    }

    @ViewBuilder
    private func loaded(_ audit: MobileStoredItemAudit) -> some View {
        let palletReference = audit.pallet?.reference ?? reference
        let canCount = audit.can("count")
        let canAdd = audit.can("add_item")
        let complete = audit.action("complete")
        let countedText = "\(audit.counts.counted.grouped) of \(audit.counts.lines.grouped) counted"
        AppScreen(
            title: (audit.reference?.isEmpty == false ? audit.reference : nil) ?? palletReference,
            kicker: PalletDeliveryText.joined(["Stored item audit", "Pallet \(palletReference)", audit.stateLabel])
        ) {
            if let message = live.loadError {
                GoodsOutLoadProblem(title: "Couldn’t refresh this screen", message: message) {
                    Task { await live.load() }
                }
            }
            if let code = live.unsure?.scannedCode {
                InlineAlert(tone: .warn, title: PalletReturnText.unsureTitle, message: StoredItemAuditText.unsureMessage(code)) {
                    LinkButton("Try again", size: 13) {
                        Task { await live.retryUnsure() }
                    }
                    .frame(minHeight: 44, alignment: .leading)
                    .disabled(live.isWorking)
                    .accessibilityIdentifier("fulfilment.audit.retryUnsure")
                }
            }
            palletCard(audit)
            if canCount {
                FulfilmentScanCard(
                    title: "Scan to count",
                    detail: "Scan each item on pallet \(palletReference)",
                    highlight: live.lastScanned ?? "Nothing scanned yet",
                    footnote: live.queuedScans > 0 ? "\(live.queuedScans) scan\(live.queuedScans == 1 ? "" : "s") waiting" : (live.lastScanned == nil ? nil : "Last scanned"),
                    identifier: "fulfilment.audit.scan"
                ) {
                    isScanning = true
                }
            }
            SectionHeading("Stored items", detail: countedText)
            if audit.lines.isEmpty, !canAdd {
                HelperText("No stored items on this pallet.")
            } else {
                Card {
                    ForEach(audit.lines.map(AuditLineItem.init)) { item in
                        lineRow(item.line, canCount: canCount)
                        RowDivider()
                    }
                    if canAdd {
                        ListRow("Add an item not on the list", subtitle: "Search the customer’s items, or scan one", icon: .search, iconTone: .neutral) {
                            live.choiceSearch = ""
                            isAddingItem = true
                        }
                        .accessibilityIdentifier("fulfilment.audit.addItem")
                    }
                }
            }
            HelperText("Completing the audit applies the counted quantities to this pallet. Items not counted stay as they are.")
        } footer: {
            if let complete {
                AikuButton(complete.label.isEmpty ? "Complete audit" : complete.label, look: StoredItemAuditActionLooks.look(for: complete.key), isLoading: live.working == .complete) {
                    isConfirmingComplete = true
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.audit.complete")
            } else {
                VStack(alignment: .leading, spacing: 2) {
                    Text(audit.stateLabel ?? audit.state)
                        .font(.aiku(14, .semibold))
                        .foregroundStyle(AikuColor.text)
                    Text(countedText)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
        }
        .hardwareScanner(isEnabled: canCount && counting == nil && !isAddingItem && !isScanning) { code in
            Task { await live.scan(code) }
        }
        .scanMissFeedback(live.scanMisses)
        .fullScreenCover(isPresented: $isScanning) {
            FulfilmentCodeCamera(title: "Scan to count", onClose: { isScanning = false }) { code in
                isScanning = false
                Task { await live.scan(code) }
            }
        }
        .aikuSheet(item: $counting) { target in
            CountSheet(live: live, target: target) { counting = nil }
        }
        .aikuSheet(isPresented: $isAddingItem) {
            AddItemSheet(live: live) { choice in
                isAddingItem = false
                counting = CountTarget(storedItemID: choice.id, reference: choice.reference, name: choice.name, expected: 0, counted: nil, deltaID: nil)
            } onClose: {
                isAddingItem = false
            }
        }
        .aikuConfirm(
            "Complete \((audit.reference?.isEmpty == false ? audit.reference : nil) ?? palletReference)?",
            isPresented: $isConfirmingComplete,
            message: "The counted quantities are applied to pallet \(palletReference). Items not counted stay as they are.",
            confirmTitle: complete?.label ?? "Complete audit"
        ) {
            Task { await live.complete() }
        }
    }

    private func palletCard(_ audit: MobileStoredItemAudit) -> some View {
        var facts: [GoodsInField] = []
        if let pallet = audit.pallet {
            facts.append(GoodsInField("Pallet", PalletDeliveryText.joined([pallet.reference, pallet.customerReference])))
        }
        facts.append(GoodsInField("Location", audit.pallet?.location?.code ?? "no location"))
        if let customer = audit.customer?.name {
            facts.append(GoodsInField("Customer", customer))
        }
        return GoodsInFieldGrid(title: "Pallet", fields: facts)
    }

    private func lineRow(_ line: MobileStoredItemAudit.Line, canCount: Bool) -> some View {
        let tone = FulfilmentStorageText.auditTone(type: line.auditType, difference: line.difference)
        return ListRow(
            line.storedItem.reference ?? "#\(line.storedItem.id)",
            subtitle: PalletDeliveryText.joined([line.storedItem.name, line.isCounted ? "Expected \(line.quantity)" : "Expected \(line.quantity) · not counted yet"]),
            chips: FulfilmentStorageText.auditChip(line).map { [Chip($0, tone)] } ?? [],
            action: canCount ? {
                live.clearSheetError()
                counting = CountTarget(
                    storedItemID: line.storedItem.id,
                    reference: line.storedItem.reference ?? "#\(line.storedItem.id)",
                    name: line.storedItem.name,
                    expected: line.quantity,
                    counted: line.auditedQuantity,
                    deltaID: line.deltaId
                )
            } : nil
        ) {
            EmptyView()
        } trailing: {
            FulfilmentCountLabel(done: line.auditedQuantity ?? 0, total: line.quantity, tone: line.isCounted ? tone : .neutral)
        }
        .accessibilityIdentifier("fulfilment.audit.line.\(line.storedItem.reference ?? "\(line.storedItem.id)")")
    }
}

private struct CountSheet: View {
    let live: StoredItemAuditLive
    let target: CountTarget
    let onClose: () -> Void
    @State private var text: String
    @State private var isMissing = false

    init(live: StoredItemAuditLive, target: CountTarget, onClose: @escaping () -> Void) {
        self.live = live
        self.target = target
        self.onClose = onClose
        _text = State(initialValue: String(target.counted ?? target.expected))
    }

    var body: some View {
        SheetScaffold(title: target.reference, subtitle: target.name) {
            Text("Expected \(target.expected)")
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
            AikuTextField("Counted", text: $text, error: isMissing ? "Enter the counted quantity." : live.sheetError, keyboard: .numberPad)
                .accessibilityIdentifier("fulfilment.audit.countField")
        } footer: {
            AikuButton("Save count", look: StoredItemAuditActionLooks.look(for: "count"), isLoading: live.isSavingCount) {
                guard let quantity = Int(text.trimmingCharacters(in: .whitespacesAndNewlines)), quantity >= 0 else {
                    isMissing = true
                    return
                }
                Task {
                    if await live.count(storedItemID: target.storedItemID, quantity: quantity) { onClose() }
                }
            }
            .disabled(live.isWorking)
            .accessibilityIdentifier("fulfilment.audit.saveCount")
            if let deltaID = target.deltaID {
                AikuButton("Remove count", icon: .trash, kind: .danger) {
                    Task {
                        if await live.removeCount(deltaID: deltaID) { onClose() }
                    }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.audit.removeCount")
            }
            AikuButton("Cancel", kind: .tertiary, action: onClose)
        }
    }
}

private struct AddItemSheet: View {
    @Bindable var live: StoredItemAuditLive
    let onChoose: (MobileAuditChoice) -> Void
    let onClose: () -> Void

    var body: some View {
        SheetScaffold(title: "Add an item not on the list") {
            SearchField(text: $live.choiceSearch, placeholder: "Search reference, barcode or name")
            if let message = live.choicesError {
                InlineAlert(title: "Couldn’t load the customer’s items", message: message)
            } else if live.isLoadingChoices, live.choices.isEmpty {
                SkeletonRow()
                SkeletonRow()
            } else if live.choices.isEmpty {
                HelperText("No other item of this customer matches.")
            } else {
                CardList(live.choices) { choice in
                    ListRow(choice.reference, subtitle: PalletDeliveryText.joined([choice.name, choice.barcode]), action: { onChoose(choice) })
                        .accessibilityIdentifier("fulfilment.audit.choice.\(choice.reference)")
                }
            }
        } footer: {
            AikuButton("Cancel", kind: .tertiary, action: onClose)
        }
        .task(id: live.choiceSearch) {
            await live.searchChoices(live.choiceSearch)
        }
    }
}
