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
                InlineAlert(tone: .warn, icon: .warning, title: instruction, message: entry.message)
            } else if let message = entry.message {
                InlineAlert(tone: .info, title: message)
            }
            itemCard(entry)
            let differences = ConflictDetails.differences(entry)
            if differences.contains(where: \.hasQuantity) {
                SectionHeading("What’s different")
                differenceTable(differences, packedIn: entry.item?.packedIn, actionTitle: L10n.text(entry.operationLabel))
            }
            if let reason = OfflineTexts.reason(entry.reason) {
                Text(L10n.text(reason))
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
                    .padding(.horizontal, 4)
            }
            let notes = ConflictDetails.notes(entry)
            if !notes.isEmpty {
                Card {
                    ForEach(Array(notes.enumerated()), id: \.offset) { index, note in
                        if index > 0 {
                            RowDivider()
                        }
                        ShellValueRow(title: note.label, value: note.value)
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
                AikuButton(L10n.format("Open %@", record.type.title(reference)), kind: .tertiary) {
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

    private func itemCard(_ entry: OfflineOperationEntry) -> some View {
        let lineWord = L10n.text("Line")
        let summary = ConflictDetails.summary(entry, lineWord: lineWord)
        let facts = ConflictDetails.facts(entry, lineWord: lineWord)
        return Card {
            HStack(spacing: 14) {
                if let code = summary.code {
                    GoodsInPhoto(code: code, imageURL: summary.imageUrl, size: 64)
                }
                VStack(alignment: .leading, spacing: 4) {
                    Text(summary.code ?? summary.record ?? L10n.text(entry.operationLabel))
                        .font(.aiku(18, .bold))
                        .foregroundStyle(AikuColor.text)
                        .lineLimit(1)
                    if let name = summary.name {
                        Text(name)
                            .font(.aiku(14))
                            .foregroundStyle(AikuColor.text3)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    FlowLayout(spacing: 6, lineSpacing: 4) {
                        StatusChip(entry.operationLabel, tone: .info)
                        if let state = OfflineTexts.state(entry) {
                            StatusChip(state)
                        }
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            .padding(16)
            ForEach(Array(facts.enumerated()), id: \.offset) { _, fact in
                RowDivider()
                KeyValueRow(fact.label, fact.value)
            }
        }
    }

    private func differenceTable(_ rows: [ConflictDifference], packedIn: Double?, actionTitle: String) -> some View {
        Card {
            HStack(spacing: 8) {
                Spacer(minLength: 0)
                differenceHeader("Yours")
                differenceHeader("Now in Aiku")
                differenceHeader("Difference")
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .background(AikuColor.surface2)
            .accessibilityHidden(true)
            ForEach(Array(rows.enumerated()), id: \.offset) { _, row in
                RowDivider()
                differenceRow(row, packedIn: packedIn, actionTitle: actionTitle)
            }
        }
    }

    private func differenceHeader(_ title: String) -> some View {
        Text(L10n.text(title))
            .font(.aiku(12, .bold))
            .foregroundStyle(AikuColor.text3)
            .multilineTextAlignment(.trailing)
            .lineLimit(2)
            .minimumScaleFactor(0.8)
            .frame(width: Self.differenceColumnWidth, alignment: .trailing)
    }

    private func differenceRow(_ row: ConflictDifference, packedIn: Double?, actionTitle: String) -> some View {
        let title = row.title ?? actionTitle
        let yours = row.yours.map { ConflictDetails.quantity($0, packedIn: packedIn) }
        let aiku = row.aiku.map { ConflictDetails.quantity($0, packedIn: packedIn) }
        let difference = row.difference.map { ConflictDetails.signedQuantity($0, packedIn: packedIn) }
        let spokenParts: [String?] = [
            title,
            row.subtitle,
            "\(L10n.text("Yours")) \(yours ?? Self.noValue)",
            "\(L10n.text("Now in Aiku")) \(aiku ?? Self.noValue)",
            "\(L10n.text("Difference")) \(difference ?? Self.noValue)"
        ]
        let spoken = spokenParts.compactMap { $0 }.joined(separator: ", ")
        return HStack(spacing: 8) {
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.text)
                    .lineLimit(2)
                if let subtitle = row.subtitle {
                    Text(subtitle)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text4)
                        .lineLimit(1)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            differenceValue(yours, color: AikuColor.link)
            differenceValue(aiku, color: AikuColor.text)
            differenceValue(difference, color: differenceColor(row.difference))
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .frame(minHeight: 52)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(spoken)
    }

    private func differenceValue(_ text: String?, color: Color) -> some View {
        Text(text ?? Self.noValue)
            .font(.aiku(16, .bold))
            .foregroundStyle(text == nil ? AikuColor.text4 : color)
            .lineLimit(1)
            .minimumScaleFactor(0.6)
            .frame(width: Self.differenceColumnWidth, alignment: .trailing)
    }

    private func differenceColor(_ difference: Double?) -> Color {
        guard let difference else { return AikuColor.text4 }
        return difference == 0 ? AikuColor.success : AikuColor.warnInk
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
        if let reference = ConflictDetails.recordReference(entry) { return reference }
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

    private static let differenceColumnWidth: CGFloat = 70
    private static let noValue = "–"
}
