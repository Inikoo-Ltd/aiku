import SwiftUI

enum InventorySkoSheet: String, Identifiable {
    case audit
    case move
    case consume
    case addLocation
    case removeLocations

    var id: String { rawValue }
}

struct InventorySkoActions {
    var reasonChoices: (Int) -> [InventoryReasonChoice]
    var reasonsError: () -> String? = { nil }
    var loadReasons: () async -> Void = {}
    var audit: ([String: Int], [String: String]) async throws -> String
    var showsMoveReasons = true
    var move: (String, String, Int, InventoryMoveReason) async throws -> String
    var consume: (String, Int, String) async throws -> String
    var locationOptions: (String) async throws -> [InventoryLocationOption]
    var addLocation: (InventoryLocationOption, InventoryLocationType) async throws -> String
    var removeLocations: (Set<String>) async throws -> String
    var undo: (() -> Void)?
}

struct InventorySkoDetailScreen: View {
    let target: InventorySkoFeed.Target
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouse = warehouseStore.selected {
            InventoryLiveSkoDetail(
                feed: InventorySkoFeed(warehouseID: warehouse.id, target: target, canEditStocks: warehouse.can.moveStock),
                canViewLocations: warehouse.can.viewLocations
            )
        } else {
            switch target {
            case .reference(let reference):
                InventorySampleSkoDetail(reference: reference)
            case .id:
                AppScreen(title: "SKO", kicker: "SKO") {
                    EmptyState(icon: .sko, title: "Choose a warehouse", message: "Pick a warehouse to open this SKO.")
                }
            }
        }
    }
}

private struct InventoryLiveSkoDetail: View {
    @State var feed: InventorySkoFeed
    let canViewLocations: Bool
    @Environment(AppRouter.self) private var router

    var body: some View {
        Group {
            if let sko = feed.sko {
                InventorySkoDetailView(
                    sko: sko,
                    familyTitle: sko.live?.familyTitle,
                    canEditStocks: sko.live?.canEditStocks ?? false,
                    emptyLocationsMessage: "This SKO has no location in this warehouse.",
                    removeWarning: "Stock left in a removed location is written off.",
                    openHistory: {
                        router.open(InventoryRoute.skoHistoryID(id: sko.live?.id ?? 0, reference: sko.reference))
                    },
                    openLocation: canViewLocations ? { location in
                        router.open(LocationsRoute.location(code: location.code))
                    } : nil,
                    actions: actions,
                    offlineNotices: OfflineRecordNotices(queue: feed.offlineQueue, operations: OfflineOperation.stock, copyAt: feed.copyAt) { entry in
                        StockOfflineText.summary(entry, names: StockOfflineText.Names(linkLocation: { linkID in
                            sko.locations.first { $0.link?.linkID == linkID }?.code
                        }))
                    }
                )
            } else {
                AppScreen(title: feed.title, kicker: "SKO") {
                    if feed.isNotFound {
                        EmptyState(icon: .sko, title: "SKO not found", message: "\(feed.title) is not an SKO of this warehouse.")
                    } else if let message = feed.loadError {
                        InventoryLoadAlert(title: "Couldn’t load the SKO", message: message) {
                            Task { await feed.load() }
                        }
                    } else {
                        InventorySkeletonCard()
                    }
                }
            }
        }
        .screenRefreshable {
            await feed.load()
        }
        .task {
            await feed.load()
        }
    }

    private var actions: InventorySkoActions {
        InventorySkoActions(
            reasonChoices: { feed.reasonChoices(change: $0) },
            reasonsError: { feed.reasonsError },
            loadReasons: { await feed.loadReasons() },
            audit: { try await feed.audit(counted: $0, reasons: $1) },
            showsMoveReasons: false,
            move: { from, to, quantity, _ in try await feed.move(from: from, to: to, quantity: quantity) },
            consume: { try await feed.consume(location: $0, quantity: $1, note: $2) },
            locationOptions: { try await feed.locationOptions(search: $0) },
            addLocation: { try await feed.addLocation($0, type: $1) },
            removeLocations: { try await feed.removeLocations($0) },
            undo: nil
        )
    }
}

