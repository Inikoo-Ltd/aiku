import SwiftUI

struct InventoryConsumeSheet: View {
    let sko: InventorySko
    let onCancel: () -> Void
    let onSave: (String, Double, String) async throws -> String
    let onDone: (String) -> Void
    @State private var location: String
    @State private var quantity: Double
    @State private var note = ""
    @State private var error: String?
    @State private var isSaving = false

    init(
        sko: InventorySko,
        onCancel: @escaping () -> Void,
        onSave: @escaping (String, Double, String) async throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        let start = sko.locations.first { $0.quantity > 0 } ?? sko.locations.first
        _location = State(initialValue: start?.code ?? "")
        _quantity = State(initialValue: min(1, max(0, start?.quantity ?? 0)))
    }

    var body: some View {
        SheetScaffold(
            title: L10n.format("Internal consumption · %@", sko.reference),
            subtitle: "Stock used inside the company (showroom, samples…)"
        ) {
            ForEach(sko.locations.filter { $0.quantity > 0 }) { option in
                RadioCard(option.code, subtitle: L10n.format("%@ in location", option.quantityText), isSelected: option.code == location) {
                    location = option.code
                    quantity = min(quantity, max(0, option.quantity))
                    error = nil
                }
            }
            InventoryQuantityField(title: "Quantity", value: $quantity, range: 0...max(0, available), packedIn: sko.packedIn)
            AikuTextField("Note", text: $note, placeholder: "Showroom display")
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(isSaving ? "Recording…" : "Record consumption", look: AikuButtonLook(.caution, icon: .sko)) {
                save()
            }
            .disabled(location.isEmpty || quantity <= 0 || quantity > available || isSaving)
            .accessibilityIdentifier("inventory.consume.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private var available: Double {
        sko.location(location)?.quantity ?? 0
    }

    private func save() {
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                onDone(try await onSave(location, quantity, note))
            } catch {
                self.error = InventoryLive.message(for: error)
            }
        }
    }
}
