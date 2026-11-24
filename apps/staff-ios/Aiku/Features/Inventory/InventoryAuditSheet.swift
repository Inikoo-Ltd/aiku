import SwiftUI

struct InventoryAuditSheet: View {
    let sko: InventorySko
    var onlyLocation: String?
    let reasonChoices: (Int) -> [InventoryReasonChoice]
    let reasonsError: String?
    let onCancel: () -> Void
    let onSave: ([String: Int], [String: String]) async throws -> String
    let onDone: (String) -> Void
    @State private var counts: [String: Int]
    @State private var reasons: [String: String] = [:]
    @State private var error: String?
    @State private var isSaving = false
    @State private var isConfirming = false

    init(
        sko: InventorySko,
        onlyLocation: String? = nil,
        reasonChoices: @escaping (Int) -> [InventoryReasonChoice],
        reasonsError: String? = nil,
        onCancel: @escaping () -> Void,
        onSave: @escaping ([String: Int], [String: String]) async throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.onlyLocation = onlyLocation
        self.reasonChoices = reasonChoices
        self.reasonsError = reasonsError
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        _counts = State(initialValue: Dictionary(uniqueKeysWithValues: sko.locations.map { ($0.code, max(0, $0.quantity)) }))
    }

    var body: some View {
        SheetScaffold(title: L10n.format("Audit stock · %@", sko.reference), subtitle: subtitle) {
            Card {
                ForEach(Array(auditedLocations.enumerated()), id: \.element.id) { index, location in
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
                askToSave()
            }
            .disabled(!canSave || isSaving)
            .accessibilityIdentifier("inventory.audit.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
        .aikuConfirm(
            "Save this count?",
            isPresented: $isConfirming,
            message: StockConfirmText.auditSummary(sko, counts: countedValues),
            confirmTitle: "Save audit"
        ) {
            save()
        }
    }

    private var subtitle: String {
        if let onlyLocation, auditedLocations.count == 1 {
            return L10n.format("%@ · count %@", sko.name, onlyLocation)
        }
        return L10n.format("%@ · count each location", sko.name)
    }

    private var auditedLocations: [InventorySkoLocation] {
        guard let onlyLocation else { return sko.locations }
        return sko.locations.filter { $0.code == onlyLocation }
    }

    private var countedValues: [String: Int] {
        Dictionary(uniqueKeysWithValues: auditedLocations.map { ($0.code, counted($0)) })
    }

    private func counted(_ location: InventorySkoLocation) -> Int {
        counts[location.code] ?? max(0, location.quantity)
    }

    private func change(_ location: InventorySkoLocation) -> Int {
        counted(location) - location.quantity
    }

    private var changedLocations: [InventorySkoLocation] {
        auditedLocations.filter { change($0) != 0 }
    }

    private func validReason(_ location: InventorySkoLocation) -> String? {
        guard let reason = reasons[location.code], reasonChoices(change(location)).contains(where: { $0.id == reason }) else { return nil }
        return reason
    }

    private var canSave: Bool {
        !auditedLocations.isEmpty && changedLocations.allSatisfy { validReason($0) != nil }
    }

    private func countedText(_ location: InventorySkoLocation) -> String {
        StockConfirmText.countedText(counted(location), was: location.quantity, wasText: location.quantityText, packedIn: sko.packedIn)
    }

    private func locationRow(_ location: InventorySkoLocation) -> some View {
        let change = change(location)
        return VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 3) {
                    Text(location.code)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(L10n.format("was %@ · counted %@", location.quantityText, countedText(location)))
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
                .accessibilityLabel(L10n.format("Counted in %@", location.code))
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
    }

    private func reasonSection(_ location: InventorySkoLocation) -> some View {
        let change = change(location)
        return VStack(alignment: .leading, spacing: 8) {
            SheetSectionLabel(change < 0 ? L10n.format("Reason for %@ (decrease)", location.code) : L10n.format("Reason for %@ (increase)", location.code))
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

    private func askToSave() {
        if let missing = changedLocations.first(where: { validReason($0) == nil }) {
            error = InventoryStockError.missingReason(missing.code).message
            return
        }
        isConfirming = true
    }

    private func save() {
        let audited = countedValues
        let chosen = reasons.filter { audited[$0.key] != nil }
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                onDone(try await onSave(audited, chosen))
            } catch {
                self.error = InventoryLive.message(for: error)
            }
        }
    }
}
