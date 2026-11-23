import SwiftUI

struct SystemConflictResolveView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.signOut) private var signOut
    private let store = SystemSyncStore.shared
    private let offline = OfflineWork.shared
    let reference: String
    @State private var isConfirmingRollback = false

    private var operationID: Int? { Int(reference) }

    var body: some View {
        Group {
            if let operationID, let entry = store.operation(operationID) {
                content(entry, id: operationID)
            } else {
                AppScreen(title: "Resolve conflict", kicker: "Offline & sync") {
                    if store.isLoading || !store.hasLoaded {
                        Card {
                            SkeletonRow()
                        }
                    } else {
                        EmptyState(icon: .checkDouble, title: "Nothing to resolve", message: "This action no longer needs you.", actionTitle: "Back") {
                            router.back()
                        }
                    }
                }
            }
        }
        .task {
            if let operationID, store.operation(operationID) == nil {
                await store.load()
            }
        }
    }

    private func content(_ entry: OfflineOperationEntry, id: Int) -> some View {
        AppScreen(title: entry.labels.title.isEmpty ? entry.operationLabel : entry.labels.title, kicker: entry.operationLabel) {
            if let instruction = OfflineTexts.instruction(entry), entry.isOpen {
                InlineAlert(tone: .warn, icon: .warning, title: instruction, message: OfflineTexts.reason(entry.reason))
            } else if let state = OfflineTexts.state(entry) {
                InlineAlert(tone: state.tone == .danger ? .danger : state.tone, title: state.text, message: OfflineTexts.reason(entry.reason))
            }
            Card {
                details(entry)
            }
            if !entry.countedRows.isEmpty {
                SectionHeading("Counted")
                Card {
                    ForEach(Array(entry.countedRows.enumerated()), id: \.offset) { index, row in
                        if index > 0 {
                            RowDivider()
                        }
                        ShellValueRow(
                            title: row.locationCode ?? "—",
                            value: "\(OfflineTexts.quantity(row.counted)) counted · Aiku \(OfflineTexts.quantity(row.quantity))"
                        )
                    }
                }
            }
            if let results = store.ranAgain[id], !results.isEmpty {
                SectionHeading("Ran again after this")
                CardList(results) { result in
                    ListRow(
                        result.labels.title.isEmpty ? result.operationLabel : result.labels.title,
                        subtitle: OfflineTexts.reason(result.reason),
                        icon: result.offlineOperation?.icon ?? .sync,
                        iconTone: .neutral,
                        chips: [OfflineTexts.state(result)].compactMap { $0 }
                    )
                }
            }
            if let record = entry.record?.key {
                let reference = recordReference(entry, record: record)
                AikuButton("Open \(record.type.title(reference))", kind: .tertiary) {
                    open(record, reference: reference)
                }
            }
        } footer: {
            if entry.isOpen, !entry.allowedResolutions.isEmpty {
                VStack(spacing: 8) {
                    ForEach(entry.allowedResolutions, id: \.self) { resolution in
                        AikuButton(
                            resolution.label,
                            icon: resolution == entry.allowedResolutions.first ? .check : nil,
                            kind: resolution == entry.allowedResolutions.first ? .primary : .tertiary,
                            isLoading: store.workingOn == id
                        ) {
                            Task { await resolve(id, with: resolution) }
                        }
                        .accessibilityIdentifier("system.resolve.\(resolution.rawValue)")
                    }
                }
            } else if entry.canRollback {
                AikuButton("Roll back", icon: .undo, kind: .danger, isLoading: store.workingOn == id) {
                    isConfirmingRollback = true
                }
                .accessibilityIdentifier("system.rollback")
            }
        }
        .aikuConfirm(
            "Roll back this action?",
            isPresented: $isConfirmingRollback,
            message: "Aiku undoes what this offline action recorded, the same way as undoing it online.",
            confirmTitle: "Roll back",
            isDestructive: true
        ) {
            Task { await rollback(id) }
        }
    }

    @ViewBuilder
    private func details(_ entry: OfflineOperationEntry) -> some View {
        if let subtitle = entry.labels.subtitle {
            ShellValueRow(title: "Line", value: subtitle)
            RowDivider()
        }
        if let requested = entry.requestedQuantity {
            ShellValueRow(title: "You did", value: OfflineTexts.quantity(requested))
            RowDivider()
            ShellValueRow(title: "Aiku recorded", value: OfflineTexts.quantity(entry.appliedQuantity ?? 0))
        }
        if entry.countedSince {
            RowDivider()
            ShellValueRow(title: "Counted since", value: "Recorded without a stock movement")
        }
        if let name = entry.conflictingUser?.name {
            RowDivider()
            ShellValueRow(title: "Changed by", value: name)
        }
        if let task = entry.taskReference {
            RowDivider()
            ShellValueRow(title: "Stock check", value: task)
        }
        if let blockedBy = entry.blockedBySequence {
            RowDivider()
            ShellValueRow(title: "Waits for", value: "Your earlier action \(blockedBy)")
        }
    }

    private func resolve(_ id: Int, with resolution: OfflineResolution) async {
        guard let change = await store.resolve(id, with: resolution) else { return }
        handle(change)
        if case .resolved = change {
            offline.syncNow(.manual)
        }
    }

    private func rollback(_ id: Int) async {
        guard let change = await store.rollback(id, reason: nil) else { return }
        handle(change)
    }

    private func handle(_ change: SystemSyncChange) {
        switch change {
        case .resolved:
            toasts.show("Marked as resolved")
        case .rolledBack:
            toasts.show("Rolled back", tone: .neutral)
        case .refused(let message):
            toasts.show(message, tone: .danger)
        case .sessionExpired:
            signOut()
        }
    }

    private func recordReference(_ entry: OfflineOperationEntry, record: OfflineRecordKey) -> String {
        let title = entry.labels.title.components(separatedBy: " · ").first?.trimmingCharacters(in: .whitespaces) ?? ""
        if !title.isEmpty { return title }
        return OfflineWork.shared.downloaded(record).flatMap { SystemSyncStore.reference(in: $0.body) } ?? "#\(record.id)"
    }

    private func open(_ record: OfflineRecordKey, reference: String) {
        switch record.type {
        case .deliveryNote:
            router.open(GoodsOutRoute.deliveryNoteID(id: record.id))
        case .stockDelivery:
            router.open(GoodsInRoute.stockDelivery(id: record.id, reference: reference))
        case .customerReturn:
            router.open(GoodsInRoute.returnDetail(id: record.id, reference: reference))
        case .orgStock:
            router.open(InventoryRoute.skoID(id: record.id))
        case .location:
            if reference.hasPrefix("#") {
                router.open(SystemRoute.syncQueue)
            } else {
                router.open(LocationsRoute.location(code: reference))
            }
        }
    }
}
