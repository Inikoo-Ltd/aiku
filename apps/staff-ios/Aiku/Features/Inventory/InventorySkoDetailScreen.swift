import SwiftUI

enum InventorySkoSheet: Identifiable {
    case audit
    case auditLocation(String)
    case move
    case consume
    case addLocation
    case locationMenu(String)
    case barcode(InventoryShownBarcode)

    var id: String {
        switch self {
        case .audit: "audit"
        case .auditLocation(let code): "audit.\(code)"
        case .move: "move"
        case .consume: "consume"
        case .addLocation: "addLocation"
        case .locationMenu(let code): "locationMenu.\(code)"
        case .barcode(let barcode): "barcode.\(barcode.value)"
        }
    }
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
                        EmptyState(icon: .sko, title: "SKO not found", message: L10n.format("%@ is not an SKO of this warehouse.", feed.title))
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
                var draft = InventoryAuditDraft(sko: store.sko(reference: reference), only: Set(counted.keys))
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
    let openHistory: () -> Void
    let openLocation: ((InventorySkoLocation) -> Void)?
    let actions: InventorySkoActions
    var offlineNotices: OfflineRecordNotices? = nil
    @State private var activeSheet: InventorySkoSheet?
    @State private var swipeOpen: GoodsOutSwipeOpen?
    @State private var pendingRemoval: InventorySkoLocation?
    @State private var isRemoving = false