private struct InventorySampleSkoDetail: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    private let store = InventoryStore.shared

    var body: some View {
        let sko = store.sko(reference: reference)
        InventorySkoDetailView(
            sko: sko,
            familyTitle: store.family(of: sko)?.title,
            canEditStocks: true,
            emptyLocationsMessage: "Add a location to start keeping stock of this SKO.",
            removeWarning: "Stock left in a removed location is written off.",
            openHistory: {
                router.open(InventoryRoute.skoHistory(reference: sko.reference))
            },
            openLocation: { location in
                router.open(LocationsRoute.skoInLocation(sko: sko.reference, location: location.code))
            },
            actions: actions(sko)
        )
    }

    private func actions(_ sko: InventorySko) -> InventorySkoActions {
        let reference = sko.reference
        return InventorySkoActions(
            reasonChoices: { change in
                InventoryAuditReason.reasons(forChange: change).map { InventoryReasonChoice(id: $0.rawValue, title: $0.title) }
            },
            audit: { counted, reasons in
                var draft = InventoryAuditDraft(sko: store.sko(reference: reference))
                draft.counts.merge(counted) { _, new in new }
                draft.reasons = reasons.compactMapValues(InventoryAuditReason.init(rawValue:))
                return try store.audit(reference, draft: draft)
            },
            move: { try store.move(reference, from: $0, to: $1, quantity: $2, reason: $3) },
            consume: { try store.consume(reference, location: $0, quantity: $1, note: $2) },
            locationOptions: { search in store.availableLocations(for: store.sko(reference: reference), search: search) },
            addLocation: { try store.addLocation(reference, location: $0.code, type: $1) },
            removeLocations: { try store.removeLocations(reference, codes: $0) },
            undo: { store.undo() }
        )
    }
}

private struct InventorySkoDetailView: View {
    @Environment(ToastCenter.self) private var toasts
    let sko: InventorySko
    let familyTitle: String?
    let canEditStocks: Bool
    let emptyLocationsMessage: String
    let removeWarning: String
    let openHistory: () -> Void
    let openLocation: ((InventorySkoLocation) -> Void)?
    let actions: InventorySkoActions
    var offlineNotices: OfflineRecordNotices? = nil
    @State private var activeSheet: InventorySkoSheet?

    var body: some View {
        AppScreen(
            title: sko.reference,
            kicker: "SKO · \(sko.live?.stateLabel ?? sko.state.title)",
            actions: [HeaderAction(.history, label: "Stock history") { openHistory() }]
        ) {
            header
            if let offlineNotices {
                offlineNotices
            }
            if !infoLines.isEmpty {
                InventoryInfoCard(title: "SKO", lines: infoLines)
            }
            InventorySkoStockCard(sko: sko)
            SectionHeading("Locations", detail: "\(sko.locations.count)")
            if sko.locations.isEmpty {
                if canEditStocks {
                    EmptyState(icon: .locations, title: "No locations yet", message: emptyLocationsMessage, actionTitle: "Add location") {
                        activeSheet = .addLocation
                    }
                } else {
                    EmptyState(icon: .locations, title: "No locations yet", message: emptyLocationsMessage)
                }
            } else {
                VStack(spacing: 8) {
                    ForEach(sko.locations) { location in
                        InventorySkoLocationCard(
                            location: location,
                            packedIn: sko.packedIn,
                            action: openLocation.map { open in { open(location) } }
                        )
                    }
                }
            }
            if canEditStocks {
                SectionHeading("Stock actions")
                actionButtons
                HelperText("Stock actions need stock edit rights. Move stock needs at least two locations.")
            }
        }
        .aikuSheet(item: $activeSheet) { sheet in
            sheetContent(sheet)
        }
    }

