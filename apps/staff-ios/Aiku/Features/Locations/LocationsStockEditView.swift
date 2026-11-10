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
    @State private var settings: LocationsSlotSettings
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
                            settings.roles.formSymmetricDifference([role])
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
            HelperText("Storing locations have a replenishment quantity instead of min and max. Needs stock edit rights.")
        } footer: {
            HStack(spacing: 8) {
                AikuButton("Audit", kind: .tertiary) { sheet = .audit }
                    .accessibilityIdentifier("locations.stockEdit.audit")
                AikuButton("Move", kind: .tertiary) { sheet = .move }
                    .disabled(slot.quantity <= 0)
                    .accessibilityIdentifier("locations.stockEdit.move")
                AikuButton("Save") { save() }
                    .accessibilityIdentifier("locations.stockEdit.save")
            }
        }
        .aikuSheet(item: $sheet) { kind in
            switch kind {
            case .audit:
                LocationsAuditSheet(location: location, onlySko: slot.skoReference) { counts in
                    let differences = store.audit(location: location.code, counts: counts)
                    sheet = nil
                    toasts.show(LocationsToastText.audit(location: location.code, differences: differences))
                } onCancel: {
                    sheet = nil
                }
            case .move:
                LocationsMoveSheet(
                    source: location,
                    warehouse: store.warehouse,
                    startsWithSome: true,
                    preselected: slot.skoReference
                ) { target, quantities, removeFromSource in
                    let result = store.move(from: location.code, to: target, quantities: quantities, removeFromSource: removeFromSource)
                    sheet = nil
                    LocationsToastText.showMove(result, target: target, store: store, toasts: toasts)
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
        store.update(sko: slot.skoReference, in: location.code, settings: settings)
        toasts.show("\(slot.skoReference) in \(location.code) saved")
        router.back()
    }
}
