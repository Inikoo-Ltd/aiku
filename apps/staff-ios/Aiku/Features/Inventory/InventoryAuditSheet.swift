import SwiftUI

struct InventoryAuditSheet: View {
    let sko: InventorySko
    let onCancel: () -> Void
    let onSave: (InventoryAuditDraft) throws -> String
    let onDone: (String) -> Void
    @State private var draft: InventoryAuditDraft
    @State private var error: String?

    init(
        sko: InventorySko,
        onCancel: @escaping () -> Void,
        onSave: @escaping (InventoryAuditDraft) throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        _draft = State(initialValue: InventoryAuditDraft(sko: sko))
    }

    var body: some View {
        SheetScaffold(title: "Audit stock · \(sko.reference)", subtitle: "\(sko.name) · count each location") {
            Card {
                ForEach(Array(sko.locations.enumerated()), id: \.element.id) { index, location in
                    if index > 0 {
                        RowDivider()
                    }
                    locationRow(location)
                }
            }
            ForEach(draft.changedLocations(in: sko)) { location in
                reasonSection(location)
            }
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton("Save audit") {
                save()
            }
            .disabled(!draft.canSave(sko))
            .accessibilityIdentifier("inventory.audit.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private func locationRow(_ location: InventorySkoLocation) -> some View {
        let change = draft.change(for: location)
        return VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 3) {
                    Text(location.code)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text("was \(location.quantity) · counted \(draft.counted(location))")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                if change == 0 {
                    StatusChip("no change", tone: .success)
                } else {
                    StatusChip(InventoryStockMovement.signed(change), tone: change < 0 ? .warn : .success)
                }
            }
            QuantityStepper(value: countBinding(location), range: 0...99999)
                .accessibilityLabel("Counted in \(location.code)")
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
    }

    private func reasonSection(_ location: InventorySkoLocation) -> some View {
        let change = draft.change(for: location)
        return VStack(alignment: .leading, spacing: 8) {
            SheetSectionLabel("Reason for \(location.code) (\(change < 0 ? "decrease" : "increase"))")
            ForEach(InventoryAuditReason.reasons(forChange: change), id: \.self) { reason in
                RadioCard(reason.title, isSelected: draft.validReason(for: location) == reason) {
                    draft.reasons[location.code] = reason
                    error = nil
                }
            }
        }
    }

    private func countBinding(_ location: InventorySkoLocation) -> Binding<Int> {
        Binding {
            draft.counted(location)
        } set: { newValue in
            draft.counts[location.code] = newValue
            error = nil
        }
    }

    private func save() {
        do {
            onDone(try onSave(draft))
        } catch let failure as InventoryStockError {
            error = failure.message
        } catch {
            self.error = "Couldn’t save the audit."
        }
    }
}