    var body: some View {
        let removal = pendingRemoval
        AppScreen(
            title: sko.reference,
            kicker: L10n.format("SKO · %@", sko.live?.stateLabel ?? L10n.text(sko.state.title)),
            actions: [HeaderAction(.history, label: "Stock history") { openHistory() }]
        ) {
            header
            if let offlineNotices {
                offlineNotices
            }
            if !infoRows.isEmpty {
                InventorySkoInfoCard(title: L10n.text("SKO"), rows: infoRows) { barcode in
                    activeSheet = .barcode(barcode)
                }
            }
            InventorySkoStockCard(sko: sko, actions: stockActions)
            locationsHeading
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
                        locationCard(location)
                    }
                }
                if canEditStocks, sko.locations.count >= Self.minimumLocationsToCountAll {
                    LinkButton("Count all locations") {
                        activeSheet = .audit
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .padding(.horizontal, 4)
                    .accessibilityIdentifier("inventory.action.countAll")
                }
            }
        }
        .aikuSheet(item: $activeSheet) { sheet in
            sheetContent(sheet)
        }
        .aikuConfirm(
            removal.map { StockConfirmText.removeTitle($0) } ?? "",
            isPresented: Binding {
                pendingRemoval != nil
            } set: { isShown in
                if !isShown {
                    pendingRemoval = nil
                }
            },
            message: removal.map { StockConfirmText.removeMessage($0, skoReference: sko.reference) } ?? "",
            confirmTitle: "Remove",
            isDestructive: true
        ) {
            if let removal {
                remove(removal)
            }
        }
    }

    private static let minimumLocationsToCountAll = 2

    private var header: some View {
        HStack(spacing: 8) {
            ProductThumb(size: 96, label: L10n.format("Product photo of %@", sko.reference))
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

    private var locationsHeading: some View {
        HStack(alignment: .center, spacing: 12) {
            SectionHeading("Locations", detail: "\(sko.locations.count)")
            if canEditStocks {
                LinkButton("+ Add location", size: 13) {
                    activeSheet = .addLocation
                }
                .accessibilityIdentifier("inventory.action.addLocation")
            }
        }
    }

    private var infoRows: [InventorySkoInfoRow] {
        var rows: [InventorySkoInfoRow] = []
        if sko.unitsPerSko > 0 {
            rows.append(InventorySkoInfoRow(icon: .sko, text: L10n.format("Packed in: %lld units per SKO", sko.unitsPerSko)))
        }
        if let barcode = sko.barcode {
            rows.append(InventorySkoInfoRow(
                icon: .scan,
                text: L10n.format("SKO barcode %@", barcode),
                barcode: InventoryShownBarcode(title: L10n.text("SKO barcode"), value: barcode)
            ))
        }
        if let unitBarcode = sko.unitBarcode {
            rows.append(InventorySkoInfoRow(
                icon: .scan,
                text: L10n.format("Unit barcode %@", unitBarcode),
                barcode: InventoryShownBarcode(title: L10n.text("Unit barcode"), value: unitBarcode)
            ))
        }
        if let note = sko.pickerNote {
            rows.append(InventorySkoInfoRow(icon: .note, text: L10n.format("Note to pickers: %@", note)))
        }
        if let note = sko.packerNote {
            rows.append(InventorySkoInfoRow(icon: .note, text: L10n.format("Note to packers: %@", note)))
        }
        if let abnormality = sko.abnormalityNote {
            rows.append(InventorySkoInfoRow(icon: .warning, text: L10n.format("Abnormality: %@", abnormality)))
        }
        return rows
    }

    private var stockActions: [InventorySkoStockAction] {
        guard canEditStocks else { return [] }
        return [
            InventorySkoStockAction(id: "consume", title: "Internal consumption", icon: .sko, isEnabled: sko.canConsume) {
                activeSheet = .consume
            },
            InventorySkoStockAction(id: "move", title: "Move stock", icon: .transfer, isEnabled: sko.canMoveStock) {
                activeSheet = .move
            },
        ]
    }

    @ViewBuilder
    private func locationCard(_ location: InventorySkoLocation) -> some View {
        let card = InventorySkoLocationCard(
            location: location,
            packedIn: sko.packedIn,
            onTap: tapAction(location),
            onAudit: menuAudit(location)
        )
        if canEditStocks {
            GoodsOutSwipeRow(
                rowID: location.code,
                open: $swipeOpen,
                trailingActions: [
                    GoodsOutSwipeAction(
                        id: "inventory.remove.\(location.code)",
                        title: "Remove location",
                        icon: .trash,
                        background: AikuColor.dangerStrong,
                        foreground: AikuColor.onDangerStrong
                    ) {
                        pendingRemoval = location
                    },
                ],
                trailingBackground: AikuColor.dangerStrong,
                fullTrailing: { pendingRemoval = location }
            ) {
                card
            }
            .clipShape(RoundedRectangle(cornerRadius: 12))
        } else {
            card
        }
    }

    private func tapAction(_ location: InventorySkoLocation) -> (() -> Void)? {
        if canEditStocks {
            return { openFromCard { activeSheet = .locationMenu(location.code) } }
        }
        if let openLocation {
            return { openFromCard { openLocation(location) } }
        }
        return nil
    }

    private func openFromCard(_ open: () -> Void) {
        if swipeOpen != nil {
            swipeOpen = nil
        } else {
            open()
        }
    }

    @ViewBuilder
    private func sheetContent(_ sheet: InventorySkoSheet) -> some View {
        switch sheet {
        case .audit:
            auditSheet(onlyLocation: nil)
        case .auditLocation(let code):
            auditSheet(onlyLocation: code)
        case .move:
            InventoryMoveSheet(sko: sko, showsReasons: actions.showsMoveReasons, onCancel: closeSheet, onSave: actions.move, onDone: finish)
        case .consume:
            InventoryConsumeSheet(sko: sko, onCancel: closeSheet, onSave: actions.consume, onDone: finish)
        case .addLocation:
            InventoryAddLocationSheet(sko: sko, options: actions.locationOptions, onCancel: closeSheet, onSave: actions.addLocation, onDone: finish)
        case .locationMenu(let code):
            if let location = sko.location(code) {
                InventoryLocationMenuSheet(
                    sko: sko,
                    location: location,
                    onAudit: menuAudit(location),
                    onOpen: menuOpen(location),
                    onRemove: menuRemove(location),
                    onCancel: closeSheet
                )
            }
        case .barcode(let barcode):
            InventoryBarcodeSheet(barcode: barcode, subtitle: "\(sko.reference) · \(sko.name)", onClose: closeSheet)
        }
    }

    private func menuAudit(_ location: InventorySkoLocation) -> (() -> Void)? {
        guard canEditStocks else { return nil }
        return { activeSheet = .auditLocation(location.code) }
    }

    private func menuOpen(_ location: InventorySkoLocation) -> (() -> Void)? {
        guard let openLocation else { return nil }
        return {
            activeSheet = nil
            openLocation(location)
        }
    }

    private func menuRemove(_ location: InventorySkoLocation) -> (() -> Void)? {
        guard canEditStocks else { return nil }
        return {
            activeSheet = nil
            remove(location)
        }
    }

    private func auditSheet(onlyLocation: String?) -> some View {
        InventoryAuditSheet(
            sko: sko,
            onlyLocation: onlyLocation,
            reasonChoices: actions.reasonChoices,
            reasonsError: actions.reasonsError(),
            onCancel: closeSheet,
            onSave: actions.audit,
            onDone: finish
        )
        .task {
            await actions.loadReasons()
        }
    }

    private func remove(_ location: InventorySkoLocation) {
        guard !isRemoving else { return }
        isRemoving = true
        swipeOpen = nil
        Task {
            defer { isRemoving = false }
            do {
                finish(try await actions.removeLocations([location.code]))
            } catch {
                toasts.show(InventoryLive.message(for: error), tone: .danger)
            }
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

private struct InventorySkoInfoRow: Identifiable {
    let icon: AppIcon
    let text: String
    var barcode: InventoryShownBarcode? = nil

    var id: String { text }
}

private struct InventorySkoInfoCard: View {
    let title: String
    let rows: [InventorySkoInfoRow]
    let showBarcode: (InventoryShownBarcode) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.aiku(13, .bold))
                .foregroundStyle(AikuColor.text)
            ForEach(rows) { row in
                HStack(spacing: 10) {
                    row.icon.image
                        .font(.system(size: 14))
                        .foregroundStyle(AikuColor.text3)
                        .frame(width: 16)
                        .accessibilityHidden(true)
                    Text(row.text)
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text2)
                        .frame(maxWidth: .infinity, alignment: .leading)
                    if let barcode = row.barcode {
                        IconButton(.barcode, label: "Show barcode", tint: AikuColor.link) {
                            showBarcode(barcode)
                        }
                        .accessibilityIdentifier("inventory.showBarcode.\(barcode.value)")
                    }
                }
                .frame(minHeight: 28)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }
}

private struct InventorySkoStockAction: Identifiable {
    let id: String
    let title: String
    let icon: AppIcon
    let isEnabled: Bool
    let action: () -> Void
}

private struct InventorySkoStockCard: View {
    let sko: InventorySko
    let actions: [InventorySkoStockAction]

    var body: some View {
        Card {
            ViewThatFits(in: .horizontal) {
                HStack(alignment: .top, spacing: 12) {
                    stockSummary
                    if !actions.isEmpty {
                        VStack(spacing: 8) {
                            actionButtons
                        }
                        .fixedSize(horizontal: true, vertical: false)
                    }
                }
                VStack(alignment: .leading, spacing: 12) {
                    stockSummary
                    if !actions.isEmpty {
                        HStack(spacing: 8) {
                            actionButtons
                        }
                    }
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 14)
            RowDivider()
            KeyValueRow("Reserved", sko.live?.reservedLabel ?? sko.reserved.grouped)
            RowDivider()
            KeyValueRow("To be picked", sko.live?.toBePickedLabel ?? sko.toBePicked.grouped)
            RowDivider()
            KeyValueRow("Cover", sko.coverLabel)
        }
        .accessibilityIdentifier("inventory.stockCard")
    }

    private var stockSummary: some View {
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
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
    }

    private var actionButtons: some View {
        ForEach(actions) { action in
            AikuButton(action.title, look: AikuButtonLook(.tertiary, icon: action.icon), size: .small, action: action.action)
                .disabled(!action.isEnabled)
                .accessibilityIdentifier("inventory.action.\(action.id)")
        }
    }

    private var isNegative: Bool {
        sko.live?.stockIsNegative ?? (sko.stock < 0)
    }
}

private struct InventorySkoLocationCard: View {
    let location: InventorySkoLocation
    let packedIn: Double?
    let onTap: (() -> Void)?
    let onAudit: (() -> Void)?

    var body: some View {
        Group {
            if let onTap {
                card
                    .onTapGesture(perform: onTap)
                    .accessibilityAddTraits(.isButton)
                    .accessibilityAction {
                        onTap()
                    }
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
                    FlowLayout(spacing: 8, lineSpacing: 4) {
                        Text(location.code)
                            .font(.aiku(17, .bold))
                            .foregroundStyle(AikuColor.text)
                        StatusChip(location.type.title, tone: location.typeTone)
                    }
                    Text(location.auditedText(translate: { L10n.text($0) }))
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                if let onAudit {
                    AikuButton("Audit", look: AikuButtonLook(.tertiary, icon: .audit), size: .small, fullWidth: false, action: onAudit)
                        .fixedSize()
                        .accessibilityIdentifier("inventory.location.\(location.code).audit")
                }
            }
            .padding(.leading, 14)
            .padding(.trailing, 10)
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
