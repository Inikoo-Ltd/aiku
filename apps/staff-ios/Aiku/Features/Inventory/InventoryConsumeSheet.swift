import SwiftUI

struct InventoryConsumeSheet: View {
    let sko: InventorySko
    let onCancel: () -> Void
    let onSave: (String, Int, String) async throws -> String
    let onDone: (String) -> Void
    @State private var location: String
    @State private var quantity = 1
    @State private var note = ""
    @State private var error: String?
    @State private var isSaving = false

    init(
        sko: InventorySko,
        onCancel: @escaping () -> Void,
        onSave: @escaping (String, Int, String) async throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        _location = State(initialValue: (sko.locations.first { $0.quantity > 0 } ?? sko.locations.first)?.code ?? "")
    }

    var body: some View {
        SheetScaffold(
            title: "Internal consumption · \(sko.reference)",
            subtitle: "Stock used inside the company (showroom, samples…)"
        ) {
            ForEach(sko.locations.filter { $0.quantity > 0 }) { option in
                RadioCard(option.code, subtitle: "\(option.quantityText) in location", isSelected: option.code == location) {
                    location = option.code
                    quantity = min(quantity, max(1, option.quantity))
                    error = nil
                }
            }
            InventoryQuantityField(title: "Quantity", value: $quantity, range: 1...max(1, available))
            AikuTextField("Note", text: $note, placeholder: "Showroom display")
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(isSaving ? "Recording…" : "Record consumption", look: AikuButtonLook(.caution, icon: .sko)) {
                save()
            }
            .disabled(location.isEmpty || quantity > available || isSaving)
            .accessibilityIdentifier("inventory.consume.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private var available: Int {
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
