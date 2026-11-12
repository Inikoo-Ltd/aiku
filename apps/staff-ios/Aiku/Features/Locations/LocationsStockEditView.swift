import SwiftUI

struct LocationsStockEditView: View {
    let sko: String
    let locationCode: String
    @Environment(AppRouter.self) private var router
    @State private var store = LocationsStore.shared

    var body: some View {
        if let context = resolved {
            LocationsStockEditForm(location: context.location, slot: context.slot, store: store)
                .id("\(context.location.code)|\(context.slot.skoReference)")
        } else {
            AppScreen(title: "\(sko) in \(locationCode)", kicker: "SKO in location") {
                EmptyState(
                    icon: .sko,
                    title: "\(sko) is no longer in \(locationCode)",
                    message: "It was moved or removed from this location.",
                    actionTitle: "Back to location"
                ) {
                    router.back()
                }
            }
        }
    }

    private var resolved: (location: LocationsLocation, slot: LocationsSlot)? {
        if let location = store.warehouse.location(code: locationCode) {
            return location.slot(sko).map { (location, $0) }
        }
        guard let fallback = store.warehouse.locations.first(where: { !$0.slots.isEmpty }), let slot = fallback.slots.first else { return nil }
        return (fallback, slot)
    }
}

private enum LocationsStockEditSheet: String, Identifiable {
    case audit
    case move

    var id: String { rawValue }
}