    private var header: some View {
        HStack(spacing: 8) {
            ProductThumb(size: 96, label: "Product photo of \(sko.reference)")
            VStack(alignment: .leading, spacing: 4) {
                Text(sko.name)
                    .font(.aiku(16, .bold))
                    .foregroundStyle(AikuColor.text)
                    .fixedSize(horizontal: false, vertical: true)
                FlowLayout(spacing: 6, lineSpacing: 6) {
                    StatusChip(sko.listChip)
                    if sko.isOnDemand {
                        StatusChip("On demand", tone: .info)
                    }
                    if let familyTitle {
                        StatusChip(familyTitle, tone: .neutral)
                    }
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
    }

    private var infoLines: [InventoryInfoLine] {
        var lines: [InventoryInfoLine] = []
        if sko.unitsPerSko > 0 {
            lines.append(InventoryInfoLine(icon: .sko, text: "Packed in: \(sko.unitsPerSko) \(sko.unitsPerSko == 1 ? "unit" : "units") per SKO"))
        }
        let barcodes = [sko.barcode.map { "SKO barcode \($0)" }, sko.unitBarcode.map { "unit EAN \($0)" }].compactMap { $0 }
        if !barcodes.isEmpty {
            lines.append(InventoryInfoLine(icon: .scan, text: barcodes.joined(separator: " · ")))
        }
        if let note = sko.pickerNote {
            lines.append(InventoryInfoLine(icon: .note, text: "Note to pickers: \(note)"))
        }
        if let note = sko.packerNote {
            lines.append(InventoryInfoLine(icon: .note, text: "Note to packers: \(note)"))
        }
        if let abnormality = sko.abnormalityNote {
            lines.append(InventoryInfoLine(icon: .warning, text: "Abnormality: \(abnormality)"))
        }
        return lines
    }

    private var actionButtons: some View {
        VStack(spacing: 8) {
            StatGrid(columns: 2) {
                actionButton("Audit stock", icon: .check, sheet: .audit, isEnabled: !sko.locations.isEmpty)
                actionButton("Move stock", icon: .locations, sheet: .move, isEnabled: sko.canMoveStock)
                actionButton("Internal consumption", icon: .sko, sheet: .consume, isEnabled: sko.canConsume)
                actionButton("Add location", icon: .locations, sheet: .addLocation, isEnabled: true)
            }
            AikuButton("Remove locations", look: AikuButtonLook(.danger, icon: .trash)) {
                activeSheet = .removeLocations
            }
            .disabled(sko.locations.isEmpty)
            .accessibilityIdentifier("inventory.action.removeLocations")
        }
    }

    private func actionButton(_ title: String, icon: AppIcon, sheet: InventorySkoSheet, isEnabled: Bool) -> some View {
        Button {
            activeSheet = sheet
        } label: {
            HStack(spacing: 8) {
                icon.image.font(.system(size: 17, weight: .medium))
                Text(title)
            }
        }
        .buttonStyle(AikuButtonStyle(kind: .tertiary))
        .disabled(!isEnabled)
        .accessibilityIdentifier("inventory.action.\(sheet.rawValue)")
    }

    @ViewBuilder
    private func sheetContent(_ sheet: InventorySkoSheet) -> some View {
        switch sheet {
        case .audit:
            InventoryAuditSheet(
                sko: sko,
                reasonChoices: actions.reasonChoices,
                reasonsError: actions.reasonsError(),
                onCancel: closeSheet,
                onSave: actions.audit,
                onDone: finish
            )
            .task {
                await actions.loadReasons()
            }
        case .move:
            InventoryMoveSheet(sko: sko, showsReasons: actions.showsMoveReasons, onCancel: closeSheet, onSave: actions.move, onDone: finish)
        case .consume:
            InventoryConsumeSheet(sko: sko, onCancel: closeSheet, onSave: actions.consume, onDone: finish)
        case .addLocation:
            InventoryAddLocationSheet(sko: sko, options: actions.locationOptions, onCancel: closeSheet, onSave: actions.addLocation, onDone: finish)
        case .removeLocations:
            InventoryRemoveLocationsSheet(sko: sko, warning: removeWarning, onCancel: closeSheet, onSave: actions.removeLocations, onDone: finish)
        }
    }

    private func closeSheet() {
        activeSheet = nil
    }

    private func finish(_ message: String) {
        activeSheet = nil
        if let undo = actions.undo {
            toasts.show(message, actionTitle: "Undo") {
                undo()
                toasts.show("Undone", tone: .info)
            }
        } else {
            toasts.show(message, tone: .success)
        }
    }
}

private struct InventorySkoStockCard: View {
    let sko: InventorySko

    var body: some View {
        Card {
            VStack(alignment: .leading, spacing: 6) {
                Text(L10n.text("In locations").uppercased(with: LanguageStore.shared.locale))
                    .font(.aiku(12, .bold))
                    .tracking(0.7)
                    .foregroundStyle(AikuColor.text3)
                Text(sko.live?.stockLabel ?? sko.stock.grouped)
                    .font(.aiku(32, .bold))
                    .foregroundStyle(isNegative ? AikuColor.danger : AikuColor.text)
                    .monospacedDigit()
                    .lineLimit(1)
                    .minimumScaleFactor(0.6)
                if let unitsInTotal = sko.unitsInTotalText(translate: { L10n.text($0) }) {
                    Text(unitsInTotal)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .accessibilityElement(children: .combine)
            RowDivider()
            KeyValueRow("Reserved", sko.live?.reservedLabel ?? sko.reserved.grouped)
            RowDivider()
            KeyValueRow("To be picked", sko.live?.toBePickedLabel ?? sko.toBePicked.grouped)
            RowDivider()
            KeyValueRow("Cover", sko.coverLabel)
        }
        .accessibilityIdentifier("inventory.stockCard")
    }

    private var isNegative: Bool {
        sko.live?.stockIsNegative ?? (sko.stock < 0)
    }
}

private struct InventorySkoLocationCard: View {
    let location: InventorySkoLocation
    let packedIn: Double?
    let action: (() -> Void)?

    var body: some View {
        Group {
            if let action {
                Button(action: action) { card }
                    .buttonStyle(.plain)
            } else {
                card
            }
        }
        .accessibilityIdentifier("inventory.location.\(location.code)")
    }

    private var card: some View {
        let footerLines = location.footerLines(translate: { L10n.text($0) })
        return Card {
            HStack(alignment: .center, spacing: 12) {
                IconTile(.locations, tone: .info)
                VStack(alignment: .leading, spacing: 2) {
                    Text(location.code)
                        .font(.aiku(17, .bold))
                        .foregroundStyle(AikuColor.text)
                        .fixedSize(horizontal: false, vertical: true)
                    Text(location.auditedText(translate: { L10n.text($0) }))
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                StatusChip(location.type.title, tone: location.typeTone)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            RowDivider()
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                Text(location.quantityText)
                    .font(.aiku(20, .bold))
                    .foregroundStyle(location.isNegative ? AikuColor.danger : AikuColor.text)
                    .monospacedDigit()
                Spacer(minLength: 8)
                if let unitsInTotal = location.unitsInTotalText(packedIn: packedIn, translate: { L10n.text($0) }) {
                    Text(unitsInTotal)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .multilineTextAlignment(.trailing)
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .frame(minHeight: 44)
            if !footerLines.isEmpty {
                RowDivider()
                VStack(alignment: .leading, spacing: 4) {
                    ForEach(footerLines, id: \.self) { line in
                        Text(line)
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text2)
                    }
                }
                .padding(.horizontal, 14)
                .padding(.vertical, 10)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(AikuColor.surface2)
            }
        }
        .contentShape(Rectangle())
    }
}
