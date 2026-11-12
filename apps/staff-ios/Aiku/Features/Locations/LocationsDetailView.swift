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

    var body: some View {
        locationContent
            .task(id: code) {
                await store.loadLocation(code: code, apiClient: apiClient)
            }
    }

    @ViewBuilder
    private var locationContent: some View {
        if let location = store.warehouse.locationOrFallback(code: code) {
            content(location)
        } else {
            AppScreen(title: "Location") {
                EmptyState(icon: .locations, title: "Location not found")
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
                        Text(slot.quantity.locationsGrouped)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(slot.isEmpty ? AikuColor.warn : AikuColor.text)
                            .monospacedDigit()
                    }
                    .accessibilityIdentifier("locations.slot.\(slot.skoReference)")
                }
                SectionHeading("Location actions")
                actions
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
                        ListRow(pallet.reference, subtitle: "\(pallet.customer) · Pallet · \(pallet.state)", icon: .pallet, iconTone: .neutral, showsChevron: true) {
                            router.open(FulfilmentRoute.pallet(reference: pallet.reference))
                        }
                        .accessibilityIdentifier("locations.pallet.\(pallet.reference)")
                        RowDivider()
                    }
                }
                if location.pallets.isEmpty {
                    RowDivider()
                }
                ListRow("Stock movements", subtitle: "History of this location", icon: .history, iconTone: .neutral) {
                    router.open(InventoryRoute.stockHistory)
                }
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
            LocationsOverviewLine(icon: .scan, text: "Barcode \(location.barcode) (Code 128)")
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

    private var actions: some View {
        VStack(spacing: 8) {
            HStack(spacing: 8) {
                LocationsActionButton(title: "Audit", icon: .check) { sheet = .audit }
                    .accessibilityIdentifier("locations.action.audit")
                LocationsActionButton(title: "Move all SKOs", icon: .locations) { sheet = .moveAll }
                    .accessibilityIdentifier("locations.action.moveAll")
            }
            HStack(spacing: 8) {
                LocationsActionButton(title: "Move some", icon: .locations) { sheet = .moveSome }
                    .accessibilityIdentifier("locations.action.moveSome")
                LocationsActionButton(title: "Remove SKOs", kind: .danger) { sheet = .remove }
                    .accessibilityIdentifier("locations.action.remove")
            }
        }
    }

    @ViewBuilder
    private func sheetContent(_ kind: LocationsDetailSheet, location: LocationsLocation) -> some View {
        switch kind {
        case .audit:
            LocationsAuditSheet(location: location) { counts in
                saveAudit(location: location, counts: counts)
            } onCancel: {
                sheet = nil
            }
        case .moveAll, .moveSome:
            LocationsMoveSheet(
                source: location,
                warehouse: store.warehouse,
                startsWithSome: kind == .moveSome
            ) { target, quantities, removeFromSource in
                move(from: location.code, to: target, quantities: quantities, removeFromSource: removeFromSource)
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
        let differences = store.audit(location: location.code, counts: counts)
        sheet = nil
        toasts.show(LocationsToastText.audit(location: location.code, differences: differences))
    }

    private func move(from source: String, to target: String, quantities: [String: Int], removeFromSource: Bool) {
        let result = store.move(from: source, to: target, quantities: quantities, removeFromSource: removeFromSource)
        sheet = nil
        LocationsToastText.showMove(result, target: target, store: store, toasts: toasts)
    }

    private func remove(_ references: Set<String>, from code: String) {
        let result = store.remove(skos: references, from: code)
        sheet = nil
        toasts.show("\(LocationsFormat.skos(result.removed)) removed from \(code)", actionTitle: "Undo") {
            store.restore(result.snapshot)
        }
    }
}

enum LocationsToastText {
    static func audit(location: String, differences: Int) -> String {
        switch differences {
        case 0: "Audit saved · \(location) · no differences"
        case 1: "Audit saved · \(location) · 1 difference"
        default: "Audit saved · \(location) · \(differences) differences"
        }
    }

    @MainActor
    static func showMove(_ result: (moved: Int, snapshot: LocationsWarehouse), target: String, store: LocationsStore, toasts: ToastCenter) {
        guard result.moved > 0 else {
            toasts.show("Nothing to move", tone: .warn)
            return
        }
        toasts.show("\(LocationsFormat.units(result.moved)) moved to \(target)", actionTitle: "Undo") {
            store.restore(result.snapshot)
        }
    }
}