private struct LocationsStockEditForm: View {
    let location: LocationsLocation
    let slot: LocationsSlot
    let store: LocationsStore
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.apiClient) private var apiClient
    @State private var settings: LocationsSlotSettings
    @State private var isSaving = false
    @State private var sheet: LocationsStockEditSheet?

    init(location: LocationsLocation, slot: LocationsSlot, store: LocationsStore) {
        self.location = location
        self.slot = slot
        self.store = store
        _settings = State(initialValue: slot.settings)
    }

    var body: some View {
        AppScreen(title: "\(slot.skoReference) in \(location.code)", kicker: "SKO in location") {
            Button {
                router.open(InventoryRoute.sko(reference: slot.skoReference))
            } label: {
                HStack(spacing: 12) {
                    LocationsSlotThumb(reference: slot.skoReference, size: 64)
                    VStack(alignment: .leading, spacing: 2) {
                        Text(slot.skoReference)
                            .font(.aiku(16, .bold))
                            .foregroundStyle(AikuColor.text)
                        Text("\(slot.name) · \(slot.quantity.locationsGrouped) in location")
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                            .multilineTextAlignment(.leading)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                    AppIcon.chevronRight.image
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(AikuColor.faint)
                        .accessibilityHidden(true)
                }
                .padding(.horizontal, 14)
                .padding(.vertical, 12)
                .cardSurface()
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("locations.stockEdit.sko")

            SectionHeading("Type")
            RadioCard("Picking", subtitle: "The SKO’s other picking location becomes storing", isSelected: settings.type == .picking) {
                settings.type = .picking
            }
            .accessibilityIdentifier("locations.stockEdit.picking")
            RadioCard("Storing", isSelected: settings.type == .storing) {
                settings.type = .storing
            }
            .accessibilityIdentifier("locations.stockEdit.storing")

            if settings.type == .picking {
                SectionHeading("Active picking location")
                HStack(alignment: .top, spacing: 8) {
                    ForEach(LocationsPickingRole.allCases, id: \.self) { role in
                        RadioCard(role.title, subtitle: roleDetail(role), isSelected: settings.roles.contains(role)) {
                            if slot.roles.contains(role) {
                                settings.roles.insert(role)
                            } else {
                                settings.roles.formSymmetricDifference([role])
                            }
                        }
                        .accessibilityIdentifier("locations.stockEdit.role.\(role.rawValue)")
                    }
                }
                VStack(alignment: .leading, spacing: 6) {
                    FieldLabel("Min stock")
                    QuantityStepper(value: $settings.minStock, range: 0...9999)
                }
                VStack(alignment: .leading, spacing: 6) {
                    FieldLabel("Max stock")
                    QuantityStepper(value: $settings.maxStock, range: settings.minStock...9999)
                }
            } else {
                VStack(alignment: .leading, spacing: 6) {
                    FieldLabel("Replenishment quantity")
                    QuantityStepper(value: $settings.replenishment, range: 0...9999)
                }
            }

            AikuTextArea("Note", text: $settings.note, placeholder: "Top shelf, behind the gift sets", minHeight: 60)
            if settings.type == .picking {
                HelperText("An active picking location stays active until another location of this SKO is made the active one, as on the web.")
            }
            HelperText("Storing locations have a replenishment quantity instead of min and max. Needs stock edit rights.")
        } footer: {
            HStack(spacing: 8) {
                AikuButton("Audit", kind: .tertiary) { sheet = .audit }
                    .disabled(!store.canEditStocks(in: location.code))
                    .accessibilityIdentifier("locations.stockEdit.audit")
                AikuButton("Move", kind: .tertiary) { sheet = .move }
                    .disabled(slot.quantity <= 0 || !store.canEditLocations(in: location.code))
                    .accessibilityIdentifier("locations.stockEdit.move")
                AikuButton("Save") { save() }
                    .disabled(isSaving || !store.canEditStocks(in: location.code))
                    .accessibilityIdentifier("locations.stockEdit.save")
            }
        }
        .aikuSheet(item: $sheet) { kind in
            switch kind {
            case .audit:
                LocationsAuditSheet(location: location, onlySko: slot.skoReference) { counts in
                    sheet = nil
                    audit(counts)
                } onCancel: {
                    sheet = nil
                }
            case .move:
                LocationsMoveSheet(
                    source: location,
                    warehouse: store.warehouse,
                    startsWithSome: true,
                    preselected: slot.skoReference,
                    isRemote: store.isRemote,
                    searchTargets: { query in await store.searchMoveTargets(query, apiClient: apiClient) }
                ) { target, quantities, removeFromSource, movesEverything in
                    sheet = nil
                    move(to: target, quantities: quantities, removeFromSource: removeFromSource, movesEverything: movesEverything)
                } onCancel: {
                    sheet = nil
                }
            }
        }
    }

    private func roleDetail(_ role: LocationsPickingRole) -> String {
        if settings.roles.contains(role) {
            return role.activeDetail
        }
        if let other = store.warehouse.otherLocation(holding: role, sko: slot.skoReference, excluding: location.code) {
            return "Currently \(other)"
        }
        return "Not set"
    }

    private func save() {
        guard store.isRemote else {
            store.update(sko: slot.skoReference, in: location.code, settings: settings)
            toasts.show("\(slot.skoReference) in \(location.code) saved")
            router.back()
            return
        }
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                try await store.updateRemotely(sko: slot.skoReference, in: location.code, settings: settings, apiClient: apiClient)
                toasts.show("\(slot.skoReference) in \(location.code) saved")
                router.back()
            } catch {
                toasts.show("Not saved: \(error.localizedDescription)", tone: .danger)
            }
        }
    }

    private func audit(_ counts: [LocationsAuditCount]) {
        guard store.isRemote else {
            let differences = store.audit(location: location.code, counts: counts)
            toasts.show(LocationsToastText.audit(location: location.code, differences: differences))
            return
        }
        Task {
            do {
                let differences = try await store.auditRemotely(location: location.code, counts: counts, apiClient: apiClient)
                toasts.show(LocationsToastText.audit(location: location.code, differences: differences))
            } catch {
                toasts.show("Audit not saved: \(error.localizedDescription)", tone: .danger)
            }
        }
    }

    private func move(to target: String, quantities: [String: Int], removeFromSource: Bool, movesEverything: Bool) {
        guard store.isRemote else {
            let result = store.move(from: location.code, to: target, quantities: quantities, removeFromSource: removeFromSource)
            LocationsToastText.showMove(result, target: target, store: store, toasts: toasts)
            return
        }
        Task {
            do {
                let moved = try await store.moveRemotely(from: location.code, to: target, quantities: quantities, removeFromSource: removeFromSource, movesEverything: false, apiClient: apiClient)
                toasts.show(moved > 0 ? "\(LocationsFormat.units(moved)) moved to \(target)" : "Nothing to move", tone: moved > 0 ? .success : .warn)
            } catch {
                toasts.show("Move not saved: \(error.localizedDescription)", tone: .danger)
            }
        }
    }
}
