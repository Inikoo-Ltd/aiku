import SwiftUI

struct FulfilmentPalletView: View {
    let palletID: Int
    let reference: String
    @Environment(\.apiClient) private var apiClient
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouseID = warehouseStore.selected?.id {
            StoredPalletScreen(live: StoredPalletLive(warehouseID: warehouseID, palletID: palletID, apiClient: apiClient), reference: reference)
                .id("\(warehouseID)-\(palletID)")
        } else {
            AppScreen(title: reference, kicker: "Pallet") {
                EmptyState(icon: .pallet, title: "Choose a warehouse first")
            }
        }
    }
}

private struct StoredPalletScreen: View {
    @State var live: StoredPalletLive
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var isMoving = false
    @State private var isReporting = false

    var body: some View {
        Group {
            if let pallet = live.pallet {
                loaded(pallet)
            } else {
                AppScreen(title: reference, kicker: "Pallet") {
                    if let message = live.loadError {
                        InlineAlert(title: "Couldn’t load pallet \(reference)", message: message)
                        AikuButton("Try again", kind: .secondary) {
                            Task { await live.load() }
                        }
                    } else {
                        SkeletonRow()
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
        .onChange(of: live.startedAuditID) { _, auditID in
            guard let auditID else { return }
            router.open(FulfilmentRoute.storedItemAudit(id: auditID, reference: live.pallet?.reference ?? reference))
            live.auditOpened()
        }
    }

    private func loaded(_ pallet: MobileStoredPallet) -> some View {
        AppScreen(title: pallet.reference, kicker: PalletDeliveryText.joined([FulfilmentStorageText.typeText(pallet) ?? "Pallet", pallet.customer?.name])) {
            facts(pallet)
            incidentNotice(pallet)
            details(pallet)
            if pallet.itemsStorage == true {
                let items = pallet.storedItems ?? []
                SectionHeading("Stored items", detail: items.count.grouped)
                if items.isEmpty {
                    FulfilmentNote("No stored items on this pallet.")
                } else {
                    CardList(items) { item in
                        ListRow(
                            item.reference ?? "#\(item.id)",
                            subtitle: PalletDeliveryText.joined([item.name, item.barcode]),
                            chips: item.state != "active" ? (item.stateLabel.map { [Chip($0, FulfilmentStorageText.storedItemTone(item.state))] } ?? []) : []
                        ) {
                            EmptyView()
                        } trailing: {
                            Text(item.quantity.grouped)
                                .font(.aiku(15, .bold))
                                .foregroundStyle(AikuColor.text)
                                .monospacedDigit()
                        }
                    }
                }
            }
            actions(pallet)
        }
        .aikuSheet(isPresented: $isMoving) {
            MovePalletSheet(live: live, pallet: pallet) { isMoving = false }
        }
        .aikuSheet(isPresented: $isReporting) {
            PalletIncidentReportSheet(live: live, pallet: pallet) { isReporting = false }
        }
    }

    private func facts(_ pallet: MobileStoredPallet) -> some View {
        var facts = [
            GoodsInFact("State", pallet.stateLabel ?? pallet.state),
            GoodsInFact("Status", pallet.statusLabel ?? pallet.status ?? "—"),
            GoodsInFact("Type", FulfilmentStorageText.typeText(pallet) ?? "—"),
            GoodsInFact("Location", FulfilmentStorageText.locationText(pallet))
        ]
        if let customerReference = PalletDeliveryText.quoted(pallet.customerReference) {
            facts.append(GoodsInFact("Customer ref", customerReference))
        }
        if let notes = pallet.notes, !notes.isEmpty {
            facts.append(GoodsInFact("Notes", notes))
        }
        return GoodsInFactGrid(title: "Pallet", facts: facts)
    }

    @ViewBuilder
    private func incidentNotice(_ pallet: MobileStoredPallet) -> some View {
        if let incident = pallet.incident {
            let line = FulfilmentStorageText.incidentLine(pallet, includeCustomer: false)
            InlineAlert(
                tone: FulfilmentStorageText.incidentTone(incident.state),
                icon: .warning,
                title: incident.stateLabel ?? pallet.stateLabel ?? "Incident",
                message: line.isEmpty ? nil : line
            )
        }
    }

    @ViewBuilder
    private func details(_ pallet: MobileStoredPallet) -> some View {
        let lines: [(AppIcon, String)] = [
            pallet.customer?.name.map { (.person, $0) },
            pallet.delivery.map { (.stockDelivery, "Delivery \($0.reference)") },
            pallet.return.map { (.goodsOut, "Return \($0.reference)") },
            PalletDeliveryText.day(pallet.receivedAt).map { (.check, "Received \($0)") },
            PalletDeliveryText.day(pallet.bookedInAt).map { (.pallet, "Booked in \($0)") },
            PalletDeliveryText.day(pallet.storingAt).map { (.locations, "Storing since \($0)") }
        ].compactMap { $0 }
        if !lines.isEmpty {
            VStack(alignment: .leading, spacing: 6) {
                Text("Details")
                    .font(.aiku(13, .bold))
                    .foregroundStyle(AikuColor.text)
                ForEach(Array(lines.enumerated()), id: \.offset) { _, line in
                    HStack(spacing: 10) {
                        line.0.image
                            .font(.system(size: 15))
                            .foregroundStyle(AikuColor.text3)
                            .frame(width: 20)
                            .accessibilityHidden(true)
                        Text(line.1)
                            .font(.aiku(14))
                            .foregroundStyle(AikuColor.text2)
                    }
                }
            }
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
            .overlay(RoundedRectangle(cornerRadius: 12).stroke(AikuColor.border))
        }
    }

    @ViewBuilder
    private func actions(_ pallet: MobileStoredPallet) -> some View {
        let canMove = pallet.action("move") != nil
        let canAudit = pallet.action("audit") != nil
        let canReport = !pallet.incidentStates.isEmpty
        if canMove || canAudit || canReport {
            SectionHeading("Actions")
            Card {
                if canMove {
                    ListRow("Move to another location", subtitle: "Scan the new location", icon: .locations, iconTone: .info) {
                        live.resetFinder()
                        isMoving = true
                    }
                    .accessibilityIdentifier("fulfilment.pallet.move")
                }
                if canAudit {
                    if canMove { RowDivider() }
                    ListRow(pallet.openAuditId != nil ? "Continue audit" : "Stored item audit", subtitle: "Count the items on this pallet", icon: .check, iconTone: .info) {
                        Task { await live.startAudit() }
                    }
                    .disabled(live.isWorking)
                    .accessibilityIdentifier("fulfilment.pallet.audit")
                }
                if canReport {
                    if canMove || canAudit { RowDivider() }
                    ListRow("Set as damaged or lost", subtitle: "Report an incident", icon: .warning, iconTone: .danger) {
                        live.clearSheetError()
                        isReporting = true
                    }
                    .accessibilityIdentifier("fulfilment.pallet.incident")
                }
            }
        }
    }
}

private struct MovePalletSheet: View {
    @Bindable var live: StoredPalletLive
    let pallet: MobileStoredPallet
    let onClose: () -> Void
    @State private var isScanning = false

    var body: some View {
        SheetScaffold(title: "Move \(pallet.reference)", subtitle: pallet.location.map { "Now in \($0.code)" } ?? "no location yet") {
            Text("Scan the new location for \(pallet.reference)")
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
            SearchField(text: $live.locationQuery, placeholder: "Search or scan a location", onScan: { isScanning = true })
                .accessibilityIdentifier("fulfilment.move.locationSearch")
            if let message = live.locationError {
                FieldError(message)
            }
            if let picked = live.pickedLocation, !live.locationResults.contains(picked) {
                choice(picked)
            }
            ForEach(live.locationResults) { location in
                choice(location)
            }
            if live.isSearchingLocations {
                ProgressView().frame(maxWidth: .infinity)
            } else if !live.locationQuery.trimmingCharacters(in: .whitespaces).isEmpty, live.locationResults.isEmpty, live.locationError == nil, live.pickedLocation == nil {
                Text("No pallet location starts with “\(live.locationQuery.trimmingCharacters(in: .whitespaces))”")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            if let message = live.sheetError {
                FieldError(message)
            }
        } footer: {
            if let target = live.pickedLocation {
                AikuButton("Move to \(target.code)", icon: .locations) {
                    Task {
                        if await live.move(to: target) { onClose() }
                    }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.move.confirm")
            }
            AikuButton("Cancel", kind: .tertiary, action: onClose)
        }
        .task(id: live.locationQuery) {
            await live.searchLocations(live.locationQuery)
        }
        .hardwareScanner(isEnabled: !live.isWorking && !isScanning) { code in
            Task {
                if await live.move(scannedCode: code) { onClose() }
            }
        }
        .fullScreenCover(isPresented: $isScanning) {
            FulfilmentCodeCamera(title: "Scan the location", onClose: { isScanning = false }) { code in
                isScanning = false
                Task {
                    if await live.move(scannedCode: code) { onClose() }
                }
            }
        }
    }

    private func choice(_ location: MobileFulfilmentLocation) -> some View {
        RadioCard(location.code, subtitle: PalletDeliveryText.plural(location.numberPallets, "pallet", "pallets"), isSelected: live.pickedLocation?.id == location.id) {
            live.pickedLocation = location
        }
        .accessibilityIdentifier("fulfilment.move.location.\(location.code)")
    }
}

private struct PalletIncidentReportSheet: View {
    let live: StoredPalletLive
    let pallet: MobileStoredPallet
    let onClose: () -> Void
    @State private var chosen: String?
    @State private var note = ""

    var body: some View {
        let states = pallet.incidentStates
        let selected = chosen ?? states.first
        SheetScaffold(title: "Report incident", subtitle: PalletDeliveryText.joined([pallet.reference, pallet.location?.code])) {
            if states.contains("damaged") {
                RadioCard("Damaged", subtitle: "The pallet or its goods are damaged", isSelected: selected == "damaged") {
                    chosen = "damaged"
                }
                .accessibilityIdentifier("fulfilment.incident.damaged")
            }
            if states.contains("lost") {
                RadioCard("Lost", subtitle: "The pallet is not where it should be", isSelected: selected == "lost") {
                    chosen = "lost"
                }
                .accessibilityIdentifier("fulfilment.incident.lost")
            }
            AikuTextArea("What happened", text: $note, placeholder: "")
            if let message = live.sheetError {
                FieldError(message)
            }
            HelperText("Saved as the incident report with your name and today’s date. The pallet’s location is cleared.")
        } footer: {
            if let selected {
                AikuButton(selected == "lost" ? "Set as lost" : "Set as damaged", look: FulfilmentInboundActionLooks.look(for: selected == "lost" ? "lost" : "damaged")) {
                    Task {
                        if await live.reportIncident(state: selected, message: String(note.prefix(1000))) { onClose() }
                    }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.incident.save")
            }
            AikuButton("Cancel", kind: .tertiary, action: onClose)
        }
    }
}
