import SwiftUI

enum LocationsDetailSheet: String, Identifiable {
    case audit
    case moveAll
    case moveSome
    case remove

    var id: String { rawValue }
}

struct LocationsDetailView: View {
    let code: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var store = LocationsStore.shared
    @Environment(\.apiClient) private var apiClient
    @State private var sheet: LocationsDetailSheet?
    @State private var lookedUpCode: String?

    var body: some View {
        locationContent
            .task(id: code) {
                await store.loadLocation(code: code, apiClient: apiClient)
                lookedUpCode = code
            }
            .screenRefreshable { await store.loadLocation(code: code, apiClient: apiClient) }
    }

    @ViewBuilder
    private var locationContent: some View {
        if let location = store.location(code: code) {
            content(location)
        } else if lookedUpCode != code {
            AppScreen(title: code, kicker: "Location") {
                SkeletonRow()
                SkeletonRow()
            }
        } else {
            AppScreen(title: code, kicker: "Location") {
                EmptyState(icon: .locations, title: L10n.format("Location %@ not found", code), message: "It isn’t in this warehouse, or it was deleted.")
            }
        }
    }

    private func content(_ location: LocationsLocation) -> some View {
        let hasSkos = !location.slots.isEmpty
        return AppScreen(
            title: location.code,
            kicker: location.kicker,
            actions: hasSkos ? [HeaderAction(.check, label: "Audit location") { sheet = .audit }] : []
        ) {
            OfflineRecordNotices(queue: store.offlineQueue(code: location.code), operations: OfflineOperation.stock, copyAt: store.copyDates[location.code]) { entry in
                StockOfflineText.summary(entry, names: store.offlineNames(code: location.code))
            }
            overview(location)
            if hasSkos {
                SectionHeading("SKOs", detail: "\(location.slots.count)")
                CardList(location.slots) { slot in
                    ListRow(
                        slot.skoReference,
                        subtitle: slot.name,
                        chips: [slot.type.chip],
                        action: { router.open(LocationsRoute.skoInLocation(sko: slot.skoReference, location: location.code)) }
                    ) {
                        LocationsSlotThumb(reference: slot.skoReference)
                    } trailing: {
                        Text(slot.quantityText)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(slot.isEmpty ? AikuColor.warn : AikuColor.text)
                            .monospacedDigit()
                    }
                    .accessibilityIdentifier("locations.slot.\(slot.skoReference)")
                }
                SectionHeading("Location actions")
                actions(location)
            } else if store.isWaitingForSkos(in: location) {
                HStack(spacing: 8) {
                    ProgressView()
                    Text("Loading SKOs…")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, minHeight: 44)
                .accessibilityElement(children: .combine)
                .accessibilityLabel("Loading SKOs")
            } else if location.pallets.isEmpty {
                HelperText("No SKOs in this location.")
            }
            Card {
                if location.pallets.isEmpty {
                    ListRow("Pallets", subtitle: "None in this location", icon: .pallet, iconTone: .neutral)
                } else {
                    ForEach(location.pallets) { pallet in
                        ListRow(
                            pallet.reference,
                            subtitle: [pallet.customer, L10n.text("Pallet"), pallet.state].filter { !$0.isEmpty }.joined(separator: " · "),
                            icon: .pallet,
                            iconTone: .neutral
                        )
                        .accessibilityIdentifier("locations.pallet.\(pallet.reference)")
                        RowDivider()
                    }
                }
                if location.pallets.isEmpty {
                    RowDivider()
                }
                ListRow("Stock movements", subtitle: "History of this location", icon: .history, iconTone: .neutral, showsChevron: true) {
                    router.open(LocationsRoute.movements(code: location.code))
                }
                .accessibilityIdentifier("locations.movements")
            }
            HelperText("Status is operational or broken. Moving and removing need location edit rights. SKOs are added to a location from the SKO page.")
        }
        .aikuSheet(item: $sheet) { kind in
            sheetContent(kind, location: location)
        }
    }

    private func overview(_ location: LocationsLocation) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("Overview")
                .font(.aiku(13, .bold))
                .foregroundStyle(AikuColor.text)
            LocationsOverviewLine(icon: .sko, text: location.scopeSummary)
            LocationsOverviewLine(icon: .weight, text: location.capacitySummary)
            LocationsOverviewLine(icon: .scan, text: L10n.format("Barcode %@ (Code 128)", location.barcode))
            LocationsOverviewLine(icon: .history, text: location.auditSummary)
            if let flags = location.flagSummary {
                LocationsOverviewLine(icon: .pin, text: flags)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }

    private func actions(_ location: LocationsLocation) -> some View {
        let canAudit = store.canEditStocks(in: location.code)
        let canMove = store.canEditLocations(in: location.code)
        return VStack(spacing: 8) {
            HStack(spacing: 8) {
                LocationsActionButton(title: "Audit", icon: .check) { sheet = .audit }
                    .disabled(!canAudit)
                    .accessibilityIdentifier("locations.action.audit")
                LocationsActionButton(title: "Move all SKOs", icon: .locations) { sheet = .moveAll }
                    .disabled(!canMove)
                    .accessibilityIdentifier("locations.action.moveAll")
            }
            HStack(spacing: 8) {
                LocationsActionButton(title: "Move some", icon: .locations) { sheet = .moveSome }
                    .disabled(!canMove)
                    .accessibilityIdentifier("locations.action.moveSome")
                LocationsActionButton(title: "Remove SKOs", icon: .trash, kind: .danger) { sheet = .remove }
                    .disabled(!canMove)
                    .accessibilityIdentifier("locations.action.remove")
            }
            if store.isRemote, !canAudit || !canMove {
                HelperText(LocationsRightsText.detail(canAudit: canAudit, canMove: canMove))
            }
        }
    }

