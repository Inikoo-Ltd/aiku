import SwiftUI

struct InventoryMoveSheet: View {
    let sko: InventorySko
    var showsReasons = true
    let onCancel: () -> Void
    let onSave: (String, String, Int, InventoryMoveReason) async throws -> String
    let onDone: (String) -> Void
    @State private var source: String
    @State private var destination: String
    @State private var quantity: Int
    @State private var reason: InventoryMoveReason = .warehouseTransfer
    @State private var error: String?
    @State private var isSaving = false

    init(
        sko: InventorySko,
        showsReasons: Bool = true,
        onCancel: @escaping () -> Void,
        onSave: @escaping (String, String, Int, InventoryMoveReason) async throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.showsReasons = showsReasons
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        let from = sko.locations.first { $0.quantity > 0 } ?? sko.locations.first
        let to = sko.locations.first { $0.code != from?.code && $0.type == .storing }
            ?? sko.locations.first { $0.code != from?.code }
        _source = State(initialValue: from?.code ?? "")
        _destination = State(initialValue: to?.code ?? "")
        _quantity = State(initialValue: min(12, max(1, from?.quantity ?? 1)))
    }

    var body: some View {
        SheetScaffold(title: "Move stock · \(sko.reference)", subtitle: "Between two of its locations") {
            SheetSectionLabel("From")
            ForEach(sko.locations.filter { $0.quantity > 0 }) { location in
                RadioCard("From \(location.code)", subtitle: "\(location.quantityText) in location", isSelected: location.code == source) {
                    selectSource(location)
                }
            }
            SheetSectionLabel("To")
            ForEach(sko.locations.filter { $0.code != source }) { location in
                RadioCard("To \(location.code)", subtitle: "\(location.quantityText) in location", isSelected: location.code == destination) {
                    destination = location.code
                    error = nil
                }
            }
            InventoryQuantityField(title: "Quantity", value: $quantity, range: 1...max(1, available))
            if available > 0, quantity != available {
                LinkButton("Move all \(available)") {
                    quantity = available
                }
                .frame(minHeight: 44)
            }
            if showsReasons {
                SheetSectionLabel("Reason")
                ForEach(InventoryMoveReason.allCases, id: \.self) { option in
                    RadioCard(option.title, isSelected: option == reason) {
                        reason = option
                    }
                }
            }
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(isSaving ? "Moving…" : "Move \(quantity)") {
                save()
            }
            .disabled(!canSave || isSaving)
            .accessibilityIdentifier("inventory.move.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private var available: Int {
        sko.location(source)?.quantity ?? 0
    }

    private var canSave: Bool {
        !source.isEmpty && !destination.isEmpty && source != destination && quantity > 0 && quantity <= available
    }

    private func selectSource(_ location: InventorySkoLocation) {
        source = location.code
        if destination == location.code {
            destination = sko.locations.first { $0.code != location.code }?.code ?? ""
        }
        quantity = min(quantity, max(1, location.quantity))
        error = nil
    }

    private func save() {
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                onDone(try await onSave(source, destination, quantity, reason))
            } catch {
                self.error = InventoryLive.message(for: error)
            }
        }
    }
}
