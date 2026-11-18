import SwiftUI

struct InventoryAuditSheet: View {
    let sko: InventorySko
    let reasonChoices: (Int) -> [InventoryReasonChoice]
    let reasonsError: String?
    let onCancel: () -> Void
    let onSave: ([String: Int], [String: String]) async throws -> String
    let onDone: (String) -> Void
    @State private var counts: [String: Int]
    @State private var reasons: [String: String] = [:]
    @State private var error: String?
    @State private var isSaving = false

    init(
        sko: InventorySko,
        reasonChoices: @escaping (Int) -> [InventoryReasonChoice],
        reasonsError: String? = nil,
        onCancel: @escaping () -> Void,
        onSave: @escaping ([String: Int], [String: String]) async throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.reasonChoices = reasonChoices
        self.reasonsError = reasonsError
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        _counts = State(initialValue: Dictionary(uniqueKeysWithValues: sko.locations.map { ($0.code, max(0, $0.quantity)) }))
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
            ForEach(changedLocations) { location in
                reasonSection(location)
            }
            if let reasonsError, !changedLocations.isEmpty {
                FieldError(reasonsError)
            }
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(isSaving ? "Saving…" : "Save audit", look: AikuButtonLook(.primary, icon: .check)) {
                save()
            }
            .disabled(!canSave || isSaving)
            .accessibilityIdentifier("inventory.audit.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private func counted(_ location: InventorySkoLocation) -> Int {
        counts[location.code] ?? max(0, location.quantity)
    }

    private func change(_ location: InventorySkoLocation) -> Int {
        counted(location) - location.quantity
    }

    private var changedLocations: [InventorySkoLocation] {
        sko.locations.filter { change($0) != 0 }
    }

    private func validReason(_ location: InventorySkoLocation) -> String? {
        guard let reason = reasons[location.code], reasonChoices(change(location)).contains(where: { $0.id == reason }) else { return nil }
        return reason
    }

    private var canSave: Bool {
        !sko.locations.isEmpty && changedLocations.allSatisfy { validReason($0) != nil }
    }

    private func locationRow(_ location: InventorySkoLocation) -> some View {
        let change = change(location)
        return VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 3) {
                    Text(location.code)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text("was \(location.quantityText) · counted \(counted(location))")
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
        let change = change(location)
        return VStack(alignment: .leading, spacing: 8) {
            SheetSectionLabel("Reason for \(location.code) (\(change < 0 ? "decrease" : "increase"))")
            ForEach(reasonChoices(change)) { reason in
                RadioCard(reason.title, isSelected: validReason(location) == reason.id) {
                    reasons[location.code] = reason.id
                    error = nil
                }
            }
        }
    }

    private func countBinding(_ location: InventorySkoLocation) -> Binding<Int> {
        Binding {
            counted(location)
        } set: { newValue in
            counts[location.code] = newValue
            error = nil
        }
    }

    private func save() {
        if let missing = changedLocations.first(where: { validReason($0) == nil }) {
            error = InventoryStockError.missingReason(missing.code).message
            return
        }
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                onDone(try await onSave(counts, reasons))
            } catch {
                self.error = InventoryLive.message(for: error)
            }
        }
    }
}