    @ViewBuilder
    private func sheetContent(_ kind: LocationsDetailSheet, location: LocationsLocation) -> some View {
        switch kind {
        case .audit:
            LocationsAuditSheet(
                location: location,
                onFindSko: store.isRemote ? { code in await store.findSkoForAudit(code, in: location.code, apiClient: apiClient) } : nil
            ) { counts in
                saveAudit(location: location, counts: counts)
            } onCancel: {
                sheet = nil
            }
        case .moveAll, .moveSome:
            LocationsMoveSheet(
                source: location,
                warehouse: store.warehouse,
                startsWithSome: kind == .moveSome,
                isRemote: store.isRemote,
                searchTargets: { query in await store.searchMoveTargets(query, apiClient: apiClient) }
            ) { target, quantities, removeFromSource, movesEverything in
                move(from: location.code, to: target, quantities: quantities, removeFromSource: removeFromSource, movesEverything: movesEverything)
            } onCancel: {
                sheet = nil
            }
        case .remove:
            LocationsRemoveSheet(location: location) { references in
                remove(references, from: location.code)
            } onCancel: {
                sheet = nil
            }
        }
    }

    private func saveAudit(location: LocationsLocation, counts: [LocationsAuditCount]) {
        sheet = nil
        guard store.isRemote else {
            let differences = store.audit(location: location.code, counts: counts)
            toasts.show(LocationsToastText.audit(location: location.code, differences: Int(differences)))
            return
        }
        Task {
            do {
                switch try await store.auditRemotely(location: location.code, counts: counts, apiClient: apiClient) {
                case .saved(let differences):
                    toasts.show(LocationsToastText.audit(location: location.code, differences: Int(differences)))
                case .queued:
                    toasts.show(OfflineRecordText.queued, tone: .info)
                }
            } catch {
                toasts.show(L10n.format("Audit not saved: %@", error.localizedDescription), tone: .danger)
            }
        }
    }

    private func move(from source: String, to target: String, quantities: [String: Double], removeFromSource: Bool, movesEverything: Bool) {
        sheet = nil
        guard store.isRemote else {
            let result = store.move(from: source, to: target, quantities: quantities, removeFromSource: removeFromSource)
            LocationsToastText.showMove(result, target: target, store: store, toasts: toasts)
            return
        }
        Task {
            do {
                let outcome = try await store.moveRemotely(from: source, to: target, quantities: quantities, removeFromSource: removeFromSource, movesEverything: movesEverything, apiClient: apiClient)
                LocationsToastText.showMove(outcome, target: target, toasts: toasts)
            } catch {
                toasts.show(L10n.format("Move not saved: %@", error.localizedDescription), tone: .danger)
            }
        }
    }

    private func remove(_ references: Set<String>, from code: String) {
        sheet = nil
        guard store.isRemote else {
            let result = store.remove(skos: references, from: code)
            toasts.show(L10n.format("%lld SKOs removed from %@", result.removed, code), actionTitle: "Undo") {
                store.restore(result.snapshot)
            }
            return
        }
        Task {
            do {
                let removed = try await store.removeRemotely(skos: references, from: code, apiClient: apiClient)
                toasts.show(L10n.format("%lld SKOs removed from %@", removed, code))
            } catch {
                toasts.show(L10n.format("SKOs not removed: %@", error.localizedDescription), tone: .danger)
            }
        }
    }
}

enum LocationsRightsText {
    static func detail(canAudit: Bool, canMove: Bool) -> String {
        switch (canAudit, canMove) {
        case (false, false): "Auditing needs stock edit rights; moving and removing SKOs need location edit rights."
        case (false, true): "Auditing needs stock edit rights."
        default: "Moving and removing SKOs need location edit rights."
        }
    }
}

@MainActor
enum LocationsToastText {
    static func audit(location: String, differences: Int) -> String {
        differences == 0
            ? L10n.format("Audit saved · %@ · no differences", location)
            : L10n.format("Audit saved · %@ · %lld differences", location, differences)
    }

    @MainActor
    static func showMove(_ outcome: LocationsWriteOutcome, target: String, toasts: ToastCenter) {
        switch outcome {
        case .saved(let moved):
            toasts.show(moved > 0 ? movedText(moved, target: target) : L10n.text("Nothing to move"), tone: moved > 0 ? .success : .warn)
        case .queued:
            toasts.show(OfflineRecordText.queued, tone: .info)
        }
    }

    @MainActor
    static func movedText(_ moved: Double, target: String) -> String {
        moved == moved.rounded() ? L10n.format("%lld units moved to %@", Int(moved), target) : L10n.text("Stock moved")
    }

    @MainActor
    static func showMove(_ result: (moved: Double, snapshot: LocationsWarehouse), target: String, store: LocationsStore, toasts: ToastCenter) {
        guard result.moved > 0 else {
            toasts.show("Nothing to move", tone: .warn)
            return
        }
        toasts.show(movedText(result.moved, target: target), actionTitle: "Undo") {
            store.restore(result.snapshot)
        }
    }
}
