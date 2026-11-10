import SwiftUI

struct InventoryMoveSheet: View {
    let sko: InventorySko
    let onCancel: () -> Void
    let onSave: (String, String, Int, InventoryMoveReason) throws -> String
    let onDone: (String) -> Void
    @State private var source: String
    @State private var destination: String
    @State private var quantity: Int
    @State private var reason: InventoryMoveReason = .warehouseTransfer
    @State private var error: String?

    init(
        sko: InventorySko,
        onCancel: @escaping () -> Void,
        onSave: @escaping (String, String, Int, InventoryMoveReason) throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
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
                RadioCard("From \(location.code)", subtitle: "\(location.quantity) in location", isSelected: location.code == source) {
                    selectSource(location)
                }
            }
            SheetSectionLabel("To")
            ForEach(sko.locations.filter { $0.code != source }) { location in
                RadioCard("To \(location.code)", subtitle: "\(location.quantity) in location", isSelected: location.code == destination) {
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
            SheetSectionLabel("Reason")
            ForEach(InventoryMoveReason.allCases, id: \.self) { option in
                RadioCard(option.title, isSelected: option == reason) {
                    reason = option
                }
            }
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton("Move \(quantity)") {
                save()
            }
            .disabled(!canSave)
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
        do {
            onDone(try onSave(source, destination, quantity, reason))
        } catch let failure as InventoryStockError {
            error = failure.message
        } catch {
            self.error = "Couldn’t move the stock."
        }
    }
}
