import SwiftUI

enum InventorySkoSheet: String, Identifiable {
    case audit
    case move
    case consume
    case addLocation
    case removeLocations

    var id: String { rawValue }
}

struct InventorySkoDetailScreen: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let reference: String
    @State private var activeSheet: InventorySkoSheet?
    private let store = InventoryStore.shared

    var body: some View {
        let sko = store.sko(reference: reference)
        AppScreen(
            title: sko.reference,
            kicker: "SKO · \(sko.state.title)",
            actions: [HeaderAction(.history, label: "Stock history") {
                router.open(InventoryRoute.skoHistory(reference: sko.reference))
            }]
        ) {
            header(sko)
            InventoryInfoCard(title: "SKO", lines: infoLines(sko))
            StatGrid(columns: 4) {
                StatTile(sko.stock.grouped, label: "In locations", tone: sko.stock < 0 ? .danger : nil)
                StatTile(sko.reserved.grouped, label: "Reserved")
                StatTile(sko.toBePicked.grouped, label: "To be picked")
                StatTile(sko.coverLabel, label: "Cover")
            }
            SectionHeading("Locations", detail: "\(sko.locations.count)")
            if sko.locations.isEmpty {
                EmptyState(
                    icon: .locations,
                    title: "No locations yet",
                    message: "Add a location to start keeping stock of this SKO.",
                    actionTitle: "Add location"
                ) {
                    activeSheet = .addLocation
                }
            } else {
                CardList(sko.locations) { location in
                    ListRow(
                        location.code,
                        subtitle: location.settingsLine,
                        icon: .locations,
                        iconTone: .info,
                        chips: [location.chip],
                        action: {
                            router.open(LocationsRoute.skoInLocation(sko: sko.reference, location: location.code))
                        }
                    ) {
                        Text(location.quantity.grouped)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(location.quantity < 0 ? AikuColor.danger : AikuColor.text)
                            .monospacedDigit()
                            .fixedSize()
                    }
                    .accessibilityIdentifier("inventory.location.\(location.code)")
                }
            }
            SectionHeading("Stock actions")
            actions(sko)
            HelperText("Stock actions need stock edit rights. Move stock needs at least two locations.")
        }
        .aikuSheet(item: $activeSheet) { sheet in
            sheetContent(sheet, sko: sko)
        }
    }

    private func header(_ sko: InventorySko) -> some View {
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
                    if let family = store.family(of: sko) {
                        StatusChip(family.title, tone: .neutral)
                    }
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
    }

    private func infoLines(_ sko: InventorySko) -> [InventoryInfoLine] {
        var lines = [
            InventoryInfoLine(icon: .sko, text: "Packed in: \(sko.unitsPerSko) units per SKO"),
            InventoryInfoLine(icon: .scan, text: "SKO barcode \(sko.barcode) · unit EAN \(sko.unitBarcode)")
        ]
        if let note = sko.pickerNote {
            lines.append(InventoryInfoLine(icon: .note, text: "Note to pickers: \(note)"))
        }
        if let abnormality = sko.abnormalityNote {
            lines.append(InventoryInfoLine(icon: .warning, text: "Abnormality: \(abnormality)"))
        }
        return lines
    }

    private func actions(_ sko: InventorySko) -> some View {
        VStack(spacing: 8) {
            StatGrid(columns: 2) {
                actionButton("Audit stock", icon: .check, sheet: .audit, isEnabled: !sko.locations.isEmpty)
                actionButton("Move stock", icon: .locations, sheet: .move, isEnabled: sko.canMoveStock)
                actionButton("Internal consumption", icon: .sko, sheet: .consume, isEnabled: sko.canConsume)
                actionButton("Add location", icon: .locations, sheet: .addLocation, isEnabled: true)
            }
            AikuButton("Remove locations", kind: .danger) {
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
    private func sheetContent(_ sheet: InventorySkoSheet, sko: InventorySko) -> some View {
        switch sheet {
        case .audit:
            InventoryAuditSheet(sko: sko, onCancel: closeSheet) { draft in
                try store.audit(sko.reference, draft: draft)
            } onDone: { message in
                finish(message, undoable: true)
            }
        case .move:
            InventoryMoveSheet(sko: sko, onCancel: closeSheet) { from, to, quantity, reason in
                try store.move(sko.reference, from: from, to: to, quantity: quantity, reason: reason)
            } onDone: { message in
                finish(message, undoable: true)
            }
        case .consume:
            InventoryConsumeSheet(sko: sko, onCancel: closeSheet) { location, quantity, note in
                try store.consume(sko.reference, location: location, quantity: quantity, note: note)
            } onDone: { message in
                finish(message, undoable: true)
            }
        case .addLocation:
            InventoryAddLocationSheet(
                sko: sko,
                options: { search in store.availableLocations(for: sko, search: search) },
                onCancel: closeSheet
            ) { code, type in
                try store.addLocation(sko.reference, location: code, type: type)
            } onDone: { message in
                finish(message, undoable: true)
            }
        case .removeLocations:
            InventoryRemoveLocationsSheet(sko: sko, onCancel: closeSheet) { codes in
                try store.removeLocations(sko.reference, codes: codes)
            } onDone: { message in
                finish(message, undoable: true)
            }
        }
    }

    private func closeSheet() {
        activeSheet = nil
    }

    private func finish(_ message: String, undoable: Bool) {
        activeSheet = nil
        if undoable {
            toasts.show(message, actionTitle: "Undo") {
                store.undo()
                toasts.show("Undone", tone: .info)
            }
        } else {
            toasts.show(message)
        }
    }
}
